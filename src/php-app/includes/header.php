<?php
// Header Global del Portal - SGD

require_once __DIR__ . '/session.php';
require_once __DIR__ . '/db.php';

$pagina_actual = basename($_SERVER['PHP_SELF']);
$nombre_usuario = $_SESSION['nombreusuario'] ?? 'Usuario';
$rol_usuario = $_SESSION['rol'] ?? 'Colaborador';
$empresa_id = $_SESSION['empresaid'] ?? 0;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SGD - Sistema de Gestión Documental</title>
    
    <!-- Meta tags SEO -->
    <meta name="description" content="Sistema de Gestión Documental multi-tenencia académico.">
    
    <!-- Bootstrap 5 CSS (CDN) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons (CDN) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.min.css" rel="stylesheet">
    
    <!-- Estilos Personalizados (Café y Beige con Tipografía Elegante) -->
    <link href="css/index.css" rel="stylesheet">
</head>
<body>

<div class="app-container">
    <!-- Sidebar / Barra Lateral -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <a href="index.php" class="sidebar-brand">
                <i class="bi bi-journal-bookmark-fill"></i>
                <span class="brand-font">QualityDoc</span>
            </a>
        </div>
        
        <nav class="flex-grow-1">
            <ul class="nav-menu">
                <li>
                    <a href="index.php" class="nav-item-link <?php echo ($pagina_actual == 'index.php') ? 'active' : ''; ?>">
                        <i class="bi bi-house-door"></i>
                        <span>Inicio</span>
                    </a>
                </li>
                <li>
                    <a href="documentos.php" class="nav-item-link <?php echo ($pagina_actual == 'documentos.php' || $pagina_actual == 'visor.php' || $pagina_actual == 'sugerencia.php') ? 'active' : ''; ?>">
                        <i class="bi bi-folder2-open"></i>
                        <span>Documentos</span>
                    </a>
                </li>
                <li>
                    <a href="logs.php" class="nav-item-link <?php echo ($pagina_actual == 'logs.php') ? 'active' : ''; ?>">
                        <i class="bi bi-shield-check"></i>
                        <span>Auditoría (Logs)</span>
                    </a>
                </li>
                <li>
                    <a href="reportes.php" class="nav-item-link <?php echo ($pagina_actual == 'reportes.php') ? 'active' : ''; ?>">
                        <i class="bi bi-bar-chart-line"></i>
                        <span>Reportes</span>
                    </a>
                </li>
            </ul>
        </nav>
        
        <!-- Pie de la barra lateral (Información del Tenant y Cierre de Sesión) -->
        <div class="border-top border-secondary pt-3 mt-auto">
            <div class="mb-3">
                <small class="text-muted d-block font-sans" style="font-size: 0.75rem; letter-spacing: 0.05em; text-transform: uppercase;">
                    Empresa Activa
                </small>
                <div class="d-flex align-items-center gap-2 mt-1">
                    <span class="badge bg-warning text-dark font-sans" style="font-size: 0.85rem;">
                        ID: <?php echo htmlspecialchars($empresa_id); ?>
                    </span>
                    <small class="text-light font-sans" style="font-size: 0.85rem;">
                        <?php 
                        $empresa_nombre = $_SESSION['empresanombre'] ?? ($empresa_id == 2 ? 'KittyBeauty' : ($empresa_id == 1 ? 'Empresa Maestra' : 'Tenant Corporativo'));
                        echo htmlspecialchars($empresa_nombre); 
                        ?>
                    </small>
                </div>
            </div>
            <a href="login.php?logout=1" class="btn btn-sm btn-outline-danger w-100 font-sans d-flex align-items-center justify-content-center gap-2">
                <i class="bi bi-box-arrow-right"></i>
                Cerrar Sesión
            </a>
        </div>
    </aside>

    <!-- Contenido Principal -->
    <main class="main-content">
        <!-- Encabezado Superior -->
        <header class="top-header">
            <h2 class="top-header-title">
                <?php
                switch ($pagina_actual) {
                    case 'index.php':
                        echo "Inicio";
                        break;
                    case 'documentos.php':
                        echo "Panel de Control";
                        break;
                    case 'logs.php':
                        echo "Registro de Auditoría";
                        break;
                    case 'visor.php':
                        echo "Visor de Documentos";
                        break;
                    case 'sugerencia.php':
                        echo "Emitir Sugerencia";
                        break;
                    case 'reportes.php':
                        echo "Reportes Operativos";
                        break;
                    default:
                        echo "Sistema de Gestión Documental";
                }
                ?>
            </h2>
            
            <div class="user-profile-badge">
                <i class="bi bi-person-circle"></i>
                <span><strong><?php echo htmlspecialchars($nombre_usuario); ?></strong></span>
                <span class="badge bg-secondary font-sans"><?php echo htmlspecialchars(ucfirst($rol_usuario)); ?></span>
            </div>
        </header>
        
        <!-- Contenedor del Cuerpo de la Página -->
        <div class="container-fluid p-4">
