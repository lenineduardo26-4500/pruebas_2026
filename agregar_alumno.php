<?php
session_start();
if (isset($_SESSION['usuario'])) {
    if (!isset($_SESSION['init'])) {
        session_regenerate_id(true);
        $_SESSION['init'] = true;
    }
}
error_reporting(0);
ini_set('display_errors', 0);

include("conexion.php");
if ($conexion instanceof mysqli) {
    mysqli_set_charset($conexion, "utf8mb4");
}

$es_administrador = isset($_SESSION['rol']) && $_SESSION['rol'] === 'Administrador';
$rol_actual = $_SESSION['rol'] ?? 'Administrador';
$mensaje_error = "";

$conf_db = [];
if ($conexion instanceof PDO) {
    try {
        $res_conf = $conexion->query("SELECT clave, valor FROM configuracion");
        if ($res_conf) {
            while($r = $res_conf->fetch(PDO::FETCH_ASSOC)) {
                $conf_db[$r['clave']] = $r['valor'];
            }
        }
    } catch (Exception $e) {}
} else {
    $res_conf = @mysqli_query($conexion, "SELECT clave, valor FROM configuracion");
    if ($res_conf) {
        while($r = mysqli_fetch_assoc($res_conf)) {
            $conf_db[$r['clave']] = $r['valor'];
        }
    }
}
$nombre_escuela = $conf_db['nombre_escuela'] ?? 'ESCUELA BÁSICA "GLADYS C. LOBO DE CARNEVALI"';
$periodo_activo_db = $conf_db['periodo_escolar'] ?? '';

function obtenerDocentePorGradoSeccion($conexion, $grado_val, $seccion_val, $periodo_activo) {
    $grado_limpio = trim((string)$grado_val);
    $sec_raw = trim((string)$seccion_val);
    if ($grado_limpio === '' || $sec_raw === '') {
        return '';
    }
    $sec_alt = ($sec_raw === 'U') ? 'Única' : (($sec_raw === 'Única') ? 'U' : $sec_raw);

    if ($conexion instanceof PDO) {
        try {
            $sql_doc = "SELECT d.nombres_apellidos
                        FROM docente_seccion ds
                        INNER JOIN docentes d ON ds.id_docente = d.id
                        INNER JOIN grados g ON ds.id_grado = g.id
                        INNER JOIN secciones s ON ds.id_seccion = s.id
                        WHERE TRIM(g.nombre_grado) = :grado
                          AND (TRIM(s.nombre_seccion) = :sec1 OR TRIM(s.nombre_seccion) = :sec2)
                          AND COALESCE(d.estado, 'Activo') = 'Activo'
                        ORDER BY (TRIM(ds.`año_escolar`) = :periodo) DESC, ds.id DESC
                        LIMIT 1";
            $st_doc = $conexion->prepare($sql_doc);
            $st_doc->execute([
                ':grado'   => $grado_limpio,
                ':sec1'    => $sec_raw,
                ':sec2'    => $sec_alt,
                ':periodo' => trim((string)$periodo_activo)
            ]);
            $fila_doc = $st_doc->fetch(PDO::FETCH_ASSOC);
            if ($fila_doc && !empty($fila_doc['nombres_apellidos'])) {
                return mb_convert_case(trim($fila_doc['nombres_apellidos']), MB_CASE_TITLE, 'UTF-8');
            }
        } catch (Exception $e) {}
    } elseif ($conexion instanceof mysqli) {
        $g_esc = mysqli_real_escape_string($conexion, $grado_limpio);
        $s1_esc = mysqli_real_escape_string($conexion, $sec_raw);
        $s2_esc = mysqli_real_escape_string($conexion, $sec_alt);
        $p_esc = mysqli_real_escape_string($conexion, trim((string)$periodo_activo));
        $sql_doc = "SELECT d.nombres_apellidos
                    FROM docente_seccion ds
                    INNER JOIN docentes d ON ds.id_docente = d.id
                    INNER JOIN grados g ON ds.id_grado = g.id
                    INNER JOIN secciones s ON ds.id_seccion = s.id
                    WHERE TRIM(g.nombre_grado) = '$g_esc'
                      AND (TRIM(s.nombre_seccion) = '$s1_esc' OR TRIM(s.nombre_seccion) = '$s2_esc')
                      AND COALESCE(d.estado, 'Activo') = 'Activo'
                    ORDER BY (TRIM(ds.`año_escolar`) = '$p_esc') DESC, ds.id DESC
                    LIMIT 1";
        $res_doc = @mysqli_query($conexion, $sql_doc);
        if ($res_doc && $fila_doc = mysqli_fetch_assoc($res_doc)) {
            if (!empty($fila_doc['nombres_apellidos'])) {
                return mb_convert_case(trim($fila_doc['nombres_apellidos']), MB_CASE_TITLE, 'UTF-8');
            }
        }
    }
    return '';
}

if (isset($_GET['buscar_docente_ajax']) && isset($_GET['grado']) && isset($_GET['seccion'])) {
    $docente_hallado = obtenerDocentePorGradoSeccion($conexion, $_GET['grado'], $_GET['seccion'], $periodo_activo_db);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'encontrado' => ($docente_hallado !== ''),
        'docente'    => $docente_hallado
    ]);
    exit();
}

if (isset($_GET['buscar_regular_ajax']) && isset($_GET['termino']) && isset($_GET['tipo'])) {
    $termino = trim($_GET['termino']);
    $tipo = trim($_GET['tipo']);
    $resultados = [];
    if ($termino !== '' && $conexion instanceof PDO) {
        try {
            if ($tipo === 'cedula') {
                $sql_b = "SELECT e.`cédula_escolar`, e.nombres, e.apellidos, g.nombre_grado AS grado, s.nombre_seccion AS seccion 
                          FROM estudiante e 
                          LEFT JOIN inscripcion i ON e.`cédula_escolar` = i.`cédula_escolar_estudiante` 
                          LEFT JOIN grados g ON i.id_grado = g.id 
                          LEFT JOIN secciones s ON i.id_seccion = s.id 
                          WHERE e.`cédula_escolar` LIKE :t 
                          GROUP BY e.`cédula_escolar` 
                          LIMIT 8";
                $st_b = $conexion->prepare($sql_b);
                $st_b->execute([':t' => "%" . $termino . "%"]);
            } else {
                $sql_b = "SELECT e.`cédula_escolar`, e.nombres, e.apellidos, g.nombre_grado AS grado, s.nombre_seccion AS seccion 
                          FROM estudiante e 
                          LEFT JOIN inscripcion i ON e.`cédula_escolar` = i.`cédula_escolar_estudiante` 
                          LEFT JOIN grados g ON i.id_grado = g.id 
                          LEFT JOIN secciones s ON i.id_seccion = s.id 
                          WHERE e.nombres LIKE :t OR e.apellidos LIKE :t 
                          GROUP BY e.`cédula_escolar` 
                          LIMIT 8";
                $st_b = $conexion->prepare($sql_b);
                $st_b->execute([':t' => "%" . $termino . "%"]);
            }
            $resultados = $st_b->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {}
    }
    header('Content-Type: application/json');
    echo json_encode($resultados);
    exit();
}

if (isset($_GET['cargar_regular_ajax']) && isset($_GET['cedula'])) {
    $cedula_mod = trim($_GET['cedula']);
    $respuesta = ['exito' => false];

    if ($cedula_mod !== '' && $conexion instanceof PDO) {
        try {
            $sql_mod = "SELECT 
                            e.*,
                            i.tipo_estudiante AS tipo_ingreso,
                            g.nombre_grado AS grado,
                            s.nombre_seccion AS seccion_nombre,
                            i.`año_escolar`,
                            i.plantel_procedencia,
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
                        WHERE e.`cédula_escolar` = :ced_exacta 
                           OR e.`cédula_escolar` = :ced_v 
                           OR e.`cédula_escolar` = :ced_e 
                           OR e.`cédula_escolar` LIKE :ced_like
                        LIMIT 1";

            $st = $conexion->prepare($sql_mod);
            $st->execute([
                ':ced_exacta' => $cedula_mod,
                ':ced_v'      => 'V-' . $cedula_mod,
                ':ced_e'      => 'E-' . $cedula_mod,
                ':ced_like'   => '%-' . $cedula_mod
            ]);
            $datos = $st->fetch(PDO::FETCH_ASSOC);

            if ($datos) {
                $_SESSION['modo_edicion'] = true;
                $_SESSION['cedula_original'] = $datos['cédula_escolar'];

                $partes_ced_est = explode('-', $datos['cédula_escolar']);
                $nac_ced_est = (count($partes_ced_est) > 1) ? $partes_ced_est[0] : 'V';
                $num_ced_est = (count($partes_ced_est) > 1) ? $partes_ced_est[1] : $datos['cédula_escolar'];
                $sec_form = ($datos['seccion_nombre'] === 'Única') ? 'U' : ($datos['seccion_nombre'] ?? '');
                $docente_asignado_bd = obtenerDocentePorGradoSeccion($conexion, $datos['grado'] ?? '', $sec_form, $periodo_activo_db);

                $_SESSION['estudiante'] = [
                    'tipo_ingreso'          => 'Estudiante regular',
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
                    'docente'               => $docente_asignado_bd,
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

                $respuesta = [
                    'exito'      => true,
                    'estudiante' => $_SESSION['estudiante']
                ];
            }
        } catch (Exception $e) {}
    }

    header('Content-Type: application/json');
    echo json_encode($respuesta);
    exit();
}

if (isset($_GET['modo_nuevo_ajax'])) {
    unset($_SESSION['modo_edicion'], $_SESSION['cedula_original'], $_SESSION['estudiante'], $_SESSION['representante'], $_SESSION['madre'], $_SESSION['padre'], $_SESSION['adicional']);
    header('Content-Type: application/json');
    echo json_encode(['ok' => true]);
    exit();
}

if (isset($_GET['verificar_ajax']) && isset($_GET['num'])) {
    $num_ajax = trim($_GET['num']);
    $existe_ajax = false;
    $ced_orig = $_SESSION['cedula_original'] ?? '';
    $en_edicion = !empty($_SESSION['modo_edicion']);
    
    if (!empty($num_ajax)) {
        if ($conexion instanceof PDO) {
            try {
                if ($en_edicion && $ced_orig !== '') {
                    $stmt_ajx = $conexion->prepare("SELECT `cédula_escolar` FROM estudiante WHERE `cédula_escolar` LIKE :num AND `cédula_escolar` != :orig LIMIT 1");
                    $stmt_ajx->execute([':num' => '%-' . $num_ajax, ':orig' => $ced_orig]);
                } else {
                    $stmt_ajx = $conexion->prepare("SELECT `cédula_escolar` FROM estudiante WHERE `cédula_escolar` LIKE :num LIMIT 1");
                    $stmt_ajx->execute([':num' => '%-' . $num_ajax]);
                }
                if ($stmt_ajx->fetch()) { $existe_ajax = true; }
            } catch (Exception $e) {}
        } else {
            $num_esc_ajx = mysqli_real_escape_string($conexion, $num_ajax);
            $orig_esc = mysqli_real_escape_string($conexion, $ced_orig);
            $cond_orig = ($en_edicion && $ced_orig !== '') ? " AND `cédula_escolar` != '$orig_esc'" : "";
            $res_ajx = @mysqli_query($conexion, "SELECT `cédula_escolar` FROM estudiante WHERE `cédula_escolar` LIKE '%-$num_esc_ajx'$cond_orig LIMIT 1");
            if ($res_ajx && mysqli_num_rows($res_ajx) > 0) { $existe_ajax = true; }
        }
    }
    
    header('Content-Type: application/json');
    echo json_encode(['existe' => $existe_ajax, 'cedula' => $num_ajax]);
    exit();
}

if (isset($_POST['guardar_estudiante'])) {
    $nac_est = $_POST['nacionalidad_cedula'] ?? 'V';
    $num_est = trim($_POST['numero_cedula_alumno'] ?? '');
    $_POST['numero_cedula_alumno'] = $num_est;
    $cedula_completa = !empty($num_est) ? $nac_est . '-' . $num_est : '';
    $_POST['cedula_alumno'] = $cedula_completa;
    $_POST['periodo_escolar'] = $periodo_activo_db;

    $tipo_ing_post = $_POST['tipo_ingreso'] ?? '';
    if ($tipo_ing_post === 'Nuevo ingreso') {
        $_POST['plantel_procedencia'] = trim($_POST['plantel_procedencia'] ?? '');
        if (empty($_POST['es_regular_cargado'])) {
            unset($_SESSION['modo_edicion'], $_SESSION['cedula_original']);
        }
    } else {
        $_POST['plantel_procedencia'] = '';
        if (!empty($cedula_completa)) {
            $_SESSION['modo_edicion'] = true;
            if (empty($_SESSION['cedula_original'])) {
                $_SESSION['cedula_original'] = $cedula_completa;
            }
        }
    }

    $grado_post = trim($_POST['grado'] ?? '');
    $seccion_post = trim($_POST['seccion'] ?? '');
    $_POST['docente'] = obtenerDocentePorGradoSeccion($conexion, $grado_post, $seccion_post, $periodo_activo_db);

    if (!in_array($grado_post, ['1', '2', '3', '4', '5', '6'])) {
        $mensaje_error = "<div style='background-color: #f8d7da; color: #721c24; padding: 12px; border-radius: 6px; margin-bottom: 15px; font-size: 0.88rem; font-weight: 600; border: 1px solid #f5c6cb;'><i class='fas fa-exclamation-triangle'></i> Error: El grado a cursar debe estar comprendido entre 1.° y 6.° grado.</div>";
    } else {
        $_SESSION['estudiante'] = array_merge($_SESSION['estudiante'] ?? [], $_POST);

        $existe_estudiante = false;
        $ced_orig = $_SESSION['cedula_original'] ?? '';
        $en_edicion = !empty($_SESSION['modo_edicion']);

        if (!empty($num_est)) {
            if ($conexion instanceof PDO) {
                try {
                    if ($en_edicion && $ced_orig !== '') {
                        $stmt_check = $conexion->prepare("SELECT `cédula_escolar` FROM estudiante WHERE `cédula_escolar` LIKE :num AND `cédula_escolar` != :orig LIMIT 1");
                        $stmt_check->execute([':num' => '%-' . $num_est, ':orig' => $ced_orig]);
                    } else {
                        $stmt_check = $conexion->prepare("SELECT `cédula_escolar` FROM estudiante WHERE `cédula_escolar` LIKE :num LIMIT 1");
                        $stmt_check->execute([':num' => '%-' . $num_est]);
                    }
                    if ($stmt_check->fetch()) { $existe_estudiante = true; }
                } catch (Exception $e) {}
            } elseif ($conexion instanceof mysqli) {
                $num_escapado = mysqli_real_escape_string($conexion, $num_est);
                $orig_esc = mysqli_real_escape_string($conexion, $ced_orig);
                $cond_orig = ($en_edicion && $ced_orig !== '') ? " AND `cédula_escolar` != '$orig_esc'" : "";
                $res_check = @mysqli_query($conexion, "SELECT `cédula_escolar` FROM estudiante WHERE `cédula_escolar` LIKE '%-$num_escapado'$cond_orig LIMIT 1");
                if ($res_check && mysqli_num_rows($res_check) > 0) { $existe_estudiante = true; }
            }
        }

        if ($existe_estudiante) {
            $mensaje_error = "<div style='background-color: #f8d7da; color: #721c24; padding: 12px; border-radius: 6px; margin-bottom: 15px; font-size: 0.88rem; font-weight: 600; border: 1px solid #f5c6cb;'><i class='fas fa-exclamation-triangle'></i> No se puede continuar: El número de cédula <strong>" . htmlspecialchars($num_est) . "</strong> ya se encuentra registrado en el sistema.</div>";
        } else {
            header("Location: agregar_representante.php");
            exit();
        }
    }
}

$tipo_ingreso_actual = $_SESSION['estudiante']['tipo_ingreso'] ?? '';
$tiene_datos_cargados = !empty($_SESSION['estudiante']['numero_cedula_alumno']);
$en_modo_edicion = !empty($_SESSION['modo_edicion']);
$docente_inicial_sesion = obtenerDocentePorGradoSeccion($conexion, $_SESSION['estudiante']['grado'] ?? '', $_SESSION['estudiante']['seccion'] ?? '', $periodo_activo_db);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrar Alumno - Sistema de Inscripción</title>
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
        body.dark-mode .search-panel-box {
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

        .card { background: #FFFFFF; padding: 20px; border-radius: 12px; box-shadow: var(--card-shadow); margin-bottom: 20px; width: 100%; box-sizing: border-box; border: 1px solid var(--border-color); }
        .card h3 { font-size: 1.15rem; font-weight: 700; margin-bottom: 5px; color: var(--text-primary); }
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 10px 12px; margin-bottom: 15px; }
        .form-group { display: flex; flex-direction: column; }
        .form-group label { font-size: 0.82rem; font-weight: 600; margin-bottom: 3px; color: var(--text-secondary); }
        .form-group input, .form-group select, .form-group textarea { padding: 5px 8px; border: 1px solid var(--border-color); border-radius: 8px; font-size: 0.85rem; width: 100%; box-sizing: border-box; background-color: var(--bg-surface); color: var(--text-primary); height: 32px; }
        .form-group textarea { resize: vertical; height: 38px; }
        .cedula-group { display: flex; gap: 5px; }
        .cedula-group select { width: 75px; flex-shrink: 0; }
        .btn-container { display: flex; justify-content: flex-end; margin-top: 15px; }
        .btn-next { background-color: #2563eb; color: white; border: none; padding: 8px 20px; border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 0.88rem; transition: background-color 0.2s; }
        .btn-next:hover { background-color: #1d4ed8; }
        .btn-next:disabled { background-color: #64748b; cursor: not-allowed; opacity: 0.65; }

        .search-panel-box {
            background-color: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: 12px;
            padding: 16px 20px;
            box-shadow: var(--card-shadow);
            margin-bottom: 20px;
            position: relative;
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

        .resultados-busqueda-lista {
            position: absolute;
            top: 100%;
            left: 240px;
            width: 320px;
            background-color: var(--bg-surface);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.25);
            z-index: 100;
            max-height: 200px;
            overflow-y: auto;
            display: none;
            margin-top: 3px;
        }

        body.dark-mode .resultados-busqueda-lista {
            background-color: var(--bg-surface) !important;
            border-color: var(--border-color) !important;
        }

        .item-resultado-alumno {
            padding: 8px 12px;
            border-bottom: 1px solid var(--border-color);
            cursor: pointer;
            font-size: 0.82rem;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
        }

        .item-resultado-alumno:last-child { border-bottom: none; }
        .item-resultado-alumno:hover { background-color: rgba(37, 99, 235, 0.12); }
        
        body.dark-mode .item-resultado-alumno:hover {
            background-color: rgba(59, 130, 246, 0.2) !important;
        }

        .badge-grado-sec {
            background-color: #0d6efd;
            color: #ffffff;
            font-size: 0.72rem;
            font-weight: 700;
            padding: 2px 7px;
            border-radius: 4px;
            white-space: nowrap;
        }

        .barra-bloqueo-edicion {
            margin-bottom: 15px;
            padding: 10px 14px;
            background-color: rgba(245, 158, 11, 0.14);
            border: 1px solid rgba(245, 158, 11, 0.35);
            border-left: 4px solid #f59e0b;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
        }

        .btn-volver-buscar {
            background-color: #2563eb;
            color: #ffffff;
            border: none;
            padding: 5px 12px;
            border-radius: 6px;
            font-size: 0.78rem;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: background-color 0.2s;
        }
        .btn-volver-buscar:hover { background-color: #1d4ed8; }

        .contenedor-campos-bloqueables { position: relative; transition: opacity 0.2s ease; }
        .contenedor-campos-bloqueables.bloqueado-lectura { opacity: 0.72; }
        .capa-transparente-bloqueo { display: none; position: absolute; inset: 0; background: rgba(255, 255, 255, 0.08); z-index: 10; cursor: not-allowed; border-radius: 8px; }
        .contenedor-campos-bloqueables.bloqueado-lectura .capa-transparente-bloqueo { display: block; }

        @media (max-width: 768px) {
            html, body { flex-direction: column; }
            .main-content { padding: 12px; }
            .resultados-busqueda-lista { left: 20px; right: 20px; width: auto; }
        }
    </style>
</head>
<body>

    <?php include("menu.php"); ?>

    <main class="main-content">
        <div class="top-header-bar">
            <div class="top-breadcrumb">
                <i class="fas fa-user-plus" style="color: #2563eb;"></i>
                <span>Registrar Alumno</span>
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

        <div class="card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; flex-wrap: wrap; gap: 8px;">
                <h3 style="margin: 0;"><i class="fas fa-user-plus text-primary" style="color: #2563eb;"></i> Paso 1: Datos del Estudiante</h3>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <button type="button" id="btn_volver_buscar_top" class="btn-volver-buscar" style="display: none;" onclick="volverABuscarOtro()">
                        <i class="fas fa-search"></i> Buscar otro estudiante
                    </button>
                    <a href="limpiar_sesion.php?redirect=agregar_alumno.php" onclick="return confirm('¿Estás seguro de limpiar todos los datos del formulario?');" style="background-color: #ef4444; color: white; padding: 5px 12px; border-radius: 6px; text-decoration: none; font-size: 0.82rem; font-weight: 600;">
                        <i class="fas fa-trash-alt"></i> Limpiar Formulario
                    </a>
                </div>
            </div>
            <hr style="margin-bottom: 15px; border: 0; border-top: 1px solid var(--border-color);">

            <?php echo $mensaje_error; ?>
            <div id="alerta_cedula_ajax" style="display: none; background-color: #f8d7da; color: #721c24; padding: 12px; border-radius: 6px; margin-bottom: 15px; font-size: 0.88rem; font-weight: 600; border: 1px solid #f5c6cb;">
                <i class='fas fa-exclamation-triangle'></i> ¡Atención! El número de cédula (<span id="cedula_repetida_txt"></span>) ya se encuentra registrado en el sistema.
            </div>
            <div id="alerta_busqueda_regular" style="display: none; background-color: #f8d7da; color: #721c24; padding: 11px 14px; border-radius: 6px; margin-bottom: 15px; font-size: 0.86rem; font-weight: 600; border: 1px solid #f5c6cb;">
                <i class="fas fa-exclamation-circle"></i> No se encontró ningún estudiante registrado con ese criterio de búsqueda.
            </div>
            <div id="alerta_sin_docente" style="display: none; background-color: #fff3cd; color: #856404; padding: 12px 15px; border-radius: 8px; margin-bottom: 15px; font-size: 0.88rem; font-weight: 600; border: 1px solid #ffeeba; border-left: 4px solid #f59e0b; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
                <div>
                    <i class="fas fa-chalkboard-teacher" style="margin-right: 6px;"></i>
                    <span id="texto_alerta_sin_docente">Aún no hay un docente asignado para este grado y sección. Por favor, registre y asigne al profesor correspondiente en el módulo de Gestión de Docentes.</span>
                </div>
                <a href="docentes.php" style="background-color: #f59e0b; color: #ffffff; padding: 5px 12px; border-radius: 6px; text-decoration: none; font-size: 0.8rem; font-weight: 600; white-space: nowrap;">
                    <i class="fas fa-user-plus"></i> Ir a Docentes
                </a>
            </div>

            <form action="" method="POST" id="form_alumno">
                <input type="hidden" name="es_regular_cargado" id="es_regular_cargado" value="<?php echo $en_modo_edicion ? '1' : '0'; ?>">

                <div class="form-grid" id="contenedor_selector_tipo" style="margin-bottom: 15px;">
                    <div class="form-group" style="max-width: 280px;">
                        <label>Tipo de Ingreso (*):</label>
                        <select name="tipo_ingreso" id="tipo_ingreso" onchange="cambiarTipoIngreso()" required>
                            <option value="">Elige una opción</option>
                            <option value="Estudiante regular" <?php echo ($tipo_ingreso_actual === 'Estudiante regular') ? 'selected' : ''; ?>>Estudiante regular</option>
                            <option value="Nuevo ingreso" <?php echo ($tipo_ingreso_actual === 'Nuevo ingreso') ? 'selected' : ''; ?>>Nuevo ingreso</option>
                        </select>
                    </div>
                </div>

                <div id="seccion_buscador_regular" class="search-panel-box" style="display: none;">
                    <div class="search-panel-title">
                        <i class="fas fa-search" style="color: #2563eb;"></i> Seleccionar Estudiante para Consulta
                    </div>
                    <div class="sub-header-label">Búsqueda por datos personales:</div>
                    <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap; position: relative;">
                        <select id="tipoBusqueda" class="form-control" onchange="actualizarReglaValidacion(true)" style="padding: 8px; border-radius: 6px; border: 1px solid var(--border-color); width: 220px; font-size: 0.9rem; height: 38px;">
                            <option value="cedula">Cédula Escolar / Identidad</option>
                            <option value="nombre">Nombre o Apellido</option>
                        </select>
                        
                        <input type="text" id="inputValorBusqueda" class="form-control" placeholder="Ingrese solo números (máx 15)..." maxlength="15" onkeypress="if(event.key === 'Enter'){ event.preventDefault(); ejecutarBusquedaRegular(); }" oninput="sugerirAlumnosRegular(this.value);" autocomplete="off" style="padding: 8px 12px; width: 280px; border-radius: 6px; border: 1px solid var(--border-color); font-size: 0.9rem; height: 38px;">
                        
                        <button type="button" onclick="ejecutarBusquedaRegular()" style="padding: 8px 16px; background: #2563eb; color: #fff; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 0.9rem; height: 38px;">
                            <i class="fas fa-search"></i> Buscar
                        </button>
                        <div id="lista_resultados_regular" class="resultados-busqueda-lista"></div>
                    </div>
                </div>

                <div id="contenedor_formulario_alumno" style="display: none;">

                    <div id="barra_bloqueo_regular" class="barra-bloqueo-edicion" style="display: none;">
                        <div style="font-size: 0.84rem; font-weight: 600; color: var(--text-primary);">
                            <i class="fas fa-lock" id="icono_candado_regular" style="color: #d97706; margin-right: 5px;"></i>
                            <span id="texto_estado_bloqueo">Datos cargados en modo protegido. Si desea modificar algún dato, quite el tilde de bloqueo:</span>
                        </div>
                        <div style="display: flex; align-items: center; gap: 14px; flex-wrap: wrap;">
                            <label style="display: flex; align-items: center; gap: 7px; font-size: 0.84rem; font-weight: 700; cursor: pointer; margin: 0; user-select: none;">
                                <input type="checkbox" id="chk_bloquear_campos" checked onchange="alternarBloqueoRegular()" style="width: 16px; height: 16px; cursor: pointer; accent-color: #2563eb;">
                                <span>Bloquear datos (Quitar tilde para modificar)</span>
                            </label>
                        </div>
                    </div>

                    <div id="zona_campos_bloqueables" class="contenedor-campos-bloqueables">
                        <div class="capa-transparente-bloqueo" title="Quite el tilde arriba para poder modificar los datos"></div>

                        <div class="form-grid">
                            <div class="form-group">
                                <label>Nombres (*):</label>
                                <input type="text" name="nombres" id="nombres" maxlength="50" onkeypress="return soloLetrasYEspacios(event)" oninput="limpiarLetras(this)" pattern="[A-Za-zÁÉÍÓÚáéíóúñÑ\s]+" title="Solo letras y espacios" value="<?php echo htmlspecialchars($_SESSION['estudiante']['nombres'] ?? ''); ?>" required>
                            </div>

                            <div class="form-group">
                                <label>Apellidos (*):</label>
                                <input type="text" name="apellidos" id="apellidos" maxlength="50" onkeypress="return soloLetrasYEspacios(event)" oninput="limpiarLetras(this)" pattern="[A-Za-zÁÉÍÓÚáéíóúñÑ\s]+" title="Solo letras y espacios" value="<?php echo htmlspecialchars($_SESSION['estudiante']['apellidos'] ?? ''); ?>" required>
                            </div>

                            <div class="form-group">
                                <label>Cédula Escolar / Identidad (*):</label>
                                <div class="cedula-group">
                                    <select name="nacionalidad_cedula" id="nacionalidad_cedula">
                                        <option value="V" <?php echo (($_SESSION['estudiante']['nacionalidad_cedula'] ?? 'V') == 'V') ? 'selected' : ''; ?>>V-</option>
                                        <option value="E" <?php echo (($_SESSION['estudiante']['nacionalidad_cedula'] ?? '') == 'E') ? 'selected' : ''; ?>>E-</option>
                                    </select>
                                    <input type="text" name="numero_cedula_alumno" id="numero_cedula_alumno" placeholder="123456789" maxlength="15" onkeypress="return soloNumeros(event)" oninput="limpiarNumeros(this); verificarCedulaDuplicada()" pattern="[0-9]{1,15}" title="Ingrese solo números (máximo 15 dígitos)" value="<?php echo htmlspecialchars($_SESSION['estudiante']['numero_cedula_alumno'] ?? ''); ?>" required>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Fecha de Nacimiento (*):</label>
                                <input type="date" name="fecha_nacimiento" id="fecha_nacimiento" min="1990-01-01" max="<?php echo date('Y-m-d'); ?>" value="<?php echo htmlspecialchars($_SESSION['estudiante']['fecha_nacimiento'] ?? ''); ?>" required>
                            </div>

                            <div class="form-group">
                                <label>Género (*):</label>
                                <select name="genero" id="genero" required>
                                    <option value="">Elige una opción</option>
                                    <option value="MASCULINO" <?php echo (($_SESSION['estudiante']['genero'] ?? '') == 'MASCULINO') ? 'selected' : ''; ?>>MASCULINO</option>
                                    <option value="FEMENINO" <?php echo (($_SESSION['estudiante']['genero'] ?? '') == 'FEMENINO') ? 'selected' : ''; ?>>FEMENINO</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label>Nacionalidad (*):</label>
                                <input type="text" name="nacionalidad" id="nacionalidad" maxlength="30" onkeypress="return soloLetrasYEspacios(event)" oninput="limpiarLetras(this)" pattern="[A-Za-zÁÉÍÓÚáéíóúñÑ\s]+" title="Solo letras" value="<?php echo htmlspecialchars($_SESSION['estudiante']['nacionalidad'] ?? 'Venezolana'); ?>" required>
                            </div>

                            <div class="form-group" style="grid-column: span 2;">
                                <label>Dirección de Domicilio:</label>
                                <textarea name="direccion_domicilio" id="direccion_domicilio" maxlength="150"><?php echo htmlspecialchars($_SESSION['estudiante']['direccion_domicilio'] ?? ''); ?></textarea>
                            </div>

                            <div class="form-group">
                                <label>Grado a Cursar (1° a 6°) (*):</label>
                                <select name="grado" id="grado" onchange="consultarDocenteAsignado()" required>
                                    <option value="">Seleccione Grado...</option>
                                    <option value="1" <?php echo (($_SESSION['estudiante']['grado'] ?? '') == '1') ? 'selected' : ''; ?>>1° Grado</option>
                                    <option value="2" <?php echo (($_SESSION['estudiante']['grado'] ?? '') == '2') ? 'selected' : ''; ?>>2° Grado</option>
                                    <option value="3" <?php echo (($_SESSION['estudiante']['grado'] ?? '') == '3') ? 'selected' : ''; ?>>3° Grado</option>
                                    <option value="4" <?php echo (($_SESSION['estudiante']['grado'] ?? '') == '4') ? 'selected' : ''; ?>>4° Grado</option>
                                    <option value="5" <?php echo (($_SESSION['estudiante']['grado'] ?? '') == '5') ? 'selected' : ''; ?>>5° Grado</option>
                                    <option value="6" <?php echo (($_SESSION['estudiante']['grado'] ?? '') == '6') ? 'selected' : ''; ?>>6° Grado</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label>Sección (*):</label>
                                <select name="seccion" id="seccion" onchange="consultarDocenteAsignado()" required>
                                    <option value="">Elige una opción</option>
                                    <option value="A" <?php echo (($_SESSION['estudiante']['seccion'] ?? '') == 'A') ? 'selected' : ''; ?>>A</option>
                                    <option value="B" <?php echo (($_SESSION['estudiante']['seccion'] ?? '') == 'B') ? 'selected' : ''; ?>>B</option>
                                    <option value="C" <?php echo (($_SESSION['estudiante']['seccion'] ?? '') == 'C') ? 'selected' : ''; ?>>C</option>
                                    <option value="D" <?php echo (($_SESSION['estudiante']['seccion'] ?? '') == 'D') ? 'selected' : ''; ?>>D</option>
                                    <option value="U" <?php echo (($_SESSION['estudiante']['seccion'] ?? '') == 'U') ? 'selected' : ''; ?>>U</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label>Docente:</label>
                                <input type="text" name="docente" id="docente" placeholder="Se asigna según grado y sección" value="<?php echo htmlspecialchars($docente_inicial_sesion); ?>" readonly style="background-color: var(--bg-surface-alt); cursor: not-allowed; font-weight: 600;">
                            </div>

                            <div class="form-group">
                                <label>Periodo Escolar (*):</label>
                                <input type="text" name="periodo_escolar_mostrar" id="periodo_escolar" value="<?php echo htmlspecialchars($periodo_activo_db); ?>" readonly style="background-color: var(--bg-surface-alt); cursor: not-allowed; font-weight: 600;">
                            </div>

                            <div class="form-group" id="grupo_plantel_procedencia" style="grid-column: span 2; display: none;">
                                <label>Plantel de Procedencia:</label>
                                <input type="text" name="plantel_procedencia" id="plantel_procedencia" maxlength="100" placeholder="Nombre del plantel de donde proviene el estudiante" value="<?php echo htmlspecialchars($_SESSION['estudiante']['plantel_procedencia'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>

                    <div class="btn-container">
                        <button type="submit" name="guardar_estudiante" id="btn_siguiente" class="btn-next">Siguiente <i class="fas fa-arrow-right"></i></button>
                    </div>
                </div>
            </form>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let regularCargado = <?php echo ($tiene_datos_cargados && $tipo_ingreso_actual === 'Estudiante regular') ? 'true' : 'false'; ?>;
        const periodoInstitucionalActivo = <?php echo json_encode($periodo_activo_db); ?>;

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
                input.value = input.value.replace(/[^0-9]/g, '').slice(0, 15);
            } else {
                input.placeholder = "Ingrese nombres o apellidos...";
                input.setAttribute('maxlength', '50');
                input.value = input.value.replace(/[^A-Za-zÁÉÍÓÚáéíóúñÑ\s]/g, '').slice(0, 50);
            }
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

        function limpiarLetras(campo) {
            campo.value = campo.value.replace(/[^a-zA-ZáéíóúÁÉÍÓÚñÑ\s]/g, '');
        }

        function limpiarNumeros(campo) {
            campo.value = campo.value.replace(/[^0-9]/g, '');
        }

        function consultarDocenteAsignado() {
            const grado = document.getElementById('grado').value.trim();
            const seccion = document.getElementById('seccion').value.trim();
            const inputDocente = document.getElementById('docente');
            const alertaSinDocente = document.getElementById('alerta_sin_docente');
            const textoAlerta = document.getElementById('texto_alerta_sin_docente');

            if (grado === '' || seccion === '') {
                inputDocente.value = '';
                inputDocente.placeholder = 'Se asigna según grado y sección';
                alertaSinDocente.style.display = 'none';
                return;
            }

            fetch('agregar_alumno.php?buscar_docente_ajax=1&grado=' + encodeURIComponent(grado) + '&seccion=' + encodeURIComponent(seccion))
                .then(r => r.json())
                .then(data => {
                    if (data && data.encontrado && data.docente) {
                        inputDocente.value = data.docente;
                        inputDocente.placeholder = 'Se asigna según grado y sección';
                        alertaSinDocente.style.display = 'none';
                    } else {
                        const secTexto = (seccion === 'U') ? 'Única' : seccion;
                        inputDocente.value = '';
                        inputDocente.placeholder = 'Sin docente asignado';
                        textoAlerta.textContent = `Aún no hay un docente asignado para ${grado}° Grado Sección "${secTexto}". Por favor, registre y asigne al profesor correspondiente en el módulo de Gestión de Docentes.`;
                        alertaSinDocente.style.display = 'flex';
                    }
                })
                .catch(err => console.error(err));
        }

        function cambiarTipoIngreso(esCargaInicial = false) {
            const tipo = document.getElementById('tipo_ingreso').value;
            const contenedorSelector = document.getElementById('contenedor_selector_tipo');
            const buscadorBox = document.getElementById('seccion_buscador_regular');
            const contenedorForm = document.getElementById('contenedor_formulario_alumno');
            const grupoPlantel = document.getElementById('grupo_plantel_procedencia');
            const barraBloqueo = document.getElementById('barra_bloqueo_regular');
            const btnVolverTop = document.getElementById('btn_volver_buscar_top');
            const chkBloqueo = document.getElementById('chk_bloquear_campos');
            const alertaAjax = document.getElementById('alerta_cedula_ajax');
            const alertaBusqueda = document.getElementById('alerta_busqueda_regular');
            const alertaSinDocente = document.getElementById('alerta_sin_docente');

            alertaBusqueda.style.display = 'none';

            if (tipo === '') {
                contenedorSelector.style.display = 'grid';
                buscadorBox.style.display = 'none';
                contenedorForm.style.display = 'none';
                grupoPlantel.style.display = 'none';
                barraBloqueo.style.display = 'none';
                btnVolverTop.style.display = 'none';
                alertaSinDocente.style.display = 'none';
            } else if (tipo === 'Nuevo ingreso') {
                contenedorSelector.style.display = 'grid';
                buscadorBox.style.display = 'none';
                contenedorForm.style.display = 'block';
                grupoPlantel.style.display = 'flex';
                barraBloqueo.style.display = 'none';
                btnVolverTop.style.display = 'none';
                chkBloqueo.checked = false;
                alternarBloqueoRegular();

                if (!esCargaInicial && regularCargado) {
                    regularCargado = false;
                    document.getElementById('es_regular_cargado').value = '0';
                    limpiarCamposDOM();
                    fetch('agregar_alumno.php?modo_nuevo_ajax=1');
                }
            } else if (tipo === 'Estudiante regular') {
                grupoPlantel.style.display = 'none';
                document.getElementById('plantel_procedencia').value = '';
                alertaAjax.style.display = 'none';

                if (regularCargado) {
                    contenedorSelector.style.display = 'none';
                    buscadorBox.style.display = 'none';
                    contenedorForm.style.display = 'block';
                    barraBloqueo.style.display = 'flex';
                    btnVolverTop.style.display = 'inline-flex';
                    chkBloqueo.checked = true;
                    alternarBloqueoRegular();
                } else {
                    contenedorSelector.style.display = 'grid';
                    buscadorBox.style.display = 'block';
                    contenedorForm.style.display = 'none';
                    barraBloqueo.style.display = 'none';
                    btnVolverTop.style.display = 'none';
                    alertaSinDocente.style.display = 'none';
                }
            }
        }

        function volverABuscarOtro() {
            regularCargado = false;
            document.getElementById('es_regular_cargado').value = '0';
            limpiarCamposDOM();
            document.getElementById('inputValorBusqueda').value = '';
            document.getElementById('lista_resultados_regular').style.display = 'none';
            fetch('agregar_alumno.php?modo_nuevo_ajax=1');

            document.getElementById('tipo_ingreso').value = 'Estudiante regular';
            cambiarTipoIngreso();
            document.getElementById('inputValorBusqueda').focus();
        }

        function limpiarCamposDOM() {
            document.getElementById('nombres').value = '';
            document.getElementById('apellidos').value = '';
            document.getElementById('nacionalidad_cedula').value = 'V';
            document.getElementById('numero_cedula_alumno').value = '';
            document.getElementById('fecha_nacimiento').value = '';
            document.getElementById('genero').value = '';
            document.getElementById('nacionalidad').value = 'Venezolana';
            document.getElementById('direccion_domicilio').value = '';
            document.getElementById('grado').value = '';
            document.getElementById('seccion').value = '';
            document.getElementById('docente').value = '';
            document.getElementById('docente').placeholder = 'Se asigna según grado y sección';
            document.getElementById('alerta_sin_docente').style.display = 'none';
            document.getElementById('plantel_procedencia').value = '';
            document.getElementById('periodo_escolar').value = periodoInstitucionalActivo;
        }

        function alternarBloqueoRegular() {
            const chk = document.getElementById('chk_bloquear_campos');
            const zona = document.getElementById('zona_campos_bloqueables');
            const icono = document.getElementById('icono_candado_regular');
            const texto = document.getElementById('texto_estado_bloqueo');

            if (chk && chk.checked && document.getElementById('tipo_ingreso').value === 'Estudiante regular') {
                zona.classList.add('bloqueado-lectura');
                if (icono) {
                    icono.className = 'fas fa-lock';
                    icono.style.color = '#d97706';
                }
                if (texto) {
                    texto.textContent = 'Datos cargados en modo protegido. Si desea modificar algún dato, quite el tilde de bloqueo:';
                }
            } else {
                zona.classList.remove('bloqueado-lectura');
                if (icono) {
                    icono.className = 'fas fa-lock-open';
                    icono.style.color = '#10b981';
                }
                if (texto) {
                    texto.textContent = 'Modo edición habilitado. Ya puede modificar los datos necesarios:';
                }
            }
        }

        function sugerirAlumnosRegular(termino) {
            const lista = document.getElementById('lista_resultados_regular');
            const tipo = document.getElementById('tipoBusqueda').value;
            const q = termino.trim();
            if (q.length < 1) {
                lista.style.display = 'none';
                lista.innerHTML = '';
                return;
            }

            if (tipo === 'cedula') {
                termino = termino.replace(/[^0-9]/g, '');
                document.getElementById('inputValorBusqueda').value = termino;
            }

            fetch('agregar_alumno.php?buscar_regular_ajax=1&tipo=' + tipo + '&termino=' + encodeURIComponent(termino))
                .then(r => r.json())
                .then(data => {
                    lista.innerHTML = '';
                    if (data && data.length > 0) {
                        data.forEach(item => {
                            const div = document.createElement('div');
                            div.className = 'item-resultado-alumno';
                            const gradoTxt = item.grado ? `${item.grado}° - Sec. ${item.seccion || 'U'}` : 'S/G';
                            div.innerHTML = `
                                <div><i class="fas fa-user-graduate" style="color: #2563eb; margin-right: 4px;"></i> <strong>${item.cédula_escolar}</strong> — ${item.nombres} ${item.apellidos}</div>
                                <span class="badge-grado-sec">${gradoTxt}</span>
                            `;
                            div.onclick = function() {
                                document.getElementById('inputValorBusqueda').value = item.cédula_escolar;
                                lista.style.display = 'none';
                                ejecutarBusquedaRegular(item.cédula_escolar);
                            };
                            lista.appendChild(div);
                        });
                        lista.style.display = 'block';
                    } else {
                        lista.style.display = 'none';
                    }
                })
                .catch(err => console.error(err));
        }

        function ejecutarBusquedaRegular(cedulaDirecta = '') {
            const cedula = cedulaDirecta !== '' ? cedulaDirecta : document.getElementById('inputValorBusqueda').value.trim();
            const alertaBusqueda = document.getElementById('alerta_busqueda_regular');
            const lista = document.getElementById('lista_resultados_regular');
            alertaBusqueda.style.display = 'none';
            lista.style.display = 'none';

            if (cedula === '') {
                document.getElementById('inputValorBusqueda').focus();
                return;
            }

            fetch('agregar_alumno.php?cargar_regular_ajax=1&cedula=' + encodeURIComponent(cedula))
                .then(r => r.json())
                .then(res => {
                    if (res && res.exito && res.estudiante) {
                        const est = res.estudiante;
                        document.getElementById('nombres').value = est.nombres || '';
                        document.getElementById('apellidos').value = est.apellidos || '';
                        document.getElementById('nacionalidad_cedula').value = est.nacionalidad_cedula || 'V';
                        document.getElementById('numero_cedula_alumno').value = est.numero_cedula_alumno || '';
                        document.getElementById('fecha_nacimiento').value = est.fecha_nacimiento || '';
                        document.getElementById('genero').value = est.genero || '';
                        document.getElementById('nacionalidad').value = est.nacionalidad || 'Venezolana';
                        document.getElementById('direccion_domicilio').value = est.direccion_domicilio || '';
                        document.getElementById('grado').value = est.grado || '';
                        document.getElementById('seccion').value = est.seccion || '';
                        document.getElementById('docente').value = est.docente || '';
                        document.getElementById('periodo_escolar').value = periodoInstitucionalActivo;
                        document.getElementById('plantel_procedencia').value = est.plantel_procedencia || '';

                        regularCargado = true;
                        document.getElementById('es_regular_cargado').value = '1';

                        document.getElementById('contenedor_selector_tipo').style.display = 'none';
                        document.getElementById('seccion_buscador_regular').style.display = 'none';
                        document.getElementById('contenedor_formulario_alumno').style.display = 'block';
                        document.getElementById('barra_bloqueo_regular').style.display = 'flex';
                        document.getElementById('btn_volver_buscar_top').style.display = 'inline-flex';
                        document.getElementById('chk_bloquear_campos').checked = true;
                        alternarBloqueoRegular();

                        document.getElementById('alerta_cedula_ajax').style.display = 'none';
                        document.getElementById('btn_siguiente').disabled = false;
                        document.getElementById('btn_siguiente').style.opacity = '1';
                        consultarDocenteAsignado();
                    } else {
                        alertaBusqueda.style.display = 'block';
                    }
                })
                .catch(err => console.error(err));
        }

        function verificarCedulaDuplicada() {
            var num = document.getElementById('numero_cedula_alumno').value.trim();
            var alerta = document.getElementById('alerta_cedula_ajax');
            var txtCedula = document.getElementById('cedula_repetida_txt');
            var btnSiguiente = document.getElementById('btn_siguiente');

            if (num.length > 0) {
                fetch('agregar_alumno.php?verificar_ajax=1&num=' + encodeURIComponent(num))
                    .then(response => response.json())
                    .then(data => {
                        if (data.existe) {
                            txtCedula.textContent = data.cedula;
                            alerta.style.display = 'block';
                            btnSiguiente.disabled = true;
                            btnSiguiente.style.opacity = '0.5';
                        } else {
                            alerta.style.display = 'none';
                            btnSiguiente.disabled = false;
                            btnSiguiente.style.opacity = '1';
                        }
                    })
                    .catch(error => console.error('Error en verificación:', error));
            } else {
                alerta.style.display = 'none';
                btnSiguiente.disabled = false;
                btnSiguiente.style.opacity = '1';
            }
        }

        document.addEventListener('click', function(e) {
            const box = document.getElementById('seccion_buscador_regular');
            const lista = document.getElementById('lista_resultados_regular');
            if (box && lista && !box.contains(e.target)) {
                lista.style.display = 'none';
            }
        });

        window.addEventListener('DOMContentLoaded', function() {
            actualizarReglaValidacion(false);
            cambiarTipoIngreso(true);
            if (document.getElementById('numero_cedula_alumno').value.trim() !== '') {
                verificarCedulaDuplicada();
            }
            if (document.getElementById('grado').value !== '' && document.getElementById('seccion').value !== '') {
                consultarDocenteAsignado();
            }
        });
    </script>
</body>
</html>