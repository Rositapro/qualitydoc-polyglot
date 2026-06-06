<?php
session_start();
require_once 'conexion.php';

if (!isset($_GET['id'])) {
    die("ID de documento no especificado.");
}

$id = $_GET['id'];

// Consultar la ruta del archivo usando PDO
$stmt = $pdo->prepare("SELECT rutaarchivo FROM documento WHERE iddocumento = :id");
$stmt->bindValue(':id', $id, PDO::PARAM_INT);
$stmt->execute();
$documento = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$documento) {
    die("Documento no encontrado.");
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Visor de Documentos</title>
</head>
<body style="margin:0; padding:0; height:100vh;">
    <iframe src="<?= htmlspecialchars($documento['rutaarchivo']) ?>" width="100%" height="100%"></iframe>
</body>
</html>