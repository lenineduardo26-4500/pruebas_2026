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

$total_inscritos = 0;
$res_ins = ($conexion instanceof PDO) ? $conexion->query("SELECT COUNT(*) as total FROM inscripcion WHERE tipo_estudiante != 'Retirado'") : mysqli_query($conexion, "SELECT COUNT(*) as total FROM inscripcion WHERE tipo_estudiante != 'Retirado'");
if ($res_ins) {
    $row = ($conexion instanceof PDO) ? $res_ins->fetch(PDO::FETCH_ASSOC) : mysqli_fetch_assoc($res_ins);
    $total_inscritos = (int)($row['total'] ?? 0);
}

$total_grados = 0;
$res_gr = ($conexion instanceof PDO) ? $conexion->query("SELECT COUNT(*) as total FROM grados") : mysqli_query($conexion, "SELECT COUNT(*) as total FROM grados");
if ($res_gr) {
    $row = ($conexion instanceof PDO) ? $res_gr->fetch(PDO::FETCH_ASSOC) : mysqli_fetch_assoc($res_gr);
    $total_grados = (int)($row['total'] ?? 0);
}

$total_secciones = 0;
$res_sec = ($conexion instanceof PDO) ? $conexion->query("SELECT COUNT(*) as total FROM secciones") : mysqli_query($conexion, "SELECT COUNT(*) as total FROM secciones");
if ($res_sec) {
    $row = ($conexion instanceof PDO) ? $res_sec->fetch(PDO::FETCH_ASSOC) : mysqli_fetch_assoc($res_sec);
    $total_secciones = (int)($row['total'] ?? 0);
}

$total_docentes = 0;
$res_doc = ($conexion instanceof PDO) ? $conexion->query("SELECT COUNT(*) as total FROM docentes WHERE estado = 'Activo'") : mysqli_query($conexion, "SELECT COUNT(*) as total FROM docentes WHERE estado = 'Activo'");
if ($res_doc) {
    $row = ($conexion instanceof PDO) ? $res_doc->fetch(PDO::FETCH_ASSOC) : mysqli_fetch_assoc($res_doc);
    $total_docentes = (int)($row['total'] ?? 0);
}

$varones_est = 0;
$hembras_est = 0;
$sql_gen_est = "SELECT 
    SUM(CASE WHEN e.`género` = 'MASCULINO' THEN 1 ELSE 0 END) as varones,
    SUM(CASE WHEN e.`género` = 'FEMENINO' THEN 1 ELSE 0 END) as hembras
    FROM inscripcion i
    INNER JOIN estudiante e ON i.`cédula_escolar_estudiante` = e.`cédula_escolar`
    WHERE i.tipo_estudiante != 'Retirado'";
$res_ge = ($conexion instanceof PDO) ? $conexion->query($sql_gen_est) : mysqli_query($conexion, $sql_gen_est);
if ($res_ge) {
    $row_ge = ($conexion instanceof PDO) ? $res_ge->fetch(PDO::FETCH_ASSOC) : mysqli_fetch_assoc($res_ge);
    $varones_est = (int)($row_ge['varones'] ?? 0);
    $hembras_est = (int)($row_ge['hembras'] ?? 0);
}

$varones_doc = 0;
$hembras_doc = 0;
$sql_gen_doc = "SELECT 
    SUM(CASE WHEN UPPER(TRIM(COALESCE(`genero`, ''))) IN ('MASCULINO', 'M') THEN 1 ELSE 0 END) as varones,
    SUM(CASE WHEN UPPER(TRIM(COALESCE(`genero`, ''))) IN ('FEMENINO', 'F') THEN 1 ELSE 0 END) as hembras
    FROM docentes
    WHERE estado = 'Activo'";
$res_gd = ($conexion instanceof PDO) ? @$conexion->query($sql_gen_doc) : @mysqli_query($conexion, $sql_gen_doc);
if ($res_gd) {
    $row_gd = ($conexion instanceof PDO) ? $res_gd->fetch(PDO::FETCH_ASSOC) : @mysqli_fetch_assoc($res_gd);
    $varones_doc = (int)($row_gd['varones'] ?? 0);
    $hembras_doc = (int)($row_gd['hembras'] ?? 0);
}

$sql_tabla = "SELECT 
    g.nombre_grado AS grado,
    s.nombre_seccion AS seccion,
    COUNT(i.id) AS inscritos,
    SUM(CASE WHEN e.`género` = 'MASCULINO' THEN 1 ELSE 0 END) AS varones,
    SUM(CASE WHEN e.`género` = 'FEMENINO' THEN 1 ELSE 0 END) AS hembras,
    COALESCE(d.nombres_apellidos, 'Sin asignar') AS docente
FROM inscripcion i
INNER JOIN estudiante e ON i.`cédula_escolar_estudiante` = e.`cédula_escolar`
LEFT JOIN grados g ON i.id_grado = g.id
LEFT JOIN secciones s ON i.id_seccion = s.id
LEFT JOIN docente_seccion ds ON (
    ds.id_grado = i.id_grado 
    AND ds.id_seccion = i.id_seccion 
    AND ds.año_escolar = i.año_escolar
)
LEFT JOIN docentes d ON ds.id_docente = d.id
WHERE i.tipo_estudiante != 'Retirado'
GROUP BY i.id_grado, i.id_seccion, i.año_escolar, d.nombres_apellidos
ORDER BY g.id ASC, s.nombre_seccion ASC";

$resultado_tabla = ($conexion instanceof PDO) ? $conexion->query($sql_tabla) : mysqli_query($conexion, $sql_tabla);

$es_administrador = isset($_SESSION['rol']) && $_SESSION['rol'] === 'Administrador';
$rol_actual = $_SESSION['rol'] ?? 'Administrador';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estadística Estudiantil - Sistema de Inscripción</title>
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

        body.dark-mode .panel-box, body.dark-mode .top-header-bar, body.dark-mode table, body.dark-mode .table-responsive {
            background-color: var(--bg-surface) !important; border-color: var(--border-color) !important; color: var(--text-primary) !important;
        }
        body.dark-mode th { background-color: #1e293b !important; color: var(--text-primary) !important; border-color: var(--border-color) !important; }
        body.dark-mode td { background-color: var(--bg-surface) !important; color: var(--text-primary) !important; border-color: var(--border-color) !important; }
        body.dark-mode tr:nth-child(even) td { background-color: var(--bg-surface-alt) !important; }
        body.dark-mode tr:hover td { background-color: #22304a !important; }

        html, body {
            height: 100vh; width: 100%; margin: 0; padding: 0;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            background-color: var(--bg-main); color: var(--text-primary);
            display: flex; overflow: hidden; transition: background-color 0.25s ease, color 0.25s ease;
        }

        .main-content {
            flex: 1 1 0%; width: 100%; min-width: 0; max-width: 100%; height: 100vh;
            padding: 12px 18px 14px 18px; box-sizing: border-box;
            display: flex; flex-direction: column; overflow: hidden; background-color: var(--bg-main);
        }

        .top-header-bar {
            background-color: var(--bg-surface); padding: 8px 16px;
            border-radius: 12px; border: 1px solid var(--border-color);
            box-shadow: var(--card-shadow); display: flex;
            justify-content: space-between; align-items: center;
            margin-bottom: 10px; flex-wrap: wrap; gap: 8px; flex-shrink: 0;
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
            width: 32px; height: 32px; border-radius: 50%;
            border: 1px solid var(--border-color); background-color: var(--bg-surface-alt);
            color: var(--text-secondary); display: flex; align-items: center; justify-content: center; cursor: pointer;
        }
        .user-profile-pill {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 4px 12px; border-radius: 50rem;
            background-color: var(--bg-surface-alt); border: 1px solid var(--border-color);
            font-size: 0.78rem; font-weight: 600; color: var(--text-primary);
        }
        .role-badge { padding: 2px 8px; border-radius: 5px; font-size: 0.68rem; font-weight: 700; text-transform: uppercase; }
        .role-badge.badge-admin { background-color: rgba(239, 68, 68, 0.15); color: #ef4444; }
        .role-badge.badge-coord { background-color: rgba(59, 130, 246, 0.15); color: #3b82f6; }

        .dashboard-header-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; flex-shrink: 0; }
        .dashboard-title { font-size: 1.12rem; font-weight: 800; color: var(--text-primary); margin: 0; }

        .kpi-gradient-card {
            border-radius: 10px; padding: 10px 14px; color: #ffffff;
            position: relative; overflow: hidden; box-shadow: 0 6px 15px -4px rgba(0, 0, 0, 0.15);
            display: flex; flex-direction: column; justify-content: space-between; min-height: 78px;
        }
        .kpi-blue { background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%); }
        .kpi-cyan { background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%); }
        .kpi-emerald { background: linear-gradient(135deg, #059669 0%, #047857 100%); }
        .kpi-indigo { background: linear-gradient(135deg, #7c3aed 0%, #6d28d9 100%); }

        .kpi-top-row { display: flex; justify-content: space-between; align-items: center; gap: 8px; }
        .kpi-label { font-size: 0.66rem; font-weight: 700; text-transform: uppercase; opacity: 0.9; margin-bottom: 2px; }
        .kpi-value { font-size: 1.4rem; font-weight: 800; line-height: 1; margin: 0; }
        .kpi-icon-box {
            width: 34px; height: 34px; border-radius: 8px; background: rgba(255, 255, 255, 0.22);
            display: flex; align-items: center; justify-content: center; font-size: 1rem; color: #ffffff; flex-shrink: 0;
        }
        .kpi-footer-note { font-size: 0.68rem; opacity: 0.9; margin-top: 4px; font-weight: 500; }

        .panel-box {
            background-color: var(--bg-surface); border: 1px solid var(--border-color);
            border-radius: 12px; padding: 10px 14px; box-shadow: var(--card-shadow);
        }
        .panel-box-title { font-size: 0.82rem; font-weight: 700; color: var(--text-primary); margin: 0 0 8px 0; display: flex; align-items: center; gap: 7px; }

        .sex-panel-body { display: flex; align-items: center; justify-content: space-between; gap: 14px; }
        .sex-boxes-col { flex: 1; min-width: 0; }
        .sex-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 6px; }
        .sex-box-male { background-color: #eff6ff; border: 1px solid #dbeafe; border-radius: 10px; padding: 8px 12px; display: flex; align-items: center; gap: 10px; }
        .sex-box-female { background-color: #fff1f2; border: 1px solid #ffe4e6; border-radius: 10px; padding: 8px 12px; display: flex; align-items: center; gap: 10px; }
        .sex-icon-male { width: 32px; height: 32px; background-color: #dbeafe; color: #2563eb; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; flex-shrink: 0; }
        .sex-icon-female { width: 32px; height: 32px; background-color: #ffe4e6; color: #e11d48; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; flex-shrink: 0; }
        .sex-number-male { font-size: 1.05rem; font-weight: 800; color: #3b82f6; line-height: 1.1; margin: 0; }
        .sex-number-female { font-size: 1.05rem; font-weight: 800; color: #f43f5e; line-height: 1.1; margin: 0; }
        .sex-label { font-size: 0.68rem; color: var(--text-secondary); display: block; margin-top: 1px; }
        .sex-footer { text-align: center; font-size: 0.72rem; color: var(--text-secondary); padding-top: 5px; border-top: 1px solid var(--border-color); }
        .mini-doughnut-box { width: 82px; height: 82px; position: relative; flex-shrink: 0; display: flex; align-items: center; justify-content: center; }

        .table-card { flex: 1; min-height: 0; display: flex; flex-direction: column; margin-bottom: 0; }
        .table-responsive { flex: 1; min-height: 0; width: 100%; overflow-y: auto; overflow-x: auto; border: 1px solid var(--border-color); border-radius: 8px; background-color: var(--bg-surface); }
        table { width: 100%; border-collapse: collapse; font-size: 12.5px; background-color: var(--bg-surface); color: var(--text-primary); margin-bottom: 0; }
        th, td { border: 1px solid var(--border-color); padding: 8px 10px; text-align: left; white-space: nowrap; }
        th { background-color: #f1f5f9; color: var(--text-primary); font-weight: 700; position: sticky; top: 0; z-index: 10; }
        tr:nth-child(even) { background-color: var(--bg-surface-alt); }
        tr:hover { background-color: rgba(37, 99, 235, 0.05); }

        @media (max-width: 991px) {
            html, body { height: auto; min-height: 100vh; flex-direction: column; overflow-y: auto; }
            .main-content { height: auto; overflow: visible; padding: 12px; }
            .table-responsive { max-height: 350px; }
        }
    </style>
</head>
<body>

    <?php include("menu.php"); ?>

    <main class="main-content">
        <div class="top-header-bar">
            <div class="top-breadcrumb">
                <i class="fas fa-chart-bar" style="color: #2563eb;"></i>
                <span>Estadística Estudiantil</span>
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

        <div class="dashboard-header-row">
            <h1 class="dashboard-title">Estadística Estudiantil - Grados (1° a 6°) y Secciones Activas</h1>
        </div>

        <div class="row g-2 mb-2" style="flex-shrink: 0;">
            <div class="col-6 col-xl-3">
                <div class="kpi-gradient-card kpi-blue">
                    <div class="kpi-top-row">
                        <div>
                            <div class="kpi-label">Total Estudiantes</div>
                            <h3 class="kpi-value"><?php echo $total_inscritos; ?></h3>
                        </div>
                        <div class="kpi-icon-box"><i class="fas fa-users"></i></div>
                    </div>
                    <div class="kpi-footer-note">Inscritos oficialmente</div>
                </div>
            </div>

            <div class="col-6 col-xl-3">
                <div class="kpi-gradient-card kpi-cyan">
                    <div class="kpi-top-row">
                        <div>
                            <div class="kpi-label">Total Grados</div>
                            <h3 class="kpi-value"><?php echo $total_grados; ?></h3>
                        </div>
                        <div class="kpi-icon-box"><i class="fas fa-layer-group"></i></div>
                    </div>
                    <div class="kpi-footer-note">Niveles (1° a 6°)</div>
                </div>
            </div>

            <div class="col-6 col-xl-3">
                <div class="kpi-gradient-card kpi-emerald">
                    <div class="kpi-top-row">
                        <div>
                            <div class="kpi-label">Total Secciones</div>
                            <h3 class="kpi-value"><?php echo $total_secciones; ?></h3>
                        </div>
                        <div class="kpi-icon-box"><i class="fas fa-list-check"></i></div>
                    </div>
                    <div class="kpi-footer-note">Secciones habilitadas</div>
                </div>
            </div>

            <div class="col-6 col-xl-3">
                <div class="kpi-gradient-card kpi-indigo">
                    <div class="kpi-top-row">
                        <div>
                            <div class="kpi-label">Docentes Activos</div>
                            <h3 class="kpi-value"><?php echo $total_docentes; ?></h3>
                        </div>
                        <div class="kpi-icon-box"><i class="fas fa-chalkboard-user"></i></div>
                    </div>
                    <div class="kpi-footer-note">Personal activo</div>
                </div>
            </div>
        </div>

        <div class="row g-2 mb-2" style="flex-shrink: 0;">
            <div class="col-12 col-lg-6">
                <div class="panel-box">
                    <h4 class="panel-box-title"><i class="fas fa-users" style="color: #10b981;"></i> Estudiantes por Sexo</h4>
                    <div class="sex-panel-body">
                        <div class="sex-boxes-col">
                            <div class="sex-grid">
                                <div class="sex-box-male">
                                    <div class="sex-icon-male"><i class="fas fa-male"></i></div>
                                    <div>
                                        <h4 class="sex-number-male"><?php echo $varones_est; ?></h4>
                                        <span class="sex-label">Hombres</span>
                                    </div>
                                </div>
                                <div class="sex-box-female">
                                    <div class="sex-icon-female"><i class="fas fa-female"></i></div>
                                    <div>
                                        <h4 class="sex-number-female"><?php echo $hembras_est; ?></h4>
                                        <span class="sex-label">Mujeres</span>
                                    </div>
                                </div>
                            </div>
                            <div class="sex-footer">Total: <strong><?php echo $total_inscritos; ?></strong> estudiantes</div>
                        </div>
                        <div class="mini-doughnut-box"><canvas id="chartEstudiantes"></canvas></div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-lg-6">
                <div class="panel-box">
                    <h4 class="panel-box-title"><i class="fas fa-user-tie" style="color: #2563eb;"></i> Docentes Activos</h4>
                    <div class="sex-panel-body">
                        <div class="sex-boxes-col">
                            <div class="sex-grid">
                                <div class="sex-box-male">
                                    <div class="sex-icon-male"><i class="fas fa-male"></i></div>
                                    <div>
                                        <h4 class="sex-number-male"><?php echo $varones_doc; ?></h4>
                                        <span class="sex-label">Hombres</span>
                                    </div>
                                </div>
                                <div class="sex-box-female">
                                    <div class="sex-icon-female"><i class="fas fa-female"></i></div>
                                    <div>
                                        <h4 class="sex-number-female"><?php echo $hembras_doc; ?></h4>
                                        <span class="sex-label">Mujeres</span>
                                    </div>
                                </div>
                            </div>
                            <div class="sex-footer">Total: <strong><?php echo $total_docentes; ?></strong> activos</div>
                        </div>
                        <div class="mini-doughnut-box"><canvas id="chartDocentes"></canvas></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="panel-box table-card">
            <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2" style="flex-shrink: 0;">
                <h5 class="mb-0 fw-bold" style="font-size: 0.88rem;">
                    <i class="fas fa-table" style="color: #2563eb; margin-right: 6px;"></i> Estructura de Grados (1° a 6°) y Secciones Activas
                </h5>
                <span style="font-size: 0.76rem; color: var(--text-secondary);">Total Docentes Activos: <strong><?php echo $total_docentes; ?></strong></span>
            </div>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Grado</th>
                            <th>Sección</th>
                            <th>Estudiantes Inscritos</th>
                            <th>Varones</th>
                            <th>Hembras</th>
                            <th>Docente Asignado</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($resultado_tabla && (($conexion instanceof PDO) ? $resultado_tabla->rowCount() > 0 : mysqli_num_rows($resultado_tabla) > 0)): ?>
                            <?php while($row = ($conexion instanceof PDO) ? $resultado_tabla->fetch(PDO::FETCH_ASSOC) : mysqli_fetch_assoc($resultado_tabla)): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($row['grado'] ?? 'No asignado'); ?>° Grado</strong></td>
                                    <td>
                                        <span style="background:rgba(124, 58, 237, 0.15); color:#8b5cf6; padding:3px 10px; border-radius:4px; font-weight:700; font-size:0.75rem;">
                                            Sección <?php echo htmlspecialchars($row['seccion'] ?? 'Única'); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span style="background:rgba(37, 99, 235, 0.15); color:#3b82f6; padding:3px 10px; border-radius:4px; font-weight:700; font-size:0.75rem;">
                                            <?php echo htmlspecialchars($row['inscritos']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span style="display:inline-flex; align-items:center; gap:6px; font-weight:700;">
                                            <i class="fas fa-male" style="color:#38bdf8; font-size:1.05rem;"></i>
                                            <?php echo htmlspecialchars($row['varones']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span style="display:inline-flex; align-items:center; gap:6px; font-weight:700;">
                                            <i class="fas fa-female" style="color:#f472b6; font-size:1.05rem;"></i>
                                            <?php echo htmlspecialchars($row['hembras']); ?>
                                        </span>
                                    </td>
                                    <td><strong><?php echo htmlspecialchars($row['docente'] ?? 'Sin asignar'); ?></strong></td>
                                    <td>
                                        <span style="background:rgba(16, 185, 129, 0.15); color:#10b981; padding:3px 10px; border-radius:4px; font-weight:600; font-size:0.75rem;">
                                            Activo
                                        </span>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" style="text-align: center; color: var(--text-secondary); padding: 25px;">
                                    No hay registros de inscripciones activas en este momento.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let chartEstInstance = null;
        let chartDocInstance = null;

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

            const newBorder = isDark ? '#131c2e' : '#ffffff';
            if (chartEstInstance) { chartEstInstance.data.datasets[0].borderColor = newBorder; chartEstInstance.update(); }
            if (chartDocInstance) { chartDocInstance.data.datasets[0].borderColor = newBorder; chartDocInstance.update(); }
        }

        function actualizarDashboard() {
            const icon = document.getElementById('refreshIcon');
            if (icon) { icon.classList.add('fa-spin'); }
            setTimeout(() => { window.location.reload(); }, 450);
        }

        document.addEventListener('DOMContentLoaded', function () {
            const varEst = <?php echo max(0, (int)$varones_est); ?>;
            const hemEst = <?php echo max(0, (int)$hembras_est); ?>;
            const varDoc = <?php echo max(0, (int)$varones_doc); ?>;
            const hemDoc = <?php echo max(0, (int)$hembras_doc); ?>;

            const isDark = document.body.classList.contains('dark-mode');
            const borderColor = isDark ? '#131c2e' : '#ffffff';

            const ctxEst = document.getElementById('chartEstudiantes');
            if (ctxEst) {
                chartEstInstance = new Chart(ctxEst, {
                    type: 'doughnut',
                    data: {
                        labels: ['Hombres', 'Mujeres'],
                        datasets: [{
                            data: (varEst + hemEst > 0) ? [varEst, hemEst] : [1, 1],
                            backgroundColor: ['#3b82f6', '#f43f5e'],
                            borderColor: borderColor,
                            borderWidth: 2
                        }]
                    },
                    options: { responsive: true, maintainAspectRatio: false, cutout: '65%', plugins: { legend: { display: false } } }
                });
            }

            const ctxDoc = document.getElementById('chartDocentes');
            if (ctxDoc) {
                chartDocInstance = new Chart(ctxDoc, {
                    type: 'doughnut',
                    data: {
                        labels: ['Hombres', 'Mujeres'],
                        datasets: [{
                            data: (varDoc + hemDoc > 0) ? [varDoc, hemDoc] : [1, 1],
                            backgroundColor: ['#3b82f6', '#f43f5e'],
                            borderColor: borderColor,
                            borderWidth: 2
                        }]
                    },
                    options: { responsive: true, maintainAspectRatio: false, cutout: '65%', plugins: { legend: { display: false } } }
                });
            }
        });
    </script>
</body>
</html>