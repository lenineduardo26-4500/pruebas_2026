<?php
session_start();
if (isset($_SESSION['usuario'])) {
    if (!isset($_SESSION['init'])) {
        session_regenerate_id(true);
        $_SESSION['init'] = true;
    }
}
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (isset($_POST['guardar_padre'])) {
    $_SESSION['padre'] = $_POST;
}

include("conexion.php");
if ($conexion instanceof mysqli) {
    mysqli_set_charset($conexion, "utf8mb4");
}

$periodo_activo = "";
if ($conexion instanceof PDO) {
    try {
        $stmt_p = $conexion->query("SELECT valor FROM configuracion WHERE clave = 'periodo_escolar' LIMIT 1");
        $row_p = $stmt_p->fetch(PDO::FETCH_ASSOC);
        if ($row_p && !empty($row_p['valor'])) {
            $periodo_activo = $row_p['valor'];
        }
    } catch (Exception $e) {}
} else if ($conexion instanceof mysqli) {
    $res_p = @mysqli_query($conexion, "SELECT valor FROM configuracion WHERE clave = 'periodo_escolar' LIMIT 1");
    if ($res_p && $row_p = mysqli_fetch_assoc($res_p)) {
        if (!empty($row_p['valor'])) {
            $periodo_activo = $row_p['valor'];
        }
    }
}

$es_administrador = isset($_SESSION['rol']) && $_SESSION['rol'] === 'Administrador';
$rol_actual = $_SESSION['rol'] ?? 'Administrador';
$mensaje_resultado = "";

function valorCampo($clave) {
    if (isset($_SESSION['adicional'][$clave]) && $_SESSION['adicional'][$clave] !== '') {
        return htmlspecialchars($_SESSION['adicional'][$clave], ENT_QUOTES, 'UTF-8');
    }
    if (isset($_SESSION['estudiante'][$clave]) && $_SESSION['estudiante'][$clave] !== '') {
        return htmlspecialchars($_SESSION['estudiante'][$clave], ENT_QUOTES, 'UTF-8');
    }
    return '';
}

function traducirErrorBD($mensaje_original, $cedula) {
    if (stripos($mensaje_original, 'Duplicate entry') !== false || stripos($mensaje_original, '1062') !== false || stripos($mensaje_original, '23000') !== false) {
        return "Error al guardar el estudiante con la cédula <strong>" . htmlspecialchars($cedula) . "</strong> por duplicación: Ya existe un alumno registrado con ese número de cédula en el sistema.";
    }
    return "Error al guardar en la base de datos: Por favor verifique los datos ingresados (" . htmlspecialchars($mensaje_original) . ").";
}

if (!function_exists('registrarHistorialSeguridad')) {
    function registrarHistorialSeguridad($conexion, $descripcion, $usuario) {
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
}

if (!function_exists('ejecutarSQL')) {
    function ejecutarSQL($mysqli, $sql) {
        if (!mysqli_query($mysqli, $sql)) {
            throw new Exception(mysqli_error($mysqli));
        }
    }
}

if (isset($_POST['finalizar_registro'])) {
    $_SESSION['adicional'] = $_POST;
    $_SESSION['estudiante'] = array_merge($_SESSION['estudiante'] ?? [], $_POST);
    
    $est = $_SESSION['estudiante'] ?? [];
    $rep = $_SESSION['representante'] ?? [];
    $mad = $_SESSION['madre'] ?? [];
    $pad = $_SESSION['padre'] ?? [];
    $adi = $_SESSION['adicional'] ?? [];

    $es_edicion = !empty($_SESSION['modo_edicion']) && !empty($_SESSION['cedula_original']);
    $cedula_original = $_SESSION['cedula_original'] ?? '';

    $cedula_escolar = trim($est['cedula_alumno'] ?? '');
    if (empty($cedula_escolar) && !empty($est['numero_cedula_alumno'])) {
        $cedula_escolar = ($est['nacionalidad_cedula'] ?? 'V') . '-' . trim($est['numero_cedula_alumno']);
    }
    $cedula_escolar = substr($cedula_escolar, 0, 15);
    $usuario_actual = $_SESSION['usuario'] ?? 'Administrador';

    if (empty($cedula_escolar)) {
        $mensaje_resultado = "<div style='background: #f8d7da; color: #721c24; padding: 12px; border-radius: 6px; margin-bottom: 15px; font-weight: 600;'>Error: No se encontraron los datos del Paso 1 (Estudiante). Por favor, inicie el registro desde el Paso 1.</div>";
    } else {
        $psep_est = (($est['padres_separados'] ?? 'No') === 'Sí') ? 1 : 0;
        $rep_est  = (($est['repitio_grado'] ?? 'No') === 'Sí') ? 1 : 0;
        $nesp_est = (($est['necesidades_especiales'] ?? 'No') === 'Sí') ? 1 : 0;
        $inf_est  = (($est['informe_especialista'] ?? 'No') === 'Sí') ? 1 : 0;
        $alim_est = (($est['informe_alimentacion'] ?? 'No') === 'Sí') ? 1 : 0;
        $aula_est = (($est['aula_integrada'] ?? 'No') === 'Sí') ? 1 : 0;
        $aut_rep  = (($rep['autorizacion_legal'] ?? 'No') === 'Sí') ? 1 : 0;

        $peso_val  = (isset($est['peso']) && $est['peso'] !== '') ? floatval(str_replace(',', '.', $est['peso'])) : null;
        $talla_val = (isset($est['talla']) && $est['talla'] !== '') ? floatval(str_replace(',', '.', $est['talla'])) : null;

        $grado_val = trim($est['grado'] ?? '1');
        $seccion_val = trim($est['seccion'] ?? 'A');
        $seccion_busqueda = ($seccion_val === 'U') ? 'Única' : $seccion_val;

        $tablas_hijas = [
            'representante', 'madre', 'padre', 'vivienda', 'inscripcion',
            'hermano', 'traslado_estudiante', 'antecedente_medico',
            'vacuna', 'medicamento_contraindicado', 'problema_comunidad'
        ];

        if ($conexion instanceof PDO) {
            try {
                $conexion->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $conexion->beginTransaction();

                if ($es_edicion) {
                    $sql_est = "UPDATE estudiante SET
                        cédula_escolar = :cedula, nombres = :nombres, apellidos = :apellidos, fecha_nacimiento = :fnac,
                        género = :genero, nacionalidad = :nac, peso = :peso, talla = :talla, talla_camisa = :tcamisa,
                        talla_pantalón = :tpantalon, calzado = :calzado, entidad_federal = :entidad,
                        lugar_nacimiento = :lnac, municipio_nacimiento = :mnac, parroquia_nacimiento = :pnac,
                        dirección_domicilio = :dir, religión = :rel, con_quien_vive = :vive, padres_separados = :psep,
                        ha_repetido_grado = :rep, grados_repetidos = :greep, necesidades_especiales = :nesp,
                        descripción_condición = :desc, informe_especialista = :inf, informe_alimentación = :alim,
                        atendido_aula_integrada = :aula
                        WHERE cédula_escolar = :cedula_orig";
                    $stmt = $conexion->prepare($sql_est);
                    $stmt->execute([
                        ':cedula'      => $cedula_escolar,
                        ':nombres'     => $est['nombres'] ?? '',
                        ':apellidos'   => $est['apellidos'] ?? '',
                        ':fnac'        => !empty($est['fecha_nacimiento']) ? $est['fecha_nacimiento'] : null,
                        ':genero'      => $est['genero'] ?? '',
                        ':nac'         => $est['nacionalidad'] ?? 'Venezolana',
                        ':peso'        => $peso_val,
                        ':talla'       => $talla_val,
                        ':tcamisa'     => $est['talla_camisa'] ?? '',
                        ':tpantalon'   => $est['talla_pantalon'] ?? '',
                        ':calzado'     => $est['calzado'] ?? '',
                        ':entidad'     => $est['entidad_federal'] ?? '',
                        ':lnac'        => $est['lugar_nacimiento'] ?? '',
                        ':mnac'        => $est['municipio_nacimiento'] ?? '',
                        ':pnac'        => $est['parroquia_nacimiento'] ?? '',
                        ':dir'         => $est['direccion_domicilio'] ?? '',
                        ':rel'         => $est['religion'] ?? '',
                        ':vive'        => $est['vive_con'] ?? '',
                        ':psep'        => $psep_est,
                        ':rep'         => $rep_est,
                        ':greep'       => $est['especifique_grados'] ?? '',
                        ':nesp'        => $nesp_est,
                        ':desc'        => $est['descripcion_necesidades'] ?? '',
                        ':inf'         => $inf_est,
                        ':alim'        => $alim_est,
                        ':aula'        => $aula_est,
                        ':cedula_orig' => $cedula_original
                    ]);

                    foreach ($tablas_hijas as $tb) {
                        $st_del = $conexion->prepare("DELETE FROM $tb WHERE cédula_escolar_estudiante = :ced");
                        $st_del->execute([':ced' => $cedula_escolar]);
                    }
                } else {
                    $sql_est = "INSERT INTO estudiante (
                        cédula_escolar, nombres, apellidos, fecha_nacimiento, género, nacionalidad, 
                        peso, talla, talla_camisa, talla_pantalón, calzado, entidad_federal, 
                        lugar_nacimiento, municipio_nacimiento, parroquia_nacimiento, dirección_domicilio, 
                        religión, con_quien_vive, padres_separados, ha_repetido_grado, grados_repetidos, 
                        necesidades_especiales, descripción_condición, informe_especialista, informe_alimentación, atendido_aula_integrada
                    ) VALUES (
                        :cedula, :nombres, :apellidos, :fnac, :genero, :nac, 
                        :peso, :talla, :tcamisa, :tpantalon, :calzado, :entidad, 
                        :lnac, :mnac, :pnac, :dir, 
                        :rel, :vive, :psep, :rep, :greep, 
                        :nesp, :desc, :inf, :alim, :aula
                    )";
                    $stmt = $conexion->prepare($sql_est);
                    $stmt->execute([
                        ':cedula'    => $cedula_escolar,
                        ':nombres'   => $est['nombres'] ?? '',
                        ':apellidos' => $est['apellidos'] ?? '',
                        ':fnac'      => !empty($est['fecha_nacimiento']) ? $est['fecha_nacimiento'] : null,
                        ':genero'    => $est['genero'] ?? '',
                        ':nac'       => $est['nacionalidad'] ?? 'Venezolana',
                        ':peso'      => $peso_val,
                        ':talla'     => $talla_val,
                        ':tcamisa'   => $est['talla_camisa'] ?? '',
                        ':tpantalon' => $est['talla_pantalon'] ?? '',
                        ':calzado'   => $est['calzado'] ?? '',
                        ':entidad'   => $est['entidad_federal'] ?? '',
                        ':lnac'      => $est['lugar_nacimiento'] ?? '',
                        ':mnac'      => $est['municipio_nacimiento'] ?? '',
                        ':pnac'      => $est['parroquia_nacimiento'] ?? '',
                        ':dir'       => $est['direccion_domicilio'] ?? '',
                        ':rel'       => $est['religion'] ?? '',
                        ':vive'      => $est['vive_con'] ?? '',
                        ':psep'      => $psep_est,
                        ':rep'       => $rep_est,
                        ':greep'     => $est['especifique_grados'] ?? '',
                        ':nesp'      => $nesp_est,
                        ':desc'      => $est['descripcion_necesidades'] ?? '',
                        ':inf'       => $inf_est,
                        ':alim'      => $alim_est,
                        ':aula'      => $aula_est
                    ]);
                }

                $sql_rep = "INSERT INTO representante (
                    cédula_escolar_estudiante, nombres, apellidos, cédula_identidad, fecha_nacimiento, 
                    rif, correo_electrónico, teléfono_celular, teléfono_residencia, dirección_habitación, 
                    nivel_instrucción, profesion, ocupación, empresa, teléfono_empresa, dirección_empresa, 
                    parentesco, autorización_legal, número_poder_notariado, nacionalidad
                ) VALUES (
                    :cedula, :nombres, '', :ci, :fnac, 
                    :rif, :correo, :telf, '', :dir, 
                    :niv, :prof, :ocupa, :emp, :temp, :diremp, 
                    :parentesco, :aut, :poder, :nac
                )";
                $stmt = $conexion->prepare($sql_rep);
                $stmt->execute([
                    ':cedula'     => $cedula_escolar,
                    ':nombres'    => $rep['nombre_representante'] ?? '',
                    ':ci'         => $rep['cedula_identidad'] ?? '',
                    ':fnac'       => !empty($rep['fecha_nacimiento_representante']) ? $rep['fecha_nacimiento_representante'] : null,
                    ':rif'        => $rep['rif_representante'] ?? '',
                    ':correo'     => $rep['correo_electronico_representante'] ?? '',
                    ':telf'       => $rep['telefono_representante'] ?? '',
                    ':dir'        => $rep['direccion_habitacion_representante'] ?? '',
                    ':niv'        => $rep['nivel_instruccion_representante'] ?? '',
                    ':prof'       => $rep['profesion_representante'] ?? '',
                    ':ocupa'      => $rep['ocupacion_representante'] ?? '',
                    ':emp'        => $rep['empresa_representante'] ?? '',
                    ':temp'       => $rep['telefono_empresa_representante'] ?? '',
                    ':diremp'     => $rep['direccion_empresa_representante'] ?? '',
                    ':parentesco' => mb_substr($rep['parentesco_representante'] ?? '', 0, 30, 'UTF-8'),
                    ':aut'        => $aut_rep,
                    ':poder'      => $rep['numero_poder'] ?? '',
                    ':nac'        => $rep['nacionalidad_cedula_rep'] ?? 'V'
                ]);

                if (!empty($mad['nombres_apellidos_madre']) && ($mad['no_aplica_madre'] ?? '') !== '1') {
                    $sql_mad = "INSERT INTO madre (cédula_escolar_estudiante, nombres, apellidos, cédula_identidad, teléfono, fecha_nacimiento, estado_civil, lugar_nacimiento, correo, dirección_habitación, nivel_instrucción, profesion, ocupación, empresa, teléfono_empresa, dirección_empresa, nacionalidad)
                                VALUES (:cedula, :nombres, '', :ci, :telf, :fnac, :eciv, :lnac, :correo, :dir, :niv, :prof, :ocupa, :emp, :temprec, :diremp, :nac)";
                    $stmt = $conexion->prepare($sql_mad);
                    $stmt->execute([
                        ':cedula'  => $cedula_escolar,
                        ':nombres' => $mad['nombres_apellidos_madre'],
                        ':ci'      => $mad['ci_madre'] ?? '',
                        ':telf'    => $mad['telefono_madre'] ?? '',
                        ':fnac'    => !empty($mad['fecha_nacimiento_madre']) ? $mad['fecha_nacimiento_madre'] : null,
                        ':eciv'    => $mad['estado_civil_madre'] ?? '',
                        ':lnac'    => $mad['lugar_nacimiento_madre'] ?? '',
                        ':correo'  => $mad['correo_madre'] ?? '',
                        ':dir'     => $mad['direccion_habitacion_madre'] ?? '',
                        ':niv'     => $mad['nivel_instruccion_madre'] ?? '',
                        ':prof'    => $mad['profesion_madre'] ?? '',
                        ':ocupa'   => $mad['ocupacion_madre'] ?? '',
                        ':emp'     => $mad['empresa_madre'] ?? '',
                        ':temprec' => $mad['telefono_empresa_madre'] ?? '',
                        ':diremp'  => $mad['direccion_empresa_madre'] ?? '',
                        ':nac'     => $mad['nacionalidad_cedula_madre'] ?? 'V'
                    ]);
                }

                if (!empty($pad['nombres_apellidos_padre']) && ($pad['no_aplica_padre'] ?? '') !== '1') {
                    $sql_pad = "INSERT INTO padre (cédula_escolar_estudiante, nombres, apellidos, cédula_identidad, teléfono, fecha_nacimiento, estado_civil, lugar_nacimiento, correo, dirección_habitación, nivel_instrucción, profesion, ocupación, empresa, teléfono_empresa, dirección_empresa, nacionalidad)
                                VALUES (:cedula, :nombres, '', :ci, :telf, :fnac, :eciv, :lnac, :correo, :dir, :niv, :prof, :ocupa, :emp, :temprec, :diremp, :nac)";
                    $stmt = $conexion->prepare($sql_pad);
                    $stmt->execute([
                        ':cedula'  => $cedula_escolar,
                        ':nombres' => $pad['nombres_apellidos_padre'],
                        ':ci'      => $pad['ci_padre'] ?? '',
                        ':telf'    => $pad['telefono_padre'] ?? '',
                        ':fnac'    => !empty($pad['fecha_nacimiento_padre']) ? $pad['fecha_nacimiento_padre'] : null,
                        ':eciv'    => $pad['estado_civil_padre'] ?? '',
                        ':lnac'    => $pad['lugar_nacimiento_padre'] ?? '',
                        ':correo'  => $pad['correo_padre'] ?? '',
                        ':dir'     => $pad['direccion_habitacion_padre'] ?? '',
                        ':niv'     => $pad['nivel_instruccion_padre'] ?? '',
                        ':prof'    => $pad['profesion_padre'] ?? '',
                        ':ocupa'   => $pad['ocupacion_padre'] ?? '',
                        ':emp'     => $pad['empresa_padre'] ?? '',
                        ':temprec' => $pad['telefono_empresa_padre'] ?? '',
                        ':diremp'  => $pad['direccion_empresa_padre'] ?? '',
                        ':nac'     => $pad['nacionalidad_cedula_padre'] ?? 'V'
                    ]);
                }

                $stmt = $conexion->prepare("INSERT INTO vivienda (cédula_escolar_estudiante, condición_vivienda, tipo_vivienda) VALUES (:cedula, :cond, :tipo)");
                $stmt->execute([
                    ':cedula' => $cedula_escolar,
                    ':cond'   => $adi['condicion_vivienda'] ?? '',
                    ':tipo'   => $adi['tipo_vivienda'] ?? ''
                ]);

                $id_grado = 1; $id_seccion = 1;
                if (!empty($grado_val)) {
                    $st_g = $conexion->prepare("SELECT id FROM grados WHERE nombre_grado = :g LIMIT 1");
                    $st_g->execute([':g' => $grado_val]);
                    if ($rg = $st_g->fetch(PDO::FETCH_ASSOC)) {
                        $id_grado = $rg['id'];
                    } else {
                        $st_ins_g = $conexion->prepare("INSERT INTO grados (nombre_grado) VALUES (:g)");
                        $st_ins_g->execute([':g' => $grado_val]);
                        $id_grado = $conexion->lastInsertId();
                    }
                }

                if (!empty($seccion_busqueda)) {
                    $st_s = $conexion->prepare("SELECT id FROM secciones WHERE nombre_seccion = :s OR nombre_seccion = :s_raw LIMIT 1");
                    $st_s->execute([':s' => $seccion_busqueda, ':s_raw' => $seccion_val]);
                    if ($rs = $st_s->fetch(PDO::FETCH_ASSOC)) {
                        $id_seccion = $rs['id'];
                    } else {
                        $st_ins_s = $conexion->prepare("INSERT INTO secciones (nombre_seccion) VALUES (:s)");
                        $st_ins_s->execute([':s' => $seccion_busqueda]);
                        $id_seccion = $conexion->lastInsertId();
                    }
                }

                $stmt = $conexion->prepare("INSERT INTO inscripcion (cédula_escolar_estudiante, año_escolar, id_grado, id_seccion, tipo_estudiante, plantel_procedencia) VALUES (:cedula, :anio, :id_grado, :id_seccion, :tipo, :plantel)");
                $stmt->execute([
                    ':cedula'     => $cedula_escolar,
                    ':anio'       => $periodo_activo,
                    ':id_grado'   => $id_grado,
                    ':id_seccion' => $id_seccion,
                    ':tipo'       => $est['tipo_ingreso'] ?? '',
                    ':plantel'    => $est['plantel_procedencia'] ?? ''
                ]);

                if (!empty($adi['hermanos_escuela'])) {
                    $stmt = $conexion->prepare("INSERT INTO hermano (cédula_escolar_estudiante, nombres_apellidos, grado) VALUES (:cedula, :nom, '')");
                    $stmt->execute([
                        ':cedula' => $cedula_escolar,
                        ':nom'    => $adi['hermanos_escuela']
                    ]);
                }

                if (!empty($adi['viene_escuela']) || !empty($adi['parentesco_trae']) || !empty($adi['nombre_trae'])) {
                    $stmt = $conexion->prepare("INSERT INTO traslado_estudiante (cédula_escolar_estudiante, tipo, modalidad, parentesco_persona, nombres_apellidos_persona, cédula_persona) VALUES (:cedula, 'Entrada', :mod, :par, :nom, '')");
                    $stmt->execute([
                        ':cedula' => $cedula_escolar,
                        ':mod'    => $adi['viene_escuela'] ?? '',
                        ':par'    => $adi['parentesco_trae'] ?? '',
                        ':nom'    => $adi['nombre_trae'] ?? ''
                    ]);
                }

                if (!empty($adi['retira_casa']) || !empty($adi['parentesco_retira']) || !empty($adi['nombre_retira'])) {
                    $stmt = $conexion->prepare("INSERT INTO traslado_estudiante (cédula_escolar_estudiante, tipo, modalidad, parentesco_persona, nombres_apellidos_persona, cédula_persona) VALUES (:cedula, 'Salida', :mod, :par, :nom, '')");
                    $stmt->execute([
                        ':cedula' => $cedula_escolar,
                        ':mod'    => $adi['retira_casa'] ?? '',
                        ':par'    => $adi['parentesco_retira'] ?? '',
                        ':nom'    => $adi['nombre_retira'] ?? ''
                    ]);
                }

                if (!empty($adi['patologias']) || !empty($adi['condicion_docente'])) {
                    $stmt = $conexion->prepare("INSERT INTO antecedente_medico (cédula_escolar_estudiante, enfermedades_tratamiento, condición_importante) VALUES (:cedula, :pat, :cond)");
                    $stmt->execute([
                        ':cedula' => $cedula_escolar,
                        ':pat'    => $adi['patologias'] ?? '',
                        ':cond'   => $adi['condicion_docente'] ?? ''
                    ]);
                }

                if (!empty($adi['vacunas'])) {
                    $stmt = $conexion->prepare("INSERT INTO vacuna (cédula_escolar_estudiante, nombre_vacuna, fecha_aplicación) VALUES (:cedula, :vac, NULL)");
                    $stmt->execute([':cedula' => $cedula_escolar, ':vac' => $adi['vacunas']]);
                }

                if (!empty($adi['medicamentos'])) {
                    $stmt = $conexion->prepare("INSERT INTO medicamento_contraindicado (cédula_escolar_estudiante, nombre_medicamento, observación) VALUES (:cedula, :med, '')");
                    $stmt->execute([':cedula' => $cedula_escolar, ':med' => $adi['medicamentos']]);
                }

                if (!empty($adi['problemas_comunidad'])) {
                    $stmt = $conexion->prepare("INSERT INTO problema_comunidad (cédula_escolar_estudiante, descripción) VALUES (:cedula, :prob)");
                    $stmt->execute([':cedula' => $cedula_escolar, ':prob' => $adi['problemas_comunidad']]);
                }

                $conexion->commit();
                $accion_txt = $es_edicion ? "Modificación exitosa del alumno con cédula: " : "Inscripción exitosa del alumno con cédula: ";
                registrarHistorialSeguridad($conexion, $accion_txt . $cedula_escolar, $usuario_actual);
                unset($_SESSION['estudiante'], $_SESSION['representante'], $_SESSION['madre'], $_SESSION['padre'], $_SESSION['adicional'], $_SESSION['modo_edicion'], $_SESSION['cedula_original']);
                $mensaje_resultado = "<div style='background: #d4edda; color: #155724; padding: 12px; border-radius: 6px; margin-bottom: 15px; font-weight: 600;'>¡Datos del estudiante guardados exitosamente en el sistema!</div>";
            } catch (Exception $e) {
                $conexion->rollBack();
                $mensaje_resultado = "<div style='background: #f8d7da; color: #721c24; padding: 12px; border-radius: 6px; margin-bottom: 15px; font-weight: 600;'><i class='fas fa-exclamation-triangle'></i> " . traducirErrorBD($e->getMessage(), $cedula_escolar) . "</div>";
            }
        } else {
            $mysqli = $conexion;
            mysqli_begin_transaction($mysqli);
            try {
                $cedula_esc = mysqli_real_escape_string($mysqli, $cedula_escolar);
                $cedula_orig_esc = mysqli_real_escape_string($mysqli, $cedula_original);
                $nombres_est = mysqli_real_escape_string($mysqli, $est['nombres'] ?? '');
                $apellidos_est = mysqli_real_escape_string($mysqli, $est['apellidos'] ?? '');
                $fnac_est = mysqli_real_escape_string($mysqli, $est['fecha_nacimiento'] ?? '');
                $genero_est = mysqli_real_escape_string($mysqli, $est['género'] ?? $est['genero'] ?? '');
                $nac_est = mysqli_real_escape_string($mysqli, $est['nacionalidad'] ?? 'Venezolana');
                $peso_est = ($peso_val !== null) ? $peso_val : "NULL";
                $talla_est = ($talla_val !== null) ? $talla_val : "NULL";
                $tcamisa_est = mysqli_real_escape_string($mysqli, $est['talla_camisa'] ?? '');
                $tpantalon_est = mysqli_real_escape_string($mysqli, $est['talla_pantalon'] ?? '');
                $calzado_est = mysqli_real_escape_string($mysqli, $est['calzado'] ?? '');
                $entidad_est = mysqli_real_escape_string($mysqli, $est['entidad_federal'] ?? '');
                $lnac_est = mysqli_real_escape_string($mysqli, $est['lugar_nacimiento'] ?? '');
                $mnac_est = mysqli_real_escape_string($mysqli, $est['municipio_nacimiento'] ?? '');
                $pnac_est = mysqli_real_escape_string($mysqli, $est['parroquia_nacimiento'] ?? '');
                $dir_est = mysqli_real_escape_string($mysqli, $est['direccion_domicilio'] ?? '');
                $rel_est = mysqli_real_escape_string($mysqli, $est['religion'] ?? '');
                $vive_est = mysqli_real_escape_string($mysqli, $est['vive_con'] ?? '');
                $greep_est = mysqli_real_escape_string($mysqli, $est['especifique_grados'] ?? '');
                $desc_est = mysqli_real_escape_string($mysqli, $est['descripcion_necesidades'] ?? '');

                if ($es_edicion) {
                    $sql_est = "UPDATE estudiante SET
                        cédula_escolar = '$cedula_esc', nombres = '$nombres_est', apellidos = '$apellidos_est',
                        fecha_nacimiento = '$fnac_est', género = '$genero_est', nacionalidad = '$nac_est',
                        peso = $peso_est, talla = $talla_est, talla_camisa = '$tcamisa_est',
                        talla_pantalón = '$tpantalon_est', calzado = '$calzado_est', entidad_federal = '$entidad_est',
                        lugar_nacimiento = '$lnac_est', municipio_nacimiento = '$mnac_est', parroquia_nacimiento = '$pnac_est',
                        dirección_domicilio = '$dir_est', religión = '$rel_est', con_quien_vive = '$vive_est',
                        padres_separados = $psep_est, ha_repetido_grado = $rep_est, grados_repetidos = '$greep_est',
                        necesidades_especiales = $nesp_est, descripción_condición = '$desc_est',
                        informe_especialista = $inf_est, informe_alimentación = $alim_est, atendido_aula_integrada = $aula_est
                        WHERE cédula_escolar = '$cedula_orig_esc'";
                    ejecutarSQL($mysqli, $sql_est);

                    foreach ($tablas_hijas as $tb) {
                        ejecutarSQL($mysqli, "DELETE FROM $tb WHERE cédula_escolar_estudiante = '$cedula_esc'");
                    }
                } else {
                    $sql_est = "INSERT INTO estudiante (
                        cédula_escolar, nombres, apellidos, fecha_nacimiento, género, nacionalidad, 
                        peso, talla, talla_camisa, talla_pantalón, calzado, entidad_federal, 
                        lugar_nacimiento, municipio_nacimiento, parroquia_nacimiento, dirección_domicilio, 
                        religión, con_quien_vive, padres_separados, ha_repetido_grado, grados_repetidos, 
                        necesidades_especiales, descripción_condición, informe_especialista, informe_alimentación, atendido_aula_integrada
                    ) VALUES (
                        '$cedula_esc', '$nombres_est', '$apellidos_est', '$fnac_est', '$genero_est', '$nac_est', 
                        $peso_est, $talla_est, '$tcamisa_est', '$tpantalon_est', '$calzado_est', '$entidad_est', 
                        '$lnac_est', '$mnac_est', '$pnac_est', '$dir_est', 
                        '$rel_est', '$vive_est', $psep_est, $rep_est, '$greep_est', 
                        $nesp_est, '$desc_est', $inf_est, $alim_est, $aula_est
                    )";
                    ejecutarSQL($mysqli, $sql_est);
                }

                $nom_rep = mysqli_real_escape_string($mysqli, $rep['nombre_representante'] ?? '');
                $ci_rep = mysqli_real_escape_string($mysqli, $rep['cedula_identidad'] ?? '');
                $fnac_rep = !empty($rep['fecha_nacimiento_representante']) ? "'" . mysqli_real_escape_string($mysqli, $rep['fecha_nacimiento_representante']) . "'" : "NULL";
                $rif_rep = mysqli_real_escape_string($mysqli, $rep['rif_representante'] ?? '');
                $correo_rep = mysqli_real_escape_string($mysqli, $rep['correo_electronico_representante'] ?? '');
                $telf_rep = mysqli_real_escape_string($mysqli, $rep['telefono_representante'] ?? '');
                $dir_rep = mysqli_real_escape_string($mysqli, $rep['direccion_habitacion_representante'] ?? '');
                $niv_rep = mysqli_real_escape_string($mysqli, $rep['nivel_instruccion_representante'] ?? '');
                $prof_rep = mysqli_real_escape_string($mysqli, $rep['profesion_representante'] ?? '');
                $ocupa_rep = mysqli_real_escape_string($mysqli, $rep['ocupacion_representante'] ?? '');
                $emp_rep = mysqli_real_escape_string($mysqli, $rep['empresa_representante'] ?? '');
                $temp_rep = mysqli_real_escape_string($mysqli, $rep['telefono_empresa_representante'] ?? '');
                $diremp_rep = mysqli_real_escape_string($mysqli, $rep['direccion_empresa_representante'] ?? '');
                $parent_rep = mysqli_real_escape_string($mysqli, mb_substr($rep['parentesco_representante'] ?? '', 0, 30, 'UTF-8'));
                $poder_rep = mysqli_real_escape_string($mysqli, $rep['numero_poder'] ?? '');
                $nac_rep = mysqli_real_escape_string($mysqli, $rep['nacionalidad_cedula_rep'] ?? 'V');

                $sql_rep = "INSERT INTO representante (
                    cédula_escolar_estudiante, nombres, apellidos, cédula_identidad, fecha_nacimiento, 
                    rif, correo_electrónico, teléfono_celular, teléfono_residencia, dirección_habitación, 
                    nivel_instrucción, profesion, ocupación, empresa, teléfono_empresa, dirección_empresa, 
                    parentesco, autorización_legal, número_poder_notariado, nacionalidad
                ) VALUES (
                    '$cedula_esc', '$nom_rep', '', '$ci_rep', $fnac_rep, 
                    '$rif_rep', '$correo_rep', '$telf_rep', '', '$dir_rep', 
                    '$niv_rep', '$prof_rep', '$ocupa_rep', '$emp_rep', '$temp_rep', '$diremp_rep', 
                    '$parent_rep', $aut_rep, '$poder_rep', '$nac_rep'
                )";
                ejecutarSQL($mysqli, $sql_rep);

                if (!empty($mad['nombres_apellidos_madre']) && ($mad['no_aplica_madre'] ?? '') !== '1') {
                    $m_nom = mysqli_real_escape_string($mysqli, $mad['nombres_apellidos_madre']);
                    $m_ci = mysqli_real_escape_string($mysqli, $mad['ci_madre'] ?? '');
                    $m_telf = mysqli_real_escape_string($mysqli, $mad['telefono_madre'] ?? '');
                    $m_fnac = !empty($mad['fecha_nacimiento_madre']) ? "'" . mysqli_real_escape_string($mysqli, $mad['fecha_nacimiento_madre']) . "'" : "NULL";
                    $m_eciv = mysqli_real_escape_string($mysqli, $mad['estado_civil_madre'] ?? '');
                    $m_lnac = mysqli_real_escape_string($mysqli, $mad['lugar_nacimiento_madre'] ?? '');
                    $m_correo = mysqli_real_escape_string($mysqli, $mad['correo_madre'] ?? '');
                    $m_dir = mysqli_real_escape_string($mysqli, $mad['direccion_habitacion_madre'] ?? '');
                    $m_niv = mysqli_real_escape_string($mysqli, $mad['nivel_instruccion_madre'] ?? '');
                    $m_prof = mysqli_real_escape_string($mysqli, $mad['profesion_madre'] ?? '');
                    $m_ocupa = mysqli_real_escape_string($mysqli, $mad['ocupacion_madre'] ?? '');
                    $m_emp = mysqli_real_escape_string($mysqli, $mad['empresa_madre'] ?? '');
                    $m_temp = mysqli_real_escape_string($mysqli, $mad['telefono_empresa_madre'] ?? '');
                    $m_diremp = mysqli_real_escape_string($mysqli, $mad['direccion_empresa_madre'] ?? '');
                    $m_nac = mysqli_real_escape_string($mysqli, $mad['nacionalidad_cedula_madre'] ?? 'V');

                    $sql_mad = "INSERT INTO madre (cédula_escolar_estudiante, nombres, apellidos, cédula_identidad, teléfono, fecha_nacimiento, estado_civil, lugar_nacimiento, correo, dirección_habitación, nivel_instrucción, profesion, ocupación, empresa, teléfono_empresa, dirección_empresa, nacionalidad)
                                VALUES ('$cedula_esc', '$m_nom', '', '$m_ci', '$m_telf', $m_fnac, '$m_eciv', '$m_lnac', '$m_correo', '$m_dir', '$m_niv', '$m_prof', '$m_ocupa', '$m_emp', '$m_temp', '$m_diremp', '$m_nac')";
                    ejecutarSQL($mysqli, $sql_mad);
                }

                if (!empty($pad['nombres_apellidos_padre']) && ($pad['no_aplica_padre'] ?? '') !== '1') {
                    $p_nom = mysqli_real_escape_string($mysqli, $pad['nombres_apellidos_padre']);
                    $p_ci = mysqli_real_escape_string($mysqli, $pad['ci_padre'] ?? '');
                    $p_telf = mysqli_real_escape_string($mysqli, $pad['telefono_padre'] ?? '');
                    $p_fnac = !empty($pad['fecha_nacimiento_padre']) ? "'" . mysqli_real_escape_string($mysqli, $pad['fecha_nacimiento_padre']) . "'" : "NULL";
                    $p_eciv = mysqli_real_escape_string($mysqli, $pad['estado_civil_padre'] ?? '');
                    $p_lnac = mysqli_real_escape_string($mysqli, $pad['lugar_nacimiento_padre'] ?? '');
                    $p_correo = mysqli_real_escape_string($mysqli, $pad['correo_padre'] ?? '');
                    $p_dir = mysqli_real_escape_string($mysqli, $pad['direccion_habitacion_padre'] ?? '');
                    $p_niv = mysqli_real_escape_string($mysqli, $pad['nivel_instruccion_padre'] ?? '');
                    $p_prof = mysqli_real_escape_string($mysqli, $pad['profesion_padre'] ?? '');
                    $p_ocupa = mysqli_real_escape_string($mysqli, $pad['ocupacion_padre'] ?? '');
                    $p_emp = mysqli_real_escape_string($mysqli, $pad['empresa_padre'] ?? '');
                    $p_temp = mysqli_real_escape_string($mysqli, $pad['telefono_empresa_padre'] ?? '');
                    $p_diremp = mysqli_real_escape_string($mysqli, $pad['direccion_empresa_padre'] ?? '');
                    $p_nac = mysqli_real_escape_string($mysqli, $pad['nacionalidad_cedula_padre'] ?? 'V');

                    $sql_pad = "INSERT INTO padre (cédula_escolar_estudiante, nombres, apellidos, cédula_identidad, teléfono, fecha_nacimiento, estado_civil, lugar_nacimiento, correo, dirección_habitación, nivel_instrucción, profesion, ocupación, empresa, teléfono_empresa, dirección_empresa, nacionalidad)
                                VALUES ('$cedula_esc', '$p_nom', '', '$p_ci', '$p_telf', $p_fnac, '$p_eciv', '$p_lnac', '$p_correo', '$p_dir', '$p_niv', '$p_prof', '$p_ocupa', '$p_emp', '$p_temp', '$p_diremp', '$p_nac')";
                    ejecutarSQL($mysqli, $sql_pad);
                }

                $v_cond = mysqli_real_escape_string($mysqli, $adi['condicion_vivienda'] ?? '');
                $v_tipo = mysqli_real_escape_string($mysqli, $adi['tipo_vivienda'] ?? '');
                ejecutarSQL($mysqli, "INSERT INTO vivienda (cédula_escolar_estudiante, condición_vivienda, tipo_vivienda) VALUES ('$cedula_esc', '$v_cond', '$v_tipo')");

                $i_anio = mysqli_real_escape_string($mysqli, $periodo_activo);
                $i_tipo = mysqli_real_escape_string($mysqli, $est['tipo_ingreso'] ?? '');
                $i_plantel = mysqli_real_escape_string($mysqli, $est['plantel_procedencia'] ?? '');

                $id_grado = 1; $id_seccion = 1;
                if (!empty($grado_val)) {
                    $g_esc = mysqli_real_escape_string($mysqli, $grado_val);
                    $res_g = @mysqli_query($mysqli, "SELECT id FROM grados WHERE nombre_grado = '$g_esc' LIMIT 1");
                    if ($res_g && $row = mysqli_fetch_assoc($res_g)) {
                        $id_grado = $row['id'];
                    } else {
                        @mysqli_query($mysqli, "INSERT INTO grados (nombre_grado) VALUES ('$g_esc')");
                        $id_grado = mysqli_insert_id($mysqli);
                    }
                }

                if (!empty($seccion_busqueda)) {
                    $s_esc = mysqli_real_escape_string($mysqli, $seccion_busqueda);
                    $s_raw = mysqli_real_escape_string($mysqli, $seccion_val);
                    $res_s = @mysqli_query($mysqli, "SELECT id FROM secciones WHERE nombre_seccion = '$s_esc' OR nombre_seccion = '$s_raw' LIMIT 1");
                    if ($res_s && $row = mysqli_fetch_assoc($res_s)) {
                        $id_seccion = $row['id'];
                    } else {
                        @mysqli_query($mysqli, "INSERT INTO secciones (nombre_seccion) VALUES ('$s_esc')");
                        $id_seccion = mysqli_insert_id($mysqli);
                    }
                }

                ejecutarSQL($mysqli, "INSERT INTO inscripcion (cédula_escolar_estudiante, año_escolar, id_grado, id_seccion, tipo_estudiante, plantel_procedencia) VALUES ('$cedula_esc', '$i_anio', $id_grado, $id_seccion, '$i_tipo', '$i_plantel')");

                if (!empty($adi['hermanos_escuela'])) {
                    $h_nom = mysqli_real_escape_string($mysqli, $adi['hermanos_escuela']);
                    ejecutarSQL($mysqli, "INSERT INTO hermano (cédula_escolar_estudiante, nombres_apellidos, grado) VALUES ('$cedula_esc', '$h_nom', '')");
                }

                if (!empty($adi['viene_escuela']) || !empty($adi['parentesco_trae']) || !empty($adi['nombre_trae'])) {
                    $t_mod = mysqli_real_escape_string($mysqli, $adi['viene_escuela'] ?? '');
                    $t_par = mysqli_real_escape_string($mysqli, $adi['parentesco_trae'] ?? '');
                    $t_nom = mysqli_real_escape_string($mysqli, $adi['nombre_trae'] ?? '');
                    ejecutarSQL($mysqli, "INSERT INTO traslado_estudiante (cédula_escolar_estudiante, tipo, modalidad, parentesco_persona, nombres_apellidos_persona, cédula_persona) VALUES ('$cedula_esc', 'Entrada', '$t_mod', '$t_par', '$t_nom', '')");
                }

                if (!empty($adi['retira_casa']) || !empty($adi['parentesco_retira']) || !empty($adi['nombre_retira'])) {
                    $ts_mod = mysqli_real_escape_string($mysqli, $adi['retira_casa'] ?? '');
                    $ts_par = mysqli_real_escape_string($mysqli, $adi['parentesco_retira'] ?? '');
                    $ts_nom = mysqli_real_escape_string($mysqli, $adi['nombre_retira'] ?? '');
                    ejecutarSQL($mysqli, "INSERT INTO traslado_estudiante (cédula_escolar_estudiante, tipo, modalidad, parentesco_persona, nombres_apellidos_persona, cédula_persona) VALUES ('$cedula_esc', 'Salida', '$ts_mod', '$ts_par', '$ts_nom', '')");
                }

                if (!empty($adi['patologias']) || !empty($adi['condicion_docente'])) {
                    $a_pat = mysqli_real_escape_string($mysqli, $adi['patologias'] ?? '');
                    $a_con = mysqli_real_escape_string($mysqli, $adi['condicion_docente'] ?? '');
                    ejecutarSQL($mysqli, "INSERT INTO antecedente_medico (cédula_escolar_estudiante, enfermedades_tratamiento, condición_importante) VALUES ('$cedula_esc', '$a_pat', '$a_con')");
                }

                if (!empty($adi['vacunas'])) {
                    $vac = mysqli_real_escape_string($mysqli, $adi['vacunas']);
                    ejecutarSQL($mysqli, "INSERT INTO vacuna (cédula_escolar_estudiante, nombre_vacuna, fecha_aplicación) VALUES ('$cedula_esc', '$vac', NULL)");
                }

                if (!empty($adi['medicamentos'])) {
                    $med = mysqli_real_escape_string($mysqli, $adi['medicamentos']);
                    ejecutarSQL($mysqli, "INSERT INTO medicamento_contraindicado (cédula_escolar_estudiante, nombre_medicamento, observación) VALUES ('$cedula_esc', '$med', '')");
                }

                if (!empty($adi['problemas_comunidad'])) {
                    $prob = mysqli_real_escape_string($mysqli, $adi['problemas_comunidad']);
                    ejecutarSQL($mysqli, "INSERT INTO problema_comunidad (cédula_escolar_estudiante, descripción) VALUES ('$cedula_esc', '$prob')");
                }

                mysqli_commit($mysqli);
                $accion_txt = $es_edicion ? "Modificación exitosa del alumno con cédula: " : "Inscripción exitosa del alumno con cédula: ";
                registrarHistorialSeguridad($mysqli, $accion_txt . $cedula_esc, $usuario_actual);
                unset($_SESSION['estudiante'], $_SESSION['representante'], $_SESSION['madre'], $_SESSION['padre'], $_SESSION['adicional'], $_SESSION['modo_edicion'], $_SESSION['cedula_original']);
                $mensaje_resultado = "<div style='background: #d4edda; color: #155724; padding: 12px; border-radius: 6px; margin-bottom: 15px; font-weight: 600;'>¡Datos del estudiante guardados exitosamente en el sistema!</div>";
            } catch (Exception $e) {
                mysqli_rollback($mysqli);
                $mensaje_resultado = "<div style='background: #f8d7da; color: #721c24; padding: 12px; border-radius: 6px; margin-bottom: 15px; font-weight: 600;'><i class='fas fa-exclamation-triangle'></i> " . traducirErrorBD($e->getMessage(), $cedula_escolar) . "</div>";
            }
        }
    }
}

$tc = valorCampo('talla_camisa');
$tp = valorCampo('talla_pantalon');
$ps = valorCampo('padres_separados');
$rg = valorCampo('repitio_grado');
$ne = valorCampo('necesidades_especiales');
$ie = valorCampo('informe_especialista');
$ia = valorCampo('informe_alimentacion');
$ai = valorCampo('aula_integrada');
$cv = valorCampo('condicion_vivienda');
$tv = valorCampo('tipo_vivienda');
$ve = valorCampo('viene_escuela');
$rc = valorCampo('retira_casa');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Datos Adicionales y Vivienda - Sistema de Inscripción</title>
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
        .form-group textarea { resize: vertical; height: 45px; }
        .section-title { font-size: 0.9rem; font-weight: bold; color: var(--text-primary); margin: 18px 0 8px 0; border-bottom: 1px solid var(--border-color); padding-bottom: 4px; }
        .nota-institucional { font-size: 0.78rem; color: var(--text-secondary); font-style: italic; margin-top: 4px; margin-bottom: 10px; line-height: 1.3; }
        .btn-container { display: flex; justify-content: space-between; margin-top: 20px; }
        .btn-prev { background-color: #64748b; color: white; border: none; padding: 8px 20px; border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 0.88rem; text-decoration: none; display: inline-flex; align-items: center; }
        .btn-prev:hover { opacity: 0.9; color: white; }
        .btn-finish { background-color: #059669; color: white; border: none; padding: 8px 20px; border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 0.88rem; transition: background-color 0.2s; }
        .btn-finish:hover { opacity: 0.9; }

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
                <i class="fas fa-clipboard-list" style="color: #2563eb;"></i>
                <span>Datos Adicionales y Vivienda</span>
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
                <h3 style="margin: 0;"><i class="fas fa-clipboard-list text-primary" style="color: #2563eb;"></i> Paso 5: Datos Adicionales, Vivienda, Salud y Traslado (Final)</h3>
                <a href="limpiar_sesion.php?redirect=agregar_adicional.php" onclick="return confirm('¿Estás seguro de limpiar todos los datos del formulario?');" style="background-color: #ef4444; color: white; padding: 5px 12px; border-radius: 6px; text-decoration: none; font-size: 0.82rem; font-weight: 600;">
                    <i class="fas fa-trash-alt"></i> Limpiar Formulario
                </a>
            </div>
            <hr style="margin-bottom: 15px; border: 0; border-top: 1px solid var(--border-color);">

            <?php echo $mensaje_resultado; ?>

            <form action="" method="POST">
                <div class="section-title">PESO, TALLAS Y DATOS DE NACIMIENTO DEL ESTUDIANTE</div>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Peso (kg):</label>
                        <input type="number" name="peso" step="0.01" min="5" max="120" placeholder="Ej: 35.5" onkeypress="return soloDecimales(event)" oninput="validarPeso(this)" title="Solo números (máximo 120 kg)" value="<?php echo valorCampo('peso'); ?>">
                    </div>

                    <div class="form-group">
                        <label>Talla / Estatura (m):</label>
                        <input type="number" name="talla" step="0.01" min="0.40" max="2.20" placeholder="Ej: 1.45" onkeypress="return soloDecimales(event)" oninput="validarTalla(this)" title="Solo números (máximo 2.20 m)" value="<?php echo valorCampo('talla'); ?>">
                    </div>

                    <div class="form-group">
                        <label>Talla de Camisa:</label>
                        <select name="talla_camisa">
                            <option value="">Elige una opción</option>
                            <option value="4" <?php echo ($tc === '4') ? 'selected' : ''; ?>>4</option>
                            <option value="6" <?php echo ($tc === '6') ? 'selected' : ''; ?>>6</option>
                            <option value="8" <?php echo ($tc === '8') ? 'selected' : ''; ?>>8</option>
                            <option value="10" <?php echo ($tc === '10') ? 'selected' : ''; ?>>10</option>
                            <option value="12" <?php echo ($tc === '12') ? 'selected' : ''; ?>>12</option>
                            <option value="14" <?php echo ($tc === '14') ? 'selected' : ''; ?>>14</option>
                            <option value="16" <?php echo ($tc === '16') ? 'selected' : ''; ?>>16</option>
                            <option value="SS" <?php echo ($tc === 'SS') ? 'selected' : ''; ?>>SS</option>
                            <option value="S" <?php echo ($tc === 'S') ? 'selected' : ''; ?>>S</option>
                            <option value="M" <?php echo ($tc === 'M') ? 'selected' : ''; ?>>M</option>
                            <option value="L" <?php echo ($tc === 'L') ? 'selected' : ''; ?>>L</option>
                            <option value="XL" <?php echo ($tc === 'XL') ? 'selected' : ''; ?>>XL</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Talla de Pantalón:</label>
                        <select name="talla_pantalon">
                            <option value="">Elige una opción</option>
                            <option value="4" <?php echo ($tp === '4') ? 'selected' : ''; ?>>4</option>
                            <option value="6" <?php echo ($tp === '6') ? 'selected' : ''; ?>>6</option>
                            <option value="8" <?php echo ($tp === '8') ? 'selected' : ''; ?>>8</option>
                            <option value="10" <?php echo ($tp === '10') ? 'selected' : ''; ?>>10</option>
                            <option value="12" <?php echo ($tp === '12') ? 'selected' : ''; ?>>12</option>
                            <option value="14" <?php echo ($tp === '14') ? 'selected' : ''; ?>>14</option>
                            <option value="16" <?php echo ($tp === '16') ? 'selected' : ''; ?>>16</option>
                            <option value="26" <?php echo ($tp === '26') ? 'selected' : ''; ?>>26</option>
                            <option value="28" <?php echo ($tp === '28') ? 'selected' : ''; ?>>28</option>
                            <option value="30" <?php echo ($tp === '30') ? 'selected' : ''; ?>>30</option>
                            <option value="32" <?php echo ($tp === '32') ? 'selected' : ''; ?>>32</option>
                            <option value="34" <?php echo ($tp === '34') ? 'selected' : ''; ?>>34</option>
                            <option value="S" <?php echo ($tp === 'S') ? 'selected' : ''; ?>>S</option>
                            <option value="M" <?php echo ($tp === 'M') ? 'selected' : ''; ?>>M</option>
                            <option value="L" <?php echo ($tp === 'L') ? 'selected' : ''; ?>>L</option>
                            <option value="XL" <?php echo ($tp === 'XL') ? 'selected' : ''; ?>>XL</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Calzado:</label>
                        <input type="number" name="calzado" min="15" max="48" placeholder="Ej: 35" onkeypress="return soloNumeros(event)" oninput="validarCalzado(this)" title="Solo números (entre 15 y 48)" value="<?php echo valorCampo('calzado'); ?>">
                    </div>

                    <div class="form-group">
                        <label>Entidad Federal (Estado):</label>
                        <input type="text" name="entidad_federal" maxlength="30" onkeypress="return soloLetrasYEspacios(event)" oninput="limpiarLetras(this)" pattern="[A-Za-zÁÉÍÓÚáéíóúñÑ\s]*" title="Solo letras" value="<?php echo valorCampo('entidad_federal'); ?>">
                    </div>

                    <div class="form-group">
                        <label>Lugar de Nacimiento:</label>
                        <input type="text" name="lugar_nacimiento" maxlength="40" onkeypress="return soloLetrasYEspacios(event)" oninput="limpiarLetras(this)" pattern="[A-Za-zÁÉÍÓÚáéíóúñÑ\s]*" title="Solo letras" value="<?php echo valorCampo('lugar_nacimiento'); ?>">
                    </div>

                    <div class="form-group">
                        <label>Municipio de Nacimiento:</label>
                        <input type="text" name="municipio_nacimiento" maxlength="30" onkeypress="return soloLetrasYEspacios(event)" oninput="limpiarLetras(this)" pattern="[A-Za-zÁÉÍÓÚáéíóúñÑ\s]*" title="Solo letras" value="<?php echo valorCampo('municipio_nacimiento'); ?>">
                    </div>

                    <div class="form-group">
                        <label>Parroquia de Nacimiento:</label>
                        <input type="text" name="parroquia_nacimiento" maxlength="30" onkeypress="return soloLetrasYEspacios(event)" oninput="limpiarLetras(this)" pattern="[A-Za-zÁÉÍÓÚáéíóúñÑ\s]*" title="Solo letras" value="<?php echo valorCampo('parroquia_nacimiento'); ?>">
                    </div>
                </div>

                <div class="section-title">CONTEXTO SOCIOFAMILIAR Y CONDICIONES ESPECIALES</div>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Religión:</label>
                        <input type="text" name="religion" maxlength="25" onkeypress="return soloLetrasYEspacios(event)" oninput="limpiarLetras(this)" pattern="[A-Za-zÁÉÍÓÚáéíóúñÑ\s]*" title="Solo letras" value="<?php echo valorCampo('religion'); ?>">
                    </div>

                    <div class="form-group">
                        <label>¿Con quién vive el estudiante?:</label>
                        <input type="text" name="vive_con" maxlength="40" onkeypress="return soloLetrasYEspacios(event)" oninput="limpiarLetras(this)" pattern="[A-Za-zÁÉÍÓÚáéíóúñÑ\s]*" title="Solo letras" value="<?php echo valorCampo('vive_con'); ?>">
                    </div>

                    <div class="form-group">
                        <label>¿Sus padres están separados?:</label>
                        <select name="padres_separados">
                            <option value="">Elige una opción</option>
                            <option value="No" <?php echo ($ps === 'No') ? 'selected' : ''; ?>>No</option>
                            <option value="Sí" <?php echo ($ps === 'Sí') ? 'selected' : ''; ?>>Sí</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>¿Ha repetido grados?:</label>
                        <select name="repitio_grado">
                            <option value="">Elige una opción</option>
                            <option value="No" <?php echo ($rg === 'No') ? 'selected' : ''; ?>>No</option>
                            <option value="Sí" <?php echo ($rg === 'Sí') ? 'selected' : ''; ?>>Sí</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Especifique cuáles:</label>
                        <input type="text" name="especifique_grados" maxlength="50" value="<?php echo valorCampo('especifique_grados'); ?>">
                    </div>

                    <div class="form-group">
                        <label>Presenta necesidades especiales:</label>
                        <select name="necesidades_especiales">
                            <option value="">Elige una opción</option>
                            <option value="No" <?php echo ($ne === 'No') ? 'selected' : ''; ?>>No</option>
                            <option value="Sí" <?php echo ($ne === 'Sí') ? 'selected' : ''; ?>>Sí</option>
                        </select>
                    </div>

                    <div class="form-group" style="grid-column: span 2;">
                        <label>Describa la condición que presenta:</label>
                        <textarea name="descripcion_necesidades" maxlength="100"><?php echo valorCampo('descripcion_necesidades'); ?></textarea>
                    </div>

                    <div class="form-group">
                        <label>¿Posee informe emitido por un especialista?:</label>
                        <select name="informe_especialista">
                            <option value="">Elige una opción</option>
                            <option value="No" <?php echo ($ie === 'No') ? 'selected' : ''; ?>>No</option>
                            <option value="Sí" <?php echo ($ie === 'Sí') ? 'selected' : ''; ?>>Sí</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>¿Presenta informe médico de alimentación?:</label>
                        <select name="informe_alimentacion">
                            <option value="">Elige una opción</option>
                            <option value="No" <?php echo ($ia === 'No') ? 'selected' : ''; ?>>No</option>
                            <option value="Sí" <?php echo ($ia === 'Sí') ? 'selected' : ''; ?>>Sí</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>¿Ha sido atendido por aula integrada?:</label>
                        <select name="aula_integrada">
                            <option value="">Elige una opción</option>
                            <option value="No" <?php echo ($ai === 'No') ? 'selected' : ''; ?>>No</option>
                            <option value="Sí" <?php echo ($ai === 'Sí') ? 'selected' : ''; ?>>Sí</option>
                        </select>
                    </div>
                </div>

                <div class="section-title">CONDICIÓN DE LA VIVIENDA / TIPO DE VIVIENDA</div>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Condición de la Vivienda:</label>
                        <select name="condicion_vivienda">
                            <option value="">Elige una opción</option>
                            <option value="Alquilada" <?php echo ($cv === 'Alquilada') ? 'selected' : ''; ?>>Alquilada</option>
                            <option value="Prestada" <?php echo ($cv === 'Prestada') ? 'selected' : ''; ?>>Prestada</option>
                            <option value="Propia" <?php echo ($cv === 'Propia') ? 'selected' : ''; ?>>Propia</option>
                            <option value="Pagándola" <?php echo ($cv === 'Pagándola') ? 'selected' : ''; ?>>Pagándola</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Tipo de Vivienda:</label>
                        <select name="tipo_vivienda">
                            <option value="">Elige una opción</option>
                            <option value="Apartamento" <?php echo ($tv === 'Apartamento') ? 'selected' : ''; ?>>Apartamento</option>
                            <option value="Casa" <?php echo ($tv === 'Casa') ? 'selected' : ''; ?>>Casa</option>
                            <option value="Rancho" <?php echo ($tv === 'Rancho') ? 'selected' : ''; ?>>Rancho</option>
                        </select>
                    </div>
                </div>

                <div class="section-title">Hermanos que estudian en la escuela</div>
                <div class="form-grid">
                    <div class="form-group" style="grid-column: span 2;">
                        <label>Nombres de hermanos que estudian en la escuela / Grado:</label>
                        <textarea name="hermanos_escuela" maxlength="100" placeholder="Ej: Juan Pérez (3er Grado), María Pérez (1er Grado)"><?php echo valorCampo('hermanos_escuela'); ?></textarea>
                    </div>
                </div>

                <div class="section-title">Antecedentes y Salud</div>
                <div class="form-grid">
                    <div class="form-group" style="grid-column: span 2;">
                        <label>ANTECEDENTES PERSONALES DE PATOLOGÍAS DEL NIÑO / NIÑA: especifique las enfermedades que ha padecido y tratamiento</label>
                        <textarea name="patologias" maxlength="100"><?php echo valorCampo('patologias'); ?></textarea>
                    </div>

                    <div class="form-group" style="grid-column: span 2;">
                        <label>Describa alguna condición importante que usted considere debe conocer el docente por el bienestar de su representado.</label>
                        <textarea name="condicion_docente" maxlength="100"><?php echo valorCampo('condicion_docente'); ?></textarea>
                        <div class="nota-institucional">NOTA: LOS REPRESENTANTES DEBEN PRESENTAR INFORME MÉDICO DE LAS ENFERMEDADES QUE PADECE EL NIÑO EMITIDO POR LOS ESPECIALISTAS.</div>
                    </div>

                    <div class="form-group">
                        <label>VACUNAS RECIBIDAS</label>
                        <input type="text" name="vacunas" maxlength="30" onkeypress="return soloLetrasYEspacios(event)" oninput="limpiarLetras(this)" value="<?php echo valorCampo('vacunas'); ?>">
                    </div>

                    <div class="form-group">
                        <label>MEDICAMENTOS CONTRAINDICADOS</label>
                        <input type="text" name="medicamentos" maxlength="40" onkeypress="return soloLetrasYEspacios(event)" oninput="limpiarLetras(this)" value="<?php echo valorCampo('medicamentos'); ?>">
                    </div>
                </div>

                <div class="section-title">Logística de Traslado</div>
                <div class="form-grid">
                    <div class="form-group">
                        <label>¿Con quién se viene el estudiante a la escuela?:</label>
                        <select name="viene_escuela">
                            <option value="">Elige una opción</option>
                            <option value="Solo" <?php echo ($ve === 'Solo') ? 'selected' : ''; ?>>Solo</option>
                            <option value="Acompañado" <?php echo ($ve === 'Acompañado') ? 'selected' : ''; ?>>Acompañado</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>¿Con quién se retira el estudiante a su casa?:</label>
                        <select name="retira_casa">
                            <option value="">Elige una opción</option>
                            <option value="Solo" <?php echo ($rc === 'Solo') ? 'selected' : ''; ?>>Solo</option>
                            <option value="Acompañado" <?php echo ($rc === 'Acompañado') ? 'selected' : ''; ?>>Acompañado</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Parentesco de la persona que lo trae a la escuela:</label>
                        <input type="text" name="parentesco_trae" maxlength="25" onkeypress="return soloLetrasYEspacios(event)" oninput="limpiarLetras(this)" pattern="[A-Za-zÁÉÍÓÚáéíóúñÑ\s]*" title="Solo letras" value="<?php echo valorCampo('parentesco_trae'); ?>">
                    </div>

                    <div class="form-group">
                        <label>Nombres y apellidos de quien lo trae a la escuela:</label>
                        <input type="text" name="nombre_trae" maxlength="50" onkeypress="return soloLetrasYEspacios(event)" oninput="limpiarLetras(this)" pattern="[A-Za-zÁÉÍÓÚáéíóúñÑ\s]*" title="Solo letras" value="<?php echo valorCampo('nombre_trae'); ?>">
                    </div>

                    <div class="form-group">
                        <label>Parentesco de la persona que lo retira de la escuela:</label>
                        <input type="text" name="parentesco_retira" maxlength="25" onkeypress="return soloLetrasYEspacios(event)" oninput="limpiarLetras(this)" pattern="[A-Za-zÁÉÍÓÚáéíóúñÑ\s]*" title="Solo letras" value="<?php echo valorCampo('parentesco_retira'); ?>">
                    </div>

                    <div class="form-group">
                        <label>Nombres y apellidos de quien lo retira de la escuela:</label>
                        <input type="text" name="nombre_retira" maxlength="50" onkeypress="return soloLetrasYEspacios(event)" oninput="limpiarLetras(this)" pattern="[A-Za-zÁÉÍÓÚáéíóúñÑ\s]*" title="Solo letras" value="<?php echo valorCampo('nombre_retira'); ?>">
                    </div>
                </div>

                <div class="section-title">Entorno Comunitario</div>
                <div class="form-grid">
                    <div class="form-group" style="grid-column: span 2;">
                        <label>Describa los problemas que se presentan en su comunidad y afectan al núcleo familiar:</label>
                        <textarea name="problemas_comunidad" maxlength="100"><?php echo valorCampo('problemas_comunidad'); ?></textarea>
                    </div>
                </div>

                <div class="btn-container">
                    <a href="agregar_padre.php" class="btn-prev"><i class="fas fa-arrow-left"></i> Anterior</a>
                    <button type="submit" name="finalizar_registro" class="btn-finish"><i class="fas fa-check-circle"></i> Finalizar y Guardar</button>
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

        function soloDecimales(e) {
            var key = e.charCode || e.keyCode;
            var caracter = String.fromCharCode(key);
            return ((key >= 48 && key <= 57) || caracter === '.' || caracter === ',');
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

        function validarPeso(campo) {
            campo.value = campo.value.replace(/[^0-9.,]/g, '');
            if (parseFloat(campo.value) > 120) {
                campo.value = '120';
            }
        }

        function validarTalla(campo) {
            campo.value = campo.value.replace(/[^0-9.,]/g, '');
            if (parseFloat(campo.value) > 2.20) {
                campo.value = '2.20';
            }
        }

        function validarCalzado(campo) {
            campo.value = campo.value.replace(/[^0-9]/g, '');
            if (parseInt(campo.value) > 48) {
                campo.value = '48';
            }
        }
    </script>
</body>
</html>