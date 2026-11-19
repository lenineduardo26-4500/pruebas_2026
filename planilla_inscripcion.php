<?php
session_start();
date_default_timezone_set('America/Caracas');
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
$pdo->exec("SET sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''))");

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `historial_planillas` (
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

function guardarEnHistorialPlanillas(PDO $pdo, $accion, $estudiante_nom, $cedula_esc, $usuario, $rol) {
    try {
        $fecha = date('Y-m-d H:i:s');
        $stmt = $pdo->prepare("INSERT INTO `historial_planillas` (`codigo`, `accion`, `estudiante`, `cedula_escolar`, `usuario`, `rol`, `fecha_hora`) VALUES ('', :acc, :est, :ced, :usu, :rol, :fec)");
        $stmt->execute([
            ':acc' => $accion,
            ':est' => $estudiante_nom,
            ':ced' => $cedula_esc,
            ':usu' => $usuario,
            ':rol' => $rol,
            ':fec' => $fecha
        ]);
        $id_nuevo = $pdo->lastInsertId();
        $codigo = 'PLN-' . str_pad((string)$id_nuevo, 4, '0', STR_PAD_LEFT);
        $up = $pdo->prepare("UPDATE `historial_planillas` SET `codigo` = :cod WHERE `id` = :id");
        $up->execute([':cod' => $codigo, ':id' => $id_nuevo]);
        try {
            $desc_general = $accion . ' [' . $codigo . '] - Estudiante: ' . $estudiante_nom . ' (' . $cedula_esc . ')';
            $st_hg = $pdo->prepare("INSERT INTO `historial` (`descripcion`, `usuario`, `fecha_hora`) VALUES (:desc, :usu, :fec)");
            $st_hg->execute([
                ':desc' => $desc_general,
                ':usu'  => $usuario,
                ':fec'  => $fecha
            ]);
        } catch (Exception $e2) {}
        return $codigo;
    } catch (Exception $e) {
        return false;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_registrar_planilla'])) {
    header('Content-Type: application/json; charset=utf-8');
    $acc_post = trim($_POST['accion'] ?? 'Impresión / Guardar PDF de Planilla de Inscripción');
    $est_post = trim($_POST['estudiante'] ?? '');
    $ced_post = trim($_POST['cedula'] ?? '');
    $cod = guardarEnHistorialPlanillas($pdo, $acc_post, $est_post, $ced_post, $usuario_actual, $rol_actual);
    echo json_encode(['ok' => (bool)$cod, 'codigo' => $cod]);
    exit();
}

function descomponerFecha($fecha) {
    if (empty($fecha) || strpos((string)$fecha, '0000-00-00') === 0) return ['', '', ''];
    $solo_fecha = substr(trim((string)$fecha), 0, 10);
    $partes = explode('-', $solo_fecha);
    return (count($partes) === 3) ? [$partes[2], $partes[1], $partes[0]] : ['', '', ''];
}

function obtenerDato($fila, $opciones) {
    if (!$fila || !is_array($fila)) return '';
    foreach ((array)$opciones as $opcion) {
        foreach ($fila as $columna => $valor) {
            if (strcasecmp($columna, $opcion) === 0 && !empty(trim((string)$valor))) {
                return trim((string)$valor);
            }
        }
    }
    return '';
}

function obtenerSeccionAlumno($alumno) {
    return obtenerDato($alumno, ['seccion', 'sección', 'Seccion', 'Sección']);
}

$config_escuela = 'Escuela Gladys C. Lobo de Carnevali';
$config_periodo = '';
try {
    $st_conf = $pdo->query("SELECT `clave`, `valor` FROM `configuracion` WHERE `clave` IN ('nombre_escuela', 'periodo_escolar', 'estado')");
    if ($st_conf) {
        $conf_tmp = [];
        while ($row_c = $st_conf->fetch(PDO::FETCH_ASSOC)) {
            $conf_tmp[$row_c['clave']] = trim((string)$row_c['valor']);
        }
        $estado_cfg = $conf_tmp['estado'] ?? 'Activo';
        if ($estado_cfg === 'Activo') {
            if (!empty($conf_tmp['nombre_escuela'])) $config_escuela = $conf_tmp['nombre_escuela'];
            if (!empty($conf_tmp['periodo_escolar'])) $config_periodo = $conf_tmp['periodo_escolar'];
        }
    }
} catch (Exception $e) {}

$grados_disponibles = ['1' => true, '2' => true, '3' => true, '4' => true, '5' => true, '6' => true];
$secciones_disponibles = ['A' => true, 'B' => true, 'C' => true, 'D' => true, 'Única' => true];
$periodos_disponibles = [];
if (!empty($config_periodo)) {
    $periodos_disponibles[$config_periodo] = true;
}

try {
    $sql_opt = "SELECT DISTINCT g.nombre_grado as grado, s.nombre_seccion as sección 
                FROM inscripcion i 
                LEFT JOIN grados g ON i.id_grado = g.id 
                LEFT JOIN secciones s ON i.id_seccion = s.id";
    $res_opt = $pdo->query($sql_opt);
    if ($res_opt) {
        foreach ($res_opt->fetchAll(PDO::FETCH_ASSOC) as $opt) {
            $g = obtenerDato($opt, ['grado']);
            $s = obtenerDato($opt, ['sección', 'seccion']);
            if (!empty($g)) $grados_disponibles[$g] = true;
            if (!empty($s)) $secciones_disponibles[$s] = true;
        }
    }
} catch (Exception $e) {}

uksort($grados_disponibles, function($a, $b) { return strnatcmp($a, $b); });
uksort($secciones_disponibles, function($a, $b) { return strnatcmp($a, $b); });

$tipo_busqueda  = trim($_GET['tipo_busqueda'] ?? 'cedula');
$valor_busqueda = trim($_GET['valor'] ?? '');
$filtro_grado   = trim($_GET['filtro_grado'] ?? '');
$filtro_seccion = trim($_GET['filtro_seccion'] ?? '');
$filtro_periodo = trim($_GET['filtro_periodo'] ?? '');
$cedula_generar = trim($_GET['generar_cedula'] ?? '');

if ($tipo_busqueda === 'cedula') {
    $valor_busqueda = preg_replace('/[^0-9]/', '', $valor_busqueda);
    if (strlen($valor_busqueda) > 15) $valor_busqueda = substr($valor_busqueda, 0, 15);
} else {
    $valor_busqueda = preg_replace('/[^A-Za-zÁÉÍÓÚáéíóúñÑ\s]/u', '', $valor_busqueda);
    if (strlen($valor_busqueda) > 50) $valor_busqueda = substr($valor_busqueda, 0, 50);
}

$resultados_busqueda = [];
$estudiante = null;
$hermanos = [];
$error = "";

if (!empty($cedula_generar)) {
    try {
        $sql_planilla = "SELECT e.*,
                                g.`nombre_grado` AS grado,
                                s.`nombre_seccion` AS seccion,
                                i.`año_escolar`, i.`tipo_estudiante`, i.`plantel_procedencia`,
                                r.`nombres` AS rep_nombres, r.`apellidos` AS rep_apellidos, r.`cédula_identidad` AS rep_ci, r.`rif` AS rep_rif, r.`correo_electrónico` AS rep_correo, r.`teléfono_celular` AS rep_telf, r.`teléfono_residencia` AS rep_telres, r.`parentesco` AS rep_parentesco, r.`autorización_legal` AS rep_aut, r.`número_poder_notariado` AS rep_poder,
                                m.`nombres` AS mad_nombres, m.`apellidos` AS mad_apellidos, m.`cédula_identidad` AS mad_ci, m.`teléfono` AS mad_telf, m.`fecha_nacimiento` AS mad_fnac, m.`estado_civil` AS mad_eciv, m.`lugar_nacimiento` AS mad_lnac, m.`correo` AS mad_correo, m.`dirección_habitación` AS mad_dir, m.`nivel_instrucción` AS mad_niv, m.`profesion` AS mad_prof, m.`ocupación` AS mad_ocupa, m.`empresa` AS mad_emp, m.`teléfono_empresa` AS mad_temp, m.`dirección_empresa` AS mad_diremp,
                                p.`nombres` AS pad_nombres, p.`apellidos` AS pad_apellidos, p.`cédula_identidad` AS pad_ci, p.`teléfono` AS pad_telf, p.`fecha_nacimiento` AS pad_fnac, p.`estado_civil` AS pad_eciv, p.`lugar_nacimiento` AS pad_lnac, p.`correo` AS pad_correo, p.`dirección_habitación` AS pad_dir, p.`nivel_instrucción` AS pad_niv, p.`profesion` AS pad_prof, p.`ocupación` AS pad_ocupa, p.`empresa` AS pad_emp, p.`teléfono_empresa` AS pad_temp, p.`dirección_empresa` AS pad_diremp,
                                v.`condición_vivienda`, v.`tipo_vivienda`,
                                am.`enfermedades_tratamiento`, am.`condición_importante`,
                                vac.`nombre_vacuna`,
                                mc.`nombre_medicamento`,
                                pc.`descripción` AS problema_comunidad_desc,
                                t_ent.`modalidad` AS entrada_modalidad,
                                t_ent.`parentesco_persona` AS entrada_parentesco,
                                t_ent.`nombres_apellidos_persona` AS entrada_persona,
                                t_sal.`modalidad` AS salida_modalidad,
                                t_sal.`parentesco_persona` AS salida_parentesco,
                                t_sal.`nombres_apellidos_persona` AS salida_persona
                         FROM `estudiante` e
                         LEFT JOIN `inscripcion` i ON e.`cédula_escolar` = i.`cédula_escolar_estudiante`
                         LEFT JOIN `grados` g ON i.`id_grado` = g.`id`
                         LEFT JOIN `secciones` s ON i.`id_seccion` = s.`id`
                         LEFT JOIN `representante` r ON e.`cédula_escolar` = r.`cédula_escolar_estudiante`
                         LEFT JOIN `madre` m ON e.`cédula_escolar` = m.`cédula_escolar_estudiante`
                         LEFT JOIN `padre` p ON e.`cédula_escolar` = p.`cédula_escolar_estudiante`
                         LEFT JOIN `vivienda` v ON e.`cédula_escolar` = v.`cédula_escolar_estudiante`
                         LEFT JOIN `antecedente_medico` am ON e.`cédula_escolar` = am.`cédula_escolar_estudiante`
                         LEFT JOIN `vacuna` vac ON e.`cédula_escolar` = vac.`cédula_escolar_estudiante`
                         LEFT JOIN `medicamento_contraindicado` mc ON e.`cédula_escolar` = mc.`cédula_escolar_estudiante`
                         LEFT JOIN `problema_comunidad` pc ON e.`cédula_escolar` = pc.`cédula_escolar_estudiante`
                         LEFT JOIN `traslado_estudiante` t_ent ON e.`cédula_escolar` = t_ent.`cédula_escolar_estudiante` AND t_ent.`tipo` = 'Entrada'
                         LEFT JOIN `traslado_estudiante` t_sal ON e.`cédula_escolar` = t_sal.`cédula_escolar_estudiante` AND t_sal.`tipo` = 'Salida'
                         WHERE e.`cédula_escolar` = :cedula
                         ORDER BY i.`id` DESC LIMIT 1";

        $stmt = $pdo->prepare($sql_planilla);
        $stmt->execute([':cedula' => $cedula_generar]);
        $estudiante = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        if ($estudiante && !empty($estudiante['cédula_escolar'])) {
            $stmt_h = $pdo->prepare("SELECT `nombres_apellidos`, `grado` FROM `hermano` WHERE `cédula_escolar_estudiante` = :ced LIMIT 2");
            $stmt_h->execute([':ced' => $estudiante['cédula_escolar']]);
            $hermanos = $stmt_h->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) {}
}

if (!empty($valor_busqueda) && !$estudiante) {
    try {
        if ($tipo_busqueda === 'cedula') {
            $sql = "SELECT e.*, g.nombre_grado as grado, s.nombre_seccion as sección, i.`año_escolar`
                    FROM estudiante e
                    LEFT JOIN inscripcion i ON e.`cédula_escolar` = i.cédula_escolar_estudiante
                    LEFT JOIN grados g ON i.id_grado = g.id
                    LEFT JOIN secciones s ON i.id_seccion = s.id
                    WHERE e.`cédula_escolar` = :valor OR e.`cédula_escolar` LIKE :valor_like
                    GROUP BY e.`cédula_escolar`
                    ORDER BY g.nombre_grado ASC, s.nombre_seccion ASC, e.apellidos ASC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['valor' => $valor_busqueda, 'valor_like' => "%$valor_busqueda%"]);
        } else {
            $sql = "SELECT e.*, g.nombre_grado as grado, s.nombre_seccion as sección, i.`año_escolar`
                    FROM estudiante e
                    LEFT JOIN inscripcion i ON e.`cédula_escolar` = i.cédula_escolar_estudiante
                    LEFT JOIN grados g ON i.id_grado = g.id
                    LEFT JOIN secciones s ON i.id_seccion = s.id
                    WHERE e.nombres LIKE :v1 OR e.apellidos LIKE :v2 OR CONCAT(TRIM(e.nombres), ' ', TRIM(e.apellidos)) LIKE :v3 OR CONCAT(TRIM(e.apellidos), ' ', TRIM(e.nombres)) LIKE :v4
                    GROUP BY e.`cédula_escolar`
                    ORDER BY g.nombre_grado ASC, s.nombre_seccion ASC, e.apellidos ASC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['v1' => "%$valor_busqueda%", 'v2' => "%$valor_busqueda%", 'v3' => "%$valor_busqueda%", 'v4' => "%$valor_busqueda%"]);
        }
        $resultados_busqueda = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $error = "Error en la consulta: " . $e->getMessage();
    }
} elseif ((!empty($filtro_grado) || !empty($filtro_seccion) || !empty($filtro_periodo)) && !$estudiante) {
    try {
        $condiciones = [];
        $params = [];
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
        if (!empty($filtro_periodo) && $filtro_periodo !== $config_periodo) {
            $condiciones[] = "TRIM(i.`año_escolar`) = :periodo";
            $params['periodo'] = $filtro_periodo;
        }

        $where_sql = count($condiciones) > 0 ? "WHERE " . implode(" AND ", $condiciones) : "";
        $sql = "SELECT e.*, g.nombre_grado as grado, s.nombre_seccion as sección, i.`año_escolar`
                FROM estudiante e
                INNER JOIN inscripcion i ON e.`cédula_escolar` = i.cédula_escolar_estudiante
                LEFT JOIN grados g ON i.id_grado = g.id
                LEFT JOIN secciones s ON i.id_seccion = s.id
                $where_sql
                GROUP BY e.`cédula_escolar`
                ORDER BY e.apellidos ASC, e.nombres ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $resultados_busqueda = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $error = "Error al filtrar: " . $e->getMessage();
    }
}

if ((!empty($valor_busqueda) || !empty($filtro_grado) || !empty($filtro_seccion) || !empty($filtro_periodo)) && empty($resultados_busqueda) && !$estudiante && empty($error)) {
    $error = "No se encontraron alumnos con los criterios seleccionados.";
}

list($est_d, $est_m, $est_a) = descomponerFecha($estudiante['fecha_nacimiento'] ?? '');
list($mad_d, $mad_m, $mad_a) = descomponerFecha($estudiante['mad_fnac'] ?? '');
list($pad_d, $pad_m, $pad_a) = descomponerFecha($estudiante['pad_fnac'] ?? '');

$ent_mod = mb_strtolower(trim($estudiante['entrada_modalidad'] ?? ''), 'UTF-8');
$viene_solo       = ($ent_mod === 'solo' || $ent_mod === 'sólo') ? 'X' : '';
$viene_acompanado = ($ent_mod === 'acompañado' || $ent_mod === 'acompanado') ? 'X' : '';

$sal_mod = mb_strtolower(trim($estudiante['salida_modalidad'] ?? ''), 'UTF-8');
$retira_solo       = ($sal_mod === 'solo' || $sal_mod === 'sólo') ? 'X' : '';
$retira_acompanado = ($sal_mod === 'acompañado' || $sal_mod === 'acompanado') ? 'X' : '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Planilla de Inscripción - Sistema de Inscripción</title>
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
        body.dark-mode table.lista-alumnos,
        body.dark-mode .form-control,
        body.dark-mode select {
            background-color: var(--bg-surface) !important;
            border-color: var(--border-color) !important;
            color: var(--text-primary) !important;
        }

        body.dark-mode table.lista-alumnos th { background-color: #1e293b !important; color: var(--text-primary) !important; border-color: var(--border-color) !important; }
        body.dark-mode table.lista-alumnos td { background-color: var(--bg-surface) !important; color: var(--text-primary) !important; border-color: var(--border-color) !important; }
        body.dark-mode table.lista-alumnos tr:nth-child(even) td { background-color: var(--bg-surface-alt) !important; }

        html body.dark-mode .planilla-wrapper {
            background-color: #ffffff !important;
            color: #000000 !important;
        }

        html body.dark-mode .planilla-wrapper * {
            color: #000000 !important;
        }

        html body.dark-mode .planilla-wrapper table.p-table,
        html body.dark-mode .planilla-wrapper table.p-table tr,
        html body.dark-mode .planilla-wrapper table.p-table td,
        html body.dark-mode .planilla-wrapper table.fotos-table,
        html body.dark-mode .planilla-wrapper table.fotos-table tr,
        html body.dark-mode .planilla-wrapper table.fotos-table td {
            background-color: #ffffff !important;
            background: #ffffff !important;
            color: #000000 !important;
            border-color: #000000 !important;
        }

        html body.dark-mode .planilla-wrapper table.firmas-table,
        html body.dark-mode .planilla-wrapper table.firmas-table tr,
        html body.dark-mode .planilla-wrapper table.firmas-table td {
            background-color: transparent !important;
            background: transparent !important;
            border: none !important;
            color: #000000 !important;
        }

        html body.dark-mode .planilla-wrapper .logo-banner {
            filter: none !important;
            mix-blend-mode: normal !important;
            opacity: 1 !important;
            background-color: transparent !important;
        }

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
            margin-bottom: 14px;
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

        .container-fluid {
            padding: 0;
            box-sizing: border-box;
            width: 100%;
            max-width: 1000px;
            margin: 0 auto;
            flex: 1;
            min-height: 0;
            display: flex;
            flex-direction: column;
        }

        .card { background: var(--bg-surface); padding: 20px; border-radius: 12px; box-shadow: var(--card-shadow); margin-bottom: 16px; border: 1px solid var(--border-color); box-sizing: border-box; }

        .table-responsive { width: 100%; max-height: 290px; overflow-y: auto; overflow-x: auto; border: 1px solid var(--border-color); border-radius: 8px; margin-top: 10px; }
        table.lista-alumnos { width: 100%; border-collapse: collapse; font-size: 13px; background-color: var(--bg-surface); color: var(--text-primary); }
        .lista-alumnos th, .lista-alumnos td { border: 1px solid var(--border-color); padding: 10px; text-align: left; vertical-align: middle; }
        .lista-alumnos th { background-color: #f1f5f9; font-weight: 700; position: sticky; top: 0; z-index: 2; }

        .planilla-scroll-box {
            flex: 1;
            min-height: 0;
            overflow-y: auto;
            overflow-x: auto;
            background-color: var(--bg-surface);
            border: 1px solid var(--border-color);
            border-radius: 10px;
            padding: 16px;
            box-shadow: var(--card-shadow);
        }

        .planilla-scroll-box::-webkit-scrollbar { width: 10px; height: 10px; }
        .planilla-scroll-box::-webkit-scrollbar-track { background: #e2e8f0; border-radius: 8px; }
        .planilla-scroll-box::-webkit-scrollbar-thumb { background: #64748b; border-radius: 8px; }
        .planilla-scroll-box::-webkit-scrollbar-thumb:hover { background: #475569; }

        .planilla-wrapper {
            font-family: Cambria, Georgia, 'Times New Roman', serif;
            font-size: 11px;
            color: #000 !important;
            background-color: #fff !important;
            padding: 15px 20px;
            border-radius: 6px;
            width: 100%;
            max-width: 980px;
            margin: 0 auto;
            box-sizing: border-box;
        }

        .planilla-wrapper * { color: #000 !important; }
        .container-planilla { width: 100%; max-width: 980px; margin: 0 auto; box-sizing: border-box; padding: 5px 10px; }
        .top-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 4px; background-color: #ffffff !important; }
        .logo-banner { height: 62px; width: auto; object-fit: contain; background: transparent !important; }
        .fotos-table { border-collapse: collapse; }
        .fotos-table td { border: 1px solid #000 !important; width: 95px; height: 72px; vertical-align: top; text-align: center; font-family: Arial, sans-serif; font-size: 10px; font-weight: bold; padding-top: 4px; background-color: #fff !important; }

        .columns-container { display: flex; justify-content: space-between; align-items: flex-start; gap: 26px; width: 100%; }
        .col-left { width: 48.5%; box-sizing: border-box; }
        .col-right { width: 48.5%; box-sizing: border-box; }

        .intro-lines { font-family: Arial, sans-serif; font-size: 10.5px; font-weight: bold; line-height: 1.45; margin-bottom: 4px; white-space: nowrap; }
        .sec-heading { text-align: center; font-weight: bold; font-size: 11px; margin: 4px 0 2px 0; text-transform: uppercase; }

        table.p-table { width: 100%; border-collapse: collapse; margin-top: -1px; table-layout: fixed; background-color: #fff !important; }
        table.p-table td { border: 1px solid #000 !important; padding: 3px 5px; font-size: 10.5px; vertical-align: top; line-height: 1.25; word-wrap: break-word; background-color: #fff !important; }

        .val-inline { font-weight: normal; color: #111 !important; }
        .val-block { display: block; min-height: 14px; font-weight: normal; color: #111 !important; margin-top: 2px; }
        .center-cell { text-align: center; vertical-align: middle !important; }

        .caja-talla {
            display: inline-block;
            border: 1px solid #000;
            width: 28px;
            height: 18px;
            text-align: center;
            line-height: 18px;
            font-weight: bold;
            font-family: Arial, sans-serif;
            background-color: #fff !important;
            margin-left: 3px;
            vertical-align: middle;
        }

        .nota-medica { font-size: 9px; line-height: 1.25; text-align: justify; margin: 6px 0; font-family: Cambria, Georgia, serif; }

        .acta-section { margin-top: 25px; padding-top: 10px; font-family: Arial, sans-serif; font-size: 10px; page-break-inside: avoid; }
        .acta-title { text-align: center; font-weight: bold; font-size: 11.5px; text-decoration: underline; margin-bottom: 10px; }
        .acta-subtitle { text-align: center; font-weight: bold; font-size: 10.5px; text-decoration: underline; margin: 10px 0 6px 0; }
        .acta-text { line-height: 1.45; text-align: justify; margin-bottom: 8px; }
        .firmas-table { width: 100%; margin-top: 38px; border-collapse: collapse; text-align: center; font-size: 9.5px; background: transparent !important; }
        .firmas-table td { border: none !important; width: 33.33%; vertical-align: top; background: transparent !important; }
        .linea-firma { width: 75%; margin: 0 auto 4px auto; border-top: 1px solid #000; }

        @media print {
            @page { size: letter portrait; margin: 8mm; }
            .no-print, header, nav, footer, button, form, .sidebar, .top-header-bar { display: none !important; }
            html, body { height: auto !important; max-height: none !important; overflow: visible !important; background: #ffffff !important; margin: 0 !important; padding: 0 !important; width: 100% !important; display: block !important; }
            .main-content { height: auto !important; max-height: none !important; overflow: visible !important; padding: 0 !important; margin: 0 !important; width: 100% !important; display: block !important; }
            .container-fluid { padding: 0 !important; margin: 0 !important; max-width: 100% !important; width: 100% !important; display: block !important; }
            .planilla-scroll-box { overflow: visible !important; border: none !important; padding: 0 !important; box-shadow: none !important; height: auto !important; }
            .planilla-wrapper { padding: 0 !important; box-shadow: none !important; border: none !important; max-width: 100% !important; width: 100% !important; }
            .container-planilla { max-width: 100% !important; width: 100% !important; padding: 0 !important; }
        }
    </style>
</head>
<body>

    <?php include("menu.php"); ?>

    <main class="main-content">
        <div class="top-header-bar no-print">
            <div class="top-breadcrumb">
                <i class="fas fa-file-signature" style="color: #2563eb;"></i>
                <span>Módulo de Planilla de Inscripción</span>
            </div>

            <div class="top-actions">
                <a href="historial_planillas.php" class="tool-action-btn">
                    <i class="fas fa-history" style="color: #2563eb;"></i> Historial de Planilla
                </a>

                <button type="button" class="tool-action-btn btn-refresh-cache" onclick="actualizarDashboard()" title="Actualizar datos del sistema">
                    <i class="fas fa-sync-alt" id="refreshIcon"></i> Actualizar Sistema
                </button>

                <button type="button" class="btn-theme-switch" onclick="toggleDashboardTheme()" title="Cambiar Modo Claro / Oscuro">
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

        <div class="container-fluid">

            <?php if (!$estudiante) { ?>
                <div class="card no-print">
                    <h2 style="font-size: 1.15rem; font-weight: 700; margin-bottom: 15px; color: var(--text-primary);">
                        <i class="fas fa-search text-primary" style="color: #2563eb;"></i> Seleccionar Estudiante para Planilla de Inscripción
                    </h2>

                    <form method="GET" action="planilla_inscripcion.php" style="margin-bottom: 15px;">
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

                    <form method="GET" action="planilla_inscripcion.php" style="margin:0;">
                        <div style="font-size: 0.82rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 5px;">Filtrar por Grado (1° a 6°), Sección y Período:</div>
                        <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                            <select name="filtro_grado" class="form-control" style="padding: 8px; border-radius: 6px; border: 1px solid var(--border-color); width: 180px; font-size: 0.88rem;" required>
                                <option value="">Seleccione Grado...</option>
                                <?php foreach ($grados_disponibles as $g => $v) { ?>
                                    <option value="<?php echo htmlspecialchars($g, ENT_QUOTES, 'UTF-8'); ?>" <?php echo ($filtro_grado == $g) ? 'selected' : ''; ?>><?php echo htmlspecialchars($g, ENT_QUOTES, 'UTF-8'); ?>° Grado</option>
                                <?php } ?>
                            </select>

                            <select name="filtro_seccion" class="form-control" style="padding: 8px; border-radius: 6px; border: 1px solid var(--border-color); width: 140px; font-size: 0.88rem;" required>
                                <option value="">Sección...</option>
                                <?php foreach ($secciones_disponibles as $s => $v) { ?>
                                    <option value="<?php echo htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); ?>" <?php echo ($filtro_seccion == $s) ? 'selected' : ''; ?>>Sección <?php echo htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); ?></option>
                                <?php } ?>
                            </select>

                            <select name="filtro_periodo" class="form-control" style="padding: 8px; border-radius: 6px; border: 1px solid var(--border-color); width: 160px; font-size: 0.88rem;" required>
                                <option value="">Período...</option>
                                <?php foreach ($periodos_disponibles as $p => $v) { ?>
                                    <option value="<?php echo htmlspecialchars($p, ENT_QUOTES, 'UTF-8'); ?>" <?php echo ($filtro_periodo == $p) ? 'selected' : ''; ?>><?php echo htmlspecialchars($p, ENT_QUOTES, 'UTF-8'); ?></option>
                                <?php } ?>
                            </select>

                            <button type="submit" style="padding: 8px 16px; background: #059669; color: #fff; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 0.9rem;">
                                <i class="fas fa-filter"></i> Filtrar Alumnos
                            </button>

                            <a href="planilla_inscripcion.php" style="padding: 8px 14px; background: #64748b; color: #fff; border-radius: 6px; text-decoration: none; font-weight: 600; font-size: 0.9rem;">Limpiar</a>
                        </div>
                    </form>

                    <?php if (!empty($error)) { ?>
                        <div style="color: #721c24; background-color: #f8d7da; border: 1px solid #f5c6cb; padding: 12px; border-radius: 8px; margin-top: 15px; font-weight: bold; font-size: 0.9rem;"><?php echo $error; ?></div>
                    <?php } ?>
                </div>

                <?php if (!empty($resultados_busqueda)) { ?>
                    <div class="card no-print" style="margin-bottom: 0;">
                        <h3 style="font-size: 1rem; font-weight: 700; margin-bottom: 10px; color: var(--text-primary);">Lista de Alumnos Encontrados (Seleccione uno para generar la planilla):</h3>
                        <div class="table-responsive">
                            <table class="lista-alumnos">
                                <thead>
                                    <tr>
                                        <th style="width: 50px; text-align: center;">N°</th>
                                        <th>Cédula Escolar</th>
                                        <th>Nombres y Apellidos</th>
                                        <th>Grado</th>
                                        <th>Sección</th>
                                        <th style="text-align: center; width: 190px;">Acción</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $contador = 1;
                                    foreach ($resultados_busqueda as $alum) {
                                        $cedula_alum = obtenerDato($alum, ['cédula_escolar', 'cedula_escolar']);
                                    ?>
                                        <tr>
                                            <td style="text-align: center; font-weight: bold; color: var(--text-secondary);"><?php echo $contador++; ?></td>
                                            <td><strong><?php echo htmlspecialchars($cedula_alum, ENT_QUOTES, 'UTF-8'); ?></strong></td>
                                            <td><?php echo htmlspecialchars(obtenerDato($alum, ['nombres']) . ' ' . obtenerDato($alum, ['apellidos']), ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?php echo htmlspecialchars(obtenerDato($alum, ['grado']), ENT_QUOTES, 'UTF-8'); ?>°</td>
                                            <td><?php echo htmlspecialchars(obtenerSeccionAlumno($alum), ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td style="text-align: center;">
                                                <a href="planilla_inscripcion.php?generar_cedula=<?php echo urlencode($cedula_alum); ?>&tipo_busqueda=<?php echo urlencode($tipo_busqueda); ?>&valor=<?php echo urlencode($valor_busqueda); ?>&filtro_grado=<?php echo urlencode($filtro_grado); ?>&filtro_seccion=<?php echo urlencode($filtro_seccion); ?>&filtro_periodo=<?php echo urlencode($filtro_periodo); ?>"
                                                   style="padding: 6px 12px; background: #2563eb; color: white; border-radius: 6px; text-decoration: none; font-weight: 600; font-size: 0.82rem; display: inline-flex; align-items: center; gap: 5px;">
                                                    <i class="fas fa-file-signature"></i> Generar Planilla
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

            <?php if ($estudiante) {
                $nom_completo_js = trim(($estudiante['nombres'] ?? '') . ' ' . ($estudiante['apellidos'] ?? ''));
                $ced_escolar_js  = trim($estudiante['cédula_escolar'] ?? '');
            ?>
                <div class="no-print" style="width: 100%; max-width: 980px; margin: 0 auto 10px auto; display: flex; gap: 10px; justify-content: space-between; align-items: center; flex-wrap: wrap; flex-shrink: 0;">
                    <a href="planilla_inscripcion.php?tipo_busqueda=<?php echo urlencode($tipo_busqueda); ?>&valor=<?php echo urlencode($valor_busqueda); ?>&filtro_grado=<?php echo urlencode($filtro_grado); ?>&filtro_seccion=<?php echo urlencode($filtro_seccion); ?>&filtro_periodo=<?php echo urlencode($filtro_periodo); ?>" style="padding: 7px 14px; background: #64748b; color: #fff; border-radius: 6px; text-decoration: none; font-weight: 600; font-size: 0.85rem;">
                        <i class="fas fa-arrow-left"></i> Volver al Listado
                    </a>

                    <div style="display: flex; gap: 10px; align-items: center;">
                        <button type="button" onclick="imprimirYRegistrarPlanilla('<?php echo htmlspecialchars($nom_completo_js, ENT_QUOTES, 'UTF-8'); ?>', '<?php echo htmlspecialchars($ced_escolar_js, ENT_QUOTES, 'UTF-8'); ?>')" style="padding: 8px 16px; background: #059669; color: #fff; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 0.88rem;">
                            🖨️ Imprimir Planilla / Guardar PDF
                        </button>
                    </div>
                </div>

                <div class="planilla-scroll-box">
                    <div class="planilla-wrapper">
                        <div class="container-planilla">
                            <div class="top-header">
                                <div>
                                    <img src="imagenes/logo_izquierda.png" alt="Ministerio de Educación" class="logo-banner">
                                </div>
                                <table class="fotos-table">
                                    <tr>
                                        <td>Foto del<br>Alumno</td>
                                        <td>Foto del<br>Representante</td>
                                    </tr>
                                </table>
                            </div>

                            <div class="columns-container">
                                <div class="col-left">
                                    <div class="intro-lines">
                                        PLANILLA DE INSCRIPCIÓN - AÑO ESCOLAR: <u><?php echo htmlspecialchars($config_periodo, ENT_QUOTES, 'UTF-8'); ?></u>    GRADO: <u>  <?php echo htmlspecialchars(!empty($estudiante['grado']) ? $estudiante['grado'] . ((!empty($estudiante['seccion'])) ? ' "' . $estudiante['seccion'] . '"' : '') : '        ', ENT_QUOTES, 'UTF-8'); ?>  </u><br>
                                        Estudiante regular: <u> <?php echo (($estudiante['tipo_estudiante'] ?? '') === 'Estudiante regular') ? 'X' : ' '; ?> </u>   Nuevo ingreso: <u>  <?php echo (($estudiante['tipo_estudiante'] ?? '') === 'Nuevo ingreso') ? 'X' : '    '; ?>  </u><br>
                                        Plantel de procedencia: <u> <?php echo htmlspecialchars(!empty($estudiante['plantel_procedencia']) ? $estudiante['plantel_procedencia'] : '                              ', ENT_QUOTES, 'UTF-8'); ?> </u>
                                    </div>

                                    <div class="sec-heading">DATOS DEL ESTUDIANTE:</div>
                                    <table class="p-table" style="margin-top: 0;">
                                        <tr>
                                            <td>
                                                Nombres y apellidos:
                                                <span class="val-block"><?php echo htmlspecialchars(trim(($estudiante['nombres'] ?? '') . ' ' . ($estudiante['apellidos'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>
                                                Cédula escolar/identidad:
                                                <span class="val-block"><?php echo htmlspecialchars($estudiante['cédula_escolar'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
                                            </td>
                                        </tr>
                                    </table>
                                    <table class="p-table">
                                        <tr>
                                            <td colspan="3" style="width: 44%;">Fecha de nacimiento</td>
                                            <td style="width: 56%;">Nacionalidad</td>
                                        </tr>
                                        <tr>
                                            <td class="center-cell" style="height: 16px;"><?php echo htmlspecialchars($est_d, ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td class="center-cell" style="height: 16px;"><?php echo htmlspecialchars($est_m, ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td class="center-cell" style="height: 16px;"><?php echo htmlspecialchars($est_a, ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td class="center-cell" style="height: 16px;"><?php echo htmlspecialchars($estudiante['nacionalidad'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                        </tr>
                                    </table>
                                    <table class="p-table">
                                        <tr>
                                            <td style="width: 50%;">Peso <span class="val-inline"><?php echo htmlspecialchars($estudiante['peso'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span></td>
                                            <td style="width: 50%;">Talla <span class="val-inline"><?php echo htmlspecialchars($estudiante['talla'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span></td>
                                        </tr>
                                        <tr>
                                            <td style="width: 50%;">
                                                Talla de Camisa:
                                                <span class="caja-talla"><?php echo htmlspecialchars($estudiante['talla_camisa'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
                                            </td>
                                            <td style="width: 50%;">
                                                Talla de Pantalón:
                                                <span class="caja-talla"><?php echo htmlspecialchars($estudiante['talla_pantalón'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td colspan="2">
                                                Calzado:
                                                <span class="caja-talla"><?php echo htmlspecialchars($estudiante['calzado'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td colspan="2">Lugar de nacimiento: <span class="val-inline"><?php echo htmlspecialchars($estudiante['lugar_nacimiento'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span></td>
                                        </tr>
                                        <tr>
                                            <td colspan="2">Municipio de nacimiento: <span class="val-inline"><?php echo htmlspecialchars($estudiante['municipio_nacimiento'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span></td>
                                        </tr>
                                        <tr>
                                            <td colspan="2">
                                                Dirección de domicilio:
                                                <span class="val-block"><?php echo htmlspecialchars($estudiante['dirección_domicilio'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td colspan="2">Religión: <span class="val-inline"><?php echo htmlspecialchars($estudiante['religión'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span></td>
                                        </tr>
                                        <tr>
                                            <td colspan="2">¿Con quién vive el estudiante? <span class="val-inline"><?php echo htmlspecialchars($estudiante['con_quien_vive'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span></td>
                                        </tr>
                                        <tr>
                                            <td colspan="2">¿Sus padres están separados? <span class="val-inline"><?php echo isset($estudiante['padres_separados']) ? ($estudiante['padres_separados'] == 1 ? 'Sí' : 'No') : ''; ?></span></td>
                                        </tr>
                                    </table>
                                    <table class="p-table">
                                        <tr>
                                            <td style="width: 52%;">¿Ha repetido grados?</td>
                                            <td style="width: 23%;">Sí <?php echo (isset($estudiante['ha_repetido_grado']) && $estudiante['ha_repetido_grado'] == 1) ? '<strong>(X)</strong>' : ''; ?></td>
                                            <td style="width: 25%;">No <?php echo (isset($estudiante['ha_repetido_grado']) && $estudiante['ha_repetido_grado'] == 0 && !empty($estudiante)) ? '<strong>(X)</strong>' : ''; ?></td>
                                        </tr>
                                        <tr>
                                            <td colspan="3" style="height: 22px;">Especifique cuales: <span class="val-inline"><?php echo htmlspecialchars($estudiante['grados_repetidos'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span></td>
                                        </tr>
                                    </table>
                                    <table class="p-table">
                                        <tr>
                                            <td style="width: 72%;">Presenta necesidades educativas<br>especiales</td>
                                            <td style="width: 12%;">Sí <?php echo (isset($estudiante['necesidades_especiales']) && $estudiante['necesidades_especiales'] == 1) ? '<strong>(X)</strong>' : ''; ?></td>
                                            <td style="width: 16%;">No <?php echo (isset($estudiante['necesidades_especiales']) && $estudiante['necesidades_especiales'] == 0 && !empty($estudiante)) ? '<strong>(X)</strong>' : ''; ?></td>
                                        </tr>
                                    </table>
                                    <div style="font-size: 10.5px; padding: 2px 4px; min-height: 26px;">
                                        Describa detalladamente la condición que presenta
                                        <span class="val-block"><?php echo htmlspecialchars($estudiante['descripción_condición'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
                                    </div>
                                    <table class="p-table" style="margin-top: 0;">
                                        <tr>
                                            <td style="width: 55%;">¿Posee informe emitido por un<br>especialista? <span class="val-inline"><?php echo isset($estudiante['informe_especialista']) ? ($estudiante['informe_especialista'] == 1 ? 'Sí' : 'No') : ''; ?></span></td>
                                            <td style="width: 45%;">¿Ha sido atendido por<br>aula Integrada? <span class="val-inline"><?php echo isset($estudiante['atendido_aula_integrada']) ? ($estudiante['atendido_aula_integrada'] == 1 ? 'Sí' : 'No') : ''; ?></span></td>
                                        </tr>
                                    </table>

                                    <div class="sec-heading">DATOS DEL REPRESENTANTE</div>
                                    <table class="p-table" style="margin-top: 0;">
                                        <tr>
                                            <td colspan="2" style="height: 24px;">
                                                Nombres y apellidos: <span class="val-inline"><?php echo htmlspecialchars(trim(($estudiante['rep_nombres'] ?? '') . ' ' . ($estudiante['rep_apellidos'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td style="width: 58%; height: 22px;">Cédula de identidad: <span class="val-inline"><?php echo htmlspecialchars($estudiante['rep_ci'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span></td>
                                            <td style="width: 42%; height: 22px;">Rif: <span class="val-inline"><?php echo htmlspecialchars($estudiante['rep_rif'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span></td>
                                        </tr>
                                        <tr>
                                            <td colspan="2">Correo electrónico: <span class="val-inline"><?php echo htmlspecialchars($estudiante['rep_correo'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span></td>
                                        </tr>
                                        <tr>
                                            <td colspan="2" style="height: 24px;">Números de contacto(celular, residencia) <span class="val-inline"><?php echo htmlspecialchars(trim(($estudiante['rep_telf'] ?? '') . ' ' . ($estudiante['rep_telres'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></span></td>
                                        </tr>
                                        <tr>
                                            <td colspan="2">Parentesco con el estudiante: <span class="val-inline"><?php echo htmlspecialchars($estudiante['rep_parentesco'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span></td>
                                        </tr>
                                        <tr>
                                            <td colspan="2">Posee autorización legal para ser representante: <span class="val-inline"><?php echo isset($estudiante['rep_aut']) ? ($estudiante['rep_aut'] == 1 ? 'Sí' : 'No') : ''; ?></span></td>
                                        </tr>
                                        <tr>
                                            <td colspan="2">Número del poder notariado: <span class="val-inline"><?php echo htmlspecialchars($estudiante['rep_poder'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span></td>
                                        </tr>
                                    </table>

                                    <div class="sec-heading">DATOS DE LA MADRE</div>
                                    <table class="p-table" style="margin-top: 0;">
                                        <tr>
                                            <td style="width: 70%; height: 26px;">
                                                Nombres y apellidos:
                                                <span class="val-block"><?php echo htmlspecialchars(trim(($estudiante['mad_nombres'] ?? '') . ' ' . ($estudiante['mad_apellidos'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></span>
                                            </td>
                                            <td style="width: 30%; height: 26px;">
                                                C.I.:
                                                <span class="val-block"><?php echo htmlspecialchars($estudiante['mad_ci'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
                                            </td>
                                        </tr>
                                    </table>
                                    <table class="p-table">
                                        <tr>
                                            <td rowspan="2" style="width: 28%;">
                                                Teléfono:
                                                <span class="val-block"><?php echo htmlspecialchars($estudiante['mad_telf'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
                                            </td>
                                            <td colspan="3" style="width: 36%; font-size: 9.5px;">Fecha de nacimiento:</td>
                                            <td style="width: 36%;">Estado civil</td>
                                        </tr>
                                        <tr>
                                            <td class="center-cell" style="height: 16px;"><?php echo htmlspecialchars($mad_d, ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td class="center-cell" style="height: 16px;"><?php echo htmlspecialchars($mad_m, ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td class="center-cell" style="height: 16px;"><?php echo htmlspecialchars($mad_a, ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td class="center-cell" style="height: 16px;"><?php echo htmlspecialchars($estudiante['mad_eciv'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                        </tr>
                                    </table>
                                    <table class="p-table">
                                        <tr>
                                            <td colspan="2">Lugar de nacimiento: <span class="val-inline"><?php echo htmlspecialchars($estudiante['mad_lnac'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span></td>
                                        </tr>
                                        <tr>
                                            <td colspan="2">Correo: <span class="val-inline"><?php echo htmlspecialchars($estudiante['mad_correo'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span></td>
                                        </tr>
                                        <tr>
                                            <td colspan="2">Dirección de habitación: <span class="val-inline"><?php echo htmlspecialchars($estudiante['mad_dir'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span></td>
                                        </tr>
                                        <tr>
                                            <td style="width: 50%;">Nivel de instrucción: <span class="val-inline"><?php echo htmlspecialchars($estudiante['mad_niv'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span></td>
                                            <td style="width: 50%;">Profesión: <span class="val-inline"><?php echo htmlspecialchars($estudiante['mad_prof'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span></td>
                                        </tr>
                                        <tr>
                                            <td>Ocupación: <span class="val-inline"><?php echo htmlspecialchars($estudiante['mad_ocupa'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span></td>
                                            <td>Empresa: <span class="val-inline"><?php echo htmlspecialchars($estudiante['mad_emp'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span></td>
                                        </tr>
                                    </table>
                                    <table class="p-table">
                                        <tr>
                                            <td style="width: 40%; height: 28px;">
                                                Teléf. de empresa:
                                                <span class="val-block"><?php echo htmlspecialchars($estudiante['mad_temp'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
                                            </td>
                                            <td style="width: 60%; height: 28px;">
                                                Dirección de la empresa:
                                                <span class="val-block"><?php echo htmlspecialchars($estudiante['mad_diremp'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
                                            </td>
                                        </tr>
                                    </table>

                                    <div class="sec-heading">DATOS DEL PADRE</div>
                                    <table class="p-table" style="margin-top: 0;">
                                        <tr>
                                            <td style="width: 72%; height: 26px;">
                                                Nombres y apellidos:
                                                <span class="val-block"><?php echo htmlspecialchars(trim(($estudiante['pad_nombres'] ?? '') . ' ' . ($estudiante['pad_apellidos'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></span>
                                            </td>
                                            <td style="width: 28%; height: 26px;">
                                                C.I.:
                                                <span class="val-block"><?php echo htmlspecialchars($estudiante['pad_ci'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
                                            </td>
                                        </tr>
                                    </table>
                                    <table class="p-table">
                                        <tr>
                                            <td rowspan="2" style="width: 30%; font-size: 9.5px;">
                                                Teléfono:
                                                <span class="val-block"><?php echo htmlspecialchars($estudiante['pad_telf'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
                                            </td>
                                            <td colspan="3" style="width: 36%; font-size: 9px;">Fecha de nacimiento:</td>
                                            <td style="width: 34%;">Estado civil</td>
                                        </tr>
                                        <tr>
                                            <td class="center-cell" style="height: 16px;"><?php echo htmlspecialchars($pad_d, ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td class="center-cell" style="height: 16px;"><?php echo htmlspecialchars($pad_m, ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td class="center-cell" style="height: 16px;"><?php echo htmlspecialchars($pad_a, ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td class="center-cell" style="height: 16px;"><?php echo htmlspecialchars($estudiante['pad_eciv'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                        </tr>
                                    </table>
                                    <table class="p-table">
                                        <tr>
                                            <td style="height: 26px;">Lugar de nacimiento: <span class="val-inline"><?php echo htmlspecialchars($estudiante['pad_lnac'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span></td>
                                        </tr>
                                    </table>
                                </div>

                                <div class="col-right">
                                    <table class="p-table" style="margin-top: 12px;">
                                        <tr>
                                            <td colspan="2">Correo: <span class="val-inline"><?php echo htmlspecialchars($estudiante['pad_correo'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span></td>
                                        </tr>
                                        <tr>
                                            <td colspan="2">Dirección de habitación: <span class="val-inline"><?php echo htmlspecialchars($estudiante['pad_dir'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span></td>
                                        </tr>
                                        <tr>
                                            <td style="width: 52%;">Nivel de instrucción: <span class="val-inline"><?php echo htmlspecialchars($estudiante['pad_niv'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span></td>
                                            <td style="width: 48%;">Profesión: <span class="val-inline"><?php echo htmlspecialchars($estudiante['pad_prof'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span></td>
                                        </tr>
                                        <tr>
                                            <td>Ocupación: <span class="val-inline"><?php echo htmlspecialchars($estudiante['pad_ocupa'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span></td>
                                            <td>Empresa: <span class="val-inline"><?php echo htmlspecialchars($estudiante['pad_emp'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span></td>
                                        </tr>
                                    </table>
                                    <table class="p-table">
                                        <tr>
                                            <td style="width: 40%; height: 30px;">
                                                Teléf. de empresa:
                                                <span class="val-block"><?php echo htmlspecialchars($estudiante['pad_temp'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
                                            </td>
                                            <td style="width: 60%; height: 30px;">
                                                Dirección de la empresa:
                                                <span class="val-block"><?php echo htmlspecialchars($estudiante['pad_diremp'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
                                            </td>
                                        </tr>
                                    </table>

                                    <div class="sec-heading">CONDICIÓN DE LA VIVIENDA:</div>
                                    <table class="p-table" style="margin-top: 0;">
                                        <tr>
                                            <td style="width: 25%;">Alquilada</td>
                                            <td style="width: 25%;">Prestada</td>
                                            <td style="width: 25%;">Propia</td>
                                            <td style="width: 25%;">Pagándola</td>
                                        </tr>
                                        <tr>
                                            <td class="center-cell" style="height: 16px;"><?php echo (($estudiante['condición_vivienda'] ?? '') === 'Alquilada') ? '<strong>X</strong>' : ''; ?></td>
                                            <td class="center-cell" style="height: 16px;"><?php echo (($estudiante['condición_vivienda'] ?? '') === 'Prestada') ? '<strong>X</strong>' : ''; ?></td>
                                            <td class="center-cell" style="height: 16px;"><?php echo (($estudiante['condición_vivienda'] ?? '') === 'Propia') ? '<strong>X</strong>' : ''; ?></td>
                                            <td class="center-cell" style="height: 16px;"><?php echo (($estudiante['condición_vivienda'] ?? '') === 'Pagándola') ? '<strong>X</strong>' : ''; ?></td>
                                        </tr>
                                    </table>

                                    <div class="sec-heading">TIPO DE VIVIENDA</div>
                                    <table class="p-table" style="margin: 0 auto; width: 74%;">
                                        <tr>
                                            <td style="width: 44%;">Apartamento</td>
                                            <td style="width: 28%;">Casa</td>
                                            <td style="width: 28%;">Rancho</td>
                                        </tr>
                                        <tr>
                                            <td class="center-cell" style="height: 16px;"><?php echo (($estudiante['tipo_vivienda'] ?? '') === 'Apartamento') ? '<strong>X</strong>' : ''; ?></td>
                                            <td class="center-cell" style="height: 16px;"><?php echo (($estudiante['tipo_vivienda'] ?? '') === 'Casa') ? '<strong>X</strong>' : ''; ?></td>
                                            <td class="center-cell" style="height: 16px;"><?php echo (($estudiante['tipo_vivienda'] ?? '') === 'Rancho') ? '<strong>X</strong>' : ''; ?></td>
                                        </tr>
                                    </table>

                                    <table class="p-table" style="margin-top: 8px; width: 96%;">
                                        <tr>
                                            <td style="width: 82%;"><strong>Nombres de hermanos que estudian en la<br>escuela</strong></td>
                                            <td style="width: 18%;"><strong>Grado</strong></td>
                                        </tr>
                                        <tr>
                                            <td style="height: 18px;"><?php echo htmlspecialchars($hermanos[0]['nombres_apellidos'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td class="center-cell" style="height: 18px;"><?php echo htmlspecialchars($hermanos[0]['grado'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                        </tr>
                                        <tr>
                                            <td style="height: 18px;"><?php echo htmlspecialchars($hermanos[1]['nombres_apellidos'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td class="center-cell" style="height: 18px;"><?php echo htmlspecialchars($hermanos[1]['grado'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                        </tr>
                                    </table>

                                    <table class="p-table" style="margin-top: 8px;">
                                        <tr>
                                            <td style="font-size: 9px;"><strong>ANTECEDENTES PERSONALES DE PATOLOGÍAS DEL NIÑO /NIÑA:<br>especifique las enfermedades que ha padecido y tratamiento</strong></td>
                                        </tr>
                                        <tr>
                                            <td style="height: 18px;"><?php echo htmlspecialchars($estudiante['enfermedades_tratamiento'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                        </tr>
                                        <tr>
                                            <td style="height: 18px;"></td>
                                        </tr>
                                    </table>

                                    <table class="p-table" style="margin-top: 8px;">
                                        <tr>
                                            <td style="font-size: 9.5px;">Describa alguna condición importante que usted considere debe conocer<br>el docente por el bienestar de su representado.</td>
                                        </tr>
                                        <tr>
                                            <td style="height: 18px;"><?php echo htmlspecialchars($estudiante['condición_importante'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                        </tr>
                                        <tr>
                                            <td style="height: 18px;"></td>
                                        </tr>
                                    </table>

                                    <div class="nota-medica">
                                        NOTA: LOS REPRESENTANTES DEBEN PRESENTAR INFORME MÉDICO DE LAS ENFERMEDADES QUE PADECE EL NIÑO EMITIDO POR LOS ESPECIALISTAS.
                                    </div>

                                    <table class="p-table" style="margin-top: 6px;">
                                        <tr>
                                            <td class="center-cell" style="font-size: 9px; padding: 2px;"><strong>VACUNAS RECIBIDAS</strong></td>
                                        </tr>
                                        <tr>
                                            <td style="height: 30px;"><?php echo htmlspecialchars($estudiante['nombre_vacuna'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                        </tr>
                                    </table>

                                    <table class="p-table" style="margin-top: 8px;">
                                        <tr>
                                            <td class="center-cell" style="font-size: 9px; padding: 2px;"><strong>MEDICAMENTOS CONTRAINDICADOS</strong></td>
                                        </tr>
                                        <tr>
                                            <td style="height: 34px;"><?php echo htmlspecialchars($estudiante['nombre_medicamento'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                        </tr>
                                    </table>

                                    <table class="p-table" style="margin-top: 8px;">
                                        <tr>
                                            <td style="width: 50%;"><strong>¿Con quién se viene el<br>estudiante a la escuela?</strong></td>
                                            <td style="width: 50%;"><strong>¿Con quién se retira el<br>estudiante a su casa?</strong></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Sólo:</strong> <span class="val-inline"><?php echo $viene_solo ? '<strong>(X)</strong>' : ''; ?></span></td>
                                            <td><strong>Sólo:</strong> <span class="val-inline"><?php echo $retira_solo ? '<strong>(X)</strong>' : ''; ?></span></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Acompañado:</strong> <span class="val-inline"><?php echo $viene_acompanado ? '<strong>(X)</strong>' : ''; ?></span></td>
                                            <td><strong>Acompañado:</strong> <span class="val-inline"><?php echo $retira_acompanado ? '<strong>(X)</strong>' : ''; ?></span></td>
                                        </tr>
                                    </table>

                                    <table class="p-table" style="margin-top: 8px;">
                                        <tr>
                                            <td style="width: 50%;"><strong>Parentesco de la persona<br>que lo trae a la escuela</strong></td>
                                            <td style="width: 50%;"><strong>Nombres y apellidos de<br>quien lo trae a la escuela:</strong></td>
                                        </tr>
                                        <tr>
                                            <td style="height: 32px;"><?php echo htmlspecialchars($estudiante['entrada_parentesco'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td style="height: 32px;"><?php echo htmlspecialchars($estudiante['entrada_persona'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                        </tr>
                                    </table>

                                    <table class="p-table" style="margin-top: 8px;">
                                        <tr>
                                            <td style="width: 50%; font-size: 9.5px;"><strong>Parentesco de la persona que lo<br>retira de la escuela</strong></td>
                                            <td style="width: 50%; font-size: 9.5px;"><strong>Nombres y apellidos de quien lo<br>retira de la escuela:</strong></td>
                                        </tr>
                                        <tr>
                                            <td style="height: 32px;"><?php echo htmlspecialchars($estudiante['salida_parentesco'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td style="height: 32px;"><?php echo htmlspecialchars($estudiante['salida_persona'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                        </tr>
                                    </table>

                                    <table class="p-table" style="margin-top: 8px; width: 96%;">
                                        <tr>
                                            <td style="font-family: Arial, sans-serif; font-size: 10.5px; padding: 4px 6px;">
                                                <u>Describa los problemas que se presentan en su comunidad y<br>afectan a su núcleo familiar.</u>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td style="height: 20px;"><?php echo htmlspecialchars($estudiante['problema_comunidad_desc'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                        </tr>
                                        <tr>
                                            <td style="height: 20px;"></td>
                                        </tr>
                                        <tr>
                                            <td style="height: 20px;"></td>
                                        </tr>
                                    </table>
                                </div>
                            </div>

                            <div class="acta-section">
                                <div class="acta-title">Acta compromiso Académico y de Corresponsabilidad Familiar</div>
                                <?php
                                $meses_acta = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
                                $acta_dia = date('d');
                                $acta_mes = $meses_acta[date('n') - 1];
                                $acta_ano_actual = date('Y');
                                $acta_anio = !empty($config_periodo) ? htmlspecialchars($config_periodo, ENT_QUOTES, 'UTF-8') : '_______________________';
                                $acta_escuela = !empty($estudiante) ? htmlspecialchars($config_escuela, ENT_QUOTES, 'UTF-8') : '____________________________________________________________________';
                                $rep_nom_completo = trim(($estudiante['rep_nombres'] ?? '') . ' ' . ($estudiante['rep_apellidos'] ?? ''));
                                $acta_rep = !empty($rep_nom_completo) ? htmlspecialchars($rep_nom_completo, ENT_QUOTES, 'UTF-8') : '_____________________________________________________';
                                $acta_rep_ci = !empty($estudiante['rep_ci']) ? htmlspecialchars($estudiante['rep_ci'], ENT_QUOTES, 'UTF-8') : '____________________';
                                $est_nom_completo = trim(($estudiante['nombres'] ?? '') . ' ' . ($estudiante['apellidos'] ?? ''));
                                $acta_est = !empty($est_nom_completo) ? htmlspecialchars($est_nom_completo, ENT_QUOTES, 'UTF-8') : '_______________________________________________________________________';
                                ?>
                                <div class="acta-text">
                                    Hoy <u> <?php echo $acta_dia; ?> </u> de <u> <?php echo $acta_mes; ?> </u> del <u> <?php echo $acta_ano_actual; ?> </u> del año escolar <u> <?php echo $acta_anio; ?> </u>, reunidos en las instalaciones de la Institución <u> <?php echo $acta_escuela; ?> </u>, el (la) sr (a): <u> <?php echo $acta_rep; ?> </u>, titular de la C.I: <u> <?php echo $acta_rep_ci; ?> </u> Representante Legal del(la) estudiante <u> <?php echo $acta_est; ?> </u>, se deja constancia del presente compromiso se sustenta en la <strong>CRBV</strong> (Art. 102), la <strong>LOE</strong> (Art. 17 , 6 y 109) y la <strong>LOPNNA</strong> (Art. 5, 29, 53, 54 y 55), los cuales establecen la Corresponsabilidad obligatoria de las familias en el proceso educativo, así como el deber del representante de supervisar, garantizar la asistencia y asegurar el cumplimiento de los deberes escolares de su representado.
                                </div>

                                <div class="acta-subtitle">Compromisos de Corresponsabilidad</div>
                                <div class="acta-text">
                                    En aras de garantizar el <strong>Derecho a una Educación de Calidad</strong> y el cumplimiento de los deberes escolares, las partes establecen el siguiente acuerdo vinculante:<br>
                                    <strong>I. Compromiso del Estudiante:</strong> El/La estudiante se compromete a:<br>
                                    <u>Asistencia a Clases:</u> Cumpliendo el horario establecido y haciendo uso correcto del uniforme escolar. Los niños sin zarcillos, piercing, tatuajes, y con corte de cabello adecuado sin usar gomina, ni corte modernos: rayas, copetes, u/otros. Las niñas sin maquillaje, uñas largas o pintadas, con cintillos, cintas o ganchos azules, blancos o negros, evitar el uso de zarcillos y prendas extravagantes tanto en tamaño como color.<br>
                                    <u>Cumplimiento de tareas:</u> Cumplir a cabalidad con todas las tareas, asignaciones, materiales, investigaciones, evaluations y actividades propuestas por la docente, prestando atención durante las explicaciones.<br>
                                    <u>Actitud y comportamiento:</u> Mantener una actitud proactiva ante el aprendizaje, solicitando la ayuda de los docentes y/o compañeros cuando no comprenda algo, demostrando respeto a los acuerdos de convivencia, y una conducta adecuada en el aula y en la institución. Cuidar y hacer buen uso de las instalaciones y tratar con respeto a todo el personal que aquí labora.<br>
                                    Se considera representante del estudiante sus padres, tutor o persona debidamente autorizada por escrito ante organismos legales (Colocación familiar expedida por el CMDNNA).<br>
                                    <strong>II. Compromiso del Padre/Madre/Representante:</strong> El/La Representante se compromete a cumplir con la Corresponsabilidad en la educación de su representado, específicamente a:<br>
                                    <u>Asistencia a Clases:</u> Traer diariamente y de forma puntual a su representado a todas las clases y actividades escolares en el horario establecido. En caso de enfermedad y/u otra situación, comunicarse con la docente para justificar la ausencia del día. Teniendo en cuenta que con el 25% de inasistencias se reprueba el año escolar.<br>
                                    <u>Seguimiento y supervisión:</u> Revisar diariamente el cuaderno y las asignaciones escolares. Supervisar directamente que el estudiante cumpla con sus tareas y dedique un tiempo específico y adecuado a la edad (Lectura, Repaso, Estudio) en casa.<br>
                                    <u>Comunicación Constante:</u> Acudir a la institución cada vez que sea convocado (a) por la Docente de Aula o la Dirección, para informarse sobre el avance, aplicar correctivos y participar en la planificación. Cumpliendo con una vestimenta adecuada (sin escotes, minifaldas, pijamas, shorts, bermudas, licras, pantallas, pantalones rotas, entre otras). (Respetando los espacios y tiempos diseñados para ello).<br>
                                    <u>Provisión de Recursos:</u> Asegurar que el (la) estudiante disponga de los materiales de estudio (libros, cuadernos, útiles) necesarios para el desarrollo efectivo de las actividades, y un ambiente adecuado para el estudio. Evitando que mi representado acuda con equipos que generen distracción, peleas o gastos innecesarios.<br>
                                    <u>Apoyo especializado (si aplica):</u> Acompañar y autorizar a mi representado durante las jornadas médicas, garantizar que ingiera todos los alimentos suministrados por el Programa de Alimentación Escolar (PAE). Buscar y garantizar la atención de especialistas (psicopedagogo, psicólogo, terapista de lenguaje, neurólogo, etc.) si la institución lo sugiere para apoyar el proceso educativo integral del niño o niña.<br>
                                    <u>Garantizar:</u> el uso y cuidado correcto de las instalaciones y trato acorde al personal de la institución. Así como el respeto a la diversidad de conciencia, pensamientos religión y culturas. En caso de que mi representado incumpla, me hago responsable de los daños ocasionados conforme a lo estipulado en el decreto N° 2731 gaceta oficial N°31524.<br>
                                    <strong>III. Compromiso de la Institución:</strong> La Escuela, a través del Docente de Aula, se compromete a:<br>
                                    <u>Buen trato:</u> Garantiza el trato respetuoso, amable y cordial de todo el personal que aquí labora hacia su representado y padre, madre o representante. Así como el respeto a la diversidad de conciencia, pensamientos religión y culturas.<br>
                                    <u>Plan Pedagógico:</u> Diseñar y aplicar de forma sostenida y continúa el Plan Pedagógico y las adaptaciones curriculares en las áreas de mayor debilidad, utilizando estrategias diferenciadas según las necesidades detectadas.<br>
                                    <u>Seguimiento y Evaluación:</u> Realizar un seguimiento continuo y semanal del desempeño académico y conductual del estudiante. Mantener informado al representante sobre el progreso o las dificultades en el horario establecido. (Respetando los espacios y tiempos diseñados para ello).<br>
                                    <u>Orientación:</u> Ofrecer la orientación necesaria y pedagógica tanto al estudiante como al representante para superar las dificultades identificadas y promover el éxito escolar.<br>
                                    <strong>No nos haremos responsables por el extravió o daño de objetos que el estudiante traiga a la escuela: celulares, relojes, tablas, prendas personales: zarcillos, cadenas, esclavas, y/o cualquier otro que no requiera para sus actividades escolares y generan distracción.</strong>
                                </div>

                                <table class="firmas-table">
                                    <tr>
                                        <td>
                                            <div class="linea-firma"></div>
                                            Firma del Representante
                                        </td>
                                        <td>
                                            <div class="linea-firma"></div>
                                            Firma Del Director (E)<br>
                                            <span style="font-size: 8px;">Sello de la<br>Institución</span>
                                        </td>
                                        <td>
                                            <div class="linea-firma"></div>
                                            Firma del docente de aula
                                        </td>
                                    </tr>
                                </table>
                            </div>

                        </div>
                    </div>
                </div>
            <?php } ?>

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

        const inputBusqPlanilla = document.getElementById('inputValorBusqueda');
        if (inputBusqPlanilla) {
            inputBusqPlanilla.addEventListener('input', function() {
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

        function imprimirYRegistrarPlanilla(nombreEstudiante, cedulaEstudiante) {
            const formData = new FormData();
            formData.append('ajax_registrar_planilla', '1');
            formData.append('accion', 'Impresión / Guardar PDF de Planilla de Inscripción');
            formData.append('estudiante', nombreEstudiante);
            formData.append('cedula', cedulaEstudiante);

            fetch('planilla_inscripcion.php', { method: 'POST', body: formData })
            .then(() => { window.print(); })
            .catch(() => { window.print(); });
        }
    </script>
</body>
</html>