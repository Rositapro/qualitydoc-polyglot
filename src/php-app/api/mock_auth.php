<?php
// API Simuladora de Autenticación Externa (Módulo de Universidad)
header("Content-Type: application/json; charset=UTF-8");

// Obtener datos recibidos (soporta POST tradicional y JSON)
$input_raw = file_get_contents("php://input");
$data = json_decode($input_raw, true);

if (!$data) {
    $data = $_POST;
}

$email = isset($data['email']) ? trim($data['email']) : '';
$password = isset($data['password']) ? trim($data['password']) : '';

// Validar que los campos no estén vacíos
if (empty($email) || empty($password)) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Faltan parámetros requeridos: email o password."
    ]);
    exit();
}

// Cuentas de usuario registradas con sus respectivas empresas y roles (Contraseña de prueba general: 12345)
$usuarios_permitidos = [
    'superadmin@qualitydoc.com' => [
        'password' => 'Document2026!',
        'idusuario' => 'superadmin',
        'nombreusuario' => 'Super Administrador',
        'rol' => 'superadmin',
        'empresaid' => 0
    ],
    'juan@empresa1.com' => [
        'password' => '12345',
        'idusuario' => 'juan',
        'nombreusuario' => 'Juan Pérez (Administrador)',
        'rol' => 'admin',
        'empresaid' => 1
    ],
    'ana@empresa1.com' => [
        'password' => '12345',
        'idusuario' => 'ana',
        'nombreusuario' => 'Ana Gómez (Colaborador)',
        'rol' => 'colaborador',
        'empresaid' => 1
    ],
    'carlos@empresa2.com' => [
        'password' => '12345',
        'idusuario' => 'carlos',
        'nombreusuario' => 'Carlos Ruíz (Administrador)',
        'rol' => 'admin',
        'empresaid' => 2
    ],
    'maria@empresa2.com' => [
        'password' => '12345',
        'idusuario' => 'maria',
        'nombreusuario' => 'María López (Colaborador)',
        'rol' => 'colaborador',
        'empresaid' => 2
    ]
];

$email_lower = strtolower($email);

// Validar credenciales
if (array_key_exists($email_lower, $usuarios_permitidos)) {
    $user_info = $usuarios_permitidos[$email_lower];
    
    // Comprobar contraseña
    if ($user_info['password'] === $password) {
        http_response_code(200);
        echo json_encode([
            "success" => true,
            "user" => [
                "idusuario" => $user_info['idusuario'],
                "nombreusuario" => $user_info['nombreusuario'],
                "rol" => $user_info['rol'],
                "empresaid" => $user_info['empresaid']
            ]
        ]);
        exit();
    }
} else {
    // Para facilitar pruebas dinámicas con otros usuarios académicos:
    // Si la contraseña es "12345" y el usuario no está en la lista de arriba, lo permitiremos asignando una empresa por defecto o según un dominio.
    if ($password === '12345') {
        // Por defecto, si el email contiene "empresa2", asignamos Empresa 2, de lo contrario Empresa 1
        $empresa_detectada = (strpos($email_lower, 'empresa2') !== false) ? 2 : 1;
        $username_parsed = explode('@', $email_lower)[0];
        
        http_response_code(200);
        echo json_encode([
            "success" => true,
            "user" => [
                "idusuario" => $username_parsed,
                "nombreusuario" => ucwords($username_parsed) . " (Invitado)",
                "rol" => "colaborador",
                "empresaid" => $empresa_detectada
            ]
        ]);
        exit();
    }
}

// Si la autenticación falla
http_response_code(401);
echo json_encode([
    "success" => false,
    "message" => "Credenciales incorrectas."
]);
exit();
