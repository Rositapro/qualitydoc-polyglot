<?php
// Reporte de Auditoría (Logs de Consultas) - SGD

require_once __DIR__ . '/includes/header.php';

// El $pdo y las variables de sesión ($empresa_id, etc.) se heredan de header.php

try {
    // Consultar logs de la empresa actual, uniendo con usuarios y documentos para mostrar detalles
    // Aplicando estricta multi-tenencia en todos los joins
    $stmt = $pdo->prepare("
        SELECT 
            l.idlog, 
            l.idusuario, 
            l.accion, 
            l.fecha, 
            u.nombreusuario, 
            u.rol,
            d.codigo, 
            d.titulodocumento,
            d.idiso
        FROM logsconsultas l
        INNER JOIN usuarios u ON l.idusuario = u.idusuario AND u.empresaid = :empresa
        INNER JOIN documento d ON l.iddocumento = d.iddocumento AND d.empresaid = :empresa
        WHERE l.empresaid = :empresa
        ORDER BY l.fecha DESC
    ");
    $stmt->execute(['empresa' => $empresa_id]);
    $logs = $stmt->fetchAll();

} catch (PDOException $e) {
    echo "<div class='alert alert-danger font-sans'>Error de base de datos al cargar logs: " . htmlspecialchars($e->getMessage()) . "</div>";
    $logs = [];
}
?>

<div class="row font-sans">
    <!-- Tabla Detallada de Auditoría -->
    <div class="col-12 col-xl-8 mb-4">
        <div class="card-elegant h-100">
            <div class="card-elegant-header d-flex justify-content-between align-items-center">
                <h5 class="m-0"><i class="bi bi-shield-fill-check text-success me-2"></i>Historial de Acceso y Operaciones</h5>
                <span class="badge bg-dark">Total Registros: <?php echo count($logs); ?></span>
            </div>
            
            <div class="table-responsive">
                <table class="table-elegant">
                    <thead>
                        <tr>
                            <th style="width: 80px;">Ref</th>
                            <th>Usuario</th>
                            <th>Acción</th>
                            <th>Documento / Código</th>
                            <th style="width: 180px;">Fecha y Hora</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($logs) > 0): ?>
                            <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td class="text-muted">#<?php echo $log['idlog']; ?></td>
                                    <td>
                                        <div class="d-flex flex-column">
                                            <span class="fw-bold"><?php echo htmlspecialchars($log['nombreusuario']); ?></span>
                                            <small class="text-muted" style="font-size: 0.75rem;">ID: <?php echo htmlspecialchars($log['idusuario']); ?> (<?php echo htmlspecialchars($log['rol']); ?>)</small>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($log['accion'] === 'visualizacion'): ?>
                                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill font-sans px-3">
                                                <i class="bi bi-eye-fill me-1"></i> Ver
                                            </span>
                                        <?php elseif ($log['accion'] === 'descarga'): ?>
                                            <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill font-sans px-3">
                                                <i class="bi bi-download me-1"></i> Descargar
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-purple-subtle text-purple border border-purple-subtle rounded-pill font-sans px-3" style="background-color: #f3e8ff; color: #6b21a8; border-color: #e9d5ff;">
                                                <i class="bi bi-chat-left-text-fill me-1"></i> Sugerencia
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="d-flex flex-column">
                                            <span class="text-truncate" style="max-width: 250px;" title="<?php echo htmlspecialchars($log['titulodocumento']); ?>">
                                                <?php echo htmlspecialchars($log['titulodocumento']); ?>
                                            </span>
                                            <small class="font-sans"><code><?php echo htmlspecialchars($log['codigo']); ?></code> [<?php echo htmlspecialchars($log['idiso']); ?>]</small>
                                        </div>
                                    </td>
                                    <td class="font-sans text-muted">
                                        <?php echo date('d/m/Y H:i:s', strtotime($log['fecha'])); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center py-5 text-muted">
                                    <i class="bi bi-database-x fs-2 d-block mb-3"></i>
                                    No hay registros de auditoría para esta empresa aún.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Timeline Interactiva (Panel Lateral) -->
    <div class="col-12 col-xl-4 mb-4">
        <div class="card-elegant h-100">
            <div class="card-elegant-header">
                <h5 class="m-0"><i class="bi bi-activity text-warning me-2"></i>Línea de Tiempo Reciente</h5>
            </div>
            <div class="card-elegant-body" style="max-height: 500px; overflow-y: auto;">
                <?php if (count($logs) > 0): ?>
                    <div class="timeline-elegant">
                        <?php 
                        // Mostrar los últimos 8 registros en el timeline para evitar sobrecargar la vista
                        $timeline_logs = array_slice($logs, 0, 8);
                        foreach ($timeline_logs as $log): 
                            $class_timeline = $log['accion']; // visualizacion, descarga, sugerencia
                        ?>
                            <div class="timeline-item <?php echo $class_timeline; ?>">
                                <div class="timeline-date">
                                    <?php echo date('d M, H:i', strtotime($log['fecha'])); ?>
                                </div>
                                <div class="timeline-text mt-1">
                                    <strong><?php echo htmlspecialchars($log['nombreusuario']); ?></strong>
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
                        Sin actividad reciente.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
