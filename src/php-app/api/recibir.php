<?php
// API REST: Recibir Nuevos Documentos desde Módulo .NET
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
$estado = isset($data['estado']) ? strtolower(trim($data['estado'])) : 'vigente';
$empresaid = isset($data['empresaid']) ? intval($data['empresaid']) : 0;
$archivo_base64 = isset($data['archivo_base64']) ? trim($data['archivo_base64']) : '';
$nombre_archivo_original = isset($data['nombrearchivo']) ? trim($data['nombrearchivo']) : '';

if (empty($titulodocumento) || empty($codigo) || empty($version) || empty($idiso) || $empresaid <= 0) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Faltan parámetros obligatorios: titulodocumento, codigo, version, idiso, empresaid."
    ]);
    exit();
}

// Validar valores permitidos para estado
if ($estado !== 'vigente' && $estado !== 'obsoleto') {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "El estado del documento debe ser 'vigente' o 'obsoleto'."
    ]);
    exit();
}

$ruta_guardada = null;

// 2. Procesar y guardar el archivo si se envió en base64
if (!empty($archivo_base64)) {
    try {
        // Asegurar que la carpeta de descargas existe
        $directorio_uploads = __DIR__ . '/../uploads';
        if (!file_exists($directorio_uploads)) {
            mkdir($directorio_uploads, 0777, true);
        }

        // Decodificar el archivo base64
        // Puede venir con la cabecera data:application/pdf;base64,...
        if (preg_match('/^data:([^;]+);base64,(.*)$/', $archivo_base64, $matches)) {
            $datos_binarios = base64_decode($matches[2]);
        } else {
            $datos_binarios = base64_decode($archivo_base64);
        }

        if ($datos_binarios === false) {
            throw new Exception("Error al decodificar la cadena base64.");
        }

        // Determinar extensión del archivo (por defecto .pdf si no se detecta otra)
        $extension = 'pdf';
        if (!empty($nombre_archivo_original)) {
            $partes = explode('.', $nombre_archivo_original);
            $extension = strtolower(end($partes));
        }

        // Nombre único seguro en disco
        $nombre_archivo_seguro = preg_replace('/[^A-Za-z0-9_\-]/', '_', $codigo) . "_v" . preg_replace('/[^A-Za-z0-9_\-\.]/', '_', $version) . "_" . time() . "." . $extension;
        $ruta_completa = $directorio_uploads . '/' . $nombre_archivo_seguro;

        // Guardar el archivo en disco
        if (file_put_contents($ruta_completa, $datos_binarios) === false) {
            throw new Exception("No se pudo escribir el archivo en el servidor.");
        }

        $ruta_guardada = $nombre_archivo_seguro;

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            "success" => false,
            "message" => "Error al guardar el archivo adjunto: " . $e->getMessage()
        ]);
        exit();
    }
}

// 3. Insertar el documento en la base de datos
try {
    $stmt = $pdo->prepare("
        INSERT INTO documento (titulodocumento, codigo, version, idiso, estado, empresaid, rutaarchivo) 
        VALUES (:titulo, :codigo, :version, :iso, :estado, :empresa, :ruta)
    ");
    
    $stmt->execute([
        'titulo'  => $titulodocumento,
        'codigo'  => $codigo,
        'version' => $version,
        'iso'     => $idiso,
        'estado'  => $estado,
        'empresa' => $empresaid,
        'ruta'    => $ruta_guardada
    ]);

    $id_nuevo = $pdo->lastInsertId();

    http_response_code(201);
    echo json_encode([
        "success" => true,
        "message" => "Documento registrado y guardado con éxito.",
        "iddocumento" => intval($id_nuevo)
    ]);
    exit();

} catch (PDOException $e) {
    // Si la inserción falla y guardamos un archivo físico, intentar eliminarlo para no dejar basura
    if ($ruta_guardada && file_exists($directorio_uploads . '/' . $ruta_guardada)) {
        unlink($directorio_uploads . '/' . $ruta_guardada);
    }

    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Error al insertar el documento en la base de datos: " . $e->getMessage()
    ]);
    exit();
}
