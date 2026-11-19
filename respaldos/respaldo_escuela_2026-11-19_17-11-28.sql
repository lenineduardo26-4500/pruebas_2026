-- Respaldo de la Base de Datos del Sistema Escolar
-- Fecha: 2026-11-19 17:11:28



CREATE TABLE `antecedente_medico` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cédula_escolar_estudiante` varchar(15) COLLATE utf8mb4_spanish_ci NOT NULL,
  `enfermedades_tratamiento` text COLLATE utf8mb4_spanish_ci,
  `condición_importante` text COLLATE utf8mb4_spanish_ci,
  PRIMARY KEY (`id`),
  KEY `fk_estudiante_antecedente_medico` (`cédula_escolar_estudiante`),
  CONSTRAINT `fk_estudiante_antecedente_medico` FOREIGN KEY (`cédula_escolar_estudiante`) REFERENCES `estudiante` (`cédula_escolar`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;



CREATE TABLE `archivados` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cedula_escolar` varchar(15) COLLATE utf8mb4_spanish_ci NOT NULL,
  `nombres` varchar(100) COLLATE utf8mb4_spanish_ci NOT NULL,
  `apellidos` varchar(100) COLLATE utf8mb4_spanish_ci NOT NULL,
  `grado` varchar(30) COLLATE utf8mb4_spanish_ci NOT NULL DEFAULT '6',
  `seccion` varchar(30) COLLATE utf8mb4_spanish_ci NOT NULL DEFAULT '',
  `año_escolar` varchar(25) COLLATE utf8mb4_spanish_ci NOT NULL DEFAULT '',
  `fecha_archivo` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `usuario` varchar(80) COLLATE utf8mb4_spanish_ci NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_archivados_cedula` (`cedula_escolar`),
  CONSTRAINT `fk_estudiante_archivados` FOREIGN KEY (`cedula_escolar`) REFERENCES `estudiante` (`cédula_escolar`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;



CREATE TABLE `autoridades` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombres_apellidos` varchar(150) COLLATE utf8mb4_spanish_ci NOT NULL,
  `cedula` varchar(20) COLLATE utf8mb4_spanish_ci NOT NULL,
  `cargo` varchar(100) COLLATE utf8mb4_spanish_ci NOT NULL,
  `estatus` varchar(20) COLLATE utf8mb4_spanish_ci DEFAULT 'Activo',
  `firma` varchar(255) COLLATE utf8mb4_spanish_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

INSERT INTO `autoridades` (`id`, `nombres_apellidos`, `cedula`, `cargo`, `estatus`, `firma`) VALUES ('1', 'Elsy Alarcón', 'V-10100589', 'Director(a)', 'Activo', 'firmas/firma_1794528430_9026.png');


CREATE TABLE `calendario` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tipo` varchar(50) COLLATE utf8mb4_spanish_ci NOT NULL,
  `fecha_inicio` date NOT NULL,
  `fecha_fin` date DEFAULT NULL,
  `descripcion` text COLLATE utf8mb4_spanish_ci,
  `estado` varchar(50) COLLATE utf8mb4_spanish_ci DEFAULT 'Programado',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

INSERT INTO `calendario` (`id`, `tipo`, `fecha_inicio`, `fecha_fin`, `descripcion`, `estado`) VALUES ('1', 'Feriado', '2026-11-18', '2026-11-19', 'hhhhhhhhh', 'Programado');


CREATE TABLE `configuracion` (
  `clave` varchar(50) COLLATE utf8mb4_spanish_ci NOT NULL,
  `valor` text COLLATE utf8mb4_spanish_ci NOT NULL,
  PRIMARY KEY (`clave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

INSERT INTO `configuracion` (`clave`, `valor`) VALUES ('codigo_dea', '009631530');
INSERT INTO `configuracion` (`clave`, `valor`) VALUES ('direccion', 'Avenida 1 Hoyada de Milla');
INSERT INTO `configuracion` (`clave`, `valor`) VALUES ('estado', 'Activo');
INSERT INTO `configuracion` (`clave`, `valor`) VALUES ('municipio', 'Municipio libertador');
INSERT INTO `configuracion` (`clave`, `valor`) VALUES ('nombre_escuela', 'Escuela Gladys C. Lobo de Carnevali');
INSERT INTO `configuracion` (`clave`, `valor`) VALUES ('periodo_escolar', '2026-2027');


CREATE TABLE `docente_seccion` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_docente` int(11) NOT NULL,
  `id_grado` int(11) NOT NULL,
  `id_seccion` int(11) NOT NULL,
  `año_escolar` varchar(9) COLLATE utf8mb4_spanish_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_grado_seccion_anio` (`id_grado`,`id_seccion`,`año_escolar`),
  KEY `fk_docente_asignado` (`id_docente`),
  KEY `fk_grado_asignado` (`id_grado`),
  KEY `fk_seccion_asignado` (`id_seccion`),
  CONSTRAINT `fk_docente_asignado` FOREIGN KEY (`id_docente`) REFERENCES `docentes` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_grado_asignado` FOREIGN KEY (`id_grado`) REFERENCES `grados` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_seccion_asignado` FOREIGN KEY (`id_seccion`) REFERENCES `secciones` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

INSERT INTO `docente_seccion` (`id`, `id_docente`, `id_grado`, `id_seccion`, `año_escolar`) VALUES ('9', '4', '2', '2', '2026-2027');
INSERT INTO `docente_seccion` (`id`, `id_docente`, `id_grado`, `id_seccion`, `año_escolar`) VALUES ('10', '5', '1', '1', '2026-2027');


CREATE TABLE `docentes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombres_apellidos` varchar(120) COLLATE utf8mb4_spanish_ci NOT NULL,
  `genero` varchar(15) COLLATE utf8mb4_spanish_ci DEFAULT 'MASCULINO',
  `estado` varchar(20) COLLATE utf8mb4_spanish_ci NOT NULL DEFAULT 'Activo',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

INSERT INTO `docentes` (`id`, `nombres_apellidos`, `genero`, `estado`) VALUES ('1', 'maria', 'FEMENINO', 'Activo');
INSERT INTO `docentes` (`id`, `nombres_apellidos`, `genero`, `estado`) VALUES ('3', 'jose perez', 'MASCULINO', 'Activo');
INSERT INTO `docentes` (`id`, `nombres_apellidos`, `genero`, `estado`) VALUES ('4', 'leonardo mora', 'MASCULINO', 'Activo');
INSERT INTO `docentes` (`id`, `nombres_apellidos`, `genero`, `estado`) VALUES ('5', 'lennin niño', 'MASCULINO', 'Activo');


CREATE TABLE `estudiante` (
  `cédula_escolar` varchar(15) COLLATE utf8mb4_spanish_ci NOT NULL,
  `nombres` varchar(60) COLLATE utf8mb4_spanish_ci NOT NULL,
  `apellidos` varchar(60) COLLATE utf8mb4_spanish_ci NOT NULL,
  `fecha_nacimiento` date NOT NULL,
  `género` varchar(15) COLLATE utf8mb4_spanish_ci DEFAULT NULL,
  `nacionalidad` varchar(30) COLLATE utf8mb4_spanish_ci NOT NULL,
  `peso` decimal(5,2) DEFAULT NULL,
  `talla` decimal(5,2) DEFAULT NULL,
  `talla_camisa` varchar(10) COLLATE utf8mb4_spanish_ci DEFAULT NULL,
  `talla_pantalón` varchar(10) COLLATE utf8mb4_spanish_ci DEFAULT NULL,
  `calzado` varchar(10) COLLATE utf8mb4_spanish_ci DEFAULT NULL,
  `entidad_federal` varchar(60) COLLATE utf8mb4_spanish_ci DEFAULT NULL,
  `lugar_nacimiento` varchar(100) COLLATE utf8mb4_spanish_ci DEFAULT NULL,
  `municipio_nacimiento` varchar(60) COLLATE utf8mb4_spanish_ci DEFAULT NULL,
  `parroquia_nacimiento` varchar(80) COLLATE utf8mb4_spanish_ci DEFAULT NULL,
  `dirección_domicilio` varchar(200) COLLATE utf8mb4_spanish_ci DEFAULT NULL,
  `religión` varchar(40) COLLATE utf8mb4_spanish_ci DEFAULT NULL,
  `con_quien_vive` varchar(100) COLLATE utf8mb4_spanish_ci DEFAULT NULL,
  `padres_separados` tinyint(1) DEFAULT NULL,
  `ha_repetido_grado` tinyint(1) DEFAULT NULL,
  `grados_repetidos` varchar(100) COLLATE utf8mb4_spanish_ci DEFAULT NULL,
  `necesidades_especiales` tinyint(1) DEFAULT NULL,
  `descripción_condición` text COLLATE utf8mb4_spanish_ci,
  `informe_especialista` tinyint(1) DEFAULT NULL,
  `informe_alimentación` tinyint(1) DEFAULT NULL,
  `atendido_aula_integrada` tinyint(1) DEFAULT NULL,
  PRIMARY KEY (`cédula_escolar`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

INSERT INTO `estudiante` (`cédula_escolar`, `nombres`, `apellidos`, `fecha_nacimiento`, `género`, `nacionalidad`, `peso`, `talla`, `talla_camisa`, `talla_pantalón`, `calzado`, `entidad_federal`, `lugar_nacimiento`, `municipio_nacimiento`, `parroquia_nacimiento`, `dirección_domicilio`, `religión`, `con_quien_vive`, `padres_separados`, `ha_repetido_grado`, `grados_repetidos`, `necesidades_especiales`, `descripción_condición`, `informe_especialista`, `informe_alimentación`, `atendido_aula_integrada`) VALUES ('V-28163342', 'carlos ', 'perez', '2001-09-24', 'MASCULINO', 'Venezolana', '75.00', '1.94', 'M', '12', '40', 'merida', 'hospital merida', 'libertador', 'milla', '', '', '', '0', '0', '', '0', '', '0', '0', '0');


CREATE TABLE `grados` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre_grado` varchar(30) COLLATE utf8mb4_spanish_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

INSERT INTO `grados` (`id`, `nombre_grado`) VALUES ('1', '1');
INSERT INTO `grados` (`id`, `nombre_grado`) VALUES ('2', '2');
INSERT INTO `grados` (`id`, `nombre_grado`) VALUES ('3', '3');
INSERT INTO `grados` (`id`, `nombre_grado`) VALUES ('4', '4');
INSERT INTO `grados` (`id`, `nombre_grado`) VALUES ('5', '5');
INSERT INTO `grados` (`id`, `nombre_grado`) VALUES ('6', '6');


CREATE TABLE `hermano` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cédula_escolar_estudiante` varchar(15) COLLATE utf8mb4_spanish_ci NOT NULL,
  `nombres_apellidos` varchar(120) COLLATE utf8mb4_spanish_ci NOT NULL,
  `grado` varchar(20) COLLATE utf8mb4_spanish_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_estudiante_hermano` (`cédula_escolar_estudiante`),
  CONSTRAINT `fk_estudiante_hermano` FOREIGN KEY (`cédula_escolar_estudiante`) REFERENCES `estudiante` (`cédula_escolar`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;



CREATE TABLE `historial` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `descripcion` text COLLATE utf8mb4_spanish_ci NOT NULL,
  `usuario` varchar(100) COLLATE utf8mb4_spanish_ci NOT NULL,
  `fecha_hora` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=100 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

INSERT INTO `historial` (`id`, `descripcion`, `usuario`, `fecha_hora`) VALUES ('1', 'Cierre de sesión de usuario', 'estudiantes_pnf', '2026-11-13 11:35:20');
INSERT INTO `historial` (`id`, `descripcion`, `usuario`, `fecha_hora`) VALUES ('2', 'Inicio de sesión exitoso', 'estudiantes_pnf', '2026-11-13 11:35:28');
INSERT INTO `historial` (`id`, `descripcion`, `usuario`, `fecha_hora`) VALUES ('3', 'Generación de respaldo de base de datos (respaldo_escuela_2026-11-13_11-37-56.sql)', 'estudiantes_pnf', '2026-11-13 11:37:56');
INSERT INTO `historial` (`id`, `descripcion`, `usuario`, `fecha_hora`) VALUES ('4', 'Actualización de datos y asignación del docente: Lennin Niño al 6° Grado Sección A (2026-2027)', 'estudiantes_pnf', '2026-11-13 12:50:11');
INSERT INTO `historial` (`id`, `descripcion`, `usuario`, `fecha_hora`) VALUES ('5', 'Cambio de estado del docente Lennin Niño a: Inactivo', 'estudiantes_pnf', '2026-11-13 12:50:48');
INSERT INTO `historial` (`id`, `descripcion`, `usuario`, `fecha_hora`) VALUES ('6', 'Cambio de estado del docente Lennin Niño a: Activo', 'estudiantes_pnf', '2026-11-13 12:50:54');
INSERT INTO `historial` (`id`, `descripcion`, `usuario`, `fecha_hora`) VALUES ('7', 'Cambio de estado del docente Lennin Niño a: Inactivo', 'estudiantes_pnf', '2026-11-13 12:50:56');
INSERT INTO `historial` (`id`, `descripcion`, `usuario`, `fecha_hora`) VALUES ('8', 'Cambio de estado del docente Lennin Niño a: Activo', 'estudiantes_pnf', '2026-11-13 12:51:01');
INSERT INTO `historial` (`id`, `descripcion`, `usuario`, `fecha_hora`) VALUES ('9', 'Cambio de estado del docente Lennin Niño a: Inactivo', 'estudiantes_pnf', '2026-11-13 12:51:02');
INSERT INTO `historial` (`id`, `descripcion`, `usuario`, `fecha_hora`) VALUES ('10', 'Desvinculación del docente Lennin Niño del 6° Grado Sección A (2026-2027)', 'estudiantes_pnf', '2026-11-13 12:51:06');
INSERT INTO `historial` (`id`, `descripcion`, `usuario`, `fecha_hora`) VALUES ('11', 'Cambio de estado del docente Maria a: Inactivo', 'estudiantes_pnf', '2026-11-13 13:07:35');
INSERT INTO `historial` (`id`, `descripcion`, `usuario`, `fecha_hora`) VALUES ('12', 'Cambio de estado del docente Leonardo Mora a: Inactivo', 'estudiantes_pnf', '2026-11-13 13:07:37');
INSERT INTO `historial` (`id`, `descripcion`, `usuario`, `fecha_hora`) VALUES ('13', 'Cambio de estado del docente Jose Perez a: Inactivo', 'estudiantes_pnf', '2026-11-13 13:07:38');
INSERT INTO `historial` (`id`, `descripcion`, `usuario`, `fecha_hora`) VALUES ('14', 'Modificación de datos del docente: Maria al 6° Grado Sección A (2026-2027)', 'estudiantes_pnf', '2026-11-13 13:08:03');
INSERT INTO `historial` (`id`, `descripcion`, `usuario`, `fecha_hora`) VALUES ('15', 'Modificación de datos del docente: Leonardo Mora al 2° Grado Sección B (2026-2027)', 'estudiantes_pnf', '2026-11-13 13:08:51');
INSERT INTO `historial` (`id`, `descripcion`, `usuario`, `fecha_hora`) VALUES ('16', 'Modificación de datos del docente: Lennin Niño al 1° Grado Sección A (2026-2027)', 'estudiantes_pnf', '2026-11-13 13:09:01');
INSERT INTO `historial` (`id`, `descripcion`, `usuario`, `fecha_hora`) VALUES ('17', 'Cambio de estado del docente Maria a: Activo', 'estudiantes_pnf', '2026-11-13 13:09:08');
INSERT INTO `historial` (`id`, `descripcion`, `usuario`, `fecha_hora`) VALUES ('18', 'Cambio de estado del docente Lennin Niño a: Activo', 'estudiantes_pnf', '2026-11-13 13:09:19');
INSERT INTO `historial` (`id`, `descripcion`, `usuario`, `fecha_hora`) VALUES ('19', 'Cambio de estado del docente Leonardo Mora a: Activo', 'estudiantes_pnf', '2026-11-13 13:09:22');
INSERT INTO `historial` (`id`, `descripcion`, `usuario`, `fecha_hora`) VALUES ('20', 'Cierre de sesión de usuario', 'estudiantes_pnf', '2026-11-13 13:13:27');
INSERT INTO `historial` (`id`, `descripcion`, `usuario`, `fecha_hora`) VALUES ('21', 'Inicio de sesión exitoso', 'estudiantes_pnf', '2026-11-13 19:41:49');
INSERT INTO `historial` (`id`, `descripcion`, `usuario`, `fecha_hora`) VALUES ('22', 'Modificación de la configuración institucional', 'estudiantes_pnf', '2026-11-13 21:02:35');
INSERT INTO `historial` (`id`, `descripcion`, `usuario`, `fecha_hora`) VALUES ('23', 'Modificación de la configuración institucional', 'estudiantes_pnf', '2026-11-13 21:02:44');
INSERT INTO `historial` (`id`, `descripcion`, `usuario`, `fecha_hora`) VALUES ('24', 'Modificación de autoridad: Elsy Alarcón', 'estudiantes_pnf', '2026-11-13 21:08:43');
INSERT INTO `historial` (`id`, `descripcion`, `usuario`, `fecha_hora`) VALUES ('25', 'Cambio de estado del docente Jose Perez a: Activo', 'estudiantes_pnf', '2026-11-13 21:52:34');
INSERT INTO `historial` (`id`, `descripcion`, `usuario`, `fecha_hora`) VALUES ('26', 'Cambio de estado del docente Maria a: Inactivo', 'estudiantes_pnf', '2026-11-13 21:52:41');
INSERT INTO `historial` (`id`, `descripcion`, `usuario`, `fecha_hora`) VALUES ('27', 'Desvinculación de sección del docente Maria (6° Grado Sec. A)', 'estudiantes_pnf', '2026-11-13 21:52:50');
INSERT INTO `historial` (`id`, `descripcion`, `usuario`, `fecha_hora`) VALUES ('28', 'Cambio de estado del docente Maria a: Activo', 'estudiantes_pnf', '2026-11-13 21:56:49');
INSERT INTO `historial` (`id`, `descripcion`, `usuario`, `fecha_hora`) VALUES ('29', 'Modificación de datos del docente: Maria al 3° Grado Sección C (2026-2027)', 'estudiantes_pnf', '2026-11-13 21:57:34');
INSERT INTO `historial` (`id`, `descripcion`, `usuario`, `fecha_hora`) VALUES ('30', 'Desvinculación de sección del docente Maria (3° Grado Sec. C)', 'estudiantes_pnf', '2026-11-13 21:57:54');
INSERT INTO `historial` (`id`, `descripcion`, `usuario`, `fecha_hora`) VALUES ('31', 'Cierre de sesión de usuario', 'estudiantes_pnf', '2026-11-13 22:00:47');
INSERT INTO `historial` (`id`, `descripcion`, `usuario`, `fecha_hora`) VALUES ('32', 'Inicio de sesión exitoso', 'estudiantes_pnf', '2026-11-14 19:56:43');
INSERT INTO `historial` (`id`, `descripcion`, `usuario`, `fecha_hora`) VALUES ('33', 'Generación de respaldo de base de datos (respaldo_escuela_2026-11-14_19-56-57.sql)', 'estudiantes_pnf', '2026-11-14 19:57:01');
INSERT INTO `historial` (`id`, `descripcion`, `usuario`, `fecha_hora`) VALUES ('34', 'Consulta de planilla de inscripción para impresión del alumno: carlos  perez', 'estudiantes_pnf', '2026-11-14 20:24:27');
INSERT INTO `historial` (`id`, `descripcion`, `usuario`, `fecha_hora`) VALUES ('35', 'Consulta de planilla de inscripción para impresión del alumno: carlos  perez', 'estudiantes_pnf', '2026-11-14 20:24:41');
INSERT INTO `historial` (`id`, `descripcion`, `usuario`, `fecha_hora`) VALUES ('36', 'Consulta de planilla de inscripción para impresión del alumno: ana vegas mora cadenas', 'estudiantes_pnf', '2026-11-14 20:26:13');
INSERT INTO `historial` (`id`, `descripcion`, `usuario`, `fecha_hora`) VALUES ('37', 'Consulta de planilla de inscripción para impresión del alumno: pablo peres ', 'estudiantes_pnf', '2026-11-14 20:28:11');
INSERT INTO `historial` (`id`, `descripcion`, `usuario`, `fecha_hora`) VALUES ('38', 'Consulta de planilla de inscripción para impresión del alumno: pablo peres ', 'estudiantes_pnf', '2026-11-14 20:30:10');
INSERT INTO `historial` (`id`, `descripcion`, `usuario`, `fecha_hora`) VALUES ('39', 'Consulta de planilla de inscripción para impresión del alumno: ana vegas mora cadenas', 'estudiantes_pnf', '2026-11-14 20:59:42');
INSERT INTO `historial` (`id`, `descripcion`, `usuario`, `fecha_hora`) VALUES ('40', 'Generación de planilla de inscripción del alumno: carlos  perez', 'estudiantes_pnf', '2026-11-14 21:11:29');
INSERT INTO `historial` (`id`, `descripcion`, `usuario`, `fecha_hora`) VALUES ('41', 'Generación de planilla de inscripción del alumno: carlos  perez', 'estudiantes_pnf', '2026-11-14 21:12:27');
INSERT INTO `historial` (`id`, `descripcion`, `usuario`, `fecha_hora`) VALUES ('42', 'Generación de planilla de inscripción del alumno: carlos  perez', 'estudiantes_pnf', '2026-11-14 21:22:01');
INSERT INTO `historial` (`id`, `descripcion`, `usuario`, `fecha_hora`) VALUES ('43', 'Generación de planilla de inscripción del alumno: carlos  perez', 'estudiantes_pnf', '2026-11-14 21:23:50');
INSERT INTO `historial` (`id`, `descripcion`, `usuario`, `fecha_hora`) VALUES ('44', 'Generación de planilla de inscripción del alumno: carlos  perez', 'estudiantes_pnf', '2026-11-14 21:24:21');
INSERT INTO `historial` (`id`, `descripcion`, `usuario`, `fecha_hora`) VALUES ('45', 'Cierre de sesión de usuario', 'estudiantes_pnf', '2026-11-14 23:31:49');
INSERT INTO `historial` (`id`, `descripcion`, `usuario`, `fecha_hora`) VALUES ('46', 'Inicio de sesión exitoso', 'estudiantes_pnf', '2026-11-15 09:17:56');
INSERT INTO `historial` (`id`, `descripcion`, `usuario`, `fecha_hora`) VALUES ('47', 'Cierre de sesión de usuario', 'estudiantes_pnf', '2026-11-15 09:24:44');
INSERT INTO `historial` (`id`, `descripcion`, `usuario`, `fecha_hora`) VALUES ('48', 'Inicio de sesión exitoso', 'estudiantes_pnf', '2026-11-15 09:25:59');
INSERT INTO `historial` (`id`, `descripcion`, `usuario`, `fecha_hora`) VALUES ('49', 'Generación de respaldo de base de datos (respaldo_escuela_2026-11-15_09-26-06.sql)', 'estudiantes_pnf', '2026-11-15 09:26:06');
INSERT INTO `historial` (`id`, `descripcion`, `usuario`, `fecha_hora`) VALUES ('50', 'Inactivación de la configuración institucional', 'estudiantes_pnf', '2026-11-15 11:48:32');
INSERT INTO `historial` (`id`, `descripcion`, `usuario`, `fecha_hora`) VALUES ('51', 'Cierre de sesión de usuario', 'estudiantes_pnf', '2026-11-16 06:55:14');
INSERT INTO `historial` (`id`, `descripcion`, `usuario`, `fecha_hora`) VALUES ('52', 'Inicio de sesión exitoso', 'estudiantes_pnf', '2026-11-16 06:55:20');
INSERT INTO `historial` (`id`, `descripcion`, `usuario`, `fecha_hora`) VALUES ('53', 'Generación de respaldo de base de datos (respaldo_escuela_2026-11-16_06-55-27.sql)', 'estudiantes_pnf', '2026-11-16 06:55:27');
INSERT INTO `historial` (`id`, `descripcion`, `usuario`, `fecha_hora`) VALUES ('54', 'Cierre de sesión de usuario', 'estudiantes_pnf', '2026-11-16 13:52:10');
INSERT INTO `historial` (`id`, `descripcion`, `usuario`, `fecha_hora`) VALUES ('55', 'Inicio de sesión exitoso', 'estudiantes_pnf', '2026-11-16 14:20:32');
INSERT INTO `historial` (`id`, `descripcion`, `usuario`, `fecha_hora`) VALUES ('56', 'Generación de respaldo de base de datos (respaldo_escuela_2026-11-16_14-20-39.sql)', 'estudiantes_pnf', '2026-11-16 14:20:39');
INSERT INTO `historial` (`id`, `descripcion`, `usuario`, `fecha_hora`) VALUES ('57', 'Cierre de sesión de usuario', 'estudiantes_pnf', '2026-11-16 15:10:30');
INSERT INTO `historial` (`id`, `descripcion`, `usuario`, `fecha_hora`) VALUES ('58', 'Inicio de sesión exitoso', 'estudiantes_pnf', '2026-11-16 15:12:12');
INSERT INTO `historial` (`id`, `descripcion`, `usuario`, `fecha_hora`) VALUES ('59', 'Cierre de sesión de usuario', 'estudiantes_pnf', '2026-11-16 15:12:21');
INSERT INTO `historial` (`id`, `descripcion`, `usuario`, `fecha_hora`) VALUES ('60', 'Inicio de sesión exitoso', 'estudiantes_pnf', '2026-11-16 15:32:02');
INSERT INTO `historial` (`id`, `descripcion`, `usuario`, `fecha_hora`) VALUES ('61', 'Cierre de sesión de usuario', 'estudiantes_pnf', '2026-11-16 18:59:12');
INSERT INTO `historial` (`id`, `descripcion`, `usuario`, `fecha_hora`) VALUES ('62', 'Inicio de sesión exitoso', 'estudiantes_pnf', '2026-11-16 18:59:35');
INSERT INTO `historial` (`id`, `descripcion`, `usuario`, `fecha_hora`) VALUES ('63', 'Modificación de la configuración institucional', 'estudiantes_pnf', '2026-11-16 20:49:57');
INSERT INTO `historial` (`id`, `descripcion`, `usuario`, `fecha_hora`) VALUES ('64', 'Modificación de la configuración institucional', 'estudiantes_pnf', '2026-11-16 20:50:23');
INSERT INTO `historial` (`id`, `descripcion`, `usuario`, `fecha_hora`) VALUES ('65', 'Cierre de sesión de usuario', 'estudiantes_pnf', '2026-11-16 20:51:21');
INSERT INTO `historial` (`id`, `descripcion`, `usuario`, `fecha_hora`) VALUES ('66', 'Inicio de sesión exitoso', 'estudiantes_pnf', '2026-11-17 06:50:20');
INSERT INTO `historial` (`id`, `descripcion`, `usuario`, `fecha_hora`) VALUES ('67', 'Cierre de sesión de usuario', 'estudiantes_pnf', '2026-11-17 08:06:29');
INSERT INTO `historial` (`id`, `descripcion`, `usuario`, `fecha_hora`) VALUES ('68', 'Inicio de sesión exitoso', 'estudiantes_pnf', '2026-11-18 09:51:23');
INSERT INTO `historial` (`id`, `descripcion`, `usuario`, `fecha_hora`) VALUES ('69', 'Generación de respaldo de base de datos (respaldo_escuela_2026-11-18_10-48-20.sql)', 'estudiantes_pnf', '2026-11-18 10:48:20');
INSERT INTO `historial` (`id`, `descripcion`, `usuario`, `fecha_hora`) VALUES ('70', 'Cierre de sesión de usuario', 'Desconocido', '2026-11-18 12:31:13');
INSERT INTO `historial` (`id`, `descripcion`, `usuario`, `fecha_hora`) VALUES ('71', 'Inicio de sesión exitoso', 'estudiantes_pnf', '2026-11-18 13:21:05');
INSERT INTO `historial` (`id`, `descripcion`, `usuario`, `fecha_hora`) VALUES ('72', 'Modificación exitosa del alumno con cédula: V-1234567899999', 'estudiantes_pnf', '2026-11-18 13:24:07');
INSERT INTO `historial` (`id`, `descripcion`, `usuario`, `fecha_hora`) VALUES ('73', 'Generación de respaldo de base de datos (respaldo_escuela_2026-11-18_15-27-42.sql)', 'estudiantes_pnf', '2026-11-18 15:27:42');
INSERT INTO `historial` (`id`, `descripcion`, `usuario`, `fecha_hora`) VALUES ('74', 'Generación de respaldo de base de datos (respaldo_escuela_2026-11-18_15-31-27.sql)', 'estudiantes_pnf', '2026-11-18 15:31:27');
INSERT INTO `historial` (`id`, `descripcion`, `usuario`, `fecha_hora`) VALUES ('75', 'Modificación de la configuración institucional', 'estudiantes_pnf', '2026-11-18 16:05:39');
INSERT INTO `historial` (`id`, `descripcion`, `usuario`, `fecha_hora`) VALUES ('76', 'Cierre de sesión de usuario', 'estudiantes_pnf', '2026-11-18 17:02:17');
INSERT INTO `historial` (`id`, `descripcion`, `usuario`, `fecha_hora`) VALUES ('77', 'Inicio de sesión exitoso', 'estudiantes_pnf', '2026-11-18 17:11:56');
INSERT INTO `historial` (`id`, `descripcion`, `usuario`, `fecha_hora`) VALUES ('78', 'Modificación exitosa del alumno con cédula: V-28163342', 'estudiantes_pnf', '2026-11-18 17:13:20');
INSERT INTO `historial` (`id`, `descripcion`, `usuario`, `fecha_hora`) VALUES ('79', 'Modificación de la configuración institucional', 'estudiantes_pnf', '2026-11-18 17:23:12');
INSERT INTO `historial` (`id`, `descripcion`, `usuario`, `fecha_hora`) VALUES ('80', 'Cierre de sesión de usuario', 'estudiantes_pnf', '2026-11-18 18:15:01');
INSERT INTO `historial` (`id`, `descripcion`, `usuario`, `fecha_hora`) VALUES ('81', 'Inicio de sesión exitoso', 'estudiantes_pnf', '2026-11-18 18:15:48');
INSERT INTO `historial` (`id`, `descripcion`, `usuario`, `fecha_hora`) VALUES ('82', 'Modificación de la configuración institucional', 'estudiantes_pnf', '2026-11-18 18:31:02');
INSERT INTO `historial` (`id`, `descripcion`, `usuario`, `fecha_hora`) VALUES ('83', 'Registro del nuevo usuario: Admin (Estado: Activo)', 'estudiantes_pnf', '2026-11-18 22:38:35');
INSERT INTO `historial` (`id`, `descripcion`, `usuario`, `fecha_hora`) VALUES ('84', 'Cierre de sesión de usuario', 'estudiantes_pnf', '2026-11-18 22:41:40');
INSERT INTO `historial` (`id`, `descripcion`, `usuario`, `fecha_hora`) VALUES ('85', 'Inicio de sesión exitoso', 'Admin', '2026-11-18 22:42:05');
INSERT INTO `historial` (`id`, `descripcion`, `usuario`, `fecha_hora`) VALUES ('86', 'Registro de evento en calendario (Feriado): hhhhhhhhh', 'Admin', '2026-11-18 22:59:44');
INSERT INTO `historial` (`id`, `descripcion`, `usuario`, `fecha_hora`) VALUES ('87', 'Cierre de sesión de usuario', 'Admin', '2026-11-18 23:20:18');
INSERT INTO `historial` (`id`, `descripcion`, `usuario`, `fecha_hora`) VALUES ('88', 'Inicio de sesión exitoso', 'Admin', '2026-11-19 10:58:47');
INSERT INTO `historial` (`id`, `descripcion`, `usuario`, `fecha_hora`) VALUES ('89', 'Envío de Constancia por Correo (joseavendano@gmail.com) [CST-0003] - Estudiante: carlos perez (V-28163342)', 'Admin', '2026-11-19 14:38:46');
INSERT INTO `historial` (`id`, `descripcion`, `usuario`, `fecha_hora`) VALUES ('90', 'Reactivación del estudiante carlos  perez (Cédula: V-28163342)', 'Admin', '2026-11-19 15:21:59');
INSERT INTO `historial` (`id`, `descripcion`, `usuario`, `fecha_hora`) VALUES ('91', 'Impresión / Guardar PDF de Constancia de Estudio [CST-0004] - Estudiante: carlos perez (V-28163342)', 'Admin', '2026-11-19 15:24:49');
INSERT INTO `historial` (`id`, `descripcion`, `usuario`, `fecha_hora`) VALUES ('92', 'Envío de Constancia por Correo (joseavendano@gmail.com) [CST-0005] - Estudiante: carlos perez (V-28163342)', 'Admin', '2026-11-19 15:24:54');
INSERT INTO `historial` (`id`, `descripcion`, `usuario`, `fecha_hora`) VALUES ('93', 'Envío de Constancia por Correo (joseavendano@gmail.com) [CST-0006] - Estudiante: carlos perez (V-28163342)', 'Admin', '2026-11-19 15:28:43');
INSERT INTO `historial` (`id`, `descripcion`, `usuario`, `fecha_hora`) VALUES ('94', 'Reactivación del estudiante carlos  perez (Cédula: V-28163342)', 'Admin', '2026-11-19 16:07:23');
INSERT INTO `historial` (`id`, `descripcion`, `usuario`, `fecha_hora`) VALUES ('95', 'Archivado del estudiante carlos  perez (Cédula: V-28163342, 6° Grado Sec. A)', 'Admin', '2026-11-19 16:08:05');
INSERT INTO `historial` (`id`, `descripcion`, `usuario`, `fecha_hora`) VALUES ('96', 'Desarchivado del estudiante carlos  perez (Cédula: V-28163342)', 'Admin', '2026-11-19 16:09:01');
INSERT INTO `historial` (`id`, `descripcion`, `usuario`, `fecha_hora`) VALUES ('97', 'Archivado del estudiante carlos  perez (Cédula: V-28163342, 6° Grado Sec. A)', 'Admin', '2026-11-19 16:09:27');
INSERT INTO `historial` (`id`, `descripcion`, `usuario`, `fecha_hora`) VALUES ('98', 'Desarchivado del estudiante carlos  perez (Cédula: V-28163342)', 'Admin', '2026-11-19 16:09:36');
INSERT INTO `historial` (`id`, `descripcion`, `usuario`, `fecha_hora`) VALUES ('99', 'Retiro del estudiante carlos  perez (Cédula: V-28163342) - Motivo: Retiro institucional', 'Admin', '2026-11-19 16:09:44');


CREATE TABLE `historial_constancias` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `codigo` varchar(25) COLLATE utf8mb4_spanish_ci NOT NULL,
  `accion` varchar(120) COLLATE utf8mb4_spanish_ci NOT NULL,
  `estudiante` varchar(150) COLLATE utf8mb4_spanish_ci NOT NULL,
  `cedula_escolar` varchar(25) COLLATE utf8mb4_spanish_ci NOT NULL,
  `usuario` varchar(80) COLLATE utf8mb4_spanish_ci NOT NULL,
  `rol` varchar(50) COLLATE utf8mb4_spanish_ci NOT NULL,
  `fecha_hora` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

INSERT INTO `historial_constancias` (`id`, `codigo`, `accion`, `estudiante`, `cedula_escolar`, `usuario`, `rol`, `fecha_hora`) VALUES ('1', 'CST-0001', 'Impresión / Guardar PDF de Constancia de Estudio', 'carlos perez', 'V-28163342', 'estudiantes_pnf', 'Administrador', '2026-11-15 09:20:12');
INSERT INTO `historial_constancias` (`id`, `codigo`, `accion`, `estudiante`, `cedula_escolar`, `usuario`, `rol`, `fecha_hora`) VALUES ('2', 'CST-0002', 'Impresión / Guardar PDF de Constancia de Estudio', 'carlos perez', 'V-28163342', 'estudiantes_pnf', 'Administrador', '2026-11-18 13:24:56');
INSERT INTO `historial_constancias` (`id`, `codigo`, `accion`, `estudiante`, `cedula_escolar`, `usuario`, `rol`, `fecha_hora`) VALUES ('3', 'CST-0003', 'Envío de Constancia por Correo (joseavendano@gmail.com)', 'carlos perez', 'V-28163342', 'Admin', 'Administrador', '2026-11-19 14:38:46');
INSERT INTO `historial_constancias` (`id`, `codigo`, `accion`, `estudiante`, `cedula_escolar`, `usuario`, `rol`, `fecha_hora`) VALUES ('4', 'CST-0004', 'Impresión / Guardar PDF de Constancia de Estudio', 'carlos perez', 'V-28163342', 'Admin', 'Administrador', '2026-11-19 15:24:49');
INSERT INTO `historial_constancias` (`id`, `codigo`, `accion`, `estudiante`, `cedula_escolar`, `usuario`, `rol`, `fecha_hora`) VALUES ('5', 'CST-0005', 'Envío de Constancia por Correo (joseavendano@gmail.com)', 'carlos perez', 'V-28163342', 'Admin', 'Administrador', '2026-11-19 15:24:54');
INSERT INTO `historial_constancias` (`id`, `codigo`, `accion`, `estudiante`, `cedula_escolar`, `usuario`, `rol`, `fecha_hora`) VALUES ('6', 'CST-0006', 'Envío de Constancia por Correo (joseavendano@gmail.com)', 'carlos perez', 'V-28163342', 'Admin', 'Administrador', '2026-11-19 15:28:43');


CREATE TABLE `historial_planillas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `codigo` varchar(25) COLLATE utf8mb4_spanish_ci NOT NULL,
  `accion` varchar(120) COLLATE utf8mb4_spanish_ci NOT NULL,
  `estudiante` varchar(150) COLLATE utf8mb4_spanish_ci NOT NULL,
  `cedula_escolar` varchar(25) COLLATE utf8mb4_spanish_ci NOT NULL,
  `usuario` varchar(80) COLLATE utf8mb4_spanish_ci NOT NULL,
  `rol` varchar(50) COLLATE utf8mb4_spanish_ci NOT NULL,
  `fecha_hora` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

INSERT INTO `historial_planillas` (`id`, `codigo`, `accion`, `estudiante`, `cedula_escolar`, `usuario`, `rol`, `fecha_hora`) VALUES ('1', 'PLN-0001', 'Impresión / Guardar PDF de Planilla de Inscripción', 'carlos  perez', 'V-28163342', 'estudiantes_pnf', 'Administrador', '2026-11-15 09:19:08');
INSERT INTO `historial_planillas` (`id`, `codigo`, `accion`, `estudiante`, `cedula_escolar`, `usuario`, `rol`, `fecha_hora`) VALUES ('2', 'PLN-0002', 'Impresión / Guardar PDF de Planilla de Inscripción', 'carlos  perez', 'V-28163342', 'estudiantes_pnf', 'Administrador', '2026-11-18 09:54:49');
INSERT INTO `historial_planillas` (`id`, `codigo`, `accion`, `estudiante`, `cedula_escolar`, `usuario`, `rol`, `fecha_hora`) VALUES ('3', 'PLN-0003', 'Impresión / Guardar PDF de Planilla de Inscripción', 'carlos  perez', 'V-28163342', 'estudiantes_pnf', 'Administrador', '2026-11-18 09:55:13');
INSERT INTO `historial_planillas` (`id`, `codigo`, `accion`, `estudiante`, `cedula_escolar`, `usuario`, `rol`, `fecha_hora`) VALUES ('4', 'PLN-0004', 'Impresión / Guardar PDF de Planilla de Inscripción', 'leonardo mora cadenas', 'V-30619094', 'estudiantes_pnf', 'Administrador', '2026-11-18 10:13:33');


CREATE TABLE `inscripcion` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cédula_escolar_estudiante` varchar(15) COLLATE utf8mb4_spanish_ci NOT NULL,
  `año_escolar` varchar(9) COLLATE utf8mb4_spanish_ci NOT NULL,
  `id_grado` int(11) NOT NULL,
  `id_seccion` int(11) NOT NULL,
  `tipo_estudiante` varchar(30) COLLATE utf8mb4_spanish_ci NOT NULL,
  `plantel_procedencia` varchar(100) COLLATE utf8mb4_spanish_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_estudiante_inscripcion` (`cédula_escolar_estudiante`),
  KEY `fk_grado_inscripcion` (`id_grado`),
  KEY `fk_seccion_inscripcion` (`id_seccion`),
  CONSTRAINT `fk_estudiante_inscripcion` FOREIGN KEY (`cédula_escolar_estudiante`) REFERENCES `estudiante` (`cédula_escolar`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_grado_inscripcion` FOREIGN KEY (`id_grado`) REFERENCES `grados` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_seccion_inscripcion` FOREIGN KEY (`id_seccion`) REFERENCES `secciones` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

INSERT INTO `inscripcion` (`id`, `cédula_escolar_estudiante`, `año_escolar`, `id_grado`, `id_seccion`, `tipo_estudiante`, `plantel_procedencia`) VALUES ('10', 'V-28163342', '2026-2029', '6', '1', 'Retirado', '');


CREATE TABLE `madre` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cédula_escolar_estudiante` varchar(15) COLLATE utf8mb4_spanish_ci NOT NULL,
  `nombres` varchar(60) COLLATE utf8mb4_spanish_ci DEFAULT NULL,
  `apellidos` varchar(60) COLLATE utf8mb4_spanish_ci DEFAULT NULL,
  `cédula_identidad` varchar(15) COLLATE utf8mb4_spanish_ci DEFAULT NULL,
  `teléfono` varchar(20) COLLATE utf8mb4_spanish_ci DEFAULT NULL,
  `fecha_nacimiento` date DEFAULT NULL,
  `estado_civil` varchar(20) COLLATE utf8mb4_spanish_ci DEFAULT NULL,
  `lugar_nacimiento` varchar(100) COLLATE utf8mb4_spanish_ci DEFAULT NULL,
  `correo` varchar(100) COLLATE utf8mb4_spanish_ci DEFAULT NULL,
  `dirección_habitación` varchar(200) COLLATE utf8mb4_spanish_ci DEFAULT NULL,
  `nivel_instrucción` varchar(50) COLLATE utf8mb4_spanish_ci DEFAULT NULL,
  `profesion` varchar(60) COLLATE utf8mb4_spanish_ci DEFAULT NULL,
  `ocupación` varchar(60) COLLATE utf8mb4_spanish_ci DEFAULT NULL,
  `empresa` varchar(100) COLLATE utf8mb4_spanish_ci DEFAULT NULL,
  `teléfono_empresa` varchar(20) COLLATE utf8mb4_spanish_ci DEFAULT NULL,
  `dirección_empresa` varchar(200) COLLATE utf8mb4_spanish_ci DEFAULT NULL,
  `nacionalidad` varchar(1) COLLATE utf8mb4_spanish_ci DEFAULT 'V',
  PRIMARY KEY (`id`),
  KEY `fk_estudiante_madre` (`cédula_escolar_estudiante`),
  CONSTRAINT `fk_estudiante_madre` FOREIGN KEY (`cédula_escolar_estudiante`) REFERENCES `estudiante` (`cédula_escolar`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

INSERT INTO `madre` (`id`, `cédula_escolar_estudiante`, `nombres`, `apellidos`, `cédula_identidad`, `teléfono`, `fecha_nacimiento`, `estado_civil`, `lugar_nacimiento`, `correo`, `dirección_habitación`, `nivel_instrucción`, `profesion`, `ocupación`, `empresa`, `teléfono_empresa`, `dirección_empresa`, `nacionalidad`) VALUES ('9', 'V-28163342', 'rosa cadenas', '', 'V-232424242', '', NULL, '', '', 'joseavendano@gmail.com', '', '', 'ama de casa', '', '', '', '', 'V');


CREATE TABLE `medicamento_contraindicado` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cédula_escolar_estudiante` varchar(15) COLLATE utf8mb4_spanish_ci NOT NULL,
  `nombre_medicamento` varchar(100) COLLATE utf8mb4_spanish_ci NOT NULL,
  `observación` varchar(200) COLLATE utf8mb4_spanish_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_estudiante_medicamento_contraindicado` (`cédula_escolar_estudiante`),
  CONSTRAINT `fk_estudiante_medicamento_contraindicado` FOREIGN KEY (`cédula_escolar_estudiante`) REFERENCES `estudiante` (`cédula_escolar`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;



CREATE TABLE `padre` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cédula_escolar_estudiante` varchar(15) COLLATE utf8mb4_spanish_ci NOT NULL,
  `nombres` varchar(60) COLLATE utf8mb4_spanish_ci DEFAULT NULL,
  `apellidos` varchar(60) COLLATE utf8mb4_spanish_ci DEFAULT NULL,
  `cédula_identidad` varchar(15) COLLATE utf8mb4_spanish_ci DEFAULT NULL,
  `teléfono` varchar(20) COLLATE utf8mb4_spanish_ci DEFAULT NULL,
  `fecha_nacimiento` date DEFAULT NULL,
  `estado_civil` varchar(20) COLLATE utf8mb4_spanish_ci DEFAULT NULL,
  `lugar_nacimiento` varchar(100) COLLATE utf8mb4_spanish_ci DEFAULT NULL,
  `correo` varchar(100) COLLATE utf8mb4_spanish_ci DEFAULT NULL,
  `dirección_habitación` varchar(200) COLLATE utf8mb4_spanish_ci DEFAULT NULL,
  `nivel_instrucción` varchar(50) COLLATE utf8mb4_spanish_ci DEFAULT NULL,
  `profesion` varchar(60) COLLATE utf8mb4_spanish_ci DEFAULT NULL,
  `ocupación` varchar(60) COLLATE utf8mb4_spanish_ci DEFAULT NULL,
  `empresa` varchar(100) COLLATE utf8mb4_spanish_ci DEFAULT NULL,
  `teléfono_empresa` varchar(20) COLLATE utf8mb4_spanish_ci DEFAULT NULL,
  `dirección_empresa` varchar(200) COLLATE utf8mb4_spanish_ci DEFAULT NULL,
  `nacionalidad` varchar(1) COLLATE utf8mb4_spanish_ci DEFAULT 'V',
  PRIMARY KEY (`id`),
  KEY `fk_estudiante_padre` (`cédula_escolar_estudiante`),
  CONSTRAINT `fk_estudiante_padre` FOREIGN KEY (`cédula_escolar_estudiante`) REFERENCES `estudiante` (`cédula_escolar`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;



CREATE TABLE `problema_comunidad` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cédula_escolar_estudiante` varchar(15) COLLATE utf8mb4_spanish_ci NOT NULL,
  `descripción` text COLLATE utf8mb4_spanish_ci,
  PRIMARY KEY (`id`),
  KEY `fk_estudiante_problema_comunidad` (`cédula_escolar_estudiante`),
  CONSTRAINT `fk_estudiante_problema_comunidad` FOREIGN KEY (`cédula_escolar_estudiante`) REFERENCES `estudiante` (`cédula_escolar`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;



CREATE TABLE `representante` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cédula_escolar_estudiante` varchar(15) COLLATE utf8mb4_spanish_ci NOT NULL,
  `nombres` varchar(60) COLLATE utf8mb4_spanish_ci NOT NULL,
  `apellidos` varchar(60) COLLATE utf8mb4_spanish_ci NOT NULL,
  `cédula_identidad` varchar(15) COLLATE utf8mb4_spanish_ci NOT NULL,
  `fecha_nacimiento` date DEFAULT NULL,
  `rif` varchar(15) COLLATE utf8mb4_spanish_ci DEFAULT NULL,
  `correo_electrónico` varchar(100) COLLATE utf8mb4_spanish_ci DEFAULT NULL,
  `teléfono_celular` varchar(20) COLLATE utf8mb4_spanish_ci NOT NULL,
  `teléfono_residencia` varchar(20) COLLATE utf8mb4_spanish_ci DEFAULT NULL,
  `dirección_habitación` varchar(200) COLLATE utf8mb4_spanish_ci DEFAULT NULL,
  `nivel_instrucción` varchar(50) COLLATE utf8mb4_spanish_ci DEFAULT NULL,
  `profesion` varchar(60) COLLATE utf8mb4_spanish_ci DEFAULT NULL,
  `ocupación` varchar(60) COLLATE utf8mb4_spanish_ci DEFAULT NULL,
  `empresa` varchar(100) COLLATE utf8mb4_spanish_ci DEFAULT NULL,
  `teléfono_empresa` varchar(20) COLLATE utf8mb4_spanish_ci DEFAULT NULL,
  `dirección_empresa` varchar(200) COLLATE utf8mb4_spanish_ci DEFAULT NULL,
  `parentesco` varchar(30) COLLATE utf8mb4_spanish_ci NOT NULL,
  `autorización_legal` tinyint(1) DEFAULT '0',
  `número_poder_notariado` varchar(30) COLLATE utf8mb4_spanish_ci DEFAULT NULL,
  `nacionalidad` varchar(1) COLLATE utf8mb4_spanish_ci NOT NULL DEFAULT 'V',
  PRIMARY KEY (`id`),
  KEY `fk_estudiante_representante` (`cédula_escolar_estudiante`),
  CONSTRAINT `fk_estudiante_representante` FOREIGN KEY (`cédula_escolar_estudiante`) REFERENCES `estudiante` (`cédula_escolar`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

INSERT INTO `representante` (`id`, `cédula_escolar_estudiante`, `nombres`, `apellidos`, `cédula_identidad`, `fecha_nacimiento`, `rif`, `correo_electrónico`, `teléfono_celular`, `teléfono_residencia`, `dirección_habitación`, `nivel_instrucción`, `profesion`, `ocupación`, `empresa`, `teléfono_empresa`, `dirección_empresa`, `parentesco`, `autorización_legal`, `número_poder_notariado`, `nacionalidad`) VALUES ('11', 'V-28163342', 'rosa cadenas', '', 'V-232424242', NULL, '', 'joseavendano@gmail.com', '', '', '', '', 'ama de casa', '', '', '', '', 'Mamá', '0', '', 'V');


CREATE TABLE `retirados` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cedula` varchar(15) COLLATE utf8mb4_spanish_ci NOT NULL,
  `nombres` varchar(100) COLLATE utf8mb4_spanish_ci DEFAULT NULL,
  `apellidos` varchar(100) COLLATE utf8mb4_spanish_ci DEFAULT NULL,
  `motivo` text COLLATE utf8mb4_spanish_ci,
  `grado` varchar(30) COLLATE utf8mb4_spanish_ci DEFAULT NULL,
  `seccion` varchar(30) COLLATE utf8mb4_spanish_ci DEFAULT NULL,
  `periodo_escolar` varchar(20) COLLATE utf8mb4_spanish_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_retirados_cedula` (`cedula`),
  CONSTRAINT `fk_estudiante_retirados` FOREIGN KEY (`cedula`) REFERENCES `estudiante` (`cédula_escolar`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

INSERT INTO `retirados` (`id`, `cedula`, `nombres`, `apellidos`, `motivo`, `grado`, `seccion`, `periodo_escolar`) VALUES ('1', 'V-28163342', 'carlos ', 'perez', 'Retiro institucional', '6', 'A', '2026-2027');


CREATE TABLE `secciones` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre_seccion` varchar(10) COLLATE utf8mb4_spanish_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

INSERT INTO `secciones` (`id`, `nombre_seccion`) VALUES ('1', 'A');
INSERT INTO `secciones` (`id`, `nombre_seccion`) VALUES ('2', 'B');
INSERT INTO `secciones` (`id`, `nombre_seccion`) VALUES ('3', 'C');
INSERT INTO `secciones` (`id`, `nombre_seccion`) VALUES ('4', 'D');
INSERT INTO `secciones` (`id`, `nombre_seccion`) VALUES ('5', 'Única');


CREATE TABLE `traslado_estudiante` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cédula_escolar_estudiante` varchar(15) COLLATE utf8mb4_spanish_ci NOT NULL,
  `tipo` varchar(10) COLLATE utf8mb4_spanish_ci NOT NULL,
  `modalidad` varchar(15) COLLATE utf8mb4_spanish_ci NOT NULL,
  `parentesco_persona` varchar(30) COLLATE utf8mb4_spanish_ci DEFAULT NULL,
  `nombres_apellidos_persona` varchar(120) COLLATE utf8mb4_spanish_ci DEFAULT NULL,
  `cédula_persona` varchar(15) COLLATE utf8mb4_spanish_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_estudiante_traslado_estudiante` (`cédula_escolar_estudiante`),
  CONSTRAINT `fk_estudiante_traslado_estudiante` FOREIGN KEY (`cédula_escolar_estudiante`) REFERENCES `estudiante` (`cédula_escolar`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

INSERT INTO `traslado_estudiante` (`id`, `cédula_escolar_estudiante`, `tipo`, `modalidad`, `parentesco_persona`, `nombres_apellidos_persona`, `cédula_persona`) VALUES ('6', 'V-28163342', 'Entrada', 'Solo', '', '', '');


CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario` varchar(50) COLLATE utf8mb4_spanish_ci NOT NULL,
  `clave` varchar(255) COLLATE utf8mb4_spanish_ci NOT NULL,
  `nombre` varchar(100) COLLATE utf8mb4_spanish_ci DEFAULT NULL,
  `rol` varchar(50) COLLATE utf8mb4_spanish_ci NOT NULL DEFAULT 'Administrador',
  `correo` varchar(100) COLLATE utf8mb4_spanish_ci DEFAULT '',
  `pin_seguridad` varchar(100) COLLATE utf8mb4_spanish_ci DEFAULT '',
  `estado` varchar(20) COLLATE utf8mb4_spanish_ci DEFAULT 'Activo',
  PRIMARY KEY (`id`),
  UNIQUE KEY `usuario` (`usuario`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

INSERT INTO `usuarios` (`id`, `usuario`, `clave`, `nombre`, `rol`, `correo`, `pin_seguridad`, `estado`) VALUES ('13', 'Admin', '$2y$10$ON4jM4.A188RWT9RMXGAWO3NarP5z3y7skFJSvW0d5RDM8r/uU36y', NULL, 'Administrador', 'admin_escuela@gmil.com', '2026', 'Activo');


CREATE TABLE `vacuna` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cédula_escolar_estudiante` varchar(15) COLLATE utf8mb4_spanish_ci NOT NULL,
  `nombre_vacuna` varchar(80) COLLATE utf8mb4_spanish_ci NOT NULL,
  `fecha_aplicación` date DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_estudiante_vacuna` (`cédula_escolar_estudiante`),
  CONSTRAINT `fk_estudiante_vacuna` FOREIGN KEY (`cédula_escolar_estudiante`) REFERENCES `estudiante` (`cédula_escolar`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;



CREATE TABLE `vivienda` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cédula_escolar_estudiante` varchar(15) COLLATE utf8mb4_spanish_ci NOT NULL,
  `condición_vivienda` varchar(20) COLLATE utf8mb4_spanish_ci NOT NULL,
  `tipo_vivienda` varchar(20) COLLATE utf8mb4_spanish_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_estudiante_vivienda` (`cédula_escolar_estudiante`),
  CONSTRAINT `fk_estudiante_vivienda` FOREIGN KEY (`cédula_escolar_estudiante`) REFERENCES `estudiante` (`cédula_escolar`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

INSERT INTO `vivienda` (`id`, `cédula_escolar_estudiante`, `condición_vivienda`, `tipo_vivienda`) VALUES ('11', 'V-28163342', 'Propia', 'Casa');
