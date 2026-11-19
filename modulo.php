<?php
session_start();
if (isset($_SESSION['usuario'])) {
    if (!isset($_SESSION['init'])) {
        session_regenerate_id(true);
        $_SESSION['init'] = true;
    }
}
if (!isset($_SESSION['autenticado']) || $_SESSION['autenticado'] !== true) {
    header("Location: procesar.php");
    exit();
}

include("conexion.php");
if ($conexion instanceof mysqli) {
    mysqli_set_charset($conexion, "utf8mb4");
}

$es_administrador = isset($_SESSION['rol']) && $_SESSION['rol'] === 'Administrador';
$rol_actual = $_SESSION['rol'] ?? 'Administrador';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Módulo - Sistema de Inscripción</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        html, body { min-height: 100%; margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #F4F7F6; color: #212529; display: flex; width: 100%; }
        .main-content { flex: 1; min-width: 0; padding: 20px 30px; background-color: #F4F7F6; box-sizing: border-box; }
        .card { background: #FFFFFF; padding: 25px; border-radius: 10px; box-shadow: 0 2px 5px rgba(0,0,0,0.03); margin-bottom: 25px; width: 100%; box-sizing: border-box; }
        @media (max-width: 768px) { html, body { flex-direction: column; } .main-content { padding: 15px; } }
    </style>
</head>
<body>

    <?php include("menu.php"); ?>

    <main class="main-content">
        <div class="card">
            <h2><i class="fas fa-folder-open text-primary"></i> Módulo del Sistema</h2>
            <p class="text-muted">Seleccione una opción del panel izquierdo para operar dentro del sistema institucional.</p>
        </div>
    </main>

    <script>
        function toggleSubmenu(e) {
            e.preventDefault();
            const submenu = document.getElementById('alumnosSubmenu');
            submenu.classList.toggle('show');
        }
    </script>
</body>
</html>