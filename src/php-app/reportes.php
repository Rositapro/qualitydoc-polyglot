<?php
// Módulo de Reportes Operativos - SGD/QualityDoc

require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/db.php';

$empresa_id = $_SESSION['empresaid'] ?? 0;

$rol_usuario = $_SESSION['rol'] ?? 'Colaborador';
$usuario_id = $_SESSION['idusuario'] ?? '';
$is_superadmin = (strcasecmp($rol_usuario, 'superadmin') === 0);
$is_admin = (strcasecmp($rol_usuario, 'admin') === 0 || strcasecmp($rol_usuario, 'administrator') === 0);

// 1. Procesar rango de fechas por defecto (últimos 30 días) y filtros de acción
$fecha_inicio = isset($_GET['fecha_inicio']) ? trim($_GET['fecha_inicio']) : date('Y-m-d', strtotime('-30 days'));
$fecha_fin = isset($_GET['fecha_fin']) ? trim($_GET['fecha_fin']) : date('Y-m-d');
$filtro_accion = isset($_GET['filtro_accion']) ? trim($_GET['filtro_accion']) : '';

// Ajustar las fechas para incluir todo el día en las consultas (timestamp)
$query_inicio = $fecha_inicio . " 00:00:00";
$query_fin = $fecha_fin . " 23:59:59";

// 2. LÓGICA DE EXPORTACIÓN A EXCEL/CSV (Antes de pintar cualquier HTML)
if (isset($_GET['export_csv']) && $_GET['export_csv'] == 1) {
    try {
        $join_user_csv = "l.idusuario = u.idusuario";
        $join_doc_csv = "l.iddocumento = d.iddocumento";
        $where_csv = ["l.fecha BETWEEN :inicio AND :fin"];
        $params_csv = [
            'inicio' => $query_inicio,
            'fin' => $query_fin
        ];

        if (!$is_superadmin) {
            $join_user_csv .= " AND u.empresaid = :empresa";
            $join_doc_csv .= " AND d.empresaid = :empresa";
            $where_csv[] = "l.empresaid = :empresa";
            $params_csv['empresa'] = $empresa_id;
            
            if (!$is_admin) {
                $where_csv[] = "l.idusuario = :idusuario";
                $params_csv['idusuario'] = $usuario_id;
            }
        }

        $sql_csv = "
            SELECT 
                l.idlog, 
                u.nombreusuario, 
                u.rol, 
                l.accion, 
                d.codigo, 
                d.titulodocumento, 
                d.idiso, 
                l.fecha,
                l.empresaid
            FROM logsconsultas l
            INNER JOIN usuarios u ON {$join_user_csv}
            INNER JOIN documento d ON {$join_doc_csv}
            WHERE " . implode(" AND ", $where_csv) . "
        ";
        
        if (!empty($filtro_accion)) {
            $sql_csv .= " AND l.accion = :accion";
            $params_csv['accion'] = $filtro_accion;
        }
        
        $sql_csv .= " ORDER BY l.fecha DESC";
        
        $stmt_csv = $pdo->prepare($sql_csv);
        $stmt_csv->execute($params_csv);
        $csv_logs = $stmt_csv->fetchAll();

        // Configurar cabeceras de descarga de CSV
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="reporte_operativo_empresa_' . ($is_superadmin ? 'todos' : $empresa_id) . '_' . date('Ymd') . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // UTF-8 BOM para Excel
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Cabeceras de columnas
        $headers = ['Ref Log'];
        if ($is_superadmin) {
            $headers[] = 'ID Empresa';
            $headers[] = 'Empresa';
        }
        $headers = array_merge($headers, ['Usuario', 'Rol', 'Acción', 'Código Documento', 'Título Documento', 'Norma ISO', 'Fecha y Hora']);
        fputcsv($output, $headers);
        
        foreach ($csv_logs as $log) {
            $row = ['#' . $log['idlog']];
            if ($is_superadmin) {
                $row[] = $log['empresaid'];
                $row[] = $log['empresaid'] == 2 ? 'KittyBeauty' : ($log['empresaid'] == 1 ? 'Empresa Maestra' : 'Tenant Corporativo');
            }
            $row = array_merge($row, [
                $log['nombreusuario'],
                ucfirst($log['rol']),
                strtoupper($log['accion']),
                $log['codigo'],
                $log['titulodocumento'],
                $log['idiso'],
                $log['fecha']
            ]);
            fputcsv($output, $row);
        }
        fclose($output);
        exit();

    } catch (PDOException $e) {
        die("Error al generar la exportación: " . $e->getMessage());
    }
}

// Cargar cabecera normal
require_once __DIR__ . '/includes/header.php';

// 3. Consultas de base de datos para las métricas del Reporte
try {
    // A. Conteo por Tipo de Acción
    $where_actions = ["fecha BETWEEN :inicio AND :fin"];
    $params_actions = [
        'inicio' => $query_inicio,
        'fin' => $query_fin
    ];
    if (!$is_superadmin) {
        $where_actions[] = "empresaid = :empresa";
        $params_actions['empresa'] = $empresa_id;
        
        if (!$is_admin) {
            $where_actions[] = "idusuario = :idusuario";
            $params_actions['idusuario'] = $usuario_id;
        }
    }
    
    $sql_actions = "
        SELECT accion, COUNT(*) as total 
        FROM logsconsultas 
        WHERE " . implode(" AND ", $where_actions) . "
        GROUP BY accion
    ";
    
    $stmt_actions = $pdo->prepare($sql_actions);
    $stmt_actions->execute($params_actions);
    $action_counts = $stmt_actions->fetchAll(PDO::FETCH_KEY_PAIR);
    
    $total_operaciones = array_sum($action_counts);
    $total_vistas = $action_counts['visualizacion'] ?? 0;
    $total_descargas = $action_counts['descarga'] ?? 0;
    $total_sugerencias = $action_counts['sugerencia'] ?? 0;

    // B. Actividad por Norma ISO (para gráfica de barras)
    $join_doc_iso = "l.iddocumento = d.iddocumento";
    $where_iso = ["l.fecha BETWEEN :inicio AND :fin"];
    $params_iso = [
        'inicio' => $query_inicio,
        'fin' => $query_fin
    ];
    if (!$is_superadmin) {
        $join_doc_iso .= " AND d.empresaid = :empresa";
        $where_iso[] = "l.empresaid = :empresa";
        $params_iso['empresa'] = $empresa_id;
        
        if (!$is_admin) {
            $where_iso[] = "l.idusuario = :idusuario";
            $params_iso['idusuario'] = $usuario_id;
        }
    }

    $sql_iso = "
        SELECT d.idiso, COUNT(l.idlog) as total 
        FROM logsconsultas l
        INNER JOIN documento d ON {$join_doc_iso}
        WHERE " . implode(" AND ", $where_iso) . "
        GROUP BY d.idiso
        ORDER BY total DESC
    ";

    $stmt_iso = $pdo->prepare($sql_iso);
    $stmt_iso->execute($params_iso);
    $iso_activity = $stmt_iso->fetchAll();

    // C. Top 5 Documentos Más Activos (Ficha Operativa)
    $join_doc_top = "l.iddocumento = d.iddocumento";
    $where_top = ["l.fecha BETWEEN :inicio AND :fin"];
    $params_top = [
        'inicio' => $query_inicio,
        'fin' => $query_fin
    ];
    if (!$is_superadmin) {
        $join_doc_top .= " AND d.empresaid = :empresa";
        $where_top[] = "l.empresaid = :empresa";
        $params_top['empresa'] = $empresa_id;
        
        if (!$is_admin) {
            $where_top[] = "l.idusuario = :idusuario";
            $params_top['idusuario'] = $usuario_id;
        }
    }

    $sql_top = "
        SELECT d.iddocumento, d.titulodocumento, d.codigo, d.idiso, COUNT(l.idlog) as total_operaciones 
        FROM logsconsultas l
        INNER JOIN documento d ON {$join_doc_top}
        WHERE " . implode(" AND ", $where_top) . "
        GROUP BY d.iddocumento, d.titulodocumento, d.codigo, d.idiso
        ORDER BY total_operaciones DESC
        LIMIT 5
    ";

    $stmt_top = $pdo->prepare($sql_top);
    $stmt_top->execute($params_top);
    $top_documentos = $stmt_top->fetchAll();

    // D. Tabla Detallada del Reporte actual
    $join_user_details = "l.idusuario = u.idusuario";
    $join_doc_details = "l.iddocumento = d.iddocumento";
    $where_details = ["l.fecha BETWEEN :inicio AND :fin"];
    $params_details = [
        'inicio' => $query_inicio,
        'fin' => $query_fin
    ];
    if (!$is_superadmin) {
        $join_user_details .= " AND u.empresaid = :empresa";
        $join_doc_details .= " AND d.empresaid = :empresa";
        $where_details[] = "l.empresaid = :empresa";
        $params_details['empresa'] = $empresa_id;
        
        if (!$is_admin) {
            $where_details[] = "l.idusuario = :idusuario";
            $params_details['idusuario'] = $usuario_id;
        }
    }

    $sql_details = "
        SELECT 
            l.idlog, 
            u.nombreusuario, 
            u.rol, 
            l.accion, 
            d.codigo, 
            d.titulodocumento, 
            d.idiso, 
            l.fecha,
            l.empresaid
        FROM logsconsultas l
        INNER JOIN usuarios u ON {$join_user_details}
        INNER JOIN documento d ON {$join_doc_details}
        WHERE " . implode(" AND ", $where_details) . "
    ";
    
    if (!empty($filtro_accion)) {
        $sql_details .= " AND l.accion = :accion";
        $params_details['accion'] = $filtro_accion;
    }

    $sql_details .= " ORDER BY l.fecha DESC";
    
    $stmt_details = $pdo->prepare($sql_details);
    $stmt_details->execute($params_details);
    $report_logs = $stmt_details->fetchAll();

} catch (PDOException $e) {
    echo "<div class='alert alert-danger font-sans'>Error al generar métricas de reportes: " . htmlspecialchars($e->getMessage()) . "</div>";
    $total_operaciones = $total_vistas = $total_descargas = $total_sugerencias = 0;
    $iso_activity = $top_documentos = $report_logs = [];
}
?>

<!-- Estilos específicos para Impresión y Gráficas de Calidad -->
<style>
    /* Estilo de Gráficas de Barra con CSS Puro */
    .bar-chart-container {
        display: flex;
        flex-direction: column;
        gap: 1.25rem;
    }
    .bar-chart-row {
        display: flex;
        flex-direction: column;
        gap: 0.25rem;
    }
    .bar-chart-label-group {
        display: flex;
        justify-content: space-between;
        font-size: 0.85rem;
        font-family: var(--font-sans);
        font-weight: 600;
        color: var(--color-primary);
    }
    .bar-chart-progress-bg {
        background-color: #edeae2;
        border-radius: 50px;
        height: 12px;
        overflow: hidden;
        width: 100%;
        border: 1px solid var(--border-color);
    }
    .bar-chart-progress-fill {
        background: linear-gradient(90deg, var(--color-accent) 0%, var(--color-primary) 100%);
        height: 100%;
        border-radius: 50px;
        transition: width 1s cubic-bezier(0.4, 0, 0.2, 1);
    }

    /* Ocultar sección de impresión oficial de calidad en pantalla */
    .print-report-header {
        display: none;
    }

    /* Reglas de Impresión / PDF */
    @media print {
        .sidebar, .top-header, .card-filters-print, .btn-actions-print, .no-print {
            display: none !important;
        }
        .main-content {
            margin-left: 0 !important;
            padding: 0 !important;
            width: 100% !important;
        }
        body {
            background-color: #fff !important;
            color: #000 !important;
            font-size: 11pt !important;
        }
        .card-elegant {
            border: 1px solid #ccc !important;
            box-shadow: none !important;
            margin-bottom: 2rem !important;
            page-break-inside: avoid;
        }
        .print-report-header {
            display: block !important;
            border-bottom: 2px double #333;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            font-family: var(--font-sans);
        }
        .print-title {
            font-family: var(--font-title);
            font-size: 22pt;
            font-weight: bold;
            text-align: center;
            margin-bottom: 0.5rem;
        }
        .print-meta {
            display: flex;
            justify-content: space-between;
            font-size: 10pt;
            color: #555;
        }
        table {
            page-break-inside: auto;
        }
        tr {
            page-break-inside: avoid;
            page-break-after: auto;
        }
    }
</style>

<!-- Cabecera Oficial del Reporte (Solo Visible al Guardar PDF / Imprimir) -->
<div class="print-report-header font-sans">
    <div class="print-title">QualityDoc - Reporte Operativo</div>
    <div class="text-center text-muted mb-3" style="font-size: 11pt;">Control de Gestión Documental y Calidad</div>
    <div class="print-meta">
        <div><strong>Tenant / Empresa Activa:</strong> <?php echo $is_superadmin ? 'Todas (Consolidado SuperAdmin)' : 'ID #' . htmlspecialchars($empresa_id) . ' (' . htmlspecialchars($_SESSION['empresanombre'] ?? ($empresa_id == 2 ? 'KittyBeauty' : ($empresa_id == 1 ? 'Empresa Maestra' : 'Tenant Corporativo'))) . ')'; ?></div>
        <div><strong>Rango Reportado:</strong> <?php echo date('d/m/Y', strtotime($fecha_inicio)); ?> - <?php echo date('d/m/Y', strtotime($fecha_fin)); ?></div>
        <div><strong>Fecha Emisión:</strong> <?php echo date('d/m/Y H:i'); ?></div>
    </div>
</div>

<!-- Filtros de Reportes (Pantalla) -->
<div class="card-elegant mb-4 font-sans card-filters-print no-print">
    <div class="card-elegant-header d-flex justify-content-between align-items-center">
        <h5 class="m-0" style="font-size: 1.1rem; font-weight: 600;"><i class="bi bi-calendar3 me-2 text-warning"></i>Rango de Fechas del Reporte</h5>
        <span class="badge badge-iso">Filtros Activos</span>
    </div>
    <div class="card-elegant-body">
        <form method="GET" action="reportes.php" class="row g-3">
            <div class="col-12 col-md-3">
                <label for="fecha_inicio" class="form-label form-label-elegant">Fecha Inicio</label>
                <input type="date" name="fecha_inicio" id="fecha_inicio" class="form-control form-control-elegant" value="<?php echo htmlspecialchars($fecha_inicio); ?>" required>
            </div>
            
            <div class="col-12 col-md-3">
                <label for="fecha_fin" class="form-label form-label-elegant">Fecha Fin</label>
                <input type="date" name="fecha_fin" id="fecha_fin" class="form-control form-control-elegant" value="<?php echo htmlspecialchars($fecha_fin); ?>" required>
            </div>

            <div class="col-12 col-md-3">
                <label for="filtro_accion" class="form-label form-label-elegant">Acción (Operación)</label>
                <select name="filtro_accion" id="filtro_accion" class="form-select form-control-elegant">
                    <option value="" <?php echo ($filtro_accion === '') ? 'selected' : ''; ?>>-- Todas las acciones --</option>
                    <option value="visualizacion" <?php echo ($filtro_accion === 'visualizacion') ? 'selected' : ''; ?>>Ver</option>
                    <option value="descarga" <?php echo ($filtro_accion === 'descarga') ? 'selected' : ''; ?>>Descargar</option>
                    <option value="sugerencia" <?php echo ($filtro_accion === 'sugerencia') ? 'selected' : ''; ?>>Sugerencia</option>
                </select>
            </div>
            
            <div class="col-12 col-md-3 d-flex align-items-end gap-2">
                <button type="submit" class="btn btn-elegant-primary flex-grow-1">
                    <i class="bi bi-arrow-repeat me-1"></i> Filtrar
                </button>
                <a href="reportes.php" class="btn btn-elegant-outline" title="Restaurar Fechas">
                    <i class="bi bi-arrow-counterclockwise"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Botones de Acción (Exportación / Impresión) -->
<div class="d-flex justify-content-end gap-3 mb-4 btn-actions-print no-print font-sans">
    <a href="reportes.php?export_csv=1&fecha_inicio=<?php echo urlencode($fecha_inicio); ?>&fecha_fin=<?php echo urlencode($fecha_fin); ?>&filtro_accion=<?php echo urlencode($filtro_accion); ?>" class="btn btn-elegant-outline d-flex align-items-center gap-2" id="btn-export-csv">
        <i class="bi bi-file-earmark-excel-fill text-success"></i> Exportar Excel / CSV
    </a>
    <button onclick="window.print();" class="btn btn-elegant-primary d-flex align-items-center gap-2" id="btn-print-report">
        <i class="bi bi-printer-fill"></i> Imprimir Reporte (PDF)
    </button>
</div>

<!-- Grid de Indicadores Operativos -->
<div class="row mb-4 font-sans">
    <div class="col-12 col-md-6 col-lg-3 mb-3">
        <div class="card-elegant stat-card p-3 h-100">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="text-muted mb-1 text-uppercase fw-semibold" style="font-size: 0.75rem; letter-spacing: 0.05em;">Operaciones Totales</h6>
                    <div class="stat-val"><?php echo $total_operaciones; ?></div>
                </div>
                <div class="fs-1 text-muted opacity-50"><i class="bi bi-activity"></i></div>
            </div>
        </div>
    </div>
    
    <div class="col-12 col-md-6 col-lg-3 mb-3">
        <div class="card-elegant stat-card p-3 h-100" style="border-left-color: #3182ce !important;">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="text-muted mb-1 text-uppercase fw-semibold" style="font-size: 0.75rem; letter-spacing: 0.05em;">Visualizaciones</h6>
                    <div class="stat-val text-primary"><?php echo $total_vistas; ?></div>
                </div>
                <div class="fs-1 text-primary opacity-50"><i class="bi bi-eye-fill"></i></div>
            </div>
        </div>
    </div>
    
    <div class="col-12 col-md-6 col-lg-3 mb-3">
        <div class="card-elegant stat-card p-3 h-100" style="border-left-color: var(--color-vigente-text) !important;">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="text-muted mb-1 text-uppercase fw-semibold" style="font-size: 0.75rem; letter-spacing: 0.05em;">Descargas</h6>
                    <div class="stat-val text-success"><?php echo $total_descargas; ?></div>
                </div>
                <div class="fs-1 text-success opacity-50"><i class="bi bi-download"></i></div>
            </div>
        </div>
    </div>

    <div class="col-12 col-md-6 col-lg-3 mb-3">
        <div class="card-elegant stat-card p-3 h-100" style="border-left-color: #805ad5 !important;">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="text-muted mb-1 text-uppercase fw-semibold" style="font-size: 0.75rem; letter-spacing: 0.05em;">Sugerencias Recibidas</h6>
                    <div class="stat-val" style="color: #805ad5;"><?php echo $total_sugerencias; ?></div>
                </div>
                <div class="fs-1 text-primary opacity-50" style="color: #805ad5 !important;"><i class="bi bi-chat-right-quote-fill"></i></div>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <!-- Columna Izquierda: Gráfico de Actividad por Norma ISO -->
    <div class="col-12 col-lg-6 mb-3">
        <div class="card-elegant h-100">
            <div class="card-elegant-header">
                <h5 class="m-0 font-title"><i class="bi bi-pie-chart-fill me-2 text-warning"></i>Actividad por Norma Reguladora</h5>
            </div>
            <div class="card-elegant-body font-sans">
                <?php if (count($iso_activity) > 0): ?>
                    <div class="bar-chart-container my-3">
                        <?php foreach ($iso_activity as $iso): 
                            // Calcular porcentaje de actividad para la barra de progreso
                            $porcentaje = $total_operaciones > 0 ? round(($iso['total'] / $total_operaciones) * 100) : 0;
                        ?>
                            <div class="bar-chart-row">
                                <div class="bar-chart-label-group">
                                    <span>Norma: <strong><?php echo htmlspecialchars($iso['idiso']); ?></strong></span>
                                    <span><?php echo $iso['total']; ?> ops (<?php echo $porcentaje; ?>%)</span>
                                </div>
                                <div class="bar-chart-progress-bg">
                                    <div class="bar-chart-progress-fill" style="width: <?php echo $porcentaje; ?>%;"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-bar-chart fs-1 d-block mb-3"></i>
                        No hay suficiente información para graficar en este periodo.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Columna Derecha: Top 5 Documentos Más Activos -->
    <div class="col-12 col-lg-6 mb-3">
        <div class="card-elegant h-100">
            <div class="card-elegant-header">
                <h5 class="m-0 font-title"><i class="bi bi-trophy-fill me-2 text-warning"></i>Top 5 Documentos Más Consultados</h5>
            </div>
            <div class="table-responsive">
                <table class="table-elegant" style="font-size: 0.9rem;">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Título Documento</th>
                            <th class="text-center">Norma</th>
                            <th class="text-center" style="width: 100px;">Operaciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($top_documentos) > 0): ?>
                            <?php foreach ($top_documentos as $doc): ?>
                                <tr>
                                    <td><code><?php echo htmlspecialchars($doc['codigo']); ?></code></td>
                                    <td><strong><?php echo htmlspecialchars($doc['titulodocumento']); ?></strong></td>
                                    <td class="text-center"><span class="badge badge-iso"><?php echo htmlspecialchars($doc['idiso']); ?></span></td>
                                    <td class="text-center fw-bold text-dark"><?php echo $doc['total_operaciones']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="text-center py-5 text-muted">
                                    <i class="bi bi-file-earmark-x fs-2 d-block mb-2"></i>
                                    No hay registros de documentos consultados en este rango.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Historial Detallado y Línea de Tiempo de Auditoría -->
<div class="row font-sans">
    <!-- Historial Detallado de Operaciones (col-12 col-xl-8) -->
    <div class="col-12 col-xl-8 mb-4">
        <div class="card-elegant h-100">
            <div class="card-elegant-header d-flex justify-content-between align-items-center">
                <h5 class="m-0 font-title"><i class="bi bi-list-check me-2 text-primary"></i>Historial Detallado de Operaciones</h5>
                <span class="badge bg-dark font-sans">Total en Rango: <?php echo count($report_logs); ?></span>
            </div>
            
            <div class="table-responsive">
                <table class="table-elegant" style="font-size: 0.9rem;">
                    <thead>
                        <tr>
                            <th style="width: 80px;">Ref</th>
                            <?php if ($is_superadmin): ?>
                                <th>Empresa</th>
                            <?php endif; ?>
                            <th>Usuario</th>
                            <th>Acción</th>
                            <th>Documento / Código</th>
                            <th style="width: 180px;">Fecha y Hora</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($report_logs) > 0): ?>
                            <?php foreach ($report_logs as $log): ?>
                                <tr>
                                    <td class="text-muted">#<?php echo $log['idlog']; ?></td>
                                    <?php if ($is_superadmin): ?>
                                        <td>
                                            <span class="badge bg-secondary font-sans" style="font-size: 0.75rem;">
                                                ID: <?php echo $log['empresaid']; ?>
                                            </span>
                                            <br>
                                            <small class="text-muted" style="font-size: 0.75rem;">
                                                <?php echo $log['empresaid'] == 2 ? 'KittyBeauty' : ($log['empresaid'] == 1 ? 'Empresa Maestra' : 'Tenant Corporativo'); ?>
                                            </small>
                                        </td>
                                    <?php endif; ?>
                                    <td>
                                        <strong><?php echo htmlspecialchars($log['nombreusuario']); ?></strong><br>
                                        <small class="text-muted" style="font-size: 0.75rem;"><?php echo htmlspecialchars(ucfirst($log['rol'])); ?></small>
                                    </td>
                                    <td>
                                        <?php if ($log['accion'] === 'visualizacion'): ?>
                                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-3">
                                                <i class="bi bi-eye-fill me-1"></i> Ver
                                            </span>
                                        <?php elseif ($log['accion'] === 'descarga'): ?>
                                            <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-3">
                                                <i class="bi bi-download me-1"></i> Descargar
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-purple-subtle text-purple border border-purple-subtle rounded-pill px-3" style="background-color: #f3e8ff; color: #6b21a8; border-color: #e9d5ff;">
                                                <i class="bi bi-chat-left-text-fill me-1"></i> Sugerencia
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="d-flex flex-column">
                                            <span class="text-truncate" style="max-width: 250px;" title="<?php echo htmlspecialchars($log['titulodocumento']); ?>">
                                                <?php echo htmlspecialchars($log['titulodocumento']); ?>
                                            </span>
                                            <small class="text-muted"><code><?php echo htmlspecialchars($log['codigo']); ?></code> [<?php echo htmlspecialchars($log['idiso']); ?>]</small>
                                        </div>
                                    </td>
                                    <td class="text-muted">
                                        <?php echo date('d/m/Y H:i:s', strtotime($log['fecha'])); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center py-5 text-muted">
                                    <i class="bi bi-database-x fs-2 d-block mb-3"></i>
                                    No se encontraron registros de operaciones en el periodo seleccionado.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Línea de Tiempo de Auditoría (col-12 col-xl-4) -->
    <div class="col-12 col-xl-4 mb-4">
        <div class="card-elegant h-100">
            <div class="card-elegant-header">
                <h5 class="m-0 font-title"><i class="bi bi-activity text-warning me-2"></i>Línea de Tiempo Reciente</h5>
            </div>
            <div class="card-elegant-body" style="max-height: 500px; overflow-y: auto;">
                <?php if (count($report_logs) > 0): ?>
                    <div class="timeline-elegant">
                        <?php 
                        // Mostrar los últimos 8 registros en el timeline para evitar sobrecargar la vista
                        $timeline_logs = array_slice($report_logs, 0, 8);
                        foreach ($timeline_logs as $log): 
                            $class_timeline = $log['accion']; // visualizacion, descarga, sugerencia
                        ?>
                            <div class="timeline-item <?php echo $class_timeline; ?>">
                                <div class="timeline-date">
                                    <?php echo date('d M, H:i', strtotime($log['fecha'])); ?>
                                </div>
                                <div class="timeline-text mt-1">
                                    <strong><?php echo htmlspecialchars($log['nombreusuario']); ?></strong>
                                    <?php if ($is_superadmin): ?>
                                        <span class="badge bg-secondary font-sans" style="font-size: 0.7rem; padding: 0.2em 0.5em; vertical-align: middle;">
                                            ID: <?php echo $log['empresaid']; ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($log['accion'] === 'visualizacion'): ?>
                                        visualizó el documento
                                    <?php elseif ($log['accion'] === 'descarga'): ?>
                                        descargó el documento
                                    <?php else: ?>
                                        dejó una sugerencia en el documento
                                    <?php endif; ?>
                                    <br>
                                    <small class="text-muted"><code><?php echo htmlspecialchars($log['codigo']); ?></code> - <?php echo htmlspecialchars($log['titulodocumento']); ?></small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-clock-history fs-2 d-block mb-2"></i>
                        Sin actividad reciente en este rango.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
