# AGENTS.md — Royal Backend (S.A.M.I.)

Laravel 12 REST API (PHP ^8.2) for school management. JWT auth via `tymon/jwt-auth` (not Sanctum).

## Quick start

```bash
composer run setup        # install, .env, migrate, key gen, npm install/build
composer run dev          # concurrently: serve + queue:listen + vite
composer run test         # config:clear + php artisan test
php artisan serve         # dev server on localhost:8000
```

## Architecture

- **API routes**: `routes/api.php` includes per-module route files (`routes/api/auth.php`, `routes/api/usuarios.php`, etc.)
- **Auth**: JWT stored in httpOnly cookie (`token`/`admissions_token`). Middleware `JwtFromCookie` reads cookie → sets `Authorization` header. Middleware `ValidateSystem` checks JWT `system` claim (`general` vs `admissions`).
- **Two auth systems**: `general` (main platform) and `admissions` (admissions portal). Use `auth:api` + `system:{name}` middleware.
- **Controllers** in `app/Http/Controllers/{Module}/`, **Services** in `app/Services/{Module}/`, **Models** in `app/Models/{Module}/`.
- **Event/Listener binding** happens in `AppServiceProvider` (bind manually), not EventServiceProvider (missing from disk).
- **DB schema managed externally** — only 4 migration files exist; core tables (`usuarios`, `areas`, etc.) are pre-existing.
- **No tests** exist (`tests/` dir absent, only phpunit.xml config).

## Key commands

```bash
php artisan migrate                    # run migrations
php artisan make:controller NameController
php artisan make:model Name -m         # model + migration
php artisan make:request NameRequest
php artisan cache:clear && php artisan route:clear && php artisan config:clear
```

## Cloudinary (PDF/document upload)

- `resource_type: 'raw'` for PDFs and Office docs — never use `auto` or `image`.
- `public_id` MUST NOT include the file extension for `raw` type (Cloudinary appends `.temp` if it does).
- Config in `.env`: `CLOUDINARY_CLOUD_NAME`, `CLOUDINARY_API_KEY`, `CLOUDINARY_API_SECRET`.
- Service: `app/Services/Cloudinary/CloudinaryService.php`.

## Conventions

- **Language**: Spanish — code comments, error messages, commits, docs.
- **Validation**: Form Request classes in `app/Http/Requests/{Module}/`.
- **Responses**: Base `Controller` has `success()`, `error()`, `apiResponse()` helpers.
- **Cookie helper**: `HasAuthCookie` trait on controllers → `makeCookie()` creates httpOnly cookie with dynamic name.
- **Naming**: Module directories are capitalized for Models (`Inventario/`), lowercase for Services (`inventario/`). Route files lowercase except `routes/api/Biblioteca.php`.

## Email

- Mailables in `app/Mail/` (GenericMail, PasswordRestoreEmail, RequestEmail, RequestForm).
- Templates in `resources/views/emails/`.
- Listener `SendRequestEmailAdmission` queues on `emails` connection.
- Password reset listener `SendPasswordRestore` has 3 retries.

## Hikvision

- Attendance device (DS-K1T321MFWX-B) via ISAPI protocol.
- Digest auth, 3 retry attempts.
- Multi-terminal (fan-out): config in `config/services.php` and `.env` (`HIKVISION_HOST/PORT/PROTOCOL/USERNAME/PASSWORD` for the primary device, `HIKVISION_HOSTS` for additional ones, same credentials, format `"host[:port],host2[:port2]"`). Empty/unset `HIKVISION_HOSTS` = single device, unchanged behavior.
- Identity writes (register/update/delete, fingerprint/card/face binding) run on ALL configured terminals so a person can check in from any door; biometric capture (`capturarHuella`/`capturarTarjeta`/`capturarRostro`) targets one explicit terminal (`deviceId` param) since the person is physically at one device. `hikvisionattendanceService::client(string $deviceId)` memoizes one Guzzle Client per terminal; `fanOut()` is the shared helper for the "all terminals" operations.
- `GET /hikvision/devices` lists configured terminal ids for a frontend terminal selector.
- Service: `app/Services/Hikvisionattendance/hikvisionattendanceService.php` (~2200 lines).

## Other

- Google OAuth via Socialite, domain-restricted to `@royalschool.edu.co`.
- JWT TTL: 60min, refresh TTL: 20160min (14 days). Config in `config/jwt.php`.
- `JwtService` is a singleton bound in `AppServiceProvider`.
- Frontend runs on `localhost:5174` (Vite). CORS allows `localhost:3000, 5173, 5174, 4000`.
- No Docker, no CI/CD, no pre-commit hooks.
