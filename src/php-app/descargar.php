<?php
session_start();
require_once 'conexion.php';

if (!isset($_GET['id'])) {
    die("ID no especificado.");
}

$id = $_GET['id'];

// Obtener la ruta del archivo
$stmt = $pdo->prepare("SELECT rutaarchivo, titulodocumento FROM documento WHERE iddocumento = :id");
$stmt->bindValue(':id', $id, PDO::PARAM_INT);
$stmt->execute();
$documento = $stmt->fetch(PDO::FETCH_ASSOC);

if ($documento && file_exists($documento['rutaarchivo'])) {
    // Configurar encabezados para forzar descarga
    header('Content-Description: File Transfer');
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="'.basename($documento['rutaarchivo']).'"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($documento['rutaarchivo']));
    
    // Leer el archivo y enviarlo al usuario
    readfile($documento['rutaarchivo']);
    exit;
} else {
    die("El archivo no existe.");
}
?>