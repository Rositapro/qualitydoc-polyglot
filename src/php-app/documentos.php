<?php
// Dashboard Principal: Gestión Documental Multi-tenencia (Listado y Filtros)

require_once __DIR__ . '/includes/header.php';

// El $pdo y las variables de sesión ($empresa_id, etc.) se heredan de header.php

// 1. Obtener estadísticas de la empresa actual
try {
    // Total de documentos
    $stmt = $pdo->prepare("SELECT COUNT(*) AS total FROM documento WHERE empresaid = :empresa");
    $stmt->execute(['empresa' => $empresa_id]);
    $stat_total = $stmt->fetch()['total'];

    // Documentos vigentes
    $stmt = $pdo->prepare("SELECT COUNT(*) AS total FROM documento WHERE empresaid = :empresa AND estado = 'vigente'");
    $stmt->execute(['empresa' => $empresa_id]);
    $stat_vigentes = $stmt->fetch()['total'];

    // Documentos obsoletos
    $stmt = $pdo->prepare("SELECT COUNT(*) AS total FROM documento WHERE empresaid = :empresa AND estado = 'obsoleto'");
    $stmt->execute(['empresa' => $empresa_id]);
    $stat_obsoletos = $stmt->fetch()['total'];

    // Total sugerencias
    $stmt = $pdo->prepare("SELECT COUNT(*) AS total FROM sugerencias WHERE empresaid = :empresa");
    $stmt->execute(['empresa' => $empresa_id]);
    $stat_sugerencias = $stmt->fetch()['total'];

    // Obtener normas ISO distintas de los documentos de la empresa actual para el filtro
    $stmt = $pdo->prepare("SELECT DISTINCT idiso FROM documento WHERE empresaid = :empresa ORDER BY idiso ASC");
    $stmt->execute(['empresa' => $empresa_id]);
    $lista_isos = $stmt->fetchAll(PDO::FETCH_COLUMN);

} catch (PDOException $e) {
    echo "<div class='alert alert-danger font-sans'>Error al cargar estadísticas: " . htmlspecialchars($e->getMessage()) . "</div>";
    $stat_total = $stat_vigentes = $stat_obsoletos = $stat_sugerencias = 0;
    $lista_isos = [];
}

// 2. Procesar filtros
$filtro_iso = isset($_GET['filtro_iso']) ? trim($_GET['filtro_iso']) : '';
$filtro_estado = isset($_GET['filtro_estado']) ? trim($_GET['filtro_estado']) : '';
$filtro_search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Invocar al microservicio de búsqueda en MongoDB si se ingresó texto
$mongo_doc_ids = [];
$search_via_mongo = false;

if (!empty($filtro_search)) {
    $url = SEARCH_API_URL . "?q=" . urlencode($filtro_search) . "&empresaid=" . intval($empresa_id);
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    // Ignorar certificación SSL en desarrollo local
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $res = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 200 && $res !== false) {
        $json = json_decode($res, true);
        if (isset($json['success']) && $json['success'] === true && isset($json['data'])) {
            $search_via_mongo = true;
            foreach ($json['data'] as $doc) {
                if (isset($doc['metadata']['documentId'])) {
                    $mongo_doc_ids[] = intval($doc['metadata']['documentId']);
                }
            }
        }
    }
}

// 3. Consultar total de documentos que cumplen los filtros (para paginación)
try {
    $sql_count = "SELECT COUNT(DISTINCT codigo) AS total FROM documento WHERE empresaid = :empresa";
    $params = ['empresa' => $empresa_id];

    if (!empty($filtro_iso)) {
        $sql_count .= " AND idiso = :iso";
        $params['iso'] = $filtro_iso;
    }

    if (!empty($filtro_estado)) {
        $sql_count .= " AND estado = :estado";
        $params['estado'] = $filtro_estado;
    }

    if (!empty($filtro_search)) {
        if ($search_via_mongo) {
            if (count($mongo_doc_ids) > 0) {
                $placeholders = [];
                foreach ($mongo_doc_ids as $index => $id) {
                    $key = "mongo_id_count_" . $index;
                    $placeholders[] = ":" . $key;
                    $params[$key] = "QD-" . $id;
                }
                $sql_count .= " AND codigo IN (" . implode(",", $placeholders) . ")";
            } else {
                $sql_count .= " AND 1=0"; // No coincide ningún id en Mongo
            }
        } else {
            // Fallback a SQL relacional tradicional en caso de fallo del microservicio
            $sql_count .= " AND (titulodocumento ILIKE :search OR codigo ILIKE :search)";
            $params['search'] = '%' . $filtro_search . '%';
        }
    }

    $stmt_count = $pdo->prepare($sql_count);
    $stmt_count->execute($params);
    $total_filtrados = $stmt_count->fetch()['total'];

} catch (PDOException $e) {
    echo "<div class='alert alert-danger font-sans'>Error al contar documentos: " . htmlspecialchars($e->getMessage()) . "</div>";
    $total_filtrados = 0;
}

// 4. Configurar paginación
$limite_pag = 10;
$total_paginas = ceil($total_filtrados / $limite_pag);
$pagina_solicitada = isset($_GET['page']) ? intval($_GET['page']) : 1;
if ($pagina_solicitada < 1) $pagina_solicitada = 1;
if ($total_paginas > 0 && $pagina_solicitada > $total_paginas) $pagina_solicitada = $total_paginas;
$offset = ($pagina_solicitada - 1) * $limite_pag;

// 5. Consultar documentos filtrados y paginados (Estricto por empresaid)
try {
    $sql = "SELECT DISTINCT ON (codigo) * FROM documento WHERE empresaid = :empresa";
    $params_select = ['empresa' => $empresa_id];
    
    if (!empty($filtro_iso)) {
        $sql .= " AND idiso = :iso";
        $params_select['iso'] = $filtro_iso;
    }

    if (!empty($filtro_estado)) {
        $sql .= " AND estado = :estado";
        $params_select['estado'] = $filtro_estado;
    }

    if (!empty($filtro_search)) {
        if ($search_via_mongo) {
            if (count($mongo_doc_ids) > 0) {
                $placeholders = [];
                foreach ($mongo_doc_ids as $index => $id) {
                    $key = "mongo_id_select_" . $index;
                    $placeholders[] = ":" . $key;
                    $params_select[$key] = "QD-" . $id;
                }
                $sql .= " AND codigo IN (" . implode(",", $placeholders) . ")";
            } else {
                $sql .= " AND 1=0";
            }
        } else {
            $sql .= " AND (titulodocumento ILIKE :search OR codigo ILIKE :search)";
            $params_select['search'] = '%' . $filtro_search . '%';
        }
    }

    // Ordenar por código y versión de forma descendente para ver la última versión primero
    $sql .= " ORDER BY codigo ASC, iddocumento DESC LIMIT :limit OFFSET :offset";
    
    // Remplazar marcadores enteros en la consulta para evitar problemas de tipos en Postgres
    $sql = str_replace(':limit', (int)$limite_pag, $sql);
    $sql = str_replace(':offset', (int)$offset, $sql);

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params_select);
    $documentos = $stmt->fetchAll();

} catch (PDOException $e) {
    echo "<div class='alert alert-danger font-sans'>Error al cargar documentos: " . htmlspecialchars($e->getMessage()) . "</div>";
    $documentos = [];
}
?>
<div class="row mb-4 font-sans">
    <div class="col-12 col-md-6 col-lg-3">
        <div class="card-elegant stat-card p-3">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="text-muted mb-1 text-uppercase fw-semibold" style="font-size: 0.8rem;">Total Documentos</h6>
                    <div class="stat-val"><?php echo $stat_total; ?></div>
                </div>
                <div class="fs-1 text-muted opacity-50"><i class="bi bi-file-earmark-text"></i></div>
            </div>
        </div>
    </div>
    
    <div class="col-12 col-md-6 col-lg-3">
        <div class="card-elegant stat-card p-3" style="border-left-color: var(--color-vigente-text) !important;">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="text-muted mb-1 text-uppercase fw-semibold" style="font-size: 0.8rem;">Vigentes</h6>
                    <div class="stat-val text-success"><?php echo $stat_vigentes; ?></div>
                </div>
                <div class="fs-1 text-success opacity-50"><i class="bi bi-check-circle-fill"></i></div>
            </div>
        </div>
    </div>
    
    <div class="col-12 col-md-6 col-lg-3">
        <div class="card-elegant stat-card p-3" style="border-left-color: var(--color-obsoleto-text) !important;">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="text-muted mb-1 text-uppercase fw-semibold" style="font-size: 0.8rem;">Obsoletos</h6>
                    <div class="stat-val text-danger"><?php echo $stat_obsoletos; ?></div>
                </div>
                <div class="fs-1 text-danger opacity-50"><i class="bi bi-x-circle-fill"></i></div>
            </div>
        </div>
    </div>

    <div class="col-12 col-md-6 col-lg-3">
        <div class="card-elegant stat-card p-3" style="border-left-color: #805ad5 !important;">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="text-muted mb-1 text-uppercase fw-semibold" style="font-size: 0.8rem;">Sugerencias</h6>
                    <div class="stat-val" style="color: #805ad5;"><?php echo $stat_sugerencias; ?></div>
                </div>
                <div class="fs-1 text-primary opacity-50" style="color: #805ad5 !important;"><i class="bi bi-chat-right-quote-fill"></i></div>
            </div>
        </div>
    </div>
</div>

<!-- Panel de Filtros -->
<div class="card-elegant mb-4 font-sans">
    <div class="card-elegant-header">
        <h5 class="m-0" style="font-size: 1.1rem; font-weight: 600;"><i class="bi bi-funnel-fill me-2 text-warning"></i>Filtros de Búsqueda</h5>
    </div>
    <div class="card-elegant-body">
        <form method="GET" action="documentos.php" class="row g-3">
            <div class="col-12 col-md-3">
                <label for="search" class="form-label form-label-elegant">Buscar por título o código</label>
                <input type="text" name="search" id="search" class="form-control form-control-elegant" placeholder="Ej. Manual, PROC..." value="<?php echo htmlspecialchars($filtro_search); ?>">
            </div>
            
            <div class="col-12 col-md-3">
                <label for="filtro_iso" class="form-label form-label-elegant">Norma ISO</label>
                <select name="filtro_iso" id="filtro_iso" class="form-select form-control-elegant">
                    <option value="">-- Todas las Normas --</option>
                    <?php foreach ($lista_isos as $iso): ?>
                        <option value="<?php echo htmlspecialchars($iso); ?>" <?php echo ($filtro_iso === $iso) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($iso); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-12 col-md-3">
                <label for="filtro_estado" class="form-label form-label-elegant">Estado</label>
                <select name="filtro_estado" id="filtro_estado" class="form-select form-control-elegant">
                    <option value="">-- Todos los Estados --</option>
                    <option value="vigente" <?php echo ($filtro_estado === 'vigente') ? 'selected' : ''; ?>>Vigente</option>
                    <option value="obsoleto" <?php echo ($filtro_estado === 'obsoleto') ? 'selected' : ''; ?>>Obsoleto</option>
                </select>
            </div>
            
            <div class="col-12 col-md-3 d-flex align-items-end gap-2">
                <button type="submit" class="btn btn-elegant-primary flex-grow-1">
                    <i class="bi bi-search me-1"></i> Filtrar
                </button>
                <a href="documentos.php" class="btn btn-elegant-outline">
                    <i class="bi bi-arrow-counterclockwise"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Listado de Documentos -->
<div class="card-elegant">
    <div class="card-elegant-header d-flex justify-content-between align-items-center">
        <h5 class="m-0"><i class="bi bi-file-earmark-pdf-fill me-2 text-primary"></i>Documentos Registrados</h5>
        <span class="badge badge-iso">Total Filtrados: <?php echo count($documentos); ?></span>
    </div>
    
    <div class="table-responsive">
        <table class="table-elegant">
            <thead>
                <tr>
                    <th style="width: 80px;">ID</th>
                    <th>Título del Documento</th>
                    <th style="width: 120px;">Código</th>
                    <th style="width: 100px;">Versión</th>
                    <th style="width: 140px;">Norma ISO</th>
                    <th style="width: 120px;">Estado</th>
                    <th style="width: 150px;" class="text-center">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($documentos) > 0): ?>
                    <?php foreach ($documentos as $doc): ?>
                        <?php
                            // Consultar el historial de versiones para este documento
                            try {
                                $stmt_versions = $pdo->prepare("SELECT * FROM documento WHERE empresaid = :empresa AND codigo = :codigo ORDER BY iddocumento DESC");
                                $stmt_versions->execute(['empresa' => $empresa_id, 'codigo' => $doc['codigo']]);
                                $historial_versiones = $stmt_versions->fetchAll();
                            } catch (PDOException $e) {
                                $historial_versiones = [];
                            }
                        ?>
                        <tr>
                            <td class="font-sans text-muted">#<?php echo $doc['iddocumento']; ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($doc['titulodocumento']); ?></strong>
                                
                                <!-- Collapsible Version History -->
                                <div class="mt-1">
                                    <button class="btn btn-sm btn-link p-0 text-decoration-none text-secondary d-flex align-items-center gap-1" type="button" data-bs-toggle="collapse" data-bs-target="#versions-<?php echo $doc['iddocumento']; ?>" aria-expanded="false" aria-controls="versions-<?php echo $doc['iddocumento']; ?>" style="font-size: 0.8rem; color: #805ad5 !important;">
                                        <i class="bi bi-clock-history"></i> Ver Historial de Versiones (<?php echo count($historial_versiones); ?>)
                                    </button>
                                    <div class="collapse mt-2" id="versions-<?php echo $doc['iddocumento']; ?>">
                                        <ul class="list-group list-group-flush border rounded-3 p-2 bg-light shadow-sm" style="font-size: 0.85rem; max-width: 350px;">
                                            <?php if (count($historial_versiones) > 0): ?>
                                                <?php foreach ($historial_versiones as $ver): ?>
                                                    <li class="list-group-item bg-transparent d-flex justify-content-between align-items-center py-2 px-1 border-light">
                                                        <div>
                                                            <span class="fw-bold text-dark">v<?php echo htmlspecialchars($ver['version']); ?></span>
                                                        </div>
                                                        <div class="d-flex align-items-center gap-2">
                                                            <?php if ($ver['estado'] === 'vigente'): ?>
                                                                <span class="badge badge-status badge-vigente py-1 px-2" style="font-size: 0.75rem;">Vigente</span>
                                                                <?php if (!empty($ver['rutaarchivo'])): ?>
                                                                    <a href="descargar.php?id=<?php echo $ver['iddocumento']; ?>" class="btn btn-sm btn-outline-success p-1 py-0 d-flex align-items-center justify-content-center" style="font-size: 0.75rem;" title="Descargar Versión Vigente">
                                                                        <i class="bi bi-download"></i>
                                                                    </a>
                                                                <?php endif; ?>
                                                            <?php else: ?>
                                                                <span class="badge badge-status badge-obsoleto py-1 px-2" style="font-size: 0.75rem;">Obsoleto</span>
                                                                <span class="text-muted" title="Las versiones obsoletas no se pueden descargar (Solo Auditoría)">
                                                                    <i class="bi bi-lock-fill"></i>
                                                                </span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </li>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <li class="list-group-item bg-transparent py-2 text-muted fst-italic">Sin versiones registradas.</li>
                                            <?php endif; ?>
                                        </ul>
                                    </div>
                                </div>
                            </td>
                            <td class="font-sans"><code><?php echo htmlspecialchars($doc['codigo']); ?></code></td>
                            <td class="font-sans text-center"><?php echo htmlspecialchars($doc['version']); ?></td>
                            <td>
                                <span class="badge badge-status badge-iso"><?php echo htmlspecialchars($doc['idiso']); ?></span>
                            </td>
                            <td>
                                <?php if ($doc['estado'] === 'vigente'): ?>
                                    <span class="badge badge-status badge-vigente">
                                        <i class="bi bi-patch-check-fill me-1"></i> Vigente
                                    </span>
                                <?php else: ?>
                                    <span class="badge badge-status badge-obsoleto">
                                        <i class="bi bi-slash-circle me-1"></i> Obsoleto
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="d-flex justify-content-center gap-2">
                                    <!-- Ojito para Visualizar -->
                                    <a href="visor.php?id=<?php echo $doc['iddocumento']; ?>" 
                                       class="btn-action-table" 
                                       title="Visualizar documento"
                                       id="btn-ver-<?php echo $doc['iddocumento']; ?>">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    
                                    <!-- Descargar -->
                                    <a href="descargar.php?id=<?php echo $doc['iddocumento']; ?>" 
                                       class="btn-action-table download-btn" 
                                       title="Descargar archivo"
                                       id="btn-descarga-<?php echo $doc['iddocumento']; ?>">
                                        <i class="bi bi-download"></i>
                                    </a>
                                    
                                    <!-- Sugerencia -->
                                    <a href="sugerencia.php?id=<?php echo $doc['iddocumento']; ?>" 
                                       class="btn-action-table" 
                                       title="Emitir sugerencia"
                                       id="btn-sugerencia-<?php echo $doc['iddocumento']; ?>"
                                       style="border-color: #805ad5; color: #805ad5;">
                                        <i class="bi bi-chat-left-text"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="text-center py-5 font-sans text-muted">
                            <i class="bi bi-folder-x fs-1 d-block mb-3"></i>
                            No se encontraron documentos registrados para esta empresa o con los filtros seleccionados.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($total_paginas > 1): ?>
    <nav aria-label="Navegación de páginas" class="mt-4 font-sans">
        <ul class="pagination justify-content-center gap-1">
            <!-- Anterior -->
            <li class="page-item <?php echo ($pagina_solicitada <= 1) ? 'disabled' : ''; ?>">
                <a class="page-link rounded" href="documentos.php?page=<?php echo $pagina_solicitada - 1; ?>&filtro_iso=<?php echo urlencode($filtro_iso); ?>&filtro_estado=<?php echo urlencode($filtro_estado); ?>&search=<?php echo urlencode($filtro_search); ?>" style="color: var(--color-primary); border-color: var(--border-color); background-color: #fff;">
                    <i class="bi bi-chevron-left"></i>
                </a>
            </li>
            
            <!-- Páginas -->
            <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                <li class="page-item <?php echo ($pagina_solicitada == $i) ? 'active' : ''; ?>">
                    <a class="page-link rounded fw-bold" href="documentos.php?page=<?php echo $i; ?>&filtro_iso=<?php echo urlencode($filtro_iso); ?>&filtro_estado=<?php echo urlencode($filtro_estado); ?>&search=<?php echo urlencode($filtro_search); ?>" 
                       style="<?php echo ($pagina_solicitada == $i) ? 'background-color: var(--color-primary) !important; border-color: var(--color-primary) !important; color: white !important;' : 'color: var(--color-primary); border-color: var(--border-color); background-color: #fff;'; ?>">
                        <?php echo $i; ?>
                    </a>
                </li>
            <?php endfor; ?>
            
            <!-- Siguiente -->
            <li class="page-item <?php echo ($pagina_solicitada >= $total_paginas) ? 'disabled' : ''; ?>">
                <a class="page-link rounded" href="documentos.php?page=<?php echo $pagina_solicitada + 1; ?>&filtro_iso=<?php echo urlencode($filtro_iso); ?>&filtro_estado=<?php echo urlencode($filtro_estado); ?>&search=<?php echo urlencode($filtro_search); ?>" style="color: var(--color-primary); border-color: var(--border-color); background-color: #fff;">
                    <i class="bi bi-chevron-right"></i>
                </a>
            </li>
        </ul>
    </nav>
<?php endif; ?>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
