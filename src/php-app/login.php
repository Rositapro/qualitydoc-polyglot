<?php
session_start();
require_once 'conexion.php';

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $correo = $_POST['usuario'] ?? '';
    $password = $_POST['password'] ?? '';

    // Llamar a la API de Odeth
    $url = "http://web-php/api/simulador_odeth.php"; 
    $data = json_encode(['correo' => $correo, 'password' => $password]);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode == 200) {
        $usuarioOdeth = json_decode($response, true);
        
        // Sincronizar en tu base local
        $stmt = $pdo->prepare("INSERT INTO usuarios (idusuario, nombreusuario, rol, empresaid) 
                                VALUES (:id, :nombre, :rol, 1) 
                                ON CONFLICT (idusuario) DO UPDATE 
                                SET nombreusuario = :nombre, rol = :rol");
        
        $stmt->execute([
            ':id' => $usuarioOdeth['idusuario'],
            ':nombre' => $usuarioOdeth['nombre'],
            ':rol' => $usuarioOdeth['rol']
        ]);

        $_SESSION['usuario'] = $usuarioOdeth['nombre'];
        $_SESSION['idusuario'] = $usuarioOdeth['idusuario'];
        $_SESSION['rol'] = $usuarioOdeth['rol'];
        $_SESSION['empresaid'] = 1;

        header("Location: index.php");
        exit();
    } else {
        $error = "Credenciales incorrectas o error en el servicio de Odeth.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Login - QualityDoc</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light d-flex align-items-center justify-content-center" style="height: 100vh;">
    <div class="card p-4 shadow" style="width: 350px;">
        <h3 class="text-center">QualityDoc</h3>
        <?php if($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>
        <form method="POST">
            <div class="mb-3">
                <label>Usuario / Correo</label>
                <input type="text" name="usuario" class="form-control" required>
            </div>
            <div class="mb-3">
                <label>Contraseña</label>
                <input type="password" name="password" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-primary w-100">Entrar</button>
        </form>
    </div>
</body>
</html>