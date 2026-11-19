<?php
session_start();
// Configuración de seguridad para sesiones y manejo de errores limpio
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
            alert('Acceso denegado. La gestión de Autoridades y Firmas es exclusiva para la Dirección.');
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

$mensaje_aut = "";
$usuario_actual = $_SESSION['usuario'] ?? 'Administrador';

$editando = false;
$edit_id = "";
$edit_nombres = "";
$edit_cedula = "";
$edit_cargo = "";
$edit_estatus = "";

if (!function_exists('registrarHistorialAutoridades')) {
    function registrarHistorialAutoridades($conexion, $descripcion, $usuario) {
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
}

if (!function_exists('guardarFirmaProcesada')) {
    function guardarFirmaProcesada($base64_str, $file_upload) {
        if (!is_dir('firmas')) {
            mkdir('firmas', 0777, true);
        }
        // Prioridad 1: Firma procesada y recortada sin fondo en PNG de alta resolución
        if (!empty($base64_str) && strpos($base64_str, 'data:image/png;base64,') === 0) {
            $data = base64_decode(substr($base64_str, strpos($base64_str, ',') + 1));
            if ($data !== false) {
                $nuevo_nombre_firma = "firma_" . time() . "_" . rand(1000, 9999) . ".png";
                $ruta_destino = "firmas/" . $nuevo_nombre_firma;
                if (file_put_contents($ruta_destino, $data) !== false) {
                    return $ruta_destino;
                }
            }
        }
        // Prioridad 2: Subida tradicional de imagen por respaldo
        if (isset($file_upload) && $file_upload['error'] === UPLOAD_ERR_OK) {
            $nombre_archivo = $file_upload['name'];
            $tmp_name = $file_upload['tmp_name'];
            $extension = strtolower(pathinfo($nombre_archivo, PATHINFO_EXTENSION));
            if ($extension !== 'pdf') {
                $nuevo_nombre_firma = "firma_" . time() . "_" . rand(1000, 9999) . "." . $extension;
                $ruta_destino = "firmas/" . $nuevo_nombre_firma;
                if (move_uploaded_file($tmp_name, $ruta_destino)) {
                    return $ruta_destino;
                }
            }
        }
        return "";
    }
}

if (isset($_GET['editar'])) {
    $edit_id = intval($_GET['editar']);
    if ($conexion instanceof PDO) {
        $stmt_edit = $conexion->prepare("SELECT * FROM autoridades WHERE id = :id LIMIT 1");
        $stmt_edit->execute([':id' => $edit_id]);
        $row_edit = $stmt_edit->fetch(PDO::FETCH_ASSOC);
        if ($row_edit) {
            $editando = true;
            $edit_nombres = $row_edit['nombres_apellidos'];
            $edit_cedula = $row_edit['cedula'];
            $edit_cargo = $row_edit['cargo'];
            $edit_estatus = $row_edit['estatus'] ?? 'Activo';
        }
    } else {
        $res_edit = mysqli_query($conexion, "SELECT * FROM autoridades WHERE id = $edit_id LIMIT 1");
        if ($res_edit && mysqli_num_rows($res_edit) > 0) {
            $row_edit = mysqli_fetch_assoc($res_edit);
            $editando = true;
            $edit_nombres = $row_edit['nombres_apellidos'];
            $edit_cedula = $row_edit['cedula'];
            $edit_cargo = $row_edit['cargo'];
            $edit_estatus = $row_edit['estatus'] ?? 'Activo';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    if ($_POST['accion'] === 'guardar_autoridad') {
        $nombres_apellidos = trim($_POST['nombres_apellidos'] ?? '');
        $cedula = trim($_POST['cedula'] ?? '');
        $cargo = trim($_POST['cargo'] ?? '');
        $estatus = trim($_POST['estatus'] ?? 'Activo');
        $firma_procesada = $_POST['firma_procesada'] ?? '';
        $ruta_firma = "";

        if (!empty($nombres_apellidos) && !empty($cedula) && !empty($cargo) && !empty($estatus) && strlen($nombres_apellidos) >= 10 && strlen($nombres_apellidos) <= 80 && preg_match('/^[A-Za-zÁÉÍÓÚáéíóúñÑ\s]+$/u', $nombres_apellidos) && strlen($cedula) >= 7 && strlen($cedula) <= 12 && preg_match('/^[VE]-\d{6,8}$/', $cedula)) {
            
            $ruta_firma = guardarFirmaProcesada($firma_procesada, $_FILES['firma'] ?? null);

            if ($conexion instanceof PDO) {
                $stmt = $conexion->prepare("INSERT INTO autoridades (nombres_apellidos, cedula, cargo, firma, estatus) VALUES (:nom, :ced, :cargo, :firma, :est)");
                if ($stmt->execute([':nom' => $nombres_apellidos, ':ced' => $cedula, ':cargo' => $cargo, ':firma' => $ruta_firma, ':est' => $estatus])) {
                    registrarHistorialAutoridades($conexion, "Registro de autoridad ($cargo): $nombres_apellidos", $usuario_actual);
                    $mensaje_aut = "<div style='color: #155724; background-color: #d4edda; padding: 10px; border-radius: 5px; margin-bottom: 15px;'>¡Autoridad y firma registradas exitosamente!</div>";
                }
            } else {
                $n_esc = mysqli_real_escape_string($conexion, $nombres_apellidos);
                $c_esc = mysqli_real_escape_string($conexion, $cedula);
                $cargo_esc = mysqli_real_escape_string($conexion, $cargo);
                $f_esc = mysqli_real_escape_string($conexion, $ruta_firma);
                $est_esc = mysqli_real_escape_string($conexion, $estatus);
                $sql_insert = "INSERT INTO autoridades (nombres_apellidos, cedula, cargo, firma, estatus) VALUES ('$n_esc', '$c_esc', '$cargo_esc', '$f_esc', '$est_esc')";
                if (mysqli_query($conexion, $sql_insert)) {
                    registrarHistorialAutoridades($conexion, "Registro de autoridad ($cargo): $nombres_apellidos", $usuario_actual);
                    $mensaje_aut = "<div style='color: #155724; background-color: #d4edda; padding: 10px; border-radius: 5px; margin-bottom: 15px;'>¡Autoridad y firma registradas exitosamente!</div>";
                }
            }
        }
    } elseif ($_POST['accion'] === 'actualizar_autoridad') {
        $id_aut = intval($_POST['id_autoridad']);
        $nombres_apellidos = trim($_POST['nombres_apellidos'] ?? '');
        $cedula = trim($_POST['cedula'] ?? '');
        $cargo = trim($_POST['cargo'] ?? '');
        $estatus = trim($_POST['estatus'] ?? 'Activo');
        $firma_procesada = $_POST['firma_procesada'] ?? '';

        if (!empty($nombres_apellidos) && !empty($cedula) && !empty($cargo) && !empty($estatus)) {
            $sql_firma_parte = "";
            $parametros = [':nom' => $nombres_apellidos, ':ced' => $cedula, ':cargo' => $cargo, ':est' => $estatus, ':id' => $id_aut];
            
            $nueva_ruta_firma = guardarFirmaProcesada($firma_procesada, $_FILES['firma'] ?? null);
            if (!empty($nueva_ruta_firma)) {
                $sql_firma_parte = ", firma = :firma";
                $parametros[':firma'] = $nueva_ruta_firma;
            }

            if ($conexion instanceof PDO) {
                $stmt = $conexion->prepare("UPDATE autoridades SET nombres_apellidos = :nom, cedula = :ced, cargo = :cargo, estatus = :est" . $sql_firma_parte . " WHERE id = :id");
                if ($stmt->execute($parametros)) {
                    registrarHistorialAutoridades($conexion, "Modificación de autoridad: $nombres_apellidos", $usuario_actual);
                    $mensaje_aut = "<div style='color: #155724; background-color: #d4edda; padding: 10px; border-radius: 5px; margin-bottom: 15px;'>¡Autoridad modificada exitosamente!</div>";
                    $editando = false;
                    $edit_id = $edit_nombres = $edit_cedula = $edit_cargo = $edit_estatus = "";
                }
            } else {
                $n_esc = mysqli_real_escape_string($conexion, $nombres_apellidos);
                $c_esc = mysqli_real_escape_string($conexion, $cedula);
                $cargo_esc = mysqli_real_escape_string($conexion, $cargo);
                $est_esc = mysqli_real_escape_string($conexion, $estatus);
                $sql_f_msqli = "";
                if (!empty($parametros[':firma'] ?? '')) {
                    $f_esc = mysqli_real_escape_string($conexion, $parametros[':firma']);
                    $sql_f_msqli = ", firma='$f_esc'";
                }
                $sql_up = "UPDATE autoridades SET nombres_apellidos='$n_esc', cedula='$c_esc', cargo='$cargo_esc', estatus='$est_esc'$sql_f_msqli WHERE id=$id_aut";
                if (mysqli_query($conexion, $sql_up)) {
                    registrarHistorialAutoridades($conexion, "Modificación de autoridad: $n_esc", $usuario_actual);
                    $mensaje_aut = "<div style='color: #155724; background-color: #d4edda; padding: 10px; border-radius: 5px; margin-bottom: 15px;'>¡Autoridad modificada exitosamente!</div>";
                    $editando = false;
                    $edit_id = $edit_nombres = $edit_cedula = $edit_cargo = $edit_estatus = "";
                }
            }
        }
    } elseif ($_POST['accion'] === 'inactivar_autoridad') {
        $id_aut = intval($_POST['id_autoridad']);
        if ($conexion instanceof PDO) {
            $stmt_inact = $conexion->prepare("UPDATE autoridades SET estatus = 'Inactivo' WHERE id = :id");
            $stmt_inact->execute([':id' => $id_aut]);
        } else {
            mysqli_query($conexion, "UPDATE autoridades SET estatus = 'Inactivo' WHERE id = $id_aut");
        }
        registrarHistorialAutoridades($conexion, "Inactivación de autoridad ID: $id_aut", $usuario_actual);
        $mensaje_aut = "<div style='background-color: #fff3cd; color: #856404; padding: 10px; border-radius: 5px; margin-bottom: 15px;'>La autoridad ha sido marcada como inactiva.</div>";
    }
}

$es_administrador = isset($_SESSION['rol']) && $_SESSION['rol'] === 'Administrador';
$rol_actual = $_SESSION['rol'] ?? 'Administrador';
$archivo_destino_aut = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Autoridades - Sistema de Inscripción</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Librería PDF.js para leer documentos escaneados en PDF directamente -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
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

        .card { background: #FFFFFF; padding: 20px; border-radius: 12px; box-shadow: var(--card-shadow); margin-bottom: 20px; width: 100%; box-sizing: border-box; border: 1px solid var(--border-color); }
        .card h2 { font-size: 1.15rem; font-weight: 700; margin-bottom: 5px; color: var(--text-primary); }
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 15px; margin-bottom: 15px; width: 100%; box-sizing: border-box; }
        .form-group { display: flex; flex-direction: column; width: 100%; box-sizing: border-box; }
        .form-group label { font-size: 0.85rem; font-weight: 600; margin-bottom: 5px; color: var(--text-secondary); }
        .form-group input, .form-group select { padding: 9px 12px; border: 1px solid var(--border-color); border-radius: 8px; font-size: 0.9rem; width: 100%; box-sizing: border-box; background-color: var(--bg-surface); color: var(--text-primary); }
        .btn-guardar { background-color: <?php echo $editando ? '#f59e0b' : '#2563eb'; ?>; color: white; border: none; padding: 9px 18px; border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 0.9rem; transition: background-color 0.2s; }
        .btn-guardar:hover { background-color: #1d4ed8; }
        .btn-modificar { background-color: #f59e0b; color: white; padding: 5px 10px; border-radius: 6px; font-size: 0.78rem; font-weight: 600; text-decoration: none; display: inline-block; }
        .btn-inactivar { background-color: #d97706; color: white; border: none; padding: 5px 10px; border-radius: 6px; cursor: pointer; font-size: 0.78rem; font-weight: 600; }
        .table-responsive { width: 100%; overflow-x: auto; border: 1px solid var(--border-color); border-radius: 8px; margin-top: 15px; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; background-color: var(--bg-surface); color: var(--text-primary); }
        th, td { border: 1px solid var(--border-color); padding: 10px; text-align: left; white-space: nowrap; }
        th { background-color: #f1f5f9; color: var(--text-primary); font-weight: 700; position: sticky; top: 0; z-index: 10; }
        tr:nth-child(even) { background-color: var(--bg-surface-alt); }
        tr:hover { background-color: rgba(37, 99, 235, 0.04); }

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

        .firma-panel-box {
            border: 1px solid var(--border-color);
            border-radius: 10px;
            padding: 15px;
            background-color: var(--bg-surface-alt);
            margin-top: 5px;
            margin-bottom: 15px;
        }

        .firma-tabs {
            display: flex;
            gap: 8px;
            margin-bottom: 12px;
            flex-wrap: wrap;
        }

        .btn-tab-firma {
            padding: 7px 14px;
            border-radius: 6px;
            border: 1px solid var(--border-color);
            background-color: var(--bg-surface);
            color: var(--text-secondary);
            font-size: 0.82rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-tab-firma.active {
            background-color: #2563eb;
            color: #ffffff;
            border-color: #2563eb;
        }

        .work-grid-firma {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(290px, 1fr));
            gap: 15px;
            margin-top: 10px;
            align-items: stretch;
        }

        .crop-box-container {
            background: #1e293b;
            border: 1px solid #475569;
            border-radius: 8px;
            padding: 10px;
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }

        #canvasRecorte {
            max-width: 100%;
            max-height: 240px;
            border-radius: 4px;
            cursor: crosshair;
            touch-action: none;
            background: #ffffff;
        }

        .firma-preview-wrapper {
            background: #ffffff;
            border: 2px dashed #94a3b8;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
            position: relative;
            min-height: 180px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }

        .linea-guia-constancia {
            width: 260px;
            border-top: 1px solid #000;
            margin-top: -10px;
            padding-top: 4px;
            font-size: 12px;
            color: #000 !important;
            font-style: italic;
            font-family: Arial, sans-serif;
        }

        #canvasPadDibujo {
            background: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            cursor: crosshair;
            touch-action: none;
            max-width: 100%;
        }

        @media (max-width: 768px) {
            html, body { flex-direction: column; }
            .main-content { padding: 12px; }
        }
    </style>
</head>
<body>

    <?php include("menu.php"); ?>

    <main class="main-content">
        <div class="top-header-bar">
            <div class="top-breadcrumb">
                <i class="fas fa-user-tie" style="color: #2563eb;"></i>
                <span>Gestión de Autoridades</span>
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
            <h2><i class="fas fa-user-plus text-primary" style="color: #2563eb;"></i> <?php echo $editando ? 'Modificar Autoridad / Directivo' : 'Registrar Director(a) o Autoridad Firmante'; ?></h2>
            <p class="text-muted" style="color: var(--text-secondary);">Agregue los datos del personal directivo y suba el documento escaneado (PDF) o foto de la firma.</p>
            <hr style="margin-bottom: 20px; border: 0; border-top: 1px solid var(--border-color);">

            <?php if (!empty($mensaje_aut)) echo $mensaje_aut; ?>

            <form method="POST" action="<?php echo $archivo_destino_aut; ?>" enctype="multipart/form-data" id="formAutoridad" onsubmit="return validarEnvioFirma()">
                <input type="hidden" name="accion" value="<?php echo $editando ? 'actualizar_autoridad' : 'guardar_autoridad'; ?>">
                <?php if ($editando): ?>
                    <input type="hidden" name="id_autoridad" value="<?php echo $edit_id; ?>">
                <?php endif; ?>
                
                <input type="hidden" name="firma_procesada" id="firma_procesada" value="">

                <div class="form-grid">
                    <div class="form-group">
                        <label>Nombres y Apellidos (*):</label>
                        <input type="text" name="nombres_apellidos" minlength="10" maxlength="80" pattern="[A-Za-zÁÉÍÓÚáéíóúñÑ\s]{10,80}" title="Solo letras y espacios" oninput="this.value = this.value.replace(/[^A-Za-zÁÉÍÓÚáéíóúñÑ\s]/g, '')" required value="<?php echo htmlspecialchars($edit_nombres); ?>" placeholder="Ej: María Pérez">
                    </div>
                    <div class="form-group">
                        <label>Cédula de Identidad (*):</label>
                        <input type="text" name="cedula" minlength="7" maxlength="12" pattern="[VE]-\d{6,8}" title="Debe incluir la inicial y el guion, ej: V-12345678" required value="<?php echo htmlspecialchars($edit_cedula); ?>" placeholder="V-12345678">
                    </div>
                    <div class="form-group">
                        <label>Cargo Institucional (*):</label>
                        <select name="cargo" required>
                            <option value="">Seleccione un cargo...</option>
                            <option value="Director(a)" <?php if($edit_cargo=='Director(a)') echo 'selected'; ?>>Director(a)</option>
                            <option value="Subdirector(a)" <?php if($edit_cargo=='Subdirector(a)') echo 'selected'; ?>>Subdirector(a)</option>
                            <option value="Secretario(a)" <?php if($edit_cargo=='Secretario(a)') echo 'selected'; ?>>Secretario(a)</option>
                            <option value="Coordinador(a)" <?php if($edit_cargo=='Coordinador(a)') echo 'selected'; ?>>Coordinador(a)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Estado del Registro (*):</label>
                        <select name="estatus" required>
                            <option value="">Seleccione un estado...</option>
                            <option value="Activo" <?php if($edit_estatus=='Activo') echo 'selected'; ?>>Activo</option>
                            <option value="Inactivo" <?php if($edit_estatus=='Inactivo') echo 'selected'; ?>>Inactivo</option>
                        </select>
                    </div>
                </div>

                <div class="firma-panel-box">
                    <label style="font-size: 0.88rem; font-weight: 700; color: var(--text-primary); margin-bottom: 8px; display: block;">
                        <i class="fas fa-signature" style="color: #2563eb;"></i> Firma de la Autoridad <?php echo $editando ? '(Opcional si no desea cambiarla)' : '(*)'; ?>:
                    </label>

                    <div class="firma-tabs">
                        <button type="button" class="btn-tab-firma active" id="tabSubirBtn" onclick="cambiarModoFirma('subir')">
                            <i class="fas fa-file-pdf"></i> Subir Escaneo PDF o Foto de Firma (Extraer Solo la Firma)
                        </button>
                        <button type="button" class="btn-tab-firma" id="tabDibujarBtn" onclick="cambiarModoFirma('dibujar')">
                            <i class="fas fa-pen-nib"></i> Dibujar Firma en Pantalla
                        </button>
                    </div>

                    <!-- MODO 1: Subir PDF Escaneado o Imagen -->
                    <div id="seccionSubirFoto">
                        <div style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap; margin-bottom: 8px;">
                            <input type="file" name="firma" id="inputFotoFirma" accept="image/*,.pdf,application/pdf" style="padding: 6px; max-width: 330px; background: var(--bg-surface); border: 1px solid var(--border-color); border-radius: 6px; font-size: 0.84rem;">
                            
                            <div id="controlesAjusteFoto" style="display: none; align-items: center; gap: 12px; flex-wrap: wrap;">
                                <button type="button" onclick="rotarFotoFirma()" style="padding: 6px 12px; background: #0891b2; color: #fff; border: none; border-radius: 6px; font-size: 0.8rem; font-weight: 600; cursor: pointer;">
                                    <i class="fas fa-redo"></i> Girar 90°
                                </button>
                                <div style="display: flex; align-items: center; gap: 6px; font-size: 0.8rem; color: var(--text-secondary); font-weight: 600;">
                                    <span>Limpieza de papel:</span>
                                    <input type="range" id="umbralLimpieza" min="100" max="240" value="175" oninput="procesarFotoFirma()" style="width: 105px; cursor: pointer;">
                                </div>
                                <div style="display: flex; align-items: center; gap: 6px; font-size: 0.8rem; color: var(--text-secondary); font-weight: 600;">
                                    <span>Grosor de tinta:</span>
                                    <input type="range" id="fuerzaTinta" min="1" max="5" value="3" oninput="procesarFotoFirma()" style="width: 85px; cursor: pointer;">
                                </div>
                            </div>
                        </div>

                        <div id="estadoCargaPdf" style="display: none; color: #2563eb; font-weight: 600; font-size: 0.85rem; margin-bottom: 8px;">
                            <i class="fas fa-spinner fa-spin"></i> Procesando documento escaneado en alta resolución...
                        </div>

                        <div class="work-grid-firma" id="panelRecorteYPrevia" style="display: none;">
                            <!-- Paso 1: Seleccionar solo la firma -->
                            <div class="crop-box-container">
                                <div style="color: #fff; font-size: 0.78rem; font-weight: 600; margin-bottom: 6px;">
                                    <i class="fas fa-crop-alt" style="color: #38bdf8;"></i> 1. Arrastre con el mouse sobre la hoja para encerrar <u>SOLO la firma</u>:
                                </div>
                                <canvas id="canvasRecorte"></canvas>
                                <small style="color: #cbd5e1; font-size: 0.73rem; margin-top: 5px;">
                                    Encierre únicamente la firma en el recuadro azul para ignorar manchas o bordes de la hoja.
                                </small>
                            </div>

                            <!-- Paso 2: Resultado final HD sobre la línea -->
                            <div style="display: flex; flex-direction: column; justify-content: center;">
                                <div style="font-size: 0.8rem; font-weight: 700; color: #059669; margin-bottom: 6px;">
                                    <i class="fas fa-check-circle"></i> 2. Resultado en Alta Definición (Solo la firma sin fondo):
                                </div>
                                <div class="firma-preview-wrapper">
                                    <img id="imgVistaPreviaFirma" src="" alt="Vista previa sin fondo" style="height: 85px; width: auto; max-width: 280px; object-fit: contain; z-index: 2;">
                                    <div class="linea-guia-constancia">
                                        Prof. Directora / Autoridad
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- MODO 2: Dibujar en pantalla -->
                    <div id="seccionDibujarFirma" style="display: none; text-align: center; margin-bottom: 10px;">
                        <p style="font-size: 0.8rem; color: var(--text-secondary); margin-bottom: 6px;">Dibuje la firma dentro del recuadro blanco usando el mouse o pantalla táctil:</p>
                        <canvas id="canvasPadDibujo" width="520" height="160"></canvas>
                        <div style="margin-top: 6px;">
                            <button type="button" onclick="limpiarPadDibujo()" style="padding: 5px 12px; background: #ef4444; color: #fff; border: none; border-radius: 6px; font-size: 0.78rem; font-weight: 600; cursor: pointer;">
                                <i class="fas fa-eraser"></i> Borrar y repetir trazo
                            </button>
                        </div>
                    </div>
                </div>

                <div style="display: flex; gap: 10px; margin-top: 15px;">
                    <button type="submit" class="btn-guardar"><i class="fas <?php echo $editando ? 'fa-save' : 'fa-user-plus'; ?>"></i> <?php echo $editando ? 'Guardar Cambios' : 'Registrar Autoridad y Firma'; ?></button>
                    <?php if ($editando): ?>
                        <a href="<?php echo $archivo_destino_aut; ?>" style="background: #64748b; color: white; padding: 9px 18px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 0.9rem; display: inline-flex; align-items: center; gap: 6px;">
                            <i class="fas fa-times"></i> Cancelar
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <div class="card">
            <h2><i class="fas fa-id-badge text-primary" style="color: #2563eb;"></i> Listado de Autoridades Registradas</h2>
            <div class="table-responsive">
                <table>
                    <tr>
                        <th style="text-align: center; width: 170px;">Acción</th>
                        <th>Nombres y Apellidos</th>
                        <th>Cédula</th>
                        <th>Cargo</th>
                        <th>Firma Registrada</th>
                        <th>Estatus</th>
                    </tr>
                    <?php
                    if ($conexion instanceof PDO) {
                        $stmt_aut = $conexion->query("SELECT * FROM autoridades ORDER BY id DESC");
                        $res_aut = $stmt_aut->fetchAll(PDO::FETCH_ASSOC);
                        if (!empty($res_aut)) {
                            foreach($res_aut as $a) {
                                $estatus_val = $a['estatus'] ?? 'Activo';
                                $badge_color = ($estatus_val === 'Activo') ? 'background:rgba(16, 185, 129, 0.15); color:#10b981;' : 'background:rgba(217, 119, 6, 0.15); color:#d97706;';
                                $img_firma = (!empty($a['firma']) && file_exists($a['firma'])) 
                                    ? "<img src='{$a['firma']}?v=" . time() . "' alt='Firma' style='height: 42px; object-fit: contain; background: #fff; padding: 2px; border: 1px solid #ddd; border-radius: 4px;'>" 
                                    : "<span style='color: #999; font-size: 0.8rem;'>Sin firma</span>";

                                echo "<tr>
                                        <td style='text-align: center; display: flex; gap: 5px; justify-content: center;'>
                                            <a href='{$archivo_destino_aut}?editar=" . $a['id'] . "' class='btn-modificar' title='Modificar'>
                                                <i class='fas fa-edit'></i> Modificar
                                            </a>";
                                if ($estatus_val === 'Activo') {
                                    echo "<form method='POST' action='{$archivo_destino_aut}' style='margin:0;'>
                                                <input type='hidden' name='accion' value='inactivar_autoridad'>
                                                <input type='hidden' name='id_autoridad' value='" . $a['id'] . "'>
                                                <button type='submit' class='btn-inactivar' onclick=\"return confirm('¿Está seguro de inactivar esta autoridad?');\" title='Inactivar'>
                                                    <i class='fas fa-user-slash'></i> Inactivar
                                                </button>
                                            </form>";
                                } else {
                                    echo "<span style='font-size:0.75rem; color:var(--text-secondary); align-self:center;'>Inactivo</span>";
                                }
                                echo "</td>
                                        <td><strong>" . htmlspecialchars($a['nombres_apellidos']) . "</strong></td>
                                        <td>" . htmlspecialchars($a['cedula']) . "</td>
                                        <td><span style='background:rgba(37, 99, 235, 0.15); color:#2563eb; padding:3px 8px; border-radius:4px; font-weight:600;'>" . htmlspecialchars($a['cargo']) . "</span></td>
                                        <td style='text-align: center;'>{$img_firma}</td>
                                        <td><span style='" . $badge_color . " padding:3px 8px; border-radius:4px; font-weight:600;'>" . htmlspecialchars($estatus_val) . "</span></td>
                                      </tr>";
                            }
                        } else {
                            echo "<tr><td colspan='6' style='text-align: center; color: var(--text-secondary); padding: 20px;'>No hay autoridades registradas en el sistema.</td></tr>";
                        }
                    } else {
                        $res_aut = mysqli_query($conexion, "SELECT * FROM autoridades ORDER BY id DESC");
                        if ($res_aut && mysqli_num_rows($res_aut) > 0) {
                            while($a = mysqli_fetch_assoc($res_aut)) {
                                $estatus_val = $a['estatus'] ?? 'Activo';
                                $badge_color = ($estatus_val === 'Activo') ? 'background:rgba(16, 185, 129, 0.15); color:#10b981;' : 'background:rgba(217, 119, 6, 0.15); color:#d97706;';
                                $img_firma = (!empty($a['firma']) && file_exists($a['firma'])) 
                                    ? "<img src='{$a['firma']}?v=" . time() . "' alt='Firma' style='height: 42px; object-fit: contain; background: #fff; padding: 2px; border: 1px solid #ddd; border-radius: 4px;'>" 
                                    : "<span style='color: #999; font-size: 0.8rem;'>Sin firma</span>";

                                echo "<tr>
                                        <td style='text-align: center; display: flex; gap: 5px; justify-content: center;'>
                                            <a href='{$archivo_destino_aut}?editar=" . $a['id'] . "' class='btn-modificar' title='Modificar'>
                                                <i class='fas fa-edit'></i> Modificar
                                            </a>";
                                if ($estatus_val === 'Activo') {
                                    echo "<form method='POST' action='{$archivo_destino_aut}' style='margin:0;'>
                                                <input type='hidden' name='accion' value='inactivar_autoridad'>
                                                <input type='hidden' name='id_autoridad' value='" . $a['id'] . "'>
                                                <button type='submit' class='btn-inactivar' onclick=\"return confirm('¿Está seguro de inactivar esta autoridad?');\" title='Inactivar'>
                                                    <i class='fas fa-user-slash'></i> Inactivar
                                                </button>
                                            </form>";
                                } else {
                                    echo "<span style='font-size:0.75rem; color:var(--text-secondary); align-self:center;'>Inactivo</span>";
                                }
                                echo "</td>
                                        <td><strong>" . htmlspecialchars($a['nombres_apellidos']) . "</strong></td>
                                        <td>" . htmlspecialchars($a['cedula']) . "</td>
                                        <td><span style='background:rgba(37, 99, 235, 0.15); color:#2563eb; padding:3px 8px; border-radius:4px; font-weight:600;'>" . htmlspecialchars($a['cargo']) . "</span></td>
                                        <td style='text-align: center;'>{$img_firma}</td>
                                        <td><span style='" . $badge_color . " padding:3px 8px; border-radius:4px; font-weight:600;'>" . htmlspecialchars($estatus_val) . "</span></td>
                                      </tr>";
                            }
                        } else {
                            echo "<tr><td colspan='6' style='text-align: center; color: var(--text-secondary); padding: 20px;'>No hay autoridades registradas en el sistema.</td></tr>";
                        }
                    }
                    ?>
                </table>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        if (typeof pdfjsLib !== 'undefined') {
            pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
        }

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

        let imagenOriginalFirma = null;
        let canvasRotadoBase = document.createElement('canvas');
        let anguloRotacion = 0;
        let modoActualFirma = 'subir';
        const esEdicion = <?php echo $editando ? 'true' : 'false'; ?>;

        let seleccion = { x: 0.12, y: 0.15, w: 0.76, h: 0.70 };
        let arrastrandoSeleccion = false;
        let puntoInicio = { x: 0, y: 0 };

        const canvasRecorte = document.getElementById('canvasRecorte');
        const ctxRecorte = canvasRecorte.getContext('2d');

        function cambiarModoFirma(modo) {
            modoActualFirma = modo;
            document.getElementById('tabSubirBtn').classList.toggle('active', modo === 'subir');
            document.getElementById('tabDibujarBtn').classList.toggle('active', modo === 'dibujar');
            document.getElementById('seccionSubirFoto').style.display = (modo === 'subir') ? 'block' : 'none';
            document.getElementById('seccionDibujarFirma').style.display = (modo === 'dibujar') ? 'block' : 'none';
            
            if (modo === 'subir' && imagenOriginalFirma) {
                procesarFotoFirma();
            } else if (modo === 'dibujar') {
                exportarTrazoDibujado();
            }
        }

        document.getElementById('inputFotoFirma').addEventListener('change', async function(e) {
            const file = e.target.files[0];
            if (!file) return;

            const esPdf = file.type === 'application/pdf' || file.name.toLowerCase().endsWith('.pdf');

            if (esPdf) {
                document.getElementById('estadoCargaPdf').style.display = 'block';
                try {
                    const arrayBuffer = await file.arrayBuffer();
                    const pdf = await pdfjsLib.getDocument({ data: arrayBuffer }).promise;
                    const page = await pdf.getPage(1);
                    const viewport = page.getViewport({ scale: 2.5 });

                    const tempCanvas = document.createElement('canvas');
                    tempCanvas.width = viewport.width;
                    tempCanvas.height = viewport.height;
                    const tempCtx = tempCanvas.getContext('2d');

                    await page.render({ canvasContext: tempCtx, viewport: viewport }).promise;

                    const img = new Image();
                    img.onload = function() {
                        document.getElementById('estadoCargaPdf').style.display = 'none';
                        iniciarImagenCargada(img, false);
                    };
                    img.src = tempCanvas.toDataURL('image/png');
                } catch (err) {
                    document.getElementById('estadoCargaPdf').style.display = 'none';
                    alert('No se pudo leer el archivo PDF. Intente subirlo como imagen JPG o PNG.');
                }
            } else {
                const reader = new FileReader();
                reader.onload = function(evt) {
                    const img = new Image();
                    img.onload = function() {
                        const girarAuto = (img.height > img.width * 1.15);
                        iniciarImagenCargada(img, girarAuto);
                    };
                    img.src = evt.target.result;
                };
                reader.readAsDataURL(file);
            }
        });

        function iniciarImagenCargada(img, girarAuto) {
            imagenOriginalFirma = img;
            anguloRotacion = girarAuto ? -90 : 0;
            seleccion = { x: 0.12, y: 0.15, w: 0.76, h: 0.70 };
            document.getElementById('controlesAjusteFoto').style.display = 'inline-flex';
            document.getElementById('panelRecorteYPrevia').style.display = 'grid';
            actualizarCanvasRotado();
            dibujarSelectorRecorte();
            procesarFotoFirma();
        }

        function rotarFotoFirma() {
            if (!imagenOriginalFirma) return;
            anguloRotacion = (anguloRotacion + 90) % 360;
            actualizarCanvasRotado();
            dibujarSelectorRecorte();
            procesarFotoFirma();
        }

        function actualizarCanvasRotado() {
            const w = imagenOriginalFirma.width;
            const h = imagenOriginalFirma.height;
            const rotada90 = Math.abs(anguloRotacion) === 90 || Math.abs(anguloRotacion) === 270;

            canvasRotadoBase.width = rotada90 ? h : w;
            canvasRotadoBase.height = rotada90 ? w : h;

            const ctx = canvasRotadoBase.getContext('2d');
            ctx.save();
            ctx.translate(canvasRotadoBase.width / 2, canvasRotadoBase.height / 2);
            ctx.rotate(anguloRotacion * Math.PI / 180);
            ctx.drawImage(imagenOriginalFirma, -w / 2, -h / 2, w, h);
            ctx.restore();

            const maxVistaW = 420;
            const escala = Math.min(1, maxVistaW / canvasRotadoBase.width);
            canvasRecorte.width = Math.round(canvasRotadoBase.width * escala);
            canvasRecorte.height = Math.round(canvasRotadoBase.height * escala);
        }

        function dibujarSelectorRecorte() {
            const cw = canvasRecorte.width;
            const ch = canvasRecorte.height;
            ctxRecorte.clearRect(0, 0, cw, ch);
            ctxRecorte.drawImage(canvasRotadoBase, 0, 0, cw, ch);

            ctxRecorte.fillStyle = 'rgba(15, 23, 42, 0.55)';
            ctxRecorte.fillRect(0, 0, cw, ch);

            const sx = seleccion.x * cw;
            const sy = seleccion.y * ch;
            const sw = seleccion.w * cw;
            const sh = seleccion.h * ch;

            ctxRecorte.save();
            ctxRecorte.beginPath();
            ctxRecorte.rect(sx, sy, sw, sh);
            ctxRecorte.clip();
            ctxRecorte.drawImage(canvasRotadoBase, 0, 0, cw, ch);
            ctxRecorte.restore();

            ctxRecorte.strokeStyle = '#2563eb';
            ctxRecorte.lineWidth = 2;
            ctxRecorte.setLineDash([5, 4]);
            ctxRecorte.strokeRect(sx, sy, sw, sh);
            ctxRecorte.setLineDash([]);
        }

        function posRelativaRecorte(e) {
            const rect = canvasRecorte.getBoundingClientRect();
            const clientX = e.touches ? e.touches[0].clientX : e.clientX;
            const clientY = e.touches ? e.touches[0].clientY : e.clientY;
            return {
                x: Math.max(0, Math.min(1, (clientX - rect.left) / rect.width)),
                y: Math.max(0, Math.min(1, (clientY - rect.top) / rect.height))
            };
        }

        canvasRecorte.addEventListener('mousedown', function(e) {
            arrastrandoSeleccion = true;
            puntoInicio = posRelativaRecorte(e);
        });

        canvasRecorte.addEventListener('mousemove', function(e) {
            if (!arrastrandoSeleccion) return;
            const actual = posRelativaRecorte(e);
            const x = Math.min(puntoInicio.x, actual.x);
            const y = Math.min(puntoInicio.y, actual.y);
            const w = Math.abs(actual.x - puntoInicio.x);
            const h = Math.abs(actual.y - puntoInicio.y);
            if (w > 0.05 && h > 0.05) {
                seleccion = { x, y, w, h };
                dibujarSelectorRecorte();
            }
        });

        window.addEventListener('mouseup', function() {
            if (arrastrandoSeleccion) {
                arrastrandoSeleccion = false;
                procesarFotoFirma();
            }
        });

        canvasRecorte.addEventListener('touchstart', function(e) {
            e.preventDefault();
            arrastrandoSeleccion = true;
            puntoInicio = posRelativaRecorte(e);
        }, { passive: false });

        canvasRecorte.addEventListener('touchmove', function(e) {
            if (!arrastrandoSeleccion) return;
            e.preventDefault();
            const actual = posRelativaRecorte(e);
            const x = Math.min(puntoInicio.x, actual.x);
            const y = Math.min(puntoInicio.y, actual.y);
            const w = Math.abs(actual.x - puntoInicio.x);
            const h = Math.abs(actual.y - puntoInicio.y);
            if (w > 0.05 && h > 0.05) {
                seleccion = { x, y, w, h };
                dibujarSelectorRecorte();
            }
        }, { passive: false });

        window.addEventListener('touchend', function() {
            if (arrastrandoSeleccion) {
                arrastrandoSeleccion = false;
                procesarFotoFirma();
            }
        });

        function procesarFotoFirma() {
            if (!imagenOriginalFirma) return;

            const rx = Math.round(seleccion.x * canvasRotadoBase.width);
            const ry = Math.round(seleccion.y * canvasRotadoBase.height);
            const rw = Math.max(20, Math.round(seleccion.w * canvasRotadoBase.width));
            const rh = Math.max(20, Math.round(seleccion.h * canvasRotadoBase.height));

            const canvasZona = document.createElement('canvas');
            canvasZona.width = rw;
            canvasZona.height = rh;
            const ctxZona = canvasZona.getContext('2d');
            ctxZona.drawImage(canvasRotadoBase, rx, ry, rw, rh, 0, 0, rw, rh);

            const imgData = ctxZona.getImageData(0, 0, rw, rh);
            const data = imgData.data;
            const umbral = parseInt(document.getElementById('umbralLimpieza').value, 10);
            const fuerza = parseInt(document.getElementById('fuerzaTinta').value, 10);

            let minX = rw, minY = rh, maxX = 0, maxY = 0;
            let hayTrazos = false;
            const rangoSuave = 35;

            for (let y = 0; y < rh; y++) {
                for (let x = 0; x < rw; x++) {
                    const idx = (y * rw + x) * 4;
                    const r = data[idx];
                    const g = data[idx + 1];
                    const b = data[idx + 2];

                    const lum = 0.299 * r + 0.587 * g + 0.114 * b;

                    if (lum < umbral) {
                        let alphaNorm = (umbral - lum) / rangoSuave;
                        alphaNorm = Math.min(1, alphaNorm * (0.65 + fuerza * 0.22));
                        const alpha = Math.round(alphaNorm * 255);

                        data[idx] = 0;
                        data[idx + 1] = 0;
                        data[idx + 2] = 0;
                        data[idx + 3] = alpha;

                        if (alpha > 95) {
                            if (x < minX) minX = x;
                            if (x > maxX) maxX = x;
                            if (y < minY) minY = y;
                            if (y > maxY) maxY = y;
                            hayTrazos = true;
                        }
                    } else {
                        data[idx + 3] = 0;
                    }
                }
            }

            ctxZona.putImageData(imgData, 0, 0);

            let canvasRecortado = canvasZona;
            if (hayTrazos && (maxX - minX > 8) && (maxY - minY > 8)) {
                const pad = 10;
                const cropX = Math.max(0, minX - pad);
                const cropY = Math.max(0, minY - pad);
                const cropW = Math.min(rw - cropX, (maxX - minX) + pad * 2);
                const cropH = Math.min(rh - cropY, (maxY - minY) + pad * 2);

                canvasRecortado = document.createElement('canvas');
                canvasRecortado.width = cropW;
                canvasRecortado.height = cropH;
                canvasRecortado.getContext('2d').drawImage(canvasZona, cropX, cropY, cropW, cropH, 0, 0, cropW, cropH);
            }

            const anchoObjetivo = Math.max(700, canvasRecortado.width);
            const escalaHD = anchoObjetivo / canvasRecortado.width;
            const canvasHD = document.createElement('canvas');
            canvasHD.width = Math.round(canvasRecortado.width * escalaHD);
            canvasHD.height = Math.round(canvasRecortado.height * escalaHD);

            const ctxHD = canvasHD.getContext('2d');
            ctxHD.imageSmoothingEnabled = true;
            ctxHD.imageSmoothingQuality = 'high';
            ctxHD.drawImage(canvasRecortado, 0, 0, canvasHD.width, canvasHD.height);

            const dataUrl = canvasHD.toDataURL('image/png');
            document.getElementById('firma_procesada').value = dataUrl;
            document.getElementById('imgVistaPreviaFirma').src = dataUrl;
        }

        const padCanvas = document.getElementById('canvasPadDibujo');
        const padCtx = padCanvas.getContext('2d');
        let dibujando = false;
        let tieneTrazoPad = false;

        padCtx.strokeStyle = '#000000';
        padCtx.lineWidth = 3.2;
        padCtx.lineCap = 'round';
        padCtx.lineJoin = 'round';

        function obtenerPosPad(e) {
            const rect = padCanvas.getBoundingClientRect();
            const clientX = e.touches ? e.touches[0].clientX : e.clientX;
            const clientY = e.touches ? e.touches[0].clientY : e.clientY;
            return {
                x: (clientX - rect.left) * (padCanvas.width / rect.width),
                y: (clientY - rect.top) * (padCanvas.height / rect.height)
            };
        }

        function iniciarTrazo(e) {
            e.preventDefault();
            dibujando = true;
            tieneTrazoPad = true;
            const pos = obtenerPosPad(e);
            padCtx.beginPath();
            padCtx.moveTo(pos.x, pos.y);
        }

        function moverTrazo(e) {
            if (!dibujando) return;
            e.preventDefault();
            const pos = obtenerPosPad(e);
            padCtx.lineTo(pos.x, pos.y);
            padCtx.stroke();
        }

        function finalizarTrazo() {
            if (!dibujando) return;
            dibujando = false;
            exportarTrazoDibujado();
        }

        padCanvas.addEventListener('mousedown', iniciarTrazo);
        padCanvas.addEventListener('mousemove', moverTrazo);
        window.addEventListener('mouseup', finalizarTrazo);
        padCanvas.addEventListener('touchstart', iniciarTrazo, { passive: false });
        padCanvas.addEventListener('touchmove', moverTrazo, { passive: false });
        window.addEventListener('touchend', finalizarTrazo);

        function limpiarPadDibujo() {
            padCtx.clearRect(0, 0, padCanvas.width, padCanvas.height);
            tieneTrazoPad = false;
            document.getElementById('firma_procesada').value = '';
        }

        function exportarTrazoDibujado() {
            if (!tieneTrazoPad) {
                document.getElementById('firma_procesada').value = '';
                return;
            }
            const dataUrl = padCanvas.toDataURL('image/png');
            document.getElementById('firma_procesada').value = dataUrl;
        }

        function validarEnvioFirma() {
            const firmaBase64 = document.getElementById('firma_procesada').value;
            const inputArchivo = document.getElementById('inputFotoFirma');
            if (!esEdicion && !firmaBase64 && (!inputArchivo.files || inputArchivo.files.length === 0)) {
                alert('Por favor, suba el documento/foto de la firma o dibújela en pantalla antes de registrar.');
                return false;
            }
            if (firmaBase64) {
                inputArchivo.value = '';
            }
            return true;
        }
    </script>
</body>
</html>