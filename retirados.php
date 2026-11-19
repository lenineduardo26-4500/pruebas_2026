<?php
require_once("control_sesion.php");
verificarLogin();

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

$periodo_activo_conf = '';
if ($conexion instanceof PDO) {
    try {
        $st_pc = $conexion->query("SELECT valor FROM configuracion WHERE clave = 'periodo_escolar' LIMIT 1");
        $row_pc = $st_pc ? $st_pc->fetch(PDO::FETCH_ASSOC) : null;
        if ($row_pc && !empty($row_pc['valor'])) {
            $periodo_activo_conf = trim((string)$row_pc['valor']);
        }
    } catch (Exception $e) {}
} elseif ($conexion instanceof mysqli) {
    $res_pc = @mysqli_query($conexion, "SELECT valor FROM configuracion WHERE clave = 'periodo_escolar' LIMIT 1");
    if ($res_pc && $row_pc = mysqli_fetch_assoc($res_pc)) {
        if (!empty($row_pc['valor'])) {
            $periodo_activo_conf = trim((string)$row_pc['valor']);
        }
    }
}

function registrarHistorialRetirados($conexion, $descripcion, $usuario) {
    $fecha = date('Y-m-d H:i:s');
    if ($conexion instanceof PDO) {
        try {
            $stmt = $conexion->prepare("INSERT INTO historial (descripcion, usuario, fecha_hora) VALUES (:descripcion, :usuario, :fecha)");
            $stmt->execute([':descripcion' => $descripcion, ':usuario' => $usuario, ':fecha' => $fecha]);
        } catch (Exception $ex) {}
    } elseif ($conexion instanceof mysqli) {
        $desc_l = mysqli_real_escape_string($conexion, $descripcion);
        $usu_l = mysqli_real_escape_string($conexion, $usuario);
        @mysqli_query($conexion, "INSERT INTO historial (descripcion, usuario, fecha_hora) VALUES ('$desc_l', '$usu_l', '$fecha')");
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'reactivar_estudiante') {
    if (!validarTokenCSRF($_POST['csrf_token'] ?? '')) {
        $mensaje = "<div class='no-print' style='color: #721c24; background-color: #f8d7da; padding: 10px 14px; border-radius: 8px; margin-bottom: 15px; font-size: 0.85rem; font-weight: 600;'>Solicitud no válida. Por seguridad, recargue la página e intente nuevamente.</div>";
    } else {
        $cedula_reactivar = trim($_POST['reactivar_cedula'] ?? '');
        if (!empty($cedula_reactivar)) {
            if ($conexion instanceof PDO) {
                try {
                    $nombre_est_hist = '';
                    $st_nom = $conexion->prepare("SELECT nombres, apellidos FROM estudiante WHERE cédula_escolar = :cedula LIMIT 1");
                    $st_nom->execute([':cedula' => $cedula_reactivar]);
                    if ($r_nom = $st_nom->fetch(PDO::FETCH_ASSOC)) {
                        $nombre_est_hist = trim(($r_nom['nombres'] ?? '') . ' ' . ($r_nom['apellidos'] ?? ''));
                    }

                    $stmt_act = $conexion->prepare("UPDATE inscripcion SET tipo_estudiante = 'Estudiante regular' WHERE cédula_escolar_estudiante = :cedula");
                    $stmt_act->execute([':cedula' => $cedula_reactivar]);

                    try {
                        $stmt_del_ret = $conexion->prepare("DELETE FROM retirados WHERE cedula = :cedula");
                        $stmt_del_ret->execute([':cedula' => $cedula_reactivar]);
                    } catch (Exception $e_del) {}

                    $desc_react = !empty($nombre_est_hist)
                        ? "Reactivación del estudiante " . $nombre_est_hist . " (Cédula: " . $cedula_reactivar . ")"
                        : "Reactivación del estudiante con cédula: " . $cedula_reactivar;
                    registrarHistorialRetirados($conexion, $desc_react, $usuario_actual);

                    $mensaje = "<div class='no-print' style='color: #155724; background-color: #d4edda; padding: 10px 14px; border-radius: 8px; margin-bottom: 15px; font-size: 0.85rem; font-weight: 600;'>¡Estudiante reactivado exitosamente y devuelto al listado activo!</div>";
                } catch (Exception $e) {}
            } elseif ($conexion instanceof mysqli) {
                $c_esc = mysqli_real_escape_string($conexion, $cedula_reactivar);
                $nombre_est_hist = '';
                $res_nom = @mysqli_query($conexion, "SELECT nombres, apellidos FROM estudiante WHERE cédula_escolar = '$c_esc' LIMIT 1");
                if ($res_nom && $r_nom = mysqli_fetch_assoc($res_nom)) {
                    $nombre_est_hist = trim(($r_nom['nombres'] ?? '') . ' ' . ($r_nom['apellidos'] ?? ''));
                }

                @mysqli_query($conexion, "UPDATE inscripcion SET tipo_estudiante = 'Estudiante regular' WHERE cédula_escolar_estudiante = '$c_esc'");
                @mysqli_query($conexion, "DELETE FROM retirados WHERE cedula = '$c_esc'");

                $desc_react = !empty($nombre_est_hist)
                    ? "Reactivación del estudiante " . $nombre_est_hist . " (Cédula: " . $cedula_reactivar . ")"
                    : "Reactivación del estudiante con cédula: " . $cedula_reactivar;
                registrarHistorialRetirados($conexion, $desc_react, $usuario_actual);

                $mensaje = "<div class='no-print' style='color: #155724; background-color: #d4edda; padding: 10px 14px; border-radius: 8px; margin-bottom: 15px; font-size: 0.85rem; font-weight: 600;'>¡Estudiante reactivado exitosamente y devuelto al listado activo!</div>";
            }
        }
    }
}

$motivos_retiro = [];
if ($conexion instanceof PDO) {
    try {
        $st_mot = $conexion->query("SELECT cedula, motivo FROM retirados ORDER BY id DESC");
        if ($st_mot) {
            while ($rm = $st_mot->fetch(PDO::FETCH_ASSOC)) {
                $ced_m = trim((string)($rm['cedula'] ?? ''));
                $mot_m = trim((string)($rm['motivo'] ?? ''));
                if ($ced_m !== '' && !isset($motivos_retiro[$ced_m]) && $mot_m !== '') {
                    $motivos_retiro[$ced_m] = $mot_m;
                }
            }
        }
    } catch (Exception $e) {}
} elseif ($conexion instanceof mysqli) {
    $res_mot = @mysqli_query($conexion, "SELECT cedula, motivo FROM retirados ORDER BY id DESC");
    if ($res_mot) {
        while ($rm = mysqli_fetch_assoc($res_mot)) {
            $ced_m = trim((string)($rm['cedula'] ?? ''));
            $mot_m = trim((string)($rm['motivo'] ?? ''));
            if ($ced_m !== '' && !isset($motivos_retiro[$ced_m]) && $mot_m !== '') {
                $motivos_retiro[$ced_m] = $mot_m;
            }
        }
    }
}

$registros_retirados = [];
$grados_unicos = [];
$secciones_unicas = [];
$periodos_unicos = [];

$sql_retirados = "SELECT 
                    e.cédula_escolar,
                    e.nombres,
                    e.apellidos,
                    g.nombre_grado as grado,
                    s.nombre_seccion as seccion,
                    i.año_escolar
                  FROM inscripcion i
                  JOIN estudiante e ON i.cédula_escolar_estudiante = e.cédula_escolar
                  LEFT JOIN grados g ON i.id_grado = g.id
                  LEFT JOIN secciones s ON i.id_seccion = s.id
                  WHERE i.tipo_estudiante = 'Retirado'
                  ORDER BY e.apellidos ASC, e.nombres ASC";

$res_ret = ($conexion instanceof PDO) ? $conexion->query($sql_retirados) : @mysqli_query($conexion, $sql_retirados);
if ($res_ret) {
    $rows_r = ($conexion instanceof PDO) ? $res_ret->fetchAll(PDO::FETCH_ASSOC) : $res_ret;
    foreach ($rows_r as $rt) {
        $gra_val = trim((string)($rt['grado'] ?? '1'));
        $sec_val = trim((string)($rt['seccion'] ?? 'A'));
        $per_val = !empty($rt['año_escolar']) ? trim((string)$rt['año_escolar']) : $periodo_activo_conf;
        $rt['grado_mostrar'] = $gra_val;
        $rt['seccion_mostrar'] = $sec_val;
        $rt['periodo_mostrar'] = $per_val;
        $registros_retirados[] = $rt;
        if ($gra_val !== '') $grados_unicos[$gra_val] = true;
        if ($sec_val !== '') $secciones_unicas[$sec_val] = true;
        if ($per_val !== '') $periodos_unicos[$per_val] = true;
    }
}
uksort($grados_unicos, 'strnatcmp');
ksort($secciones_unicas);
krsort($periodos_unicos);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial de Retirados - Sistema de Inscripción</title>
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
        body.dark-mode table,
        body.dark-mode .form-control,
        body.dark-mode select,
        body.dark-mode .modal-content {
            background-color: var(--bg-surface) !important;
            border-color: var(--border-color) !important;
            color: var(--text-primary) !important;
        }

        body.dark-mode .modal-footer {
            border-top-color: var(--border-color) !important;
        }

        body.dark-mode th { background-color: #1e293b !important; color: var(--text-primary) !important; border-color: var(--border-color) !important; }
        body.dark-mode td { background-color: var(--bg-surface) !important; color: var(--text-primary) !important; border-color: var(--border-color) !important; }
        body.dark-mode tr:nth-child(even) td { background-color: var(--bg-surface-alt) !important; }
        body.dark-mode tbody tr:hover td { background-color: #22304a !important; }

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
        .header-actions { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; margin-bottom: 6px; }
        .btn-imprimir {
            background-color: #2563eb; color: white; border: none; padding: 6px 12px;
            border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 0.82rem;
            text-decoration: none; display: inline-flex; align-items: center; gap: 6px;
        }
        .btn-imprimir:hover { opacity: 0.9; color: white; }

        .table-responsive { width: 100%; overflow-x: auto; border: 1px solid var(--border-color); border-radius: 8px; margin-top: 15px; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; background-color: var(--bg-surface); color: var(--text-primary); }
        th, td { border: 1px solid var(--border-color); padding: 11px; text-align: left; white-space: nowrap; }
        th { background-color: #007bff; color: white; position: sticky; top: 0; z-index: 10; }
        td.col-motivo { white-space: normal; min-width: 180px; max-width: 300px; word-break: break-word; }
        tbody tr:nth-child(even) { background-color: var(--bg-surface-alt); }
        tbody tr:hover { background-color: rgba(37, 99, 235, 0.04); }

        .btn-reactivar {
            background-color: #059669; color: white; border: none; padding: 4px 10px;
            border-radius: 6px; font-size: 0.78rem; font-weight: 600; cursor: pointer;
            text-decoration: none; display: inline-flex; align-items: center; gap: 5px;
        }
        .btn-reactivar:hover { opacity: 0.85; color: white; }

        @media (max-width: 768px) { html, body { flex-direction: column; } .main-content { padding: 12px; } }
        @media print {
            .sidebar, .top-header-bar, .no-print, .btn-imprimir, .modal, .modal-backdrop { display: none !important; }
            html, body, .main-content { height: auto !important; overflow: visible !important; display: block !important; background: #ffffff !important; padding: 0 !important; }
            .card { box-shadow: none !important; border: none !important; padding: 0 !important; }
            .table-responsive { overflow: visible !important; border: none !important; }
            th { background-color: #f1f5f9 !important; color: #000000 !important; }
        }
    </style>
</head>
<body>

    <?php include("menu.php"); ?>

    <main class="main-content">
        <div class="top-header-bar no-print">
            <div class="top-breadcrumb">
                <i class="fas fa-user-slash" style="color: #2563eb;"></i>
                <span>Historial de Retirados</span>
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
                    <span><?php echo htmlspecialchars($_SESSION['usuario'] ?? 'Usuario', ENT_QUOTES, 'UTF-8'); ?></span>
                    <span class="role-badge <?php echo $es_administrador ? 'badge-admin' : 'badge-coord'; ?>">
                        <?php echo $es_administrador ? 'Admin' : htmlspecialchars($rol_actual, ENT_QUOTES, 'UTF-8'); ?>
                    </span>
                </div>
            </div>
        </div>

        <?php if (!empty($mensaje)) echo $mensaje; ?>

        <div class="card">
            <div class="header-actions">
                <h2><i class="fas fa-user-slash text-primary" style="color: #2563eb;"></i> Historial de Alumnos Retirados</h2>
                <button type="button" onclick="window.print()" class="btn-imprimir no-print">
                    <i class="fas fa-print"></i> Imprimir
                </button>
            </div>
            <p class="text-muted" style="color: var(--text-secondary); margin-bottom: 12px;">Consulta el listado general de estudiantes retirados de la institución y gestiona su reincorporación.</p>
            <hr style="margin-top: 0; margin-bottom: 15px; border: 0; border-top: 1px solid var(--border-color);">

            <div class="row g-2 mb-2 no-print align-items-end">
                <div class="col-12 col-md-4">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text" style="background: var(--bg-surface-alt); color: var(--text-secondary); border-color: var(--border-color);"><i class="fas fa-search"></i></span>
                        <input type="text" id="buscarRetirado" class="form-control" placeholder="Buscar por cédula, nombre o motivo..." onkeyup="filtrarRetirados()" style="font-size: 0.84rem;">
                    </div>
                </div>
                <div class="col-4 col-md-2">
                    <select id="filtroGradoRet" class="form-control form-control-sm" onchange="filtrarRetirados()" style="font-size: 0.84rem;">
                        <option value="">Todos los grados</option>
                        <?php foreach ($grados_unicos as $gra => $v): ?>
                            <option value="<?php echo htmlspecialchars($gra, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($gra, ENT_QUOTES, 'UTF-8'); ?>° Grado</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-4 col-md-2">
                    <select id="filtroSeccionRet" class="form-control form-control-sm" onchange="filtrarRetirados()" style="font-size: 0.84rem;">
                        <option value="">Todas las secciones</option>
                        <?php foreach ($secciones_unicas as $sec => $v): ?>
                            <option value="<?php echo htmlspecialchars($sec, ENT_QUOTES, 'UTF-8'); ?>">Sección <?php echo htmlspecialchars($sec, ENT_QUOTES, 'UTF-8'); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-4 col-md-2">
                    <select id="filtroPeriodoRet" class="form-control form-control-sm" onchange="filtrarRetirados()" style="font-size: 0.84rem;">
                        <option value="">Todos los períodos</option>
                        <?php foreach ($periodos_unicos as $per => $v): ?>
                            <option value="<?php echo htmlspecialchars($per, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($per, ENT_QUOTES, 'UTF-8'); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-md-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm w-100" onclick="limpiarBusquedaRetirados()" title="Limpiar filtros" style="border-color: var(--border-color); font-size: 0.82rem;">
                        <i class="fas fa-eraser"></i> Limpiar
                    </button>
                </div>
            </div>

            <div class="table-responsive">
                <table id="tablaRetirados">
                    <thead>
                        <tr>
                            <th>Cédula / Identidad</th>
                            <th>Apellidos y Nombres</th>
                            <th>Grado</th>
                            <th>Sección</th>
                            <th>Periodo Escolar</th>
                            <th>Motivo del Retiro</th>
                            <th class="no-print" style="text-align: center;">Estatus / Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if (!empty($registros_retirados)) {
                            foreach ($registros_retirados as $rt) {
                                $ced_rt = trim((string)($rt['cédula_escolar'] ?? ''));
                                $nombre_mostrar = trim(($rt['apellidos'] ?? '') . ', ' . ($rt['nombres'] ?? ''), ', ');
                                $grado_rt = $rt['grado_mostrar'] ?? '1';
                                $seccion_rt = $rt['seccion_mostrar'] ?? 'A';
                                $periodo_rt = $rt['periodo_mostrar'] ?? '';
                                $motivo_rt = $motivos_retiro[$ced_rt] ?? 'Retiro institucional';

                                $ced_js = htmlspecialchars(json_encode($ced_rt, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
                                $nom_js = htmlspecialchars(json_encode($nombre_mostrar, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');

                                echo "<tr data-fila-retirado='1' data-grado='" . htmlspecialchars($grado_rt, ENT_QUOTES, 'UTF-8') . "' data-seccion='" . htmlspecialchars($seccion_rt, ENT_QUOTES, 'UTF-8') . "' data-periodo='" . htmlspecialchars($periodo_rt, ENT_QUOTES, 'UTF-8') . "'>
                                        <td>" . htmlspecialchars($ced_rt, ENT_QUOTES, 'UTF-8') . "</td>
                                        <td><strong>" . htmlspecialchars($nombre_mostrar, ENT_QUOTES, 'UTF-8') . "</strong></td>
                                        <td>" . htmlspecialchars($grado_rt, ENT_QUOTES, 'UTF-8') . "°</td>
                                        <td>" . htmlspecialchars($seccion_rt, ENT_QUOTES, 'UTF-8') . "</td>
                                        <td>" . htmlspecialchars($periodo_rt, ENT_QUOTES, 'UTF-8') . "</td>
                                        <td class='col-motivo'>" . htmlspecialchars($motivo_rt, ENT_QUOTES, 'UTF-8') . "</td>
                                        <td class='no-print' style='text-align: center;'>
                                            <button type='button' class='btn-reactivar' onclick='abrirModalReactivar(" . $ced_js . ", " . $nom_js . ")' title='Volver a Activar'>
                                                <i class='fas fa-user-check'></i> Reactivar
                                            </button>
                                        </td>
                                      </tr>";
                            }
                            echo "<tr id='filaSinCoincidencias' style='display: none;'><td colspan='7' style='text-align: center; color: var(--text-secondary); padding: 20px;'>No se encontraron estudiantes retirados que coincidan con los filtros aplicados.</td></tr>";
                        } else {
                            echo "<tr><td colspan='7' style='text-align: center; color: var(--text-secondary); padding: 20px;'>No hay registros de alumnos retirados cargados en el historial.</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <div class="modal fade no-print" id="modalConfirmarReactivar" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius: 12px; border: 1px solid var(--border-color); box-shadow: 0 10px 30px rgba(0,0,0,0.25);">
                <div class="modal-header text-white" style="background-color: #059669; border-top-left-radius: 11px; border-top-right-radius: 11px;">
                    <h5 class="modal-title" style="font-size: 1rem; font-weight: 700;">
                        <i class="fas fa-user-check me-2"></i> Confirmar Reactivación de Estudiante
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4" style="font-size: 0.9rem;">
                    <p class="mb-2">¿Está seguro de volver a activar al estudiante <strong id="modalNombreEstudiante"></strong> (Cédula: <strong id="modalCedulaEstudiante"></strong>)?</p>
                    <p class="mb-0" style="color: var(--text-secondary); font-size: 0.83rem;">El estudiante regresará al listado activo como Estudiante regular.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <form method="POST" action="retirados.php" style="margin: 0;">
                        <input type="hidden" name="accion" value="reactivar_estudiante">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="reactivar_cedula" id="inputCedulaReactivar" value="">
                        <button type="submit" class="btn btn-success btn-sm">
                            <i class="fas fa-user-check me-1"></i> Sí, reactivar
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

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

        function abrirModalReactivar(cedula, nombre) {
            document.getElementById('modalNombreEstudiante').textContent = nombre;
            document.getElementById('modalCedulaEstudiante').textContent = cedula;
            document.getElementById('inputCedulaReactivar').value = cedula;
            const modalEl = document.getElementById('modalConfirmarReactivar');
            const modal = new bootstrap.Modal(modalEl);
            modal.show();
        }

        function filtrarRetirados() {
            const texto = document.getElementById('buscarRetirado').value.toLowerCase().trim();
            const grado = document.getElementById('filtroGradoRet').value;
            const seccion = document.getElementById('filtroSeccionRet').value;
            const periodo = document.getElementById('filtroPeriodoRet').value;
            const filas = document.querySelectorAll('#tablaRetirados tbody tr[data-fila-retirado="1"]');
            let visibles = 0;

            filas.forEach(function (tr) {
                const matchTexto = !texto || tr.textContent.toLowerCase().indexOf(texto) > -1;
                const matchGrado = !grado || (tr.getAttribute('data-grado') || '') === grado;
                const matchSeccion = !seccion || (tr.getAttribute('data-seccion') || '') === seccion;
                const matchPeriodo = !periodo || (tr.getAttribute('data-periodo') || '') === periodo;
                const coincide = matchTexto && matchGrado && matchSeccion && matchPeriodo;
                tr.style.display = coincide ? '' : 'none';
                if (coincide) visibles++;
            });

            const filaVacia = document.getElementById('filaSinCoincidencias');
            if (filaVacia) {
                filaVacia.style.display = (visibles === 0) ? '' : 'none';
            }
        }

        function limpiarBusquedaRetirados() {
            document.getElementById('buscarRetirado').value = '';
            document.getElementById('filtroGradoRet').value = '';
            document.getElementById('filtroSeccionRet').value = '';
            document.getElementById('filtroPeriodoRet').value = '';
            filtrarRetirados();
        }
    </script>
</body>
</html>