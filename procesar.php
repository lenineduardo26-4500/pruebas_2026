<?php
session_start();
include("conexion.php");
if ($conexion instanceof mysqli) {
    mysqli_set_charset($conexion, "utf8mb4");
}

if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    $usuario_actual = $_SESSION['usuario'] ?? 'Desconocido';
    if ($conexion instanceof PDO) {
        try {
            $stmt_h = $conexion->prepare("INSERT INTO historial (descripcion, usuario, fecha_hora) VALUES (:desc, :usu, :fec)");
            $stmt_h->execute([':desc' => 'Cierre de sesión de usuario', ':usu' => $usuario_actual, ':fec' => date('Y-m-d H:i:s')]);
        } catch (Exception $e) {}
    } else {
        @mysqli_query($conexion, "INSERT INTO historial (descripcion, usuario, fecha_hora) VALUES ('Cierre de sesión de usuario', '" . mysqli_real_escape_string($conexion, $usuario_actual) . "', '" . date('Y-m-d H:i:s') . "')");
    }

    session_unset();
    session_destroy();
    header("Location: procesar.php?logout=1");
    exit();
}

function registrarHistorialLogin($conexion, $descripcion, $usuario) {
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

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = trim($_POST['usuario'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (!empty($usuario) && !empty($password)) {
        if ($conexion instanceof PDO) {
            $stmt = $conexion->prepare("SELECT * FROM usuarios WHERE usuario = :usuario LIMIT 1");
            $stmt->execute([':usuario' => $usuario]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                $clave_bd = $user['clave'] ?? '';
                if (!empty($clave_bd) && ($password === $clave_bd || password_verify($password, $clave_bd))) {
                    
                    // VALIDACIÓN: Bloquear si el usuario está inactivo
                    $estado_usuario = $user['estado'] ?? 'Activo';
                    if ($estado_usuario === 'Inactivo') {
                        $error = "Acceso denegado. Su cuenta se encuentra inactiva.";
                    } else {
                        $_SESSION['autenticado'] = true;
                        $_SESSION['usuario'] = $user['usuario'];
                        $_SESSION['rol'] = $user['rol'] ?? 'Administrador';
                        
                        registrarHistorialLogin($conexion, "Inicio de sesión exitoso", $user['usuario']);
                        header("Location: index.php");
                        exit();
                    }

                } else {
                    $error = "Usuario o contraseña incorrectos.";
                }
            } else {
                $error = "El usuario ingresado no existe.";
            }
        } else {
            $usuario_limpio = mysqli_real_escape_string($conexion, $usuario);
            $sql = "SELECT * FROM usuarios WHERE usuario = '$usuario_limpio' LIMIT 1";
            $resultado = mysqli_query($conexion, $sql);

            if ($resultado && mysqli_num_rows($resultado) > 0) {
                $user = mysqli_fetch_assoc($resultado);
                $clave_bd = $user['clave'] ?? '';

                if (!empty($clave_bd) && ($password === $clave_bd || password_verify($password, $clave_bd))) {
                    
                    // VALIDACIÓN: Bloquear si el usuario está inactivo
                    $estado_usuario = $user['estado'] ?? 'Activo';
                    if ($estado_usuario === 'Inactivo') {
                        $error = "Acceso denegado. Su cuenta se encuentra inactiva.";
                    } else {
                        $_SESSION['autenticado'] = true;
                        $_SESSION['usuario'] = $user['usuario'];
                        $_SESSION['rol'] = $user['rol'] ?? 'Administrador';
                        
                        registrarHistorialLogin($conexion, "Inicio de sesión exitoso", $user['usuario']);
                        header("Location: index.php");
                        exit();
                    }

                } else {
                    $error = "Usuario o contraseña incorrectos.";
                }
            } else {
                $error = "El usuario ingresado no existe.";
            }
        }
    } else {
        $error = "Por favor, complete todos los campos.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - Sistema de Inscripción</title>
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
            --btn-sec-bg: #f1f5f9;
            --btn-sec-text: #334155;
            --btn-sec-border: #e2e8f0;
        }

        body.dark-mode {
            --bg-card-right: #1e293b;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --input-bg: #0f172a;
            --input-border: #334155;
            --divider-color: #334155;
            --card-shadow: 0 24px 50px rgba(0, 0, 0, 0.75);
            --btn-sec-bg: #0f172a;
            --btn-sec-text: #e2e8f0;
            --btn-sec-border: #334155;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 20px;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            background-color: #162338;
            background-image: 
                linear-gradient(135deg, rgba(15, 23, 42, 0.82) 0%, rgba(30, 58, 138, 0.78) 100%),
                url('imagenes/fondo.jpg'), 
                url('imagenes/fondo.jpeg');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            background-attachment: fixed;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }

        .login-split-wrapper {
            display: flex;
            width: 100%;
            max-width: 830px;
            min-height: 500px;
            border-radius: 18px;
            overflow: hidden;
            box-shadow: var(--card-shadow);
            border: 1px solid rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(4px);
        }

        .login-brand-panel {
            flex: 0 0 45%;
            background: linear-gradient(145deg, #0b5ed7 0%, #1e3a8a 60%, #172554 100%);
            color: #ffffff;
            padding: 40px 28px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .login-brand-panel::before {
            content: "";
            position: absolute;
            width: 260px;
            height: 260px;
            background: radial-gradient(circle, rgba(255,255,255,0.12) 0%, transparent 70%);
            top: -80px;
            left: -80px;
            border-radius: 50%;
        }

        .login-brand-panel::after {
            content: "";
            position: absolute;
            width: 220px;
            height: 220px;
            background: radial-gradient(circle, rgba(59,130,246,0.25) 0%, transparent 70%);
            bottom: -60px;
            right: -60px;
            border-radius: 50%;
        }

        .brand-logo-card {
            background: #ffffff;
            width: 130px;
            height: 130px;
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 12px;
            margin-bottom: 20px;
            box-shadow: 0 12px 28px rgba(0, 0, 0, 0.28);
            position: relative;
            z-index: 2;
        }

        .brand-logo-card img {
            width: 88px;
            height: 88px;
            object-fit: contain;
        }

        .brand-title {
            font-size: 1.35rem;
            font-weight: 800;
            letter-spacing: 0.5px;
            margin: 0 0 10px 0;
            position: relative;
            z-index: 2;
            text-transform: uppercase;
            line-height: 1.25;
        }

        .brand-subtitle {
            font-size: 0.86rem;
            line-height: 1.45;
            color: #ffffff;
            font-weight: 700;
            margin: 0 0 6px 0;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            position: relative;
            z-index: 2;
        }

        .brand-tagline {
            font-size: 0.78rem;
            line-height: 1.4;
            color: rgba(255, 255, 255, 0.85);
            margin: 0;
            position: relative;
            z-index: 2;
        }

        .login-form-panel {
            flex: 1;
            background-color: var(--bg-card-right);
            padding: 38px 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            position: relative;
            transition: background-color 0.25s ease;
        }

        .theme-toggle-btn {
            position: absolute;
            top: 20px;
            right: 22px;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            border: 1px solid var(--input-border);
            background: var(--input-bg);
            color: var(--text-muted);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 0.95rem;
            transition: all 0.2s ease;
        }

        .theme-toggle-btn:hover {
            color: #2563eb;
            border-color: #2563eb;
            transform: scale(1.06);
        }

        .form-header h2 {
            margin: 0 0 6px 0;
            font-size: 1.55rem;
            font-weight: 700;
            color: var(--text-main);
        }

        .role-access-note {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--text-muted);
            margin-bottom: 22px;
        }

        .role-access-note i {
            color: #2563eb;
        }

        .alert-error {
            background-color: rgba(239, 68, 68, 0.12);
            color: #ef4444;
            padding: 11px 14px;
            border-radius: 9px;
            margin-bottom: 18px;
            font-size: 0.84rem;
            font-weight: 600;
            border: 1px solid rgba(239, 68, 68, 0.3);
            border-left: 4px solid #ef4444;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .form-group {
            margin-bottom: 16px;
            text-align: left;
        }

        .form-group label {
            display: block;
            font-size: 0.82rem;
            font-weight: 600;
            margin-bottom: 6px;
            color: var(--text-main);
        }

        .input-icon-wrap {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-icon-wrap .field-icon {
            position: absolute;
            left: 13px;
            color: var(--text-muted);
            font-size: 0.9rem;
            pointer-events: none;
        }

        .form-group input[type="text"],
        .form-group input[type="password"] {
            width: 100%;
            padding: 11px 38px 11px 38px;
            background-color: var(--input-bg);
            border: 1px solid var(--input-border);
            border-radius: 9px;
            font-size: 0.9rem;
            color: var(--text-main);
            transition: all 0.2s ease;
        }

        .form-group input:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.18);
            outline: none;
        }

        .toggle-password {
            position: absolute;
            right: 12px;
            background: none;
            border: none;
            color: var(--text-muted);
            cursor: pointer;
            font-size: 0.95rem;
            padding: 4px;
        }

        .toggle-password:hover {
            color: #2563eb;
        }

        .show-pass-row {
            display: flex;
            align-items: center;
            gap: 7px;
            margin: -4px 0 16px 0;
            font-size: 0.8rem;
            color: var(--text-muted);
            cursor: pointer;
            user-select: none;
        }

        .show-pass-row input[type="checkbox"] {
            accent-color: #2563eb;
            cursor: pointer;
            width: 15px;
            height: 15px;
        }

        .btn-login {
            background: linear-gradient(90deg, #1d4ed8 0%, #2563eb 100%);
            color: #ffffff;
            border: none;
            padding: 12px;
            border-radius: 9px;
            cursor: pointer;
            font-weight: 700;
            font-size: 0.92rem;
            width: 100%;
            box-shadow: 0 6px 16px rgba(37, 99, 235, 0.32);
            transition: all 0.2s ease;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
        }

        .btn-login:hover {
            background: linear-gradient(90deg, #1e40af 0%, #1d4ed8 100%);
            transform: translateY(-1px);
            box-shadow: 0 8px 20px rgba(37, 99, 235, 0.42);
        }

        .btn-login:disabled {
            opacity: 0.75;
            cursor: not-allowed;
            transform: none;
        }

        .portal-divider {
            margin: 18px 0 0 0;
            border-top: 1px solid var(--divider-color);
            padding-top: 16px;
        }

        .btn-secondary-portal {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            padding: 10px 12px;
            border-radius: 8px;
            font-size: 0.84rem;
            font-weight: 600;
            text-decoration: none;
            background-color: var(--btn-sec-bg);
            color: var(--btn-sec-text);
            border: 1px solid var(--btn-sec-border);
            transition: all 0.2s ease;
        }

        .btn-secondary-portal:hover {
            border-color: #2563eb;
            color: #2563eb;
        }

        .swal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.55);
            backdrop-filter: blur(2px);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            animation: fadeIn 0.2s ease;
        }

        .swal-modal {
            background: #ffffff;
            color: #1e293b;
            width: 90%;
            max-width: 360px;
            border-radius: 14px;
            padding: 28px 24px 22px 24px;
            text-align: center;
            box-shadow: 0 20px 45px rgba(0, 0, 0, 0.3);
        }

        .swal-icon-info {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            border: 3px solid #38bdf8;
            color: #0284c7;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.9rem;
            font-weight: 700;
            margin: 0 auto 14px auto;
        }

        .swal-title {
            font-size: 1.25rem;
            font-weight: 700;
            margin: 0 0 6px 0;
            color: #0f172a;
        }

        .swal-text {
            font-size: 0.9rem;
            color: #64748b;
            margin: 0 0 20px 0;
        }

        .swal-btn-ok {
            background: #2563eb;
            color: #ffffff;
            border: none;
            padding: 9px 28px;
            border-radius: 7px;
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
        }

        .swal-btn-ok:hover {
            background: #1d4ed8;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @media (max-width: 720px) {
            .login-split-wrapper {
                flex-direction: column;
                max-width: 420px;
            }
            .login-brand-panel {
                padding: 28px 20px;
            }
            .brand-logo-card {
                width: 96px;
                height: 96px;
                margin-bottom: 14px;
            }
            .brand-logo-card img {
                width: 64px;
                height: 64px;
            }
            .login-form-panel {
                padding: 28px 24px;
            }
        }
    </style>
</head>
<body>

    <?php if (isset($_GET['logout']) && $_GET['logout'] == '1'): ?>
    <div class="swal-overlay" id="logoutModal">
        <div class="swal-modal">
            <div class="swal-icon-info">i</div>
            <h3 class="swal-title">Información</h3>
            <p class="swal-text">Sesión cerrada correctamente.</p>
            <button type="button" class="swal-btn-ok" onclick="cerrarModalLogout()">OK</button>
        </div>
    </div>
    <?php endif; ?>

    <div class="login-split-wrapper">
        <div class="login-brand-panel">
            <div class="brand-logo-card">
                <img src="logo_derecha.png" alt="Logo Escuela" onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                <i class="fas fa-school" style="display:none; font-size: 3rem; color: #1e3a8a;"></i>
            </div>
            <h1 class="brand-title">SISTEMA DE INSCRIPCIÓN</h1>
            <p class="brand-subtitle">ESCUELA GLADYS CELINA LOBO DE CARNEVALI</p>
            <p class="brand-tagline">Conectando la gestión escolar, control estudiantil e inscripción</p>
        </div>

        <div class="login-form-panel">
            <button type="button" class="theme-toggle-btn" id="themeToggleBtn" onclick="toggleTheme()" title="Cambiar Modo Claro / Oscuro">
                <i class="fas fa-moon" id="themeIcon"></i>
            </button>

            <div class="form-header">
                <h2>Iniciar Sesión</h2>
                <div class="role-access-note">
                    <i class="fas fa-user-shield"></i>
                    <span>Acceso para Personal Administrativo y Docente</span>
                </div>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" action="procesar.php" id="loginForm" onsubmit="mostrarCargaLogin()">
                <div class="form-group">
                    <label for="usuarioInput">Usuario o Correo Institucional</label>
                    <div class="input-icon-wrap">
                        <i class="fas fa-user field-icon"></i>
                        <input type="text" name="usuario" id="usuarioInput" maxlength="50" required placeholder="Ingrese su usuario" autocomplete="username">
                    </div>
                </div>

                <div class="form-group">
                    <label for="password">Contraseña de acceso</label>
                    <div class="input-icon-wrap">
                        <i class="fas fa-lock field-icon"></i>
                        <input type="password" name="password" id="password" maxlength="255" required placeholder="••••••••" autocomplete="current-password">
                        <button type="button" class="toggle-password" id="togglePasswordBtn" onclick="togglePasswordVisibility()" title="Mostrar / Ocultar contraseña">
                            <i class="fas fa-eye" id="eyeIcon"></i>
                        </button>
                    </div>
                </div>

                <label class="show-pass-row">
                    <input type="checkbox" id="showPassCheckbox" onchange="togglePasswordCheckbox(this)">
                    <span>Mostrar contraseña</span>
                </label>

                <button type="submit" class="btn-login" id="btnSubmitLogin">
                    <i class="fas fa-sign-in-alt" id="btnLoginIcon"></i>
                    <span id="btnLoginText">Ingresar al Sistema</span>
                </button>
            </form>

            <div class="portal-divider">
                <a href="recuperar.php" class="btn-secondary-portal">
                    <i class="fas fa-life-ring text-primary"></i> ¿Olvidaste tus datos? Centro de Recuperación
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

        function togglePasswordVisibility() {
            const passwordInput = document.getElementById('password');
            const eyeIcon = document.getElementById('eyeIcon');
            const checkbox = document.getElementById('showPassCheckbox');

            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                eyeIcon.classList.remove('fa-eye');
                eyeIcon.classList.add('fa-eye-slash');
                if (checkbox) checkbox.checked = true;
            } else {
                passwordInput.type = 'password';
                eyeIcon.classList.remove('fa-eye-slash');
                eyeIcon.classList.add('fa-eye');
                if (checkbox) checkbox.checked = false;
            }
        }

        function togglePasswordCheckbox(cb) {
            const passwordInput = document.getElementById('password');
            const eyeIcon = document.getElementById('eyeIcon');
            if (cb.checked) {
                passwordInput.type = 'text';
                eyeIcon.classList.remove('fa-eye');
                eyeIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                eyeIcon.classList.remove('fa-eye-slash');
                eyeIcon.classList.add('fa-eye');
            }
        }

        function mostrarCargaLogin() {
            const btn = document.getElementById('btnSubmitLogin');
            const icon = document.getElementById('btnLoginIcon');
            const text = document.getElementById('btnLoginText');
            if (btn && icon && text) {
                btn.disabled = true;
                icon.className = 'fas fa-circle-notch fa-spin';
                text.textContent = 'Validando credenciales...';
            }
        }

        function cerrarModalLogout() {
            const modal = document.getElementById('logoutModal');
            if (modal) {
                modal.style.display = 'none';
            }
            if (window.history.replaceState) {
                window.history.replaceState(null, null, window.location.pathname);
            }
        }
    </script>
</body>
</html>