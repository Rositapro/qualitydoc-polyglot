<?php
// 1. INICIAR SESIÓN Y VALIDAR SEGURIDAD (Primero que todo)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['idusuario'])) {
    header("Location: login.php");
    exit();
}

// 2. INCLUIR CONEXIÓN Y HEADER (Header va después de la lógica de redirección)
require_once 'conexion.php';
require_once 'includes/header.php';

// 3. CAPTURA DE FILTROS
$empresaid = $_SESSION['empresaid'] ?? 1;
$idiso = isset($_GET['idiso']) ? $_GET['idiso'] : '';
$estado = isset($_GET['estado']) ? $_GET['estado'] : '';

// 4. CONSULTA SQL DINÁMICA
$sql = "SELECT d.iddocumento, d.titulodocumento, d.codigo, d.version, i.nombreiso, d.estado 
        FROM documento d 
        JOIN iso i ON d.idiso = i.idiso 
        WHERE d.empresaid = :empresaid";

if (!empty($idiso)) $sql .= " AND d.idiso = :idiso";
if (!empty($estado)) $sql .= " AND d.estado = :estado";

$stmt = $pdo->prepare($sql);
$stmt->bindValue(':empresaid', $empresaid, PDO::PARAM_INT);
if (!empty($idiso)) $stmt->bindValue(':idiso', $idiso, PDO::PARAM_INT);
if (!empty($estado)) $stmt->bindValue(':estado', $estado, PDO::PARAM_STR);
$stmt->execute();
$documentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container-fluid">
    <h1 class="mb-4">Consulta de Documentos</h1>
    
    <form method="GET" class="row g-3 mb-4">
        <div class="col-auto">
            <select name="idiso" class="form-select" onchange="this.form.submit()">
                <option value="">Todas las ISOs</option>
            </select>
        </div>
        <div class="col-auto">
            <select name="estado" class="form-select" onchange="this.form.submit()">
                <option value="">Cualquier estado</option>
                <option value="vigente" <?php if($estado == 'vigente') echo 'selected'; ?>>Vigente</option>
                <option value="obsoleto" <?php if($estado == 'obsoleto') echo 'selected'; ?>>Obsoleto</option>
            </select>
        </div>
    </form>

    <table class="table table-hover">
        <thead class="table-dark">
            <tr>
                <th>Documento</th>
                <th>Código</th>
                <th>ISO</th>
                <th>Estado</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($documentos as $doc): ?>
            <tr>
                <td><?= htmlspecialchars($doc['titulodocumento']) ?></td>
                <td><?= htmlspecialchars($doc['codigo']) ?></td>
                <td><?= htmlspecialchars($doc['nombreiso']) ?></td>
                <td><?= htmlspecialchars($doc['estado']) ?></td>
                <td>
                    <a href="visor.php?id=<?= $doc['iddocumento'] ?>" class="btn btn-sm btn-primary" target="_blank">Ver</a>
                    <a href="descargar.php?id=<?= $doc['iddocumento'] ?>" class="btn btn-sm btn-success">Descargar</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php 
// 5. CERRAR CON FOOTER
require_once 'includes/footer.php'; 
?>