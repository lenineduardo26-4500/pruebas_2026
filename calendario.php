<?php
session_start();
// Configuración de seguridad para sesiones y manejo de errores limpio
if (isset($_SESSION['usuario'])) {
    if (!isset($_SESSION['init'])) {
        session_regenerate_id(true);
        $_SESSION['init'] = true;
    }
}
include("conexion.php");
if ($conexion instanceof mysqli) {
    mysqli_set_charset($conexion, "utf8mb4");
}

$mensaje = "";
$usuario_actual = $_SESSION['usuario'] ?? 'Administrador';

function registrarHistorialCalendario($conexion, $descripcion, $usuario) {
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

$hoy = date('Y-m-d');
$editando_cal = false;
$edit_id = "";
$edit_tipo = "Académico";
$edit_inicio = $hoy;
$edit_fin = "";
$edit_desc = "";

if (isset($_GET['editar_evento'])) {
    $edit_id = intval($_GET['editar_evento']);
    if ($conexion instanceof PDO) {
        $stmt_e = $conexion->prepare("SELECT * FROM calendario WHERE id = :id LIMIT 1");
        $stmt_e->execute([':id' => $edit_id]);
        $row_ev = $stmt_e->fetch(PDO::FETCH_ASSOC);
        if ($row_ev) {
            $editando_cal = true;
            $edit_tipo = $row_ev['tipo'];
            $edit_inicio = $row_ev['fecha_inicio'];
            $edit_fin = $row_ev['fecha_fin'];
            $edit_desc = $row_ev['descripcion'];
        }
    } else {
        $res_e = mysqli_query($conexion, "SELECT * FROM calendario WHERE id = $edit_id LIMIT 1");
        if ($res_e && $row_ev = mysqli_fetch_assoc($res_e)) {
            $editando_cal = true;
            $edit_tipo = $row_ev['tipo'];
            $edit_inicio = $row_ev['fecha_inicio'];
            $edit_fin = $row_ev['fecha_fin'];
            $edit_desc = $row_ev['descripcion'];
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'guardar_evento') {
    $id_evento = intval($_POST['id_evento'] ?? 0);
    $tipo = trim($_POST['tipo'] ?? '');
    $fecha_inicio = trim($_POST['fecha_inicio'] ?? '');
    $fecha_fin = !empty($_POST['fecha_fin']) ? trim($_POST['fecha_fin']) : null;
    $descripcion = trim($_POST['descripcion'] ?? '');

    $valido = true;

    if (empty($fecha_inicio)) {
        $mensaje = "<div style='color: #721c24; background-color: #f8d7da; padding: 10px; border-radius: 5px; margin-bottom: 15px;'>Error: La fecha de inicio es obligatoria.</div>";
        $valido = false;
    } elseif ($fecha_inicio < $hoy && !$id_evento) {
        $mensaje = "<div style='color: #721c24; background-color: #f8d7da; padding: 10px; border-radius: 5px; margin-bottom: 15px;'>Error: No se puede registrar un evento con una fecha anterior al día de hoy.</div>";
        $valido = false;
    } elseif (strlen($descripcion) > 100) {
        $mensaje = "<div style='color: #721c24; background-color: #f8d7da; padding: 10px; border-radius: 5px; margin-bottom: 15px;'>Error: La descripción no puede superar los 100 caracteres.</div>";
        $valido = false;
    }

    if ($valido && $fecha_fin) {
        $ts_inicio = strtotime($fecha_inicio);
        $ts_fin = strtotime($fecha_fin);
        $limite_max = strtotime('+364 days', $ts_inicio);

        if ($ts_fin < $ts_inicio) {
            $mensaje = "<div style='color: #721c24; background-color: #f8d7da; padding: 10px; border-radius: 5px; margin-bottom: 15px;'>Error: La fecha de cierre no puede ser anterior a la fecha de inicio.</div>";
            $valido = false;
        } elseif ($ts_fin > $limite_max) {
            $mensaje = "<div style='color: #721c24; background-color: #f8d7da; padding: 10px; border-radius: 5px; margin-bottom: 15px;'>Error: El evento no puede superar el límite máximo de un año (364 días).</div>";
            $valido = false;
        }
    }

    if ($valido) {
        if ($conexion instanceof PDO) {
            if ($id_evento > 0) {
                $stmt = $conexion->prepare("UPDATE calendario SET tipo = :tipo, fecha_inicio = :fini, fecha_fin = :ffin, descripcion = :desc WHERE id = :id");
                $ejecutado = $stmt->execute([':tipo' => $tipo, ':fini' => $fecha_inicio, ':ffin' => $fecha_fin, ':desc' => $descripcion, ':id' => $id_evento]);
                $accion_txt = "Modificación de evento en calendario";
            } else {
                $stmt = $conexion->prepare("INSERT INTO calendario (tipo, fecha_inicio, fecha_fin, descripcion) VALUES (:tipo, :fini, :ffin, :desc)");
                $ejecutado = $stmt->execute([':tipo' => $tipo, ':fini' => $fecha_inicio, ':ffin' => $fecha_fin, ':desc' => $descripcion]);
                $accion_txt = "Registro de evento en calendario";
            }

            if ($ejecutado) {
                registrarHistorialCalendario($conexion, "$accion_txt ($tipo): $descripcion", $usuario_actual);
                $mensaje = "<div style='color: #155724; background-color: #d4edda; padding: 10px; border-radius: 5px; margin-bottom: 15px;'>¡Evento guardado exitosamente en el calendario escolar!</div>";
                $editando_cal = false;
            } else {
                $mensaje = "<div style='color: #721c24; background-color: #f8d7da; padding: 10px; border-radius: 5px; margin-bottom: 15px;'>Error al guardar el evento.</div>";
            }
        } else {
            $t_esc = mysqli_real_escape_string($conexion, $tipo);
            $fi_esc = mysqli_real_escape_string($conexion, $fecha_inicio);
            $ff_esc = !empty($fecha_fin) ? "'" . mysqli_real_escape_string($conexion, $fecha_fin) . "'" : "NULL";
            $d_esc = mysqli_real_escape_string($conexion, $descripcion);

            if ($id_evento > 0) {
                $sql_query = "UPDATE calendario SET tipo='$t_esc', fecha_inicio='$fi_esc', fecha_fin=$ff_esc, descripcion='$d_esc' WHERE id=$id_evento";
                $accion_txt = "Modificación de evento en calendario";
            } else {
                $sql_query = "INSERT INTO calendario (tipo, fecha_inicio, fecha_fin, descripcion) VALUES ('$t_esc', '$fi_esc', $ff_esc, '$d_esc')";
                $accion_txt = "Registro de evento en calendario";
            }

            if (mysqli_query($conexion, $sql_query)) {
                registrarHistorialCalendario($conexion, "$accion_txt ($tipo): $descripcion", $usuario_actual);
                $mensaje = "<div style='color: #155724; background-color: #d4edda; padding: 10px; border-radius: 5px; margin-bottom: 15px;'>¡Evento guardado exitosamente en el calendario escolar!</div>";
                $editando_cal = false;
            } else {
                $mensaje = "<div style='color: #721c24; background-color: #f8d7da; padding: 10px; border-radius: 5px; margin-bottom: 15px;'>Error al guardar el evento: " . mysqli_error($conexion) . "</div>";
            }
        }
    } else {
        if ($id_evento > 0) {
            $editando_cal = true;
            $edit_id = $id_evento;
            $edit_tipo = $tipo;
            $edit_inicio = $fecha_inicio;
            $edit_fin = $fecha_fin;
            $edit_desc = $descripcion;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'eliminar_evento') {
    $id_evento = intval($_POST['id_evento']);
    if ($id_evento > 0) {
        $nombre_evento = "";
        if ($conexion instanceof PDO) {
            $stmt_sel = $conexion->prepare("SELECT tipo, descripcion FROM calendario WHERE id = :id");
            $stmt_sel->execute([':id' => $id_evento]);
            $ev = $stmt_sel->fetch(PDO::FETCH_ASSOC);
            if ($ev) {
                $nombre_evento = " (" . $ev['tipo'] . "): " . $ev['descripcion'];
            }

            $stmt = $conexion->prepare("DELETE FROM calendario WHERE id = :id");
            $stmt->execute([':id' => $id_evento]);
            registrarHistorialCalendario($conexion, "Eliminación de evento en calendario$nombre_evento", $usuario_actual);
        } else {
            $res_sel = mysqli_query($conexion, "SELECT tipo, descripcion FROM calendario WHERE id = $id_evento");
            if ($res_sel && $ev = mysqli_fetch_assoc($res_sel)) {
                $nombre_evento = " (" . $ev['tipo'] . "): " . $ev['descripcion'];
            }

            mysqli_query($conexion, "DELETE FROM calendario WHERE id = $id_evento");
            registrarHistorialCalendario($conexion, "Eliminación de evento en calendario$nombre_evento", $usuario_actual);
        }
        $mensaje = "<div style='color: #721c24; background-color: #f8d7da; padding: 10px; border-radius: 5px; margin-bottom: 15px;'>El evento ha sido eliminado del calendario.</div>";
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
    <title>Calendario Escolar - Sistema de Inscripción</title>
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
        body.dark-mode table {
            background-color: var(--bg-surface) !important;
            border-color: var(--border-color) !important;
            color: var(--text-primary) !important;
        }

        body.dark-mode th {
            background-color: #1e293b !important;
            color: var(--text-primary) !important;
            border-color: var(--border-color) !important;
        }

        body.dark-mode td {
            background-color: var(--bg-surface) !important;
            color: var(--text-primary) !important;
            border-color: var(--border-color) !important;
        }

        body.dark-mode tr:nth-child(even) td {
            background-color: var(--bg-surface-alt) !important;
        }

        body.dark-mode tr:hover td {
            background-color: #22304a !important;
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

        .tool-action-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 0.78rem;
            font-weight: 600;
            text-decoration: none;
            border: 1px solid var(--border-color);
            background-color: var(--bg-surface-alt);
            color: var(--text-primary);
            transition: all 0.2s ease;
            cursor: pointer;
        }

        .tool-action-btn.btn-refresh-cache {
            background: rgba(37, 99, 235, 0.12);
            color: #2563eb;
            border-color: rgba(37, 99, 235, 0.3);
        }

        .tool-action-btn.btn-refresh-cache:hover {
            background: #2563eb;
            color: #ffffff;
        }

        .card { background: #FFFFFF; padding: 20px; border-radius: 12px; box-shadow: var(--card-shadow); margin-bottom: 20px; width: 100%; box-sizing: border-box; border: 1px solid var(--border-color); }
        .card h2 { font-size: 1.15rem; font-weight: 700; margin-bottom: 5px; color: var(--text-primary); }
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 15px; margin-bottom: 15px; width: 100%; box-sizing: border-box; }
        .form-group { display: flex; flex-direction: column; width: 100%; box-sizing: border-box; }
        .form-group label { font-size: 0.85rem; font-weight: 600; margin-bottom: 5px; color: var(--text-secondary); }
        .form-group input, .form-group select, .form-group textarea { padding: 8px 10px; border: 1px solid var(--border-color); border-radius: 8px; font-size: 0.9rem; width: 100%; box-sizing: border-box; background-color: var(--bg-surface); color: var(--text-primary); }
        .btn-guardar { background-color: <?php echo $editando_cal ? '#ffc107' : '#007bff'; ?>; color: <?php echo $editando_cal ? '#212529' : 'white'; ?>; border: none; padding: 9px 18px; border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 0.9rem; transition: background-color 0.2s; }
        .btn-guardar:hover { background-color: <?php echo $editando_cal ? '#e0a800' : '#0056b3'; ?>; }
        .btn-modificar { background-color: #ffc107; color: #212529; border: none; padding: 5px 10px; border-radius: 6px; cursor: pointer; font-size: 0.80rem; font-weight: 600; text-decoration: none; display: inline-block; }
        .btn-modificar:hover { background-color: #e0a800; }
        .btn-eliminar { background-color: #dc3545; color: white; border: none; padding: 5px 10px; border-radius: 6px; cursor: pointer; font-size: 0.80rem; font-weight: 600; }
        .btn-eliminar:hover { background-color: #c82333; }
        .table-responsive { width: 100%; overflow-x: auto; border: 1px solid var(--border-color); border-radius: 8px; margin-top: 15px; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; background-color: var(--bg-surface); color: var(--text-primary); }
        th, td { border: 1px solid var(--border-color); padding: 11px; text-align: left; white-space: nowrap; }
        th { background-color: #007bff; color: white; position: sticky; top: 0; z-index: 10; }
        tr:nth-child(even) { background-color: var(--bg-surface-alt); }
        tr:hover { background-color: rgba(37, 99, 235, 0.04); }

        @media (max-width: 768px) {
            html, body { flex-direction: column; }
            .main-content { padding: 12px; }
        }
    </style>
</head>
<body>

    <?php include("menu.php"); ?>

    <main class="main-content" id="seccionCalendario">
        <div class="top-header-bar">
            <div class="top-breadcrumb">
                <i class="fas fa-calendar-alt" style="color: #2563eb;"></i>
                <span>Calendario Escolar</span>
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

        <div class="card">
            <h2><i class="fas fa-calendar-plus text-primary" style="color: #2563eb;"></i> <?php echo $editando_cal ? 'Modificar Fecha o Evento Escolar' : 'Registrar Fecha o Evento Escolar'; ?></h2>
            <p class="text-muted" style="color: var(--text-secondary);">Seleccione el tipo de evento y establezca las fechas.</p>
            <hr style="margin-bottom: 15px; border: 0; border-top: 1px solid var(--border-color);">

            <?php if (!empty($mensaje)) echo $mensaje; ?>

            <form method="POST" action="calendario.php#seccionCalendario">
                <input type="hidden" name="accion" value="guardar_evento">
                <?php if ($editando_cal): ?>
                    <input type="hidden" name="id_evento" value="<?php echo $edit_id; ?>">
                <?php endif; ?>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Tipo de Evento (*):</label>
                        <select name="tipo" required>
                            <option value="Académico" <?php if($edit_tipo=='Académico') echo 'selected'; ?>>Académico</option>
                            <option value="Feriado" <?php if($edit_tipo=='Feriado') echo 'selected'; ?>>Feriado / Día Festivo</option>
                            <option value="Administrativo" <?php if($edit_tipo=='Administrativo') echo 'selected'; ?>>Administrativo</option>
                            <option value="Evaluación" <?php if($edit_tipo=='Evaluación') echo 'selected'; ?>>Evaluación / Lapso</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Fecha de Inicio (*):</label>
                        <input type="date" id="fecha_inicio" name="fecha_inicio" min="<?php echo $hoy; ?>" value="<?php echo htmlspecialchars($edit_inicio); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Fecha de Cierre (Opcional):</label>
                        <input type="date" id="fecha_fin" name="fecha_fin" value="<?php echo htmlspecialchars($edit_fin); ?>">
                    </div>
                </div>
                <div class="form-group" style="margin-bottom: 15px;">
                    <label>Descripción / Observación:</label>
                    <textarea name="descripcion" rows="2" maxlength="100" placeholder="Detalles de la fecha o actividad institucional..."><?php echo htmlspecialchars($edit_desc); ?></textarea>
                </div>
                <div style="display: flex; gap: 10px;">
                    <button type="submit" class="btn-guardar"><i class="fas fa-save"></i> <?php echo $editando_cal ? 'Guardar Cambios' : 'Registrar en el Calendario'; ?></button>
                    <?php if ($editando_cal): ?>
                        <a href="calendario.php" style="background: #6c757d; color: white; padding: 9px 18px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 0.9rem; display: inline-flex; align-items: center; gap: 6px;">
                            <i class="fas fa-times"></i> Cancelar
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <div class="card">
            <h2><i class="fas fa-calendar-alt text-primary" style="color: #2563eb;"></i> Cronograma de Fechas y Actividades</h2>
            <div class="table-responsive">
                <table>
                    <tr>
                        <th style="text-align: center; width: 150px;">Acción</th>
                        <th>Tipo de Evento</th>
                        <th>Fecha Inicio</th>
                        <th>Fecha Fin</th>
                        <th>Descripción</th>
                    </tr>
                    <?php
                    if ($conexion instanceof PDO) {
                        $stmt_cal = $conexion->query("SELECT * FROM calendario ORDER BY fecha_inicio ASC");
                        $res_cal = $stmt_cal->fetchAll(PDO::FETCH_ASSOC);
                        if (!empty($res_cal)) {
                            foreach($res_cal as $e) {
                                echo "<tr>
                                        <td style='text-align: center; white-space: nowrap;'>
                                            <div style='display: inline-flex; gap: 5px; justify-content: center; align-items: center;'>
                                                <a href='calendario.php?editar_evento=" . $e['id'] . "#seccionCalendario' class='btn-modificar'>
                                                    <i class='fas fa-edit'></i> Modificar
                                                </a>
                                                <form method='POST' action='calendario.php' style='margin:0; display:inline;'>
                                                    <input type='hidden' name='accion' value='eliminar_evento'>
                                                    <input type='hidden' name='id_evento' value='" . $e['id'] . "'>
                                                    <button type='submit' class='btn-eliminar' onclick=\"return confirm('¿Está seguro de eliminar este evento del calendario?');\">
                                                        <i class='fas fa-trash-alt'></i> Eliminar
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                        <td><span style='background:#e6f7ff; color:#0056b3; padding:3px 8px; border-radius:4px; font-weight:600;'>" . htmlspecialchars($e['tipo'] ?? 'Académico') . "</span></td>
                                        <td>" . htmlspecialchars($e['fecha_inicio']) . "</td>
                                        <td>" . htmlspecialchars($e['fecha_fin'] ?? 'N/A') . "</td>
                                        <td>" . htmlspecialchars($e['descripcion'] ?? '-') . "</td>
                                      </tr>";
                            }
                        } else {
                            echo "<tr><td colspan='5' style='text-align: center; color: var(--text-secondary); padding: 20px;'>No hay fechas ni eventos registrados en el calendario escolar.</td></tr>";
                        }
                    } else {
                        $res_cal = mysqli_query($conexion, "SELECT * FROM calendario ORDER BY fecha_inicio ASC");
                        if ($res_cal && mysqli_num_rows($res_cal) > 0) {
                            while($e = mysqli_fetch_assoc($res_cal)) {
                                echo "<tr>
                                        <td style='text-align: center; white-space: nowrap;'>
                                            <div style='display: inline-flex; gap: 5px; justify-content: center; align-items: center;'>
                                                <a href='calendario.php?editar_evento=" . $e['id'] . "#seccionCalendario' class='btn-modificar'>
                                                    <i class='fas fa-edit'></i> Modificar
                                                </a>
                                                <form method='POST' action='calendario.php' style='margin:0; display:inline;'>
                                                    <input type='hidden' name='accion' value='eliminar_evento'>
                                                    <input type='hidden' name='id_evento' value='" . $e['id'] . "'>
                                                    <button type='submit' class='btn-eliminar' onclick=\"return confirm('¿Está seguro de eliminar este evento del calendario?');\">
                                                        <i class='fas fa-trash-alt'></i> Eliminar
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                        <td><span style='background:#e6f7ff; color:#0056b3; padding:3px 8px; border-radius:4px; font-weight:600;'>" . htmlspecialchars($e['tipo'] ?? 'Académico') . "</span></td>
                                        <td>" . htmlspecialchars($e['fecha_inicio']) . "</td>
                                        <td>" . htmlspecialchars($e['fecha_fin'] ?? 'N/A') . "</td>
                                        <td>" . htmlspecialchars($e['descripcion'] ?? '-') . "</td>
                                      </tr>";
                            }
                        } else {
                            echo "<tr><td colspan='5' style='text-align: center; color: var(--text-secondary); padding: 20px;'>No hay fechas ni eventos registrados en el calendario escolar.</td></tr>";
                        }
                    }
                    ?>
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

        const inputInicio = document.getElementById('fecha_inicio');
        const inputFin = document.getElementById('fecha_fin');

        inputInicio.addEventListener('change', function() {
            if (this.value) {
                let inicio = new Date(this.value);
                inputFin.min = this.value;
                
                let maxFecha = new Date(inicio);
                maxFecha.setDate(maxFecha.getDate() + 364);
                let anio = maxFecha.getFullYear();
                let mes = String(maxFecha.getMonth() + 1).padStart(2, '0');
                let dia = String(maxFecha.getDate()).padStart(2, '0');
                inputFin.max = `${anio}-${mes}-${dia}`;
            } else {
                inputFin.min = '';
                inputFin.max = '';
            }
        });
    </script>
</body>
</html>