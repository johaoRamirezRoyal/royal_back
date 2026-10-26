# Convenciones de código y módulo nuevo

## Carpetas

| Capa | Ejemplo |
|---|---|
| Models | `app/Models/Inventario/` |
| Controllers | `app/Http/Controllers/Inventarios/` |
| Services | `app/Services/inventario/` o `app/Services/GestionAcademica/` (mezcla mayúscula y minúscula: sigue la del módulo) |
| Requests | `app/Http/Requests/Inventario/` |
| Routes | `routes/api/inventario.php` (todo en minúscula salvo `Biblioteca.php`) |

## Controller

```php
class InventariosController extends Controller
{
    public function __construct(protected InventarioServices $service_inventario) {}
}
```

Métodos del `Controller` base:

| Método | Uso |
|---|---|
| `success(string $message, mixed $data = null, int $status = 200)` | respuesta exitosa simple |
| `error(array\|string $message, int $status = 500, ...)` | error directo |
| `apiResponse(array $response)` | recibe el array del service y detecta el tipo de error (database, connection o logic) |
| `paginatedResponse(array $response, ?string $resourceClass)` | paginación con resource opcional |

## Service

```php
public function listar(): array
{
    try {
        return ['error' => false, 'message' => 'ok', 'data' => Inventario::all()];
    } catch (\Exception $e) {
        return ['error' => true, 'message' => $e->getMessage()];
    }
}
```

Respuesta estándar de la API:
- Éxito: `{ "error": false, "message": "...", "data": {...} }`.
- Error: `{ "error": true, "error_type": "logic|database|connection", "message": "...", "file": "...", "line": 123 }`.

## Model

- Define `$table`, `$primaryKey`, `public $timestamps = false` (las tablas legacy no
  tienen timestamps), `$fillable`, `$casts`, `$attributes` y `$hidden`.
- `Usuario` extiende `Authenticatable` e implementa `JWTSubject` (`id_user`,
  `getAuthPassword()` devuelve `pass`). Los demás modelos extienden `Model`.

## Form Request

`authorize()` devuelve `true` (el permiso va en la ruta y el controller).
`prepareForValidation()` normaliza la entrada y `messages()` va en español.

```php
public function rules(): array
{
    return [
        'nombre' => ['required', 'string', 'max:255'],
        'codigo' => ['required', Rule::unique('inventario', 'codigo')],
    ];
}
```

## Crear un módulo nuevo

1. Modelo en `app/Models/<Modulo>/`.
2. Service en `app/Services/<modulo>/`, con try-catch y el array estándar.
3. Form Request en `app/Http/Requests/<Modulo>/` (recomendado).
4. Controller en `app/Http/Controllers/<Modulo>/`, con el service inyectado y
   `apiResponse()`.
5. Rutas en `routes/api/<modulo>.php`, incluidas en `routes/api.php` dentro del grupo
   `system:general`:
   ```php
   Route::prefix('modulo')->group(function () { require __DIR__.'/api/modulo.php'; });
   ```
   Agrega el middleware `permiso` (ver [`permisos.md`](permisos.md)).
6. Si el service debe ser singleton, regístralo en `AppServiceProvider::register()`.
7. Si creaste una migración, regístrala en `migraciones_sql.sql`.
8. Documenta el módulo en `docs/contexto/modulos/<modulo>.md` y agrégalo al índice de
   `AGENTS.md`.

## Seeders de datos de prueba

`DatabaseSeeder` corre: Asignatura → AreaAcademica → PeriodoAcademico (vía el service,
respeta el calendario configurado) → EsquemaHorario → FranjaHorario → DocenteAsignatura →
CargaAcademica → Horario → AsistenciaClase.

- Resuelve el año activo de forma dinámica; nunca uses `id_anio_escolar=1` fijo.
- Para buscar un docente por nombre contra datos reales (nombres completos en un solo
  campo, `apellido` a veces `"."`), usa el resolver difuso
  `database/seeders/Concerns/ResolvesDocentePorNombre.php`.
