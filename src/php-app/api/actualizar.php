<?php
// api/actualizar.php

// 1. Conexión a la base de datos
require '../conexion.php';

// 2. Cabeceras JSON
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

// 3. Leer el paquete de Odeth
$datosJSON = file_get_contents("php://input");
$data = json_decode($datosJSON);

// 4. Verificar que Odeth nos mande el ID viejo y los datos del nuevo
if (
    !empty($data->iddocumento_viejo) && 
    !empty($data->iddocumento_nuevo) && 
    !empty($data->titulodocumento) && 
    !empty($data->codigo) && 
    !empty($data->version) && 
    !empty($data->rutaarchivo) && 
    !empty($data->empresaid)
) {
    try {
        // 5. INICIAR TRANSACCIÓN: Si uno de los dos pasos falla, se cancela todo por seguridad.
        $pdo->beginTransaction();

        // --- PASO A: Jubilar el documento viejo ---
        $sqlObsoleto = "UPDATE documento SET estado = 'obsoleto' WHERE iddocumento = :iddocumento_viejo";
        $stmtObsoleto = $pdo->prepare($sqlObsoleto);
        $stmtObsoleto->bindParam(':iddocumento_viejo', $data->iddocumento_viejo, PDO::PARAM_INT);
        $stmtObsoleto->execute();

        // --- PASO B: Dar de alta el documento nuevo ---
        $sqlNuevo = "INSERT INTO documento (iddocumento, titulodocumento, codigo, version, rutaarchivo, empresaid, estado)
                     VALUES (:iddocumento_nuevo, :titulodocumento, :codigo, :version, :rutaarchivo, :empresaid, 'vigente')";
        $stmtNuevo = $pdo->prepare($sqlNuevo);
        $stmtNuevo->bindParam(':iddocumento_nuevo', $data->iddocumento_nuevo, PDO::PARAM_INT);
        $stmtNuevo->bindParam(':titulodocumento', $data->titulodocumento);
        $stmtNuevo->bindParam(':codigo', $data->codigo);
        $stmtNuevo->bindParam(':version', $data->version, PDO::PARAM_INT);
        $stmtNuevo->bindParam(':rutaarchivo', $data->rutaarchivo);
        $stmtNuevo->bindParam(':empresaid', $data->empresaid, PDO::PARAM_INT);
        $stmtNuevo->execute();

        // 6. CONFIRMAR TRANSACCIÓN: Todo salió bien, guardamos los cambios.
        $pdo->commit();

        http_response_code(201); // Creado con éxito
        echo json_encode(["mensaje" => "Actualización exitosa: Versión anterior marcada como obsoleta y nueva versión vigente."]);

    } catch (PDOException $e) {
        // 7. DESHACER TRANSACCIÓN: Hubo un error, no guardamos datos a medias.
        $pdo->rollBack();
        
        http_response_code(500); // Error del servidor
        echo json_encode(["error" => "Error interno al actualizar versiones: " . $e->getMessage()]);
    }
} else {
    // Si Odeth envía mal los datos
    http_response_code(400); 
    echo json_encode(["error" => "Datos incompletos. Se requiere el ID viejo y los datos del documento nuevo."]);
}
?>