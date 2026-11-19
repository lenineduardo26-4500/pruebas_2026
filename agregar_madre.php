<?php
session_start();
// Configuración de seguridad para sesiones y manejo de errores limpio
if (isset($_SESSION['usuario'])) {
    if (!isset($_SESSION['init'])) {
        session_regenerate_id(true);
        $_SESSION['init'] = true;
    }
}
error_reporting(0);

if (isset($_POST['guardar_representante'])) {
    $_SESSION['representante'] = $_POST;
}

$error = "";

if (isset($_POST['guardar_madre'])) {
    $no_aplica = isset($_POST['no_aplica_madre']) && $_POST['no_aplica_madre'] == '1';
    if ($no_aplica) {
        $_SESSION['madre'] = ['no_aplica_madre' => '1'];
        header("Location: agregar_padre.php");
        exit();
    } else {
        $nac = $_POST['nacionalidad_cedula_madre'] ?? 'V';
        $num = trim($_POST['numero_cedula_madre'] ?? '');
        $_POST['ci_madre'] = !empty($num) ? $nac . '-' . $num : '';
        if (!empty(trim($_POST['nombres_apellidos_madre'])) && !empty($num)) {
            $_SESSION['madre'] = $_POST;
            header("Location: agregar_padre.php");
            exit();
        } else {
            $error = "Debe completar al menos los Nombres y la Cédula de la madre, o marcar la casilla si no aplica.";
        }
    }
}

include("conexion.php");
$es_administrador = isset($_SESSION['rol']) && $_SESSION['rol'] === 'Administrador';
$rol_actual = $_SESSION['rol'] ?? 'Administrador';
$madre_no_aplica = (($_SESSION['madre']['no_aplica_madre'] ?? '') == '1');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrar Madre - Sistema de Inscripción</title>
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
        body.dark-mode .top-header-bar {
            background-color: var(--bg-surface) !important;
            border-color: var(--border-color) !important;
            color: var(--text-primary) !important;
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
        .card h3 { font-size: 1.15rem; font-weight: 700; margin-bottom: 5px; color: var(--text-primary); }
        .alert-error { background-color: #f8d7da; color: #721c24; padding: 10px; border-radius: 6px; margin-bottom: 15px; font-size: 0.88rem; border: 1px solid #f5c6cb; }
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 10px 12px; margin-bottom: 15px; }
        .form-group { display: flex; flex-direction: column; }
        .form-group label { font-size: 0.82rem; font-weight: 600; margin-bottom: 3px; color: var(--text-secondary); }
        .form-group input, .form-group select, .form-group textarea { padding: 5px 8px; border: 1px solid var(--border-color); border-radius: 8px; font-size: 0.85rem; width: 100%; box-sizing: border-box; background-color: var(--bg-surface); color: var(--text-primary); height: 32px; }
        .cedula-group { display: flex; gap: 5px; }
        .cedula-group select { width: 75px; flex-shrink: 0; }
        .btn-container { display: flex; justify-content: space-between; margin-top: 15px; }
        .btn-prev { background-color: #64748b; color: white; border: none; padding: 8px 20px; border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 0.88rem; text-decoration: none; display: inline-flex; align-items: center; }
        .btn-prev:hover { opacity: 0.9; color: white; }
        .btn-next { background-color: #2563eb; color: white; border: none; padding: 8px 20px; border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 0.88rem; transition: background-color 0.2s; }
        .btn-next:hover { background-color: #1d4ed8; }

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
                <i class="fas fa-female" style="color: #2563eb;"></i>
                <span>Registrar Madre</span>
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
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                <h3 style="margin: 0;"><i class="fas fa-female text-primary" style="color: #2563eb;"></i> Paso 3: Datos de la Madre</h3>
                <a href="limpiar_sesion.php?redirect=agregar_madre.php" onclick="return confirm('¿Estás seguro de limpiar todos los datos del formulario?');" style="background-color: #ef4444; color: white; padding: 5px 12px; border-radius: 6px; text-decoration: none; font-size: 0.82rem; font-weight: 600;">
                    <i class="fas fa-trash-alt"></i> Limpiar Formulario
                </a>
            </div>
            <hr style="margin-bottom: 15px; border: 0; border-top: 1px solid var(--border-color);">

            <?php if (!empty($error)): ?>
                <div class="alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
            <?php endif; ?>

            <form action="" method="POST">
                <div style="margin-bottom: 15px; padding: 10px 12px; background-color: rgba(37, 99, 235, 0.1); border: 1px solid rgba(37, 99, 235, 0.2); border-radius: 8px;">
                    <label style="display: flex; align-items: center; gap: 8px; font-size: 0.88rem; font-weight: 600; color: var(--text-primary); cursor: pointer; margin: 0;">
                        <input type="checkbox" name="no_aplica_madre" id="no_aplica_madre" value="1" <?php echo $madre_no_aplica ? 'checked' : ''; ?> onchange="toggleCamposMadre()" style="width: 16px; height: 16px; cursor: pointer;">
                        No aplica / Sin datos de la madre (Continuar sin llenar este paso)
                    </label>
                </div>

                <div class="form-grid" id="campos_madre_grid">
                    <div class="form-group" style="grid-column: span 2;">
                        <label>Nombres y Apellidos (*):</label>
                        <input type="text" name="nombres_apellidos_madre" id="nombres_apellidos_madre" maxlength="50" onkeypress="return soloLetrasYEspacios(event)" oninput="limpiarLetras(this)" pattern="[A-Za-zÁÉÍÓÚáéíóúñÑ\s]+" title="Solo letras y espacios" value="<?php echo htmlspecialchars($_SESSION['madre']['nombres_apellidos_madre'] ?? ''); ?>" required>
                    </div>

                    <div class="form-group">
                        <label>C.I. (Cédula de Identidad) (*):</label>
                        <div class="cedula-group">
                            <select name="nacionalidad_cedula_madre">
                                <option value="V" <?php echo (($_SESSION['madre']['nacionalidad_cedula_madre'] ?? 'V') == 'V') ? 'selected' : ''; ?>>V-</option>
                                <option value="E" <?php echo (($_SESSION['madre']['nacionalidad_cedula_madre'] ?? '') == 'E') ? 'selected' : ''; ?>>E-</option>
                            </select>
                            <input type="text" name="numero_cedula_madre" id="numero_cedula_madre" placeholder="123456789" maxlength="9" onkeypress="return soloNumeros(event)" oninput="limpiarNumeros(this)" pattern="[0-9]{1,9}" title="Ingrese solo números (máximo 9 dígitos)" value="<?php echo htmlspecialchars($_SESSION['madre']['numero_cedula_madre'] ?? ''); ?>" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Teléfono:</label>
                        <input type="text" name="telefono_madre" maxlength="13" onkeypress="return soloTelefonos(event)" oninput="limpiarTelefonos(this)" pattern="[0-9+\-\s]*" title="Solo números y caracteres permitidos" value="<?php echo htmlspecialchars($_SESSION['madre']['telefono_madre'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label>Fecha de Nacimiento:</label>
                        <input type="date" name="fecha_nacimiento_madre" min="1925-01-01" max="<?php echo date('Y-m-d'); ?>" value="<?php echo htmlspecialchars($_SESSION['madre']['fecha_nacimiento_madre'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label>Estado Civil:</label>
                        <input type="text" name="estado_civil_madre" maxlength="15" onkeypress="return soloLetrasYEspacios(event)" oninput="limpiarLetras(this)" pattern="[A-Za-zÁÉÍÓÚáéíóúñÑ\s]*" title="Solo letras" value="<?php echo htmlspecialchars($_SESSION['madre']['estado_civil_madre'] ?? ''); ?>">
                    </div>

                    <div class="form-group" style="grid-column: span 2;">
                        <label>Lugar de Nacimiento:</label>
                        <input type="text" name="lugar_nacimiento_madre" maxlength="40" onkeypress="return soloLetrasYEspacios(event)" oninput="limpiarLetras(this)" pattern="[A-Za-zÁÉÍÓÚáéíóúñÑ\s]*" title="Solo letras y espacios" value="<?php echo htmlspecialchars($_SESSION['madre']['lugar_nacimiento_madre'] ?? ''); ?>">
                    </div>

                    <div class="form-group" style="grid-column: span 2;">
                        <label>Correo:</label>
                        <input type="email" name="correo_madre" maxlength="40" value="<?php echo htmlspecialchars($_SESSION['madre']['correo_madre'] ?? ''); ?>">
                    </div>

                    <div class="form-group" style="grid-column: span 2;">
                        <label>Dirección de Habitación:</label>
                        <input type="text" name="direccion_habitacion_madre" maxlength="100" value="<?php echo htmlspecialchars($_SESSION['madre']['direccion_habitacion_madre'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label>Nivel de Instrucción:</label>
                        <input type="text" name="nivel_instruccion_madre" maxlength="30" onkeypress="return soloLetrasYEspacios(event)" oninput="limpiarLetras(this)" pattern="[A-Za-zÁÉÍÓÚáéíóúñÑ\s]*" title="Solo letras" value="<?php echo htmlspecialchars($_SESSION['madre']['nivel_instruccion_madre'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label>Profesión:</label>
                        <input type="text" name="profesion_madre" maxlength="30" onkeypress="return soloLetrasYEspacios(event)" oninput="limpiarLetras(this)" pattern="[A-Za-zÁÉÍÓÚáéíóúñÑ\s]*" title="Solo letras" value="<?php echo htmlspecialchars($_SESSION['madre']['profesion_madre'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label>Ocupación:</label>
                        <input type="text" name="ocupacion_madre" maxlength="30" onkeypress="return soloLetrasYEspacios(event)" oninput="limpiarLetras(this)" pattern="[A-Za-zÁÉÍÓÚáéíóúñÑ\s]*" title="Solo letras" value="<?php echo htmlspecialchars($_SESSION['madre']['ocupacion_madre'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label>Empresa:</label>
                        <input type="text" name="empresa_madre" maxlength="30" onkeypress="return soloLetrasYEspacios(event)" oninput="limpiarLetras(this)" pattern="[A-Za-zÁÉÍÓÚáéíóúñÑ\s]*" title="Solo letras" value="<?php echo htmlspecialchars($_SESSION['madre']['empresa_madre'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label>Teléf. de Empresa:</label>
                        <input type="text" name="telefono_empresa_madre" maxlength="13" onkeypress="return soloTelefonos(event)" oninput="limpiarTelefonos(this)" pattern="[0-9+\-\s]*" title="Solo números y caracteres permitidos" value="<?php echo htmlspecialchars($_SESSION['madre']['telefono_empresa_madre'] ?? ''); ?>">
                    </div>

                    <div class="form-group" style="grid-column: span 2;">
                        <label>Dirección de la Empresa:</label>
                        <input type="text" name="direccion_empresa_madre" maxlength="50" value="<?php echo htmlspecialchars($_SESSION['madre']['direccion_empresa_madre'] ?? ''); ?>">
                    </div>
                </div>

                <div class="btn-container">
                    <a href="agregar_representante.php" class="btn-prev"><i class="fas fa-arrow-left"></i> Anterior</a>
                    <button type="submit" name="guardar_madre" class="btn-next">Siguiente <i class="fas fa-arrow-right"></i></button>
                </div>
            </form>
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

        function soloNumeros(e) {
            var key = e.charCode || e.keyCode;
            return (key >= 48 && key <= 57);
        }

        function soloLetrasYEspacios(e) {
            var key = e.charCode || e.keyCode;
            var letra = String.fromCharCode(key);
            var patron = /^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]$/;
            return patron.test(letra);
        }

        function soloTelefonos(e) {
            var key = e.charCode || e.keyCode;
            var letra = String.fromCharCode(key);
            return ((key >= 48 && key <= 57) || letra === '+' || letra === '-' || letra === ' ');
        }

        function limpiarLetras(campo) {
            campo.value = campo.value.replace(/[^a-zA-ZáéíóúÁÉÍÓÚñÑ\s]/g, '');
        }

        function limpiarNumeros(campo) {
            campo.value = campo.value.replace(/[^0-9]/g, '');
        }

        function limpiarTelefonos(campo) {
            campo.value = campo.value.replace(/[^0-9+\-\s]/g, '');
        }

        function toggleCamposMadre() {
            const chk = document.getElementById('no_aplica_madre');
            const grid = document.getElementById('campos_madre_grid');
            const inputs = grid.querySelectorAll('input, select, textarea');
            if (chk.checked) {
                grid.style.opacity = '0.5';
                inputs.forEach(el => {
                    el.removeAttribute('required');
                    el.disabled = true;
                });
            } else {
                grid.style.opacity = '1';
                inputs.forEach(el => {
                    el.disabled = false;
                });
                document.getElementById('nombres_apellidos_madre').setAttribute('required', 'required');
                document.getElementById('numero_cedula_madre').setAttribute('required', 'required');
            }
        }
        window.addEventListener('DOMContentLoaded', toggleCamposMadre);
    </script>
</body>
</html>