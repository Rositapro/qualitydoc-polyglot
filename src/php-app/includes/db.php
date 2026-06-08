<?php
// Conexión Centralizada a la Base de Datos PostgreSQL

require_once __DIR__ . '/config.php';

try {
    $dsn = sprintf(
        "pgsql:host=%s;port=%s;dbname=%s;options='--client_encoding=UTF8'",
        DB_HOST,
        DB_PORT,
        DB_NAME
    );
    
    $pdo = new PDO($dsn, DB_USER, DB_PASSWORD, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    // En producción no deberíamos mostrar el mensaje directo del error, pero para este proyecto escolar
    // mostraremos un mensaje claro que facilite la depuración.
    die("Error de conexión a la base de datos: " . $e->getMessage());
}
