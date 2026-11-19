<?php
if (file_exists(__DIR__ . "/control_sesion.php")) {
    require_once(__DIR__ . "/control_sesion.php");
    verificarLogin();
} else {
    date_default_timezone_set('America/Caracas');
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
}

error_reporting(0);
ini_set('display_errors', 0);

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

$es_administrador = isset($_SESSION['rol']) && $_SESSION['rol'] === 'Administrador';
$rol_actual = $_SESSION['rol'] ?? 'Administrador';
$usuario_actual = $_SESSION['usuario'] ?? 'Administrador';

if (function_exists('obtenerTokenCSRF')) {
    $csrf_token = obtenerTokenCSRF();
} else {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    $csrf_token = $_SESSION['csrf_token'];
}

$mensaje_archivo = "";

$periodo_activo_db = '';
try {
    $st_conf = $pdo->query("SELECT `clave`, `valor` FROM `configuracion` WHERE `clave` IN ('periodo_escolar', 'estado')");
    if ($st_conf) {
        $conf_tmp = [];
        while ($row_c = $st_conf->fetch(PDO::FETCH_ASSOC)) {
            $conf_tmp[$row_c['clave']] = trim((string)$row_c['valor']);
        }
        $estado_cfg = $conf_tmp['estado'] ?? 'Activo';
        if ($estado_cfg === 'Activo' && !empty($conf_tmp['periodo_escolar'])) {
            $periodo_activo_db = $conf_tmp['periodo_escolar'];
        }
    }
} catch (Exception $e) {}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'archivar_estudiantes') {
    $token_recibido = $_POST['csrf_token'] ?? '';
    $token_valido = function_exists('validarTokenCSRF')
        ? validarTokenCSRF($token_recibido)
        : (!empty($_SESSION['csrf_token']) && is_string($token_recibido) && hash_equals($_SESSION['csrf_token'], $token_recibido));

    if (!$token_valido) {
        $mensaje_archivo = "<div style='color: #721c24; background-color: #f8d7da; border: 1px solid #f5c6cb; padding: 10px 14px; border-radius: 8px; margin-bottom: 16px; font-size: 0.86rem; font-weight: 600;'>Solicitud no válida. Por seguridad, recargue la página e intente nuevamente.</div>";
    } else {
        $cedulas_post = $_POST['cedulas'] ?? [];
        if (!is_array($cedulas_post)) {
            $cedulas_post = [$cedulas_post];
        }
        $cedulas_limpias = [];
        foreach ($cedulas_post as $c_raw) {
            $c_val = trim((string)$c_raw);
            if ($c_val !== '') {
                $cedulas_limpias[$c_val] = true;
            }
        }
        $cedulas_procesar = array_keys($cedulas_limpias);

        if (!empty($cedulas_procesar)) {
            try {
                $pdo->exec("CREATE TABLE IF NOT EXISTS `archivados` (
                    `id` INT(11) NOT NULL AUTO_INCREMENT,
                    `cedula_escolar` VARCHAR(25) NOT NULL,
                    `nombres` VARCHAR(100) NOT NULL,
                    `apellidos` VARCHAR(100) NOT NULL,
                    `grado` VARCHAR(30) NOT NULL DEFAULT '6',
                    `seccion` VARCHAR(30) NOT NULL DEFAULT '',
                    `año_escolar` VARCHAR(25) NOT NULL DEFAULT '',
                    `fecha_archivo` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `usuario` VARCHAR(80) NOT NULL DEFAULT '',
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `uk_archivados_cedula` (`cedula_escolar`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;");

                $archivados_ok = [];
                $fecha_ahora = date('Y-m-d H:i:s');

                $st_info_a = $pdo->prepare("SELECT e.`cédula_escolar`, e.nombres, e.apellidos, g.nombre_grado, s.nombre_seccion, i.`año_escolar`, i.tipo_estudiante
                                            FROM estudiante e
                                            INNER JOIN inscripcion i ON e.`cédula_escolar` = i.`cédula_escolar_estudiante`
                                            LEFT JOIN grados g ON i.id_grado = g.id
                                            LEFT JOIN secciones s ON i.id_seccion = s.id
                                            WHERE e.`cédula_escolar` = :ced
                                            ORDER BY i.id DESC LIMIT 1");
                $st_upd_a = $pdo->prepare("UPDATE inscripcion SET tipo_estudiante = 'Archivado' WHERE `cédula_escolar_estudiante` = :ced");
                $st_ins_a = $pdo->prepare("INSERT INTO `archivados` (`cedula_escolar`, `nombres`, `apellidos`, `grado`, `seccion`, `año_escolar`, `fecha_archivo`, `usuario`)
                                           VALUES (:ced, :nom, :ape, :gra, :sec, :per, :fec, :usu)
                                           ON DUPLICATE KEY UPDATE
                                           `nombres` = VALUES(`nombres`),
                                           `apellidos` = VALUES(`apellidos`),
                                           `grado` = VALUES(`grado`),
                                           `seccion` = VALUES(`seccion`),
                                           `año_escolar` = VALUES(`año_escolar`),
                                           `fecha_archivo` = VALUES(`fecha_archivo`),
                                           `usuario` = VALUES(`usuario`)");

                foreach ($cedulas_procesar as $ced_arch) {
                    $st_info_a->execute([':ced' => $ced_arch]);
                    $alu_a = $st_info_a->fetch(PDO::FETCH_ASSOC);

                    if (!$alu_a) {
                        continue;
                    }

                    $grado_num = preg_replace('/[^0-9]/', '', (string)($alu_a['nombre_grado'] ?? ''));
                    $estado_actual_alu = trim((string)($alu_a['tipo_estudiante'] ?? ''));
                    if ($grado_num !== '6' || $estado_actual_alu === 'Retirado' || $estado_actual_alu === 'Archivado') {
                        continue;
                    }

                    $periodo_arch = !empty($periodo_activo_db) ? $periodo_activo_db : ($alu_a['año_escolar'] ?? '');
                    $st_upd_a->execute([':ced' => $ced_arch]);
                    $st_ins_a->execute([
                        ':ced' => $ced_arch,
                        ':nom' => $alu_a['nombres'] ?? '',
                        ':ape' => $alu_a['apellidos'] ?? '',
                        ':gra' => $alu_a['nombre_grado'] ?? '6',
                        ':sec' => $alu_a['nombre_seccion'] ?? '',
                        ':per' => $periodo_arch,
                        ':fec' => $fecha_ahora,
                        ':usu' => $usuario_actual
                    ]);

                    $archivados_ok[] = [
                        'cedula' => $ced_arch,
                        'nombre' => trim(($alu_a['nombres'] ?? '') . ' ' . ($alu_a['apellidos'] ?? '')),
                        'seccion' => $alu_a['nombre_seccion'] ?? ''
                    ];
                }

                $total_ok = count($archivados_ok);
                if ($total_ok > 0) {
                    try {
                        if ($total_ok === 1) {
                            $u_arc = $archivados_ok[0];
                            $desc_arch = "Archivado del estudiante " . $u_arc['nombre'] . " (Cédula: " . $u_arc['cedula'] . ", 6° Grado Sec. " . $u_arc['seccion'] . ")";
                        } else {
                            $lista_ceds = array_column($archivados_ok, 'cedula');
                            $resumen_ceds = implode(', ', array_slice($lista_ceds, 0, 5));
                            if ($total_ok > 5) {
                                $resumen_ceds .= ' y ' . ($total_ok - 5) . ' más';
                            }
                            $desc_arch = "Archivado por lote de " . $total_ok . " estudiantes de 6° Grado (Cédulas: " . $resumen_ceds . ")";
                        }
                        $st_hg_a = $pdo->prepare("INSERT INTO `historial` (`descripcion`, `usuario`, `fecha_hora`) VALUES (:desc, :usu, :fec)");
                        $st_hg_a->execute([
                            ':desc' => $desc_arch,
                            ':usu'  => $usuario_actual,
                            ':fec'  => $fecha_ahora
                        ]);
                    } catch (Exception $e2) {}

                    header("Location: archivados.php?msg=archivado&total=" . $total_ok);
                    exit();
                } else {
                    $mensaje_archivo = "<div style='color: #856404; background-color: #fff3cd; border: 1px solid #ffeeba; padding: 10px 14px; border-radius: 8px; margin-bottom: 16px; font-size: 0.86rem; font-weight: 600;'>Solo los estudiantes activos de 6° grado pueden pasar al módulo de Archivados.</div>";
                }
            } catch (Exception $e) {
                $mensaje_archivo = "<div style='color: #721c24; background-color: #f8d7da; border: 1px solid #f5c6cb; padding: 10px 14px; border-radius: 8px; margin-bottom: 16px; font-size: 0.86rem; font-weight: 600;'>No se pudo completar el archivado de los estudiantes seleccionados.</div>";
            }
        }
    }
}

if (isset($_GET['modificar_cedula'])) {
    $cedula_mod = trim($_GET['modificar_cedula']);
    if (!empty($cedula_mod)) {
        $sql_mod = "SELECT 
                        e.*,
                        i.tipo_estudiante AS tipo_ingreso,
                        g.nombre_grado AS grado,
                        s.nombre_seccion AS seccion_nombre,
                        i.`año_escolar`,
                        i.plantel_procedencia,
                        d.nombres_apellidos AS docente,
                        r.nombres AS rep_nombres,
                        r.apellidos AS rep_apellidos,
                        r.`cédula_identidad` AS rep_ci,
                        r.nacionalidad AS rep_nac,
                        r.parentesco AS rep_parentesco,
                        r.fecha_nacimiento AS rep_fecha_nac,
                        r.rif AS rep_rif,
                        r.`teléfono_celular` AS rep_telefono,
                        r.`correo_electrónico` AS rep_correo,
                        r.`dirección_habitación` AS rep_direccion,
                        r.`nivel_instrucción` AS rep_nivel_instruccion,
                        r.profesion AS rep_profesion,
                        r.`ocupación` AS rep_ocupacion,
                        r.empresa AS rep_empresa,
                        r.`teléfono_empresa` AS rep_telefono_empresa,
                        r.`dirección_empresa` AS rep_direccion_empresa,
                        r.`autorización_legal`,
                        r.`número_poder_notariado`,
                        m.id AS madre_id,
                        m.nombres AS madre_nombres,
                        m.apellidos AS madre_apellidos,
                        m.`cédula_identidad` AS madre_ci,
                        m.nacionalidad AS madre_nac,
                        m.`teléfono` AS madre_telefono,
                        m.fecha_nacimiento AS madre_fecha_nac,
                        m.estado_civil AS madre_estado_civil,
                        m.lugar_nacimiento AS madre_lugar_nac,
                        m.correo AS madre_correo,
                        m.`dirección_habitación` AS madre_direccion,
                        m.`nivel_instrucción` AS madre_nivel_instruccion,
                        m.profesion AS madre_profesion,
                        m.`ocupación` AS madre_ocupacion,
                        m.empresa AS madre_empresa,
                        m.`teléfono_empresa` AS madre_telefono_empresa,
                        m.`dirección_empresa` AS madre_direccion_empresa,
                        p.id AS padre_id,
                        p.nombres AS padre_nombres,
                        p.apellidos AS padre_apellidos,
                        p.`cédula_identidad` AS padre_ci,
                        p.nacionalidad AS padre_nac,
                        p.`teléfono` AS padre_telefono,
                        p.fecha_nacimiento AS padre_fecha_nac,
                        p.estado_civil AS padre_estado_civil,
                        p.lugar_nacimiento AS padre_lugar_nac,
                        p.correo AS padre_correo,
                        p.`dirección_habitación` AS padre_direccion,
                        p.`nivel_instrucción` AS padre_nivel_instruccion,
                        p.profesion AS padre_profesion,
                        p.`ocupación` AS padre_ocupacion,
                        p.empresa AS padre_empresa,
                        p.`teléfono_empresa` AS padre_telefono_empresa,
                        p.`dirección_empresa` AS padre_direccion_empresa,
                        v.`condición_vivienda`,
                        v.tipo_vivienda,
                        h.nombres_apellidos AS hermanos_escuela,
                        am.enfermedades_tratamiento,
                        am.`condición_importante`,
                        vac.nombre_vacuna,
                        med.nombre_medicamento,
                        t_ent.modalidad AS entrada_modalidad,
                        t_ent.nombres_apellidos_persona AS entrada_persona,
                        t_ent.parentesco_persona AS entrada_parentesco,
                        t_sal.modalidad AS salida_modalidad,
                        t_sal.nombres_apellidos_persona AS salida_persona,
                        t_sal.parentesco_persona AS salida_parentesco,
                        pc.`descripción` AS problema_comunidad
                    FROM estudiante e
                    LEFT JOIN inscripcion i ON e.`cédula_escolar` = i.`cédula_escolar_estudiante`
                    LEFT JOIN grados g ON i.id_grado = g.id
                    LEFT JOIN secciones s ON i.id_seccion = s.id
                    LEFT JOIN docente_seccion ds ON (
                        ds.id_grado = i.id_grado 
                        AND ds.id_seccion = i.id_seccion 
                        AND ds.`año_escolar` = i.`año_escolar`
                    )
                    LEFT JOIN docentes d ON ds.id_docente = d.id
                    LEFT JOIN representante r ON e.`cédula_escolar` = r.`cédula_escolar_estudiante`
                    LEFT JOIN vivienda v ON e.`cédula_escolar` = v.`cédula_escolar_estudiante`
                    LEFT JOIN madre m ON e.`cédula_escolar` = m.`cédula_escolar_estudiante`
                    LEFT JOIN padre p ON e.`cédula_escolar` = p.`cédula_escolar_estudiante`
                    LEFT JOIN hermano h ON e.`cédula_escolar` = h.`cédula_escolar_estudiante`
                    LEFT JOIN antecedente_medico am ON e.`cédula_escolar` = am.`cédula_escolar_estudiante`
                    LEFT JOIN vacuna vac ON e.`cédula_escolar` = vac.`cédula_escolar_estudiante`
                    LEFT JOIN medicamento_contraindicado med ON e.`cédula_escolar` = med.`cédula_escolar_estudiante`
                    LEFT JOIN problema_comunidad pc ON e.`cédula_escolar` = pc.`cédula_escolar_estudiante`
                    LEFT JOIN traslado_estudiante t_ent ON e.`cédula_escolar` = t_ent.`cédula_escolar_estudiante` AND t_ent.tipo = 'Entrada'
                    LEFT JOIN traslado_estudiante t_sal ON e.`cédula_escolar` = t_sal.`cédula_escolar_estudiante` AND t_sal.tipo = 'Salida'
                    WHERE e.`cédula_escolar` = :cedula
                    LIMIT 1";

        $st = $pdo->prepare($sql_mod);
        $st->execute([':cedula' => $cedula_mod]);
        $datos = $st->fetch(PDO::FETCH_ASSOC);

        if ($datos) {
            $_SESSION['modo_edicion'] = true;
            $_SESSION['cedula_original'] = $datos['cédula_escolar'];

            $partes_ced_est = explode('-', $datos['cédula_escolar']);
            $nac_ced_est = (count($partes_ced_est) > 1) ? $partes_ced_est[0] : 'V';
            $num_ced_est = (count($partes_ced_est) > 1) ? $partes_ced_est[1] : $datos['cédula_escolar'];

            $sec_form = ($datos['seccion_nombre'] === 'Única') ? 'U' : ($datos['seccion_nombre'] ?? '');

            $_SESSION['estudiante'] = [
                'tipo_ingreso'          => $datos['tipo_ingreso'] ?? '',
                'nombres'               => $datos['nombres'] ?? '',
                'apellidos'             => $datos['apellidos'] ?? '',
                'nacionalidad_cedula'   => $nac_ced_est,
                'numero_cedula_alumno'  => $num_ced_est,
                'cedula_alumno'         => $datos['cédula_escolar'],
                'fecha_nacimiento'      => $datos['fecha_nacimiento'] ?? '',
                'genero'                => $datos['género'] ?? '',
                'nacionalidad'          => $datos['nacionalidad'] ?? 'Venezolana',
                'direccion_domicilio'   => $datos['dirección_domicilio'] ?? '',
                'grado'                 => $datos['grado'] ?? '',
                'seccion'               => $sec_form,
                'docente'               => $datos['docente'] ?? '',
                'periodo_escolar'       => $periodo_activo_db,
                'plantel_procedencia'   => $datos['plantel_procedencia'] ?? ''
            ];

            $partes_ced_rep = explode('-', $datos['rep_ci'] ?? '');
            $nac_ced_rep = (count($partes_ced_rep) > 1) ? $partes_ced_rep[0] : ($datos['rep_nac'] ?? 'V');
            $num_ced_rep = (count($partes_ced_rep) > 1) ? $partes_ced_rep[1] : ($datos['rep_ci'] ?? '');

            $parentesco_bd = trim($datos['rep_parentesco'] ?? '');
            if (strcasecmp($parentesco_bd, 'madre') === 0) $parentesco_bd = 'Mamá';
            if (strcasecmp($parentesco_bd, 'padre') === 0) $parentesco_bd = 'Papá';
            $parentesco_sel = $parentesco_bd;
            $especificar_par = '';
            if (strpos($parentesco_bd, 'Otro - ') === 0) {
                $parentesco_sel = 'Otro';
                $especificar_par = substr($parentesco_bd, 7);
            }

            $_SESSION['representante'] = [
                'nombre_representante'               => trim(($datos['rep_nombres'] ?? '') . ' ' . ($datos['rep_apellidos'] ?? '')),
                'nacionalidad_cedula_rep'            => $nac_ced_rep,
                'numero_cedula_rep'                  => $num_ced_rep,
                'cedula_identidad'                   => $datos['rep_ci'] ?? '',
                'fecha_nacimiento_representante'     => $datos['rep_fecha_nac'] ?? '',
                'rif_representante'                  => $datos['rep_rif'] ?? '',
                'correo_electronico_representante'   => $datos['rep_correo'] ?? '',
                'telefono_representante'             => $datos['rep_telefono'] ?? '',
                'direccion_habitacion_representante' => $datos['rep_direccion'] ?? '',
                'nivel_instruccion_representante'    => $datos['rep_nivel_instruccion'] ?? '',
                'profesion_representante'            => $datos['rep_profesion'] ?? '',
                'ocupacion_representante'            => $datos['rep_ocupacion'] ?? '',
                'empresa_representante'              => $datos['rep_empresa'] ?? '',
                'telefono_empresa_representante'     => $datos['rep_telefono_empresa'] ?? '',
                'direccion_empresa_representante'    => $datos['rep_direccion_empresa'] ?? '',
                'parentesco_representante'           => $parentesco_bd,
                'parentesco_seleccionado'            => $parentesco_sel,
                'especificar_parentesco'             => $especificar_par,
                'autorizacion_legal'                 => (($datos['autorización_legal'] ?? 0) == 1) ? 'Sí' : 'No',
                'numero_poder'                       => $datos['número_poder_notariado'] ?? ''
            ];

            if (!empty($datos['madre_id']) || !empty($datos['madre_nombres'])) {
                $partes_ced_mad = explode('-', $datos['madre_ci'] ?? '');
                $nac_ced_mad = (count($partes_ced_mad) > 1) ? $partes_ced_mad[0] : ($datos['madre_nac'] ?? 'V');
                $num_ced_mad = (count($partes_ced_mad) > 1) ? $partes_ced_mad[1] : ($datos['madre_ci'] ?? '');

                $_SESSION['madre'] = [
                    'no_aplica_madre'            => '0',
                    'nombres_apellidos_madre'    => trim(($datos['madre_nombres'] ?? '') . ' ' . ($datos['madre_apellidos'] ?? '')),
                    'nacionalidad_cedula_madre'  => $nac_ced_mad,
                    'numero_cedula_madre'        => $num_ced_mad,
                    'ci_madre'                   => $datos['madre_ci'] ?? '',
                    'telefono_madre'             => $datos['madre_telefono'] ?? '',
                    'fecha_nacimiento_madre'     => $datos['madre_fecha_nac'] ?? '',
                    'estado_civil_madre'         => $datos['madre_estado_civil'] ?? '',
                    'lugar_nacimiento_madre'     => $datos['madre_lugar_nac'] ?? '',
                    'correo_madre'               => $datos['madre_correo'] ?? '',
                    'direccion_habitacion_madre' => $datos['madre_direccion'] ?? '',
                    'nivel_instruccion_madre'    => $datos['madre_nivel_instruccion'] ?? '',
                    'profesion_madre'            => $datos['madre_profesion'] ?? '',
                    'ocupacion_madre'            => $datos['madre_ocupacion'] ?? '',
                    'empresa_madre'              => $datos['madre_empresa'] ?? '',
                    'telefono_empresa_madre'     => $datos['madre_telefono_empresa'] ?? '',
                    'direccion_empresa_madre'    => $datos['madre_direccion_empresa'] ?? ''
                ];
            } else {
                $_SESSION['madre'] = ['no_aplica_madre' => '1'];
            }

            if (!empty($datos['padre_id']) || !empty($datos['padre_nombres'])) {
                $partes_ced_pad = explode('-', $datos['padre_ci'] ?? '');
                $nac_ced_pad = (count($partes_ced_pad) > 1) ? $partes_ced_pad[0] : ($datos['padre_nac'] ?? 'V');
                $num_ced_pad = (count($partes_ced_pad) > 1) ? $partes_ced_pad[1] : ($datos['padre_ci'] ?? '');

                $_SESSION['padre'] = [
                    'no_aplica_padre'            => '0',
                    'nombres_apellidos_padre'    => trim(($datos['padre_nombres'] ?? '') . ' ' . ($datos['padre_apellidos'] ?? '')),
                    'nacionalidad_cedula_padre'  => $nac_ced_pad,
                    'numero_cedula_padre'        => $num_ced_pad,
                    'ci_padre'                   => $datos['padre_ci'] ?? '',
                    'telefono_padre'             => $datos['padre_telefono'] ?? '',
                    'fecha_nacimiento_padre'     => $datos['padre_fecha_nac'] ?? '',
                    'estado_civil_padre'         => $datos['padre_estado_civil'] ?? '',
                    'lugar_nacimiento_padre'     => $datos['padre_lugar_nac'] ?? '',
                    'correo_padre'               => $datos['padre_correo'] ?? '',
                    'direccion_habitacion_padre' => $datos['padre_direccion'] ?? '',
                    'nivel_instruccion_padre'    => $datos['padre_nivel_instruccion'] ?? '',
                    'profesion_padre'            => $datos['padre_profesion'] ?? '',
                    'ocupacion_padre'            => $datos['padre_ocupacion'] ?? '',
                    'empresa_padre'              => $datos['padre_empresa'] ?? '',
                    'telefono_empresa_padre'     => $datos['padre_telefono_empresa'] ?? '',
                    'direccion_empresa_padre'    => $datos['padre_direccion_empresa'] ?? ''
                ];
            } else {
                $_SESSION['padre'] = ['no_aplica_padre' => '1'];
            }

            $_SESSION['adicional'] = [
                'peso'                    => $datos['peso'] ?? '',
                'talla'                   => $datos['talla'] ?? '',
                'talla_camisa'            => strtoupper($datos['talla_camisa'] ?? ''),
                'talla_pantalon'          => strtoupper($datos['talla_pantalón'] ?? ''),
                'calzado'                 => $datos['calzado'] ?? '',
                'entidad_federal'         => $datos['entidad_federal'] ?? '',
                'lugar_nacimiento'        => $datos['lugar_nacimiento'] ?? '',
                'municipio_nacimiento'    => $datos['municipio_nacimiento'] ?? '',
                'parroquia_nacimiento'    => $datos['parroquia_nacimiento'] ?? '',
                'religion'                => $datos['religión'] ?? '',
                'vive_con'                => $datos['con_quien_vive'] ?? '',
                'padres_separados'        => (($datos['padres_separados'] ?? 0) == 1) ? 'Sí' : 'No',
                'repitio_grado'           => (($datos['ha_repetido_grado'] ?? 0) == 1) ? 'Sí' : 'No',
                'especifique_grados'      => $datos['grados_repetidos'] ?? '',
                'necesidades_especiales'  => (($datos['necesidades_especiales'] ?? 0) == 1) ? 'Sí' : 'No',
                'descripcion_necesidades' => $datos['descripción_condición'] ?? '',
                'informe_especialista'    => (($datos['informe_especialista'] ?? 0) == 1) ? 'Sí' : 'No',
                'informe_alimentacion'    => (($datos['informe_alimentación'] ?? 0) == 1) ? 'Sí' : 'No',
                'aula_integrada'          => (($datos['atendido_aula_integrada'] ?? 0) == 1) ? 'Sí' : 'No',
                'condicion_vivienda'      => $datos['condición_vivienda'] ?? '',
                'tipo_vivienda'           => $datos['tipo_vivienda'] ?? '',
                'hermanos_escuela'        => $datos['hermanos_escuela'] ?? '',
                'patologias'              => $datos['enfermedades_tratamiento'] ?? '',
                'condicion_docente'       => $datos['condición_importante'] ?? '',
                'vacunas'                 => $datos['nombre_vacuna'] ?? '',
                'medicamentos'            => $datos['nombre_medicamento'] ?? '',
                'viene_escuela'           => $datos['entrada_modalidad'] ?? '',
                'parentesco_trae'         => $datos['entrada_parentesco'] ?? '',
                'nombre_trae'             => $datos['entrada_persona'] ?? '',
                'retira_casa'             => $datos['salida_modalidad'] ?? '',
                'parentesco_retira'       => $datos['salida_parentesco'] ?? '',
                'nombre_retira'           => $datos['salida_persona'] ?? '',
                'problemas_comunidad'     => $datos['problema_comunidad'] ?? ''
            ];

            header("Location: agregar_alumno.php");
            exit();
        }
    }
}

if (isset($_GET['retirar_cedula'])) {
    $cedula_retirar = trim($_GET['retirar_cedula']);
    $motivo_retiro = trim($_GET['motivo'] ?? 'Retiro institucional');
    if ($motivo_retiro === '') {
        $motivo_retiro = 'Retiro institucional';
    }

    if (!empty($cedula_retirar)) {
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS retirados (
                id INT AUTO_INCREMENT PRIMARY KEY,
                cedula VARCHAR(20),
                nombres VARCHAR(100),
                apellidos VARCHAR(100),
                motivo TEXT,
                grado VARCHAR(30),
                seccion VARCHAR(30),
                periodo_escolar VARCHAR(20)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

            $st_info = $pdo->prepare("SELECT i.`año_escolar`, g.nombre_grado, s.nombre_seccion, e.nombres, e.apellidos 
                                      FROM inscripcion i 
                                      JOIN estudiante e ON i.`cédula_escolar_estudiante` = e.`cédula_escolar`
                                      LEFT JOIN grados g ON i.id_grado = g.id
                                      LEFT JOIN secciones s ON i.id_seccion = s.id
                                      WHERE i.`cédula_escolar_estudiante` = :ced LIMIT 1");
            $st_info->execute([':ced' => $cedula_retirar]);
            $info_alu = $st_info->fetch(PDO::FETCH_ASSOC);

            $stmt_ret = $pdo->prepare("UPDATE inscripcion SET tipo_estudiante = 'Retirado' WHERE `cédula_escolar_estudiante` = :cedula");
            $stmt_ret->execute([':cedula' => $cedula_retirar]);

            if ($info_alu) {
                $stmt_chk = $pdo->prepare("SELECT id FROM retirados WHERE cedula = :cedula LIMIT 1");
                $stmt_chk->execute([':cedula' => $cedula_retirar]);
                if (!$stmt_chk->fetch()) {
                    $stmt_ins = $pdo->prepare("INSERT INTO retirados (cedula, nombres, apellidos, motivo, grado, seccion, periodo_escolar) VALUES (:ced, :nom, :ape, :mot, :gra, :sec, :per)");
                    $stmt_ins->execute([
                        ':ced' => $cedula_retirar,
                        ':nom' => $info_alu['nombres'],
                        ':ape' => $info_alu['apellidos'],
                        ':mot' => $motivo_retiro,
                        ':gra' => $info_alu['nombre_grado'] ?? '1',
                        ':sec' => $info_alu['nombre_seccion'] ?? 'A',
                        ':per' => !empty($periodo_activo_db) ? $periodo_activo_db : ($info_alu['año_escolar'] ?? '')
                    ]);
                }
            }

            try {
                $usuario_hist = $_SESSION['usuario'] ?? 'Administrador';
                $nombre_alu_hist = $info_alu ? trim(($info_alu['nombres'] ?? '') . ' ' . ($info_alu['apellidos'] ?? '')) : '';
                $desc_retiro = !empty($nombre_alu_hist)
                    ? "Retiro del estudiante " . $nombre_alu_hist . " (Cédula: " . $cedula_retirar . ") - Motivo: " . $motivo_retiro
                    : "Retiro del estudiante con cédula: " . $cedula_retirar . " - Motivo: " . $motivo_retiro;
                $st_hg = $pdo->prepare("INSERT INTO `historial` (`descripcion`, `usuario`, `fecha_hora`) VALUES (:desc, :usu, :fec)");
                $st_hg->execute([
                    ':desc' => $desc_retiro,
                    ':usu'  => $usuario_hist,
                    ':fec'  => date('Y-m-d H:i:s')
                ]);
            } catch (Exception $e2) {}
        } catch (Exception $e) {}
    }
    header("Location: retirados.php");
    exit();
}

$tipo_busqueda  = trim($_GET['tipo_busqueda'] ?? 'cedula');
$valor_busqueda = trim($_GET['valor'] ?? '');
$filtro_grado   = trim($_GET['grado'] ?? '');
$filtro_seccion = trim($_GET['seccion'] ?? '');
$filtro_periodo = trim($_GET['periodo'] ?? '');

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

$busqueda_realizada = (!empty($valor_busqueda) || (!empty($filtro_grado) && !empty($filtro_seccion)));

$filas = [];
$hay_sexto_grado = false;

if ($busqueda_realizada) {
    $condiciones = ["COALESCE(i.tipo_estudiante, '') NOT IN ('Retirado', 'Archivado')"];
    $params = [];

    if (!empty($valor_busqueda)) {
        if ($tipo_busqueda === 'nombre') {
            $condiciones[] = "(e.nombres LIKE :b_nom1 OR e.apellidos LIKE :b_nom2 OR CONCAT(TRIM(e.nombres), ' ', TRIM(e.apellidos)) LIKE :b_nom3 OR CONCAT(TRIM(e.apellidos), ' ', TRIM(e.nombres)) LIKE :b_nom4)";
            $params[':b_nom1'] = "%" . $valor_busqueda . "%";
            $params[':b_nom2'] = "%" . $valor_busqueda . "%";
            $params[':b_nom3'] = "%" . $valor_busqueda . "%";
            $params[':b_nom4'] = "%" . $valor_busqueda . "%";
        } else {
            $condiciones[] = "e.`cédula_escolar` LIKE :b_ced";
            $params[':b_ced'] = "%" . $valor_busqueda . "%";
        }
    }

    if (!empty($filtro_grado)) {
        $condiciones[] = "TRIM(g.nombre_grado) = :grado";
        $params[':grado'] = $filtro_grado;
    }
    if (!empty($filtro_seccion)) {
        if ($filtro_seccion === 'Única' || $filtro_seccion === 'U') {
            $condiciones[] = "(TRIM(s.nombre_seccion) = 'Única' OR TRIM(s.nombre_seccion) = 'U')";
        } else {
            $condiciones[] = "TRIM(s.nombre_seccion) = :seccion";
            $params[':seccion'] = $filtro_seccion;
        }
    }
    if (!empty($filtro_periodo) && $filtro_periodo !== $periodo_activo_db) {
        $condiciones[] = "TRIM(i.`año_escolar`) = :periodo";
        $params[':periodo'] = $filtro_periodo;
    }

    $where_clause = implode(" AND ", $condiciones);

    $sql = "SELECT 
                e.`cédula_escolar`,
                e.nombres,
                e.apellidos,
                e.fecha_nacimiento,
                e.`género`,
                e.nacionalidad,
                e.`dirección_domicilio`,
                e.entidad_federal,
                e.lugar_nacimiento,
                e.municipio_nacimiento,
                e.parroquia_nacimiento,
                e.peso,
                e.talla,
                e.talla_camisa,
                e.`talla_pantalón`,
                e.calzado,
                e.`religión`,
                e.con_quien_vive,
                e.padres_separados,
                e.ha_repetido_grado,
                e.grados_repetidos,
                e.necesidades_especiales,
                e.`descripción_condición`,
                e.informe_especialista,
                e.`informe_alimentación`,
                e.atendido_aula_integrada,
                i.tipo_estudiante AS tipo_ingreso,
                i.plantel_procedencia,
                g.nombre_grado AS grado,
                s.nombre_seccion AS seccion,
                i.`año_escolar`,
                COALESCE(d.nombres_apellidos, 'Sin asignar') AS docente,
                r.`cédula_identidad` AS rep_ci,
                CONCAT(COALESCE(r.apellidos,''), ' ', COALESCE(r.nombres,'')) AS rep_nombre_completo,
                r.parentesco AS rep_parentesco,
                r.fecha_nacimiento AS rep_fecha_nac,
                r.rif AS rep_rif,
                r.`teléfono_celular` AS rep_telefono,
                r.`teléfono_residencia` AS rep_telefono_res,
                r.`correo_electrónico` AS rep_correo,
                r.`dirección_habitación` AS rep_direccion,
                r.`nivel_instrucción` AS rep_nivel_instruccion,
                r.profesion AS rep_profesion,
                r.`ocupación` AS rep_ocupacion,
                r.empresa AS rep_empresa,
                r.`teléfono_empresa` AS rep_telefono_empresa,
                r.`dirección_empresa` AS rep_direccion_empresa,
                r.`autorización_legal`,
                r.`número_poder_notariado`,
                CONCAT(COALESCE(m.nombres,''), ' ', COALESCE(m.apellidos,'')) AS madre_nombre_completo,
                m.nombres AS madre_nombres,
                m.apellidos AS madre_apellidos,
                m.`cédula_identidad` AS madre_ci,
                m.`teléfono` AS madre_telefono,
                m.fecha_nacimiento AS madre_fecha_nac,
                m.estado_civil AS madre_estado_civil,
                m.lugar_nacimiento AS madre_lugar_nac,
                m.correo AS madre_correo,
                m.`dirección_habitación` AS madre_direccion,
                m.`nivel_instrucción` AS madre_nivel_instruccion,
                m.profesion AS madre_profesion,
                m.`ocupación` AS madre_ocupacion,
                m.empresa AS madre_empresa,
                m.`teléfono_empresa` AS madre_telefono_empresa,
                m.`dirección_empresa` AS madre_direccion_empresa,
                CONCAT(COALESCE(p.nombres,''), ' ', COALESCE(p.apellidos,'')) AS padre_nombre_completo,
                p.nombres AS padre_nombres,
                p.apellidos AS padre_apellidos,
                p.`cédula_identidad` AS padre_ci,
                p.`teléfono` AS padre_telefono,
                p.fecha_nacimiento AS padre_fecha_nac,
                p.estado_civil AS padre_estado_civil,
                p.lugar_nacimiento AS padre_lugar_nac,
                p.correo AS padre_correo,
                p.`dirección_habitación` AS padre_direccion,
                p.`nivel_instrucción` AS padre_nivel_instruccion,
                p.profesion AS padre_profesion,
                p.`ocupación` AS padre_ocupacion,
                p.empresa AS padre_empresa,
                p.`teléfono_empresa` AS padre_telefono_empresa,
                p.`dirección_empresa` AS padre_direccion_empresa,
                v.`condición_vivienda`,
                v.tipo_vivienda,
                h.nombres_apellidos AS hermanos_escuela,
                am.enfermedades_tratamiento,
                am.`condición_importante`,
                vac.nombre_vacuna,
                med.nombre_medicamento,
                t_ent.modalidad AS entrada_modalidad,
                t_ent.nombres_apellidos_persona AS entrada_persona,
                t_ent.parentesco_persona AS entrada_parentesco,
                t_sal.modalidad AS salida_modalidad,
                t_sal.nombres_apellidos_persona AS salida_persona,
                t_sal.parentesco_persona AS salida_parentesco,
                pc.`descripción` AS problema_comunidad
            FROM estudiante e
            INNER JOIN inscripcion i ON e.`cédula_escolar` = i.`cédula_escolar_estudiante`
            LEFT JOIN grados g ON i.id_grado = g.id
            LEFT JOIN secciones s ON i.id_seccion = s.id
            LEFT JOIN docente_seccion ds ON (
                ds.id_grado = i.id_grado 
                AND ds.id_seccion = i.id_seccion 
                AND ds.`año_escolar` = i.`año_escolar`
            )
            LEFT JOIN docentes d ON ds.id_docente = d.id
            LEFT JOIN representante r ON e.`cédula_escolar` = r.`cédula_escolar_estudiante`
            LEFT JOIN vivienda v ON e.`cédula_escolar` = v.`cédula_escolar_estudiante`
            LEFT JOIN madre m ON e.`cédula_escolar` = m.`cédula_escolar_estudiante`
            LEFT JOIN padre p ON e.`cédula_escolar` = p.`cédula_escolar_estudiante`
            LEFT JOIN hermano h ON e.`cédula_escolar` = h.`cédula_escolar_estudiante`
            LEFT JOIN antecedente_medico am ON e.`cédula_escolar` = am.`cédula_escolar_estudiante`
            LEFT JOIN vacuna vac ON e.`cédula_escolar` = vac.`cédula_escolar_estudiante`
            LEFT JOIN medicamento_contraindicado med ON e.`cédula_escolar` = med.`cédula_escolar_estudiante`
            LEFT JOIN problema_comunidad pc ON e.`cédula_escolar` = pc.`cédula_escolar_estudiante`
            LEFT JOIN traslado_estudiante t_ent ON e.`cédula_escolar` = t_ent.`cédula_escolar_estudiante` AND t_ent.tipo = 'Entrada'
            LEFT JOIN traslado_estudiante t_sal ON e.`cédula_escolar` = t_sal.`cédula_escolar_estudiante` AND t_sal.tipo = 'Salida'
            WHERE {$where_clause}
            GROUP BY e.`cédula_escolar`
            ORDER BY g.id ASC, s.nombre_seccion ASC, e.apellidos ASC, e.nombres ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($filas as $f_chk) {
        if (preg_replace('/[^0-9]/', '', (string)($f_chk['grado'] ?? '')) === '6') {
            $hay_sexto_grado = true;
            break;
        }
    }
}

function mostrarDato($valor) {
    $texto = trim((string)($valor ?? ''));
    return $texto !== '' ? htmlspecialchars($texto, ENT_QUOTES, 'UTF-8') : '-';
}

function renderGenero($genero) {
    $g = strtoupper(trim((string)$genero));
    if ($g === 'MASCULINO' || $g === 'M') {
        return '<i class="fas fa-male" style="color: #0d6efd; font-size: 1.3rem;" title="Masculino"></i>';
    } elseif ($g === 'FEMENINO' || $g === 'F') {
        return '<i class="fas fa-female" style="color: #d63384; font-size: 1.3rem;" title="Femenino"></i>';
    }
    return mostrarDato($genero);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Control Estudiantil - Sistema de Inscripción</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --bg-main: #f8fafc;
            --bg-surface: #ffffff;
            --bg-surface-alt: #f1f5f9;
            --text-primary: #0f172a;
            --text-secondary: #475569;
            --border-color: #cbd5e1;
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
        body.dark-mode .modal-content {
            background-color: var(--bg-surface) !important;
            border-color: var(--border-color) !important;
            color: var(--text-primary) !important;
        }

        body.dark-mode .modal-footer { border-top-color: var(--border-color) !important; }
        body.dark-mode .batch-archive-bar { background-color: var(--bg-surface-alt) !important; border-color: var(--border-color) !important; color: var(--text-primary) !important; }

        body.dark-mode th { background-color: #1e293b !important; color: var(--text-primary) !important; }
        body.dark-mode td { background-color: var(--bg-surface) !important; color: var(--text-primary) !important; }
        body.dark-mode select, body.dark-mode input:not([type="checkbox"]) { background-color: var(--bg-surface-alt) !important; color: var(--text-primary) !important; border-color: var(--border-color) !important; }

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
            flex-wrap: wrap;
        }

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
        }

        .role-badge {
            padding: 2px 8px;
            border-radius: 5px;
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
        }
        .role-badge.badge-admin { background-color: rgba(239, 68, 68, 0.15); color: #ef4444; }
        .role-badge.badge-coord { background-color: rgba(59, 130, 246, 0.15); color: #3b82f6; }

        .search-panel-box {
            background-color: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: 12px;
            padding: 16px 20px;
            box-shadow: var(--card-shadow);
            margin-bottom: 20px;
        }

        body.dark-mode .search-panel-box {
            background-color: var(--bg-surface);
            border-color: var(--border-color);
        }

        .search-panel-title {
            font-size: 1.15rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .sub-header-label {
            font-size: 0.82rem;
            font-weight: 600;
            color: var(--text-secondary);
            margin-bottom: 6px;
        }

        .batch-archive-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            padding: 10px 14px;
            margin-bottom: 12px;
            border-radius: 8px;
            background-color: var(--bg-surface-alt);
            border: 1px solid var(--border-color);
            font-size: 0.84rem;
        }

        .btn-lote-archivar {
            background-color: #6d28d9;
            color: #ffffff;
            border: none;
            padding: 6px 14px;
            border-radius: 7px;
            font-size: 0.82rem;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: opacity 0.2s ease;
        }

        .btn-lote-archivar:hover:not(:disabled) { opacity: 0.9; }
        .btn-lote-archivar:disabled { opacity: 0.5; cursor: not-allowed; }

        .chk-select-archivo {
            width: 16px;
            height: 16px;
            cursor: pointer;
            accent-color: #6d28d9;
        }

        .table-responsive {
            width: 100%;
            overflow-x: auto;
            border: 1px solid var(--border-color);
            border-radius: 8px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
            background-color: var(--bg-surface);
            color: var(--text-primary);
        }

        th, td {
            border: 1px solid var(--border-color);
            padding: 11px;
            text-align: left;
            vertical-align: middle;
        }

        th {
            background-color: #007bff;
            color: white;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        tr:nth-child(even) { background-color: var(--bg-surface-alt); }
        tr:hover { background-color: rgba(37, 99, 235, 0.04); }

        .btn-acciones {
            display: flex;
            gap: 5px;
            justify-content: center;
        }

        .btn-accion {
            width: 32px;
            height: 32px;
            border-radius: 6px;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            cursor: pointer;
            transition: opacity 0.2s;
            text-decoration: none;
            font-size: 0.85rem;
        }

        .btn-accion:hover { opacity: 0.85; color: white; }
        .btn-ver { background-color: #0dcaf0; }      
        .btn-carnet { background-color: #03a9f4; }   
        .btn-editar { background-color: #ffc107; color: #000; } 
        .btn-archivar { background-color: #6d28d9; }
        .btn-estado { background-color: #dc3545; }   
    </style>
</head>
<body>

    <?php include("menu.php"); ?>

    <main class="main-content">
        <div class="top-header-bar">
            <div class="top-breadcrumb">
                <i class="fas fa-graduation-cap" style="color: #2563eb;"></i>
                <span>Control Estudiantil</span>
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
                    <span><?php echo htmlspecialchars($_SESSION['usuario'] ?? 'Usuario', ENT_QUOTES, 'UTF-8'); ?></span>
                    <span class="role-badge <?php echo $es_administrador ? 'badge-admin' : 'badge-coord'; ?>">
                        <?php echo $es_administrador ? 'Admin' : htmlspecialchars($rol_actual, ENT_QUOTES, 'UTF-8'); ?>
                    </span>
                </div>
            </div>
        </div>

        <?php if (!empty($mensaje_archivo)) echo $mensaje_archivo; ?>

        <div class="search-panel-box">
            <div class="search-panel-title">
                <i class="fas fa-search" style="color: #2563eb;"></i> Seleccionar Estudiante para Consulta
            </div>

            <form method="GET" action="alumnos.php" style="margin-bottom: 15px;">
                <div class="sub-header-label">Búsqueda por datos personales:</div>
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

            <form method="GET" action="alumnos.php" style="margin:0;">
                <div class="sub-header-label">Filtrar por Grado (1° a 6°), Sección y Período:</div>
                <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                    <select name="grado" class="form-control" style="padding: 8px; border-radius: 6px; border: 1px solid var(--border-color); width: 180px; font-size: 0.88rem;" required>
                        <option value="">Seleccione Grado...</option>
                        <?php
                        $grados_opt = ['1', '2', '3', '4', '5', '6'];
                        foreach($grados_opt as $g) {
                            $sel = ($filtro_grado === $g) ? 'selected' : '';
                            echo "<option value='{$g}' {$sel}>{$g}° Grado</option>";
                        }
                        ?>
                    </select>
                    
                    <select name="seccion" class="form-control" style="padding: 8px; border-radius: 6px; border: 1px solid var(--border-color); width: 140px; font-size: 0.88rem;" required>
                        <option value="">Sección...</option>
                        <?php
                        $secciones_opt = ['A', 'B', 'C', 'D', 'Única'];
                        foreach($secciones_opt as $sec) {
                            $sel = ($filtro_seccion === $sec) ? 'selected' : '';
                            echo "<option value='{$sec}' {$sel}>Sección {$sec}</option>";
                        }
                        ?>
                    </select>

                    <select name="periodo" class="form-control" style="padding: 8px; border-radius: 6px; border: 1px solid var(--border-color); width: 160px; font-size: 0.88rem;" required>
                        <option value="">Período...</option>
                        <?php if (!empty($periodo_activo_db)): ?>
                            <option value="<?php echo htmlspecialchars($periodo_activo_db, ENT_QUOTES, 'UTF-8'); ?>" <?php echo ($filtro_periodo === $periodo_activo_db) ? 'selected' : ''; ?>><?php echo htmlspecialchars($periodo_activo_db, ENT_QUOTES, 'UTF-8'); ?></option>
                        <?php endif; ?>
                    </select>

                    <button type="submit" style="padding: 8px 16px; background: #059669; color: #fff; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 0.9rem;">
                        <i class="fas fa-filter"></i> Filtrar Alumnos
                    </button>
                    <?php if ($busqueda_realizada): ?>
                        <a href="alumnos.php" style="padding: 8px 14px; background: #64748b; color: #fff; border-radius: 6px; text-decoration: none; font-weight: 600; font-size: 0.9rem;" title="Limpiar">Limpiar</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <?php if ($busqueda_realizada): ?>
        <div class="card p-3">
            <?php if ($hay_sexto_grado): ?>
                <div class="batch-archive-bar">
                    <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                        <i class="fas fa-box-archive" style="color: #6d28d9;"></i>
                        <span><strong>Archivado por lote (6° Grado):</strong> <span id="contadorSeleccionados">0</span> seleccionados</span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="seleccionarTodosSexto(true)" style="font-size: 0.78rem;">
                            <i class="fas fa-check-double"></i> Seleccionar todos (6°)
                        </button>
                        <button type="button" class="btn-lote-archivar" id="btnArchivarLote" onclick="abrirModalArchivarLote()" disabled>
                            <i class="fas fa-box-archive"></i> Archivar Seleccionados
                        </button>
                    </div>
                </div>
            <?php endif; ?>

            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <?php if ($hay_sexto_grado): ?>
                                <th style="width: 42px; text-align: center;">
                                    <input type="checkbox" id="chkMaestroSexto" class="chk-select-archivo" title="Seleccionar todos los estudiantes de 6° grado" onchange="seleccionarTodosSexto(this.checked)">
                                </th>
                            <?php endif; ?>
                            <th style="width: 45px; text-align: center;">N°</th>
                            <th>Cédula Escolar</th>
                            <th>Nombres</th>
                            <th>Apellidos</th>
                            <th style="text-align: center;">Género</th>
                            <th>Grado</th>
                            <th>Sección</th>
                            <th>Docente Titular</th>
                            <th style="text-align: center; width: <?php echo $hay_sexto_grado ? '195px' : '160px'; ?>;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($filas)): ?>
                            <?php 
                            $num = 1;
                            foreach($filas as $fila): 
                                $cedula_est = $fila['cédula_escolar'] ?? '';
                                $nombres_est = $fila['nombres'] ?? '';
                                $apellidos_est = $fila['apellidos'] ?? '';
                                $seccion_est = $fila['seccion'] ?? '';
                                $periodo_retiro_fila = !empty($periodo_activo_db) ? $periodo_activo_db : ($fila['año_escolar'] ?? '');
                                $es_sexto_grado = (preg_replace('/[^0-9]/', '', (string)($fila['grado'] ?? '')) === '6');
                                $nombre_completo_est = trim($nombres_est . ' ' . $apellidos_est);
                                $json_fila = htmlspecialchars(json_encode($fila, JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8');
                                $ced_arch_js = htmlspecialchars(json_encode($cedula_est, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
                                $nom_arch_js = htmlspecialchars(json_encode($nombre_completo_est, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
                            ?>
                                <tr>
                                    <?php if ($hay_sexto_grado): ?>
                                        <td style="text-align: center;">
                                            <?php if ($es_sexto_grado): ?>
                                                <input type="checkbox" class="chk-select-archivo chk-sexto-item" value="<?php echo htmlspecialchars($cedula_est, ENT_QUOTES, 'UTF-8'); ?>" data-nombre="<?php echo htmlspecialchars($nombre_completo_est, ENT_QUOTES, 'UTF-8'); ?>" onchange="actualizarEstadoLote()">
                                            <?php else: ?>
                                                <span style="color: var(--text-secondary);">-</span>
                                            <?php endif; ?>
                                        </td>
                                    <?php endif; ?>
                                    <td style="text-align: center;"><?php echo $num; ?></td>
                                    <td><strong><?php echo mostrarDato($cedula_est); ?></strong></td>
                                    <td style="font-weight: 600; text-transform: uppercase;"><?php echo mostrarDato($nombres_est); ?></td>
                                    <td style="font-weight: 600; text-transform: uppercase;"><?php echo mostrarDato($apellidos_est); ?></td>
                                    <td style="text-align: center;"><?php echo renderGenero($fila['género']); ?></td>
                                    <td><?php echo mostrarDato($fila['grado']); ?>°</td>
                                    <td><?php echo mostrarDato($seccion_est); ?></td>
                                    <td><span class="badge bg-light text-dark border"><?php echo mostrarDato($fila['docente'] ?? 'Sin asignar'); ?></span></td>
                                    <td>
                                        <div class="btn-acciones">
                                            <button type="button" class="btn-accion btn-ver" title="Ver expediente completo" onclick="verExpedienteCompleto(<?php echo $json_fila; ?>)">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button type="button" class="btn-accion btn-carnet" title="Generar Ficha / Carnet" onclick="generarCarnet(<?php echo $json_fila; ?>)">
                                                <i class="fas fa-id-card"></i>
                                            </button>
                                            <a href="alumnos.php?modificar_cedula=<?php echo urlencode($cedula_est); ?>" class="btn-accion btn-editar" title="Modificar datos">
                                                <i class="fas fa-pen"></i>
                                            </a>
                                            <?php if ($es_sexto_grado): ?>
                                                <button type="button" class="btn-accion btn-archivar" title="Pasar a Archivados (6° Grado)" onclick='abrirModalArchivarUno(<?php echo $ced_arch_js; ?>, <?php echo $nom_arch_js; ?>)'>
                                                    <i class="fas fa-box-archive"></i>
                                                </button>
                                            <?php endif; ?>
                                            <button type="button" class="btn-accion btn-estado" title="Pasar a Retirados" onclick='abrirModalRetiro("<?php echo htmlspecialchars($cedula_est, ENT_QUOTES, 'UTF-8'); ?>", "<?php echo htmlspecialchars($fila['grado'] ?? '', ENT_QUOTES, 'UTF-8'); ?>", "<?php echo htmlspecialchars($seccion_est, ENT_QUOTES, 'UTF-8'); ?>", "<?php echo htmlspecialchars($periodo_retiro_fila, ENT_QUOTES, 'UTF-8'); ?>")'>
                                                <i class="fas fa-user-slash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php 
                                $num++;
                            endforeach; 
                            ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="<?php echo $hay_sexto_grado ? '10' : '9'; ?>" style="text-align: center; color: var(--text-secondary); padding: 35px;">
                                    No se encontraron estudiantes registrados con los parámetros ingresados.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </main>

    <div class="modal fade" id="modalExpediente" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content" style="border-radius: 12px;">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" style="font-weight: 700;"><i class="fas fa-file-medical-alt me-2"></i> Expediente Completo del Estudiante</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4" id="modalExpedienteBody" style="font-size: 0.88rem;"></div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalFichaEstudiante" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" style="max-width: 600px;">
            <div class="modal-content" style="border-radius: 14px; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.25);">
                <div class="modal-header text-white px-4 py-3" style="background: linear-gradient(135deg, #03a9f4, #0d6efd); border-top-left-radius: 14px; border-top-right-radius: 14px;">
                    <h5 class="modal-title" style="font-size: 1.05rem; font-weight: 700;"><i class="fas fa-id-card me-2"></i> Ficha del Estudiante</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4" id="modalFichaBody"></div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalRetiro" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius: 12px; border: none; box-shadow: 0 10px 25px rgba(0,0,0,0.2);">
                <div class="modal-header bg-danger text-white" style="border-top-left-radius: 12px; border-top-right-radius: 12px;">
                    <h5 class="modal-title" style="font-size: 1rem; font-weight: 700;"><i class="fas fa-user-slash me-2"></i> Retirar Estudiante</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label for="inputMotivoRetiro" class="form-label" style="font-size: 0.85rem; font-weight: 600;">Motivo del retiro:</label>
                        <input type="text" id="inputMotivoRetiro" class="form-control" value="Retiro institucional" style="font-size: 0.9rem;">
                    </div>
                </div>
                <div class="modal-footer" style="border-top: 1px solid var(--border-color);">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-danger btn-sm" id="btnConfirmarRetiro">Confirmar Retiro</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalArchivar" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius: 12px; border: 1px solid var(--border-color); box-shadow: 0 10px 30px rgba(0,0,0,0.25);">
                <div class="modal-header text-white" style="background-color: #6d28d9; border-top-left-radius: 11px; border-top-right-radius: 11px;">
                    <h5 class="modal-title" style="font-size: 1rem; font-weight: 700;"><i class="fas fa-box-archive me-2"></i> Pasar a Archivados (6° Grado)</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="alumnos.php" id="formArchivarEstudiantes" style="margin: 0;">
                    <input type="hidden" name="accion" value="archivar_estudiantes">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
                    <div id="contenedorCedulasArchivar"></div>
                    <div class="modal-body p-4" style="font-size: 0.9rem;">
                        <p class="mb-2" id="textoConfirmacionArchivar"></p>
                        <p class="mb-0" style="color: var(--text-secondary); font-size: 0.83rem;">El estudiante dejará de aparecer en el listado activo y quedará guardado en el módulo de Archivados.</p>
                    </div>
                    <div class="modal-footer" style="border-top: 1px solid var(--border-color);">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-sm text-white" style="background-color: #6d28d9; font-weight: 600;">
                            <i class="fas fa-box-archive me-1"></i> Confirmar Archivado
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const periodoInstitucionalActivo = <?php echo json_encode($periodo_activo_db); ?>;

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
            const tipo = document.getElementById('tipoBusqueda').value;
            const input = document.getElementById('inputValorBusqueda');
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

        document.getElementById('inputValorBusqueda').addEventListener('input', function() {
            const tipo = document.getElementById('tipoBusqueda').value;
            if (tipo === 'cedula') {
                this.value = this.value.replace(/[^0-9]/g, '').slice(0, 15);
            } else {
                this.value = this.value.replace(/[^A-Za-zÁÉÍÓÚáéíóúñÑ\s]/g, '').slice(0, 50);
            }
        });

        document.addEventListener('DOMContentLoaded', function() {
            actualizarReglaValidacion(false);
        });

        function seleccionarTodosSexto(marcar) {
            const items = document.querySelectorAll('.chk-sexto-item');
            items.forEach(function(chk) {
                chk.checked = marcar;
            });
            const maestro = document.getElementById('chkMaestroSexto');
            if (maestro) maestro.checked = marcar && items.length > 0;
            actualizarEstadoLote();
        }

        function actualizarEstadoLote() {
            const items = document.querySelectorAll('.chk-sexto-item');
            const marcados = document.querySelectorAll('.chk-sexto-item:checked');
            const contador = document.getElementById('contadorSeleccionados');
            const btnLote = document.getElementById('btnArchivarLote');
            const maestro = document.getElementById('chkMaestroSexto');

            if (contador) contador.textContent = marcados.length;
            if (btnLote) btnLote.disabled = (marcados.length === 0);
            if (maestro) maestro.checked = (items.length > 0 && marcados.length === items.length);
        }

        function prepararModalArchivar(cedulas, textoHtml) {
            const contenedor = document.getElementById('contenedorCedulasArchivar');
            contenedor.innerHTML = '';
            cedulas.forEach(function(ced) {
                const inp = document.createElement('input');
                inp.type = 'hidden';
                inp.name = 'cedulas[]';
                inp.value = ced;
                contenedor.appendChild(inp);
            });
            document.getElementById('textoConfirmacionArchivar').innerHTML = textoHtml;
            const modal = new bootstrap.Modal(document.getElementById('modalArchivar'));
            modal.show();
        }

        function abrirModalArchivarUno(cedula, nombre) {
            const safeNom = document.createElement('div');
            safeNom.textContent = nombre;
            const safeCed = document.createElement('div');
            safeCed.textContent = cedula;
            const msg = '¿Desea pasar a <strong>Archivados</strong> al estudiante <strong>' + safeNom.innerHTML + '</strong> (Cédula: <strong>' + safeCed.innerHTML + '</strong>)?';
            prepararModalArchivar([cedula], msg);
        }

        function abrirModalArchivarLote() {
            const marcados = document.querySelectorAll('.chk-sexto-item:checked');
            if (marcados.length === 0) return;
            const cedulas = [];
            marcados.forEach(function(chk) {
                cedulas.push(chk.value);
            });
            const msg = '¿Desea pasar a <strong>Archivados</strong> los <strong>' + cedulas.length + ' estudiantes de 6° grado</strong> seleccionados?';
            prepararModalArchivar(cedulas, msg);
        }

        function verExpedienteCompleto(data) {
            let html = `
                <h6 class="text-primary fw-bold">1. Datos Personales, Antropométricos y Académicos</h6>
                <hr class="my-1">
                <div class="row g-2 mb-3">
                    <div class="col-md-4"><strong>Cédula Escolar:</strong> ${data.cédula_escolar || '-'}</div>
                    <div class="col-md-4"><strong>Nombres:</strong> ${data.nombres || '-'}</div>
                    <div class="col-md-4"><strong>Apellidos:</strong> ${data.apellidos || '-'}</div>
                    <div class="col-md-4"><strong>Fecha de Nacimiento:</strong> ${data.fecha_nacimiento || '-'}</div>
                    <div class="col-md-4"><strong>Género:</strong> ${data.género || '-'}</div>
                    <div class="col-md-4"><strong>Nacionalidad:</strong> ${data.nacionalidad || '-'}</div>
                    <div class="col-md-4"><strong>Entidad Federal:</strong> ${data.entidad_federal || '-'}</div>
                    <div class="col-md-4"><strong>Lugar de Nacimiento:</strong> ${data.lugar_nacimiento || '-'}</div>
                    <div class="col-md-4"><strong>Municipio / Parroquia Nac.:</strong> ${data.municipio_nacimiento || '-'} / ${data.parroquia_nacimiento || '-'}</div>
                    <div class="col-md-4"><strong>Tipo de Ingreso:</strong> ${data.tipo_ingreso || '-'}</div>
                    <div class="col-md-4"><strong>Plantel de Procedencia:</strong> ${data.plantel_procedencia || '-'}</div>
                    <div class="col-md-4"><strong>Grado y Sección:</strong> ${data.grado || '-'}° - Sección ${data.seccion || '-'}</div>
                    <div class="col-md-4"><strong>Período Escolar:</strong> ${periodoInstitucionalActivo || data.año_escolar || '-'}</div>
                    <div class="col-md-4"><strong>Docente Titular:</strong> ${data.docente || 'Sin asignar'}</div>
                    <div class="col-md-4"><strong>Peso / Talla:</strong> ${data.peso || '-'} / ${data.talla || '-'}</div>
                    <div class="col-md-4"><strong>Talla Camisa / Pantalón / Calzado:</strong> ${data.talla_camisa || '-'} / ${data.talla_pantalón || '-'} / ${data.calzado || '-'}</div>
                    <div class="col-md-8"><strong>Dirección de Domicilio:</strong> ${data.dirección_domicilio || '-'}</div>
                    <div class="col-md-4"><strong>Religión:</strong> ${data.religión || '-'}</div>
                    <div class="col-md-4"><strong>¿Con quién vive?:</strong> ${data.con_quien_vive || '-'}</div>
                    <div class="col-md-4"><strong>¿Padres separados?:</strong> ${data.padres_separados == 1 ? 'Sí' : 'No'}</div>
                    <div class="col-md-4"><strong>¿Ha repetido grado?:</strong> ${data.ha_repetido_grado == 1 ? 'Sí (' + (data.grados_repetidos || '-') + ')' : 'No'}</div>
                    <div class="col-md-4"><strong>¿Necesidades Educativas Esp.?:</strong> ${data.necesidades_especiales == 1 ? 'Sí' : 'No'}</div>
                    <div class="col-md-4"><strong>Descripción Condición:</strong> ${data.descripción_condición || 'Ninguna'}</div>
                    <div class="col-md-4"><strong>¿Informe Especialista?:</strong> ${data.informe_especialista == 1 ? 'Sí' : 'No'}</div>
                    <div class="col-md-4"><strong>¿Informe Alimentación?:</strong> ${data.informe_alimentación == 1 ? 'Sí' : 'No'}</div>
                    <div class="col-md-4"><strong>¿Atendido Aula Integrada?:</strong> ${data.atendido_aula_integrada == 1 ? 'Sí' : 'No'}</div>
                </div>

                <h6 class="text-primary fw-bold mt-3">2. Datos del Representante Legal</h6>
                <hr class="my-1">
                <div class="row g-2 mb-3">
                    <div class="col-md-4"><strong>Nombres y Apellidos:</strong> ${data.rep_nombre_completo || '-'}</div>
                    <div class="col-md-4"><strong>Cédula / RIF:</strong> ${data.rep_ci || '-'} / ${data.rep_rif || '-'}</div>
                    <div class="col-md-4"><strong>Parentesco:</strong> ${data.rep_parentesco || '-'}</div>
                    <div class="col-md-4"><strong>Fecha de Nacimiento:</strong> ${data.rep_fecha_nac || '-'}</div>
                    <div class="col-md-4"><strong>Teléfonos:</strong> ${data.rep_telefono || '-'} ${data.rep_telefono_res ? ' / ' + data.rep_telefono_res : ''}</div>
                    <div class="col-md-4"><strong>Correo Electrónico:</strong> ${data.rep_correo || '-'}</div>
                    <div class="col-md-6"><strong>Dirección Habitación:</strong> ${data.rep_direccion || '-'}</div>
                    <div class="col-md-6"><strong>Nivel Instrucción / Profesión:</strong> ${data.rep_nivel_instruccion || '-'} / ${data.rep_profesion || '-'}</div>
                    <div class="col-md-4"><strong>Ocupación / Empresa:</strong> ${data.rep_ocupacion || '-'} / ${data.rep_empresa || '-'}</div>
                    <div class="col-md-4"><strong>Teléfono / Dir. Empresa:</strong> ${data.rep_telefono_empresa || '-'} (${data.rep_direccion_empresa || '-'})</div>
                    <div class="col-md-4"><strong>Autorización Legal / Poder:</strong> ${data.autorización_legal == 1 ? 'Sí' : 'No'} (${data.número_poder_notariado || 'N/A'})</div>
                </div>

                <h6 class="text-primary fw-bold mt-3">3. Datos de la Madre y del Padre</h6>
                <hr class="my-1">
                <div class="row g-2 mb-3">
                    <div class="col-md-6 border-end">
                        <strong class="text-secondary d-block mb-1">DATOS DE LA MADRE:</strong>
                        <div><strong>Nombres y Apellidos:</strong> ${(data.madre_nombre_completo || '').trim() || 'No registrada'}</div>
                        <div><strong>Cédula / Fecha Nac.:</strong> ${data.madre_ci || '-'} / ${data.madre_fecha_nac || '-'}</div>
                        <div><strong>Estado Civil / Lugar Nac.:</strong> ${data.madre_estado_civil || '-'} / ${data.madre_lugar_nac || '-'}</div>
                        <div><strong>Teléfono / Correo:</strong> ${data.madre_telefono || '-'} / ${data.madre_correo || '-'}</div>
                        <div><strong>Dirección Habitación:</strong> ${data.madre_direccion || '-'}</div>
                        <div><strong>Instrucción / Profesión / Ocupación:</strong> ${data.madre_nivel_instruccion || '-'} / ${data.madre_profesion || '-'} / ${data.madre_ocupacion || '-'}</div>
                        <div><strong>Empresa / Teléf. / Dirección:</strong> ${data.madre_empresa || '-'} / ${data.madre_telefono_empresa || '-'} / ${data.madre_direccion_empresa || '-'}</div>
                    </div>
                    <div class="col-md-6">
                        <strong class="text-secondary d-block mb-1">DATOS DEL PADRE:</strong>
                        <div><strong>Nombres y Apellidos:</strong> ${(data.padre_nombre_completo || '').trim() || 'No registrado'}</div>
                        <div><strong>Cédula / Fecha Nac.:</strong> ${data.padre_ci || '-'} / ${data.padre_fecha_nac || '-'}</div>
                        <div><strong>Estado Civil / Lugar Nac.:</strong> ${data.padre_estado_civil || '-'} / ${data.padre_lugar_nac || '-'}</div>
                        <div><strong>Teléfono / Correo:</strong> ${data.padre_telefono || '-'} / ${data.padre_correo || '-'}</div>
                        <div><strong>Dirección Habitación:</strong> ${data.padre_direccion || '-'}</div>
                        <div><strong>Instrucción / Profesión / Ocupación:</strong> ${data.padre_nivel_instruccion || '-'} / ${data.padre_profesion || '-'} / ${data.padre_ocupacion || '-'}</div>
                        <div><strong>Empresa / Teléf. / Dirección:</strong> ${data.padre_empresa || '-'} / ${data.padre_telefono_empresa || '-'} / ${data.padre_direccion_empresa || '-'}</div>
                    </div>
                </div>

                <h6 class="text-primary fw-bold mt-3">4. Vivienda, Salud, Vacunas y Traslado</h6>
                <hr class="my-1">
                <div class="row g-2">
                    <div class="col-md-4"><strong>Condición / Tipo Vivienda:</strong> ${data.condición_vivienda || '-'} (${data.tipo_vivienda || '-'})</div>
                    <div class="col-md-4"><strong>Hermanos en la Escuela:</strong> ${data.hermanos_escuela || 'Ninguno'}</div>
                    <div class="col-md-4"><strong>Vacunas Recibidas:</strong> ${data.nombre_vacuna || '-'}</div>
                    <div class="col-md-4"><strong>Medicamentos Contraindicados:</strong> ${data.nombre_medicamento || 'Ninguno'}</div>
                    <div class="col-md-4"><strong>Patologías y Tratamientos:</strong> ${data.enfermedades_tratamiento || 'Ninguna'}</div>
                    <div class="col-md-4"><strong>Condición Importante (Docente):</strong> ${data.condición_importante || 'Ninguna'}</div>
                    <div class="col-md-6"><strong>Traslado de Entrada:</strong> ${data.entrada_modalidad || '-'} — Traído por: ${data.entrada_persona || '-'} (${data.entrada_parentesco || '-'})</div>
                    <div class="col-md-6"><strong>Traslado de Salida:</strong> ${data.salida_modalidad || '-'} — Retirado por: ${data.salida_persona || '-'} (${data.salida_parentesco || '-'})</div>
                    <div class="col-md-12"><strong>Problemas de la Comunidad:</strong> ${data.problema_comunidad || 'Ninguno'}</div>
                </div>
            `;
            document.getElementById('modalExpedienteBody').innerHTML = html;
            var modal = new bootstrap.Modal(document.getElementById('modalExpediente'));
            modal.show();
        }

        function generarCarnet(data) {
            let html = `
                <div class="row align-items-center">
                    <div class="col-7">
                        <div class="row mb-2">
                            <div class="col-6">
                                <small class="text-muted d-block" style="font-size: 0.7rem;">Cédula Escolar</small>
                                <span class="fw-bold" style="font-size: 0.85rem;">${data.cédula_escolar || '-'}</span>
                            </div>
                            <div class="col-6">
                                <small class="text-muted d-block" style="font-size: 0.7rem;">Año Escolar</small>
                                <span class="fw-bold" style="font-size: 0.85rem;">${periodoInstitucionalActivo || data.año_escolar || '-'}</span>
                            </div>
                        </div>
                        <hr class="my-1">
                        <div class="mb-2">
                            <small class="text-muted d-block" style="font-size: 0.7rem;">Estudiante</small>
                            <span class="fw-bold text-uppercase" style="font-size: 0.85rem;">${data.apellidos || ''} ${data.nombres || ''}</span>
                        </div>
                        <hr class="my-1">
                        <div class="row mb-2">
                            <div class="col-6">
                                <small class="text-muted d-block" style="font-size: 0.7rem;">Teléfono Rep.</small>
                                <span style="font-size: 0.8rem;">${data.rep_telefono || '-'}</span>
                            </div>
                            <div class="col-6">
                                <small class="text-muted d-block" style="font-size: 0.7rem;">Docente</small>
                                <span style="font-size: 0.75rem;" class="text-truncate d-block">${data.docente || 'Sin asignar'}</span>
                            </div>
                        </div>
                        <hr class="my-1">
                        <div class="row">
                            <div class="col-4">
                                <small class="text-muted d-block" style="font-size: 0.7rem;">Grado</small>
                                <span style="font-size: 0.8rem;" class="fw-bold">${data.grado || '-'}°</span>
                            </div>
                            <div class="col-4">
                                <small class="text-muted d-block" style="font-size: 0.7rem;">Sección</small>
                                <span style="font-size: 0.8rem;" class="fw-bold">${data.seccion || '-'}</span>
                            </div>
                            <div class="col-4">
                                <small class="text-muted d-block" style="font-size: 0.7rem;">Género</small>
                                <span style="font-size: 0.8rem;">${data.género || '-'}</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-5 text-center border-start d-flex flex-column justify-content-center align-items-center">
                        <small class="text-muted d-block mb-1" style="font-size: 0.7rem;">Fotografía del Estudiante</small>
                        <div class="bg-secondary text-white rounded d-flex align-items-center justify-content-center shadow-sm" style="width: 115px; height: 140px; font-size: 2.5rem;">
                            <i class="fas fa-user"></i>
                        </div>
                    </div>
                </div>
            `;
            document.getElementById('modalFichaBody').innerHTML = html;
            var modal = new bootstrap.Modal(document.getElementById('modalFichaEstudiante'));
            modal.show();
        }

        let retiroPendiente = {};

        function abrirModalRetiro(cedula, grado, seccion, periodo) {
            retiroPendiente = { cedula, grado, seccion, periodo };
            document.getElementById('inputMotivoRetiro').value = "Retiro institucional";
            var modalRetiro = new bootstrap.Modal(document.getElementById('modalRetiro'));
            modalRetiro.show();
        }

        document.getElementById('btnConfirmarRetiro').addEventListener('click', function() {
            let motivo = document.getElementById('inputMotivoRetiro').value.trim();
            if (motivo === "") motivo = "Retiro institucional";

            let { cedula, grado, seccion, periodo } = retiroPendiente;
            window.location.href = `alumnos.php?retirar_cedula=${encodeURIComponent(cedula)}&motivo=${encodeURIComponent(motivo)}&grado=${encodeURIComponent(grado)}&seccion=${encodeURIComponent(seccion)}&periodo=${encodeURIComponent(periodo)}`;
        });
    </script>
</body>
</html>