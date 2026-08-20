# AGENTS.md — Royal Backend (S.A.M.I.)

Laravel 12 REST API (PHP ^8.2) para gestión escolar. JWT via `tymon/jwt-auth`.

## Quick start

```bash
composer run setup        # install, .env, migrate, key:generate, npm install/build
composer run dev          # concurrently: serve + queue:listen --tries=1 --queue=emails,default + npm run dev
composer run test         # config:clear + php artisan test
php artisan serve         # dev server on localhost:8000
```

## Arquitectura general

- **Lenguaje**: Español — comentarios, mensajes de error, commits, docs.
- **API 100% JSON** — sin vistas Blade (excepto emails).
- **DB externa** — migraciones mínimas (14), tablas core preexistentes.
- **Sin tests** — `tests/` no existe, solo `phpunit.xml`.

## Mapa de rutas (`routes/api.php`)

### Sin autenticación
| Prefix | File | Uso |
|--------|------|-----|
| `/` | inline | Welcome JSON |
| `POST /pushNotification` | inline | Hikvision test |
| `/auth` | `api/auth.php` | login, register, check, password restore |
| `GET /biblioteca/imagen/{carpeta}/{filename}` | inline | Imagen pública |
| `/admissions` | `api/admissions.php` | Flujo público de admisiones |

### `auth:api` (compartido — ambos sistemas)
| Prefix | File |
|--------|------|
| `/documentos` | inline |
| `/compartido` | inline + `api/anioAcademico.php` + `api/historiaClinica.php` |

### `auth:api` + `system:admissions`
| Prefix | File |
|--------|------|
| `/admisiones` | `api/admisiones.php` |
| `/admisiones/tipos-documentos` | `api/TipoDocumentos.php` |

### `auth:api` + `system:general`
| Prefix | File |
|--------|------|
| `/auth` | `api/auth-protected.php` |
| `/info-perfil` | `api/perfilUsuario.php` |
| `/usuarios` | `api/usuarios.php` |
| `/cursos` | `api/cursos.php` |
| `/permisos` | `api/permisos.php` |
| `/areas` | `api/areas.php` |
| `/inventario` | `api/inventario.php` |
| `/prestamos` | `api/prestamos.php` |
| `/reservas` | `api/reservas.php` |
| `/salones` | `api/salones.php` |
| `/horas` | `api/horas.php` |
| `/categorias` | `api/categorias.php` |
| `/hikvision` | `api/hikvision.php` |
| `/biblioteca` | `api/Biblioteca.php` |
| `/tipos-documentos` | `api/TipoDocumentos.php` |
| `/llegadas-tarde` | `api/llegadasTarde.php` |
| `/gestion-academica` | `api/gestionAcademica.php` |
| `/documentos-varios` | `api/documentosVarios.php` |
| `/asistencia-gestion` | `api/asistenciaGestion.php` |
| `/enfermeria` | `api/enfermeria.php` |
| `/proveedores` | `api/proveedores.php` |
| `/solicitudes` | `api/solicitudes.php` |

## Autenticación JWT

### Flujo
1. Login genera JWT con claims: `system`, `user_id`, `nombre`, `apellido`, `correo`, `perfil`.
2. Token se guarda en httpOnly cookie: `token` (general) o `admissions_token` (admisiones).
3. `JwtFromCookie` middleware (prepended al grupo `api`) lee la cookie y setea `Authorization: Bearer`.
4. `ValidateSystem` middleware (alias `system`) verifica el claim `system` del JWT.

### Cookies no encriptadas
`bootstrap/app.php:21` — `encryptCookies(except: ['token', 'admissions_token'])`

### Middleware stack
```php
// bootstrap/app.php
$middleware->prependToGroup('api', JwtFromCookie::class);
$middleware->alias([
    'auth' => Authenticate::class,     // 401 JSON
    'system' => ValidateSystem::class, // 403 si system mismatch
]);
```

### JwtService (`app/Services/JwtService.php`)
Singleton bound en `AppServiceProvider`. Métodos: `generateToken()`, `generateAdmissionsToken()`, `refreshToken()`, `invalidateToken()`, `getPayload()`, `authenticate()`.

### Cookie helper — `HasAuthCookie` trait
```php
use App\Http\Traits\HasAuthCookie;
// $this->makeCookie($token, 'token'|'admissions_token')
```
Usado en: `AuthController`, `AdmissionsController`.

## Sistema de permisos (`cron_opciones` / `cron_permisos`)

Tablas legacy manejadas directo por SQL (`DB::table(...)`, no Eloquent/seeders):
- `cron_opciones` (`id`, `nombre`, `id_modulo`, `user_log`, `activo`, `fechareg`) — catálogo
  de opciones/permisos (cada fila = acceso a un módulo, submódulo o acción).
- `cron_permisos` (`id_opcion`, `id_perfil`, `activo`, ...) — la matriz real: qué
  `id_perfil` tiene otorgada cuál `id_opcion`.

El chequeo real vive en `UsuariosServices::tienePermiso($opcion, $perfil): array{permiso: bool, error: bool}`
(`SELECT ... FROM cron_permisos WHERE id_opcion=? AND id_perfil=? AND activo=1`). Lo
expone `GET /api/usuarios/permiso?opt=&per=` (`UsuariosController::tienePermiso`) para
que el frontend decida qué renderizar (`PermissionGate` / fail-closed — ver
`docs/sistema-permisos.md` en el repo del frontend para el detalle de ese lado).

**Ese chequeo NO se aplica solo — cada controller tiene que llamarlo.** No existe
middleware global de permisos (revisar `bootstrap/app.php`: `JwtFromCookie`, `auth`,
`system` son los únicos alias). Una auditoría (2026-08-18) encontró que de 32
controllers, solo 2 verificaban permisos antes de ejecutar una acción — el resto,
incluido `PermisosController` (el que administra los permisos mismos), dejaba que
cualquier usuario autenticado hiciera lo que quisiera con un request directo,
saltándose por completo lo que el frontend mostraba u ocultaba. Se corrigió ese día en
`PermisosController`, `UsuariosController`, `LlegadasTardeController`/`ConfigController`,
`GestionAcademicaController`, `EnfermeriaController`, `InventariosController`,
`SalonesController`, `BibliotecaController`, `CategoriasController` — usarlos como
referencia al crear un controller nuevo con acciones sensibles.

Dos patrones según el caso (ver los controllers de arriba para ejemplos reales):

**(a) Todo el controller detrás de una sola opción, sin rutas públicas mezcladas** — un
chequeo único en el constructor:
```php
public function __construct(
    private MiService $service,
    UsuariosServices $usuariosService,
    Request $request,
) {
    $tienePermiso = $usuariosService->tienePermiso(self::OPCION, $request->user()->perfil)['permiso'] ?? false;
    if (!$tienePermiso) {
        abort($this->error('No tienes permiso para esta acción', 403));
    }
}
```
Si el controller tiene alguna ruta pública mezclada (ej. `BibliotecaController::verImagenBiblioteca`,
servida fuera de `auth:api`), el chequeo debe ser condicional a que exista usuario
autenticado (`if ($usuario = $request->user()) { ... }`) — nunca asumas que
`$request->user()` no es null.

**(b) Métodos que necesitan opciones distintas** — helper `sinAcceso()` al inicio de
cada método:
```php
private function sinAcceso(Request $request, int ...$opciones): ?JsonResponse
{
    $perfil = $request->user()->perfil;
    foreach ($opciones as $opcion) {
        if ($this->usuariosService->tienePermiso($opcion, $perfil)['permiso'] ?? false) {
            return null;
        }
    }
    return $this->error('No tienes permiso para esta acción', 403);
}
```

Reglas:
- Antes de gatear un endpoint, revisa (grep en el frontend, `src/pages/`) qué otros
  módulos lo consumen — un endpoint de lectura compartido (dropdowns, catálogos) debe
  aceptar cualquiera de las opciones válidas (OR), no solo la del módulo "dueño".
- Nunca confíes en un campo del body para "quién hizo esto" (`user_log`, `id_log`) si es
  para auditoría/seguridad — usa `$request->user()->id_user`, no lo que mande el cliente.
- Antes de dar por cerrado el cambio, confirma en BD quién tiene la opción hoy para no
  bloquear a un perfil que ya debería tener acceso:
  ```
  php artisan tinker --execute="foreach (DB::table('cron_permisos as p')->join('perfiles as pf','pf.id_perfil','=','p.id_perfil')->where('p.id_opcion',N)->where('p.activo',1)->pluck('pf.nombre') as \$n) echo \$n . PHP_EOL;"
  ```
- Para crear una opción nueva sigue el patrón de
  `database/migrations/2026_08_18_100000_seed_opcion_llegadas_tarde_recepcion.php`: un
  `up()` con `insertGetId` en `cron_opciones` + inserts iniciales en `cron_permisos`, y un
  `down()` simétrico. El `id` lo asigna el autoincrement — corre la migración local antes
  de hardcodear el número en el frontend.

## Proceso de compra (`/proveedores` + `/solicitudes`)

Módulo de compras en dos tablas paralelas: la solicitud inicial (`solicitudes_inicial`)
y la formalizada (`solicitudes`). Opciones del módulo 9 "Proceso de compra" en
`cron_opciones`: **59 Cotizaciones, 60 Listado de solicitudes, 61 Proveedores**.
La 61 y la 60 la tienen: Super Admin, Administrador, Tesorera, Asistente Contable.

### Proveedores

Un proveedor es un `Usuario` con `perfil=17`; `proveedor_detalle.id_proveedor` =
`usuarios.id_user`, y las tablas hijo (`proveedor_documento`, `proveedor_contactos`,
`proveedor_banco`) usan `id_proveedor` = `id_user`.

| Endpoint | Gate | Uso |
|----------|------|-----|
| `GET /proveedores`, `GET /proveedores/select`, `GET /proveedores/tipos-documento` | No | Listado / dropdown (solo activos) / catálogo |
| `GET /proveedores/{id}` | 61 | Detalle con documentos/contactos/bancos |
| `POST /proveedores`, `PUT /proveedores/{id}`, `PUT /proveedores/{id}/estado` | 61 | CRUD proveedor (crea usuario perfil 17 + detalle) |
| `GET/POST /proveedores/{id}/documentos` | 61 | Subida de documentos (FileStorage, no Cloudinary) |
| `PUT\|POST /proveedores/documentos/{docId}`, `PUT .../estado`, `DELETE ...` | 61 | Update/estado/elimina (borra archivo) |
| `GET/POST /proveedores/{id}/contactos`, `PUT/DELETE /proveedores/contactos/{cId}` | 61 | Contactos |
| `GET/POST /proveedores/{id}/bancos`, `PUT/DELETE /proveedores/bancos/{bId}` | 61 | Cuentas bancarias |

### Solicitudes — flujo y estados

| Estado | `solicitudes_inicial` | `solicitudes` (final) |
|--------|------------------------|------------------------|
| pendiente / formalizada | 0 | 1 (`estado`) |
| aprobada / cerrada | 1 | 2 (`estado`) |
| devuelta / devolución | 2 | 3 (`estado`) |
| rechazada | 3 | — |
| convertida | 4 | — |
| aplazada / rechazada (final) | — | `activo` 10 / 0 |

Flujo: `crear` (0) → `verificar` (aprobar 1 / devolver 2 / rechazar 3) →
`asignar-proveedor` (marca la inicial 4 y crea la `solicitudes` final estado 1) →
`aplazar`/`rechazar` (activo 10/0) → `verificar-entrega` (cerrada 2 / devolución 3).

| Endpoint | Gate | Uso |
|----------|------|-----|
| `POST /solicitudes` | No | Crea la inicial + productos (cualquier empleado) |
| `GET /solicitudes` | 60 | Paginado; filtros `per-page`, `id_user`, `id_area`, `estado`, `fecha_desde`/`fecha_hasta`, `s` (nombre/documento de usuario, ids, nombre de producto) |
| `GET /solicitudes/{id}` | 60 | Detalle con `verificacionInicial` |
| `POST /solicitudes/{id}/verificar` | 60 | Rubros Si/No + observaciones; decision `aprobar\|devolver\|rechazar` |
| `POST /solicitudes/{id}/asignar-proveedor` | 60 | Multipart: `id_proveedor` (perfil 17 activo), `iva`, `cotizacion_doc`; convierte inicial→final y copia productos |
| `PUT /solicitudes/{id}/aplazar` | 60 | `fecha_aplazado` + `activo=10` |
| `PUT /solicitudes/{id}/rechazar` | 60 | `motivo`/`observacion` + `activo=0`, limpia `fecha_aplazado` |
| `POST /solicitudes/{id}/verificar-entrega` | 60 | Multipart: rubros + `factura_doc`; decision `cerrar\|devolucion` |
| `POST /solicitudes/{id}/agregar-inventario` | 60 | Agrega los artículos de la compra al inventario |

Quirks del módulo:
- Archivos: cotización → `solicitudes/cotizaciones`, factura → `solicitudes/facturas`.
  Se guarda el `nombre_guardado` en `cotizacion_doc`/`factura_doc`; la URL de la
  cotización se expone en la respuesta como `url_cotizacion`.
- Los multipart de update usan `POST` (PHP no parsea campos en `PUT` multipart).
- Los FormRequest de subida necesitan `Accept: application/json` (si no, 302 a `/`).
- `fecha_ingreso` de proveedor no admite `'0000-00-00'` (MySQL strict) — forzar null.

### Agregar artículos al inventario (`POST /solicitudes/{id}/agregar-inventario`)

El inventario cuenta **por filas** (una unidad = una fila en `inventario`; no hay
columna `cantidad`). El endpoint (`SolicitudesController::agregarInventario` →
`InventarioServices::agregarArticulosAInventario`) crea N filas por artículo validando
que la cantidad a ingresar no supere lo solicitado:

- El rastreo de "ya ingresado" usa `inventario.id_compra` = id de la solicitud final y
  `inventario.detalles` = id del `solicitud_productos`.
- Por artículo: `disponible = solicitud_productos.cantidad`; si `yaIngresado + cantidad
  > disponible` → error 422 con el detalle. Acumula dentro del mismo request.
- Cada fila: `descripcion` = nombre del producto (máx 200), `precio` = el del artículo
  (o el enviado), `estado` = 1 por defecto, `activo`=1, `confirmado`=1, `id_compra`,
  `detalles`, y `id_area`/`id_usuario`/`id_categoria`/`fecha_compra` del request.
  Registra `inventario_log` vía `registrarLog`. Todo en una transacción.
- Request: `articulos[]` con `id_producto` (debe pertenecer a la solicitud), `cantidad`
  ≥ 1, `id_area` activa, `id_usuario` activo, `id_categoria`; opcionales `estado`,
  `precio`, `fecha_compra`.
- Respuesta: `articulos_creados` + `resumen[]` con `solicitado`/`ingresado`/`restante`.

## Convenciones de código

### Naming de directorios
| Capa | Case | Ejemplo |
|------|------|---------|
| Models | Capitalized | `app/Models/Inventario/` |
| Controllers | Capitalized | `app/Http/Controllers/Inventarios/` |
| Services | **lowercase** | `app/Services/inventario/` |
| Requests | Capitalized | `app/Http/Requests/Inventario/` |
| Routes | lowercase (excepción: `Biblioteca.php`) | `routes/api/inventario.php` |

### Controllers
```php
namespace App\Http\Controllers\Inventarios;

use App\Http\Controllers\Controller;
use App\Services\inventario\InventarioServices;

class InventariosController extends Controller
{
    protected $service_inventario;

    public function __construct(InventarioServices $service_inventario)
    {
        $this->service_inventario = $service_inventario;
    }
}
```

### Base `Controller` methods (`app/Http/Controllers/Controller.php`)
| Método | Firma | Uso |
|--------|-------|-----|
| `success()` | `(string $message, mixed $data = null, int $status = 200)` | Respuesta exitosa simple |
| `error()` | `(array\|string $message, int $status = 500, ...)` | Error directo |
| `apiResponse()` | `(array $response): JsonResponse` | **Universal**: pasa el array del service, auto-detecta error tipo database/connection/logic |
| `paginatedResponse()` | `(array $response, ?string $resourceClass)` | Paginación con resource opcional |

**IMPORTANTE**: Muchos controllers NO usan `$this->apiResponse()` y hacen inline `response()->json([...])`. Cuando crees un controller nuevo, usa `apiResponse()` si el service devuelve `['error'=>bool,...]`. Si el código existente usa inline, manten el patrón existente.

### Services
```php
namespace App\Services\inventario;

class InventarioServices
{
    public function __construct(
        private CloudinaryService $cloudinary
    ) {}

    public function listar(): array
    {
        try {
            $data = Inventario::all();
            return ['error' => false, 'message' => 'ok', 'data' => $data];
        } catch (\Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }
}
```

**Convención de retorno**: `['error' => bool, 'message' => string, 'data' => mixed]`

`app/Services/Service.php` es base abstracta con `sendError()`. La mayoría de services NO la extienden — es optativa.

### Models
```php
namespace App\Models\Inventario;

use Illuminate\Database\Eloquent\Model;

class Inventario extends Model
{
    protected $table = 'inventario';
    protected $primaryKey = 'id';
    public $timestamps = false;
    protected $fillable = ['nombre', 'descripcion', 'estado'];
    protected $casts = ['cantidad' => 'integer'];
    protected $attributes = ['estado' => 'disponible'];
}
```

Para modelos Usuario (JWT):
```php
use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Foundation\Auth\User as Authenticatable;

class Usuario extends Authenticatable implements JWTSubject
{
    protected $table = 'usuarios';
    protected $primaryKey = 'id_user';
    public $timestamps = false;
    protected $hidden = ['pass'];

    public function getJWTIdentifier() { return $this->getKey(); }
    public function getJWTCustomClaims() { return []; }
    public function getAuthPassword() { return $this->pass; }
}
```

### Form Requests
```php
namespace App\Http\Requests\Inventario;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInventarioRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    protected function prepareForValidation(): void
    {
        $this->merge(['nombre' => trim($this->nombre)]);
    }

    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:255'],
            'codigo' => ['required', Rule::unique('inventario', 'codigo')],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre es obligatorio',
            'codigo.unique' => 'El código ya existe',
        ];
    }
}
```

## Guía: crear un módulo nuevo

Ejemplo: módulo "Categorías"

### 1. Modelo
`app/Models/Categorias/Categoria.php`
- Extiende `Model` (o `Authenticatable` si es usuario JWT)
- Define `$table`, `$primaryKey`, `$fillable`, `$casts`, `$attributes`, `$hidden`
- Define relaciones

### 2. Servicio
`app/Services/categorias/CategoriasServices.php` (nota: lowercase)
- Constructor con DI de otros services si necesario
- Métodos que retornan `['error'=>bool, ...]`
- Try-catch en cada método

### 3. Form Request (opcional, recomendado)
`app/Http/Requests/Categorias/StoreCategoriaRequest.php`
- `authorize()` → `true`
- `prepareForValidation()` para normalizar
- `rules()` con validaciones
- `messages()` en español
- Métodos helper `toCategoriaData()` si conviene

### 4. Controlador
`app/Http/Controllers/Categorias/CategoriasController.php`
- Extiende `Controller`
- Constructor injection del service
- Métodos: validar → llamar service → `$this->apiResponse()`, `$this->success()`, `$this->error()`, o inline `response()->json()`

### 5. Rutas
Crear `routes/api/categorias.php` con las rutas del módulo.

En `routes/api.php`, dentro del grupo `system:general`, agregar:
```php
Route::prefix('categorias')->group(function () {
    require __DIR__.'/api/categorias.php';
});
```

### 6. Singletons (si aplica)
Si el service debe ser singleton, registrarlo en `AppServiceProvider::register()`:
```php
$this->app->singleton(CategoriasServices::class);
```

### Patrón de respuesta estándar
```json
// Éxito
{ "error": false, "message": "Creado correctamente", "data": {...} }

// Error
{ "error": true, "error_type": "logic|database|connection", "message": "...", "file": "...", "line": 123 }
```

## Cloudinary (`app/Services/Cloudinary/CloudinaryService.php`)

| Método | Uso |
|--------|-----|
| `uploadFile(UploadedFile, folder)` | Sube archivo, retorna `['error','message','data']` |
| `getFileUrl(publicId, format)` | Genera URL |
| `deleteFile(publicId, resourceType)` | Elimina |

- **PDFs** → `resource_type: 'image'` (no raw, el service actual lo trata como image)
- **Office docs** → `resource_type: 'raw'`
- `public_id` sin extensión para `raw` (Cloudinary agrega `.temp`)
- Límite 10MB, extensiones permitidas: jpg, jpeg, png, webp, pdf

## Hikvision (`app/Services/Hikvisionattendance/hikvisionattendanceService.php` ~2200 líneas)

- Protocolo ISAPI con digest auth
- Multi-terminal: `fanOut()` para operaciones en todos los dispositivos
- Captura biométrica (`capturarHuella`, `capturarTarjeta`, `capturarRostro`) → un terminal específico (`deviceId`)
- `client(string $deviceId)` memoiza client por terminal
- `GET /hikvision/devices` lista terminales
- Config en `.env`: `HIKVISION_HOST/PORT/PROTOCOL/USERNAME/PASSWORD`, `HIKVISION_HOSTS` (formato `"Nombre@host:port,Nombre2@host2:port2"`)

## Email

### Mailables (`app/Mail/`)
| Clase | Template | Vía |
|-------|----------|-----|
| `GenericMail` | `emails.generic` | `build()` (old) |
| `PasswordRestoreEmail` | `emails.passwordResotre` (markdown) | `envelope()`+`content()` (new) |
| `RequestEmail` | `emails.sendRequestEmail` | View |
| `RequestForm` | `emails.formAdmission` (markdown) | Stub |
| `RecordatorioPrestamosEmail` | `emails.recordatorioPrestamos` | Adjunta PDF |

### MailService (`app/Services/MailService.php`)
- `sendView($to, $subject, $view, $data)` — envía view directamente
- `sendGeneric($to, $titulo, $contenido)` — envía `GenericMail`
- `send($to, Mailable $mailable)` — envía mailable
- **Filtra correos inválidos** antes de enviar (evita excepciones que bloquean todo el lote)

### Eventos → Listeners
| Evento | Listener | Queue | Retries |
|--------|----------|-------|---------|
| `PasswordRestore` | `SendPasswordRestore` | default | 3 (backoff 10s) |
| `RequestEmailAdmission` | `SendRequestEmailAdmission` | `emails` | 3 (backoff 60s) |
| `RequestFormAdmission` (stub) | `SendRequestFormAdmission` (stub) | — | — |

**Binding manual** en `AppServiceProvider::register()` (NO existe `EventServiceProvider.php` aunque está enlistado en `bootstrap/providers.php`).

## Configuración relevante

| Archivo | Clave |
|---------|-------|
| `config/jwt.php` | `ttl` 60min, `refresh_ttl` 20160min (14d) |
| `config/auth.php` | Default guard: `api` (jwt driver) |
| `config/cors.php` | Orígenes: localhost:3000,5173,5174,4000 + Vercel + gestorsami |
| `config/queue.php` | Default: `database`, cola `emails` para admisiones |
| `config/mail.php` | Default: `log` |
| `config/services.php` | Google OAuth, Hikvision, SAMI SSO |

## Comandos útiles

```bash
php artisan migrate
php artisan make:controller Modulo/ModuloController
php artisan make:model Models/Modulo/Modulo -m
php artisan make:request Modulo/StoreModuloRequest
php artisan cache:clear && php artisan route:clear && php artisan config:clear
php artisan queue:listen --tries=1 --queue=emails,default
```

## Notas / Quirks

- `EventServiceProvider.php` no existe en disco aunque está referenciado en `bootstrap/providers.php` — hacer binding en `AppServiceProvider::register()`.
- Muchos controllers NO usan `$this->apiResponse()` — usan `response()->json()` inline. Al crear nuevo código, usa los helpers del base Controller. Si modificas existente, respeta el patrón local.
- Servicios NO siempre extienden `Service.php` — es optativo.
- `config/cloudinary.php` — PDFs se suben como `image`, no `raw` (el service actual usa `image` para PDFs).
- No hay `app/Services/Auth/AuthServices.php` — la lógica de auth vive en `AuthController` y `JwtService`.
- `Authenticatable` vs `Model`: Usuario usa `Authenticatable` + `JWTSubject`; demás modelos usan `Model`.
