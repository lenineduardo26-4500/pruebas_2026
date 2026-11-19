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
            alert('Acceso denegado. El Mantenimiento es exclusivo para la Dirección.');
            window.location.href = 'index.php';
          </script>";
    exit();
}

error_reporting(0);
ini_set('display_errors', 0);

include("conexion.php");
$mensaje = "";
$usuario_actual = $_SESSION['usuario'] ?? 'Administrador';

function registrarHistorialMantenimiento($conexion, $descripcion, $usuario) {
    $fecha = date('Y-m-d H:i:s');
    if ($conexion instanceof PDO) {
        try {
            $stmt = $conexion->prepare("INSERT INTO historial (descripcion, usuario, fecha_hora) VALUES (:descripcion, :usuario, :fecha)");
            $stmt->execute([':descripcion' => $descripcion, ':usuario' => $usuario, ':fecha' => $fecha]);
        } catch (Exception $ex) {
        }
    } else {
        $desc_l = mysqli_real_escape_string($conexion, $descripcion);
        $usu_l = mysqli_real_escape_string($conexion, $usuario);
        @mysqli_query($conexion, "INSERT INTO historial (descripcion, usuario, fecha_hora) VALUES ('$desc_l', '$usu_l', '$fecha')");
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    if ($_POST['accion'] === 'respaldo') {
        $carpeta_respaldos = __DIR__ . DIRECTORY_SEPARATOR . "respaldos";
        if (!is_dir($carpeta_respaldos)) {
            @mkdir($carpeta_respaldos, 0777, true);
        }

        $nombre_archivo = "respaldo_escuela_" . date("Y-m-d_H-i-s") . ".sql";
        $ruta_archivo = $carpeta_respaldos . DIRECTORY_SEPARATOR . $nombre_archivo;
        $contenido_sql = "-- Respaldo de la Base de Datos del Sistema Escolar\n";
        $contenido_sql .= "-- Fecha: " . date("Y-m-d H:i:s") . "\n\n";

        if ($conexion instanceof PDO) {
            try {
                $tablas_query = $conexion->query("SHOW TABLES");
                $tablas = $tablas_query->fetchAll(PDO::FETCH_NUM);
                foreach ($tablas as $tabla) {
                    $nombre_tabla = $tabla[0];
                    $create_query = $conexion->query("SHOW CREATE TABLE `$nombre_tabla`");
                    $create_row = $create_query->fetch(PDO::FETCH_NUM);
                    $contenido_sql .= "\n\n" . $create_row[1] . ";\n\n";

                    $datos_query = $conexion->query("SELECT * FROM `$nombre_tabla`");
                    $filas = $datos_query->fetchAll(PDO::FETCH_ASSOC);
                    foreach ($filas as $fila) {
                        $columnas = array_keys($fila);
                        $valores = array_values($fila);
                        $valores_escapados = array_map(function($val) use ($conexion) {
                            if ($val === null) return "NULL";
                            return $conexion->quote($val);
                        }, $valores);
                        $contenido_sql .= "INSERT INTO `$nombre_tabla` (`" . implode("`, `", $columnas) . "`) VALUES (" . implode(", ", $valores_escapados) . ");\n";
                    }
                }

                if (file_put_contents($ruta_archivo, $contenido_sql) !== false) {
                    registrarHistorialMantenimiento($conexion, "Generación de respaldo de base de datos ($nombre_archivo)", $usuario_actual);
                    $mensaje = "<div style='color: #155724; background-color: #d4edda; padding: 10px 14px; border-radius: 8px; margin-bottom: 15px; font-weight: 600;'>¡Respaldo generado y guardado exitosamente en <strong>respaldos/" . htmlspecialchars($nombre_archivo, ENT_QUOTES, 'UTF-8') . "</strong>!</div>";
                } else {
                    $mensaje = "<div style='color: #721c24; background-color: #f8d7da; padding: 10px 14px; border-radius: 8px; margin-bottom: 15px;'>Error: No se pudo guardar el archivo en la carpeta <strong>respaldos</strong>. Verifique los permisos de escritura.</div>";
                }
            } catch (Exception $e) {
                $mensaje = "<div style='color: #721c24; background-color: #f8d7da; padding: 10px; border-radius: 5px; margin-bottom: 15px;'>Error al generar el respaldo: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . "</div>";
            }
        } else {
            mysqli_set_charset($conexion, "utf8mb4");
            $tablas = [];
            $res = mysqli_query($conexion, "SHOW TABLES");
            while ($row = mysqli_fetch_row($res)) {
                $tablas[] = $row[0];
            }

            foreach ($tablas as $tabla) {
                $res_create = mysqli_query($conexion, "SHOW CREATE TABLE `$tabla`");
                $row_create = mysqli_fetch_row($res_create);
                $contenido_sql .= "\n\n" . $row_create[1] . ";\n\n";

                $res_data = mysqli_query($conexion, "SELECT * FROM `$tabla`");
                while ($row_data = mysqli_fetch_assoc($res_data)) {
                    $cols = array_keys($row_data);
                    $vals = array_values($row_data);
                    $vals_escapados = array_map(function($v) use ($conexion) {
                        if ($v === null) return "NULL";
                        return "'" . mysqli_real_escape_string($conexion, $v) . "'";
                    }, $vals);
                    $contenido_sql .= "INSERT INTO `$tabla` (`" . implode("`, `", $cols) . "`) VALUES (" . implode(", ", $vals_escapados) . ");\n";
                }
            }

            if (file_put_contents($ruta_archivo, $contenido_sql) !== false) {
                registrarHistorialMantenimiento($conexion, "Generación de respaldo de base de datos ($nombre_archivo)", $usuario_actual);
                $mensaje = "<div style='color: #155724; background-color: #d4edda; padding: 10px 14px; border-radius: 8px; margin-bottom: 15px; font-weight: 600;'>¡Respaldo generado y guardado exitosamente en <strong>respaldos/" . htmlspecialchars($nombre_archivo, ENT_QUOTES, 'UTF-8') . "</strong>!</div>";
            } else {
                $mensaje = "<div style='color: #721c24; background-color: #f8d7da; padding: 10px 14px; border-radius: 8px; margin-bottom: 15px;'>Error: No se pudo guardar el archivo en la carpeta <strong>respaldos</strong>. Verifique los permisos de escritura.</div>";
            }
        }
    } elseif ($_POST['accion'] === 'restaurar') {
        if (isset($_FILES['archivo_sql']) && $_FILES['archivo_sql']['error'] === UPLOAD_ERR_OK) {
            $archivo_tmp = $_FILES['archivo_sql']['tmp_name'];
            $extension = strtolower(pathinfo($_FILES['archivo_sql']['name'], PATHINFO_EXTENSION));

            if ($extension === 'sql') {
                $sql_contenido = file_get_contents($archivo_tmp);

                if ($conexion instanceof PDO) {
                    try {
                        $conexion->exec("SET foreign_key_checks = 0");
                        $tablas_q = $conexion->query("SHOW TABLES");
                        while ($t = $tablas_q->fetch(PDO::FETCH_NUM)) {
                            @$conexion->exec("DROP TABLE IF EXISTS `" . $t[0] . "`");
                        }
                        $conexion->exec($sql_contenido);
                        $conexion->exec("SET foreign_key_checks = 1");
                        registrarHistorialMantenimiento($conexion, "Restauración de base de datos exitosa mediante archivo SQL", $usuario_actual);
                        $mensaje = "<div style='color: #155724; background-color: #d4edda; padding: 10px; border-radius: 5px; margin-bottom: 15px;'>¡Base de datos restaurada exitosamente!</div>";
                    } catch (Exception $e) {
                        @$conexion->exec("SET foreign_key_checks = 1");
                        $mensaje = "<div style='color: #721c24; background-color: #f8d7da; padding: 10px; border-radius: 5px; margin-bottom: 15px;'>Error al restaurar la base de datos: " . $e->getMessage() . "</div>";
                    }
                } else {
                    mysqli_set_charset($conexion, "utf8mb4");

                    mysqli_query($conexion, "SET foreign_key_checks = 0");

                    $resultado_tablas = mysqli_query($conexion, "SHOW TABLES");
                    if ($resultado_tablas) {
                        while ($fila_tabla = mysqli_fetch_row($resultado_tablas)) {
                            $tabla_actual = $fila_tabla[0];
                            @mysqli_query($conexion, "DROP TABLE IF EXISTS `$tabla_actual`");
                        }
                    }

                    if (mysqli_multi_query($conexion, $sql_contenido)) {
                        do {
                            if ($result = mysqli_store_result($conexion)) {
                                mysqli_free_result($result);
                            }
                        } while (mysqli_more_results($conexion) && mysqli_next_result($conexion));

                        mysqli_query($conexion, "SET foreign_key_checks = 1");

                        registrarHistorialMantenimiento($conexion, "Restauración de base de datos exitosa mediante archivo SQL", $usuario_actual);
                        $mensaje = "<div style='color: #155724; background-color: #d4edda; padding: 10px; border-radius: 5px; margin-bottom: 15px;'>¡Base de datos restaurada exitosamente!</div>";
                    } else {
                        mysqli_query($conexion, "SET foreign_key_checks = 1");
                        $mensaje = "<div style='color: #721c24; background-color: #f8d7da; padding: 10px; border-radius: 5px; margin-bottom: 15px;'>Error al restaurar la base de datos: " . mysqli_error($conexion) . "</div>";
                    }
                }
            } else {
                $mensaje = "<div style='color: #721c24; background-color: #f8d7da; padding: 10px; border-radius: 5px; margin-bottom: 15px;'>Por favor, seleccione un archivo con extensión .sql válido.</div>";
            }
        } else {
            $mensaje = "<div style='color: #721c24; background-color: #f8d7da; padding: 10px; border-radius: 5px; margin-bottom: 15px;'>Debe seleccionar un archivo SQL para realizar la restauración.</div>";
        }
    } elseif ($_POST['accion'] === 'limpieza') {
        if (isset($_SESSION['estudiante'])) unset($_SESSION['estudiante']);
        if (isset($_SESSION['representante'])) unset($_SESSION['representante']);
        if (isset($_SESSION['madre'])) unset($_SESSION['madre']);
        if (isset($_SESSION['padre'])) unset($_SESSION['padre']);
        if (isset($_SESSION['adicional'])) unset($_SESSION['adicional']);

        if ($conexion instanceof PDO) {
            try {
                $tablas_q = $conexion->query("SHOW TABLES");
                while ($t = $tablas_q->fetch(PDO::FETCH_NUM)) {
                    @$conexion->exec("OPTIMIZE TABLE `" . $t[0] . "`");
                }
            } catch (Exception $ex) {
            }
        } else {
            $res_t = mysqli_query($conexion, "SHOW TABLES");
            while ($row_t = mysqli_fetch_row($res_t)) {
                @mysqli_query($conexion, "OPTIMIZE TABLE `" . $row_t[0] . "`");
            }
        }

        registrarHistorialMantenimiento($conexion, "Ejecución de limpieza de sesiones y optimización del sistema", $usuario_actual);
        $mensaje = "<div style='color: #155724; background-color: #d4edda; padding: 10px; border-radius: 5px; margin-bottom: 15px;'>¡Limpieza de caché y optimización del sistema realizadas exitosamente!</div>";
    }
}

$es_administrador = isset($_SESSION['rol']) && $_SESSION['rol'] === 'Administrador';
$rol_actual = $_SESSION['rol'] ?? 'Administrador';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mantenimiento - Sistema de Inscripción</title>
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

        body.dark-mode .card,
        body.dark-mode .top-header-bar,
        body.dark-mode .info-box,
        body.dark-mode .mantenimiento-card {
            background-color: var(--bg-surface) !important;
            border-color: var(--border-color) !important;
            color: var(--text-primary) !important;
        }

        body.dark-mode input, body.dark-mode select, body.dark-mode textarea {
            background-color: var(--bg-surface-alt) !important;
            color: var(--text-primary) !important;
            border-color: var(--border-color) !important;
        }

        html, body {
            min-height: 100vh;
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            background-color: var(--bg-main);
            color: var(--text-primary);
            display: flex;
            width: 100%;
            transition: background-color 0.25s ease, color 0.25s ease;
        }

        .main-content {
            flex: 1;
            min-width: 0;
            padding: 16px 20px 24px 20px;
            box-sizing: border-box;
            position: relative;
            z-index: 1;
            align-self: stretch;
            background-color: var(--bg-main);
            transition: background-color 0.25s ease;
        }

        .top-header-bar {
            background-color: var(--bg-surface);
            padding: 10px 18px;
            border-radius: 12px;
            border: 1px solid var(--border-color);
            box-shadow: var(--card-shadow);
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
            flex-wrap: wrap;
            gap: 10px;
            transition: background-color 0.25s ease, border-color 0.25s ease;
        }

        .top-breadcrumb {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text-secondary);
        }

        .top-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .btn-theme-switch {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            border: 1px solid var(--border-color);
            background-color: var(--bg-surface-alt);
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn-theme-switch:hover {
            color: #2563eb;
            border-color: #2563eb;
            transform: scale(1.05);
        }

        .user-profile-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 5px 12px;
            border-radius: 50rem;
            background-color: var(--bg-surface-alt);
            border: 1px solid var(--border-color);
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--text-primary);
        }

        .role-badge {
            padding: 2px 8px;
            border-radius: 5px;
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }

        .role-badge.badge-admin {
            background-color: rgba(239, 68, 68, 0.15);
            color: #ef4444;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        .role-badge.badge-coord {
            background-color: rgba(59, 130, 246, 0.15);
            color: #3b82f6;
            border: 1px solid rgba(37, 99, 235, 0.3);
        }

        .card { background: #FFFFFF; padding: 20px; border-radius: 12px; box-shadow: var(--card-shadow); margin-bottom: 20px; width: 100%; box-sizing: border-box; border: 1px solid var(--border-color); }
        .card h2 { font-size: 1.15rem; font-weight: 700; margin-bottom: 5px; color: var(--text-primary); }
        .info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 20px; }
        .info-box { background-color: var(--bg-surface-alt); border: 1px solid var(--border-color); border-radius: 8px; padding: 15px; text-align: center; }
        .info-box h4 { margin: 0 0 5px 0; font-size: 0.8rem; color: var(--text-secondary); text-transform: uppercase; font-weight: 700; }
        .info-box p { margin: 0; font-size: 1.05rem; font-weight: 800; color: #2563eb; }
        
        .acciones-container { display: flex; flex-direction: column; gap: 15px; margin-top: 20px; }
        .mantenimiento-card { background-color: var(--bg-surface-alt); border: 1px solid var(--border-color); border-radius: 10px; padding: 18px 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; }
        .mantenimiento-info h5 { margin: 0 0 4px 0; font-size: 0.95rem; font-weight: 700; color: var(--text-primary); }
        .mantenimiento-info p { margin: 0; font-size: 0.82rem; color: var(--text-secondary); }

        .btn-respaldo { background-color: #059669; color: white; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 0.9rem; transition: background-color 0.2s; display: inline-flex; align-items: center; gap: 8px; white-space: nowrap; }
        .btn-respaldo:hover { opacity: 0.9; }
        .btn-restaurar { background-color: #f59e0b; color: white; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 0.9rem; transition: background-color 0.2s; display: inline-flex; align-items: center; gap: 8px; white-space: nowrap; }
        .btn-restaurar:hover { opacity: 0.9; }
        .btn-limpieza { background-color: #ef4444; color: white; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 0.9rem; transition: background-color 0.2s; display: inline-flex; align-items: center; gap: 8px; white-space: nowrap; }
        .btn-limpieza:hover { background-color: #dc2626; }
        
        .file-input-wrapper { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
        .file-input-wrapper input[type="file"] { font-size: 0.85rem; color: var(--text-secondary); padding: 5px; background: var(--bg-surface); border: 1px solid var(--border-color); border-radius: 6px; }

        .tool-action-btn {
            display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px;
            border-radius: 8px; font-size: 0.78rem; font-weight: 600; text-decoration: none;
            border: 1px solid var(--border-color); background-color: var(--bg-surface-alt);
            color: var(--text-primary); transition: all 0.2s ease; cursor: pointer;
        }
        .tool-action-btn.btn-refresh-cache { background: rgba(37, 99, 235, 0.12); color: #2563eb; border-color: rgba(37, 99, 235, 0.3); }
        .tool-action-btn.btn-refresh-cache:hover { background: #2563eb; color: #ffffff; }

        @media (max-width: 768px) {
            html, body { flex-direction: column; }
            .main-content { padding: 12px; }
            .mantenimiento-card { flex-direction: column; align-items: flex-start; }
        }
    </style>
</head>
<body>

    <?php include("menu.php"); ?>

    <main class="main-content">
        <div class="top-header-bar">
            <div class="top-breadcrumb">
                <i class="fas fa-tools" style="color: #2563eb;"></i>
                <span>Mantenimiento del Sistema</span>
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

        <?php if (!empty($mensaje)) echo $mensaje; ?>

        <div class="card">
            <h2><i class="fas fa-database text-primary" style="color: #2563eb;"></i> Mantenimiento y Respaldo de Base de Datos</h2>
            <p class="text-muted" style="color: var(--text-secondary);">Gestión técnica, estado del servidor, copias de seguridad y limpieza del sistema.</p>
            <hr style="margin-bottom: 20px; border: 0; border-top: 1px solid var(--border-color);">
            
            <div class="info-grid">
                <div class="info-box">
                    <h4>Motor de Base de Datos</h4>
                    <p><?php echo ($conexion instanceof PDO) ? 'PostgreSQL (Neon)' : 'MySQL (Local)'; ?></p>
                </div>
                <div class="info-box">
                    <h4>Versión de PHP</h4>
                    <p><?php echo phpversion(); ?></p>
                </div>
                <div class="info-box">
                    <h4>Estado del Sistema</h4>
                    <p style="color: #059669;"><i class="fas fa-check-circle"></i> Operativo</p>
                </div>
            </div>

            <div class="acciones-container">
                <div class="mantenimiento-card">
                    <div class="mantenimiento-info">
                        <h5>Copia de Seguridad (Respaldo)</h5>
                        <p>Genera y guarda un archivo .sql con toda la información actual en la carpeta <strong>respaldos/</strong> del sistema.</p>
                    </div>
                    <form method="POST" action="mantenimiento.php" style="margin: 0;">
                        <input type="hidden" name="accion" value="respaldo">
                        <button type="submit" class="btn-respaldo"><i class="fas fa-save"></i> Generar y Guardar Respaldo</button>
                    </form>
                </div>

                <div class="mantenimiento-card">
                    <div class="mantenimiento-info">
                        <h5>Restaurar Base de Datos</h5>
                        <p>Selecciona un archivo de respaldo (.sql previo) para reestructurar la base de datos.</p>
                    </div>
                    <form method="POST" action="mantenimiento.php" enctype="multipart/form-data" style="margin: 0;" onsubmit="return confirm('¿Está seguro de restaurar la base de datos? Esto sobrescribirá los datos actuales con los del archivo seleccionado.');">
                        <input type="hidden" name="accion" value="restaurar">
                        <div class="file-input-wrapper">
                            <input type="file" name="archivo_sql" accept=".sql" required>
                            <button type="submit" class="btn-restaurar"><i class="fas fa-upload"></i> Subir y Restaurar</button>
                        </div>
                    </form>
                </div>

                <div class="mantenimiento-card">
                    <div class="mantenimiento-info">
                        <h5>Limpieza y Optimización</h5>
                        <p>Limpia las sesiones temporales abiertas y optimiza el rendimiento general de las tablas SQL.</p>
                    </div>
                    <form method="POST" action="mantenimiento.php" style="margin: 0;" onsubmit="return confirm('¿Está seguro de limpiar sesiones temporales y optimizar las tablas?');">
                        <input type="hidden" name="accion" value="limpieza">
                        <button type="submit" class="btn-limpieza"><i class="fas fa-broom"></i> Limpiar Caché y Tablas</button>
                    </form>
                </div>
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
                if (icon) {
                    icon.classList.remove('fa-moon');
                    icon.classList.add('fa-sun');
                }
            }
        }
        applyDashboardTheme();

        function toggleDashboardTheme() {
            const isDark = document.body.classList.toggle('dark-mode');
            const icon = document.getElementById('dashThemeIcon');
            localStorage.setItem('sige_theme', isDark ? 'dark' : 'light');
            if (isDark) {
                icon.classList.remove('fa-moon');
                icon.classList.add('fa-sun');
            } else {
                icon.classList.remove('fa-sun');
                icon.classList.add('fa-moon');
            }
        }

        function actualizarDashboard() {
            const icon = document.getElementById('refreshIcon');
            if (icon) {
                icon.classList.add('fa-spin');
            }
            setTimeout(() => {
                window.location.reload();
            }, 450);
        }
    </script>
</body>
</html>