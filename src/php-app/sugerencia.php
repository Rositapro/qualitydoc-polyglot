<?php
// Formulario y Controlador para Sugerencias de Documentos - SGD

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
            <p>El documento para el cual desea emitir una sugerencia no existe o no pertenece a su empresa.</p>
            <hr>
            <a href='documentos.php' class='btn btn-elegant-primary btn-sm'><i class='bi bi-arrow-left me-1'></i>Volver al Panel</a>
        </div>";
        require_once __DIR__ . '/includes/footer.php';
        exit();
    }

} catch (PDOException $e) {
    echo "<div class='alert alert-danger font-sans'>Error de base de datos: " . htmlspecialchars($e->getMessage()) . "</div>";
    require_once __DIR__ . '/includes/footer.php';
    exit();
}

$mensaje_exito = "";
$mensaje_error = "";

// Lógica al recibir el POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $comentario = isset($_POST['comentario']) ? trim($_POST['comentario']) : '';

    if (empty($comentario)) {
        $mensaje_error = "El comentario no puede estar vacío.";
    } else {
        try {
            // Iniciar transacción para asegurar consistencia
            $pdo->beginTransaction();

            // 1. Insertar en la tabla sugerencias
            $stmtInsert = $pdo->prepare("
                INSERT INTO sugerencias (iddocumento, idusuario, comentario, empresaid, fecha) 
                VALUES (:documento, :usuario, :comentario, :empresa, NOW())
            ");
            $stmtInsert->execute([
                'documento' => $id_documento,
                'usuario'   => $_SESSION['idusuario'],
                'comentario' => $comentario,
                'empresa'   => $empresa_id
            ]);

            // 2. Insertar log de la acción en logsconsultas
            $stmtLog = $pdo->prepare("
                INSERT INTO logsconsultas (idusuario, iddocumento, accion, empresaid, fecha) 
                VALUES (:usuario, :documento, 'sugerencia', :empresa, NOW())
            ");
            $stmtLog->execute([
                'usuario'   => $_SESSION['idusuario'],
                'documento' => $id_documento,
                'empresa'   => $empresa_id
            ]);

            // Confirmar transacción
            $pdo->commit();
            $mensaje_exito = "¡Sugerencia guardada y registrada en auditoría exitosamente!";
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            $mensaje_error = "Error al guardar la sugerencia: " . $e->getMessage();
        }
    }
}
?>

<div class="row justify-content-center font-sans">
    <div class="col-12 col-md-8 col-lg-6">
        <div class="card-elegant">
            <div class="card-elegant-header">
                <h5 class="m-0"><i class="bi bi-chat-right-quote-fill me-2 text-warning"></i>Nueva Sugerencia de Documento</h5>
            </div>
            
            <div class="card-elegant-body">
                <!-- Información del documento asociado -->
                <div class="p-3 mb-4 rounded border-dashed" style="background-color: #faf9f6; border: 1.5px dashed var(--border-color);">
                    <h6 class="mb-1 text-primary" style="font-weight: 600;"><?php echo htmlspecialchars($doc['titulodocumento']); ?></h6>
                    <div class="d-flex gap-3" style="font-size: 0.8rem; color: var(--text-muted);">
                        <span><strong>Código:</strong> <?php echo htmlspecialchars($doc['codigo']); ?></span>
                        <span><strong>Versión:</strong> <?php echo htmlspecialchars($doc['version']); ?></span>
                        <span><strong>ISO:</strong> <?php echo htmlspecialchars($doc['idiso']); ?></span>
                    </div>
                </div>

                <?php if (!empty($mensaje_exito)): ?>
                    <div class="alert alert-success d-flex align-items-center gap-2 mb-4" role="alert">
                        <i class="bi bi-check-circle-fill fs-5"></i>
                        <div><?php echo $mensaje_exito; ?></div>
                    </div>
                    <div class="text-center">
                        <a href="documentos.php" class="btn btn-elegant-primary"><i class="bi bi-folder2-open me-1"></i>Volver a Documentos</a>
                    </div>
                <?php else: ?>
                    <?php if (!empty($mensaje_error)): ?>
                        <div class="alert alert-danger d-flex align-items-center gap-2 mb-4" role="alert">
                            <i class="bi bi-exclamation-triangle-fill fs-5"></i>
                            <div><?php echo $mensaje_error; ?></div>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="sugerencia.php?id=<?php echo $id_documento; ?>">
                        <div class="mb-4">
                            <label for="comentario" class="form-label form-label-elegant">Escribe tu sugerencia, comentario o corrección:</label>
                            <textarea class="form-control form-control-elegant" id="comentario" name="comentario" rows="6" placeholder="Redacte aquí detalladamente las sugerencias sobre el contenido o estructura del documento..." required></textarea>
                            <div class="form-text mt-2 text-muted" style="font-size: 0.75rem;">
                                Su comentario quedará registrado con su usuario <strong>(<?php echo htmlspecialchars($_SESSION['nombreusuario']); ?>)</strong> y será visible en los reportes de calidad asociados a su empresa.
                            </div>
                        </div>

                        <div class="d-flex justify-content-end gap-2">
                             <a href="documentos.php" class="btn btn-elegant-outline">
                                Cancelar
                            </a>
                            <button type="submit" class="btn btn-elegant-primary d-flex align-items-center gap-2">
                                <i class="bi bi-send-fill"></i> Enviar Sugerencia
                            </button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
