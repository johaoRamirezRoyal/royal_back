# Pruebas de carga (k6 + Grafana)

Prueba de estrés del portal para padres y de «Fotos del curso» con [k6](https://grafana.com/docs/k6/)
(Grafana Labs). Las métricas van a InfluxDB y se ven en vivo en un panel de Grafana. **Solo
lecturas**: no publica ni borra nada.

## Qué simula (`portal-padres.js`)

- **Padres**: abren Inicio (`hijos`, `semana`, `fotos/calendario`, `fotos?mes=`) y el 30 % busca
  fotos (`fotos?q=`), con pausas de 1–3 s como una persona.
- **Docentes**: abren «Fotos del curso» (`/fotos-curso/cursos`, el listado del curso y `opciones`).
- Perfil `estres` (por defecto): sube por escalones hasta 100 padres y 20 docentes a la vez (~4,5 min).
  Perfil `humo`: una pasada corta para comprobar que el script y las cuentas funcionan.
- Umbrales: p95 por endpoint entre 800 ms y 1 s, y menos de 1 % de errores.

## Cómo correrla

Requiere k6 (`winget install GrafanaLabs.k6`) y Docker.

```bash
# 1. InfluxDB + Grafana (panel en http://localhost:3000, sin login)
docker compose -f tests/carga/docker-compose.yml up -d

# 2. La prueba. Las cuentas son las de prueba locales (docs/FOTOS_CURSO_2026_11_14.md); las
#    contraseñas van por variable de entorno, nunca en el repo.
BASE_URL='http://[::1]:8000' PERFIL=estres \
PADRE_CORREO=padre.prueba@gmail.com PADRE_PASS='…' \
DOCENTE_CORREO=docente.prueba@royalschool.edu.co DOCENTE_PASS='…' \
k6 run -o influxdb=http://localhost:8086/k6 tests/carga/portal-padres.js

# 3. Apagar
docker compose -f tests/carga/docker-compose.yml down
```

- `php artisan serve` en Windows escucha solo en IPv6: usa `http://[::1]:8000`, no `localhost`
  (k6 lo resuelve a `127.0.0.1` y el login falla con estado 0).
- `COLEGIO` (opcional, por defecto `royalschool`): el slug del login del acudiente.
- La salida `influxdb` de k6 está marcada como obsoleta para k6 v3; si se quita, se cambia por
  `opentelemetry` o `experimental-prometheus-rw` y la fuente de datos de Grafana.

## Cómo leerla

Panel «k6 — OMNIA»: peticiones, latencia p95, errores y usuarios virtuales; usuarios contra
peticiones por segundo (cuando las peticiones/s dejan de subir aunque suban los usuarios, el
servidor llegó a su techo); latencia p95 por endpoint y una tabla resumen.

**Contra `php artisan serve` los números no son los de producción**: es el servidor de desarrollo de
PHP, un proceso que atiende una petición a la vez. Sirve para comparar endpoints y encontrar los
lentos, no para saber cuántas familias aguanta producción (nginx + PHP-FPM con varios procesos).
Para eso, correrla contra un servidor de pruebas con la misma infraestructura.

## Resultado del 2026-11-16 (local, `php artisan serve`)

| Usuarios a la vez | Peticiones/s | p50 | p95 | Errores |
|---|---|---|---|---|
| 5 | 3,2 | 134 ms | 363 ms | 0 % |
| 15 | 9,8 | 500 ms | 1,3 s | 0 % |
| 24 | 11,3 | 1,6 s | 2,7 s | 0 % |
| 58 | 11,5 | 5,8 s | 6,9 s | 0 % |
| 117 | 11,3 | 12,9 s | 15,3 s | 0 % |
| 120 | 12,2 | 14,6 s | 17,1 s | 23,6 % |

- Techo de ~11,5 peticiones/s desde ~20 usuarios: a partir de ahí solo crece la cola y la latencia.
  Con 120 usuarios el servidor rechaza conexiones (cola de espera llena).
- Sin carga, lo más caro es `semana` (~280 ms: el historial completo de llegadas tarde) y
  `fotos/calendario` (~200–270 ms); `hijos` y `fotos?mes=` rondan 120 ms.
