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
