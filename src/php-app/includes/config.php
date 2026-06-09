<?php
// Configuración Global de la Aplicación

// Evitar inclusión directa
if (basename($_SERVER['PHP_SELF']) == 'config.php') {
    header("HTTP/1.1 403 Forbidden");
    exit("Acceso denegado.");
}

// Configuración de la base de datos (obtenida de las variables de entorno de Docker)
define('DB_HOST', getenv('DB_HOST') ?: 'db');
define('DB_PORT', getenv('DB_PORT') ?: '5432');
define('DB_NAME', getenv('DB_NAME') ?: 'sgd_db');
define('DB_USER', getenv('DB_USER') ?: 'sgd_user');
define('DB_PASSWORD', getenv('DB_PASSWORD') ?: 'sgd_password');

// URL del Endpoint Externo para Validación de Login (cURL)
// Dentro de Docker, el servicio web se comunica consigo mismo mediante http://web/api/mock_auth.php
define('AUTH_API_URL', getenv('AUTH_API_URL') ?: 'http://web/api/mock_auth.php');

// URL del Microservicio de Búsqueda de MongoDB (Node.js)
define('SEARCH_API_URL', getenv('SEARCH_API_URL') ?: 'http://search-service:3000/api/documents/search');

// Habilitar reporte de errores en desarrollo
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
