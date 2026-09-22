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
