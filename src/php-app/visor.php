<?php
// Visor de Documentos - SGD

require_once __DIR__ . '/includes/header.php';

// El $pdo y las variables de sesión ($empresa_id, etc.) se heredan de header.php

$id_documento = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id_documento <= 0) {
    echo "<div class='alert alert-danger font-sans'><i class='bi bi-exclamation-octagon-fill me-2'></i>ID de documento no especificado.</div>";
    require_once __DIR__ . '/includes/footer.php';
    exit();
}

try {
    // Consultar el documento validando estrictamente que pertenezca a la empresa del usuario
    $stmt = $pdo->prepare("
        SELECT * 
        FROM documento 
        WHERE iddocumento = :id AND empresaid = :empresa
    ");
    $stmt->execute([
        'id' => $id_documento,
        'empresa' => $empresa_id
    ]);
    $doc = $stmt->fetch();

    if (!$doc) {
        // Documento no encontrado o no pertenece a esta empresa
        echo "
        <div class='alert alert-danger font-sans py-4'>
            <h4 class='alert-heading'><i class='bi bi-shield-slash-fill me-2'></i>Acceso Denegado</h4>
            <p>El documento solicitado no existe o no tiene los permisos suficientes para visualizarlo dentro del entorno de su empresa (Multi-Tenant).</p>
            <hr>
            <a href='documentos.php' class='btn btn-elegant-primary btn-sm'><i class='bi bi-arrow-left me-1'></i>Volver al Panel</a>
        </div>";
        require_once __DIR__ . '/includes/footer.php';
        exit();
    }

    // Registrar la acción de visualización en la tabla logsconsultas
    $stmtLog = $pdo->prepare("
        INSERT INTO logsconsultas (idusuario, iddocumento, accion, empresaid) 
        VALUES (:usuario, :documento, 'visualizacion', :empresa)
    ");
    $stmtLog->execute([
        'usuario' => $_SESSION['idusuario'],
        'documento' => $id_documento,
        'empresa' => $empresa_id
    ]);

} catch (PDOException $e) {
    echo "<div class='alert alert-danger font-sans'>Error de base de datos: " . htmlspecialchars($e->getMessage()) . "</div>";
    require_once __DIR__ . '/includes/footer.php';
    exit();
}
?>

<div class="row font-sans">
    <!-- Panel Izquierdo: Información y Metadatos -->
    <div class="col-12 col-lg-4 mb-4">
        <div class="card-elegant h-100">
            <div class="card-elegant-header">
                <h5 class="m-0"><i class="bi bi-info-circle-fill me-2 text-accent"></i>Ficha de Control</h5>
            </div>
            <div class="card-elegant-body">
                <ul class="list-group list-group-flush" style="font-size: 0.95rem;">
                    <li class="list-group-item d-flex justify-content-between align-items-center py-3 bg-transparent">
                        <span class="text-muted">Código:</span>
                        <span class="badge bg-secondary font-sans"><?php echo htmlspecialchars($doc['codigo']); ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center py-3 bg-transparent">
                        <span class="text-muted">Versión actual:</span>
                        <span class="fw-bold text-dark"><?php echo htmlspecialchars($doc['version']); ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center py-3 bg-transparent">
                        <span class="text-muted">Norma Reguladora:</span>
                        <span class="badge badge-status badge-iso"><?php echo htmlspecialchars($doc['idiso']); ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center py-3 bg-transparent">
                        <span class="text-muted">Estado del Documento:</span>
                        <?php if ($doc['estado'] === 'vigente'): ?>
                            <span class="badge badge-status badge-vigente">Vigente</span>
                        <?php else: ?>
                            <span class="badge badge-status badge-obsoleto">Obsoleto</span>
                        <?php endif; ?>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center py-3 bg-transparent">
                        <span class="text-muted">Tenant / ID Empresa:</span>
                        <span class="badge bg-dark font-sans"><?php echo htmlspecialchars($doc['empresaid']); ?></span>
                    </li>
                </ul>

                <div class="mt-4 pt-3 border-top border-light d-flex flex-column gap-2">
                    <a href="descargar.php?id=<?php echo $doc['iddocumento']; ?>" class="btn btn-elegant-accent w-100 d-flex align-items-center justify-content-center gap-2">
                        <i class="bi bi-download"></i> Descargar Documento
                    </a>
                    <a href="sugerencia.php?id=<?php echo $doc['iddocumento']; ?>" class="btn btn-elegant-outline w-100 d-flex align-items-center justify-content-center gap-2">
                        <i class="bi bi-chat-left-text"></i> Enviar Sugerencia
                    </a>
                    <a href="documentos.php" class="btn btn-sm btn-link text-center text-muted mt-2">
                        <i class="bi bi-arrow-left me-1"></i> Volver a Documentos
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Panel Derecho: Visor de Contenido -->
    <div class="col-12 col-lg-8 mb-4">
        <div class="card-elegant position-relative overflow-hidden h-100">
            <div class="card-elegant-header d-flex justify-content-between align-items-center bg-white border-bottom">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-file-text-fill text-warning fs-4"></i>
                    <div>
                        <h5 class="m-0" style="font-size: 1.1rem;"><?php echo htmlspecialchars($doc['titulodocumento']); ?></h5>
                        <small class="text-muted font-sans" style="font-size: 0.75rem;">Visor Oficial de Calidad y Procesos</small>
                    </div>
                </div>
                <div>
                    <span class="badge bg-light text-dark border font-sans" style="font-size: 0.8rem;">Modo de Visualización Segura</span>
                </div>
            </div>
            
            <div class="card-elegant-body p-4 position-relative" style="background-color: #faf9f6; min-height: 450px;">
                <!-- Marca de agua dinámica de seguridad multi-tenant -->
                <div class="visor-watermark">
                    CONFIDENCIAL - EMPRESA <?php echo htmlspecialchars($empresa_id); ?><br>
                    USUARIO: <?php echo htmlspecialchars($_SESSION['idusuario']); ?>
                </div>

                <?php if (!empty($doc['rutaarchivo']) && file_exists(__DIR__ . '/uploads/' . $doc['rutaarchivo'])): ?>
                    <div style="height: 600px; width: 100%; position: relative; z-index: 1;">
                        <iframe src="uploads/<?php echo htmlspecialchars($doc['rutaarchivo']); ?>" style="border: 2px solid #9b9564; border-radius: 8px; width: 100%; height: 100%;"></iframe>
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning text-center py-4 position-relative z-1" style="border-radius: 8px; background-color: #fff3cd; color: #856404; border: 1px solid #ffeeba;">
                        <i class="bi bi-file-earmark-exclamation-fill display-4 mb-3 d-block"></i>
                        <h5>Visualización no disponible</h5>
                        <p>No se pudo cargar el archivo físico de este documento. Asegúrese de que el archivo existe en el servidor o descárguelo directamente.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
