<?php
session_start();
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
$error = "";
$usuario_actual_log = 'Sistema de Recuperación';

function registrarHistorialRecuperacion($conexion, $descripcion, $usuario) {
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['recuperar_por_pin'])) {
        $pin = trim($_POST['pin'] ?? '');

        if (!empty($pin)) {
            if ($conexion instanceof PDO) {
                $stmt = $conexion->prepare("SELECT usuario FROM usuarios WHERE pin_seguridad = :pin LIMIT 1");
                $stmt->execute([':pin' => $pin]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($user) {
                    $mensaje = "Su nombre de usuario es: <strong>" . htmlspecialchars($user['usuario']) . "</strong>";
                    registrarHistorialRecuperacion($conexion, "Consulta de usuario por PIN secreto", $usuario_actual_log);
                } else {
                    $error = "El PIN de seguridad es incorrecto.";
                }
            } else {
                $pin_limpio = mysqli_real_escape_string($conexion, $pin);
                $resultado = mysqli_query($conexion, "SELECT usuario FROM usuarios WHERE pin_seguridad = '$pin_limpio' LIMIT 1");
                if ($resultado && mysqli_num_rows($resultado) > 0) {
                    $user = mysqli_fetch_assoc($resultado);
                    $mensaje = "Su nombre de usuario es: <strong>" . htmlspecialchars($user['usuario']) . "</strong>";
                    registrarHistorialRecuperacion($conexion, "Consulta de usuario por PIN secreto", $usuario_actual_log);
                } else {
                    $error = "El PIN de seguridad es incorrecto.";
                }
            }
        } else {
            $error = "Por favor, ingrese su PIN secreto.";
        }
    }

    if (isset($_POST['recuperar_por_correo'])) {
        $correo = trim($_POST['correo'] ?? '');

        if (!empty($correo)) {
            if ($conexion instanceof PDO) {
                $stmt = $conexion->prepare("SELECT * FROM usuarios WHERE correo = :correo LIMIT 1");
                $stmt->execute([':correo' => $correo]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($user) {
                    $enviar_correo = $user['correo'];
                    $nombre_usuario = $user['usuario'];
                    $asunto = "Recuperación de Nombre de Usuario - Sistema Escolar";
                    $cuerpo = "Hola, has solicitado recuperar tu usuario en el Sistema de la Escuela Gladys Carnevali.\n\nTu nombre de usuario es: " . $nombre_usuario;
                    $cabeceras = "From: noreply@tu-escuela.com\r\n";
                    @mail($enviar_correo, $asunto, $cuerpo, $cabeceras);
                    registrarHistorialRecuperacion($conexion, "Envío de usuario por correo electrónico ($correo)", $usuario_actual_log);
                    $mensaje = "Se ha enviado su nombre de usuario al correo electrónico registrado. Por favor verifique su bandeja de entrada.";
                } else {
                    $error = "No se encontró ningún usuario con ese correo electrónico.";
                }
            } else {
                $correo_limpio = mysqli_real_escape_string($conexion, $correo);
                $resultado = mysqli_query($conexion, "SELECT * FROM usuarios WHERE correo = '$correo_limpio' LIMIT 1");
                if ($resultado && mysqli_num_rows($resultado) > 0) {
                    $user = mysqli_fetch_assoc($resultado);
                    $enviar_correo = $user['correo'];
                    $nombre_usuario = $user['usuario'];
                    $asunto = "Recuperación de Nombre de Usuario - Sistema Escolar";
                    $cuerpo = "Hola, has solicitado recuperar tu usuario en el Sistema de la Escuela Gladys Carnevali.\n\nTu nombre de usuario es: " . $nombre_usuario;
                    $cabeceras = "From: noreply@tu-escuela.com\r\n";
                    @mail($enviar_correo, $asunto, $cuerpo, $cabeceras);
                    registrarHistorialRecuperacion($conexion, "Envío de usuario por correo electrónico ($correo)", $usuario_actual_log);
                    $mensaje = "Se ha enviado su nombre de usuario al correo electrónico registrado. Por favor verifique su bandeja de entrada.";
                } else {
                    $error = "No se encontró ningún usuario con ese correo electrónico.";
                }
            }
        } else {
            $error = "Por favor, ingrese su correo electrónico.";
        }
    }

    if (isset($_POST['restablecer_password'])) {
        $usuario = trim($_POST['usuario'] ?? '');
        $identificador = trim($_POST['identificador'] ?? '');
        $nueva_clave = trim($_POST['nueva_clave'] ?? '');

        if (!empty($usuario) && !empty($identificador) && !empty($nueva_clave)) {
            if (!preg_match('/^[A-Z](?=.*\d)(?=.*[\W_]).{5,}$/', $nueva_clave)) {
                $error = "La nueva contraseña debe comenzar con mayúscula, tener números y un símbolo (ej. Pedro.24).";
            } else {
                $clave_cifrada = password_hash($nueva_clave, PASSWORD_DEFAULT);
                if ($conexion instanceof PDO) {
                    $stmt = $conexion->prepare("SELECT * FROM usuarios WHERE usuario = :usuario AND (correo = :ident OR pin_seguridad = :ident) LIMIT 1");
                    $stmt->execute([':usuario' => $usuario, ':ident' => $identificador]);
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($user) {
                        $stmt_up = $conexion->prepare("UPDATE usuarios SET clave = :clave WHERE usuario = :usuario");
                        $stmt_up->execute([':clave' => $clave_cifrada, ':usuario' => $usuario]);
                        registrarHistorialRecuperacion($conexion, "Restablecimiento de contraseña para el usuario: $usuario", $usuario_actual_log);
                        $mensaje = "¡Contraseña restablecida exitosamente! Ya puede iniciar sesión.";
                    } else {
                        $error = "El usuario o el dato de verificación son incorrectos.";
                    }
                } else {
                    $usuario_limpio = mysqli_real_escape_string($conexion, $usuario);
                    $id_limpio = mysqli_real_escape_string($conexion, $identificador);
                    $resultado = mysqli_query($conexion, "SELECT * FROM usuarios WHERE usuario = '$usuario_limpio' AND (correo = '$id_limpio' OR pin_seguridad = '$id_limpio') LIMIT 1");
                    if ($resultado && mysqli_num_rows($resultado) > 0) {
                        mysqli_query($conexion, "UPDATE usuarios SET clave = '$clave_cifrada' WHERE usuario = '$usuario_limpio'");
                        registrarHistorialRecuperacion($conexion, "Restablecimiento de contraseña para el usuario: $usuario", $usuario_actual_log);
                        $mensaje = "¡Contraseña restablecida exitosamente! Ya puede iniciar sesión.";
                    } else {
                        $error = "El usuario o el dato de verificación son incorrectos.";
                    }
                }
            }
        } else {
            $error = "Por favor, complete todos los campos.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Centro de Recuperación - Sistema de Inscripción</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --bg-card-right: #ffffff;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --input-bg: #f8fafc;
            --input-border: #cbd5e1;
            --divider-color: #e2e8f0;
            --card-shadow: 0 20px 45px rgba(0, 0, 0, 0.45);
            --tab-border: #e2e8f0;
        }

        body.dark-mode {
            --bg-card-right: #1e293b;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --input-bg: #0f172a;
            --input-border: #334155;
            --divider-color: #334155;
            --card-shadow: 0 24px 50px rgba(0, 0, 0, 0.75);
            --tab-border: #334155;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0; padding: 20px;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            background-color: #162338;
            background-image: 
                linear-gradient(135deg, rgba(15, 23, 42, 0.82) 0%, rgba(30, 58, 138, 0.78) 100%),
                url('imagenes/fondo.jpg'), url('imagenes/fondo.jpeg');
            background-size: cover; background-position: center; background-repeat: no-repeat; background-attachment: fixed;
            display: flex; justify-content: center; align-items: center; min-height: 100vh;
        }

        .login-split-wrapper {
            display: flex; width: 100%; max-width: 860px; min-height: 520px;
            border-radius: 18px; overflow: hidden; box-shadow: var(--card-shadow);
            border: 1px solid rgba(255, 255, 255, 0.15); backdrop-filter: blur(4px);
        }

        .login-brand-panel {
            flex: 0 0 42%;
            background: linear-gradient(145deg, #0b5ed7 0%, #1e3a8a 60%, #172554 100%);
            color: #ffffff; padding: 40px 28px;
            display: flex; flex-direction: column; justify-content: center; align-items: center; text-align: center;
            position: relative; overflow: hidden;
        }

        .brand-logo-card {
            background: #ffffff; width: 120px; height: 120px; border-radius: 20px;
            display: flex; align-items: center; justify-content: center; padding: 12px; margin-bottom: 20px;
            box-shadow: 0 12px 28px rgba(0, 0, 0, 0.28); position: relative; z-index: 2;
        }

        .brand-logo-card img { width: 82px; height: 82px; object-fit: contain; }
        .brand-title { font-size: 1.25rem; font-weight: 800; letter-spacing: 0.5px; margin: 0 0 8px 0; position: relative; z-index: 2; text-transform: uppercase; line-height: 1.25; }
        .brand-subtitle { font-size: 0.82rem; line-height: 1.45; color: #ffffff; font-weight: 700; margin: 0 0 6px 0; text-transform: uppercase; letter-spacing: 0.4px; position: relative; z-index: 2; }
        .brand-tagline { font-size: 0.76rem; color: rgba(255, 255, 255, 0.85); margin: 0; position: relative; z-index: 2; }

        .login-form-panel {
            flex: 1; background-color: var(--bg-card-right); padding: 34px 38px;
            display: flex; flex-direction: column; justify-content: center; position: relative;
            transition: background-color 0.25s ease;
        }

        .theme-toggle-btn {
            position: absolute; top: 20px; right: 22px; width: 36px; height: 36px; border-radius: 50%;
            border: 1px solid var(--input-border); background: var(--input-bg); color: var(--text-muted);
            display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 0.95rem;
            transition: all 0.2s ease;
        }
        .theme-toggle-btn:hover { color: #2563eb; border-color: #2563eb; transform: scale(1.06); }

        .form-header h2 { margin: 0 0 4px 0; font-size: 1.45rem; font-weight: 700; color: var(--text-main); }
        .form-header p { margin: 0 0 16px 0; font-size: 0.8rem; color: var(--text-muted); font-weight: 600; }

        .tabs-header { display: flex; border-bottom: 2px solid var(--tab-border); margin-bottom: 16px; gap: 6px; }
        .tab-btn {
            flex: 1; padding: 9px 12px; background: none; border: none; border-bottom: 3px solid transparent;
            cursor: pointer; font-weight: 700; font-size: 0.84rem; color: var(--text-muted);
            transition: all 0.2s ease; margin-bottom: -2px; display: flex; align-items: center; justify-content: center; gap: 7px;
        }
        .tab-btn:hover { color: #2563eb; }
        .tab-btn.active { color: #2563eb; border-bottom-color: #2563eb; }

        .tab-content { display: none; }
        .tab-content.active { display: block; animation: fadeIn 0.2s ease; }

        .alert-error {
            background-color: rgba(239, 68, 68, 0.12); color: #ef4444; padding: 10px 12px; border-radius: 8px;
            margin-bottom: 14px; font-size: 0.82rem; font-weight: 600; border: 1px solid rgba(239, 68, 68, 0.3);
            border-left: 4px solid #ef4444; display: flex; align-items: center; gap: 8px;
        }
        .alert-success {
            background-color: rgba(16, 185, 129, 0.12); color: #10b981; padding: 10px 12px; border-radius: 8px;
            margin-bottom: 14px; font-size: 0.82rem; font-weight: 600; border: 1px solid rgba(16, 185, 129, 0.3);
            border-left: 4px solid #10b981; display: flex; align-items: center; gap: 8px;
        }

        .form-group { margin-bottom: 14px; text-align: left; }
        .form-group label { display: block; font-size: 0.8rem; font-weight: 600; margin-bottom: 5px; color: var(--text-main); }
        .input-icon-wrap { position: relative; display: flex; align-items: center; }
        .input-icon-wrap .field-icon { position: absolute; left: 12px; color: var(--text-muted); font-size: 0.88rem; pointer-events: none; }
        .form-group input {
            width: 100%; padding: 10px 12px 10px 36px; background-color: var(--input-bg);
            border: 1px solid var(--input-border); border-radius: 8px; font-size: 0.88rem; color: var(--text-main);
            transition: all 0.2s ease;
        }
        .form-group input:focus { border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.18); outline: none; }

        .btn-submit {
            background: linear-gradient(90deg, #1d4ed8 0%, #2563eb 100%); color: #ffffff; border: none; padding: 11px;
            border-radius: 8px; cursor: pointer; font-weight: 700; font-size: 0.88rem; width: 100%;
            box-shadow: 0 4px 14px rgba(37, 99, 235, 0.3); transition: all 0.2s ease;
            display: flex; justify-content: center; align-items: center; gap: 7px;
        }
        .btn-submit:hover { background: linear-gradient(90deg, #1e40af 0%, #1d4ed8 100%); transform: translateY(-1px); }
        .btn-green { background: linear-gradient(90deg, #059669 0%, #10b981 100%); box-shadow: 0 4px 14px rgba(16, 185, 129, 0.3); }
        .btn-green:hover { background: linear-gradient(90deg, #047857 0%, #059669 100%); }

        .or-divider { text-align: center; position: relative; margin: 14px 0; }
        .or-divider::before { content: ""; position: absolute; left: 0; top: 50%; width: 100%; height: 1px; background-color: var(--divider-color); }
        .or-divider span { background-color: var(--bg-card-right); position: relative; padding: 0 10px; font-size: 0.74rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase; }

        .back-link-row { margin-top: 16px; border-top: 1px solid var(--divider-color); padding-top: 14px; text-align: center; }
        .back-link-row a { font-size: 0.82rem; color: #2563eb; text-decoration: none; font-weight: 600; display: inline-flex; align-items: center; gap: 6px; }
        .back-link-row a:hover { text-decoration: underline; }

        @keyframes fadeIn { from { opacity: 0; transform: translateY(4px); } to { opacity: 1; transform: translateY(0); } }

        @media (max-width: 720px) {
            .login-split-wrapper { flex-direction: column; max-width: 440px; }
            .login-brand-panel { padding: 26px 18px; }
            .brand-logo-card { width: 88px; height: 88px; margin-bottom: 12px; }
            .brand-logo-card img { width: 58px; height: 58px; }
            .login-form-panel { padding: 26px 20px; }
        }
    </style>
</head>
<body>

    <div class="login-split-wrapper">
        <div class="login-brand-panel">
            <div class="brand-logo-card">
                <img src="logo_derecha.png" alt="Logo Escuela" onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                <i class="fas fa-school" style="display:none; font-size: 2.6rem; color: #1e3a8a;"></i>
            </div>
            <h1 class="brand-title">CENTRO DE RECUPERACIÓN</h1>
            <p class="brand-subtitle">ESCUELA GLADYS CELINA LOBO DE CARNEVALI</p>
            <p class="brand-tagline">Restablecimiento seguro de credenciales y acceso escolar</p>
        </div>

        <div class="login-form-panel">
            <button type="button" class="theme-toggle-btn" id="themeToggleBtn" onclick="toggleTheme()" title="Cambiar Modo Claro / Oscuro">
                <i class="fas fa-moon" id="themeIcon"></i>
            </button>

            <div class="form-header">
                <h2>Recuperar Cuenta</h2>
                <p>Seleccione la opción correspondiente a los datos que desea consultar o actualizar</p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>

            <?php if (!empty($mensaje)): ?>
                <div class="alert-success">
                    <i class="fas fa-check-circle"></i>
                    <span><?php echo $mensaje; ?></span>
                </div>
            <?php endif; ?>

            <div class="tabs-header">
                <button type="button" class="tab-btn active" onclick="switchTab(event, 'tabUsuario')">
                    <i class="fas fa-user-tag"></i> Olvidé mi Usuario
                </button>
                <button type="button" class="tab-btn" onclick="switchTab(event, 'tabPassword')">
                    <i class="fas fa-key"></i> Olvidé mi Clave
                </button>
            </div>

            <div id="tabUsuario" class="tab-content active">
                <form method="POST" action="recuperar.php">
                    <div class="form-group">
                        <label>Opción 1: Consulta instantánea por PIN Secreto</label>
                        <div class="input-icon-wrap">
                            <i class="fas fa-shield-alt field-icon"></i>
                            <input type="password" name="pin" maxlength="100" required placeholder="Ingrese su PIN secreto registrado">
                        </div>
                    </div>
                    <button type="submit" name="recuperar_por_pin" class="btn-submit">
                        <i class="fas fa-search"></i> Consultar Usuario por PIN
                    </button>
                </form>

                <div class="or-divider">
                    <span>O también</span>
                </div>

                <form method="POST" action="recuperar.php">
                    <div class="form-group">
                        <label>Opción 2: Enviar a mi correo electrónico</label>
                        <div class="input-icon-wrap">
                            <i class="fas fa-envelope field-icon"></i>
                            <input type="email" name="correo" maxlength="100" required placeholder="correo@ejemplo.com">
                        </div>
                    </div>
                    <button type="submit" name="recuperar_por_correo" class="btn-submit btn-green">
                        <i class="fas fa-paper-plane"></i> Enviar Usuario por Correo
                    </button>
                </form>
            </div>

            <div id="tabPassword" class="tab-content">
                <form method="POST" action="recuperar.php">
                    <div class="form-group">
                        <label>Nombre de Usuario</label>
                        <div class="input-icon-wrap">
                            <i class="fas fa-user field-icon"></i>
                            <input type="text" name="usuario" maxlength="50" required placeholder="Su nombre de usuario">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Dato de Verificación (Correo o PIN Secreto)</label>
                        <div class="input-icon-wrap">
                            <i class="fas fa-id-badge field-icon"></i>
                            <input type="text" name="identificador" maxlength="100" required placeholder="Su correo o PIN registrado">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Nueva Contraseña (ej. Pedro.24)</label>
                        <div class="input-icon-wrap">
                            <i class="fas fa-lock field-icon"></i>
                            <input type="password" name="nueva_clave" maxlength="255" required placeholder="Mayúscula, números y símbolo">
                        </div>
                    </div>

                    <button type="submit" name="restablecer_password" class="btn-submit">
                        <i class="fas fa-check-circle"></i> Restablecer Contraseña
                    </button>
                </form>
            </div>

            <div class="back-link-row">
                <a href="procesar.php">
                    <i class="fas fa-arrow-left"></i> Volver a Iniciar Sesión
                </a>
            </div>
        </div>
    </div>

    <script>
        function applySavedTheme() {
            const savedTheme = localStorage.getItem('sige_theme');
            const themeIcon = document.getElementById('themeIcon');
            if (savedTheme === 'dark') {
                document.body.classList.add('dark-mode');
                if (themeIcon) {
                    themeIcon.classList.remove('fa-moon');
                    themeIcon.classList.add('fa-sun');
                }
            }
        }
        applySavedTheme();

        function toggleTheme() {
            const isDark = document.body.classList.toggle('dark-mode');
            const themeIcon = document.getElementById('themeIcon');
            localStorage.setItem('sige_theme', isDark ? 'dark' : 'light');
            if (isDark) {
                themeIcon.classList.remove('fa-moon');
                themeIcon.classList.add('fa-sun');
            } else {
                themeIcon.classList.remove('fa-sun');
                themeIcon.classList.add('fa-moon');
            }
        }

        function switchTab(evt, tabName) {
            let contents = document.getElementsByClassName("tab-content");
            for (let c of contents) { c.classList.remove("active"); }
            let buttons = document.getElementsByClassName("tab-btn");
            for (let b of buttons) { b.classList.remove("active"); }
            document.getElementById(tabName).classList.add("active");
            evt.currentTarget.classList.add("active");
        }
    </script>
</body>
</html>