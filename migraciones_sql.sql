-- =====================================================================
-- migraciones_sql.sql
--
-- Registro manual, en SQL plano, de cada migración de Laravel creada en
-- este repo — en el mismo orden en que se crean los archivos en
-- database/migrations/. Ver AGENTS.md ("Registro manual de SQL de
-- migraciones") para la práctica completa: toda sesión que agregue una
-- migración nueva debe anexar aquí su SQL equivalente.
--
-- Este archivo es solo documentación/histórico — NO se ejecuta contra la
-- BD (las migraciones reales corren vía `php artisan migrate`). Sirve
-- para poder revisar/auditar el DDL sin tener que abrir cada archivo PHP,
-- y como referencia si alguna vez hay que aplicar estos cambios a mano
-- contra otro entorno.
-- =====================================================================


-- ---------------------------------------------------------------------
-- 2026_09_10_100000_create_subcategoria_inventario_table.php
--
-- REVERTIDA por 2026_09_10_190000_drop_subcategoria_inventario_table.php
-- (más abajo) — se dejó de usar id_subcategoria, tipo_categoria=3 ya
-- alcanza. Se deja este bloque solo como historial.
--
-- Catálogo de subcategorías de inventario (Sistemas/Operativo/Área
-- Común). Agrega categoria.id_subcategoria en paralelo al legacy
-- tipo_categoria (que se mantiene, sincronizado por CategoriasServices
-- en el código — ver "Áreas Comunes" en AGENTS.md) y hace el backfill
-- desde los dos valores que ya existían.
-- ---------------------------------------------------------------------
CREATE TABLE `subcategoria_inventario` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `nombre` VARCHAR(100) NOT NULL,
    `activo` TINYINT(1) NOT NULL DEFAULT '1'
) DEFAULT CHARACTER SET utf8mb4 COLLATE 'utf8mb4_unicode_ci';

INSERT INTO `subcategoria_inventario` (`id`, `nombre`, `activo`) VALUES
    (1, 'Sistemas', 1),
    (2, 'Operativo', 1),
    (3, 'Área Común', 1);

ALTER TABLE `categoria`
    ADD `id_subcategoria` BIGINT UNSIGNED NULL AFTER `tipo_categoria`;

ALTER TABLE `categoria`
    ADD CONSTRAINT `categoria_id_subcategoria_foreign`
    FOREIGN KEY (`id_subcategoria`) REFERENCES `subcategoria_inventario` (`id`);

UPDATE `categoria` SET `id_subcategoria` = 1 WHERE `tipo_categoria` = 1;
UPDATE `categoria` SET `id_subcategoria` = 2 WHERE `tipo_categoria` = 2;


-- ---------------------------------------------------------------------
-- 2026_09_10_110000_create_bloques_table.php
--
-- Bloque = edificio/sección física asociada a un único nivel (nivel.id).
-- nivel.id es int(11) con signo (legado) — no bigint unsigned, por eso
-- id_nivel es `int`, no `bigint unsigned`, para que el FK no truene por
-- tipos incompatibles (errno 150).
-- ---------------------------------------------------------------------
CREATE TABLE `bloques` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `nombre` VARCHAR(150) NOT NULL,
    `id_nivel` INT NULL,
    `activo` TINYINT(1) NOT NULL DEFAULT '1',
    `user_log` INT NULL,
    `fechareg` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) DEFAULT CHARACTER SET utf8mb4 COLLATE 'utf8mb4_unicode_ci';

ALTER TABLE `bloques`
    ADD CONSTRAINT `bloques_id_nivel_foreign`
    FOREIGN KEY (`id_nivel`) REFERENCES `nivel` (`id`);


-- ---------------------------------------------------------------------
-- 2026_09_10_120000_create_bloque_usuario_table.php
--
-- Pivot de responsables de un bloque (N a N) — asistentes de nivel
-- (perfil 11) / coordinadores (perfil 26). usuarios.id_user también es
-- int(11) con signo, mismo motivo que arriba para id_user.
-- ---------------------------------------------------------------------
CREATE TABLE `bloque_usuario` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `id_bloque` BIGINT UNSIGNED NOT NULL,
    `id_user` INT NOT NULL,
    `fechareg` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) DEFAULT CHARACTER SET utf8mb4 COLLATE 'utf8mb4_unicode_ci';

ALTER TABLE `bloque_usuario`
    ADD CONSTRAINT `bloque_usuario_id_bloque_foreign`
    FOREIGN KEY (`id_bloque`) REFERENCES `bloques` (`id`) ON DELETE CASCADE;

ALTER TABLE `bloque_usuario`
    ADD UNIQUE `bloque_usuario_id_bloque_id_user_unique` (`id_bloque`, `id_user`);


-- ---------------------------------------------------------------------
-- 2026_09_10_130000_add_id_bloque_to_areas_table.php
--
-- Relación área → bloque (un área pertenece a lo sumo a un bloque).
-- ---------------------------------------------------------------------
ALTER TABLE `areas`
    ADD `id_bloque` BIGINT UNSIGNED NULL AFTER `nombre`;

ALTER TABLE `areas`
    ADD CONSTRAINT `areas_id_bloque_foreign`
    FOREIGN KEY (`id_bloque`) REFERENCES `bloques` (`id`);


-- ---------------------------------------------------------------------
-- 2026_09_10_140000_add_id_bloque_to_inventario_table.php
--
-- Permite un ítem de inventario asignado directo a un bloque, sin área
-- (equipos de una zona común sin salón asociado). El SET SESSION previo
-- es necesario: agregar una FK obliga a MySQL a revalidar la tabla
-- completa, y filas legacy con fecha_compra='0000-00-00' rompen eso bajo
-- NO_ZERO_DATE — se relaja solo para esta sesión/migración, no global.
-- ---------------------------------------------------------------------
SET SESSION sql_mode = (SELECT REPLACE(@@sql_mode, 'NO_ZERO_DATE', ''));

ALTER TABLE `inventario`
    ADD `id_bloque` BIGINT UNSIGNED NULL AFTER `id_area`;

ALTER TABLE `inventario`
    ADD CONSTRAINT `inventario_id_bloque_foreign`
    FOREIGN KEY (`id_bloque`) REFERENCES `bloques` (`id`);


-- ---------------------------------------------------------------------
-- 2026_09_10_150000_seed_opciones_areas_comunes.php
--
-- Dos permisos nuevos bajo cron_modulos.id=6 ("Zonas", legacy — reusado
-- solo como agrupador; sus tablas zonas/areas_zona/chek_zonas/
-- reportes_zonas NO se tocan). Ids reales confirmados tras correr la
-- migración: 108 = Administrador areas comunes, 109 = Uso areas comunes.
-- ---------------------------------------------------------------------
INSERT INTO `cron_opciones` (`nombre`, `id_modulo`, `activo`, `fechareg`)
    VALUES ('Administrador areas comunes', 6, 1, NOW());
SET @id_admin = LAST_INSERT_ID();

INSERT INTO `cron_opciones` (`nombre`, `id_modulo`, `activo`, `fechareg`)
    VALUES ('Uso areas comunes', 6, 1, NOW());
SET @id_uso = LAST_INSERT_ID();

-- Administrador areas comunes: Super Admin (1), Administrador (2)
INSERT INTO `cron_permisos` (`id_opcion`, `id_perfil`, `activo`, `fechareg`) VALUES
    (@id_admin, 1, 1, NOW()),
    (@id_admin, 2, 1, NOW());

-- Uso areas comunes: Asistente de nivel (11), Coordinador (26)
INSERT INTO `cron_permisos` (`id_opcion`, `id_perfil`, `activo`, `fechareg`) VALUES
    (@id_uso, 11, 1, NOW()),
    (@id_uso, 26, 1, NOW());

-- IDs reales en esta BD tras correr la migración: id_admin = 108, id_uso = 109.


-- ---------------------------------------------------------------------
-- 2026_09_10_190000_drop_subcategoria_inventario_table.php
--
-- Revierte 2026_09_10_100000_create_subcategoria_inventario_table —
-- decisión de producto: no hace falta tabla/columna aparte, categoria.
-- tipo_categoria ya sirve como subcategoría (1=Sistemas, 2=Operativo,
-- 3=Área Común). id_subcategoria nunca llegó a ser la fuente de verdad
-- real en el código, solo se mantenía en espejo.
-- ---------------------------------------------------------------------
ALTER TABLE `categoria` DROP FOREIGN KEY `categoria_id_subcategoria_foreign`;
ALTER TABLE `categoria` DROP COLUMN `id_subcategoria`;
DROP TABLE IF EXISTS `subcategoria_inventario`;


-- ---------------------------------------------------------------------
-- 2026_09_10_200000_recreate_subcategoria_inventario_related_to_tipo_categoria.php
--
-- Segunda vuelta, corrigiendo el diseño anterior: sí hace falta el
-- catálogo `subcategoria_inventario` (para tener nombre, no solo un int
-- mágico), pero SIN columna nueva en `categoria` — la relación es una FK
-- real sobre la columna que ya existe, `categoria.tipo_categoria` →
-- `subcategoria_inventario.id`. `tipo_categoria` sigue siendo la única
-- fuente de verdad; esta tabla solo cataloga sus valores válidos.
-- `id` es `int` (no bigint unsigned) porque tipo_categoria es `int(11)`
-- con signo (legacy) — un PK bigint unsigned rompe la FK por
-- incompatibilidad de tipos (mismo problema ya visto con bloques.id_nivel).
-- ---------------------------------------------------------------------
CREATE TABLE `subcategoria_inventario` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `nombre` VARCHAR(100) NOT NULL,
    `activo` TINYINT(1) NOT NULL DEFAULT '1',
    PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE 'utf8mb4_unicode_ci';

INSERT INTO `subcategoria_inventario` (`id`, `nombre`, `activo`) VALUES
    (1, 'Sistemas', 1),
    (2, 'Operativo', 1),
    (3, 'Área Común', 1);

ALTER TABLE `categoria`
    ADD CONSTRAINT `categoria_tipo_categoria_foreign`
    FOREIGN KEY (`tipo_categoria`) REFERENCES `subcategoria_inventario` (`id`);


-- ---------------------------------------------------------------------
-- 2026_09_10_210000_create_inventario_check_table.php
--
-- Migración del "check" semestral de zonas del SAMI legacy (tabla
-- chek_zonas, no se toca) — certifica que un ítem de inventario de Área
-- Común fue revisado en un periodo institucional. Sin índice único a
-- nivel de motor (el legacy tampoco lo tenía) — la regla "ya se hizo
-- check este periodo" se valida en InventarioServices::registrarCheckInventario.
-- Columnas `int` simples para calzar con inventario.id/anio_escolar.id/
-- usuarios.id_user, todos int(11) legacy.
-- ---------------------------------------------------------------------
CREATE TABLE `inventario_check` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `id_inventario` INT NOT NULL,
    `id_anio` INT NULL,
    `periodo` INT NULL,
    `id_user` INT NULL,
    `fechareg` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE 'utf8mb4_unicode_ci';

ALTER TABLE `inventario_check`
    ADD INDEX `inventario_check_id_inventario_index` (`id_inventario`);


-- ---------------------------------------------------------------------
-- 2026_09_14_120000_seed_opcion_mis_areas_comunes.php
--
-- Permiso para la vista de autoservicio "Mis áreas"
-- (`/inventario/areas-comunes/mis-areas`): un asistente de nivel/coordinador
-- ve el inventario de los bloques donde `bloque_usuario` lo tiene como
-- responsable. Distinto de 108/109 (esos dan acceso a TODO el módulo);
-- este solo habilita la pestaña de autoservicio, otorgado a los mismos
-- perfiles que pueden ser asignados como responsables
-- (BloquesServices::PERFILES_RESPONSABLES). Id real confirmado tras correr
-- la migración: 117 = Mis áreas comunes.
-- ---------------------------------------------------------------------
INSERT INTO `cron_opciones` (`nombre`, `id_modulo`, `activo`, `fechareg`)
    VALUES ('Mis áreas comunes', 6, 1, NOW());
SET @id_mis_areas = LAST_INSERT_ID();

-- Mis áreas comunes: Asistente de nivel (11), Coordinador (26)
INSERT INTO `cron_permisos` (`id_opcion`, `id_perfil`, `activo`, `fechareg`) VALUES
    (@id_mis_areas, 11, 1, NOW()),
    (@id_mis_areas, 26, 1, NOW());

-- Id real en esta BD tras correr la migración: id_mis_areas = 117.


-- ---------------------------------------------------------------------
-- 2026_09_14_130000_seed_opcion_editar_reservas.php
--
-- Permiso para editar una reserva ya creada (fecha, hora, salón,
-- descripción, portátiles y sonido) — distinto de 41 (crear/cancelar la
-- propia, autoservicio) y 42 (ver la programación de todos). Arranca
-- acotado a Super Admin (1) / Administrador (2) — ver
-- ReservaController::actualizarReserva. Id real confirmado tras correr la
-- migración: 118 = Reservas — Editar reserva.
-- ---------------------------------------------------------------------
INSERT INTO `cron_opciones` (`nombre`, `id_modulo`, `activo`, `fechareg`)
    VALUES ('Reservas — Editar reserva', 7, 1, NOW());
SET @id_editar_reservas = LAST_INSERT_ID();

-- Reservas — Editar reserva: Super Admin (1), Administrador (2)
INSERT INTO `cron_permisos` (`id_opcion`, `id_perfil`, `activo`, `fechareg`) VALUES
    (@id_editar_reservas, 1, 1, NOW()),
    (@id_editar_reservas, 2, 1, NOW());

-- Id real en esta BD tras correr la migración: id_editar_reservas = 118.


-- ---------------------------------------------------------------------
-- 2026_09_14_140000_add_id_motivo_to_permiso_catalogos.php
--
-- Vincula cada ítem de los catálogos "hijos" (permiso_ley, permiso_personal,
-- permisos_institucionales) con su motivo general (permiso_motivo). Sin FK
-- real a nivel de motor: esas tres tablas son MyISAM (heredadas) y
-- permiso_motivo es InnoDB — MySQL no soporta FOREIGN KEY entre motores
-- distintos. La relación queda a nivel de aplicación (Eloquent belongsTo +
-- validación exists:permiso_motivo,id).
-- ---------------------------------------------------------------------
ALTER TABLE `permiso_ley` ADD COLUMN `id_motivo` INT NULL AFTER `nombre_permiso`;
ALTER TABLE `permiso_personal` ADD COLUMN `id_motivo` INT NULL AFTER `nombre_permiso`;
ALTER TABLE `permisos_institucionales` ADD COLUMN `id_motivo` INT NULL AFTER `nombre_permiso`;


-- ---------------------------------------------------------------------
-- 2026_09_14_141000_seed_opcion_configuracion_permisos_licencias.php
--
-- Permiso para administrar los catálogos de Permisos y Licencias (motivos,
-- ley, personal, institucional): renombrar, activar/desactivar y vincular
-- cada ítem de ley/personal/institucional con su motivo general. Distinto
-- de 80/81/82/83/90/92 (todas sobre las solicitudes). Id real confirmado
-- tras correr la migración: 121 = Permisos y Licencias — Configuración de
-- catálogos.
-- ---------------------------------------------------------------------
INSERT INTO `cron_opciones` (`nombre`, `id_modulo`, `activo`, `fechareg`)
    VALUES ('Permisos y Licencias — Configuración de catálogos', 3, 1, NOW());
SET @id_config_permisos = LAST_INSERT_ID();

-- Permisos y Licencias — Configuración de catálogos: Super Admin (1), Administrador (2)
INSERT INTO `cron_permisos` (`id_opcion`, `id_perfil`, `activo`, `fechareg`) VALUES
    (@id_config_permisos, 1, 1, NOW()),
    (@id_config_permisos, 2, 1, NOW());

-- Id real en esta BD tras correr la migración: id_config_permisos = 121.
