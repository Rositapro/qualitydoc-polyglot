<?php
// api/recibir.php

// 1. Subimos un nivel en las carpetas para encontrar el archivo de conexión
require '../conexion.php'; 

// 2. Configuramos las cabeceras para indicarle a .NET que recibimos y respondemos en JSON
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

// 3. Leer el archivo JSON que envía Odeth
$datosJSON = file_get_contents("php://input");
$data = json_decode($datosJSON);

// 4. Verificar que Odeth nos esté enviando todos los datos necesarios
if (
    !empty($data->iddocumento) && 
    !empty($data->titulodocumento) && 
    !empty($data->codigo) && 
    !empty($data->version) && 
    !empty($data->rutaarchivo) && 
    !empty($data->empresaid)
) {
    try {
        // 5. Preparar la consulta SQL en minúsculas estrictas para PostgreSQL
        $sql = "INSERT INTO documento (iddocumento, titulodocumento, codigo, version, rutaarchivo, empresaid, estado)
                VALUES (:iddocumento, :titulodocumento, :codigo, :version, :rutaarchivo, :empresaid, 'vigente')";
        
        $stmt = $pdo->prepare($sql);
        
        // 6. Asignar los valores que vienen del JSON a la consulta SQL
        $stmt->bindParam(':iddocumento', $data->iddocumento, PDO::PARAM_INT);
        $stmt->bindParam(':titulodocumento', $data->titulodocumento);
        $stmt->bindParam(':codigo', $data->codigo);
        $stmt->bindParam(':version', $data->version, PDO::PARAM_INT);
        $stmt->bindParam(':rutaarchivo', $data->rutaarchivo);
        $stmt->bindParam(':empresaid', $data->empresaid, PDO::PARAM_INT);
        
        // 7. Ejecutar y responder a .NET
        if ($stmt->execute()) {
            http_response_code(201); // Código HTTP 201: Creado con éxito
            echo json_encode(["mensaje" => "Transmisión exitosa: Documento recibido y guardado en PostgreSQL."]);
        } else {
            http_response_code(503); // Código HTTP 503: Servicio no disponible
            echo json_encode(["error" => "No se pudo registrar el documento en la base de datos."]);
        }
        
    } catch (PDOException $e) {
        http_response_code(500); // Código HTTP 500: Error interno del servidor
        echo json_encode(["error" => "Error interno en el contenedor de BD: " . $e->getMessage()]);
    }
} else {
    // Si Odeth envía el JSON incompleto
    http_response_code(400); // Código HTTP 400: Mala petición
    echo json_encode(["error" => "Datos incompletos. Faltan atributos en el JSON enviado desde .NET."]);
}
?>