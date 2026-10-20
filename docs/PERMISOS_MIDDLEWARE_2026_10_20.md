# Permisos en rutas y controllers — cambios del 2026-10-20

Registro de todo lo que cambió en el manejo de permisos del backend ese día: por qué, qué se
tocó, cómo se verificó y qué quedó pendiente. La guía de uso (cómo gatear algo nuevo) está en
`docs/sistema-permisos.md` del frontend y en `AGENTS.md` de este repo; este documento es el
historial.

## 1. Punto de partida

- El reporte inicial: en producción el botón "Nueva observación" no aparecía para un Super
  Admin. El código estaba bien. El botón depende de `puede_registrar` (opción 133/99 o ser
  docente, y la 135 lo anula), así que el problema eran datos de permisos en producción:
  perfil 1 sin la 133 o con la 135.
- Al revisarlo se vio que los permisos del backend estaban repartidos en tres formas
  distintas (chequeo en el constructor, helper `sinAcceso()` por método, o nada), con los ids
  de opción escritos a mano en cada controller. Varias rutas no tenían ningún chequeo.

## 2. Observaciones: elegir el docente con la 133/99

Quien tiene permiso sobre todo el módulo de Observaciones (opción 133 o 99) ahora **elige el
docente** al registrar una observación, en vez de que quede a su nombre.

- **Migración** `2026_10_20_100000_add_id_usuario_registra_to_academico_observacion_estudiante_table`:
  columna `id_usuario_registra` (FK a `usuarios`), rellenada con `id_docente` en las filas
  existentes. **Correrla antes de desplegar el código.** SQL equivalente:

  ```sql
  ALTER TABLE academico_observacion_estudiante
      ADD COLUMN id_usuario_registra INT NULL AFTER id_docente,
      ADD CONSTRAINT academico_observacion_estudiante_id_usuario_registra_foreign
          FOREIGN KEY (id_usuario_registra) REFERENCES usuarios (id_user);
  UPDATE academico_observacion_estudiante SET id_usuario_registra = id_docente;
  ```

- `id_docente` pasa a ser "el docente en cuestión"; `id_usuario_registra`, quién la digitó.
  Solo quien la digitó la edita o elimina. Cada quien ve las que tiene como docente o las que
  registró.
- `AccesoObservaciones::eligeDocente()` = puede registrar a cualquier estudiante (133/99).
  `/catalogos` y el acta devuelven `elige_docente`; el acta trae `docentes` (perfiles 3/14
  activos, primero los que dictan en el curso, cada uno con sus asignaturas en ese curso).
- El service exige `id_docente` (docente activo) cuando `eligeDocente()`, y valida la
  asignatura contra la de ese docente. Al editar sin la 133/99 se conserva el docente que ya
  tenía.
- Frontend: selector "Docente" en `ObservacionForm`, columna/etiqueta "Docente".

## 3. Middleware `permiso`

### 3.1 Piezas

| Pieza | Qué hace |
|---|---|
| `app/Http/Middleware/RequirePermiso.php` (alias `permiso`) | Deja pasar si el perfil tiene **cualquiera** de las opciones o está en `perfil:N`. Sin usuario o sin nada de eso: 403 JSON (`No tienes permiso para acceder a este recurso.`). |
| `app/Services/Usuarios/PermisosPerfil.php` | Carga las opciones del perfil **una vez por petición** (mismo criterio que `tienePermiso`: permiso activo con opción y módulo existentes) y las guarda en el request. `PermisosPerfil::tiene($request, ...$ids)`. |
| `app/Support/Permiso.php` | **Única fuente de los ids**: una constante por opción en uso (62, con el nombre de `cron_opciones`) y por perfil (Super Admin, Administrador, Docente, Docente ICT, Coordinador). `Permiso::ruta($opciones, $perfiles)` arma el string del middleware. |

### 3.2 Regla: el permiso va en la ruta y en el controller

```php
// Ruta
Route::get('/', [XController::class, 'listar'])
    ->middleware(Permiso::ruta([Permiso::INVENTARIO, Permiso::PRESTAMOS_INVENTARIO]));

// Controller sin chequeo por método
class XController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [new Middleware(Permiso::ruta([Permiso::INVENTARIO]), except: ['accionPublica'])];
    }
}
```

- La ruta deja ver el permiso junto al endpoint; el controller no depende de que la ruta lo
  tenga. Laravel no ejecuta dos veces un middleware idéntico.
- `HasMiddleware` corre como middleware, **después** de la autenticación. Los chequeos en el
  constructor se eliminaron: Laravel instancia el controller antes de correr el middleware de
  la ruta, y `$request->user()` + `abort()` ahí era frágil.
- Lo que depende de los datos (qué ve cada quien, la 135, la 101 de Llegadas tarde, editar
  solo lo propio) sigue dentro del controller/service. La ruta solo responde "¿puede entrar?".

### 3.3 Rutas que no tenían ningún chequeo (huecos cerrados)

Opciones = unión de las páginas del frontend que consumen cada endpoint, para no dejar fuera a
nadie que hoy entra.

| Rutas | Permiso | Consumidores |
|---|---|---|
| `/hikvision/*` | 63, 2 o Super Admin | Control de acceso, Usuarios (alta/baja en terminales), Terminales |
| `/prestamos` | 16 o 17 | Mis inventarios, Préstamos |
| `/logs-actividad` | Super Admin | Página de logs |
| `/areas` crear, editar, asignar, estado | 12 | Inventario → Áreas |
| `/compartido/anio-academico/periodo-academico` crear, editar, estado, eliminar | 99 | Configuración académica |

El más grave era Hikvision: cualquier autenticado podía enrolar o eliminar empleados en los
terminales. El webhook del dispositivo (`POST /pushNotification`) es público y no cambió.

### 3.4 Rutas que ya tenían chequeo

- **Chequeo al inicio de cada método** (~300 rutas): el middleware de cada ruta se generó con
  un script a partir del propio chequeo del método (solo cuando era la primera instrucción, o
  sea incondicional), resolviendo las constantes. Todos los helpers (`sinAcceso`,
  `sinPermiso`, `ensurePermisoGestion`, `ensurePermisoCorreosDistribucion`, `soloSuperAdmin`)
  son un OR de opciones (más bypass de Super Admin en Noticias), igual que el middleware. El
  chequeo del método se dejó como segunda barrera.
- **A mano**: Instituciones (106; 106/111 en listar y cartas; Super Admin para eliminar
  documentos), Aprobaciones de compras (perfiles 1, 2, 26), módulos y contenidos de
  Capacitaciones (86), listado de Noticias (69 o Super Admin), compras de un proveedor (60/61).
- **Chequeo en el constructor → `HasMiddleware`**: AdminManagement (bases de datos, logs por
  dominio, llaves maestras, banner, correos institucionales) y Marcas por dominio (Super
  Admin), Biblioteca (20, excepto `verImagenBiblioteca`, pública), Consulta de asistencias
  (132/99), Cumpleaños (69 o Super Admin), Gestión Académica.
- **Gestión Académica**: `METODOS_DOCENTE` sigue en el controller y alimenta su
  `middleware()`: 99 en todo; además docente (3/14) en su autoservicio; además 102 en
  `verMetricasAsistencia`. Las rutas llevan las mismas reglas.
- **También con `HasMiddleware`** (antes solo protegidos por la ruta): Hikvision (excepto el
  webhook), Préstamos, Logs de actividad, Áreas (solo escrituras), Periodos académicos (solo
  escrituras), entrada de Observaciones.

Resultado: **476 de 604** rutas autenticadas del sistema general declaran su permiso.

### 3.5 Las 128 rutas sin middleware (a propósito)

Siguen validándose dentro del método, porque su regla depende de los datos y no solo del
perfil, o porque son de uso general:

- Llegadas tarde (la 101 limita a "solo hoy"), Asistencia de trabajadores (propia vs. todas),
  Indicadores de gestión (opción según el tipo de categoría), lectura de Actas (regla del
  docente), editar el propio permiso/licencia.
- Autoservicio: `/info-perfil`, `/documentos-varios`, `/modulos`, mis trámites, mis
  certificados, mis capacitaciones, mis evaluaciones/encuestas, cancelar la propia solicitud de
  compra, reservas.
- Catálogos de lectura: `/cursos`, `/tipos-documentos`, lecturas de `/usuarios`, `/areas`,
  `/salones`, `/horas`, periodos, tipos/motivos de permisos y trámites.
- `/compartido/historia-clinica`: la usa el flujo de admisiones, cuyos usuarios no tienen
  opciones en `cron_permisos`.

### 3.6 Ids centralizados

- Todas las constantes `OPCION_*`, `PERFIL_*` y `PERFILES_DOCENTE` de controllers, services,
  el modelo `ActaEstudiante` y `SwitchActiveConnection` apuntan a `Permiso`. También los dos
  ids que estaban sueltos: `[self::OPCION_PROVEEDORES, 60]` (Proveedores) y
  `OPCION_POR_TIPO = [1 => 94, 2 => 95]` (Indicadores).
- Las rutas usan `Permiso::ruta(...)`, sin números.
- Si un id cambia en BD (ya pasó con Noticias 107→69, Instituciones 104/105→106/111,
  Acudientes 106→112), se cambia solo en `Permiso`.

## 4. Cómo se verificó

- `php -l` en todos los archivos tocados; `php artisan route:list` carga sin errores.
- Comparación manual de una muestra de rutas generadas contra el chequeo de su método.
- Middleware directo con usuarios reales de la BD local: Super Admin, docente y estudiante.
- **Peticiones HTTP reales** por el kernel con JWT (una por proceso): el estudiante (16)
  recibe 403 en Biblioteca, Gestión Académica, Logs, Hikvision, crear áreas, Observaciones e
  Inventario; el Super Admin, 200; el docente entra a Mi horario y Observaciones pero no a
  Configuración académica (asignaturas). Lectura de Áreas sigue abierta (200).
- **Capa del controller sola**: rutas sintéticas sin middleware apuntando a cada controller
  con `HasMiddleware`: cada acción recibe la misma regla que su ruta, y las públicas
  (`testNotificationHikvision`, `verImagenBiblioteca`, lecturas de Áreas y Periodos) ninguna.
- No se probó en el navegador ni en producción.

## 5. Despliegue y prueba en producción

1. Correr la migración de `id_usuario_registra` (sección 2).
2. Subir el backend; luego el frontend.
3. Revisar que el Super Admin tenga la 133 activa y no la 135 (si no, sin botón "Nueva
   observación"):
   ```sql
   SELECT id_opcion, activo FROM cron_permisos WHERE id_perfil = 1 AND id_opcion IN (99,133,134,135);
   ```
4. Con un usuario de cada perfil que use el módulo: Observaciones (registrar eligiendo
   docente), Préstamos, Inventario → Áreas, Control de acceso, alta de usuario, Configuración
   académica → Periodos, Gestión Académica con un docente (tomar asistencia, Mi horario),
   Biblioteca, Logs de actividad.
5. Si alguien que debería entrar recibe 403 (`No tienes permiso para acceder a este
   recurso.`), es una opción que faltó en `Permiso::ruta(...)` de esa ruta y en el
   `middleware()`/chequeo de su controller: anotar perfil y endpoint.

## 6. Pendiente

- **Propiedad del dato** en `/documentos-varios` e `/info-perfil`: varios borrados reciben el
  id del usuario como parámetro. Si no comprueban que sea el propio usuario (o alguien con
  "Perfiles de usuario", opción 87), alguien podría borrar datos de otro. No es un tema de
  opciones, el middleware no lo cubre; queda por revisar.
- `routes/api.php` incluye `llegadasTarde.php` dos veces (rutas duplicadas, sin efecto
  funcional).
