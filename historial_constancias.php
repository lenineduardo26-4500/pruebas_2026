<?php
require_once("control_sesion.php");
verificarLogin();

ini_set('display_errors', 0);
error_reporting(0);

include("conexion.php");

$pdo = null;
if (isset($conexion) && $conexion instanceof PDO) {
    $pdo = $conexion;
} else {
    try {
        $pdo = new PDO("mysql:host=127.0.0.1;dbname=escuela_carnevali;charset=utf8mb4", "root", "");
    } catch (PDOException $e) {
        $pdo = new PDO("mysql:host=localhost;dbname=escuela_carnevali;charset=utf8mb4", "root", "");
    }
}
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
$pdo->exec("SET NAMES 'utf8mb4'");

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `historial_constancias` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `codigo` VARCHAR(25) NOT NULL,
        `accion` VARCHAR(120) NOT NULL,
        `estudiante` VARCHAR(150) NOT NULL,
        `cedula_escolar` VARCHAR(25) NOT NULL,
        `usuario` VARCHAR(80) NOT NULL,
        `rol` VARCHAR(50) NOT NULL,
        `fecha_hora` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;");
} catch (Exception $e) {}

$usuario_actual = $_SESSION['usuario'] ?? 'Administrador';
$es_administrador = isset($_SESSION['rol']) && $_SESSION['rol'] === 'Administrador';
$rol_actual = $_SESSION['rol'] ?? 'Administrador';
$csrf_token = obtenerTokenCSRF();
$mensaje = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'limpiar_historial_constancias') {
    if ($es_administrador) {
        if (!validarTokenCSRF($_POST['csrf_token'] ?? '')) {
            $mensaje = "<div class='no-print' style='color: #721c24; background-color: #f8d7da; padding: 10px 14px; border-radius: 8px; margin-bottom: 12px; font-size: 0.85rem; font-weight: 600; flex-shrink: 0;'>Solicitud no válida. Por seguridad, recargue la página e intente nuevamente.</div>";
        } else {
            try {
                $pdo->exec("TRUNCATE TABLE `historial_constancias`");
                try {
                    $st_hg = $pdo->prepare("INSERT INTO `historial` (`descripcion`, `usuario`, `fecha_hora`) VALUES (:desc, :usu, :fec)");
                    $st_hg->execute([
                        ':desc' => 'Se limpió el historial de constancias de estudio',
                        ':usu'  => $usuario_actual,
                        ':fec'  => date('Y-m-d H:i:s')
                    ]);
                } catch (Exception $e2) {}
                $mensaje = "<div class='no-print' style='color: #155724; background-color: #d4edda; padding: 10px 14px; border-radius: 8px; margin-bottom: 12px; font-size: 0.85rem; font-weight: 600; flex-shrink: 0;'>¡El historial de constancias de estudio ha sido limpiado exitosamente!</div>";
            } catch (Exception $e) {
                $mensaje = "<div class='no-print' style='color: #721c24; background-color: #f8d7da; padding: 10px 14px; border-radius: 8px; margin-bottom: 12px; font-size: 0.85rem; font-weight: 600; flex-shrink: 0;'>Error al limpiar el historial: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . "</div>";
            }
        }
    }
}

$buscar = trim($_GET['buscar'] ?? '');
$fecha_desde = trim($_GET['fecha_desde'] ?? '');
$fecha_hasta = trim($_GET['fecha_hasta'] ?? '');
if ($fecha_desde !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_desde)) {
    $fecha_desde = '';
}
if ($fecha_hasta !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_hasta)) {
    $fecha_hasta = '';
}

$hay_filtros = (!empty($buscar) || !empty($fecha_desde) || !empty($fecha_hasta));
$registros = [];

try {
    $condiciones = [];
    $params = [];

    if (!empty($buscar)) {
        $condiciones[] = "(`codigo` LIKE :b1 OR `estudiante` LIKE :b2 OR `cedula_escolar` LIKE :b3 OR `accion` LIKE :b4 OR `usuario` LIKE :b5)";
        $params[':b1'] = "%$buscar%";
        $params[':b2'] = "%$buscar%";
        $params[':b3'] = "%$buscar%";
        $params[':b4'] = "%$buscar%";
        $params[':b5'] = "%$buscar%";
    }
    if (!empty($fecha_desde)) {
        $condiciones[] = "DATE(`fecha_hora`) >= :f_desde";
        $params[':f_desde'] = $fecha_desde;
    }
    if (!empty($fecha_hasta)) {
        $condiciones[] = "DATE(`fecha_hora`) <= :f_hasta";
        $params[':f_hasta'] = $fecha_hasta;
    }

    if (!empty($condiciones)) {
        $sql = "SELECT * FROM `historial_constancias` WHERE " . implode(" AND ", $condiciones) . " ORDER BY `id` DESC";
        $st = $pdo->prepare($sql);
        $st->execute($params);
    } else {
        $st = $pdo->query("SELECT * FROM `historial_constancias` ORDER BY `id` DESC LIMIT 200");
    }

    if ($st) {
        $registros = $st->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial de Constancia de Estudio - Sistema de Inscripción</title>
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

        html, body {
            height: 100vh;
            max-height: 100vh;
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            background-color: var(--bg-main);
            color: var(--text-primary);
            display: flex;
            width: 100%;
            overflow: hidden;
        }

        .main-content {
            flex: 1;
            min-width: 0;
            height: 100vh;
            max-height: 100vh;
            padding: 16px 20px;
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            background-color: var(--bg-main);
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
            flex-shrink: 0;
            flex-wrap: wrap;
            gap: 10px;
        }

        .top-breadcrumb { display: flex; align-items: center; gap: 8px; font-size: 0.85rem; font-weight: 600; color: var(--text-secondary); }
        .top-actions { display: flex; align-items: center; gap: 10px; }

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

        .role-badge { padding: 2px 8px; border-radius: 5px; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; }
        .role-badge.badge-admin { background-color: rgba(239, 68, 68, 0.15); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.3); }
        .role-badge.badge-coord { background-color: rgba(59, 130, 246, 0.15); color: #3b82f6; border: 1px solid rgba(37, 99, 235, 0.3); }

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
            cursor: pointer;
        }

        .tool-action-btn.btn-refresh-cache {
            background: rgba(37, 99, 235, 0.12);
            color: #2563eb;
            border-color: rgba(37, 99, 235, 0.3);
        }

        .btn-imprimir {
            background-color: #2563eb;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.82rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-limpiar {
            background-color: #ef4444;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.82rem;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .card {
            background: var(--bg-surface);
            padding: 20px;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            border: 1px solid var(--border-color);
            flex: 1;
            min-height: 0;
            display: flex;
            flex-direction: column;
            max-width: 1000px;
            width: 100%;
            margin: 0 auto;
        }

        .table-responsive {
            flex: 1;
            min-height: 0;
            overflow-y: auto;
            overflow-x: auto;
            border: 1px solid var(--border-color);
            border-radius: 8px;
        }

        table.lista-hist { width: 100%; border-collapse: collapse; font-size: 13px; background-color: var(--bg-surface); color: var(--text-primary); }
        .lista-hist th, .lista-hist td { border: 1px solid var(--border-color); padding: 10px 12px; text-align: left; vertical-align: middle; }
        .lista-hist th { background-color: #f1f5f9; font-weight: 700; position: sticky; top: 0; z-index: 2; }

        .badge-codigo {
            background-color: rgba(37, 99, 235, 0.12);
            color: #2563eb;
            border: 1px solid rgba(37, 99, 235, 0.3);
            padding: 3px 8px;
            border-radius: 6px;
            font-weight: 700;
            font-size: 0.78rem;
            font-family: monospace;
        }

        @media print {
            .sidebar, .top-header-bar, .no-print, .btn-imprimir, .btn-limpiar, .modal, .modal-backdrop { display: none !important; }
            html, body, .main-content { height: auto !important; max-height: none !important; overflow: visible !important; display: block !important; background: #ffffff !important; padding: 0 !important; }
            .card { box-shadow: none !important; border: none !important; max-width: 100% !important; padding: 0 !important; }
            .table-responsive { overflow: visible !important; border: none !important; }
        }
    </style>
</head>
<body>

    <?php include("menu.php"); ?>

    <main class="main-content">
        <div class="top-header-bar no-print">
            <div class="top-breadcrumb">
                <i class="fas fa-history" style="color: #2563eb;"></i>
                <span>Historial de Constancia de Estudio</span>
            </div>

            <div class="top-actions">
                <a href="constancias.php" class="tool-action-btn">
                    <i class="fas fa-file-alt" style="color: #2563eb;"></i> Ir a Constancia de Estudio
                </a>

                <button type="button" class="tool-action-btn btn-refresh-cache" onclick="window.location.reload();">
                    <i class="fas fa-sync-alt"></i> Actualizar Sistema
                </button>

                <button type="button" class="btn-theme-switch" onclick="toggleDashboardTheme()">
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
            <?php if (!empty($mensaje)) echo $mensaje; ?>

            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; margin-bottom: 12px; flex-shrink: 0;">
                <h2 style="font-size: 1.1rem; font-weight: 700; margin: 0; color: var(--text-primary);">
                    <i class="fas fa-file-invoice" style="color: #2563eb; margin-right: 6px;"></i> Registro Exclusivo de Constancias de Estudio
                </h2>

                <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;" class="no-print">
                    <button type="button" onclick="window.print()" class="btn-imprimir">
                        <i class="fas fa-print"></i> Imprimir
                    </button>

                    <?php if ($es_administrador) { ?>
                        <button type="button" class="btn-limpiar" data-bs-toggle="modal" data-bs-target="#modalConfirmarLimpiar">
                            <i class="fas fa-trash-alt"></i> Limpiar Registros
                        </button>
                    <?php } ?>
                </div>
            </div>

            <form method="GET" action="historial_constancias.php" class="no-print" style="display: flex; justify-content: space-between; align-items: flex-end; flex-wrap: wrap; gap: 8px; margin-bottom: 14px; flex-shrink: 0;">
                <div style="display: flex; gap: 8px; align-items: flex-end; flex-wrap: wrap;">
                    <div>
                        <label style="font-size: 0.74rem; font-weight: 600; color: var(--text-secondary); display: block; margin-bottom: 2px;">Desde:</label>
                        <input type="date" name="fecha_desde" class="form-control" value="<?php echo htmlspecialchars($fecha_desde, ENT_QUOTES, 'UTF-8'); ?>" style="font-size: 0.82rem; padding: 5px 10px; width: 145px;">
                    </div>
                    <div>
                        <label style="font-size: 0.74rem; font-weight: 600; color: var(--text-secondary); display: block; margin-bottom: 2px;">Hasta:</label>
                        <input type="date" name="fecha_hasta" class="form-control" value="<?php echo htmlspecialchars($fecha_hasta, ENT_QUOTES, 'UTF-8'); ?>" style="font-size: 0.82rem; padding: 5px 10px; width: 145px;">
                    </div>
                </div>

                <div style="display: flex; gap: 8px; align-items: flex-end; flex-wrap: wrap;">
                    <div>
                        <label style="font-size: 0.74rem; font-weight: 600; color: var(--text-secondary); display: block; margin-bottom: 2px;">Buscar:</label>
                        <input type="text" name="buscar" class="form-control" value="<?php echo htmlspecialchars($buscar, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Código, estudiante o cédula..." style="width: 240px; font-size: 0.82rem; padding: 5px 10px;">
                    </div>
                    <button type="submit" style="padding: 6px 14px; background: #2563eb; color: #fff; border: none; border-radius: 6px; font-weight: 600; font-size: 0.82rem; cursor: pointer; height: 33px;">
                        <i class="fas fa-search"></i> Filtrar
                    </button>
                    <?php if ($hay_filtros) { ?>
                        <a href="historial_constancias.php" style="padding: 6px 12px; background: #64748b; color: #fff; border-radius: 6px; text-decoration: none; font-size: 0.82rem; font-weight: 600; height: 33px; display: inline-flex; align-items: center;">Limpiar</a>
                    <?php } ?>
                </div>
            </form>

            <div class="table-responsive">
                <table class="lista-hist">
                    <thead>
                        <tr>
                            <th style="width: 105px; text-align: center;">Código</th>
                            <th style="width: 165px;">Fecha y Hora</th>
                            <th>Qué se hizo</th>
                            <th>Nombre del Estudiante</th>
                            <th style="width: 190px;">Usuario / Rol</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($registros)) { ?>
                            <?php foreach ($registros as $r) {
                                $cod = !empty($r['codigo']) ? $r['codigo'] : ('CST-' . str_pad((string)$r['id'], 4, '0', STR_PAD_LEFT));
                                $fec = date('d/m/Y h:i:s A', strtotime($r['fecha_hora']));
                            ?>
                                <tr>
                                    <td style="text-align: center;"><span class="badge-codigo"><?php echo htmlspecialchars($cod, ENT_QUOTES, 'UTF-8'); ?></span></td>
                                    <td style="font-size: 0.84rem; font-weight: 600; color: var(--text-secondary);"><?php echo htmlspecialchars($fec, ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><strong><?php echo htmlspecialchars($r['accion'], ENT_QUOTES, 'UTF-8'); ?></strong></td>
                                    <td><?php echo htmlspecialchars($r['estudiante'] . ' (' . $r['cedula_escolar'] . ')', ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td>
                                        <span style="font-weight: 600;"><?php echo htmlspecialchars($r['usuario'], ENT_QUOTES, 'UTF-8'); ?></span>
                                        <span class="role-badge <?php echo (strcasecmp($r['rol'], 'Administrador') === 0) ? 'badge-admin' : 'badge-coord'; ?>" style="margin-left: 4px;">
                                            <?php echo htmlspecialchars($r['rol'], ENT_QUOTES, 'UTF-8'); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php } ?>
                        <?php } else { ?>
                            <tr>
                                <td colspan="5" style="text-align: center; color: var(--text-secondary); padding: 35px;">
                                    No hay registros de constancias de estudio para los criterios seleccionados.
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <?php if ($es_administrador) { ?>
    <div class="modal fade no-print" id="modalConfirmarLimpiar" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius: 12px; border: 1px solid var(--border-color); box-shadow: 0 10px 30px rgba(0,0,0,0.25);">
                <div class="modal-header bg-danger text-white" style="border-top-left-radius: 11px; border-top-right-radius: 11px;">
                    <h5 class="modal-title" style="font-size: 1rem; font-weight: 700;">
                        <i class="fas fa-exclamation-triangle me-2"></i> Confirmar Limpieza de Registros
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4" style="font-size: 0.9rem;">
                    <p class="mb-2">Esta acción eliminará todos los registros del <strong>Historial de Constancias de Estudio</strong> y reiniciará el correlativo de códigos desde <strong>CST-0001</strong>.</p>
                    <p class="mb-0" style="color: var(--text-secondary); font-size: 0.83rem;">Si necesita conservar constancia física de lo emitido hasta hoy, puede imprimir un respaldo antes de continuar. Esta limpieza quedará registrada en la auditoría general.</p>
                </div>
                <div class="modal-footer" style="justify-content: space-between; flex-wrap: wrap; gap: 8px;">
                    <button type="button" class="btn btn-primary btn-sm" onclick="imprimirAntesDeLimpiar()">
                        <i class="fas fa-print me-1"></i> Imprimir respaldo antes de limpiar
                    </button>
                    <div style="display: flex; gap: 8px;">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                        <form method="POST" action="historial_constancias.php" style="margin: 0;">
                            <input type="hidden" name="accion" value="limpiar_historial_constancias">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
                            <button type="submit" class="btn btn-danger btn-sm">
                                <i class="fas fa-trash-alt me-1"></i> Sí, limpiar
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php } ?>

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

        function imprimirAntesDeLimpiar() {
            const modalEl = document.getElementById('modalConfirmarLimpiar');
            if (modalEl && typeof bootstrap !== 'undefined') {
                const inst = bootstrap.Modal.getInstance(modalEl);
                if (inst) inst.hide();
            }
            setTimeout(() => { window.print(); }, 280);
        }
    </script>
</body>
</html>