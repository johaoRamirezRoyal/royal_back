-- ============================================================
-- 2026_04_23_152048_password_reset_tokens.php
-- ============================================================
CREATE TABLE `password_reset_tokens` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `email` VARCHAR(150) NOT NULL,
  `token` VARCHAR(64) NOT NULL,
  `used` TINYINT(1) NOT NULL DEFAULT 0,
  `expires_at` TIMESTAMP NOT NULL,
  `used_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `password_reset_tokens_email_index` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 2026_06_05_075518_create_admisiones_estados_table.php
-- ============================================================
CREATE TABLE `admisiones_estados` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `nombre` VARCHAR(100) NOT NULL,
  `estado` TINYINT(1) NOT NULL DEFAULT 1,
  `id_log` INT NULL DEFAULT NULL,
  `fechareg` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_updated` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `admisiones_estados_nombre_unique` (`nombre`),
  KEY `admisiones_estados_id_log_foreign` (`id_log`),
  CONSTRAINT `admisiones_estados_id_log_foreign` FOREIGN KEY (`id_log`) REFERENCES `usuarios` (`id_user`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 2026_07_15_120000_create_admisiones_citas_psicologia_table.php
-- ============================================================
CREATE TABLE `admisiones_citas_psicologia` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `id_inscripcion` INT NOT NULL,
  `id_psicologa` INT NOT NULL,
  `fecha_cita` DATETIME NOT NULL,
  `observaciones` TEXT NULL DEFAULT NULL,
  `fechareg` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_updated` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `admisiones_citas_psicologia_id_inscripcion_foreign` (`id_inscripcion`),
  KEY `admisiones_citas_psicologia_id_psicologa_foreign` (`id_psicologa`),
  CONSTRAINT `admisiones_citas_psicologia_id_inscripcion_foreign` FOREIGN KEY (`id_inscripcion`) REFERENCES `admisiones_inscripciones` (`id`) ON DELETE CASCADE,
  CONSTRAINT `admisiones_citas_psicologia_id_psicologa_foreign` FOREIGN KEY (`id_psicologa`) REFERENCES `usuarios` (`id_user`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 2026_07_22_093807_add_estado_cita_to_admisiones_citas_psicologia_table.php
-- ============================================================
ALTER TABLE `admisiones_citas_psicologia`
  ADD COLUMN `estado_cita` ENUM('AGENDADA','ATENDIDA') NOT NULL DEFAULT 'AGENDADA' AFTER `observaciones`;

-- ============================================================
-- 2026_07_23_120000_add_firma_urls_to_hce_observaciones_firmas_table.php
-- ============================================================
ALTER TABLE `hce_observaciones_firmas`
  ADD COLUMN `firma_padre_url` VARCHAR(255) NULL DEFAULT NULL AFTER `firma_padre`,
  ADD COLUMN `firma_madre_url` VARCHAR(255) NULL DEFAULT NULL AFTER `firma_madre`,
  ADD COLUMN `firma_psicologa_url` VARCHAR(255) NULL DEFAULT NULL AFTER `firma_psicologa`;

-- ============================================================
-- 2026_07_23_130000_add_doc_observacion_to_admisiones_citas_psicologia_table.php
-- ============================================================
ALTER TABLE `admisiones_citas_psicologia`
  ADD COLUMN `doc_observacion` VARCHAR(255) NULL DEFAULT NULL AFTER `observaciones`;

-- ============================================================
-- 2026_07_23_140000_add_url_to_firmas_table.php
-- ============================================================
ALTER TABLE `firmas`
  ADD COLUMN `url` VARCHAR(255) NULL DEFAULT NULL AFTER `nombre`;

-- ============================================================
-- 2026_07_23_150000_drop_firma_psicologa_from_hce_observaciones_firmas_table.php
-- ============================================================
ALTER TABLE `hce_observaciones_firmas`
  DROP COLUMN `firma_psicologa`;

-- ============================================================
-- 2026_07_24_000000_add_revision_finalizada_estado_to_admisiones_estados_table.php
-- (data seed, not a schema change)
-- ============================================================
INSERT INTO `admisiones_estados` (`nombre`, `estado`, `fechareg`)
VALUES ('REVISION FINALIZADA. A LA ESPERA DE RESULTADOS', 1, NOW());

-- ============================================================
-- 2026_07_24_090000_refactor_firma_psicologa_to_fk_in_hce_observaciones_firmas_table.php
-- ============================================================
ALTER TABLE `hce_observaciones_firmas`
  ADD COLUMN `id_firma_psicologa` INT NULL DEFAULT NULL AFTER `firma_psicologa_url`,
  ADD CONSTRAINT `hce_observaciones_firmas_id_firma_psicologa_foreign` FOREIGN KEY (`id_firma_psicologa`) REFERENCES `firmas` (`id`) ON DELETE SET NULL;

UPDATE `hce_observaciones_firmas` hof
JOIN `firmas` f ON f.`url` = hof.`firma_psicologa_url`
SET hof.`id_firma_psicologa` = f.`id`
WHERE hof.`firma_psicologa_url` IS NOT NULL;

ALTER TABLE `hce_observaciones_firmas`
  DROP COLUMN `firma_psicologa_url`;

-- ============================================================
-- 2026_07_28_120000_add_envio_tardio_to_enfermeria_atencion_table.php
-- ============================================================
ALTER TABLE `enfermeria_atencion`
  ADD COLUMN `envio_tardio` TINYINT NULL DEFAULT 0 AFTER `enviado`;

-- ============================================================
-- 2026_07_29_100000_add_unique_index_to_llegadas_tardes_table.php
-- ============================================================
ALTER TABLE `llegadas_tardes`
  ADD UNIQUE KEY `llegadas_tardes_alumno_fecha_unique` (`id_alumno`, `fecha`);

-- ============================================================
-- 2026_07_29_100001_dedupe_and_add_unique_index_to_asistencia_gestion_table.php
-- ============================================================
DELETE FROM `asistencia_gestion`
WHERE `id` NOT IN (
  SELECT id FROM (
    SELECT MIN(`id`) AS id
    FROM `asistencia_gestion`
    GROUP BY `id_user`, `fecha_asistencia`
  ) AS filas_a_conservar
);

ALTER TABLE `asistencia_gestion`
  ADD UNIQUE KEY `asistencia_gestion_user_fecha_unique` (`id_user`, `fecha_asistencia`);

-- ============================================================
-- 2026_07_29_110000_create_hikvision_dispositivos_table.php
-- ============================================================
CREATE TABLE `hikvision_dispositivos` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `device_id` VARCHAR(100) NOT NULL,
  `nombre` VARCHAR(100) NOT NULL,
  `fechareg` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_updated` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `hikvision_dispositivos_device_id_unique` (`device_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
