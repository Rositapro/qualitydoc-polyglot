<?php
// Controlador de Descarga de Documentos - SGD

require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/db.php';

// El $pdo se hereda de db.php y las variables de sesión se obtienen de $_SESSION
$empresa_id = $_SESSION['empresaid'] ?? 0;

$id_documento = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id_documento <= 0) {
    die("ID de documento no especificado.");
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
        http_response_code(403);
        die("Acceso Denegado: El documento solicitado no pertenece a su empresa o no existe.");
    }

    // Registrar la acción de descarga en logsconsultas
    $stmtLog = $pdo->prepare("
        INSERT INTO logsconsultas (idusuario, iddocumento, accion, empresaid) 
        VALUES (:usuario, :documento, 'descarga', :empresa)
    ");
    $stmtLog->execute([
        'usuario' => $_SESSION['idusuario'],
        'documento' => $id_documento,
        'empresa' => $empresa_id
    ]);

    // Lógica para enviar el archivo
    $nombre_archivo_salida = preg_replace('/[^A-Za-z0-9_\-\.]/', '_', $doc['codigo']) . "_v" . $doc['version'] . ".txt";
    
    // Ruta física potencial si el archivo fue subido por la API
    $ruta_real_subida = __DIR__ . '/uploads/' . $doc['rutaarchivo'];

    if (!empty($doc['rutaarchivo']) && file_exists($ruta_real_subida) && is_file($ruta_real_subida)) {
        // Si el archivo físico real existe (subido por api/recibir.php)
        $mime_type = mime_content_type($ruta_real_subida) ?: 'application/octet-stream';
        
        header('Content-Description: File Transfer');
        header('Content-Type: ' . $mime_type);
        header('Content-Disposition: attachment; filename="' . basename($ruta_real_subida) . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($ruta_real_subida));
        
        // Limpiar el buffer de salida de PHP
        ob_clean();
        flush();
        readfile($ruta_real_subida);
        exit();
    } else {
        // Si no hay archivo físico (ej. datos semilla), generamos una ficha técnica certificada en caliente
        $contenido_documento = "========================================================================\n";
        $contenido_documento .= "       SISTEMA DE GESTION DOCUMENTAL - FICHA DE CONTROL DE CALIDAD       \n";
        $contenido_documento .= "========================================================================\n\n";
        $contenido_documento .= "DETALLES DEL DOCUMENTO:\n";
        $contenido_documento .= "------------------------------------------------------------------------\n";
        $contenido_documento .= "ID Interno:       #" . $doc['iddocumento'] . "\n";
        $contenido_documento .= "Código:           " . $doc['codigo'] . "\n";
        $contenido_documento .= "Versión:          " . $doc['version'] . "\n";
        $contenido_documento .= "Título:           " . $doc['titulodocumento'] . "\n";
        $contenido_documento .= "Norma reguladora: " . $doc['idiso'] . "\n";
        $contenido_documento .= "Estado:           " . strtoupper($doc['estado']) . "\n";
        $contenido_documento .= "Empresa ID:       " . $doc['empresaid'] . "\n";
        $contenido_documento .= "Fecha descarga:   " . date('Y-m-d H:i:s') . "\n";
        $contenido_documento .= "Descargado por:   " . $_SESSION['idusuario'] . " (" . $_SESSION['nombreusuario'] . ")\n\n";
        $contenido_documento .= "------------------------------------------------------------------------\n";
        $contenido_documento .= "CONTENIDO CERTIFICADO Y REGLAMENTARIO:\n";
        $contenido_documento .= "------------------------------------------------------------------------\n";
        $contenido_documento .= "Este documento simula la descarga del archivo oficial correspondiente\n";
        $contenido_documento .= "al código " . $doc['codigo'] . " bajo la norma " . $doc['idiso'] . ".\n\n";
        $contenido_documento .= "El sistema ha registrado esta descarga en la tabla de logs de auditoría\n";
        $contenido_documento .= "de la empresa " . $doc['empresaid'] . " para fines de cumplimiento normativo.\n\n";
        $contenido_documento .= "PROHIBIDA SU REPRODUCCIÓN TOTAL O PARCIAL SIN AUTORIZACIÓN.\n";
        $contenido_documento .= "========================================================================\n";

        // Forzar descarga del archivo de texto
        header('Content-Description: File Transfer');
        header('Content-Type: text/plain; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $nombre_archivo_salida . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . strlen($contenido_documento));
        
        ob_clean();
        flush();
        echo $contenido_documento;
        exit();
    }

} catch (PDOException $e) {
    http_response_code(500);
    die("Error en el servidor al procesar la descarga: " . $e->getMessage());
}
