# Pruebas de estrés del portal para padres (2026-11-16)

Primera prueba de carga de OMNIA, sobre el portal para padres y «Fotos del curso» (ver
[`FOTOS_CURSO_2026_11_14.md`](FOTOS_CURSO_2026_11_14.md)). Cómo correrla y leer el panel:
[`tests/carga/README.md`](../tests/carga/README.md). Este documento recoge qué se montó, qué salió y
qué sigue.

## Qué se montó (`tests/carga/`)

- **k6** (Grafana Labs, `winget install GrafanaLabs.k6`; probado con v2.4.0): `portal-padres.js`
  simula padres (Inicio: `hijos`, `semana`, `fotos/calendario`, `fotos?mes=`; el 30 % busca fotos)
  y docentes («Fotos del curso»: `cursos`, listado, `opciones`). Solo lecturas. Perfiles `humo`
  (corto) y `estres` (escalones hasta 100 padres y 20 docentes, ~4,5 min). Una sesión por perfil
  creada en `setup()`: el login no se mide.
- **InfluxDB 1.8 + Grafana 11.2** con Docker (`docker-compose.yml`): k6 envía las métricas con
  `-o influxdb=http://localhost:8086/k6` y Grafana (http://localhost:3000, sin login, solo local)
  trae provisionado el panel «k6 — OMNIA» (`grafana/k6.json`).
- Las contraseñas de las cuentas de prueba van por variables de entorno (`PADRE_PASS`,
  `DOCENTE_PASS`), nunca en el repo.

## Resultado (local, `php artisan serve`, base `sami_royal`)

| Usuarios a la vez | Peticiones/s | p50 | p95 | Errores |
|---|---|---|---|---|
| 5 | 3,2 | 134 ms | 363 ms | 0 % |
| 15 | 9,8 | 500 ms | 1,3 s | 0 % |
| 24 | 11,3 | 1,6 s | 2,7 s | 0 % |
| 58 | 11,5 | 5,8 s | 6,9 s | 0 % |
| 117 | 11,3 | 12,9 s | 15,3 s | 0 % |
| 120 | 12,2 | 14,6 s | 17,1 s | 23,6 % |

Total: 2 507 peticiones, 3,4 % de errores (todos «conexión rechazada» en el último tramo). Ningún
umbral se cumplió (p95 de 15 s contra 0,8–1 s).

| Endpoint (sin carga) | Tiempo típico |
|---|---|
| `GET …/hijos/{id}/semana` | ~280 ms |
| `GET …/hijos/{id}/fotos/calendario` | ~200–270 ms |
| `GET …/hijos` | ~120 ms |
| `GET …/hijos/{id}/fotos?mes=` | ~120 ms |

## Qué significa

- **El techo (~11,5 peticiones/s desde ~20 usuarios) es del servidor de desarrollo, no de la
  aplicación**: `php artisan serve` (el servidor integrado de PHP) atiende una petición a la vez. A
  partir del techo solo crece la cola: la latencia sube en línea con los usuarios y, cuando la cola
  se llena, el servidor rechaza conexiones. Producción (nginx + PHP-FPM con varios procesos) tiene
  otro techo, que esta prueba **no** mide.
- **Sí sirve para comparar endpoints**: cada petición cuesta ~85 ms de servidor en promedio
  (1 / 11,5 por segundo), y `semana` y `fotos/calendario` son los más caros.

## Pendientes / recomendaciones

1. **Medir la capacidad real**: correr el perfil `estres` contra un servidor de pruebas con la
   misma infraestructura que producción (nunca contra producción sin una ventana acordada).
2. **`semana` trae el historial completo de llegadas tarde del hijo** en cada visita
   (`LlegadasTarde::obtenerLlegadasTarde`): con años de datos crecerá. Opción: limitar al periodo
   o al año activo, o paginar por semana como las fotos.
3. **`fotos/calendario`** agrupa todas las fotos del año en cada petición: si crece, cachearlo por
   curso (se invalida al publicar o borrar).
4. Repetir la prueba después de cada cambio de rendimiento y comparar con esta tabla.

## Notas del entorno (máquina de desarrollo, no del repo)

- Con el disco C: lleno, Docker no pudo escribir y la imagen `influxdb:1.8` quedó con una capa
  dañada (`snapshots/…/fs: no such file or directory` al crear el contenedor, y `docker system df`
  falla). Volver a descargarla no basta porque Docker reusa la capa; por eso el compose usa
  `influxdb:1.8-alpine`, que se apoya en otras capas.
- El disco de datos de Docker Desktop (`%LOCALAPPDATA%\Docker\wsl\disk\docker_data.vhdx`) se movió
  a `D:\DockerData\wsl\disk` con una unión (junction) en la ruta original, para no llenar C:.
- `php artisan serve` en Windows escucha solo en IPv6 (`[::1]:8000`) y al reiniciarse puede dejar
  procesos `php -S` viejos escuchando en el mismo puerto: si los números salen raros, reinícialo.
