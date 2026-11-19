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

if (isset($_POST['guardar_estudiante'])) {
    $nac_est = $_POST['nacionalidad_cedula'] ?? 'V';
    $num_est = trim($_POST['numero_cedula_alumno'] ?? ($_POST['numero_cedula_ alumno'] ?? ($_POST['numero_cedula__alumno'] ?? '')));
    $_POST['numero_cedula_alumno'] = $num_est;
    $_POST['numero_cedula_ alumno'] = $num_est;
    $_POST['cedula_alumno'] = !empty($num_est) ? $nac_est . '-' . $num_est : '';
    $_SESSION['estudiante'] = array_merge($_SESSION['estudiante'] ?? [], $_POST);
}

if (isset($_POST['guardar_representante'])) {
    $nac = $_POST['nacionalidad_cedula_rep'] ?? 'V';
    $num = trim($_POST['numero_cedula_rep'] ?? '');
    $_POST['cedula_identidad'] = !empty($num) ? $nac . '-' . $num : '';
    
    $parentesco_val = $_POST['parentesco_representante'] ?? '';
    if ($parentesco_val === 'Mamá') {
        $_SESSION['madre'] = [
            'no_aplica_madre'             => '0',
            'nombres_apellidos_madre'     => $_POST['nombre_representante'] ?? '',
            'nacionalidad_cedula_madre'   => $nac,
            'numero_cedula_madre'         => $num,
            'ci_madre'                    => $_POST['cedula_identidad'],
            'telefono_madre'              => $_POST['telefono_representante'] ?? '',
            'fecha_nacimiento_madre'      => $_POST['fecha_nacimiento_representante'] ?? '',
            'estado_civil_madre'          => $_SESSION['madre']['estado_civil_madre'] ?? '',
            'lugar_nacimiento_madre'      => $_SESSION['madre']['lugar_nacimiento_madre'] ?? '',
            'correo_madre'                => $_POST['correo_electronico_representante'] ?? '',
            'direccion_habitacion_madre'  => $_POST['direccion_habitacion_representante'] ?? '',
            'nivel_instruccion_madre'     => $_POST['nivel_instruccion_representante'] ?? '',
            'profesion_madre'             => $_POST['profesion_representante'] ?? '',
            'ocupacion_madre'             => $_POST['ocupacion_representante'] ?? '',
            'empresa_madre'               => $_POST['empresa_representante'] ?? '',
            'telefono_empresa_madre'      => $_POST['telefono_empresa_representante'] ?? '',
            'direccion_empresa_madre'     => $_POST['direccion_empresa_representante'] ?? ''
        ];
    } elseif ($parentesco_val === 'Papá') {
        $_SESSION['padre'] = [
            'no_aplica_padre'             => '0',
            'nombres_apellidos_padre'     => $_POST['nombre_representante'] ?? '',
            'nacionalidad_cedula_padre'   => $nac,
            'numero_cedula_padre'         => $num,
            'ci_padre'                    => $_POST['cedula_identidad'],
            'telefono_padre'              => $_POST['telefono_representante'] ?? '',
            'fecha_nacimiento_padre'      => $_POST['fecha_nacimiento_representante'] ?? '',
            'estado_civil_padre'          => $_SESSION['padre']['estado_civil_padre'] ?? '',
            'lugar_nacimiento_padre'      => $_SESSION['padre']['lugar_nacimiento_padre'] ?? '',
            'correo_padre'                => $_POST['correo_electronico_representante'] ?? '',
            'direccion_habitacion_padre'  => $_POST['direccion_habitacion_representante'] ?? '',
            'nivel_instruccion_padre'     => $_POST['nivel_instruccion_representante'] ?? '',
            'profesion_padre'             => $_POST['profesion_representante'] ?? '',
            'ocupacion_padre'             => $_POST['ocupacion_representante'] ?? '',
            'empresa_padre'               => $_POST['empresa_representante'] ?? '',
            'telefono_empresa_padre'      => $_POST['telefono_empresa_representante'] ?? '',
            'direccion_empresa_padre'     => $_POST['direccion_empresa_representante'] ?? ''
        ];
    } elseif ($parentesco_val === 'Otro' && !empty($_POST['especificar_parentesco'])) {
        $_POST['parentesco_seleccionado'] = 'Otro';
        $_POST['especificar_parentesco'] = trim($_POST['especificar_parentesco']);
        $_POST['parentesco_representante'] = 'Otro - ' . trim($_POST['especificar_parentesco']);
    }

    $_SESSION['representante'] = $_POST;
    header("Location: agregar_madre.php");
    exit();
}

include("conexion.php");
$es_administrador = isset($_SESSION['rol']) && $_SESSION['rol'] === 'Administrador';
$rol_actual = $_SESSION['rol'] ?? 'Administrador';
$parentesco_actual = $_SESSION['representante']['parentesco_representante'] ?? '';
$es_otro_parentesco = (($_SESSION['representante']['parentesco_seleccionado'] ?? '') === 'Otro' || strpos($parentesco_actual, 'Otro') === 0);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrar Representante - Sistema de Inscripción</title>
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
                <i class="fas fa-user-tie" style="color: #2563eb;"></i>
                <span>Registrar Representante</span>
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
                <h3 style="margin: 0;"><i class="fas fa-user-tie text-primary" style="color: #2563eb;"></i> Paso 2: Datos del Representante</h3>
                <a href="limpiar_sesion.php?redirect=agregar_representante.php" onclick="return confirm('¿Estás seguro de limpiar todos los datos del formulario?');" style="background-color: #ef4444; color: white; padding: 5px 12px; border-radius: 6px; text-decoration: none; font-size: 0.82rem; font-weight: 600;">
                    <i class="fas fa-trash-alt"></i> Limpiar Formulario
                </a>
            </div>
            <hr style="margin-bottom: 15px; border: 0; border-top: 1px solid var(--border-color);">

            <form action="" method="POST">
                <div class="form-grid">
                    <div class="form-group" style="grid-column: span 2;">
                        <label>Nombres y Apellidos (*):</label>
                        <input type="text" name="nombre_representante" maxlength="50" onkeypress="return soloLetrasYEspacios(event)" oninput="limpiarLetras(this)" pattern="[A-Za-zÁÉÍÓÚáéíóúñÑ\s]+" title="Solo letras y espacios" value="<?php echo htmlspecialchars($_SESSION['representante']['nombre_representante'] ?? ''); ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Cédula de Identidad (*):</label>
                        <div class="cedula-group">
                            <select name="nacionalidad_cedula_rep">
                                <option value="V" <?php echo (($_SESSION['representante']['nacionalidad_cedula_rep'] ?? 'V') == 'V') ? 'selected' : ''; ?>>V-</option>
                                <option value="E" <?php echo (($_SESSION['representante']['nacionalidad_cedula_rep'] ?? '') == 'E') ? 'selected' : ''; ?>>E-</option>
                            </select>
                            <input type="text" name="numero_cedula_rep" placeholder="123456789" maxlength="9" onkeypress="return soloNumeros(event)" oninput="limpiarNumeros(this)" pattern="[0-9]{1,9}" title="Ingrese solo números (máximo 9 dígitos)" value="<?php echo htmlspecialchars($_SESSION['representante']['numero_cedula_rep'] ?? ''); ?>" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Fecha de Nacimiento:</label>
                        <input type="date" name="fecha_nacimiento_representante" min="1925-01-01" max="<?php echo date('Y-m-d'); ?>" value="<?php echo htmlspecialchars($_SESSION['representante']['fecha_nacimiento_representante'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label>Rif:</label>
                        <input type="text" name="rif_representante" maxlength="12" placeholder="V-12345678-9" onkeypress="return soloRif(event)" oninput="limpiarRif(this)" pattern="[VEJPGvejpg0-9\-]*" title="Formato V-12345678-9" value="<?php echo htmlspecialchars($_SESSION['representante']['rif_representante'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label>Correo Electrónico:</label>
                        <input type="email" name="correo_electronico_representante" maxlength="40" value="<?php echo htmlspecialchars($_SESSION['representante']['correo_electronico_representante'] ?? ''); ?>">
                    </div>

                    <div class="form-group" style="grid-column: span 2;">
                        <label>Números de contacto (celular, residencia):</label>
                        <input type="text" name="telefono_representante" maxlength="13" onkeypress="return soloTelefonos(event)" oninput="limpiarTelefonos(this)" pattern="[0-9+\-\s]+" title="Solo números y caracteres permitidos" value="<?php echo htmlspecialchars($_SESSION['representante']['telefono_representante'] ?? ''); ?>">
                    </div>

                    <div class="form-group" style="grid-column: span 2;">
                        <label>Dirección de Habitación:</label>
                        <input type="text" name="direccion_habitacion_representante" maxlength="100" value="<?php echo htmlspecialchars($_SESSION['representante']['direccion_habitacion_representante'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label>Nivel de Instrucción:</label>
                        <input type="text" name="nivel_instruccion_representante" maxlength="30" onkeypress="return soloLetrasYEspacios(event)" oninput="limpiarLetras(this)" pattern="[A-Za-zÁÉÍÓÚáéíóúñÑ\s]*" title="Solo letras y espacios" value="<?php echo htmlspecialchars($_SESSION['representante']['nivel_instruccion_representante'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label>Profesión:</label>
                        <input type="text" name="profesion_representante" maxlength="30" onkeypress="return soloLetrasYEspacios(event)" oninput="limpiarLetras(this)" pattern="[A-Za-zÁÉÍÓÚáéíóúñÑ\s]*" title="Solo letras y espacios" value="<?php echo htmlspecialchars($_SESSION['representante']['profesion_representante'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label>Ocupación:</label>
                        <input type="text" name="ocupacion_representante" maxlength="30" onkeypress="return soloLetrasYEspacios(event)" oninput="limpiarLetras(this)" pattern="[A-Za-zÁÉÍÓÚáéíóúñÑ\s]*" title="Solo letras y espacios" value="<?php echo htmlspecialchars($_SESSION['representante']['ocupacion_representante'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label>Empresa:</label>
                        <input type="text" name="empresa_representante" maxlength="30" onkeypress="return soloLetrasYEspacios(event)" oninput="limpiarLetras(this)" pattern="[A-Za-zÁÉÍÓÚáéíóúñÑ\s]*" title="Solo letras y espacios" value="<?php echo htmlspecialchars($_SESSION['representante']['empresa_representante'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label>Teléf. de Empresa:</label>
                        <input type="text" name="telefono_empresa_representante" maxlength="13" onkeypress="return soloTelefonos(event)" oninput="limpiarTelefonos(this)" pattern="[0-9+\-\s]*" title="Solo números" value="<?php echo htmlspecialchars($_SESSION['representante']['telefono_empresa_representante'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label>Dirección de la Empresa:</label>
                        <input type="text" name="direccion_empresa_representante" maxlength="50" value="<?php echo htmlspecialchars($_SESSION['representante']['direccion_empresa_representante'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label>Parentesco con el estudiante (*):</label>
                        <select name="parentesco_representante" id="parentesco_representante" onchange="verificarParentesco()" required>
                            <option value="">Seleccione...</option>
                            <option value="Mamá" <?php echo ($parentesco_actual === 'Mamá') ? 'selected' : ''; ?>>Mamá</option>
                            <option value="Papá" <?php echo ($parentesco_actual === 'Papá') ? 'selected' : ''; ?>>Papá</option>
                            <option value="Otro" <?php echo $es_otro_parentesco ? 'selected' : ''; ?>>Otro</option>
                        </select>
                    </div>

                    <div class="form-group" id="grupo_especificar_parentesco" style="display: none; grid-column: span 2;">
                        <label>Nombre de la persona que representa / Parentesco (*):</label>
                        <input type="text" name="especificar_parentesco" id="especificar_parentesco" maxlength="40" onkeypress="return soloLetrasYEspacios(event)" oninput="limpiarLetras(this)" pattern="[A-Za-zÁÉÍÓÚáéíóúñÑ\s]+" placeholder="Nombre y parentesco de quien representa al estudiante" value="<?php echo htmlspecialchars($_SESSION['representante']['especificar_parentesco'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label>¿Posee autorización legal para ser representante?:</label>
                        <select name="autorizacion_legal">
                            <option value="">Elige una opción</option>
                            <option value="No" <?php echo (($_SESSION['representante']['autorizacion_legal'] ?? '') == 'No') ? 'selected' : ''; ?>>No</option>
                            <option value="Sí" <?php echo (($_SESSION['representante']['autorizacion_legal'] ?? '') == 'Sí') ? 'selected' : ''; ?>>Sí</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Número del poder notariado:</label>
                        <input type="text" name="numero_poder" maxlength="20" onkeypress="return soloNumeros(event)" oninput="limpiarNumeros(this)" pattern="[0-9]*" title="Solo números" value="<?php echo htmlspecialchars($_SESSION['representante']['numero_poder'] ?? ''); ?>">
                    </div>
                </div>

                <div class="btn-container">
                    <a href="agregar_alumno.php" class="btn-prev"><i class="fas fa-arrow-left"></i> Anterior</a>
                    <button type="submit" name="guardar_representante" class="btn-next">Siguiente <i class="fas fa-arrow-right"></i></button>
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

        function soloRif(e) {
            var key = e.charCode || e.keyCode;
            var letra = String.fromCharCode(key);
            return /^[VEJPGvejpg0-9\-]$/.test(letra);
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

        function limpiarRif(campo) {
            campo.value = campo.value.replace(/[^VEJPGvejpg0-9\-]/g, '').toUpperCase();
        }

        function verificarParentesco() {
            var select = document.getElementById('parentesco_representante').value;
            var grupo = document.getElementById('grupo_especificar_parentesco');
            var input = document.getElementById('especificar_parentesco');

            if (select === 'Otro') {
                grupo.style.display = 'flex';
                input.setAttribute('required', 'required');
            } else {
                grupo.style.display = 'none';
                input.value = '';
                input.removeAttribute('required');
            }
        }

        window.addEventListener('DOMContentLoaded', verificarParentesco);
    </script>
</body>
</html>