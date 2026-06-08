<?php
// Middleware de Gestión de Sesión Segura

if (session_status() === PHP_SESSION_NONE) {
    // Configuración de cookies de sesión para mayor seguridad
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    session_start();
}

// Obtener el nombre del archivo actual para evitar bucles de redirección
$pagina_actual = basename($_SERVER['PHP_SELF']);

// Páginas públicas que no requieren validación de sesión (ejemplo: login.php)
$paginas_publicas = ['login.php'];

$usuario_autenticado = isset($_SESSION['idusuario']) && isset($_SESSION['empresaid']);

if (!$usuario_autenticado) {
    // Si el usuario no está autenticado y está intentando acceder a una página privada
    if (!in_array($pagina_actual, $paginas_publicas)) {
        header("Location: login.php");
        exit();
    }
} else {
    // Si el usuario ya está autenticado e intenta acceder a la página de login, redirigir al dashboard
    if ($pagina_actual === 'login.php') {
        header("Location: index.php");
        exit();
    }
}
