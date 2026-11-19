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

error_reporting(0);
ini_set('display_errors', 0);

include("conexion.php");
if ($conexion instanceof mysqli) {
    mysqli_set_charset($conexion, "utf8mb4");
}

$total_alumnos = 0;
try {
    $sql_al = "SELECT COUNT(DISTINCT e.`cédula_escolar`) as total FROM estudiante e LEFT JOIN inscripcion i ON e.`cédula_escolar` = i.`cédula_escolar_estudiante` WHERE COALESCE(i.tipo_estudiante, '') != 'Retirado'";
    $res_al = ($conexion instanceof PDO) ? $conexion->query($sql_al) : @mysqli_query($conexion, $sql_al);
    if ($res_al) {
        $row = ($conexion instanceof PDO) ? $res_al->fetch(PDO::FETCH_ASSOC) : mysqli_fetch_assoc($res_al);
        $total_alumnos = (int)($row['total'] ?? 0);
    }
} catch (Exception $e) {}

$total_eventos = 0;
try {
    $res_ev = ($conexion instanceof PDO) ? $conexion->query("SELECT COUNT(*) as total FROM calendario") : @mysqli_query($conexion, "SELECT COUNT(*) as total FROM calendario");
    if ($res_ev) {
        $row = ($conexion instanceof PDO) ? $res_ev->fetch(PDO::FETCH_ASSOC) : mysqli_fetch_assoc($res_ev);
        $total_eventos = (int)($row['total'] ?? 0);
    }
} catch (Exception $e) {}

$total_autoridades = 0;
try {
    $sql_aut = "SELECT COUNT(*) as total FROM autoridades WHERE COALESCE(estatus, 'Activo') = 'Activo'";
    $res_aut = ($conexion instanceof PDO) ? $conexion->query($sql_aut) : @mysqli_query($conexion, $sql_aut);
    if ($res_aut) {
        $row = ($conexion instanceof PDO) ? $res_aut->fetch(PDO::FETCH_ASSOC) : mysqli_fetch_assoc($res_aut);
        $total_autoridades = (int)($row['total'] ?? 0);
    }
} catch (Exception $e) {}

$total_docentes = 0;
try {
    $sql_doc = "SELECT COUNT(*) as total FROM docentes WHERE COALESCE(estado, 'Activo') = 'Activo'";
    $res_doc = ($conexion instanceof PDO) ? $conexion->query($sql_doc) : @mysqli_query($conexion, $sql_doc);
    if ($res_doc) {
        $row_d = ($conexion instanceof PDO) ? $res_doc->fetch(PDO::FETCH_ASSOC) : mysqli_fetch_assoc($res_doc);
        $total_docentes = (int)($row_d['total'] ?? 0);
    }
} catch (Exception $e) {}

$total_planillas = 0;
try {
    $res_hp = ($conexion instanceof PDO) ? $conexion->query("SELECT COUNT(*) as total FROM historial_planillas") : @mysqli_query($conexion, "SELECT COUNT(*) as total FROM historial_planillas");
    if ($res_hp) {
        $row_hp = ($conexion instanceof PDO) ? $res_hp->fetch(PDO::FETCH_ASSOC) : mysqli_fetch_assoc($res_hp);
        $total_planillas = (int)($row_hp['total'] ?? 0);
    }
} catch (Exception $e) {}

$total_constancias = 0;
try {
    $res_hc = ($conexion instanceof PDO) ? $conexion->query("SELECT COUNT(*) as total FROM historial_constancias") : @mysqli_query($conexion, "SELECT COUNT(*) as total FROM historial_constancias");
    if ($res_hc) {
        $row_hc = ($conexion instanceof PDO) ? $res_hc->fetch(PDO::FETCH_ASSOC) : mysqli_fetch_assoc($res_hc);
        $total_constancias = (int)($row_hc['total'] ?? 0);
    }
} catch (Exception $e) {}

$total_documentos = $total_planillas + $total_constancias;

$total_historial = 0;
try {
    $res_hist = ($conexion instanceof PDO) ? $conexion->query("SELECT COUNT(*) as total FROM historial") : @mysqli_query($conexion, "SELECT COUNT(*) as total FROM historial");
    if ($res_hist) {
        $row_h = ($conexion instanceof PDO) ? $res_hist->fetch(PDO::FETCH_ASSOC) : mysqli_fetch_assoc($res_hist);
        $total_historial = (int)($row_h['total'] ?? 0);
    }
} catch (Exception $e) {}

$total_usuarios = 0;
$usuarios_activos = 0;
try {
    $sql_usu = "SELECT COUNT(*) as total, SUM(CASE WHEN COALESCE(estado, 'Activo') = 'Activo' THEN 1 ELSE 0 END) as activos FROM usuarios";
    $res_usu = ($conexion instanceof PDO) ? $conexion->query($sql_usu) : @mysqli_query($conexion, $sql_usu);
    if ($res_usu) {
        $row_u = ($conexion instanceof PDO) ? $res_usu->fetch(PDO::FETCH_ASSOC) : mysqli_fetch_assoc($res_usu);
        $total_usuarios = (int)($row_u['total'] ?? 0);
        $usuarios_activos = (int)($row_u['activos'] ?? 0);
    }
} catch (Exception $e) {}

$conf_db = [];
if ($conexion instanceof PDO) {
    try {
        $res_conf = $conexion->query("SELECT clave, valor FROM configuracion");
        if ($res_conf) {
            while($r = $res_conf->fetch(PDO::FETCH_ASSOC)) {
                $conf_db[$r['clave']] = $r['valor'];
            }
        }
    } catch (Exception $e) {}
} else {
    $res_conf = @mysqli_query($conexion, "SELECT clave, valor FROM configuracion");
    if ($res_conf) {
        while($r = mysqli_fetch_assoc($res_conf)) {
            $conf_db[$r['clave']] = $r['valor'];
        }
    }
}

$estado_config = $conf_db['estado'] ?? 'Activo';
$proceso_activo = ($estado_config === 'Activo' && !empty($conf_db['periodo_escolar']));
$estado_proceso_inscripcion = $proceso_activo ? 'Activo' : 'Inactivo';
$conf = [
    'nombre_escuela'  => ($estado_config === 'Activo' && !empty($conf_db['nombre_escuela'])) ? $conf_db['nombre_escuela'] : 'No registrado',
    'periodo_escolar' => ($estado_config === 'Activo' && !empty($conf_db['periodo_escolar'])) ? $conf_db['periodo_escolar'] : 'Sin período'
];

$es_administrador = isset($_SESSION['rol']) && $_SESSION['rol'] === 'Administrador';
$rol_actual = $_SESSION['rol'] ?? 'Administrador';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inicio - Sistema de Inscripción</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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

        body.dark-mode .panel-box, body.dark-mode .top-header-bar {
            background-color: var(--bg-surface) !important; border-color: var(--border-color) !important; color: var(--text-primary) !important;
        }
        body.dark-mode .mini-stat-card {
            background-color: var(--bg-surface-alt) !important; border-color: var(--border-color) !important; color: var(--text-primary) !important;
        }

        html, body {
            min-height: 100vh; margin: 0; padding: 0;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            background-color: var(--bg-main); color: var(--text-primary);
            display: flex; width: 100%; overflow-x: hidden; transition: background-color 0.25s ease, color 0.25s ease;
        }

        .main-content {
            flex: 1; min-width: 0; max-width: 100%; padding: 16px 20px 24px 20px;
            box-sizing: border-box; position: relative; z-index: 1; align-self: stretch; background-color: var(--bg-main);
        }

        .top-header-bar {
            background-color: var(--bg-surface); padding: 10px 18px;
            border-radius: 12px; border: 1px solid var(--border-color);
            box-shadow: var(--card-shadow); display: flex;
            justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap: wrap; gap: 10px;
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
        .role-badge.badge-admin { background-color: rgba(239, 68, 68, 0.15); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.3); }
        .role-badge.badge-coord { background-color: rgba(59, 130, 246, 0.15); color: #3b82f6; border: 1px solid rgba(37, 99, 235, 0.3); }

        .dashboard-header-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; flex-wrap: wrap; gap: 8px; }
        .dashboard-title { font-size: 1.3rem; font-weight: 800; color: var(--text-primary); margin: 0; }
        .dashboard-subtitle { font-size: 0.8rem; font-weight: 600; color: var(--text-secondary); margin: 2px 0 0 0; }

        .kpi-gradient-card {
            border-radius: 12px; padding: 16px 18px; color: #ffffff;
            position: relative; overflow: hidden; box-shadow: 0 8px 20px -6px rgba(0, 0, 0, 0.15);
            display: flex; flex-direction: column; justify-content: space-between;
            width: 100%; height: 100%; min-height: 124px; box-sizing: border-box;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .kpi-gradient-card:hover { transform: translateY(-2px); box-shadow: 0 12px 24px -6px rgba(0, 0, 0, 0.22); }
        .kpi-blue { background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%); }
        .kpi-cyan { background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%); }
        .kpi-indigo { background: linear-gradient(135deg, #7c3aed 0%, #6d28d9 100%); }
        .kpi-emerald { background: linear-gradient(135deg, #059669 0%, #047857 100%); }

        .kpi-top-row { display: flex; justify-content: space-between; align-items: flex-start; gap: 8px; }
        .kpi-text-col { min-width: 0; flex: 1; }
        .kpi-label { font-size: 0.72rem; font-weight: 700; text-transform: uppercase; opacity: 0.9; margin-bottom: 4px; line-height: 1.25; }
        .kpi-value { font-size: 1.8rem; font-weight: 800; line-height: 1.1; min-height: 32px; display: flex; align-items: center; margin: 0; }
        .kpi-icon-box {
            width: 40px; height: 40px; border-radius: 10px; background: rgba(255, 255, 255, 0.22);
            display: flex; align-items: center; justify-content: center; font-size: 1.15rem; color: #ffffff; flex-shrink: 0;
        }
        .kpi-footer-note { font-size: 0.74rem; opacity: 0.9; margin-top: 10px; display: flex; align-items: center; gap: 5px; font-weight: 500; line-height: 1.25; }

        .panel-box {
            background-color: var(--bg-surface); border: 1px solid var(--border-color);
            border-radius: 12px; padding: 14px 16px; box-shadow: var(--card-shadow);
            width: 100%; height: 100%; display: flex; flex-direction: column; box-sizing: border-box;
        }
        .panel-box-title { font-size: 0.85rem; font-weight: 700; color: var(--text-primary); margin: 0 0 12px 0; display: flex; align-items: center; gap: 8px; line-height: 1.3; }
        .panel-stats-row { flex: 1; }

        .mini-stat-card {
            background-color: var(--bg-surface-alt); border: 1px solid var(--border-color);
            border-left: 4px solid #2563eb; border-radius: 10px; padding: 12px 14px;
            display: flex; align-items: center; gap: 12px;
            width: 100%; height: 100%; min-height: 94px; box-sizing: border-box;
            transition: transform 0.2s ease, border-color 0.2s ease;
        }
        .mini-stat-card:hover { transform: translateY(-1px); }
        .mini-stat-card.border-cyan { border-left-color: #06b6d4; }
        .mini-stat-card.border-emerald { border-left-color: #059669; }
        .mini-stat-card.border-indigo { border-left-color: #7c3aed; }
        .mini-stat-icon { width: 38px; height: 38px; border-radius: 9px; display: flex; align-items: center; justify-content: center; font-size: 1.05rem; flex-shrink: 0; }
        .mini-stat-icon.bg-soft-blue { background: rgba(37, 99, 235, 0.15); color: #2563eb; }
        .mini-stat-icon.bg-soft-cyan { background: rgba(6, 182, 212, 0.15); color: #06b6d4; }
        .mini-stat-icon.bg-soft-emerald { background: rgba(5, 150, 105, 0.15); color: #059669; }
        .mini-stat-icon.bg-soft-indigo { background: rgba(124, 58, 237, 0.15); color: #7c3aed; }
        .mini-stat-body { min-width: 0; flex: 1; display: flex; flex-direction: column; justify-content: center; }
        .mini-stat-num { font-size: 1.15rem; font-weight: 800; color: var(--text-primary); line-height: 1.15; margin: 0; display: flex; align-items: center; gap: 6px; }
        .mini-stat-label { font-size: 0.72rem; color: var(--text-secondary); font-weight: 600; margin: 3px 0 0 0; line-height: 1.25; }
        .mini-stat-meta { display: flex; flex-wrap: wrap; align-items: center; gap: 5px; margin-top: 6px; }
        .meta-pill {
            font-size: 0.66rem; font-weight: 700; padding: 2px 7px; border-radius: 50rem;
            background: rgba(37, 99, 235, 0.12); color: #2563eb; white-space: nowrap; line-height: 1.3;
        }
        .meta-pill.pill-cyan { background: rgba(6, 182, 212, 0.14); color: #0891b2; }
        .meta-pill.pill-emerald { background: rgba(5, 150, 105, 0.14); color: #059669; }
        .meta-pill.pill-indigo { background: rgba(124, 58, 237, 0.14); color: #7c3aed; }
        body.dark-mode .meta-pill { background: rgba(59, 130, 246, 0.2); color: #60a5fa; }
        body.dark-mode .meta-pill.pill-cyan { background: rgba(6, 182, 212, 0.2); color: #22d3ee; }
        body.dark-mode .meta-pill.pill-emerald { background: rgba(16, 185, 129, 0.2); color: #34d399; }
        body.dark-mode .meta-pill.pill-indigo { background: rgba(139, 92, 246, 0.22); color: #a78bfa; }

        .status-dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; flex-shrink: 0; }
        .status-dot.dot-active { background-color: #10b981; box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.2); }
        .status-dot.dot-inactive { background-color: #f59e0b; box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.2); }

        .chart-container-box {
            position: relative; height: 180px; max-height: 180px; width: 100%;
            display: flex; align-items: center; justify-content: center;
            flex: 0 0 180px; overflow: hidden;
        }

        @media (max-width: 991px) { html, body { flex-direction: column; } .main-content { padding: 12px; } }
        @media (max-width: 768px) { .top-header-bar { flex-direction: column; align-items: stretch; gap: 8px; } .top-actions { justify-content: space-between; } }
    </style>
</head>
<body>

    <?php include("menu.php"); ?>

    <main class="main-content">
        <div class="top-header-bar">
            <div class="top-breadcrumb">
                <i class="fas fa-home" style="color: #2563eb;"></i>
                <span>Inicio</span>
            </div>

            <div class="top-actions">
                <button type="button" class="tool-action-btn btn-refresh-cache" onclick="actualizarDashboard()" title="Actualizar datos">
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

        <div class="dashboard-header-row">
            <div>
                <h1 class="dashboard-title">Panel de Control Escolar</h1>
                <p class="dashboard-subtitle"><?php echo htmlspecialchars($conf['nombre_escuela']); ?></p>
            </div>
        </div>

        <div class="row g-3 mb-3 align-items-stretch">
            <div class="col-12 col-sm-6 col-xl-3 d-flex">
                <div class="kpi-gradient-card kpi-blue">
                    <div class="kpi-top-row">
                        <div class="kpi-text-col">
                            <div class="kpi-label">Estudiantes Activos</div>
                            <h3 class="kpi-value"><?php echo $total_alumnos; ?></h3>
                        </div>
                        <div class="kpi-icon-box"><i class="fas fa-user-graduate"></i></div>
                    </div>
                    <div class="kpi-footer-note"><i class="fas fa-check-circle"></i> Total inscritos en el sistema</div>
                </div>
            </div>

            <div class="col-12 col-sm-6 col-xl-3 d-flex">
                <div class="kpi-gradient-card kpi-cyan">
                    <div class="kpi-top-row">
                        <div class="kpi-text-col">
                            <div class="kpi-label">Eventos en Calendario</div>
                            <h3 class="kpi-value"><?php echo $total_eventos; ?></h3>
                        </div>
                        <div class="kpi-icon-box"><i class="fas fa-calendar-check"></i></div>
                    </div>
                    <div class="kpi-footer-note"><i class="fas fa-clock"></i> Actividades escolares registradas</div>
                </div>
            </div>

            <div class="col-12 col-sm-6 col-xl-3 d-flex">
                <div class="kpi-gradient-card kpi-emerald">
                    <div class="kpi-top-row">
                        <div class="kpi-text-col">
                            <div class="kpi-label">Autoridades Firmantes</div>
                            <h3 class="kpi-value"><?php echo $total_autoridades; ?></h3>
                        </div>
                        <div class="kpi-icon-box"><i class="fas fa-user-tie"></i></div>
                    </div>
                    <div class="kpi-footer-note"><i class="fas fa-signature"></i> Directivos habilitados en actas</div>
                </div>
            </div>

            <div class="col-12 col-sm-6 col-xl-3 d-flex">
                <div class="kpi-gradient-card kpi-indigo">
                    <div class="kpi-top-row">
                        <div class="kpi-text-col">
                            <div class="kpi-label">Período Actual</div>
                            <h3 class="kpi-value" style="font-size: <?php echo (strlen($conf['periodo_escolar']) > 6) ? '1.3rem' : '1.8rem'; ?>;"><?php echo htmlspecialchars($conf['periodo_escolar']); ?></h3>
                        </div>
                        <div class="kpi-icon-box"><i class="fas fa-calendar-alt"></i></div>
                    </div>
                    <div class="kpi-footer-note"><i class="fas fa-shield-alt"></i> Año escolar vigente</div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-3 align-items-stretch">
            <div class="col-12 col-lg-6 d-flex">
                <div class="panel-box">
                    <h4 class="panel-box-title"><i class="fas fa-chart-line" style="color: #2563eb;"></i> Estadísticas de Gestión Escolar</h4>
                    <div class="row g-2 panel-stats-row align-items-stretch">
                        <div class="col-12 col-sm-6 d-flex">
                            <div class="mini-stat-card">
                                <div class="mini-stat-icon bg-soft-blue"><i class="fas fa-door-open"></i></div>
                                <div class="mini-stat-body">
                                    <p class="mini-stat-num">
                                        <span class="status-dot <?php echo $proceso_activo ? 'dot-active' : 'dot-inactive'; ?>"></span>
                                        <?php echo htmlspecialchars($estado_proceso_inscripcion); ?>
                                    </p>
                                    <p class="mini-stat-label">Proceso de Inscripción</p>
                                    <div class="mini-stat-meta">
                                        <span class="meta-pill"><?php echo $total_docentes; ?> Docentes activos</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-sm-6 d-flex">
                            <div class="mini-stat-card border-cyan">
                                <div class="mini-stat-icon bg-soft-cyan"><i class="fas fa-file-alt"></i></div>
                                <div class="mini-stat-body">
                                    <p class="mini-stat-num"><?php echo $total_documentos; ?></p>
                                    <p class="mini-stat-label">Documentos Emitidos</p>
                                    <div class="mini-stat-meta">
                                        <span class="meta-pill pill-cyan"><?php echo $total_planillas; ?> Planillas · <?php echo $total_constancias; ?> Constancias</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-lg-6 d-flex">
                <div class="panel-box">
                    <h4 class="panel-box-title"><i class="fas fa-users-cog" style="color: #7c3aed;"></i> Seguridad y Usuarios en el Sistema</h4>
                    <div class="row g-2 panel-stats-row align-items-stretch">
                        <div class="col-12 col-sm-6 d-flex">
                            <div class="mini-stat-card border-emerald">
                                <div class="mini-stat-icon bg-soft-emerald"><i class="fas fa-user-shield"></i></div>
                                <div class="mini-stat-body">
                                    <p class="mini-stat-num"><?php echo $total_usuarios; ?></p>
                                    <p class="mini-stat-label">Cuentas Registradas</p>
                                    <div class="mini-stat-meta">
                                        <span class="meta-pill pill-emerald"><?php echo $usuarios_activos; ?> Activas</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-sm-6 d-flex">
                            <div class="mini-stat-card border-indigo">
                                <div class="mini-stat-icon bg-soft-indigo"><i class="fas fa-history"></i></div>
                                <div class="mini-stat-body">
                                    <p class="mini-stat-num"><?php echo $total_historial; ?></p>
                                    <p class="mini-stat-label">Eventos en Historial</p>
                                    <div class="mini-stat-meta">
                                        <span class="meta-pill pill-indigo">Auditoría activa</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-3 align-items-stretch">
            <div class="col-12 col-lg-6 d-flex">
                <div class="panel-box">
                    <h4 class="panel-box-title"><i class="fas fa-chart-pie" style="color: #2563eb;"></i> Distribución de Registros del Sistema</h4>
                    <div class="chart-container-box"><canvas id="doughnutChart"></canvas></div>
                </div>
            </div>

            <div class="col-12 col-lg-6 d-flex">
                <div class="panel-box">
                    <h4 class="panel-box-title"><i class="fas fa-chart-bar" style="color: #06b6d4;"></i> Actividad por Módulo Escolar</h4>
                    <div class="chart-container-box"><canvas id="barChart"></canvas></div>
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
            location.reload();
        }

        function actualizarDashboard() {
            const icon = document.getElementById('refreshIcon');
            if (icon) icon.classList.add('fa-spin');
            setTimeout(() => { window.location.reload(); }, 450);
        }

        document.addEventListener('DOMContentLoaded', function () {
            const totalAlumnos = <?php echo max(0, (int)$total_alumnos); ?>;
            const totalDocentes = <?php echo max(0, (int)$total_docentes); ?>;
            const totalEventos = <?php echo max(0, (int)$total_eventos); ?>;
            const totalAutoridades = <?php echo max(0, (int)$total_autoridades); ?>;
            const totalPlanillas = <?php echo max(0, (int)$total_planillas); ?>;
            const totalConstancias = <?php echo max(0, (int)$total_constancias); ?>;
            const totalHistorial = <?php echo max(0, (int)$total_historial); ?>;

            const isDark = document.body.classList.contains('dark-mode');
            const textColor = isDark ? '#94a3b8' : '#475569';
            const gridColor = isDark ? '#27354f' : '#e2e8f0';

            const ctxDoughnut = document.getElementById('doughnutChart');
            if (ctxDoughnut) {
                new Chart(ctxDoughnut, {
                    type: 'doughnut',
                    data: {
                        labels: ['Estudiantes Activos', 'Docentes Activos', 'Eventos Calendario', 'Autoridades'],
                        datasets: [{
                            data: [totalAlumnos, totalDocentes, totalEventos, totalAutoridades],
                            backgroundColor: ['#2563eb', '#7c3aed', '#06b6d4', '#059669'],
                            borderColor: isDark ? '#131c2e' : '#ffffff',
                            borderWidth: 2
                        }]
                    },
                    options: { responsive: true, maintainAspectRatio: false, cutout: '65%', plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 10 }, color: textColor } } } }
                });
            }

            const ctxBar = document.getElementById('barChart');
            if (ctxBar) {
                new Chart(ctxBar, {
                    type: 'bar',
                    data: {
                        labels: ['Inscritos', 'Docentes', 'Planillas', 'Constancias', 'Historial'],
                        datasets: [{ label: 'Registros Activos', data: [totalAlumnos, totalDocentes, totalPlanillas, totalConstancias, totalHistorial], backgroundColor: ['#2563eb', '#059669', '#06b6d4', '#f59e0b', '#7c3aed'], borderRadius: 6 }]
                    },
                    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { ticks: { color: textColor, font: { size: 10 } }, grid: { color: gridColor } }, y: { beginAtZero: true, ticks: { color: textColor, precision: 0, font: { size: 10 } }, grid: { color: gridColor } } } }
                });
            }
        });
    </script>
</body>
</html>