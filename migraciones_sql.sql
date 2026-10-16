-- ---------------------------------------------------------------------------
-- 2026_09_22_090000_create_encuestas_table.php
-- Encuestas por salón vía QR (Gestión Humana): catálogo de encuestas.
-- ---------------------------------------------------------------------------
CREATE TABLE `encuestas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `titulo` varchar(255) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `activo` tinyint(4) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- 2026_09_22_090100_create_encuestas_preguntas_table.php
-- Preguntas de una encuesta. id_tipo_pregunta reusa el catálogo de Evaluaciones
-- (evaluaciones_tipos_pregunta) SIN FK real: esa tabla es MyISAM (legacy) y esta
-- es InnoDB — MySQL no permite una FK InnoDB -> MyISAM (error 150).
-- ---------------------------------------------------------------------------
CREATE TABLE `encuestas_preguntas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_encuesta` int(11) NOT NULL,
  `id_tipo_pregunta` int(11) NOT NULL,
  `texto` varchar(255) NOT NULL,
  `obligatoria` tinyint(4) NOT NULL DEFAULT 1,
  `orden` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_enc_preguntas_encuesta` (`id_encuesta`),
  CONSTRAINT `fk_enc_preguntas_encuesta` FOREIGN KEY (`id_encuesta`) REFERENCES `encuestas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- 2026_09_22_090200_create_encuestas_opciones_pregunta_table.php
-- Opciones de una pregunta de encuesta (sin puntaje — a diferencia de
-- evaluaciones_opciones_pregunta, aquí solo importa el conteo por opción).
-- ---------------------------------------------------------------------------
CREATE TABLE `encuestas_opciones_pregunta` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_pregunta` int(11) NOT NULL,
  `texto` varchar(255) NOT NULL,
  `orden` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `fk_enc_opciones_pregunta` (`id_pregunta`),
  CONSTRAINT `fk_enc_opciones_pregunta` FOREIGN KEY (`id_pregunta`) REFERENCES `encuestas_preguntas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- 2026_09_22_090300_create_encuestas_salon_table.php
-- Encuesta activa de cada salón (una fila por salón, no historial) + token del
-- link/QR público. token_publico identifica el salón en la URL pública — no su
-- id, para no permitir enumerar salones desde una URL adivinada.
-- ---------------------------------------------------------------------------
CREATE TABLE `encuestas_salon` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_salon` int(11) NOT NULL,
  `id_encuesta` int(11) DEFAULT NULL,
  `token_publico` varchar(64) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `encuestas_salon_token_publico_unique` (`token_publico`),
  KEY `fk_enc_salon_salon` (`id_salon`),
  KEY `fk_enc_salon_encuesta` (`id_encuesta`),
  CONSTRAINT `fk_enc_salon_encuesta` FOREIGN KEY (`id_encuesta`) REFERENCES `encuestas` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_enc_salon_salon` FOREIGN KEY (`id_salon`) REFERENCES `salones` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- 2026_09_22_090400_create_encuestas_respuestas_table.php
-- Respuesta anónima a una encuesta desde un salón: sin id_user/IP, por diseño.
-- ---------------------------------------------------------------------------
CREATE TABLE `encuestas_respuestas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_encuesta` int(11) NOT NULL,
  `id_salon` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_enc_respuestas_encuesta` (`id_encuesta`),
  KEY `fk_enc_respuestas_salon` (`id_salon`),
  CONSTRAINT `fk_enc_respuestas_encuesta` FOREIGN KEY (`id_encuesta`) REFERENCES `encuestas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_enc_respuestas_salon` FOREIGN KEY (`id_salon`) REFERENCES `salones` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- 2026_09_22_090500_create_encuestas_respuestas_pregunta_table.php
-- Fila por pregunta respondida (y por opción marcada, en selección múltiple).
-- id_pregunta/id_opcion son ON DELETE CASCADE (no la RESTRICT por defecto):
-- borrar una encuesta cascada por dos caminos a la vez hasta acá (via
-- encuestas_preguntas->encuestas_opciones_pregunta y via encuestas_respuestas) —
-- sin este cascade, eliminar una encuesta con respuestas ya registradas fallaba
-- con error 1451 (verificado con una prueba end-to-end real antes de este fix).
-- ---------------------------------------------------------------------------
CREATE TABLE `encuestas_respuestas_pregunta` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_respuesta` int(11) NOT NULL,
  `id_pregunta` int(11) NOT NULL,
  `id_opcion` int(11) DEFAULT NULL,
  `valor_texto` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_enc_resp_pregunta_respuesta` (`id_respuesta`),
  KEY `fk_enc_resp_pregunta_pregunta` (`id_pregunta`),
  KEY `fk_enc_resp_pregunta_opcion` (`id_opcion`),
  CONSTRAINT `fk_enc_resp_pregunta_opcion` FOREIGN KEY (`id_opcion`) REFERENCES `encuestas_opciones_pregunta` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_enc_resp_pregunta_pregunta` FOREIGN KEY (`id_pregunta`) REFERENCES `encuestas_preguntas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_enc_resp_pregunta_respuesta` FOREIGN KEY (`id_respuesta`) REFERENCES `encuestas_respuestas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- 2026_09_22_090600_seed_opciones_encuestas.php
-- Opciones de permiso del módulo Encuestas (módulo 3 = Gestión Humana) +
-- otorgamiento inmediato a Super Admin (1) / Administrador (2) / Gestión
-- Humana (8) en cron_permisos, para no repetir el incidente de "Metricas
-- Asistencias" (opción creada pero nunca otorgada, invisible para todos).
-- Ids reales asignados al correr esta migración en la BD local (sami_royal):
-- 126 = "Encuestas — Administrar encuestas y salones"
-- 127 = "Encuestas — Ver resultados"
-- (verificar contra cron_opciones antes de asumir estos mismos ids en otra BD)
-- ---------------------------------------------------------------------------
INSERT INTO `cron_opciones` (`nombre`, `id_modulo`, `activo`, `fechareg`) VALUES
  ('Encuestas — Administrar encuestas y salones', 3, 1, NOW());
SET @id_opcion_admin = LAST_INSERT_ID();

INSERT INTO `cron_opciones` (`nombre`, `id_modulo`, `activo`, `fechareg`) VALUES
  ('Encuestas — Ver resultados', 3, 1, NOW());
SET @id_opcion_ver = LAST_INSERT_ID();

INSERT INTO `cron_permisos` (`id_opcion`, `id_perfil`, `activo`, `fechareg`) VALUES
  (@id_opcion_admin, 1, 1, NOW()),
  (@id_opcion_admin, 2, 1, NOW()),
  (@id_opcion_admin, 8, 1, NOW()),
  (@id_opcion_ver, 1, 1, NOW()),
  (@id_opcion_ver, 2, 1, NOW()),
  (@id_opcion_ver, 8, 1, NOW());

-- ---------------------------------------------------------------------------
-- 2026_09_22_100000_fix_ids_opciones_encuestas.php
-- Renumera las dos opciones de Encuestas para que coincidan con los ids reales
-- de producción (129/130 — confirmado por el usuario), donde ya existían de
-- forma independiente. cron_opciones/cron_permisos no tienen FK declarada entre
-- sí, así que el UPDATE directo del id es seguro.
-- ---------------------------------------------------------------------------
UPDATE `cron_permisos` SET `id_opcion` = 129 WHERE `id_opcion` = 126;
UPDATE `cron_opciones` SET `id` = 129 WHERE `id` = 126;

UPDATE `cron_permisos` SET `id_opcion` = 130 WHERE `id_opcion` = 127;
UPDATE `cron_opciones` SET `id` = 130 WHERE `id` = 127;

-- ---------------------------------------------------------------------------
-- 2026_09_22_110000_add_id_reserva_to_encuestas_respuestas_table.php
-- Reserva del salón, del mismo día, a la que el visitante asocia su respuesta
-- antes de contestar la encuesta (ver EncuestasServices::reservasHoySalon /
-- responderPublica). FK real: `reservas` es InnoDB.
-- ---------------------------------------------------------------------------
ALTER TABLE `encuestas_respuestas`
  ADD COLUMN `id_reserva` int(11) DEFAULT NULL AFTER `id_salon`,
  ADD KEY `fk_enc_respuestas_reserva` (`id_reserva`),
  ADD CONSTRAINT `fk_enc_respuestas_reserva` FOREIGN KEY (`id_reserva`) REFERENCES `reservas` (`id`) ON DELETE SET NULL;

-- ---------------------------------------------------------------------------
-- 2026_09_23_100000_seed_opcion_vinculacion_estudiante_padre.php
-- Opción propia del módulo "Vinculación Estudiante - Padre" (Académico, módulo
-- 14) — VinculacionAcudienteController. No se reutiliza la 73 ("Estudiantes",
-- legacy Matricula) porque está otorgada al perfil Acudiente (6). Solo Super
-- Admin por defecto. Id real asignado en esta BD: 131.
-- ---------------------------------------------------------------------------
INSERT INTO `cron_opciones` (`nombre`, `id_modulo`, `activo`, `fechareg`)
VALUES ('Vinculación Estudiante - Padre', 14, 1, NOW());
SET @id_opcion_vinculacion = LAST_INSERT_ID();

INSERT INTO `cron_permisos` (`id_opcion`, `id_perfil`, `activo`, `fechareg`)
VALUES (@id_opcion_vinculacion, 1, 1, NOW());

-- ---------------------------------------------------------------------------
-- 2026_10_14_100000_create_academico_observacion_estudiante_table.php
-- Gestión Convivencial: observaciones de estudiantes por día (POSITIVA/NEGATIVA),
-- agrupadas en actas por periodo académico (ObservacionEstudianteService). Cada
-- fila es de quien la registró (id_docente). id_curso = curso del estudiante al
-- registrarla (las actas pasadas conservan el curso de entonces).
-- id_periodo_academico e id_asignatura SIN FK: periodo_academico y
-- academico_asignatura son MyISAM (no admiten llaves foráneas); los valida el
-- servicio. FK reales solo hacia usuarios y curso (InnoDB).
-- ---------------------------------------------------------------------------
create table `academico_observacion_estudiante` (`id` int not null auto_increment, `id_estudiante` int not null, `id_periodo_academico` int not null, `id_docente` int not null, `id_asignatura` int null, `id_curso` int null, `fecha` date not null, `tipo` varchar(10) not null, `motivo` text not null, `created_at` timestamp null, `updated_at` timestamp null, primary key (`id`)) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
alter table `academico_observacion_estudiante` add constraint `academico_observacion_estudiante_id_estudiante_foreign` foreign key (`id_estudiante`) references `usuarios` (`id_user`) on delete cascade;
alter table `academico_observacion_estudiante` add constraint `academico_observacion_estudiante_id_docente_foreign` foreign key (`id_docente`) references `usuarios` (`id_user`);
alter table `academico_observacion_estudiante` add constraint `academico_observacion_estudiante_id_curso_foreign` foreign key (`id_curso`) references `curso` (`id`) on delete set null;
alter table `academico_observacion_estudiante` add index `ix_obs_estudiante_periodo`(`id_estudiante`, `id_periodo_academico`);
alter table `academico_observacion_estudiante` add index `ix_obs_fecha`(`fecha`);
alter table `academico_observacion_estudiante` add index `academico_observacion_estudiante_id_periodo_academico_index`(`id_periodo_academico`);

-- ---------------------------------------------------------------------------
-- 2026_10_14_100100_seed_opcion_observaciones_estudiantes.php
-- Módulo "Gestión Convivencial" (cron_modulos.id 15) y sus opciones, con ids
-- explícitos (la migración falla si alguno ya está ocupado por otra cosa):
--   133 "Observaciones de estudiantes" — registrar a cualquier estudiante.
--   134 "Ver todas las observaciones"  — ver las de todos los profesores.
-- Otorgadas a Super Admin (1), Psicóloga Preescolar/Primaria/Secundaria
-- (21/24/25) y Coordinador (26). La migración solo inserta lo que falte (no
-- duplica módulo/opción/permiso si ya existen).
-- ---------------------------------------------------------------------------
INSERT INTO `cron_modulos` (`id`, `nombre`, `activo`, `fechareg`)
VALUES (15, 'Gestión Convivencial', 1, NOW());

INSERT INTO `cron_opciones` (`id`, `nombre`, `id_modulo`, `activo`, `fechareg`) VALUES
  (133, 'Observaciones de estudiantes', 15, 1, NOW()),
  (134, 'Ver todas las observaciones', 15, 1, NOW());

INSERT INTO `cron_permisos` (`id_opcion`, `id_perfil`, `activo`, `fechareg`) VALUES
  (133, 1, 1, NOW()), (133, 21, 1, NOW()), (133, 24, 1, NOW()), (133, 25, 1, NOW()), (133, 26, 1, NOW()),
  (134, 1, 1, NOW()), (134, 21, 1, NOW()), (134, 24, 1, NOW()), (134, 25, 1, NOW()), (134, 26, 1, NOW());

-- ---------------------------------------------------------------------------
-- 2026_10_15_100000_seed_opcion_observaciones_solo_consulta.php
-- Opción 135 "Observaciones — solo consulta" (módulo 15 Gestión Convivencial):
-- ve todas las observaciones y no registra/edita/elimina, aunque sea docente o
-- tenga la 133/99. No se otorga a ningún perfil (se asigna desde /permisos).
-- ---------------------------------------------------------------------------
INSERT INTO `cron_opciones` (`id`, `nombre`, `id_modulo`, `activo`, `fechareg`)
SELECT 135, 'Observaciones — solo consulta', 15, 1, NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `cron_opciones` WHERE `id` = 135);

-- ---------------------------------------------------------------------------
-- 2026_10_15_100000_create_actas_estudiante_table.php
-- Registro de actas de estudiantes (diálogo formativo / convivencial) por periodo
-- académico y año escolar — ActasEstudianteController. id_curso = curso del
-- estudiante al registrar el acta. Sin FK a periodo_academico (es MyISAM).
-- ---------------------------------------------------------------------------
CREATE TABLE `actas_estudiante` (
  `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `id_estudiante` int NOT NULL,
  `id_curso` int NOT NULL,
  `id_periodo_academico` int NOT NULL,
  `id_anio_escolar` int NOT NULL,
  `tipo` enum('dialogo_formativo','convivencial') NOT NULL,
  `fecha` date NOT NULL,
  `observacion` text NULL,
  `id_usuario_registra` int NULL,
  `created_at` timestamp NULL,
  `updated_at` timestamp NULL
) DEFAULT CHARACTER SET utf8mb4 COLLATE 'utf8mb4_unicode_ci';
ALTER TABLE `actas_estudiante` ADD CONSTRAINT `actas_estudiante_id_estudiante_foreign` FOREIGN KEY (`id_estudiante`) REFERENCES `usuarios` (`id_user`) ON DELETE RESTRICT;
ALTER TABLE `actas_estudiante` ADD CONSTRAINT `actas_estudiante_id_curso_foreign` FOREIGN KEY (`id_curso`) REFERENCES `curso` (`id`) ON DELETE RESTRICT;
ALTER TABLE `actas_estudiante` ADD CONSTRAINT `actas_estudiante_id_anio_escolar_foreign` FOREIGN KEY (`id_anio_escolar`) REFERENCES `anio_escolar` (`id`) ON DELETE RESTRICT;
ALTER TABLE `actas_estudiante` ADD CONSTRAINT `actas_estudiante_id_usuario_registra_foreign` FOREIGN KEY (`id_usuario_registra`) REFERENCES `usuarios` (`id_user`) ON DELETE SET NULL;
ALTER TABLE `actas_estudiante` ADD INDEX `idx_actas_est_periodo_tipo` (`id_estudiante`, `id_periodo_academico`, `tipo`);
ALTER TABLE `actas_estudiante` ADD INDEX `idx_actas_anio_curso` (`id_anio_escolar`, `id_curso`);

-- ---------------------------------------------------------------------------
-- 2026_10_15_100100_seed_opciones_actas_estudiante.php
-- Opciones del módulo "Registro de actas" (Académico, 14), ids fijos:
-- 136 = ver (listado y gráficas), 137 = gestionar (crear/editar/eliminar).
-- Solo Super Admin por defecto; el resto se otorga desde Permisos.
-- ---------------------------------------------------------------------------
INSERT INTO `cron_opciones` (`id`, `nombre`, `id_modulo`, `activo`, `fechareg`) VALUES
  (136, 'Ver actas de estudiantes', 14, 1, NOW()),
  (137, 'Gestionar actas de estudiantes', 14, 1, NOW());

INSERT INTO `cron_permisos` (`id_opcion`, `id_perfil`, `activo`, `fechareg`) VALUES
  (136, 1, 1, NOW()),
  (137, 1, 1, NOW());

-- ---------------------------------------------------------------------------
-- 2026_10_15_110000_add_id_docente_solicita_to_actas_estudiante_table.php
-- Docente que solicita el acta (quien la diligenció en papel; perfiles 3/14,
-- lo valida ActasEstudianteService). Nullable: las actas previas no lo tienen.
-- Se imprime en "Nombre del docente" del PDF.
-- ---------------------------------------------------------------------------
ALTER TABLE `actas_estudiante` ADD `id_docente_solicita` int NULL AFTER `observacion`;
ALTER TABLE `actas_estudiante` ADD CONSTRAINT `actas_estudiante_id_docente_solicita_foreign` FOREIGN KEY (`id_docente_solicita`) REFERENCES `usuarios` (`id_user`) ON DELETE SET NULL;

-- ---------------------------------------------------------------------------
-- 2026_10_15_120000_add_asunto_to_actas_estudiante_table.php
-- Asunto del acta (texto corto que la identifica; "Asunto" del PDF). Obligatorio
-- al crear/editar (lo valida el controller); nullable para las actas previas.
-- ---------------------------------------------------------------------------
ALTER TABLE `actas_estudiante` ADD `asunto` varchar(200) NULL AFTER `tipo`;

-- ---------------------------------------------------------------------------
-- 2026_10_15_130000_seed_opcion_actas_mis_estudiantes.php
-- Opción 138 "Ver actas de mis estudiantes" (módulo 15): solo lectura de las
-- actas de los estudiantes de los cursos con carga académica activa del
-- usuario. No se otorga a ningún perfil (se asigna desde /permisos).
-- ---------------------------------------------------------------------------
INSERT INTO `cron_opciones` (`id`, `nombre`, `id_modulo`, `activo`, `fechareg`)
SELECT 138, 'Ver actas de mis estudiantes', 15, 1, NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `cron_opciones` WHERE `id` = 138);

-- ---------------------------------------------------------------------------
-- 2026_10_16_120000_add_no_aplica_to_evaluaciones.php
-- «No aplica» en evaluaciones: una evaluación compartida (p. ej. psicólogas)
-- tiene secciones que no le corresponden a todos. permite_no_aplica = la
-- pregunta ofrece «No aplica» al responder; no_aplica = se marcó así y la
-- pregunta sale del máximo de su sección (y una sección toda en N/A no pondera).
-- ---------------------------------------------------------------------------
ALTER TABLE `evaluaciones_preguntas` ADD `permite_no_aplica` tinyint NOT NULL DEFAULT '0' AFTER `permite_comentario`;
ALTER TABLE `evaluaciones_respuestas_pregunta` ADD `no_aplica` tinyint NOT NULL DEFAULT '0' AFTER `valor_texto`;
