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

if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'Administrador') {
    echo "<script>
            alert('Acceso denegado. La Configuración Institucional es exclusiva para la Dirección.');
            window.location.href = 'index.php';
          </script>";
    exit();
}

error_reporting(0);
ini_set('display_errors', 0);

include("conexion.php");
if ($conexion instanceof mysqli) {
    mysqli_set_charset($conexion, "utf8mb4");
}

$mensaje_conf = "";
$usuario_actual = $_SESSION['usuario'] ?? 'Administrador';

function registrarHistorialConfig($conexion, $descripcion, $usuario) {
    $fecha = date('Y-m-d H:i:s');
    if ($conexion instanceof PDO) {
        try {
            $stmt = $conexion->prepare("INSERT INTO historial (descripcion, usuario, fecha_hora) VALUES (:descripcion, :usuario, :fecha)");
            $stmt->execute([':descripcion' => $descripcion, ':usuario' => $usuario, ':fecha' => $fecha]);
        } catch (Exception $ex) {}
    } else {
        $desc_l = mysqli_real_escape_string($conexion, $descripcion);
        $usu_l = mysqli_real_escape_string($conexion, $usuario);
        @mysqli_query($conexion, "INSERT INTO historial (descripcion, usuario, fecha_hora) VALUES ('$desc_l', '$usu_l', '$fecha')");
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'inactivar_config') {
    if ($conexion instanceof PDO) {
        $stmt_inact = $conexion->prepare("INSERT INTO configuracion (clave, valor) VALUES ('estado', 'Inactivo') ON DUPLICATE KEY UPDATE valor = 'Inactivo'");
        $stmt_inact->execute();
    } else {
        mysqli_query($conexion, "INSERT INTO configuracion (clave, valor) VALUES ('estado', 'Inactivo') ON DUPLICATE KEY UPDATE valor = 'Inactivo'");
    }
    registrarHistorialConfig($conexion, "Inactivación de la configuración institucional", $usuario_actual);
    $mensaje_conf = "<div style='background-color: #fff3cd; color: #856404; padding: 10px; border-radius: 5px; margin-bottom: 15px;'>La configuración institucional ha sido marcada como inactiva.</div>";
}

$editando_conf = isset($_GET['editar_config']) && $_GET['editar_config'] == '1';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'guardar_config') {
    $nombre_escuela  = trim($_POST['nombre_escuela'] ?? '');
    $codigo_dea      = trim($_POST['codigo_dea'] ?? '');
    $periodo_escolar = trim($_POST['periodo_escolar'] ?? '');
    $direccion       = trim($_POST['direccion'] ?? '');
    $municipio       = trim($_POST['municipio'] ?? '');
    $estado_conf     = trim($_POST['estado_conf'] ?? 'Activo');

    if (!empty($nombre_escuela) && !empty($codigo_dea) && !empty($periodo_escolar) && !empty($municipio) && !empty($direccion) && !empty($estado_conf)) {
        if ($conexion instanceof PDO) {
            $stmt_del = $conexion->prepare("DELETE FROM configuracion WHERE clave IN ('nombre_escuela', 'codigo_dea', 'periodo_escolar', 'direccion', 'municipio', 'estado')");
            $stmt_del->execute();
            
            $stmt_ins = $conexion->prepare("INSERT INTO configuracion (clave, valor) VALUES (:clave, :valor)");
            $stmt_ins->execute([':clave' => 'nombre_escuela', ':valor' => $nombre_escuela]);
            $stmt_ins->execute([':clave' => 'codigo_dea', ':valor' => $codigo_dea]);
            $stmt_ins->execute([':clave' => 'periodo_escolar', ':valor' => $periodo_escolar]);
            $stmt_ins->execute([':clave' => 'direccion', ':valor' => $direccion]);
            $stmt_ins->execute([':clave' => 'municipio', ':valor' => $municipio]);
            $stmt_ins->execute([':clave' => 'estado', ':valor' => $estado_conf]);

            registrarHistorialConfig($conexion, "Modificación de la configuración institucional", $usuario_actual);
            $mensaje_conf = "<div style='color: #155724; background-color: #d4edda; padding: 10px; border-radius: 5px; margin-bottom: 15px;'>¡Configuración institucional guardada exitosamente!</div>";
            $editando_conf = false;
        } else {
            $n_esc = mysqli_real_escape_string($conexion, $nombre_escuela);
            $dea_esc = mysqli_real_escape_string($conexion, $codigo_dea);
            $per_esc = mysqli_real_escape_string($conexion, $periodo_escolar);
            $dir_esc = mysqli_real_escape_string($conexion, $direccion);
            $mun_esc = mysqli_real_escape_string($conexion, $municipio);
            $est_esc = mysqli_real_escape_string($conexion, $estado_conf);

            mysqli_query($conexion, "DELETE FROM configuracion WHERE clave IN ('nombre_escuela', 'codigo_dea', 'periodo_escolar', 'direccion', 'municipio', 'estado')");
            mysqli_query($conexion, "INSERT INTO configuracion (clave, valor) VALUES ('nombre_escuela', '$n_esc'), ('codigo_dea', '$dea_esc'), ('periodo_escolar', '$per_esc'), ('direccion', '$dir_esc'), ('municipio', '$mun_esc'), ('estado', '$est_esc')");

            registrarHistorialConfig($conexion, "Modificación de la configuración institucional", $usuario_actual);
            $mensaje_conf = "<div style='color: #155724; background-color: #d4edda; padding: 10px; border-radius: 5px; margin-bottom: 15px;'>¡Configuración institucional guardada exitosamente!</div>";
            $editando_conf = false;
        }
    }
}

$datos_conf = [];
if ($conexion instanceof PDO) {
    $res_conf = $conexion->query("SELECT clave, valor FROM configuracion");
    while($row = $res_conf->fetch(PDO::FETCH_ASSOC)) {
        $datos_conf[$row['clave']] = $row['valor'];
    }
} else {
    $res_conf = mysqli_query($conexion, "SELECT clave, valor FROM configuracion");
    if ($res_conf) {
        while($row = mysqli_fetch_assoc($res_conf)) {
            $datos_conf[$row['clave']] = $row['valor'];
        }
    }
}

$estado_actual_conf = $datos_conf['estado'] ?? 'Activo';
$tiene_config_datos = !empty($datos_conf['nombre_escuela']);

$val_nombre    = $editando_conf ? ($_POST['nombre_escuela'] ?? ($datos_conf['nombre_escuela'] ?? '')) : '';
$val_dea       = $editando_conf ? ($_POST['codigo_dea'] ?? ($datos_conf['codigo_dea'] ?? '')) : '';
$val_periodo   = $editando_conf ? ($_POST['periodo_escolar'] ?? ($datos_conf['periodo_escolar'] ?? '')) : '';
$val_municipio = $editando_conf ? ($_POST['municipio'] ?? ($datos_conf['municipio'] ?? '')) : '';
$val_direccion = $editando_conf ? ($_POST['direccion'] ?? ($datos_conf['direccion'] ?? '')) : '';
$val_estado    = $editando_conf ? ($_POST['estado_conf'] ?? ($datos_conf['estado'] ?? 'Activo')) : ($datos_conf['estado'] ?? 'Activo');

$es_administrador = isset($_SESSION['rol']) && $_SESSION['rol'] === 'Administrador';
$rol_actual = $_SESSION['rol'] ?? 'Administrador';
$archivo_destino_conf = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuración Institucional - Sistema de Inscripción</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --bg-main: #f8fafc;
            --bg-surface: #ffffff;
            --bg-surface-alt: #f1f5f9;
            --text-primary: #0f172a;
            --text-secondary: #475569;
            --border-color: #e2e8f0;
            --card-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }

        body.dark-mode {
            --bg-main: #090d16;
            --bg-surface: #131c2e;
            --bg-surface-alt: #1a2338;
            --text-primary: #f8fafc;
            --text-secondary: #94a3b8;
            --border-color: #27354f;
            --card-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
        }

        body.dark-mode .card, body.dark-mode .top-header-bar, body.dark-mode table {
            background-color: var(--bg-surface) !important; border-color: var(--border-color) !important; color: var(--text-primary) !important;
        }

        body.dark-mode th { background-color: #1e293b !important; color: var(--text-primary) !important; border-color: var(--border-color) !important; }
        body.dark-mode td { background-color: var(--bg-surface) !important; color: var(--text-primary) !important; border-color: var(--border-color) !important; }
        body.dark-mode tr:nth-child(even) td { background-color: var(--bg-surface-alt) !important; }
        body.dark-mode tr:hover td { background-color: #22304a !important; }
        body.dark-mode input, body.dark-mode select, body.dark-mode textarea { background-color: var(--bg-surface-alt) !important; color: var(--text-primary) !important; border-color: var(--border-color) !important; }

        html, body {
            min-height: 100vh; margin: 0; padding: 0;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            background-color: var(--bg-main); color: var(--text-primary);
            display: flex; width: 100%; transition: background-color 0.25s ease, color 0.25s ease;
        }

        .main-content {
            flex: 1; min-width: 0; padding: 16px 20px 24px 20px;
            box-sizing: border-box; position: relative; z-index: 1;
            align-self: stretch; background-color: var(--bg-main);
        }

        .top-header-bar {
            background-color: var(--bg-surface); padding: 10px 18px;
            border-radius: 12px; border: 1px solid var(--border-color);
            box-shadow: var(--card-shadow); display: flex;
            justify-content: space-between; align-items: center;
            margin-bottom: 16px; flex-wrap: wrap; gap: 10px;
        }
        .top-breadcrumb { display: flex; align-items: center; gap: 8px; font-size: 0.85rem; font-weight: 600; color: var(--text-secondary); }
        .top-actions { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
        
        .tool-action-btn {
            display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px;
            border-radius: 8px; font-size: 0.78rem; font-weight: 600; text-decoration: none;
            border: 1px solid var(--border-color); background-color: var(--bg-surface-alt);
            color: var(--text-primary); transition: all 0.2s ease; cursor: pointer;
        }
        .tool-action-btn.btn-refresh-cache { background: rgba(37, 99, 235, 0.12); color: #2563eb; border-color: rgba(37, 99, 235, 0.3); }
        .tool-action-btn.btn-refresh-cache:hover { background: #2563eb; color: #ffffff; }

        .btn-theme-switch {
            width: 34px; height: 34px; border-radius: 50%;
            border: 1px solid var(--border-color); background-color: var(--bg-surface-alt);
            color: var(--text-secondary); display: flex; align-items: center; justify-content: center; cursor: pointer;
        }
        .user-profile-pill {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 5px 12px; border-radius: 50rem;
            background-color: var(--bg-surface-alt); border: 1px solid var(--border-color);
            font-size: 0.8rem; font-weight: 600; color: var(--text-primary);
        }
        .role-badge { padding: 2px 8px; border-radius: 5px; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; }
        .role-badge.badge-admin { background-color: rgba(239, 68, 68, 0.15); color: #ef4444; }
        .role-badge.badge-coord { background-color: rgba(59, 130, 246, 0.15); color: #3b82f6; }

        .card { background: #FFFFFF; padding: 20px; border-radius: 12px; box-shadow: var(--card-shadow); margin-bottom: 20px; width: 100%; box-sizing: border-box; border: 1px solid var(--border-color); }
        .card h2 { font-size: 1.15rem; font-weight: 700; margin-bottom: 5px; color: var(--text-primary); }
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 15px; margin-bottom: 15px; width: 100%; box-sizing: border-box; }
        .form-group { display: flex; flex-direction: column; width: 100%; box-sizing: border-box; }
        .form-group label { font-size: 0.85rem; font-weight: 600; margin-bottom: 5px; color: var(--text-secondary); }
        .form-group input, .form-group textarea, .form-group select { padding: 9px 12px; border: 1px solid var(--border-color); border-radius: 8px; font-size: 0.9rem; width: 100%; box-sizing: border-box; background-color: var(--bg-surface); color: var(--text-primary); }
        .btn-guardar { background-color: <?php echo $editando_conf ? '#f59e0b' : '#2563eb'; ?>; color: white; border: none; padding: 9px 18px; border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 0.9rem; }
        .btn-modificar { background-color: #f59e0b; color: white; border: none; padding: 5px 10px; border-radius: 6px; cursor: pointer; font-size: 0.78rem; font-weight: 600; text-decoration: none; display: inline-block; }
        .btn-inactivar { background-color: #d97706; color: white; border: none; padding: 5px 10px; border-radius: 6px; cursor: pointer; font-size: 0.78rem; font-weight: 600; }
        .table-responsive { width: 100%; overflow-x: auto; border: 1px solid var(--border-color); border-radius: 8px; margin-top: 15px; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; background-color: var(--bg-surface); color: var(--text-primary); }
        th, td { border: 1px solid var(--border-color); padding: 10px; text-align: left; }
        th { background-color: #f1f5f9; color: var(--text-primary); font-weight: 700; position: sticky; top: 0; z-index: 10; white-space: nowrap; }
        tr:nth-child(even) { background-color: var(--bg-surface-alt); }
        tr:hover { background-color: rgba(37, 99, 235, 0.04); }

        @media (max-width: 768px) { html, body { flex-direction: column; } .main-content { padding: 12px; } }
    </style>
</head>
<body>

    <?php include("menu.php"); ?>

    <main class="main-content">
        <div class="top-header-bar">
            <div class="top-breadcrumb">
                <i class="fas fa-school" style="color: #2563eb;"></i>
                <span>Configuración Institucional</span>
            </div>

            <div class="top-actions">
                <button type="button" class="tool-action-btn btn-refresh-cache" onclick="actualizarDashboard()" title="Actualizar datos del sistema">
                    <i class="fas fa-sync-alt" id="refreshIcon"></i> Actualizar Sistema
                </button>

                <button type="button" class="btn-theme-switch" onclick="toggleDashboardTheme()" title="Cambiar Modo Claro / Oscuro">
                    <i class="fas fa-moon" id="dashThemeIcon"></i>
                </button>

                <div class="user-profile-pill">
                    <i class="fas fa-user-circle" style="color: #2563eb;"></i>
                    <span><?php echo htmlspecialchars($_SESSION['usuario'] ?? 'Usuario'); ?></span>
                    <span class="role-badge <?php echo $es_administrador ? 'badge-admin' : 'badge-coord'; ?>">
                        <?php echo $es_administrador ? 'Admin' : htmlspecialchars($rol_actual); ?>
                    </span>
                </div>
            </div>
        </div>

        <?php if (!empty($mensaje_conf)) echo $mensaje_conf; ?>

        <div class="card">
            <h2><i class="fas fa-school text-primary" style="color: #2563eb;"></i> <?php echo $editando_conf ? 'Modificar Datos Institucionales' : 'Configuración de Datos Institucionales'; ?></h2>
            <p class="text-muted" style="color: var(--text-secondary);">Actualice la información oficial de la escuela que aparecerá reflejada en los encabezados y documentos del sistema.</p>
            <hr style="margin-bottom: 20px; border: 0; border-top: 1px solid var(--border-color);">

            <form method="POST" action="<?php echo $archivo_destino_conf; ?>">
                <input type="hidden" name="accion" value="guardar_config">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label>Nombre Oficial de la Institución (*):</label>
                        <input type="text" name="nombre_escuela" required value="<?php echo htmlspecialchars($val_nombre); ?>" placeholder="Ej: Escuela Bolivariana Gladys Carnevali">
                    </div>
                    <div class="form-group">
                        <label>Código DEA / Estadístico (*):</label>
                        <input type="text" name="codigo_dea" required value="<?php echo htmlspecialchars($val_dea); ?>" placeholder="Ej: OD01234567">
                    </div>
                    <div class="form-group">
                        <label>Periodo Escolar Activo (*):</label>
                        <input type="text" name="periodo_escolar" required value="<?php echo htmlspecialchars($val_periodo); ?>" placeholder="Ej: 2026-2027">
                    </div>
                    <div class="form-group">
                        <label>Municipio / Localidad (*):</label>
                        <input type="text" name="municipio" required value="<?php echo htmlspecialchars($val_municipio); ?>" placeholder="Ej: Municipio Libertador">
                    </div>
                    <div class="form-group">
                        <label>Estado del Plantel (*):</label>
                        <select name="estado_conf" required>
                            <option value="">Seleccione un estado...</option>
                            <option value="Activo" <?php if($val_estado=='Activo') echo 'selected'; ?>>Activo</option>
                            <option value="Inactivo" <?php if($val_estado=='Inactivo') echo 'selected'; ?>>Inactivo</option>
                        </select>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 20px;">
                    <label>Dirección Completa del Plantel (*):</label>
                    <textarea name="direccion" rows="3" required placeholder="Ubicación exacta de la escuela..."><?php echo htmlspecialchars($val_direccion); ?></textarea>
                </div>

                <div style="display: flex; gap: 10px;">
                    <button type="submit" class="btn-guardar">
                        <i class="fas fa-save"></i> <?php echo $editando_conf ? 'Guardar Cambios' : 'Guardar Configuración'; ?>
                    </button>
                    <?php if ($editando_conf): ?>
                        <a href="<?php echo $archivo_destino_conf; ?>" style="background: #64748b; color: white; padding: 9px 18px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 0.9rem; display: inline-flex; align-items: center; gap: 6px;">
                            <i class="fas fa-times"></i> Cancelar
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <div class="card">
            <h2><i class="fas fa-list-alt text-primary" style="color: #2563eb;"></i> Listado de Datos Institucionales Guardados</h2>
            <div class="table-responsive">
                <table>
                    <tr>
                        <th style="text-align: center; width: 170px;">Acción</th>
                        <th>Nombre Oficial de la Institución</th>
                        <th>Código DEA / Estadístico</th>
                        <th>Periodo Escolar Activo</th>
                        <th>Municipio / Localidad</th>
                        <th>Dirección Completa del Plantel</th>
                        <th>Estatus</th>
                    </tr>
                    <?php if ($tiene_config_datos): 
                        $badge_conf_color = ($estado_actual_conf === 'Activo') ? 'background:rgba(16, 185, 129, 0.15); color:#10b981;' : 'background:rgba(217, 119, 6, 0.15); color:#d97706;';
                    ?>
                    <tr>
                        <td style="text-align: center; display: flex; gap: 5px; justify-content: center;">
                            <a href="<?php echo $archivo_destino_conf; ?>?editar_config=1" class="btn-modificar" title="Modificar">
                                <i class="fas fa-edit"></i> Modificar
                            </a>
                            <?php if ($estado_actual_conf === 'Activo'): ?>
                            <form method="POST" action="<?php echo $archivo_destino_conf; ?>" style="margin:0;">
                                <input type="hidden" name="accion" value="inactivar_config">
                                <button type="submit" class="btn-inactivar" onclick="return confirm('¿Está seguro de inactivar la configuración institucional?');" title="Inactivar">
                                    <i class='fas fa-user-slash'></i> Inactivar
                                </button>
                            </form>
                            <?php else: ?>
                            <span style="font-size:0.75rem; color:var(--text-secondary); align-self:center;">Inactivo</span>
                            <?php endif; ?>
                        </td>
                        <td><strong><?php echo htmlspecialchars($datos_conf['nombre_escuela'] ?? ''); ?></strong></td>
                        <td><span style="background:rgba(37, 99, 235, 0.15); color:#2563eb; padding:3px 8px; border-radius:4px; font-weight:600;"><?php echo htmlspecialchars($datos_conf['codigo_dea'] ?? ''); ?></span></td>
                        <td><strong><?php echo htmlspecialchars($datos_conf['periodo_escolar'] ?? ''); ?></strong></td>
                        <td><?php echo htmlspecialchars($datos_conf['municipio'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($datos_conf['direccion'] ?? ''); ?></td>
                        <td><span style="<?php echo $badge_conf_color; ?> padding:3px 8px; border-radius:4px; font-weight:600;"><?php echo htmlspecialchars($estado_actual_conf); ?></span></td>
                    </tr>
                    <?php else: ?>
                    <tr>
                        <td colspan="7" style="text-align: center; color: var(--text-secondary); padding: 20px;">
                            No hay configuración institucional registrada en el sistema.
                        </td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function applyDashboardTheme() {
            const savedTheme = localStorage.getItem('sige_theme');
            const icon = document.getElementById('dashThemeIcon');
            if (savedTheme === 'dark') {
                document.body.classList.add('dark-mode');
                if (icon) { icon.classList.remove('fa-moon'); icon.classList.add('fa-sun'); }
            }
        }
        applyDashboardTheme();

        function toggleDashboardTheme() {
            const isDark = document.body.classList.toggle('dark-mode');
            const icon = document.getElementById('dashThemeIcon');
            localStorage.setItem('sige_theme', isDark ? 'dark' : 'light');
            if (isDark) { icon.classList.remove('fa-moon'); icon.classList.add('fa-sun'); }
            else { icon.classList.remove('fa-sun'); icon.classList.add('fa-moon'); }
        }

        function actualizarDashboard() {
            const icon = document.getElementById('refreshIcon');
            if (icon) { icon.classList.add('fa-spin'); }
            setTimeout(() => { window.location.reload(); }, 450);
        }
    </script>
</body>
</html>