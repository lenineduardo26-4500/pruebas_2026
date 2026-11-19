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

ini_set('display_errors', 0);
error_reporting(0);

include 'conexion.php';

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

$usuario_actual = isset($_SESSION['usuario']) ? $_SESSION['usuario'] : 'Administrador';
$es_administrador = (isset($_SESSION['rol']) && $_SESSION['rol'] === 'Administrador');
$rol_actual = isset($_SESSION['rol']) ? $_SESSION['rol'] : 'Administrador';

function guardarEnHistorialConstancias($pdo, $accion, $estudiante_nom, $cedula_esc, $usuario, $rol) {
    try {
        $fecha = date('Y-m-d H:i:s');
        $stmt = $pdo->prepare("INSERT INTO `historial_constancias` (`codigo`, `accion`, `estudiante`, `cedula_escolar`, `usuario`, `rol`, `fecha_hora`) VALUES ('', :acc, :est, :ced, :usu, :rol, :fec)");
        $stmt->execute(array(
            ':acc' => $accion,
            ':est' => $estudiante_nom,
            ':ced' => $cedula_esc,
            ':usu' => $usuario,
            ':rol' => $rol,
            ':fec' => $fecha
        ));
        $id_nuevo = $pdo->lastInsertId();
        $codigo = 'CST-' . str_pad((string)$id_nuevo, 4, '0', STR_PAD_LEFT);
        $up = $pdo->prepare("UPDATE `historial_constancias` SET `codigo` = :cod WHERE `id` = :id");
        $up->execute(array(':cod' => $codigo, ':id' => $id_nuevo));
        try {
            $desc_general = $accion . ' [' . $codigo . '] - Estudiante: ' . $estudiante_nom . ' (' . $cedula_esc . ')';
            $st_hg = $pdo->prepare("INSERT INTO `historial` (`descripcion`, `usuario`, `fecha_hora`) VALUES (:desc, :usu, :fec)");
            $st_hg->execute(array(
                ':desc' => $desc_general,
                ':usu'  => $usuario,
                ':fec'  => $fecha
            ));
        } catch (Exception $e2) {}
        return $codigo;
    } catch (Exception $e) {
        return false;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_registrar_constancia'])) {
    header('Content-Type: application/json; charset=utf-8');
    $acc_post = isset($_POST['accion']) ? trim($_POST['accion']) : 'Impresión / Guardar PDF de Constancia de Estudio';
    $est_post = isset($_POST['estudiante']) ? trim($_POST['estudiante']) : '';
    $ced_post = isset($_POST['cedula']) ? trim($_POST['cedula']) : '';
    $cod = guardarEnHistorialConstancias($pdo, $acc_post, $est_post, $ced_post, $usuario_actual, $rol_actual);
    echo json_encode(array('ok' => (bool)$cod, 'codigo' => $cod));
    exit();
}

$config_db = array();
try {
    $res_conf = $pdo->query("SELECT clave, valor FROM configuracion");
    if ($res_conf) {
        while ($r = $res_conf->fetch(PDO::FETCH_ASSOC)) {
            $config_db[$r['clave']] = $r['valor'];
        }
    }
} catch (Exception $e) {}

$estado_cfg = isset($config_db['estado']) ? $config_db['estado'] : 'Activo';
$config_activa = ($estado_cfg === 'Activo');

$nombre_escuela = '';
if ($config_activa) {
    if (isset($config_db['nombre_escuela'])) {
        $nombre_escuela = $config_db['nombre_escuela'];
    } elseif (isset($config_db['nombre_plantel'])) {
        $nombre_escuela = $config_db['nombre_plantel'];
    }
}

$codigo_plantel = '';
if ($config_activa) {
    if (isset($config_db['codigo_dea'])) {
        $codigo_plantel = $config_db['codigo_dea'];
    } elseif (isset($config_db['codigo_plantel'])) {
        $codigo_plantel = $config_db['codigo_plantel'];
    }
}

$periodo_escolar_conf = ($config_activa && isset($config_db['periodo_escolar'])) ? trim($config_db['periodo_escolar']) : '';
$direccion_raw = ($config_activa && isset($config_db['direccion'])) ? $config_db['direccion'] : '';
$direccion_plantel = trim(preg_replace('/\s+del\s+municipio\b.*$/i', '', $direccion_raw));

$municipio_raw = ($config_activa && isset($config_db['municipio'])) ? $config_db['municipio'] : 'Libertador';
$municipio_plantel = trim(preg_replace('/^municipio\s+/i', '', $municipio_raw));
$municipio_plantel = empty($municipio_plantel) ? 'Libertador' : mb_convert_case($municipio_plantel, MB_CASE_TITLE, "UTF-8");
$estado_plantel = 'Mérida';

$autoridad = array('nombres_apellidos' => '', 'cargo' => '', 'cedula' => '', 'firma' => '');
$autoridad_configurada = false;

try {
    $stmt_aut = $pdo->query("SELECT * FROM autoridades WHERE estatus = 'Activo' ORDER BY id DESC LIMIT 1");
    if ($stmt_aut) {
        $row_aut = $stmt_aut->fetch(PDO::FETCH_ASSOC);
        if ($row_aut) {
            $autoridad = $row_aut;
            $autoridad_configurada = true;
        }
    }
} catch (Exception $e) {}

function calcularEdad($fecha_nacimiento) {
    if (empty($fecha_nacimiento) || $fecha_nacimiento == '0000-00-00') {
        return "___";
    }
    try {
        $nacimiento = new DateTime($fecha_nacimiento);
        $hoy = new DateTime();
        return $hoy->diff($nacimiento)->y;
    } catch (Exception $e) {
        return "___";
    }
}

function obtenerDato($fila, $opciones) {
    if (!$fila || !is_array($fila)) {
        return '';
    }
    foreach ((array)$opciones as $opcion) {
        foreach ($fila as $columna => $valor) {
            if (strcasecmp($columna, $opcion) === 0 && !empty(trim((string)$valor))) {
                return trim((string)$valor);
            }
        }
    }
    return '';
}

function limpiarNumeroCedula($cedula) {
    return trim(preg_replace('/^[VEve][\-\.\s]*/', '', trim((string)$cedula)));
}

function obtenerSeccionAlumno($alumno) {
    return obtenerDato($alumno, array('seccion', 'sección', 'Seccion', 'Sección'));
}

$grados_disponibles = array('1' => true, '2' => true, '3' => true, '4' => true, '5' => true, '6' => true);
$secciones_disponibles = array('A' => true, 'B' => true, 'C' => true, 'D' => true, 'Única' => true);

$periodos_disponibles = array();
if (!empty($periodo_escolar_conf)) {
    $periodos_disponibles[$periodo_escolar_conf] = true;
}

try {
    $sql_opt = "SELECT DISTINCT g.nombre_grado as grado, s.nombre_seccion as sección 
                FROM inscripcion i 
                LEFT JOIN grados g ON i.id_grado = g.id 
                LEFT JOIN secciones s ON i.id_seccion = s.id";
    $res_opt = $pdo->query($sql_opt);
    if ($res_opt) {
        foreach ($res_opt->fetchAll(PDO::FETCH_ASSOC) as $opt) {
            $g = obtenerDato($opt, array('grado'));
            $s = obtenerDato($opt, array('sección', 'seccion'));
            if (!empty($g)) $grados_disponibles[$g] = true;
            if (!empty($s)) $secciones_disponibles[$s] = true;
        }
    }
} catch (Exception $e) {}

uksort($grados_disponibles, 'strnatcmp');
uksort($secciones_disponibles, 'strnatcmp');

$tipo_busqueda  = isset($_GET['tipo_busqueda']) ? trim($_GET['tipo_busqueda']) : 'cedula';
$valor_busqueda = isset($_GET['valor']) ? trim($_GET['valor']) : '';
$filtro_grado   = isset($_GET['filtro_grado']) ? trim($_GET['filtro_grado']) : '';
$filtro_seccion = isset($_GET['filtro_seccion']) ? trim($_GET['filtro_seccion']) : '';
$filtro_periodo = isset($_GET['filtro_periodo']) ? trim($_GET['filtro_periodo']) : '';
$cedula_generar = isset($_GET['generar_cedula']) ? trim($_GET['generar_cedula']) : null;

if ($tipo_busqueda === 'cedula') {
    $valor_busqueda = preg_replace('/[^0-9]/', '', $valor_busqueda);
    if (strlen($valor_busqueda) > 15) {
        $valor_busqueda = substr($valor_busqueda, 0, 15);
    }
} else {
    $valor_busqueda = preg_replace('/[^A-Za-zÁÉÍÓÚáéíóúñÑ\s]/u', '', $valor_busqueda);
    if (strlen($valor_busqueda) > 50) {
        $valor_busqueda = substr($valor_busqueda, 0, 50);
    }
}

$resultados_busqueda = array();
$alumno_seleccionado = null;
$error = "";
$mensaje_correo = "";
$tipo_alerta_correo = "";
$abrir_fallback_mailto = false;

if (!empty($cedula_generar)) {
    try {
        $stmt = $pdo->prepare("SELECT e.*, g.nombre_grado as grado, s.nombre_seccion as sección, i.`año_escolar`, r.nombres as rep_nombres, r.`cédula_identidad` as rep_ci, r.`correo_electrónico` as rep_correo FROM estudiante e LEFT JOIN inscripcion i ON e.`cédula_escolar` = i.cédula_escolar_estudiante LEFT JOIN grados g ON i.id_grado = g.id LEFT JOIN secciones s ON i.id_seccion = s.id LEFT JOIN representante r ON e.`cédula_escolar` = r.cédula_escolar_estudiante WHERE e.`cédula_escolar` = :cedula LIMIT 1");
        $stmt->execute(array('cedula' => $cedula_generar));
        $alumno_seleccionado = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {}
}

$correo_rep = "";
$asunto_mail = "";
$cuerpo_plano_mail = "";

if ($alumno_seleccionado) {
    $correo_rep = trim(obtenerDato($alumno_seleccionado, array('rep_correo', 'correo_electrónico', 'correo')));
    $nom_rep_email = obtenerDato($alumno_seleccionado, array('rep_nombres', 'nombres'));
    $nombres_alum = trim(obtenerDato($alumno_seleccionado, array('nombres')) . ' ' . obtenerDato($alumno_seleccionado, array('apellidos')));
    $ced_alum_reg = obtenerDato($alumno_seleccionado, array('cédula_escolar', 'cedula_escolar'));
    $grado_alum = obtenerDato($alumno_seleccionado, array('grado'));
    $secc_alum = obtenerSeccionAlumno($alumno_seleccionado);
    $escuela_nom = !empty($nombre_escuela) ? $nombre_escuela : 'Escuela Gladys C. Lobo de Carnevali';
    
    $asunto_mail = "Constancia de Estudio - " . $nombres_alum;
    $cuerpo_plano_mail = "Estimado(a) " . $nom_rep_email . ",\n\n"
        . "Se le notifica que se ha generado la constancia de estudio correspondiente a su representado(a) " . $nombres_alum . ", cursante de " . $grado_alum . "° Grado sección \"" . $secc_alum . "\".\n\n"
        . "Institución: " . $escuela_nom . "\n\n"
        . "Saludos cordiales.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enviar_correo_estudiante']) && $alumno_seleccionado) {
    if (!empty($correo_rep) && filter_var($correo_rep, FILTER_VALIDATE_EMAIL)) {
        $cuerpo_html = "
        <html>
        <head><meta charset='UTF-8'></head>
        <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #1e293b; padding: 20px;'>
            <div style='max-width: 600px; margin: 0 auto; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 25px;'>
                <h3 style='color: #2563eb; margin-top: 0; text-align: center;'>" . htmlspecialchars($escuela_nom) . "</h3>
                <h4 style='text-align: center; border-bottom: 2px solid #e2e8f0; padding-bottom: 8px;'>NOTIFICACIÓN DE CONSTANCIA DE ESTUDIO</h4>
                <p>Estimado(a) <strong>" . htmlspecialchars($nom_rep_email) . "</strong>,</p>
                <p>Se le notifica que ha sido emitida la constancia de estudio del alumno(a):</p>
                <div style='background-color: #f8fafc; border-left: 4px solid #2563eb; padding: 12px; margin: 15px 0;'>
                    <p style='margin: 4px 0;'><strong>Estudiante:</strong> " . htmlspecialchars($nombres_alum) . "</p>
                    <p style='margin: 4px 0;'><strong>Grado y Sección:</strong> " . htmlspecialchars($grado_alum) . "° '" . htmlspecialchars($secc_alum) . "'</p>
                </div>
                <p>Para retirar la constancia física sellada y firmada, acérquese a la sede de la institución.</p>
            </div>
        </body>
        </html>";

        $srv_name = isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : 'localhost';
        $remitente = !empty($nombre_escuela) ? $nombre_escuela : 'Sistema Escolar';
        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "From: " . $remitente . " <no-reply@" . $srv_name . ">\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion();

        guardarEnHistorialConstancias($pdo, 'Envío de Constancia por Correo (' . $correo_rep . ')', $nombres_alum, $ced_alum_reg, $usuario_actual, $rol_actual);

        if (@mail($correo_rep, $asunto_mail, $cuerpo_html, $headers)) {
            $mensaje_correo = "Correo enviado exitosamente a: <strong>" . htmlspecialchars($correo_rep) . "</strong>";
            $tipo_alerta_correo = "success";
        } else {
            $abrir_fallback_mailto = true;
            $mensaje_correo = "Abriendo redactor de correo para enviar a: <strong>" . htmlspecialchars($correo_rep) . "</strong>";
            $tipo_alerta_correo = "info";
        }
    } else {
        $mensaje_correo = "El estudiante no tiene una dirección de correo válida registrada en los datos del representante.";
        $tipo_alerta_correo = "danger";
    }
}

if (!empty($valor_busqueda) && !$alumno_seleccionado) {
    try {
        if ($tipo_busqueda == 'cedula') {
            $sql = "SELECT e.*, g.nombre_grado as grado, s.nombre_seccion as sección, i.`año_escolar`, r.nombres as rep_nombres, r.`cédula_identidad` as rep_ci, r.`correo_electrónico` as rep_correo FROM estudiante e LEFT JOIN inscripcion i ON e.`cédula_escolar` = i.cédula_escolar_estudiante LEFT JOIN grados g ON i.id_grado = g.id LEFT JOIN secciones s ON i.id_seccion = s.id LEFT JOIN representante r ON e.`cédula_escolar` = r.cédula_escolar_estudiante WHERE e.`cédula_escolar` = :valor OR e.`cédula_escolar` LIKE :valor_like ORDER BY g.nombre_grado ASC, s.nombre_seccion ASC, e.apellidos ASC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(array('valor' => $valor_busqueda, 'valor_like' => "%$valor_busqueda%"));
        } else {
            $sql = "SELECT e.*, g.nombre_grado as grado, s.nombre_seccion as sección, i.`año_escolar`, r.nombres as rep_nombres, r.`cédula_identidad` as rep_ci, r.`correo_electrónico` as rep_correo FROM estudiante e LEFT JOIN inscripcion i ON e.`cédula_escolar` = i.cédula_escolar_estudiante LEFT JOIN grados g ON i.id_grado = g.id LEFT JOIN secciones s ON i.id_seccion = s.id LEFT JOIN representante r ON e.`cédula_escolar` = r.cédula_escolar_estudiante WHERE e.nombres LIKE :v1 OR e.apellidos LIKE :v2 OR CONCAT(TRIM(e.nombres), ' ', TRIM(e.apellidos)) LIKE :v3 OR CONCAT(TRIM(e.apellidos), ' ', TRIM(e.nombres)) LIKE :v4 ORDER BY g.nombre_grado ASC, s.nombre_seccion ASC, e.apellidos ASC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(array('v1' => "%$valor_busqueda%", 'v2' => "%$valor_busqueda%", 'v3' => "%$valor_busqueda%", 'v4' => "%$valor_busqueda%"));
        }
        $resultados_busqueda = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $error = "Error en la consulta: " . $e->getMessage();
    }
} elseif ((!empty($filtro_grado) || !empty($filtro_seccion) || !empty($filtro_periodo)) && !$alumno_seleccionado) {
    try {
        $condiciones = array();
        $params = array();
        if (!empty($filtro_grado)) {
            $condiciones[] = "TRIM(g.nombre_grado) = :grado";
            $params['grado'] = $filtro_grado;
        }
        if (!empty($filtro_seccion)) {
            if ($filtro_seccion === 'Única' || $filtro_seccion === 'U') {
                $condiciones[] = "(TRIM(s.nombre_seccion) = 'Única' OR TRIM(s.nombre_seccion) = 'U')";
            } else {
                $condiciones[] = "TRIM(s.nombre_seccion) = :seccion";
                $params['seccion'] = $filtro_seccion;
            }
        }
        if (!empty($filtro_periodo) && $filtro_periodo !== $periodo_escolar_conf) {
            $condiciones[] = "TRIM(i.`año_escolar`) = :periodo";
            $params['periodo'] = $filtro_periodo;
        }
        
        $where_sql = count($condiciones) > 0 ? "WHERE " . implode(" AND ", $condiciones) : "";
        $sql = "SELECT e.*, g.nombre_grado as grado, s.nombre_seccion as sección, i.`año_escolar`, r.nombres as rep_nombres, r.`cédula_identidad` as rep_ci, r.`correo_electrónico` as rep_correo FROM estudiante e INNER JOIN inscripcion i ON e.`cédula_escolar` = i.cédula_escolar_estudiante LEFT JOIN grados g ON i.id_grado = g.id LEFT JOIN secciones s ON i.id_seccion = s.id LEFT JOIN representante r ON e.`cédula_escolar` = r.cédula_escolar_estudiante $where_sql ORDER BY e.apellidos ASC, e.nombres ASC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $resultados_busqueda = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $error = "Error al filtrar: " . $e->getMessage();
    }
}

if ((!empty($valor_busqueda) || !empty($filtro_grado) || !empty($filtro_seccion) || !empty($filtro_periodo)) && empty($resultados_busqueda) && !$alumno_seleccionado && empty($error)) {
    $error = "No se encontraron alumnos con los criterios seleccionados.";
}

$mailto_url = "mailto:" . rawurlencode($correo_rep) . "?subject=" . rawurlencode($asunto_mail) . "&body=" . rawurlencode($cuerpo_plano_mail);
$gmail_web_url = "https://mail.google.com/mail/?view=cm&fs=1&to=" . rawurlencode($correo_rep) . "&su=" . rawurlencode($asunto_mail) . "&body=" . rawurlencode($cuerpo_plano_mail);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Módulo de Constancias - Sistema de Inscripción</title>
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
        body.dark-mode select {
            background-color: var(--bg-surface) !important;
            border-color: var(--border-color) !important;
            color: var(--text-primary) !important;
        }

        body.dark-mode th { background-color: #1e293b !important; color: var(--text-primary) !important; border-color: var(--border-color) !important; }
        body.dark-mode td { background-color: var(--bg-surface) !important; color: var(--text-primary) !important; border-color: var(--border-color) !important; }
        body.dark-mode tr:nth-child(even) td { background-color: var(--bg-surface-alt) !important; }

        html body.dark-mode .printable-area {
            background-color: #ffffff !important;
            color: #000000 !important;
            border-color: #e2e8f0 !important;
        }

        html body.dark-mode .printable-area * {
            color: #000000 !important;
        }

        html body.dark-mode .printable-area table,
        html body.dark-mode .printable-area thead,
        html body.dark-mode .printable-area tbody,
        html body.dark-mode .printable-area tr,
        html body.dark-mode .printable-area th,
        html body.dark-mode .printable-area td,
        html body.dark-mode .printable-area tr:nth-child(even) td,
        html body.dark-mode .printable-area tr:hover td {
            background-color: #ffffff !important;
            background: #ffffff !important;
            border: none !important;
            color: #000000 !important;
        }

        html body.dark-mode .printable-area .logo-izq,
        html body.dark-mode .printable-area .logo-der,
        html body.dark-mode .printable-area .img-bandera {
            filter: none !important;
            mix-blend-mode: normal !important;
            opacity: 1 !important;
            background-color: transparent !important;
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
        }

        .main-content {
            flex: 1;
            min-width: 0;
            padding: 16px 20px 24px 20px;
            box-sizing: border-box;
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
            flex-wrap: wrap;
            gap: 10px;
        }

        .top-breadcrumb { display: flex; align-items: center; gap: 8px; font-size: 0.85rem; font-weight: 600; color: var(--text-secondary); }
        .top-actions { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }

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
            transition: all 0.2s ease;
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

        .container-fluid { padding: 0; box-sizing: border-box; width: 100%; max-width: 1000px; margin: 0 auto; }
        .card { background: var(--bg-surface); padding: 20px; border-radius: 12px; box-shadow: var(--card-shadow); margin-bottom: 20px; border: 1px solid var(--border-color); box-sizing: border-box; }
        
        .printable-area {
            border: 1px solid var(--border-color);
            padding: 25px 35px 0 35px;
            background: #ffffff !important;
            color: #000000 !important;
            box-shadow: 0px 4px 15px rgba(0,0,0,0.08);
            width: 100%;
            max-width: 740px;
            margin: 0 auto;
            border-radius: 8px;
            box-sizing: border-box;
            overflow: hidden;
            page-break-inside: avoid;
        }

        .printable-area * { color: #000000 !important; }
        .printable-area table, .printable-area tr, .printable-area td { background-color: #ffffff !important; border: none !important; }

        .logo-izq { height: 58px; width: auto; max-width: 100%; object-fit: contain; display: block; background: transparent !important; }
        .logo-der { height: 60px; width: auto; max-width: 75px; object-fit: contain; display: inline-block; background: transparent !important; }

        .texto-cintillo {
            font-size: 9.5px;
            line-height: 1.25;
            color: #000000 !important;
            text-align: left;
            border-left: 1.5px solid #94a3b8;
            padding-left: 10px;
            font-family: Arial, sans-serif;
            background: transparent !important;
        }

        .titulo-constancia { text-align: center; font-weight: bold; font-size: 14.5px; margin-bottom: 16px; color: #000000 !important; }
        .cuerpo-constancia { line-height: 1.65; text-align: justify; font-size: 12.8px; color: #000000 !important; font-style: italic; }
        .cuerpo-constancia p { margin: 0 0 11px 0; color: #000000 !important; }

        .seccion-firma { margin-top: 22px; text-align: center; display: flex; flex-direction: column; align-items: center; justify-content: center; }
        .box-firma-img { height: 52px; margin-bottom: -10px; z-index: 2; background: transparent; }
        .box-firma-img img { max-height: 100%; max-width: 175px; object-fit: contain; filter: grayscale(100%) contrast(400%) brightness(1.2); mix-blend-mode: multiply; }
        .linea-firma { width: 250px; border-top: 1px solid #000000; margin-bottom: 4px; }
        .datos-directora { font-family: Arial, sans-serif; font-size: 12.5px; line-height: 1.3; font-style: italic; color: #000000 !important; }

        .pie-bandera { margin-left: -35px; margin-right: -35px; position: relative; line-height: 0; margin-top: 18px; }
        .texto-bandera { position: absolute; top: 48%; left: 35px; width: auto; font-size: 10.5px; font-family: Arial, sans-serif; color: #000000 !important; line-height: normal; z-index: 2; background: transparent !important; }
        .img-bandera { width: 100%; height: 95px; object-fit: fill; display: block; }

        .table-responsive { width: 100%; overflow-x: auto; border: 1px solid var(--border-color); border-radius: 8px; margin-top: 15px; }
        table.lista-alumnos { width: 100%; border-collapse: collapse; font-size: 13px; background-color: var(--bg-surface); color: var(--text-primary); }
        .lista-alumnos th, .lista-alumnos td { border: 1px solid var(--border-color); padding: 10px; text-align: left; }
        .lista-alumnos th { background-color: #f1f5f9; font-weight: 700; }

        @page { size: letter portrait; margin: 0; }

        @media print {
            .no-print, header, nav, footer, button, form, .sidebar, .top-header-bar { display: none !important; }
            html, body { background: #ffffff !important; margin: 0 !important; padding: 0 !important; width: 100% !important; height: 100% !important; display: block !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
            .main-content { padding: 0 !important; margin: 0 !important; width: 100% !important; }
            .container-fluid { padding: 0 !important; margin: 0 !important; max-width: 100% !important; width: 100% !important; }
            .printable-area { border: none !important; box-shadow: none !important; border-radius: 0 !important; width: 100% !important; max-width: 100% !important; min-height: 100vh !important; padding: 40px 60px 0 60px !important; margin: 0 !important; display: flex !important; flex-direction: column !important; justify-content: space-between !important; box-sizing: border-box !important; background: #ffffff !important; }
            .logo-izq { height: 72px !important; }
            .logo-der { height: 75px !important; max-width: 90px !important; }
            .texto-cintillo { font-size: 11px !important; line-height: 1.3 !important; }
            .titulo-constancia { font-size: 17px !important; margin-top: 10px !important; margin-bottom: 25px !important; }
            .cuerpo-constancia { font-size: 15px !important; line-height: 2.0 !important; }
            .cuerpo-constancia p { margin-bottom: 18px !important; }
            .seccion-firma { margin-top: 50px !important; margin-bottom: 25px !important; }
            .box-firma-img { height: 70px !important; }
            .box-firma-img img { max-width: 210px !important; }
            .linea-firma { width: 290px !important; }
            .datos-directora { font-size: 14.5px !important; }
            .pie-bandera { margin-left: -60px !important; margin-right: -60px !important; margin-top: auto !important; }
            .texto-bandera { left: 60px !important; top: 48% !important; font-size: 12px !important; }
            .img-bandera { height: 135px !important; }
        }
    </style>
</head>
<body>

    <?php include("menu.php"); ?>

    <main class="main-content">
        <div class="top-header-bar no-print">
            <div class="top-breadcrumb">
                <i class="fas fa-file-alt" style="color: #2563eb;"></i>
                <span>Módulo de Constancias de Estudio</span>
            </div>

            <div class="top-actions">
                <a href="historial_constancias.php" class="tool-action-btn">
                    <i class="fas fa-history" style="color: #2563eb;"></i> Historial de Constancia
                </a>

                <button type="button" class="tool-action-btn btn-refresh-cache" onclick="actualizarDashboard()" title="Actualizar datos del sistema">
                    <i class="fas fa-sync-alt" id="refreshIcon"></i> Actualizar Sistema
                </button>

                <button type="button" class="btn-theme-switch" onclick="toggleDashboardTheme()" title="Cambiar Modo Claro / Oscuro">
                    <i class="fas fa-moon" id="dashThemeIcon"></i>
                </button>

                <div class="user-profile-pill">
                    <i class="fas fa-user-circle" style="color: #2563eb;"></i>
                    <span><?php echo htmlspecialchars($usuario_actual); ?></span>
                    <span class="role-badge <?php echo $es_administrador ? 'badge-admin' : 'badge-coord'; ?>">
                        <?php echo $es_administrador ? 'Admin' : htmlspecialchars($rol_actual); ?>
                    </span>
                </div>
            </div>
        </div>

        <div class="container-fluid">
            
            <?php if (!$autoridad_configurada) { ?>
                <div class="no-print" style="color: #856404; background-color: #fff3cd; border: 1px solid #ffeeba; padding: 12px; border-radius: 8px; margin-bottom: 15px; font-weight: bold; font-size: 0.9rem;">
                    ⚠️ Atención: No hay ninguna autoridad activa registrada en el sistema. Debe configurar una autoridad y los datos institucionales para que aparezcan reflejados en las constancias.
                </div>
            <?php } ?>

            <?php if (!empty($mensaje_correo)) { ?>
                <div class="no-print alert alert-<?php echo $tipo_alerta_correo; ?> alert-dismissible fade show" role="alert" style="max-width: 740px; margin: 0 auto 15px auto;">
                    <div>
                        <i class="fas <?php echo ($tipo_alerta_correo === 'success') ? 'fa-check-circle' : 'fa-info-circle'; ?> me-1"></i>
                        <?php echo $mensaje_correo; ?>
                    </div>
                    <?php if ($abrir_fallback_mailto) { ?>
                        <div class="mt-2 pt-2 border-top border-secondary-subtle d-flex gap-2 flex-wrap">
                            <a href="<?php echo $gmail_web_url; ?>" target="_blank" class="btn btn-sm btn-danger">
                                <i class="fab fa-google"></i> Abrir en Gmail Web
                            </a>
                            <a href="<?php echo $mailto_url; ?>" class="btn btn-sm btn-secondary">
                                <i class="fas fa-envelope-open-text"></i> Abrir en Aplicación de Correo
                            </a>
                        </div>
                    <?php } ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php } ?>

            <?php if (!$alumno_seleccionado) { ?>
                <div class="card no-print">
                    <h2 style="font-size: 1.15rem; font-weight: 700; margin-bottom: 15px; color: var(--text-primary);">
                        <i class="fas fa-search text-primary" style="color: #2563eb;"></i> Seleccionar Estudiante para Constancia
                    </h2>
                    
                    <form method="GET" action="constancias.php" style="margin-bottom: 15px;">
                        <div style="font-size: 0.82rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 5px;">Búsqueda por datos personales:</div>
                        <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                            <select id="tipoBusqueda" name="tipo_busqueda" class="form-control" onchange="actualizarReglaValidacion(true)" style="padding: 8px; border-radius: 6px; border: 1px solid var(--border-color); width: 220px; font-size: 0.9rem;">
                                <option value="cedula" <?php echo ($tipo_busqueda == 'cedula') ? 'selected' : ''; ?>>Cédula Escolar / Identidad</option>
                                <option value="nombre" <?php echo ($tipo_busqueda == 'nombre') ? 'selected' : ''; ?>>Nombre o Apellido</option>
                            </select>
                            <input type="text" id="inputValorBusqueda" name="valor" class="form-control" value="<?php echo htmlspecialchars($valor_busqueda, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Ingrese solo números (máx 15)..." maxlength="15" style="padding: 8px 12px; width: 280px; border-radius: 6px; border: 1px solid var(--border-color); font-size: 0.9rem;" required>
                            <button type="submit" style="padding: 8px 16px; background: #2563eb; color: #fff; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 0.9rem;">
                                <i class="fas fa-search"></i> Buscar
                            </button>
                        </div>
                    </form>

                    <hr style="border-color: var(--border-color); margin: 15px 0;">

                    <form method="GET" action="constancias.php" style="margin:0;">
                        <div style="font-size: 0.82rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 5px;">Filtrar por Grado (1° a 6°), Sección y Período:</div>
                        <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                            <select name="filtro_grado" class="form-control" style="padding: 8px; border-radius: 6px; border: 1px solid var(--border-color); width: 180px; font-size: 0.88rem;" required>
                                <option value="">Seleccione Grado...</option>
                                <?php foreach ($grados_disponibles as $g => $v) { ?>
                                    <option value="<?php echo htmlspecialchars($g); ?>" <?php echo ($filtro_grado == $g) ? 'selected' : ''; ?>><?php echo htmlspecialchars($g); ?>° Grado</option>
                                <?php } ?>
                            </select>

                            <select name="filtro_seccion" class="form-control" style="padding: 8px; border-radius: 6px; border: 1px solid var(--border-color); width: 140px; font-size: 0.88rem;" required>
                                <option value="">Sección...</option>
                                <?php foreach ($secciones_disponibles as $s => $v) { ?>
                                    <option value="<?php echo htmlspecialchars($s); ?>" <?php echo ($filtro_seccion == $s) ? 'selected' : ''; ?>>Sección <?php echo htmlspecialchars($s); ?></option>
                                <?php } ?>
                            </select>

                            <select name="filtro_periodo" class="form-control" style="padding: 8px; border-radius: 6px; border: 1px solid var(--border-color); width: 160px; font-size: 0.88rem;" required>
                                <option value="">Período...</option>
                                <?php foreach ($periodos_disponibles as $p => $v) { ?>
                                    <option value="<?php echo htmlspecialchars($p); ?>" <?php echo ($filtro_periodo == $p) ? 'selected' : ''; ?>><?php echo htmlspecialchars($p); ?></option>
                                <?php } ?>
                            </select>

                            <button type="submit" style="padding: 8px 16px; background: #059669; color: #fff; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 0.9rem;">
                                <i class="fas fa-filter"></i> Filtrar Alumnos
                            </button>

                            <a href="constancias.php" style="padding: 8px 14px; background: #64748b; color: #fff; border-radius: 6px; text-decoration: none; font-weight: 600; font-size: 0.9rem;">Limpiar</a>
                        </div>
                    </form>

                    <?php if (!empty($error)) { ?>
                        <div style="color: #721c24; background-color: #f8d7da; border: 1px solid #f5c6cb; padding: 12px; border-radius: 8px; margin-top: 15px; font-weight: bold; font-size: 0.9rem;"><?php echo $error; ?></div>
                    <?php } ?>
                </div>

                <?php if (!empty($resultados_busqueda)) { ?>
                    <div class="card no-print">
                        <h3 style="font-size: 1rem; font-weight: 700; margin-bottom: 10px; color: var(--text-primary);">Lista de Alumnos Encontrados (Seleccione uno para generar la constancia):</h3>
                        <div class="table-responsive">
                            <table class="lista-alumnos">
                                <thead>
                                    <tr>
                                        <th style="width: 50px; text-align: center;">N°</th>
                                        <th>Cédula Escolar</th>
                                        <th>Nombres y Apellidos</th>
                                        <th>Grado</th>
                                        <th>Sección</th>
                                        <th style="text-align: center; width: 170px;">Acción</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $contador = 1;
                                    foreach ($resultados_busqueda as $alum) { 
                                        $cedula_alum = obtenerDato($alum, array('cédula_escolar', 'cedula_escolar'));
                                    ?>
                                        <tr>
                                            <td style="text-align: center; font-weight: bold; color: var(--text-secondary);"><?php echo $contador++; ?></td>
                                            <td><strong><?php echo htmlspecialchars($cedula_alum); ?></strong></td>
                                            <td><?php echo htmlspecialchars(obtenerDato($alum, array('nombres')) . ' ' . obtenerDato($alum, array('apellidos'))); ?></td>
                                            <td><?php echo htmlspecialchars(obtenerDato($alum, array('grado'))); ?>°</td>
                                            <td><?php echo htmlspecialchars(obtenerSeccionAlumno($alum)); ?></td>
                                            <td style="text-align: center;">
                                                <a href="constancias.php?generar_cedula=<?php echo urlencode($cedula_alum); ?>&tipo_busqueda=<?php echo urlencode($tipo_busqueda); ?>&valor=<?php echo urlencode($valor_busqueda); ?>&filtro_grado=<?php echo urlencode($filtro_grado); ?>&filtro_seccion=<?php echo urlencode($filtro_seccion); ?>&filtro_periodo=<?php echo urlencode($filtro_periodo); ?>" 
                                                   style="padding: 6px 12px; background: #2563eb; color: white; border-radius: 6px; text-decoration: none; font-weight: 600; font-size: 0.82rem; display: inline-flex; align-items: center; gap: 5px;">
                                                    <i class="fas fa-file-invoice"></i> Generar Constancia
                                                </a>
                                            </td>
                                        </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php } ?>
            <?php } ?>

            <?php if ($alumno_seleccionado) {
                $nom_est_js = trim(obtenerDato($alumno_seleccionado, array('nombres')) . ' ' . obtenerDato($alumno_seleccionado, array('apellidos')));
                $ced_est_js = obtenerDato($alumno_seleccionado, array('cédula_escolar', 'cedula_escolar'));
            ?>
                <div class="no-print" style="max-width: 740px; margin: 0 auto 12px auto; display: flex; gap: 10px; justify-content: space-between; align-items: center; flex-wrap: wrap;">
                    <a href="constancias.php?tipo_busqueda=<?php echo urlencode($tipo_busqueda); ?>&valor=<?php echo urlencode($valor_busqueda); ?>&filtro_grado=<?php echo urlencode($filtro_grado); ?>&filtro_seccion=<?php echo urlencode($filtro_seccion); ?>&filtro_periodo=<?php echo urlencode($filtro_periodo); ?>" style="padding: 7px 14px; background: #64748b; color: #fff; border-radius: 6px; text-decoration: none; font-weight: 600; font-size: 0.85rem;">
                        <i class="fas fa-arrow-left"></i> Volver al Listado
                    </a>
                    
                    <div style="display: flex; gap: 10px; align-items: center;">
                        <button type="button" onclick="imprimirYRegistrarConstancia('<?php echo htmlspecialchars($nom_est_js, ENT_QUOTES, 'UTF-8'); ?>', '<?php echo htmlspecialchars($ced_est_js, ENT_QUOTES, 'UTF-8'); ?>')" style="padding: 8px 16px; background: #059669; color: #fff; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 0.88rem;">
                            🖨️ Imprimir / Guardar PDF
                        </button>

                        <form method="POST" action="constancias.php?generar_cedula=<?php echo urlencode($cedula_generar); ?>&tipo_busqueda=<?php echo urlencode($tipo_busqueda); ?>&valor=<?php echo urlencode($valor_busqueda); ?>&filtro_grado=<?php echo urlencode($filtro_grado); ?>&filtro_seccion=<?php echo urlencode($filtro_seccion); ?>&filtro_periodo=<?php echo urlencode($filtro_periodo); ?>" style="margin: 0; display: inline;">
                            <input type="hidden" name="enviar_correo_estudiante" value="1">
                            <button type="submit" style="padding: 8px 16px; background: #0891b2; color: #fff; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 0.88rem; display: inline-flex; align-items: center; gap: 5px;">
                                <i class="fas fa-envelope"></i> Enviar por Correo
                            </button>
                        </form>
                    </div>
                </div>

                <?php
                    $cedula_alumno_limpia = limpiarNumeroCedula(obtenerDato($alumno_seleccionado, array('cédula_escolar', 'cedula_escolar')));
                    $cedula_rep_limpia = limpiarNumeroCedula(obtenerDato($alumno_seleccionado, array('rep_ci')));
                    $anio_escolar_mostrar = $periodo_escolar_conf;
                    $nombre_aut_firma = $autoridad_configurada ? trim($autoridad['nombres_apellidos']) : '';
                    if (!empty($nombre_aut_firma) && !preg_match('/^prof/i', $nombre_aut_firma)) {
                        $nombre_aut_firma = 'Prof. ' . $nombre_aut_firma;
                    }
                ?>

                <div class="printable-area">
                    <div>
                        <table style="width: 100%; border-collapse: collapse; margin-bottom: 14px; background-color: #ffffff !important;">
                            <tr>
                                <td style="width: 50%; text-align: left; vertical-align: middle; padding: 0; background-color: #ffffff !important;">
                                    <img src="./imagenes/logo_izquierda.png" alt="Logo Izquierdo" class="logo-izq">
                                </td>
                                <td style="width: 34%; vertical-align: middle; padding: 0 8px; background-color: #ffffff !important;">
                                    <div class="texto-cintillo">
                                        Denominación Nacional<br>
                                        Escuela Gladys de Carnevali<br>
                                        Municipio Libertador. Parroquia Milla.<br>
                                        OD02721412
                                    </div>
                                </td>
                                <td style="width: 16%; text-align: right; vertical-align: middle; padding: 0; background-color: #ffffff !important;">
                                    <img src="./imagenes/logo_derecha.png" alt="Logo Derecho" class="logo-der">
                                </td>
                            </tr>
                        </table>

                        <div class="titulo-constancia">
                            CONSTANCIA DE ESTUDIO
                        </div>

                        <div class="cuerpo-constancia">
                            <p>
                                Quien suscribe la Profesora <?php echo $autoridad_configurada ? htmlspecialchars($autoridad['nombres_apellidos']) : '________________________________________'; ?>, 
                                titular de la Cédula de Identidad N° <?php echo $autoridad_configurada ? htmlspecialchars($autoridad['cedula']) : 'V-________________'; ?>,
                                <?php echo $autoridad_configurada ? htmlspecialchars($autoridad['cargo']) : '____________________'; ?> de la <?php echo !empty($nombre_escuela) ? htmlspecialchars($nombre_escuela) : '________________________________________'; ?>, 
                                Código <?php echo !empty($codigo_plantel) ? htmlspecialchars($codigo_plantel) : '___________'; ?>, 
                                ubicada en la <?php echo !empty($direccion_plantel) ? htmlspecialchars($direccion_plantel) : 'Avenida 1 Hoyada de Milla'; ?> del Municipio <?php echo htmlspecialchars($municipio_plantel); ?> del Estado <?php echo htmlspecialchars($estado_plantel); ?>, por medio de la presente.
                            </p>

                            <div style="text-align: center; text-decoration: underline; margin: 12px 0; font-style: italic; font-weight: normal; color: #000000 !important;">HACE CONSTAR</div>

                            <p>
                                Que el niño(a): <?php 
                                    $nombre_completo = obtenerDato($alumno_seleccionado, array('nombres')) . ' ' . obtenerDato($alumno_seleccionado, array('apellidos'));
                                    echo htmlspecialchars($nombre_completo); 
                                ?>
                                de <?php echo calcularEdad(obtenerDato($alumno_seleccionado, array('fecha_nacimiento'))); ?> años de edad,
                                Cédula Escolar o Cédula de Identidad: N°. V <?php echo htmlspecialchars($cedula_alumno_limpia); ?>
                                Natural de <?php echo htmlspecialchars(obtenerDato($alumno_seleccionado, array('lugar_nacimiento'))); ?>,
                                estado <?php echo htmlspecialchars(obtenerDato($alumno_seleccionado, array('municipio_nacimiento'))); ?>,
                                fue inscrito (a) para cursar el <?php echo htmlspecialchars(obtenerDato($alumno_seleccionado, array('grado'))); ?> grado,
                                Sección: <?php echo htmlspecialchars(obtenerSeccionAlumno($alumno_seleccionado)); ?>
                                de Educación Primaria año Escolar <?php echo htmlspecialchars($anio_escolar_mostrar); ?>
                                Cumpliendo un Horario de 8:00 a 1:00pm.
                            </p>

                            <p>
                                Constancia que se expide a solicitud de su representante Legal:
                                <?php echo htmlspecialchars(obtenerDato($alumno_seleccionado, array('rep_nombres'))); ?>,
                                titular de la Cédula de Identidad N° V-<?php echo htmlspecialchars($cedula_rep_limpia); ?>.
                            </p>

                            <p style="margin-bottom: 0;">
                                En la ciudad de Mérida a los <?php echo date('d'); ?> días del Mes de
                                <?php
                                    $meses = array('enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre');
                                    echo $meses[date('n') - 1];
                                ?> del año <?php echo date('Y'); ?>.
                            </p>
                        </div>

                        <div class="seccion-firma">
                            <div class="box-firma-img">
                                <?php if (isset($_SESSION['rol']) && $_SESSION['rol'] === 'Administrador' && !empty($autoridad['firma']) && file_exists($autoridad['firma'])) { ?>
                                    <img src="<?php echo htmlspecialchars($autoridad['firma']); ?>" alt="Firma Digital">
                                <?php } ?>
                            </div>
                            <div class="linea-firma"></div>
                            <div class="datos-directora">
                                <span><?php echo $autoridad_configurada ? htmlspecialchars($nombre_aut_firma) : '________________________________________'; ?></span><br>
                                <span><?php echo $autoridad_configurada ? htmlspecialchars($autoridad['cargo']) : '____________________'; ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="pie-bandera">
                        <div class="texto-bandera">
                            Dirección: Avenida 1 Hoyada de Milla. Tlf. 04149788263 -02742443999
                        </div>
                        <img src="./imagenes/bandera.png" alt="Franja Tricolor" class="img-bandera">
                    </div>
                </div>
            <?php } ?>

        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        <?php if ($abrir_fallback_mailto) { ?>
            window.open('<?php echo $gmail_web_url; ?>', '_blank');
        <?php } ?>

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
            if (icon) icon.classList.add('fa-spin');
            setTimeout(() => { window.location.reload(); }, 450);
        }

        function actualizarReglaValidacion(limpiarCampo) {
            const selectEl = document.getElementById('tipoBusqueda');
            const input = document.getElementById('inputValorBusqueda');
            if (!selectEl || !input) return;
            const tipo = selectEl.value;
            if (limpiarCampo) input.value = '';
            if (tipo === 'cedula') {
                input.placeholder = "Ingrese solo números (máx 15)...";
                input.setAttribute('maxlength', '15');
                input.setAttribute('inputmode', 'numeric');
                input.value = input.value.replace(/[^0-9]/g, '').slice(0, 15);
            } else {
                input.placeholder = "Ingrese nombres o apellidos...";
                input.setAttribute('maxlength', '50');
                input.removeAttribute('inputmode');
                input.value = input.value.replace(/[^A-Za-zÁÉÍÓÚáéíóúñÑ\s]/g, '').slice(0, 50);
            }
        }

        const inputBusqConst = document.getElementById('inputValorBusqueda');
        if (inputBusqConst) {
            inputBusqConst.addEventListener('input', function() {
                const tipo = document.getElementById('tipoBusqueda').value;
                if (tipo === 'cedula') {
                    this.value = this.value.replace(/[^0-9]/g, '').slice(0, 15);
                } else {
                    this.value = this.value.replace(/[^A-Za-zÁÉÍÓÚáéíóúñÑ\s]/g, '').slice(0, 50);
                }
            });
        }

        document.addEventListener('DOMContentLoaded', function() {
            actualizarReglaValidacion(false);
        });

        function imprimirYRegistrarConstancia(nombreEstudiante, cedulaEstudiante) {
            const formData = new FormData();
            formData.append('ajax_registrar_constancia', '1');
            formData.append('accion', 'Impresión / Guardar PDF de Constancia de Estudio');
            formData.append('estudiante', nombreEstudiante);
            formData.append('cedula', cedulaEstudiante);

            fetch('constancias.php', { method: 'POST', body: formData })
            .then(() => { window.print(); })
            .catch(() => { window.print(); });
        }
    </script>
</body>
</html>