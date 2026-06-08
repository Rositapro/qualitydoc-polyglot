<?php
// API REST: Actualizar Versiones de Documentos desde Módulo .NET
header("Content-Type: application/json; charset=UTF-8");

// Incluir configuración y conexión a base de datos
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

// Leer el payload JSON recibido
$input_raw = file_get_contents("php://input");
$data = json_decode($input_raw, true);

if (!$data) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "El cuerpo de la petición debe ser un JSON válido."
    ]);
    exit();
}

// 1. Validar parámetros obligatorios
$titulodocumento = isset($data['titulodocumento']) ? trim($data['titulodocumento']) : '';
$codigo = isset($data['codigo']) ? trim($data['codigo']) : '';
$version = isset($data['version']) ? trim($data['version']) : '';
$idiso = isset($data['idiso']) ? trim($data['idiso']) : '';
$empresaid = isset($data['empresaid']) ? intval($data['empresaid']) : 0;
$archivo_base64 = isset($data['archivo_base64']) ? trim($data['archivo_base64']) : '';
$nombre_archivo_original = isset($data['nombrearchivo']) ? trim($data['nombrearchivo']) : '';

if (empty($titulodocumento) || empty($codigo) || empty($version) || empty($idiso) || $empresaid <= 0) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Faltan parámetros obligatorios para la actualización: titulodocumento, codigo, version, idiso, empresaid."
    ]);
    exit();
}

$ruta_guardada = null;

// 2. Procesar y guardar el archivo si se envió en base64
if (!empty($archivo_base64)) {
    try {
        $directorio_uploads = __DIR__ . '/../uploads';
        if (!file_exists($directorio_uploads)) {
            mkdir($directorio_uploads, 0777, true);
        }

        if (preg_match('/^data:([^;]+);base64,(.*)$/', $archivo_base64, $matches)) {
            $datos_binarios = base64_decode($matches[2]);
        } else {
            $datos_binarios = base64_decode($archivo_base64);
        }

        if ($datos_binarios === false) {
            throw new Exception("Error al decodificar la cadena base64 del archivo.");
        }

        $extension = 'pdf';
        if (!empty($nombre_archivo_original)) {
            $partes = explode('.', $nombre_archivo_original);
            $extension = strtolower(end($partes));
        }

        $nombre_archivo_seguro = preg_replace('/[^A-Za-z0-9_\-]/', '_', $codigo) . "_v" . preg_replace('/[^A-Za-z0-9_\-\.]/', '_', $version) . "_" . time() . "." . $extension;
        $ruta_completa = $directorio_uploads . '/' . $nombre_archivo_seguro;

        if (file_put_contents($ruta_completa, $datos_binarios) === false) {
            throw new Exception("No se pudo guardar el archivo en el servidor.");
        }

        $ruta_guardada = $nombre_archivo_seguro;

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            "success" => false,
            "message" => "Error al procesar el archivo adjunto en la actualización: " . $e->getMessage()
        ]);
        exit();
    }
}

// 3. Ejecutar actualización en Transacción de Base de Datos
try {
    $pdo->beginTransaction();

    // A. Marcar el registro actual vigente como 'obsoleto' para el código y empresa correspondiente
    $stmtObsoleto = $pdo->prepare("
        UPDATE documento 
        SET estado = 'obsoleto' 
        WHERE codigo = :codigo AND empresaid = :empresa AND estado = 'vigente'
    ");
    $stmtObsoleto->execute([
        'codigo'  => $codigo,
        'empresa' => $empresaid
    ]);

    // B. Insertar la nueva versión del documento con estado 'vigente'
    $stmtInsert = $pdo->prepare("
        INSERT INTO documento (titulodocumento, codigo, version, idiso, estado, empresaid, rutaarchivo) 
        VALUES (:titulo, :codigo, :version, :iso, 'vigente', :empresa, :ruta)
    ");
    $stmtInsert->execute([
        'titulo'  => $titulodocumento,
        'codigo'  => $codigo,
        'version' => $version,
        'iso'     => $idiso,
        'empresa' => $empresaid,
        'ruta'    => $ruta_guardada
    ]);

    $id_nuevo = $pdo->lastInsertId();

    // Confirmar transacción
    $pdo->commit();

    http_response_code(200);
    echo json_encode([
        "success" => true,
        "message" => "Versión actualizada con éxito. Se marcó el documento anterior como 'obsoleto' y el nuevo como 'vigente'.",
        "iddocumento" => intval($id_nuevo)
    ]);
    exit();

} catch (PDOException $e) {
    // Revertir base de datos
    $pdo->rollBack();

    // Eliminar archivo físico de la nueva versión si la base de datos falló
    if ($ruta_guardada && file_exists($directorio_uploads . '/' . $ruta_guardada)) {
        unlink($directorio_uploads . '/' . $ruta_guardada);
    }

    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Error de base de datos durante la actualización de versión: " . $e->getMessage()
    ]);
    exit();
}
