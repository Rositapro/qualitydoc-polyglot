<?php
// api/simulador_odeth.php
header("Content-Type: application/json; charset=UTF-8");

// 1. Leemos el correo y contraseña que manda tu login.php
$datos = json_decode(file_get_contents("php://input"));

if (!empty($datos->correo) && !empty($datos->password)) {
    // 2. Simulamos que la base de datos de Odeth encontró al usuario
    http_response_code(200); // 200 = OK
    echo json_encode([
        "idusuario" => 99,
        "nombre" => "Rosalinda (Simulada por API)",
        "rol" => "Administrador"
    ]);
} else {
    // Si mandas datos vacíos
    http_response_code(400); // 400 = Error
    echo json_encode(["error" => "Credenciales vacías"]);
}
?>