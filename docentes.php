<?php
session_start();
date_default_timezone_set('America/Caracas');
if (isset($_SESSION['usuario'])) {
    if (!isset($_SESSION['init'])) {
        session_regenerate_id(true);
        $_SESSION['init'] = true;
    }
}
if (!isset($_SESSION['rol'])) {
    header("Location: login.php");
    exit();
}

$host = "localhost";
$usuario_bd = "root";
$clave_bd = "";
$nombre_bd = "escuela_carnevali";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$nombre_bd;charset=utf8mb4", $usuario_bd, $clave_bd);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
    $pdo->exec("SET NAMES 'utf8mb4'");
    $pdo->exec("SET sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''))");

    $pdo->exec("ALTER TABLE `docentes` ADD COLUMN IF NOT EXISTS `genero` VARCHAR(15) NULL AFTER `nombres_apellidos`");
    $pdo->exec("ALTER TABLE `docentes` ADD COLUMN IF NOT EXISTS `estado` VARCHAR(20) NOT NULL DEFAULT 'Activo' AFTER `genero`");
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

$es_administrador = isset($_SESSION['rol']) && $_SESSION['rol'] === 'Administrador';
$rol_actual = $_SESSION['rol'] ?? 'Administrador';
$usuario_sesion = $_SESSION['usuario'] ?? 'Administrador';

$mensaje = "";
$tipo_alerta = "";

function registrarHistorial($pdo, $descripcion, $usuario) {
    try {
        $fecha = date('Y-m-d H:i:s');
        $st_h = $pdo->prepare("INSERT INTO `historial` (`descripcion`, `usuario`, `fecha_hora`) VALUES (?, ?, ?)");
        $st_h->execute([$descripcion, $usuario, $fecha]);
    } catch (Exception $e) {}
}

$periodo_predeterminado = "";
try {
    $stmt_periodo = $pdo->query("SELECT `clave`, `valor` FROM `configuracion` WHERE `clave` IN ('periodo_escolar', 'estado')");
    if ($stmt_periodo) {
        $conf_tmp = [];
        while ($row_c = $stmt_periodo->fetch(PDO::FETCH_ASSOC)) {
            $conf_tmp[$row_c['clave']] = trim((string)$row_c['valor']);
        }
        $estado_cfg = $conf_tmp['estado'] ?? 'Activo';
        if ($estado_cfg === 'Activo' && !empty($conf_tmp['periodo_escolar'])) {
            $periodo_predeterminado = $conf_tmp['periodo_escolar'];
        }
    }
} catch (Exception $e) {}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion_guardar'])) {
    $id_doc = intval($_POST['id_docente'] ?? 0);
    $nombre_docente = trim($_POST['nombres_apellidos']);
    $genero_docente = trim($_POST['genero'] ?? 'MASCULINO');
    $id_grado = intval($_POST['id_grado']);
    $id_seccion = intval($_POST['id_seccion']);
    $periodo = $periodo_predeterminado;

    if (!in_array($id_grado, [1, 2, 3, 4, 5, 6])) {
        $mensaje = "El grado seleccionado no es válido para la escuela básica (debe ser de 1° a 6°).";
        $tipo_alerta = "danger";
    } elseif (!empty($nombre_docente) && !empty($genero_docente) && $id_grado > 0 && $id_seccion > 0 && !empty($periodo)) {
        try {
            $pdo->beginTransaction();

            if ($id_doc > 0) {
                $up_doc = $pdo->prepare("UPDATE `docentes` SET `nombres_apellidos` = ?, `genero` = ? WHERE `id` = ?");
                $up_doc->execute([$nombre_docente, $genero_docente, $id_doc]);
                $id_docente = $id_doc;
                $accion_hist = "Modificación de datos del docente: " . mb_convert_case($nombre_docente, MB_CASE_TITLE, 'UTF-8');
            } else {
                $check_doc = $pdo->prepare("SELECT `id` FROM `docentes` WHERE LOWER(`nombres_apellidos`) = LOWER(?) LIMIT 1");
                $check_doc->execute([$nombre_docente]);
                $docente_existente = $check_doc->fetch(PDO::FETCH_ASSOC);

                if ($docente_existente) {
                    $id_docente = $docente_existente['id'];
                    $up_doc = $pdo->prepare("UPDATE `docentes` SET `genero` = ?, `estado` = 'Activo' WHERE `id` = ?");
                    $up_doc->execute([$genero_docente, $id_docente]);
                    $accion_hist = "Actualización de datos y asignación del docente: " . mb_convert_case($nombre_docente, MB_CASE_TITLE, 'UTF-8');
                } else {
                    $ins_doc = $pdo->prepare("INSERT INTO `docentes` (`nombres_apellidos`, `genero`, `estado`) VALUES (?, ?, 'Activo')");
                    $ins_doc->execute([$nombre_docente, $genero_docente]);
                    $id_docente = $pdo->lastInsertId();
                    $accion_hist = "Registro y asignación de nuevo docente: " . mb_convert_case($nombre_docente, MB_CASE_TITLE, 'UTF-8');
                }
            }

            $cols = $pdo->query("SHOW COLUMNS FROM `docente_seccion`")->fetchAll(PDO::FETCH_COLUMN);
            $col_anio = in_array('año_escolar', $cols) ? '`año_escolar`' : '`' . $cols[4] . '`';

            $check_asig = $pdo->prepare("SELECT ds.* FROM `docente_seccion` ds WHERE ds.`id_grado` = ? AND ds.`id_seccion` = ? AND ds.$col_anio = ? LIMIT 1");
            $check_asig->execute([$id_grado, $id_seccion, $periodo]);
            $asig_actual = $check_asig->fetch(PDO::FETCH_ASSOC);

            if ($asig_actual && $asig_actual['id_docente'] != $id_docente) {
                $pdo->rollBack();
                $mensaje = "Ya existe otro docente asignado a ese Grado y Sección en el período escolar actual.";
                $tipo_alerta = "warning";
            } else {
                if ($id_doc > 0 && !$asig_actual) {
                    $del_prev = $pdo->prepare("DELETE FROM `docente_seccion` WHERE `id_docente` = ?");
                    $del_prev->execute([$id_docente]);
                }

                if (!$asig_actual) {
                    $ins_asig = $pdo->prepare("INSERT INTO `docente_seccion` (`id_docente`, `id_grado`, `id_seccion`, $col_anio) VALUES (?, ?, ?, ?)");
                    $ins_asig->execute([$id_docente, $id_grado, $id_seccion, $periodo]);
                }

                $st_g = $pdo->prepare("SELECT `nombre_grado` FROM `grados` WHERE `id` = ?");
                $st_g->execute([$id_grado]);
                $g_nom = $st_g->fetchColumn();

                $st_s = $pdo->prepare("SELECT `nombre_seccion` FROM `secciones` WHERE `id` = ?");
                $st_s->execute([$id_seccion]);
                $s_nom = $st_s->fetchColumn();

                $desc_completa = $accion_hist . " al {$g_nom}° Grado Sección {$s_nom} ({$periodo})";

                $pdo->commit();
                registrarHistorial($pdo, $desc_completa, $usuario_sesion);
                $mensaje = ($id_doc > 0) ? "Docente modificado correctamente." : "Docente registrado y asignado correctamente.";
                $tipo_alerta = "success";
            }
        } catch (Exception $ex) {
            $pdo->rollBack();
            $mensaje = "Ocurrió un error al procesar la solicitud: " . $ex->getMessage();
            $tipo_alerta = "danger";
        }
    } else {
        $mensaje = "Por favor complete todos los campos obligatorios y verifique que exista un período escolar activo en Configuración.";
        $tipo_alerta = "danger";
    }
}

if (isset($_GET['cambiar_estado'])) {
    $id_docente_est = intval($_GET['cambiar_estado']);
    $estado_nuevo = ($_GET['estado'] === 'Activo') ? 'Inactivo' : 'Activo';

    $st_nom = $pdo->prepare("SELECT `nombres_apellidos` FROM `docentes` WHERE `id` = ?");
    $st_nom->execute([$id_docente_est]);
    $nom_doc = $st_nom->fetchColumn();

    $stmt_est = $pdo->prepare("UPDATE `docentes` SET `estado` = ? WHERE `id` = ?");
    $stmt_est->execute([$estado_nuevo, $id_docente_est]);

    registrarHistorial($pdo, "Cambio de estado del docente " . mb_convert_case((string)$nom_doc, MB_CASE_TITLE, 'UTF-8') . " a: " . $estado_nuevo, $usuario_sesion);

    header("Location: docentes.php?msg=estado_actualizado");
    exit();
}

if (isset($_GET['eliminar_asig'])) {
    $id_eliminar = intval($_GET['eliminar_asig']);

    $st_info = $pdo->prepare("
        SELECT d.`nombres_apellidos`, g.`nombre_grado`, s.`nombre_seccion`, ds.* 
        FROM `docente_seccion` ds
        JOIN `docentes` d ON ds.`id_docente` = d.`id`
        JOIN `grados` g ON ds.`id_grado` = g.`id`
        JOIN `secciones` s ON ds.`id_seccion` = s.`id`
        WHERE ds.`id` = ? LIMIT 1
    ");
    $st_info->execute([$id_eliminar]);
    $datos_asig = $st_info->fetch(PDO::FETCH_ASSOC);

    $stmt_del = $pdo->prepare("DELETE FROM `docente_seccion` WHERE `id` = ?");
    $stmt_del->execute([$id_eliminar]);

    if ($datos_asig) {
        $desc_quitar = "Desvinculación de sección del docente " . mb_convert_case((string)$datos_asig['nombres_apellidos'], MB_CASE_TITLE, 'UTF-8') . " (" . $datos_asig['nombre_grado'] . "° Grado Sec. " . $datos_asig['nombre_seccion'] . ")";
        registrarHistorial($pdo, $desc_quitar, $usuario_sesion);
    }

    header("Location: docentes.php?msg=asignacion_retirada");
    exit();
}

if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'asignacion_retirada') {
        $mensaje = "Asignación de aula retirada. El docente continúa registrado en el sistema.";
        $tipo_alerta = "info";
    } elseif ($_GET['msg'] === 'estado_actualizado') {
        $mensaje = "Estado del docente actualizado con éxito.";
        $tipo_alerta = "success";
    }
}

$docente_editar = null;
if (isset($_GET['modificar_doc'])) {
    $id_edit = intval($_GET['modificar_doc']);
    $st_ed = $pdo->prepare("
        SELECT d.`id` AS doc_id, d.`nombres_apellidos`, d.`genero`, ds.`id_grado`, ds.`id_seccion`, ds.*
        FROM `docentes` d
        LEFT JOIN `docente_seccion` ds ON d.`id` = ds.`id_docente`
        WHERE d.`id` = ?
        ORDER BY ds.`id` DESC LIMIT 1
    ");
    $st_ed->execute([$id_edit]);
    $docente_editar = $st_ed->fetch(PDO::FETCH_ASSOC);
}

$grados = $pdo->query("SELECT `id`, `nombre_grado` FROM `grados` ORDER BY `id` ASC")->fetchAll(PDO::FETCH_ASSOC);
$secciones = $pdo->query("SELECT `id`, `nombre_seccion` FROM `secciones` ORDER BY `id` ASC")->fetchAll(PDO::FETCH_ASSOC);

$consulta_asignaciones = "
    SELECT 
        d.`id` AS doc_id,
        d.`nombres_apellidos`, 
        COALESCE(d.`genero`, 'MASCULINO') AS genero,
        COALESCE(d.`estado`, 'Activo') AS estado,
        ds.`id` AS asig_id,
        g.`nombre_grado`, 
        s.`nombre_seccion`,
        ds.*
    FROM `docentes` d
    LEFT JOIN `docente_seccion` ds ON d.`id` = ds.`id_docente`
    LEFT JOIN `grados` g ON ds.`id_grado` = g.`id`
    LEFT JOIN `secciones` s ON ds.`id_seccion` = s.`id`
    ORDER BY d.`estado` ASC, d.`nombres_apellidos` ASC
";
$asignaciones = $pdo->query($consulta_asignaciones)->fetchAll(PDO::FETCH_ASSOC);

function renderIconoGenero($genero) {
    $g = strtoupper(trim((string)$genero));
    if ($g === 'FEMENINO' || $g === 'F') {
        return '<i class="fas fa-female" style="color: #d63384; font-size: 1.15rem;" title="Femenino"></i> F';
    }
    return '<i class="fas fa-male" style="color: #0d6efd; font-size: 1.15rem;" title="Masculino"></i> M';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Docentes - Sistema de Inscripción</title>
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
        body.dark-mode input, body.dark-mode select { background-color: var(--bg-surface-alt) !important; color: var(--text-primary) !important; border-color: var(--border-color) !important; }

        html, body {
            min-height: 100vh; width: 100%; margin: 0; padding: 0;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            background-color: var(--bg-main); color: var(--text-primary);
            display: flex; overflow-x: hidden; transition: background-color 0.25s ease, color 0.25s ease;
        }

        .main-content {
            flex: 1 1 0%; width: 100%; min-width: 0;
            padding: 16px 24px 24px 24px; box-sizing: border-box;
            position: relative; background-color: var(--bg-main);
        }

        .top-header-bar {
            background-color: var(--bg-surface); padding: 10px 18px;
            border-radius: 12px; border: 1px solid var(--border-color);
            box-shadow: var(--card-shadow); display: flex;
            justify-content: space-between; align-items: center;
            margin-bottom: 16px; width: 100%; box-sizing: border-box; flex-wrap: wrap; gap: 10px;
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
        .role-badge.badge-admin { background-color: rgba(239, 68, 68, 0.15); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.3); }
        .role-badge.badge-coord { background-color: rgba(59, 130, 246, 0.15); color: #3b82f6; border: 1px solid rgba(37, 99, 235, 0.3); }

        .card { background: #FFFFFF; padding: 20px; border-radius: 12px; box-shadow: var(--card-shadow); margin-bottom: 20px; width: 100%; box-sizing: border-box; border: 1px solid var(--border-color); }
        .card h2 { font-size: 1.15rem; font-weight: 700; margin-bottom: 5px; color: var(--text-primary); }
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 15px; width: 100%; box-sizing: border-box; }
        .form-group { display: flex; flex-direction: column; width: 100%; box-sizing: border-box; }
        .form-group label { font-size: 0.85rem; font-weight: 600; margin-bottom: 5px; color: var(--text-secondary); }
        .form-group input, .form-group select { padding: 9px 12px; border: 1px solid var(--border-color); border-radius: 8px; font-size: 0.9rem; width: 100%; box-sizing: border-box; background-color: var(--bg-surface); color: var(--text-primary); }
        .btn-guardar { background-color: <?php echo $docente_editar ? '#f59e0b' : '#2563eb'; ?>; color: white; border: none; padding: 9px 18px; border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 0.9rem; }
        .btn-guardar:hover { background-color: #1d4ed8; }
        .btn-modificar { background-color: #f59e0b; color: white; padding: 5px 10px; border-radius: 6px; font-size: 0.78rem; font-weight: 600; text-decoration: none; display: inline-block; }
        .btn-inactivar { background-color: #d97706; color: white; border: none; padding: 5px 10px; border-radius: 6px; cursor: pointer; font-size: 0.78rem; font-weight: 600; }
        .btn-reactivar { background-color: #059669; color: white; border: none; padding: 5px 10px; border-radius: 6px; cursor: pointer; font-size: 0.78rem; font-weight: 600; text-decoration: none; display: inline-block; }
        .btn-desvincular { background-color: #dc2626; color: white; border: none; padding: 5px 10px; border-radius: 6px; cursor: pointer; font-size: 0.78rem; font-weight: 600; text-decoration: none; display: inline-block; }
        .table-responsive { width: 100%; overflow-x: auto; border: 1px solid var(--border-color); border-radius: 8px; margin-top: 15px; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; background-color: var(--bg-surface); color: var(--text-primary); }
        th, td { border: 1px solid var(--border-color); padding: 10px; text-align: left; white-space: nowrap; }
        th { background-color: #f1f5f9; color: var(--text-primary); font-weight: 700; position: sticky; top: 0; z-index: 10; }
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
                <i class="fas fa-chalkboard-teacher" style="color: #2563eb;"></i>
                <span>Gestión de Docentes</span>
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

        <?php if (!empty($mensaje)): ?>
            <div class="alert alert-<?php echo $tipo_alerta; ?> alert-dismissible fade show mb-3" role="alert">
                <i class="fas fa-info-circle me-2"></i><?php echo htmlspecialchars($mensaje); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="card">
            <h2><i class="fas fa-user-plus text-primary" style="color: #2563eb;"></i> <?php echo $docente_editar ? 'Modificar Docente' : 'Asignar Docente a Grado y Sección'; ?></h2>
            <p class="text-muted" style="color: var(--text-secondary);"><?php echo $docente_editar ? 'Actualice los datos del profesor seleccionado.' : 'Registro de personal docente y asignación de aulas escolares.'; ?></p>
            <hr style="margin-bottom: 20px; border: 0; border-top: 1px solid var(--border-color);">

            <form method="POST" action="docentes.php">
                <input type="hidden" name="accion_guardar" value="1">
                <input type="hidden" name="id_docente" value="<?php echo htmlspecialchars($docente_editar['doc_id'] ?? 0); ?>">

                <div class="form-grid">
                    <div class="form-group">
                        <label>Nombre del Docente (*):</label>
                        <input type="text" name="nombres_apellidos" placeholder="Ej: Rosa Pérez" value="<?php echo htmlspecialchars($docente_editar['nombres_apellidos'] ?? ''); ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Género (*):</label>
                        <select name="genero" required>
                            <option value="MASCULINO" <?php echo (($docente_editar['genero'] ?? '') === 'MASCULINO') ? 'selected' : ''; ?>>Masculino</option>
                            <option value="FEMENINO" <?php echo (($docente_editar['genero'] ?? '') === 'FEMENINO') ? 'selected' : ''; ?>>Femenino</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Grado (1° a 6°) (*):</label>
                        <select name="id_grado" required>
                            <option value="">Seleccione grado...</option>
                            <?php foreach ($grados as $g): 
                                $sel_g = (($docente_editar['id_grado'] ?? 0) == $g['id']) ? 'selected' : '';
                            ?>
                                <option value="<?php echo $g['id']; ?>" <?php echo $sel_g; ?>><?php echo htmlspecialchars($g['nombre_grado']); ?>° Grado</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Sección (*):</label>
                        <select name="id_seccion" required>
                            <option value="">Seleccione sección...</option>
                            <?php foreach ($secciones as $s): 
                                $sel_s = (($docente_editar['id_seccion'] ?? 0) == $s['id']) ? 'selected' : '';
                            ?>
                                <option value="<?php echo $s['id']; ?>" <?php echo $sel_s; ?>>Sección "<?php echo htmlspecialchars($s['nombre_seccion']); ?>"</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Período actual (*):</label>
                        <input type="text" name="periodo_escolar_input" value="<?php echo htmlspecialchars($periodo_predeterminado); ?>" readonly style="background-color: var(--bg-surface-alt); cursor: not-allowed;" title="El período escolar se gestiona desde el módulo de configuración institucional">
                    </div>
                </div>

                <div style="display: flex; gap: 10px; margin-top: 15px;">
                    <button type="submit" class="btn-guardar">
                        <i class="fas fa-save me-1"></i> <?php echo $docente_editar ? 'Actualizar Datos' : 'Guardar Asignación'; ?>
                    </button>
                    <?php if ($docente_editar): ?>
                        <a href="docentes.php" style="background: #64748b; color: white; padding: 9px 18px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 0.9rem; display: inline-flex; align-items: center; gap: 6px;">
                            <i class="fas fa-times"></i> Cancelar
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <div class="card">
            <h2><i class="fas fa-list-check text-primary" style="color: #2563eb;"></i> Plantel Docente Registrado</h2>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th style="text-align: center; width: 180px;">Acciones</th>
                            <th>Docente</th>
                            <th style="text-align: center;">Sexo</th>
                            <th>Grado</th>
                            <th>Sección</th>
                            <th style="text-align: center;">Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($asignaciones) > 0): ?>
                            <?php foreach ($asignaciones as $asig): 
                                $es_activo = ($asig['estado'] === 'Activo');
                                $badge_est = $es_activo ? 'background:rgba(16, 185, 129, 0.15); color:#10b981;' : 'background:rgba(217, 119, 6, 0.15); color:#d97706;';
                            ?>
                                <tr>
                                    <td style="text-align: center; display: flex; gap: 5px; justify-content: center;">
                                        <a href="docentes.php?modificar_doc=<?php echo $asig['doc_id']; ?>" class="btn-modificar" title="Modificar datos">
                                            <i class="fas fa-pen"></i> Modificar
                                        </a>

                                        <?php if ($es_activo): ?>
                                            <a href="docentes.php?cambiar_estado=<?php echo $asig['doc_id']; ?>&estado=Activo" class="btn-inactivar" title="Inactivar docente">
                                                <i class="fas fa-user-slash"></i> Inactivar
                                            </a>
                                        <?php else: ?>
                                            <a href="docentes.php?cambiar_estado=<?php echo $asig['doc_id']; ?>&estado=Inactivo" class="btn-reactivar" title="Reactivar docente">
                                                <i class="fas fa-user-check"></i> Activar
                                            </a>
                                        <?php endif; ?>

                                        <?php if (!empty($asig['asig_id'])): ?>
                                            <a href="docentes.php?eliminar_asig=<?php echo $asig['asig_id']; ?>" class="btn-desvincular" onclick="return confirm('¿Desea desvincular a este docente de esta aula?');" title="Desvincular aula">
                                                <i class="fas fa-unlink"></i>
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                    <td><strong><?php echo htmlspecialchars(mb_convert_case((string)$asig['nombres_apellidos'], MB_CASE_TITLE, 'UTF-8')); ?></strong></td>
                                    <td style="text-align: center;"><?php echo renderIconoGenero($asig['genero']); ?></td>
                                    <td>
                                        <?php if (!empty($asig['nombre_grado'])): ?>
                                            <span style="background:rgba(37, 99, 235, 0.15); color:#2563eb; padding:3px 8px; border-radius:4px; font-weight:600;"><?php echo htmlspecialchars($asig['nombre_grado']); ?>° Grado</span>
                                        <?php else: ?>
                                            <span style="color: var(--text-secondary); font-size: 0.8rem;">Sin asignar</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($asig['nombre_seccion'])): ?>
                                            <span style="background:rgba(124, 58, 237, 0.15); color:#8b5cf6; padding:3px 8px; border-radius:4px; font-weight:600;">Sección <?php echo htmlspecialchars($asig['nombre_seccion']); ?></span>
                                        <?php else: ?>
                                            <span style="color: var(--text-secondary); font-size: 0.8rem;">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align: center;">
                                        <span style="<?php echo $badge_est; ?> padding:3px 8px; border-radius:4px; font-weight:600; font-size: 0.75rem;">
                                            <?php echo htmlspecialchars($asig['estado']); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" style="text-align: center; color: var(--text-secondary); padding: 20px;">
                                    No hay docentes registrados en el sistema.
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