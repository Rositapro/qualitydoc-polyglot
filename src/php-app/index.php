<?php
// Página de Inicio / Bienvenida - SGD MultiTenant

require_once __DIR__ . '/includes/header.php';

// Obtener estadísticas de la empresa actual
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

} catch (PDOException $e) {
    $stat_total = $stat_vigentes = $stat_obsoletos = $stat_sugerencias = 0;
}
?>

<div class="row font-sans mb-4">
    <!-- Card de Bienvenida Principal -->
    <div class="col-12">
        <div class="card-elegant p-4 p-md-5 text-white position-relative" style="background: linear-gradient(135deg, #4a2e1a 0%, #23170f 100%); border: none; border-radius: 16px;">
            <!-- Decoración sutil de fondo -->
            <div class="position-absolute end-0 top-50 translate-middle-y opacity-10 pe-5 d-none d-md-block" style="font-size: 8rem; pointer-events: none;">
                <i class="bi bi-journal-bookmark-fill"></i>
            </div>
            
            <div class="position-relative z-1" style="max-width: 700px;">
                <span class="badge bg-warning text-dark px-3 py-2 mb-3 rounded-pill text-uppercase fw-semibold" style="letter-spacing: 0.05em; font-size: 0.75rem;">
                    Portal Corporativo Activo
                </span>
                
                <h1 class="display-5 text-white font-title mb-3" style="font-weight: 700;">
                    ¡Te damos la bienvenida, <?php echo htmlspecialchars($nombre_usuario); ?>!
                </h1>
                
                <p class="lead text-light-subtle font-sans mb-4" style="font-size: 1.1rem; line-height: 1.6; opacity: 0.9;">
                    Estás en el Sistema de Gestión Documental (SGD). Desde aquí puedes administrar, visualizar y sugerir mejoras en los manuales de calidad y procedimientos operativos de tu organización.
                </p>
                
                <div class="d-flex flex-wrap gap-3">
                    <a href="documentos.php" class="btn btn-elegant-accent px-4 py-2" id="btn-welcome-explore">
                        <i class="bi bi-folder2-open me-2"></i> Explorar Documentos
                    </a>
                    <a href="reportes.php" class="btn btn-outline-light px-4 py-2 font-sans" id="btn-welcome-logs">
                        <i class="bi bi-bar-chart-line me-2"></i> Ver Reportes de Auditoría
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Grid de Métricas Rápidas -->
<div class="row mb-4 font-sans">
    <div class="col-12 col-md-6 col-lg-3 mb-3 mb-lg-0">
        <div class="card-elegant stat-card p-3 h-100">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="text-muted mb-1 text-uppercase fw-semibold" style="font-size: 0.75rem; letter-spacing: 0.05em;">Total Documentos</h6>
                    <div class="stat-val"><?php echo $stat_total; ?></div>
                </div>
                <div class="fs-1 text-muted opacity-50"><i class="bi bi-file-earmark-text"></i></div>
            </div>
        </div>
    </div>
    
    <div class="col-12 col-md-6 col-lg-3 mb-3 mb-lg-0">
        <div class="card-elegant stat-card p-3 h-100" style="border-left-color: var(--color-vigente-text) !important;">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="text-muted mb-1 text-uppercase fw-semibold" style="font-size: 0.75rem; letter-spacing: 0.05em;">Vigentes</h6>
                    <div class="stat-val text-success"><?php echo $stat_vigentes; ?></div>
                </div>
                <div class="fs-1 text-success opacity-50"><i class="bi bi-check-circle-fill"></i></div>
            </div>
        </div>
    </div>
    
    <div class="col-12 col-md-6 col-lg-3 mb-3 mb-lg-0">
        <div class="card-elegant stat-card p-3 h-100" style="border-left-color: var(--color-obsoleto-text) !important;">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="text-muted mb-1 text-uppercase fw-semibold" style="font-size: 0.75rem; letter-spacing: 0.05em;">Obsoletos</h6>
                    <div class="stat-val text-danger"><?php echo $stat_obsoletos; ?></div>
                </div>
                <div class="fs-1 text-danger opacity-50"><i class="bi bi-x-circle-fill"></i></div>
            </div>
        </div>
    </div>

    <div class="col-12 col-md-6 col-lg-3 mb-3 mb-lg-0">
        <div class="card-elegant stat-card p-3 h-100" style="border-left-color: #805ad5 !important;">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="text-muted mb-1 text-uppercase fw-semibold" style="font-size: 0.75rem; letter-spacing: 0.05em;">Sugerencias</h6>
                    <div class="stat-val" style="color: #805ad5;"><?php echo $stat_sugerencias; ?></div>
                </div>
                <div class="fs-1 text-primary opacity-50" style="color: #805ad5 !important;"><i class="bi bi-chat-right-quote-fill"></i></div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Columna Izquierda: Accesos y Guía de Operación -->
    <div class="col-12 col-lg-6 mb-4">
        <div class="card-elegant h-100">
            <div class="card-elegant-header">
                <h5 class="m-0 font-title"><i class="bi bi-compass-fill me-2 text-accent"></i>Accesos del Sistema</h5>
            </div>
            <div class="card-elegant-body font-sans">
                <div class="d-flex flex-column gap-3">
                    <div class="p-3 rounded border border-light-subtle d-flex gap-3 align-items-start" style="background-color: #faf9f6;">
                        <div class="bg-primary text-white rounded p-2" style="background-color: var(--color-primary) !important;">
                            <i class="bi bi-folder2-open fs-4"></i>
                        </div>
                        <div>
                            <h6 class="mb-1 fw-bold text-dark">Explorador de Documentos</h6>
                            <p class="text-muted mb-2" style="font-size: 0.85rem;">
                                Consulta y descarga la documentación oficial (Vigente u Obsoleta) filtrando por Normativa ISO aplicable.
                            </p>
                            <a href="documentos.php" class="btn btn-sm btn-link p-0 text-decoration-none fw-bold" style="color: var(--color-primary);">
                                Ir a Documentos <i class="bi bi-chevron-right" style="font-size: 0.7rem;"></i>
                            </a>
                        </div>
                    </div>
                    
                    <div class="p-3 rounded border border-light-subtle d-flex gap-3 align-items-start" style="background-color: #faf9f6;">
                        <div class="bg-dark text-white rounded p-2" style="background-color: var(--color-dark) !important;">
                            <i class="bi bi-shield-check fs-4"></i>
                        </div>
                        <div>
                            <h6 class="mb-1 fw-bold text-dark">Reportes de Auditoría</h6>
                            <p class="text-muted mb-2" style="font-size: 0.85rem;">
                                Consulta el registro de auditoría en tiempo real y genera reportes de uso de los documentos de tu empresa.
                            </p>
                            <a href="reportes.php" class="btn btn-sm btn-link p-0 text-decoration-none fw-bold" style="color: var(--color-primary);">
                                Ver Reportes <i class="bi bi-chevron-right" style="font-size: 0.7rem;"></i>
                            </a>
                        </div>
                    </div>

                    <div class="p-3 rounded border border-light-subtle d-flex gap-3 align-items-start" style="background-color: #faf9f6;">
                        <div class="bg-warning text-dark rounded p-2" style="background-color: var(--color-accent) !important; color: white !important;">
                            <i class="bi bi-person-fill-gear fs-4"></i>
                        </div>
                        <div>
                            <h6 class="mb-1 fw-bold text-dark">Tu Cuenta y Rol: <?php echo htmlspecialchars($rol_usuario); ?></h6>
                            <p class="text-muted mb-0" style="font-size: 0.85rem;">
                                Tus permisos están condicionados al tenant corporativo con <strong>ID <?php echo htmlspecialchars($empresa_id); ?></strong>. Toda acción será auditada por seguridad.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Columna Derecha: Estándares ISO Admitidos -->
    <div class="col-12 col-lg-6 mb-4">
        <div class="card-elegant h-100">
            <div class="card-elegant-header">
                <h5 class="m-0 font-title"><i class="bi bi-bookmark-star-fill me-2 text-warning"></i>Normativas ISO Soportadas</h5>
            </div>
            <div class="card-elegant-body font-sans">
                <p class="text-muted" style="font-size: 0.9rem;">
                    Los documentos disponibles en este portal académico-empresarial se clasifican de acuerdo con los siguientes estándares de calidad internacional:
                </p>
                
                <div class="list-group list-group-flush">
                    <div class="list-group-item bg-transparent px-0 py-3 border-light d-flex gap-3">
                        <span class="badge badge-iso text-uppercase d-flex align-items-center justify-content-center" style="width: 85px; height: 35px; font-size: 0.75rem;">ISO 9001</span>
                        <div>
                            <h6 class="mb-0 fw-bold text-dark">Sistemas de Gestión de Calidad (SGC)</h6>
                            <small class="text-muted">Asegura la satisfacción del cliente y el control de calidad interno.</small>
                        </div>
                    </div>
                    
                    <div class="list-group-item bg-transparent px-0 py-3 border-light d-flex gap-3">
                        <span class="badge badge-iso text-uppercase d-flex align-items-center justify-content-center" style="width: 85px; height: 35px; font-size: 0.75rem;">ISO 14001</span>
                        <div>
                            <h6 class="mb-0 fw-bold text-dark">Sistemas de Gestión Ambiental (SGA)</h6>
                            <small class="text-muted">Minimiza el impacto ecológico de los procesos de la organización.</small>
                        </div>
                    </div>
                    
                    <div class="list-group-item bg-transparent px-0 py-3 border-light d-flex gap-3">
                        <span class="badge badge-iso text-uppercase d-flex align-items-center justify-content-center" style="width: 85px; height: 35px; font-size: 0.75rem;">ISO 27001</span>
                        <div>
                            <h6 class="mb-0 fw-bold text-dark">Seguridad de la Información (SGSI)</h6>
                            <small class="text-muted">Protege la confidencialidad, integridad y disponibilidad de los datos.</small>
                        </div>
                    </div>

                    <div class="list-group-item bg-transparent px-0 py-3 border-light d-flex gap-3 border-bottom-0">
                        <span class="badge badge-iso text-uppercase d-flex align-items-center justify-content-center" style="width: 85px; height: 35px; font-size: 0.75rem;">ISO 45001</span>
                        <div>
                            <h6 class="mb-0 fw-bold text-dark">Seguridad y Salud en el Trabajo</h6>
                            <small class="text-muted">Previene lesiones y enfermedades ocupacionales en el entorno laboral.</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Pie / Recomendaciones de Calidad -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card-elegant p-3 text-center border-dashed font-sans" style="background-color: #fbfaf8; border: 1.5px dashed var(--border-color); border-radius: 10px;">
            <p class="mb-0 text-muted" style="font-size: 0.85rem;">
                <i class="bi bi-info-circle-fill me-2 text-accent"></i>
                <strong>Consejo de Calidad:</strong> Cuando emitas una sugerencia sobre un documento, especifica claramente la sección y el cambio propuesto para facilitar la revisión del auditor.
            </p>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
