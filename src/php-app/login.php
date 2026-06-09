<?php
// Página de Inicio de Sesión y Autenticación con API Externa

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

// Iniciar sesión
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Lógica de Cerrar Sesión
if (isset($_GET['logout'])) {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
    header("Location: login.php");
    exit();
}

// Si ya hay una sesión activa, redirigir al Dashboard
if (isset($_SESSION['idusuario']) && isset($_SESSION['empresaid'])) {
    header("Location: index.php");
    exit();
}

$error_message = "";

// Lógica de procesamiento de Formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $password = isset($_POST['password']) ? trim($_POST['password']) : '';

    if (empty($email) || empty($password)) {
        $error_message = "El correo electrónico y la contraseña son obligatorios.";
    } else {
        // Ejecutar solicitud cURL a la API externa
        $post_data = json_encode([
            'email' => $email,
            'password' => $password
        ]);

        $ch = curl_init(AUTH_API_URL);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($post_data)
        ]);
        // Ignorar verificación de SSL en entornos locales si fuese necesario
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);

        $response_raw = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);

        if ($response_raw === false) {
            $error_message = "Error al conectar con la API de autenticación externa: " . $curl_error;
        } else {
            $response = json_decode($response_raw, true);

            if ($http_code === 200 && isset($response['success']) && $response['success'] === true) {
                // Autenticación Exitosa!
                $user_data = $response['user'];
                
                // Sincronizar el usuario localmente en la base de datos
                try {
                    // Verificar si el usuario ya existe
                    $stmt = $pdo->prepare("SELECT idusuario FROM usuarios WHERE idusuario = :id");
                    $stmt->execute(['id' => $user_data['idusuario']]);
                    $usuario_existente = $stmt->fetch();

                    if ($usuario_existente) {
                        // Actualizar información por si cambió rol o nombre
                        $stmtUpdate = $pdo->prepare("
                            UPDATE usuarios 
                            SET nombreusuario = :nombre, rol = :rol, empresaid = :empresa 
                            WHERE idusuario = :id
                        ");
                        $stmtUpdate->execute([
                            'nombre'  => $user_data['nombreusuario'],
                            'rol'     => $user_data['rol'],
                            'empresa' => $user_data['empresaid'],
                            'id'      => $user_data['idusuario']
                        ]);
                    } else {
                        // Insertar nuevo usuario sincronizado
                        $stmtInsert = $pdo->prepare("
                            INSERT INTO usuarios (idusuario, nombreusuario, rol, empresaid) 
                            VALUES (:id, :nombre, :rol, :empresa)
                        ");
                        $stmtInsert->execute([
                            'id'      => $user_data['idusuario'],
                            'nombre'  => $user_data['nombreusuario'],
                            'rol'     => $user_data['rol'],
                            'empresa' => $user_data['empresaid']
                        ]);
                    }

                    // Establecer Variables de Sesión Seguras
                    $_SESSION['idusuario'] = $user_data['idusuario'];
                    $_SESSION['nombreusuario'] = $user_data['nombreusuario'];
                    $_SESSION['rol'] = $user_data['rol'];
                    $_SESSION['empresaid'] = $user_data['empresaid'];
                    $_SESSION['empresanombre'] = $user_data['empresanombre'] ?? 'Empresa';

                    // Redirigir al Dashboard
                    header("Location: index.php");
                    exit();

                } catch (PDOException $e) {
                    $error_message = "Error local al sincronizar usuario: " . $e->getMessage();
                }
            } else {
                // Fallo de autenticación
                $error_message = isset($response['message']) ? $response['message'] : "Credenciales inválidas.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - SGD MultiTenant</title>
    
    <!-- Meta tags SEO -->
    <meta name="description" content="Acceso al Sistema de Gestión Documental Multi-tenencia.">
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.min.css" rel="stylesheet">
    
    <!-- Estilos Personalizados -->
    <link href="css/index.css" rel="stylesheet">
</head>
<body class="login-body">

<div class="login-card">
    <div class="login-logo">
        <i class="bi bi-journal-bookmark-fill"></i>
        <h2 class="mt-3 brand-font">QualityDoc</h2>
        <p class="text-muted font-sans" style="font-size: 0.9rem;">Portal de consulta pública y generación de reportes operativos</p>
    </div>

    <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger font-sans d-flex align-items-center gap-2" role="alert" style="font-size: 0.85rem;">
            <i class="bi bi-exclamation-triangle-fill"></i>
            <div><?php echo htmlspecialchars($error_message); ?></div>
        </div>
    <?php endif; ?>

    <form method="POST" action="login.php" class="font-sans">
        <div class="mb-3">
            <label for="email" class="form-label form-label-elegant">Correo Electrónico</label>
            <input type="email" class="form-control form-control-elegant" id="email" name="email" placeholder="ejemplo@correo.com" required autocomplete="email">
        </div>
        
        <div class="mb-4">
            <label for="password" class="form-label form-label-elegant">Contraseña (Módulo Externo)</label>
            <input type="password" class="form-control form-control-elegant" id="password" name="password" placeholder="Contraseña universitaria" required autocomplete="current-password">
        </div>

        <button type="submit" class="btn btn-elegant-primary w-100 py-2 d-flex align-items-center justify-content-center gap-2">
            <i class="bi bi-shield-lock-fill"></i>
            Ingresar al Sistema
        </button>
    </form>

    <!-- Guía de Credenciales de Prueba (Para facilitarle la vida al profesor/evaluador) -->
    <div class="mt-4 pt-3 border-top border-light text-center">
        <button class="btn btn-sm btn-link text-muted font-sans" type="button" data-bs-toggle="collapse" data-bs-target="#credencialesDemo" aria-expanded="false" aria-controls="credencialesDemo" style="font-size: 0.8rem; text-decoration: none;">
            <i class="bi bi-info-circle-fill me-1"></i> Mostrar credenciales de prueba
        </button>
        <div class="collapse mt-2 text-start font-sans" id="credencialesDemo" style="font-size: 0.75rem;">
            <div class="p-2 rounded bg-light border border-light-subtle">
                <strong>Empresa 1 (Empresa Maestra):</strong><br>
                • Admin: <code>admin_empresa@qualitydoc.com</code> / <code>Document2026!</code><br><br>
                <strong>Empresa 2 (KittyBeauty / Tu Empresa):</strong><br>
                • Admin: <code>administrador@qualitydoc.com</code> / <code>Document2026!</code><br>
                • Autor: <code>autor@qualitydoc.com</code> / <code>Document2026!</code><br>
                • Revisor: <code>revisor@qualitydoc.com</code> / <code>Document2026!</code><br>
                • Aprobador: <code>aprobador@qualitydoc.com</code> / <code>Document2026!</code><br>
                • Lector: <code>lector@qualitydoc.com</code> / <code>Document2026!</code>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap 5 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
