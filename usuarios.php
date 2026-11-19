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
            alert('Acceso denegado. El módulo de Usuarios es exclusivo para la Dirección.');
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

$mensaje = "";
$usuario_actual = $_SESSION['usuario'] ?? 'Administrador';

$editando = false;
$edit_id = "";
$edit_usuario = "";
$edit_rol = "";
$edit_correo = "";
$edit_estado = "";

function registrarHistorialUsuarios($conexion, $descripcion, $usuario) {
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

if (isset($_GET['editar'])) {
    $edit_id = intval($_GET['editar']);
    if ($conexion instanceof PDO) {
        $stmt_edit = $conexion->prepare("SELECT * FROM usuarios WHERE id = :id LIMIT 1");
        $stmt_edit->execute([':id' => $edit_id]);
        $row_edit = $stmt_edit->fetch(PDO::FETCH_ASSOC);
        if ($row_edit) {
            $editando = true;
            $edit_usuario = $row_edit['usuario'];
            $edit_rol = $row_edit['rol'];
            $edit_correo = $row_edit['correo'];
            $edit_estado = $row_edit['estado'] ?? '';
        }
    } else {
        $res_edit = mysqli_query($conexion, "SELECT * FROM usuarios WHERE id = $edit_id LIMIT 1");
        if ($res_edit && mysqli_num_rows($res_edit) > 0) {
            $row_edit = mysqli_fetch_assoc($res_edit);
            $editando = true;
            $edit_usuario = $row_edit['usuario'];
            $edit_rol = $row_edit['rol'];
            $edit_correo = $row_edit['correo'];
            $edit_estado = $row_edit['estado'] ?? '';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    if ($_POST['accion'] === 'crear_usuario') {
        $usuario = trim($_POST['usuario'] ?? '');
        $password = trim($_POST['password'] ?? ''); 
        $confirmar_password = trim($_POST['confirmar_password'] ?? '');
        $rol = trim($_POST['rol'] ?? '');
        $correo = trim($_POST['correo'] ?? '');
        $pin = trim($_POST['pin'] ?? '');
        $estado = trim($_POST['estado'] ?? '');

        if (empty($usuario) || empty($password) || empty($confirmar_password) || empty($rol) || empty($correo) || empty($pin) || empty($estado)) {
            $mensaje = "<div style='color: #721c24; background-color: #f8d7da; padding: 10px; border-radius: 5px; margin-bottom: 15px;'>Error: Complete todos los campos obligatorios, incluyendo el estado del usuario.</div>";
        } elseif ($password !== $confirmar_password) {
            $mensaje = "<div style='color: #721c24; background-color: #f8d7da; padding: 10px; border-radius: 5px; margin-bottom: 15px;'>Error: Las contraseñas no coinciden.</div>";
        } elseif (strlen($usuario) < 4 || strlen($usuario) > 15 || !preg_match('/^[A-Za-z_]+$/', $usuario)) {
            $mensaje = "<div style='color: #721c24; background-color: #f8d7da; padding: 10px; border-radius: 5px; margin-bottom: 15px;'>Error: El nombre de usuario debe tener entre 4 y 15 caracteres y solo permite letras y guion bajo.</div>";
        } elseif (strlen($password) < 6 || strlen($password) > 10 || !preg_match('/^[A-Z](?=.*\d)(?=.*[\W_]).{5,9}$/', $password)) {
            $mensaje = "<div style='color: #721c24; background-color: #f8d7da; padding: 10px; border-radius: 5px; margin-bottom: 15px;'>Error: La contraseña debe iniciar con mayúscula, tener números y un símbolo, entre 6 y 10 caracteres (ej. Pedro.24).</div>";
        } elseif (strlen($correo) < 8 || strlen($correo) > 50 || !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            $mensaje = "<div style='color: #721c24; background-color: #f8d7da; padding: 10px; border-radius: 5px; margin-bottom: 15px;'>Error: Ingrese un correo electrónico válido (entre 8 y 50 caracteres).</div>";
        } elseif (strlen($pin) < 4 || strlen($pin) > 8 || !preg_match('/^[0-9]+$/', $pin)) {
            $mensaje = "<div style='color: #721c24; background-color: #f8d7da; padding: 10px; border-radius: 5px; margin-bottom: 15px;'>Error: El PIN de seguridad solo permite números y debe tener entre 4 y 8 dígitos.</div>";
        } else {
            $clave_cifrada = password_hash($password, PASSWORD_DEFAULT);

            if ($conexion instanceof PDO) {
                try {
                    $chk = $conexion->prepare("SELECT id FROM usuarios WHERE usuario = :usuario LIMIT 1");
                    $chk->execute([':usuario' => $usuario]);
                    if ($chk->rowCount() > 0) {
                        $mensaje = "<div style='color: #721c24; background-color: #f8d7da; padding: 10px; border-radius: 5px; margin-bottom: 15px;'>Error: El nombre de usuario ya existe.</div>";
                    } else {
                        $stmt_ins = $conexion->prepare("INSERT INTO usuarios (usuario, clave, rol, correo, pin_seguridad, estado) VALUES (:usuario, :clave, :rol, :correo, :pin, :estado)");
                        if ($stmt_ins->execute([':usuario' => $usuario, ':clave' => $clave_cifrada, ':rol' => $rol, ':correo' => $correo, ':pin' => $pin, ':estado' => $estado])) {
                            registrarHistorialUsuarios($conexion, "Registro del nuevo usuario: $usuario (Estado: $estado)", $usuario_actual);
                            $mensaje = "<div style='color: #155724; background-color: #d4edda; padding: 10px; border-radius: 5px; margin-bottom: 15px;'>¡Usuario registrado y cifrado exitosamente!</div>";
                        } else {
                            $mensaje = "<div style='color: #721c24; background-color: #f8d7da; padding: 10px; border-radius: 5px; margin-bottom: 15px;'>Error al registrar en la base de datos.</div>";
                        }
                    }
                } catch (Exception $e) {
                    $mensaje = "<div style='color: #721c24; background-color: #f8d7da; padding: 10px; border-radius: 5px; margin-bottom: 15px;'>Error: " . $e->getMessage() . "</div>";
                }
            } else {
                $usuario_limpio = mysqli_real_escape_string($conexion, $usuario);
                $rol_limpio = mysqli_real_escape_string($conexion, $rol);
                $correo_limpio = mysqli_real_escape_string($conexion, $correo);
                $pin_limpio = mysqli_real_escape_string($conexion, $pin);
                $estado_limpio = mysqli_real_escape_string($conexion, $estado);

                $check = mysqli_query($conexion, "SELECT id FROM usuarios WHERE usuario = '$usuario_limpio' LIMIT 1");
                if (mysqli_num_rows($check) > 0) {
                    $mensaje = "<div style='color: #721c24; background-color: #f8d7da; padding: 10px; border-radius: 5px; margin-bottom: 15px;'>Error: El nombre de usuario ya existe.</div>";
                } else {
                    $sql_insert = "INSERT INTO usuarios (usuario, clave, rol, correo, pin_seguridad, estado) VALUES ('$usuario_limpio', '$clave_cifrada', '$rol_limpio', '$correo_limpio', '$pin_limpio', '$estado_limpio')";
                    if (mysqli_query($conexion, $sql_insert)) {
                        registrarHistorialUsuarios($conexion, "Registro del nuevo usuario: $usuario_limpio (Estado: $estado_limpio)", $usuario_actual);
                        $mensaje = "<div style='color: #155724; background-color: #d4edda; padding: 10px; border-radius: 5px; margin-bottom: 15px;'>¡Usuario registrado y cifrado exitosamente!</div>";
                    } else {
                        $mensaje = "<div style='color: #721c24; background-color: #f8d7da; padding: 10px; border-radius: 5px; margin-bottom: 15px;'>Error al registrar en la base de datos.</div>";
                    }
                }
            }
        }
    } 
    elseif ($_POST['accion'] === 'actualizar_usuario') {
        $id_usuario = intval($_POST['id_usuario']);
        $usuario = trim($_POST['usuario'] ?? '');
        $password = trim($_POST['password'] ?? ''); 
        $confirmar_password = trim($_POST['confirmar_password'] ?? '');
        $rol = trim($_POST['rol'] ?? '');
        $correo = trim($_POST['correo'] ?? '');
        $pin = trim($_POST['pin'] ?? '');
        $estado = trim($_POST['estado'] ?? '');

        if (empty($usuario) || empty($rol) || empty($correo) || empty($estado)) {
            $mensaje = "<div style='color: #721c24; background-color: #f8d7da; padding: 10px; border-radius: 5px; margin-bottom: 15px;'>Error: Los campos de usuario, rol, correo y estado son obligatorios.</div>";
        } elseif (!empty($password) && $password !== $confirmar_password) {
            $mensaje = "<div style='color: #721c24; background-color: #f8d7da; padding: 10px; border-radius: 5px; margin-bottom: 15px;'>Error: Las nuevas contraseñas no coinciden.</div>";
        } elseif (strlen($usuario) < 4 || strlen($usuario) > 15 || !preg_match('/^[A-Za-z_]+$/', $usuario)) {
            $mensaje = "<div style='color: #721c24; background-color: #f8d7da; padding: 10px; border-radius: 5px; margin-bottom: 15px;'>Error: El usuario debe tener entre 4 y 15 caracteres y solo permite letras y guion bajo.</div>";
        } elseif (strlen($correo) < 8 || strlen($correo) > 50 || !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            $mensaje = "<div style='color: #721c24; background-color: #f8d7da; padding: 10px; border-radius: 5px; margin-bottom: 15px;'>Error: Ingrese un correo electrónico válido (entre 8 y 50 caracteres).</div>";
        } elseif (!empty($pin) && (strlen($pin) < 4 || strlen($pin) > 8 || !preg_match('/^[0-9]+$/', $pin))) {
            $mensaje = "<div style='color: #721c24; background-color: #f8d7da; padding: 10px; border-radius: 5px; margin-bottom: 15px;'>Error: El PIN solo permite números y debe tener entre 4 y 8 dígitos.</div>";
        } else {
            if (!empty($password)) {
                if (strlen($password) < 6 || strlen($password) > 10 || !preg_match('/^[A-Z](?=.*\d)(?=.*[\W_]).{5,9}$/', $password)) {
                    $mensaje = "<div style='color: #721c24; background-color: #f8d7da; padding: 10px; border-radius: 5px; margin-bottom: 15px;'>Error: La contraseña debe tener entre 6 y 10 caracteres, iniciar con mayúscula, incluir números y un símbolo.</div>";
                }
            }

            if (empty($mensaje)) {
                if ($conexion instanceof PDO) {
                    try {
                        if (!empty($password)) {
                            $clave_cifrada = password_hash($password, PASSWORD_DEFAULT);
                            $stmt_up = $conexion->prepare("UPDATE usuarios SET usuario = :usu, clave = :clav, rol = :rol, correo = :corr, pin_seguridad = COALESCE(NULLIF(:pin, ''), pin_seguridad), estado = :est WHERE id = :id");
                            $stmt_up->execute([':usu' => $usuario, ':clav' => $clave_cifrada, ':rol' => $rol, ':corr' => $correo, ':pin' => $pin, ':est' => $estado, ':id' => $id_usuario]);
                        } else {
                            $stmt_up = $conexion->prepare("UPDATE usuarios SET usuario = :usu, rol = :rol, correo = :corr, pin_seguridad = COALESCE(NULLIF(:pin, ''), pin_seguridad), estado = :est WHERE id = :id");
                            $stmt_up->execute([':usu' => $usuario, ':rol' => $rol, ':corr' => $correo, ':pin' => $pin, ':est' => $estado, ':id' => $id_usuario]);
                        }
                        registrarHistorialUsuarios($conexion, "Modificación del usuario: $usuario (Estado: $estado)", $usuario_actual);
                        $mensaje = "<div style='color: #155724; background-color: #d4edda; padding: 10px; border-radius: 5px; margin-bottom: 15px;'>¡Usuario modificado exitosamente!</div>";
                        $editando = false;
                        $edit_id = $edit_usuario = $edit_rol = $edit_correo = "";
                        $edit_estado = "";
                    } catch (Exception $e) {
                        $mensaje = "<div style='color: #721c24; background-color: #f8d7da; padding: 10px; border-radius: 5px; margin-bottom: 15px;'>Error al actualizar el usuario: " . $e->getMessage() . "</div>";
                    }
                } else {
                    $usuario_limpio = mysqli_real_escape_string($conexion, $usuario);
                    $rol_limpio = mysqli_real_escape_string($conexion, $rol);
                    $correo_limpio = mysqli_real_escape_string($conexion, $correo);
                    $pin_limpio = mysqli_real_escape_string($conexion, $pin);
                    $estado_limpio = mysqli_real_escape_string($conexion, $estado);

                    if (!empty($password)) {
                        $clave_cifrada = password_hash($password, PASSWORD_DEFAULT);
                        $sql_update = "UPDATE usuarios SET usuario='$usuario_limpio', clave='$clave_cifrada', rol='$rol_limpio', correo='$correo_limpio', estado='$estado_limpio'" . (!empty($pin_limpio) ? ", pin_seguridad='$pin_limpio'" : "") . " WHERE id=$id_usuario";
                    } else {
                        $sql_update = "UPDATE usuarios SET usuario='$usuario_limpio', rol='$rol_limpio', correo='$correo_limpio', estado='$estado_limpio'" . (!empty($pin_limpio) ? ", pin_seguridad='$pin_limpio'" : "") . " WHERE id=$id_usuario";
                    }

                    if (mysqli_query($conexion, $sql_update)) {
                        registrarHistorialUsuarios($conexion, "Modificación del usuario: $usuario_limpio (Estado: $estado_limpio)", $usuario_actual);
                        $mensaje = "<div style='color: #155724; background-color: #d4edda; padding: 10px; border-radius: 5px; margin-bottom: 15px;'>¡Usuario modificado exitosamente!</div>";
                        $editando = false; 
                        $edit_id = $edit_usuario = $edit_rol = $edit_correo = "";
                        $edit_estado = "";
                    } else {
                        $mensaje = "<div style='color: #721c24; background-color: #f8d7da; padding: 10px; border-radius: 5px; margin-bottom: 15px;'>Error al actualizar el usuario.</div>";
                    }
                }
            }
        }
    }
    elseif ($_POST['accion'] === 'archivar_usuario') {
        $id_usuario = intval($_POST['id_usuario']);
        $usuario_actual_sesion = $_SESSION['usuario'] ?? '';
        
        if ($conexion instanceof PDO) {
            $stmt_chk = $conexion->prepare("SELECT usuario FROM usuarios WHERE id = :id");
            $stmt_chk->execute([':id' => $id_usuario]);
            $row_user = $stmt_chk->fetch(PDO::FETCH_ASSOC);
            if ($row_user) {
                if ($row_user['usuario'] === $usuario_actual_sesion) {
                    $mensaje = "<div style='color: #721c24; background-color: #f8d7da; padding: 10px; border-radius: 5px; margin-bottom: 15px;'>Error: No puedes inhabilitar tu propio usuario activo.</div>";
                } else {
                    $nombre_archivado = $row_user['usuario'];
                    $stmt_arch = $conexion->prepare("UPDATE usuarios SET estado = 'Inactivo' WHERE id = :id");
                    if ($stmt_arch->execute([':id' => $id_usuario])) {
                        registrarHistorialUsuarios($conexion, "Inhabilitación del usuario: $nombre_archivado", $usuario_actual);
                        $mensaje = "<div style='background-color: #fff3cd; color: #856404; padding: 10px; border-radius: 5px; margin-bottom: 15px;'>El usuario ha sido marcado como inactivo correctamente.</div>";
                    }
                }
            }
        } else {
            $sql_check = "SELECT usuario FROM usuarios WHERE id = $id_usuario";
            $res_check = mysqli_query($conexion, $sql_check);
            if ($res_check && $row_user = mysqli_fetch_assoc($res_check)) {
                if ($row_user['usuario'] === $usuario_actual_sesion) {
                    $mensaje = "<div style='color: #721c24; background-color: #f8d7da; padding: 10px; border-radius: 5px; margin-bottom: 15px;'>Error: No puedes inhabilitar tu propio usuario activo.</div>";
                } else {
                    $nombre_archivado = $row_user['usuario'];
                    $sql_arch = "UPDATE usuarios SET estado = 'Inactivo' WHERE id = $id_usuario";
                    if (mysqli_query($conexion, $sql_arch)) {
                        registrarHistorialUsuarios($conexion, "Inhabilitación del usuario: $nombre_archivado", $usuario_actual);
                        $mensaje = "<div style='background-color: #fff3cd; color: #856404; padding: 10px; border-radius: 5px; margin-bottom: 15px;'>El usuario ha sido marcado como inactivo correctamente.</div>";
                    }
                }
            }
        }
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
    <title>Gestión de Usuarios - Sistema de Inscripción</title>
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

        body.dark-mode th { background-color: #1e293b !important; color: var(--text-primary) !important; border-color: var(--border-color) !important; }
        body.dark-mode td { background-color: var(--bg-surface) !important; color: var(--text-primary) !important; border-color: var(--border-color) !important; }
        body.dark-mode tr:nth-child(even) td { background-color: var(--bg-surface-alt) !important; }
        body.dark-mode tr:hover td { background-color: #22304a !important; }
        body.dark-mode input, body.dark-mode select { background-color: var(--bg-surface-alt) !important; color: var(--text-primary) !important; border-color: var(--border-color) !important; }

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
        .role-badge.badge-coord { background-color: rgba(59, 130, 246, 0.15); color: #3b82f6; border: 1px solid rgba(59, 130, 246, 0.3); }

        .card { background: #FFFFFF; padding: 20px; border-radius: 12px; box-shadow: var(--card-shadow); margin-bottom: 20px; width: 100%; box-sizing: border-box; border: 1px solid var(--border-color); }
        .card h2 { font-size: 1.15rem; font-weight: 700; margin-bottom: 5px; color: var(--text-primary); }
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 15px; margin-bottom: 15px; width: 100%; box-sizing: border-box; }
        .form-group { display: flex; flex-direction: column; width: 100%; box-sizing: border-box; }
        .form-group label { font-size: 0.85rem; font-weight: 600; margin-bottom: 5px; color: var(--text-secondary); }
        .form-group input, .form-group select { padding: 9px 12px; border: 1px solid var(--border-color); border-radius: 8px; font-size: 0.9rem; width: 100%; box-sizing: border-box; background-color: var(--bg-surface); color: var(--text-primary); }
        .btn-accion { background-color: #059669; color: white; border: none; padding: 9px 18px; border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 0.9rem; }
        .btn-accion:hover { opacity: 0.9; }
        .btn-modificar { background-color: #f59e0b; color: white; border: none; padding: 5px 10px; border-radius: 6px; cursor: pointer; font-size: 0.78rem; font-weight: 600; text-decoration: none; display: inline-block; }
        .btn-archivar { background-color: #d97706; color: white; border: none; padding: 5px 10px; border-radius: 6px; cursor: pointer; font-size: 0.78rem; font-weight: 600; }
        .table-responsive { width: 100%; overflow-x: auto; border: 1px solid var(--border-color); border-radius: 8px; margin-top: 15px; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; background-color: var(--bg-surface); color: var(--text-primary); }
        th, td { border: 1px solid var(--border-color); padding: 10px; text-align: left; white-space: nowrap; }
        th { background-color: #f1f5f9; color: var(--text-primary); font-weight: 700; position: sticky; top: 0; z-index: 10; }
        tr:nth-child(even) { background-color: var(--bg-surface-alt); }
        tr:hover { background-color: rgba(37, 99, 235, 0.04); }

        .password-container { position: relative; display: flex; align-items: center; }
        .password-container input { padding-right: 38px; }
        .password-toggle-icon { position: absolute; right: 12px; cursor: pointer; color: var(--text-secondary); font-size: 0.95rem; user-select: none; }
        .password-toggle-icon:hover { color: #2563eb; }

        @media (max-width: 768px) { html, body { flex-direction: column; } .main-content { padding: 12px; } }
    </style>
</head>
<body>

    <?php include("menu.php"); ?>

    <main class="main-content">
        <div class="top-header-bar">
            <div class="top-breadcrumb">
                <i class="fas fa-users" style="color: #2563eb;"></i>
                <span>Gestión de Usuarios</span>
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

        <div class="card">
            <h2><i class="fas fa-user-shield text-primary" style="color: #2563eb;"></i> <?php echo $editando ? 'Modificar Usuario del Sistema' : 'Gestión de Usuarios del Sistema'; ?></h2>
            <p class="text-muted" style="color: var(--text-secondary);"><?php echo $editando ? 'Modifique los datos del usuario seleccionado.' : 'Registrar nuevos usuarios'; ?></p>
            <hr style="margin-bottom: 20px; border: 0; border-top: 1px solid var(--border-color);">

            <form method="POST" action="usuarios.php" style="margin-bottom: 25px;">
                <input type="hidden" name="accion" value="<?php echo $editando ? 'actualizar_usuario' : 'crear_usuario'; ?>">
                <?php if ($editando): ?>
                    <input type="hidden" name="id_usuario" value="<?php echo $edit_id; ?>">
                <?php endif; ?>

                <div class="form-grid">
                    <div class="form-group">
                        <label>Nombre de Usuario (*):</label>
                        <input type="text" name="usuario" maxlength="15" pattern="[A-Za-z_]{4,15}" title="Solo letras y guion bajo (de 4 a 15 caracteres)" oninput="this.value = this.value.replace(/[^A-Za-z_]/g, '')" required value="<?php echo htmlspecialchars($edit_usuario); ?>" placeholder="Ej: pedro_calderon">
                    </div>

                    <div class="form-group">
                        <label>Contraseña Segura <?php echo $editando ? '(Opcional):' : '(*)'; ?>:</label>
                        <div class="password-container">
                            <input type="password" name="password" id="inputPassword" maxlength="10" pattern="[A-Z](?=.*\d)(?=.*[\W_]).{5,9}" title="Debe iniciar con mayúscula, incluir números y un símbolo (de 6 a 10 caracteres)" <?php echo $editando ? '' : 'required'; ?> placeholder="Ej: Pedro.24">
                            <i class="fas fa-eye password-toggle-icon" onclick="togglePasswordVisibility('inputPassword', this)"></i>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Repetir Contraseña <?php echo $editando ? '(Opcional):' : '(*)'; ?>:</label>
                        <div class="password-container">
                            <input type="password" name="confirmar_password" id="inputConfirmPassword" maxlength="10" title="Repita la contraseña" <?php echo $editando ? '' : 'required'; ?> placeholder="Repita la contraseña">
                            <i class="fas fa-eye password-toggle-icon" onclick="togglePasswordVisibility('inputConfirmPassword', this)"></i>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Rol de Usuario (*):</label>
                        <select name="rol" required>
                            <option value="">Seleccione un rol...</option>
                            <option value="Director" <?php if($edit_rol=='Director') echo 'selected'; ?>>Director</option>
                            <option value="Administrador" <?php if($edit_rol=='Administrador') echo 'selected'; ?>>Administrador</option>
                            <option value="Secretaría" <?php if($edit_rol=='Secretaría') echo 'selected'; ?>>Secretaría</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Correo Electrónico (*):</label>
                        <input type="email" name="correo" maxlength="50" title="Ingrese un correo electrónico válido" required value="<?php echo htmlspecialchars($edit_correo); ?>" placeholder="correo@escuela.com">
                    </div>

                    <div class="form-group">
                        <label>PIN de Recuperación <?php echo $editando ? '(Opcional):' : '(*)'; ?>:</label>
                        <input type="password" name="pin" maxlength="8" pattern="[0-9]{4,8}" title="Solo números (de 4 a 8 dígitos)" oninput="this.value = this.value.replace(/[^0-9]/g, '')" <?php echo $editando ? '' : 'required'; ?> placeholder="Ej: 123456">
                    </div>

                    <div class="form-group">
                        <label>Estado del Usuario (*):</label>
                        <select name="estado" required>
                            <option value="">Seleccione un estado...</option>
                            <option value="Activo" <?php if($edit_estado=='Activo') echo 'selected'; ?>>Activo</option>
                            <option value="Inactivo" <?php if($edit_estado=='Inactivo') echo 'selected'; ?>>Inactivo</option>
                        </select>
                    </div>
                </div>

                <div style="display: flex; gap: 10px; margin-top: 15px;">
                    <button type="submit" class="btn-accion">
                        <i class="fas <?php echo $editando ? 'fa-save' : 'fa-user-plus'; ?>"></i> 
                        <?php echo $editando ? 'Guardar Cambios' : 'Registrar Nuevo Usuario'; ?>
                    </button>
                    <?php if ($editando): ?>
                        <a href="usuarios.php" style="background: #64748b; color: white; padding: 9px 18px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 0.9rem; display: inline-flex; align-items: center; gap: 6px;">
                            <i class="fas fa-times"></i> Cancelar
                        </a>
                    <?php endif; ?>
                </div>
            </form>

            <h3 style="font-size: 1.1rem; margin-bottom: 10px; color: var(--text-primary);"><i class="fas fa-users text-primary" style="color: #2563eb;"></i> Usuarios Registrados en el Sistema</h3>
            <div class="table-responsive">
                <table>
                    <tr>
                        <th style="text-align: center; width: 150px;">Acción</th>
                        <th>ID</th>
                        <th>Nombre de Usuario</th>
                        <th>Rol Asignado</th>
                        <th>Correo Electrónico</th>
                        <th>Estado</th>
                    </tr>
                    <?php
                    $res_usuarios = ($conexion instanceof PDO) ? $conexion->query("SELECT * FROM usuarios ORDER BY id DESC") : mysqli_query($conexion, "SELECT * FROM usuarios ORDER BY id DESC");
                    if ($res_usuarios) {
                        $hay_usuarios = false;
                        $rows_u = ($conexion instanceof PDO) ? $res_usuarios->fetchAll(PDO::FETCH_ASSOC) : $res_usuarios;
                        foreach($rows_u as $u) {
                            $hay_usuarios = true;
                            $rol_formateado = !empty($u['rol']) ? $u['rol'] : 'Administrador';
                            $correo_val = !empty($u['correo']) ? $u['correo'] : 'No registrado';
                            $estado_val = !empty($u['estado']) ? $u['estado'] : 'Activo';
                            $badge_color = ($estado_val === 'Activo') ? 'background:rgba(16, 185, 129, 0.15); color:#10b981;' : 'background:rgba(217, 119, 6, 0.15); color:#d97706;';
                            
                            echo "<tr>
                                    <td style='text-align: center; display: flex; gap: 5px; justify-content: center;'>
                                        <a href='usuarios.php?editar=" . $u['id'] . "' class='btn-modificar' title='Modificar'>
                                            <i class='fas fa-edit'></i> Modificar
                                        </a>";
                            if ($estado_val === 'Activo') {
                                echo "<form method='POST' action='usuarios.php' style='margin:0;'>
                                            <input type='hidden' name='accion' value='archivar_usuario'>
                                            <input type='hidden' name='id_usuario' value='" . $u['id'] . "'>
                                            <button type='submit' class='btn-archivar' onclick=\"return confirm('¿Está seguro de marcar como inactivo al usuario " . htmlspecialchars($u['usuario']) . "?');\" title='Inactivar'>
                                                <i class='fas fa-user-slash'></i> Inactivar
                                            </button>
                                        </form>";
                            } else {
                                echo "<span style='font-size:0.75rem; color:var(--text-secondary); align-self:center;'>Inactivo</span>";
                            }
                            echo "</td>
                                    <td>" . $u['id'] . "</td>
                                    <td><strong>" . htmlspecialchars($u['usuario']) . "</strong></td>
                                    <td><span style='background:rgba(37, 99, 235, 0.15); color:#2563eb; padding:3px 8px; border-radius:4px; font-weight:600;'>" . htmlspecialchars($rol_formateado) . "</span></td>
                                    <td>" . htmlspecialchars($correo_val) . "</td>
                                    <td><span style='" . $badge_color . " padding:3px 8px; border-radius:4px; font-weight:600;'>" . htmlspecialchars($estado_val) . "</span></td>
                                  </tr>";
                        }
                        if (!$hay_usuarios) {
                            echo "<tr><td colspan='6' style='text-align: center; color: var(--text-secondary); padding: 20px;'>No hay usuarios registrados en el sistema.</td></tr>";
                        }
                    } else {
                        echo "<tr><td colspan='6' style='text-align: center; color: var(--text-secondary); padding: 20px;'>No hay usuarios registrados en el sistema.</td></tr>";
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

        function togglePasswordVisibility(fieldId, iconElement) {
            const inputField = document.getElementById(fieldId);
            if (inputField.type === "password") {
                inputField.type = "text";
                iconElement.classList.remove("fa-eye");
                iconElement.classList.add("fa-eye-slash");
            } else {
                inputField.type = "password";
                iconElement.classList.remove("fa-eye-slash");
                iconElement.classList.add("fa-eye");
            }
        }
    </script>
</body>
</html>