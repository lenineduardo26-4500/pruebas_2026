<?php
require_once("control_sesion.php");
verificarRol('Administrador', 'Acceso denegado. El módulo de Historial es exclusivo para la Dirección.');

error_reporting(0);
ini_set('display_errors', 0);

include("conexion.php");
if ($conexion instanceof mysqli) {
    mysqli_set_charset($conexion, "utf8mb4");
}

$mensaje = "";
$usuario_actual = $_SESSION['usuario'] ?? 'Administrador';
$es_administrador = isset($_SESSION['rol']) && $_SESSION['rol'] === 'Administrador';
$rol_actual = $_SESSION['rol'] ?? 'Administrador';
$csrf_token = obtenerTokenCSRF();

function registrarHistorialSeguridad($conexion, $descripcion, $usuario) {
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

function parsearHistorial($desc) {
    $desc_lower = mb_strtolower($desc, 'UTF-8');
    
    $modulo = 'Sistema';
    if (strpos($desc_lower, 'constancia') !== false) {
        $modulo = 'Constancias';
    } elseif (strpos($desc_lower, 'planilla') !== false) {
        $modulo = 'Planillas';
    } elseif (strpos($desc_lower, 'docente') !== false) {
        $modulo = 'Docentes';
    } elseif (strpos($desc_lower, 'retir') !== false || strpos($desc_lower, 'reactiv') !== false) {
        $modulo = 'Retirados';
    } elseif (strpos($desc_lower, 'respaldo') !== false) {
        $modulo = 'Respaldos';
    } elseif (strpos($desc_lower, 'configuración') !== false || strpos($desc_lower, 'plantel') !== false) {
        $modulo = 'Configuración';
    } elseif (strpos($desc_lower, 'autoridad') !== false || strpos($desc_lower, 'firmante') !== false) {
        $modulo = 'Autoridades';
    } elseif (strpos($desc_lower, 'sesión') !== false || strpos($desc_lower, 'login') !== false || strpos($desc_lower, 'ingreso') !== false) {
        $modulo = 'Acceso';
    } elseif (strpos($desc_lower, 'usuario') !== false || strpos($desc_lower, 'cuenta') !== false) {
        $modulo = 'Usuarios';
    } elseif (strpos($desc_lower, 'estudiante') !== false || strpos($desc_lower, 'alumno') !== false || strpos($desc_lower, 'inscripción') !== false) {
        $modulo = 'Estudiantes';
    } elseif (strpos($desc_lower, 'historial') !== false) {
        $modulo = 'Historial';
    } elseif (strpos($desc_lower, 'calendario') !== false || strpos($desc_lower, 'evento') !== false) {
        $modulo = 'Calendario';
    }

    $accion = 'General';
    if (strpos($desc_lower, 'inicio de sesión') !== false) {
        $accion = 'Login';
    } elseif (strpos($desc_lower, 'cierre de sesión') !== false) {
        $accion = 'Logout';
    } elseif (strpos($desc_lower, 'impresión') !== false || strpos($desc_lower, 'envío de constancia') !== false || strpos($desc_lower, 'generación de planilla') !== false || strpos($desc_lower, 'emisión') !== false) {
        $accion = 'Emitir';
    } elseif (strpos($desc_lower, 'reactiv') !== false) {
        $accion = 'Reactivar';
    } elseif (strpos($desc_lower, 'retiro') !== false) {
        $accion = 'Retirar';
    } elseif (strpos($desc_lower, 'respaldo') !== false) {
        $accion = 'Respaldar';
    } elseif (strpos($desc_lower, 'desvincul') !== false) {
        $accion = 'Desvincular';
    } elseif (strpos($desc_lower, 'registro') !== false || strpos($desc_lower, 'registró') !== false || strpos($desc_lower, 'inscripción exitosa') !== false) {
        $accion = 'Registrar';
    } elseif (strpos($desc_lower, 'modific') !== false || strpos($desc_lower, 'actualiz') !== false || strpos($desc_lower, 'cambio de estado') !== false) {
        $accion = 'Modificar';
    } elseif (strpos($desc_lower, 'inactiv') !== false || strpos($desc_lower, 'inhabilit') !== false) {
        $accion = 'Inactivar';
    } elseif (strpos($desc_lower, 'elimin') !== false || strpos($desc_lower, 'borr') !== false) {
        $accion = 'Eliminar';
    } elseif (strpos($desc_lower, 'limpió') !== false) {
        $accion = 'Limpiar';
    }

    return ['modulo' => $modulo, 'accion' => $accion];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    if ($_POST['accion'] === 'limpiar_historial' && $es_administrador) {
        if (!validarTokenCSRF($_POST['csrf_token'] ?? '')) {
            $mensaje = "<div style='color: #721c24; background-color: #f8d7da; padding: 10px; border-radius: 5px; margin-bottom: 15px;'>Solicitud no válida. Por seguridad, recargue la página e intente nuevamente.</div>";
        } else {
            if ($conexion instanceof PDO) {
                try {
                    $conexion->exec("TRUNCATE TABLE historial");
                    registrarHistorialSeguridad($conexion, 'Se limpió el historial de actividad', $usuario_actual);
                    $mensaje = "<div style='color: #155724; background-color: #d4edda; padding: 10px; border-radius: 5px; margin-bottom: 15px;'>¡El historial de actividad ha sido limpiado exitosamente!</div>";
                } catch (Exception $e) {
                    $mensaje = "<div style='color: #721c24; background-color: #f8d7da; padding: 10px; border-radius: 5px; margin-bottom: 15px;'>Error al limpiar el historial: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . "</div>";
                }
            } else {
                if (mysqli_query($conexion, "TRUNCATE TABLE historial")) {
                    registrarHistorialSeguridad($conexion, 'Se limpió el historial de actividad', $usuario_actual);
                    $mensaje = "<div style='color: #155724; background-color: #d4edda; padding: 10px; border-radius: 5px; margin-bottom: 15px;'>¡El historial de actividad ha sido limpiado exitosamente!</div>";
                } else {
                    $mensaje = "<div style='color: #721c24; background-color: #f8d7da; padding: 10px; border-radius: 5px; margin-bottom: 15px;'>Error al limpiar el historial: " . htmlspecialchars(mysqli_error($conexion), ENT_QUOTES, 'UTF-8') . "</div>";
                }
            }
        }
    }
}

$sql_hist = "SELECT h.*, u.rol FROM historial h LEFT JOIN usuarios u ON h.usuario = u.usuario ORDER BY h.id DESC";
$res_h = ($conexion instanceof PDO) ? $conexion->query($sql_hist) : mysqli_query($conexion, $sql_hist);
$registros_historial = [];
$modulos_unicos = [];
$acciones_unicas = [];

$total_registros = 0;
$registros_semana = 0;
$conteo_modulos = [];
$conteo_acciones = [];
$fecha_hace_7_dias = date('Y-m-d H:i:s', strtotime('-7 days'));

if ($res_h) {
    $rows_h = ($conexion instanceof PDO) ? $res_h->fetchAll(PDO::FETCH_ASSOC) : $res_h;
    foreach($rows_h as $row) {
        $total_registros++;
        if ($row['fecha_hora'] >= $fecha_hace_7_dias) {
            $registros_semana++;
        }

        $info = parsearHistorial($row['descripcion']);
        $row['modulo_parsed'] = $info['modulo'];
        $row['accion_parsed'] = $info['accion'];
        $registros_historial[] = $row;
        
        $modulos_unicos[$info['modulo']] = true;
        $acciones_unicas[$info['accion']] = true;

        $conteo_modulos[$info['modulo']] = ($conteo_modulos[$info['modulo']] ?? 0) + 1;
        $conteo_acciones[$info['accion']] = ($conteo_acciones[$info['accion']] ?? 0) + 1;
    }
}
ksort($modulos_unicos);
ksort($acciones_unicas);

$modulo_mas_activo = 'N/A';
if (!empty($conteo_modulos)) {
    arsort($conteo_modulos);
    $modulo_mas_activo = array_key_first($conteo_modulos) . ' (' . reset($conteo_modulos) . ')';
}

$accion_mas_frecuente = 'N/A';
if (!empty($conteo_acciones)) {
    arsort($conteo_acciones);
    $accion_mas_frecuente = array_key_first($conteo_acciones) . ' (' . reset($conteo_acciones) . ')';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial - Sistema de Inscripción</title>
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

        body.dark-mode .card, body.dark-mode .top-header-bar, body.dark-mode table, body.dark-mode .table-responsive, body.dark-mode .form-control, body.dark-mode .modal-content {
            background-color: var(--bg-surface) !important; border-color: var(--border-color) !important; color: var(--text-primary) !important;
        }
        body.dark-mode .modal-footer { border-top-color: var(--border-color) !important; }
        body.dark-mode th { background-color: #1e293b !important; color: var(--text-primary) !important; border-color: var(--border-color) !important; }
        body.dark-mode td { background-color: var(--bg-surface) !important; color: var(--text-primary) !important; border-color: var(--border-color) !important; }
        body.dark-mode tr:nth-child(even) td { background-color: var(--bg-surface-alt) !important; }
        body.dark-mode tr:hover td { background-color: #22304a !important; }

        html, body {
            min-height: 100vh; margin: 0; padding: 0;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            background-color: var(--bg-main); color: var(--text-primary);
            display: flex; width: 100%; overflow-x: hidden; transition: background-color 0.25s ease, color 0.25s ease;
        }

        .main-content {
            flex: 1; min-width: 0; max-width: 100%; padding: 16px 20px 20px 20px;
            box-sizing: border-box; position: relative; z-index: 1; background-color: var(--bg-main);
        }

        .top-header-bar {
            background-color: var(--bg-surface); padding: 10px 18px;
            border-radius: 12px; border: 1px solid var(--border-color);
            box-shadow: var(--card-shadow); display: flex;
            justify-content: space-between; align-items: center; margin-bottom: 14px; flex-wrap: wrap; gap: 10px;
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

        .kpi-mini-card {
            border-radius: 10px; padding: 10px 12px; color: #ffffff;
            position: relative; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            display: flex; flex-direction: column; justify-content: space-between; min-height: 75px;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .kpi-mini-card:hover { transform: translateY(-3px); box-shadow: 0 8px 18px rgba(0,0,0,0.18); }
        .kpi-blue { background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%); }
        .kpi-green { background: linear-gradient(135deg, #059669 0%, #047857 100%); }
        .kpi-cyan { background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%); }
        .kpi-purple { background: linear-gradient(135deg, #7c3aed 0%, #6d28d9 100%); }

        .kpi-mini-title { font-size: 0.68rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; opacity: 0.9; margin: 0; }
        .kpi-mini-value { font-size: 1.25rem; font-weight: 800; margin: 2px 0 0 0; line-height: 1.1; }

        .card { background: var(--bg-surface); padding: 16px 20px; border-radius: 12px; box-shadow: var(--card-shadow); margin-bottom: 0; width: 100%; box-sizing: border-box; border: 1px solid var(--border-color); }
        .card h2 { font-size: 1.12rem; font-weight: 700; margin-bottom: 0; color: var(--text-primary); }
        .header-actions { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; flex-wrap: wrap; gap: 10px; }
        .btn-imprimir { background-color: #2563eb; color: white; border: none; padding: 6px 12px; border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 0.82rem; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; }
        .btn-limpiar { background-color: #ef4444; color: white; border: none; padding: 6px 12px; border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 0.82rem; display: inline-flex; align-items: center; gap: 6px; }
        
        .table-responsive { width: 100%; max-height: 420px; overflow-y: auto; overflow-x: auto; border: 1px solid var(--border-color); border-radius: 8px; background-color: var(--bg-surface); }
        table { width: 100%; border-collapse: collapse; font-size: 13px; background-color: var(--bg-surface); color: var(--text-primary); margin-bottom: 0; }
        th, td { border: 1px solid var(--border-color); padding: 9px 10px; text-align: left; }
        th { background-color: #f1f5f9; color: var(--text-primary); font-weight: 700; position: sticky; top: 0; z-index: 10; white-space: nowrap; }
        td { white-space: nowrap; }
        td.col-descripcion { white-space: normal; word-break: break-word; min-width: 300px; max-width: 450px; }
        tr:nth-child(even) { background-color: var(--bg-surface-alt); }
        tr:hover { background-color: rgba(37, 99, 235, 0.04); }

        @media (max-width: 991px) { html, body { flex-direction: column; } .main-content { padding: 12px; } }
        @media print { .sidebar, .header-actions, .btn-imprimir, .btn-limpiar, .no-print, .modal, .modal-backdrop { display: none !important; } .main-content { padding: 0 !important; height: auto !important; } .card { box-shadow: none !important; border: none !important; } .table-responsive { max-height: none !important; overflow: visible !important; } }
    </style>
</head>
<body>

    <?php include("menu.php"); ?>

    <main class="main-content">
        <div class="top-header-bar no-print">
            <div class="top-breadcrumb">
                <i class="fas fa-history" style="color: #2563eb;"></i>
                <span>Historial del Sistema</span>
            </div>

            <div class="top-actions">
                <button type="button" class="tool-action-btn btn-refresh-cache" onclick="actualizarDashboard()" title="Actualizar datos del sistema">
                    <i class="fas fa-sync-alt" id="refreshIcon"></i> Actualizar Sistema
                </button>
                <button type="button" class="btn-theme-switch" onclick="toggleDashboardTheme()" title="Cambiar Tema">
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

        <div class="row g-2 mb-3 no-print">
            <div class="col-6 col-md-3">
                <div class="kpi-mini-card kpi-blue">
                    <p class="kpi-mini-title"><i class="fas fa-database me-1"></i> Total Registros</p>
                    <h3 class="kpi-mini-value"><?php echo $total_registros; ?></h3>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="kpi-mini-card kpi-green">
                    <p class="kpi-mini-title"><i class="fas fa-calendar-week me-1"></i> Última Semana</p>
                    <h3 class="kpi-mini-value"><?php echo $registros_semana; ?></h3>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="kpi-mini-card kpi-cyan">
                    <p class="kpi-mini-title"><i class="fas fa-layer-group me-1"></i> Módulo más Activo</p>
                    <h3 class="kpi-mini-value" style="font-size: 0.95rem; padding-top: 2px;"><?php echo htmlspecialchars($modulo_mas_activo); ?></h3>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="kpi-mini-card kpi-purple">
                    <p class="kpi-mini-title"><i class="fas fa-bolt me-1"></i> Acción Frecuente</p>
                    <h3 class="kpi-mini-value" style="font-size: 0.95rem; padding-top: 2px;"><?php echo htmlspecialchars($accion_mas_frecuente); ?></h3>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="header-actions">
                <h2><i class="fas fa-history text-primary" style="color: #2563eb;"></i> Auditoría Global del Sistema</h2>
                
                <div style="display: flex; gap: 10px;" class="no-print">
                    <button type="button" onclick="window.print()" class="btn-imprimir">
                        <i class="fas fa-print"></i> Imprimir
                    </button>

                    <?php if ($es_administrador) { ?>
                        <button type="button" class="btn-limpiar" data-bs-toggle="modal" data-bs-target="#modalConfirmarLimpiar">
                            <i class="fas fa-trash-alt"></i> Limpiar Registros
                        </button>
                    <?php } ?>
                </div>
            </div>

            <p class="text-muted" style="margin-top: 0; margin-bottom: 10px; font-weight: 600; color: var(--text-secondary); font-size: 0.85rem;">Registro de Movimientos Recientes</p>

            <div class="row g-2 mb-3 no-print align-items-end">
                <div class="col-6 col-md-2">
                    <label style="font-size: 0.75rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 2px;">Módulo:</label>
                    <select id="filtroModulo" class="form-control" onchange="filtrarTablaHistorial()" style="font-size: 0.82rem;">
                        <option value="">Todos</option>
                        <?php foreach($modulos_unicos as $mod => $v): ?>
                            <option value="<?php echo htmlspecialchars($mod); ?>"><?php echo htmlspecialchars($mod); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label style="font-size: 0.75rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 2px;">Acción:</label>
                    <select id="filtroAccion" class="form-control" onchange="filtrarTablaHistorial()" style="font-size: 0.82rem;">
                        <option value="">Todas</option>
                        <?php foreach($acciones_unicas as $acc => $v): ?>
                            <option value="<?php echo htmlspecialchars($acc); ?>"><?php echo htmlspecialchars($acc); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label style="font-size: 0.75rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 2px;">Desde:</label>
                    <input type="date" id="filtroDesde" class="form-control" onchange="filtrarTablaHistorial()" style="font-size: 0.82rem;">
                </div>
                <div class="col-6 col-md-2">
                    <label style="font-size: 0.75rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 2px;">Hasta:</label>
                    <input type="date" id="filtroHasta" class="form-control" onchange="filtrarTablaHistorial()" style="font-size: 0.82rem;">
                </div>
                <div class="col-12 col-md-4">
                    <label style="font-size: 0.75rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 2px;">Buscar en registros:</label>
                    <div class="input-group">
                        <span class="input-group-text" style="background: var(--bg-surface-alt); color: var(--text-secondary); border-color: var(--border-color);"><i class="fas fa-search"></i></span>
                        <input type="text" id="searchInput" class="form-control" placeholder="Usuario o descripción..." onkeyup="filtrarTablaHistorial()" style="font-size: 0.82rem;">
                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="limpiarFiltrosHistorial()" title="Limpiar filtros" style="border-color: var(--border-color);">
                            <i class="fas fa-eraser"></i>
                        </button>
                    </div>
                </div>
            </div>

            <div class="table-responsive">
                <table id="tablaHistorial">
                    <thead>
                        <tr>
                            <th style="width: 170px;">Fecha y Hora</th>
                            <th>Usuario</th>
                            <th>Rol</th>
                            <th>Módulo</th>
                            <th>Acción</th>
                            <th>Detalle / Descripción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if (!empty($registros_historial)) {
                            foreach($registros_historial as $row) {
                                $rol_user = !empty($row['rol']) ? $row['rol'] : 'Administrador';
                                $badge_rol_color = ($rol_user === 'Administrador') ? 'background:rgba(239, 68, 68, 0.15); color:#ef4444;' : 'background:rgba(37, 99, 235, 0.15); color:#2563eb;';
                                $solo_fecha = substr((string)$row['fecha_hora'], 0, 10);
                                
                                $accion_val = $row['accion_parsed'];
                                $badge_acc_bg = 'background:rgba(37, 99, 235, 0.15); color:#2563eb;';
                                if ($accion_val === 'Registrar' || $accion_val === 'Login' || $accion_val === 'Reactivar' || $accion_val === 'Respaldar') $badge_acc_bg = 'background:rgba(16, 185, 129, 0.15); color:#10b981;';
                                elseif ($accion_val === 'Modificar') $badge_acc_bg = 'background:rgba(245, 158, 11, 0.15); color:#d97706;';
                                elseif ($accion_val === 'Inactivar' || $accion_val === 'Eliminar' || $accion_val === 'Limpiar' || $accion_val === 'Retirar' || $accion_val === 'Desvincular') $badge_acc_bg = 'background:rgba(239, 68, 68, 0.15); color:#ef4444;';

                                echo "<tr data-modulo='" . htmlspecialchars($row['modulo_parsed'], ENT_QUOTES, 'UTF-8') . "' data-accion='" . htmlspecialchars($row['accion_parsed'], ENT_QUOTES, 'UTF-8') . "' data-fecha='" . htmlspecialchars($solo_fecha, ENT_QUOTES, 'UTF-8') . "'>
                                        <td>" . htmlspecialchars($row['fecha_hora'], ENT_QUOTES, 'UTF-8') . "</td>
                                        <td><strong>" . htmlspecialchars($row['usuario'], ENT_QUOTES, 'UTF-8') . "</strong></td>
                                        <td><span style='" . $badge_rol_color . " padding:3px 8px; border-radius:4px; font-weight:600; font-size:0.75rem;'>" . htmlspecialchars($rol_user, ENT_QUOTES, 'UTF-8') . "</span></td>
                                        <td><span style='background:rgba(124, 58, 237, 0.15); color:#7c3aed; padding:3px 8px; border-radius:4px; font-weight:600; font-size:0.75rem;'>" . htmlspecialchars($row['modulo_parsed'], ENT_QUOTES, 'UTF-8') . "</span></td>
                                        <td><span style='" . $badge_acc_bg . " padding:3px 8px; border-radius:4px; font-weight:600; font-size:0.75rem;'>" . htmlspecialchars($row['accion_parsed'], ENT_QUOTES, 'UTF-8') . "</span></td>
                                        <td class='col-descripcion'>" . htmlspecialchars($row['descripcion'], ENT_QUOTES, 'UTF-8') . "</td>
                                      </tr>";
                            }
                        } else {
                            echo "<tr>
                                    <td colspan='6' style='text-align: center; color: var(--text-secondary); padding: 25px;'>
                                        No hay registros en el historial todavía.
                                    </td>
                                  </tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <?php if ($es_administrador) { ?>
    <div class="modal fade no-print" id="modalConfirmarLimpiar" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius: 12px; border: 1px solid var(--border-color); box-shadow: 0 10px 30px rgba(0,0,0,0.25);">
                <div class="modal-header bg-danger text-white" style="border-top-left-radius: 11px; border-top-right-radius: 11px;">
                    <h5 class="modal-title" style="font-size: 1rem; font-weight: 700;">
                        <i class="fas fa-exclamation-triangle me-2"></i> Confirmar Limpieza del Historial
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4" style="font-size: 0.9rem;">
                    <p class="mb-2">Esta acción eliminará todos los movimientos registrados en la <strong>Auditoría Global del Sistema</strong>.</p>
                    <p class="mb-0" style="color: var(--text-secondary); font-size: 0.83rem;">Si desea conservar evidencia física de la bitácora actual, puede imprimir un respaldo antes de limpiar.</p>
                </div>
                <div class="modal-footer" style="justify-content: space-between; flex-wrap: wrap; gap: 8px;">
                    <button type="button" class="btn btn-primary btn-sm" onclick="imprimirAntesDeLimpiar()">
                        <i class="fas fa-print me-1"></i> Imprimir respaldo antes de limpiar
                    </button>
                    <div style="display: flex; gap: 8px;">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                        <form method="POST" action="historial.php" style="margin: 0;">
                            <input type="hidden" name="accion" value="limpiar_historial">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
                            <button type="submit" class="btn btn-danger btn-sm">
                                <i class="fas fa-trash-alt me-1"></i> Sí, limpiar
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php } ?>

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

        function filtrarTablaHistorial() {
            const modSelect = document.getElementById('filtroModulo').value.toLowerCase();
            const accSelect = document.getElementById('filtroAccion').value.toLowerCase();
            const desdeVal = document.getElementById('filtroDesde').value;
            const hastaVal = document.getElementById('filtroHasta').value;
            const searchInput = document.getElementById('searchInput').value.toLowerCase();
            
            const table = document.getElementById('tablaHistorial');
            const trs = table.getElementsByTagName('tr');

            for (let i = 1; i < trs.length; i++) {
                const tr = trs[i];
                const rowModulo = (tr.getAttribute('data-modulo') || '').toLowerCase();
                const rowAccion = (tr.getAttribute('data-accion') || '').toLowerCase();
                const rowFecha = tr.getAttribute('data-fecha') || '';
                const rowText = tr.textContent.toLowerCase();

                let matchMod = !modSelect || rowModulo === modSelect;
                let matchAcc = !accSelect || rowAccion === accSelect;
                let matchDesde = !desdeVal || (rowFecha !== '' && rowFecha >= desdeVal);
                let matchHasta = !hastaVal || (rowFecha !== '' && rowFecha <= hastaVal);
                let matchSearch = !searchInput || rowText.indexOf(searchInput) > -1;

                tr.style.display = (matchMod && matchAcc && matchDesde && matchHasta && matchSearch) ? '' : 'none';
            }
        }

        function limpiarFiltrosHistorial() {
            document.getElementById('filtroModulo').value = '';
            document.getElementById('filtroAccion').value = '';
            document.getElementById('filtroDesde').value = '';
            document.getElementById('filtroHasta').value = '';
            document.getElementById('searchInput').value = '';
            filtrarTablaHistorial();
        }

        function imprimirAntesDeLimpiar() {
            const modalEl = document.getElementById('modalConfirmarLimpiar');
            if (modalEl && typeof bootstrap !== 'undefined') {
                const inst = bootstrap.Modal.getInstance(modalEl);
                if (inst) inst.hide();
            }
            setTimeout(() => { window.print(); }, 280);
        }
    </script>
</body>
</html>