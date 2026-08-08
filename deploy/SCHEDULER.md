# Scheduler de Laravel en producción (`royal-back-scheduler`)

## Problema

Las salidas automáticas de asistencia (`asistencia:cerrar-vencidas`, ver
`routes/console.php` y `AsistenciaGestionService::cerrarAsistenciasVencidas()`)
nunca se estaban marcando en el servidor, aunque el código y la declaración
`Schedule::command(...)->everyFifteenMinutes()` eran correctos.

Causa: el scheduler de Laravel (`Schedule::` en `routes/console.php`) **no hace
nada por sí solo**. Solo evalúa y dispara tareas cuando algo invoca
`php artisan schedule:run`, y eso hay que ejecutarlo cada minuto desde fuera de
la app (cron de sistema, systemd timer, etc.). En este servidor solo existía
`royal-back-queue.service` (el worker de colas de correo) — no había ningún
proceso disparando `schedule:run`, así que ninguna tarea programada corría
nunca, sin importar cuál fuera.

## Qué se implementó

Dos unidades systemd nuevas en `deploy/`, siguiendo el mismo patrón que
`royal-back-queue.service`:

- **`royal-back-scheduler.service`** — unidad `oneshot` que ejecuta
  `php artisan schedule:run` una vez.
- **`royal-back-scheduler.timer`** — dispara esa unidad cada minuto
  (`OnCalendar=*-*-* *:*:00`), reemplazando el clásico
  `* * * * * php artisan schedule:run` de crontab.

`schedule:run` en sí no ejecuta todo cada vez: revisa qué tareas declaradas en
`routes/console.php` les toca correr en ese minuto según su propia frecuencia
(`everyFifteenMinutes()`, `daily()`, etc.) y solo dispara esas.

## Instalación en el servidor

```bash
sudo cp deploy/royal-back-scheduler.service /etc/systemd/system/royal-back-scheduler.service
sudo cp deploy/royal-back-scheduler.timer /etc/systemd/system/royal-back-scheduler.timer
sudo systemctl daemon-reload
sudo systemctl enable --now royal-back-scheduler.timer
```

## Verificación

```bash
# Confirma que el timer está activo y muestra la próxima ejecución
systemctl list-timers royal-back-scheduler.timer

# Sigue los logs de cada tick (uno por minuto)
journalctl -u royal-back-scheduler -f
```

Para confirmar que `asistencia:cerrar-vencidas` específicamente corre bien,
se puede ejecutar manualmente en cualquier momento:

```bash
php artisan asistencia:cerrar-vencidas
```

## Archivos relacionados

- `routes/console.php` — declaración `Schedule::command('asistencia:cerrar-vencidas')->everyFifteenMinutes()`.
- `app/Console/Commands/CerrarAsistenciasVencidasCommand.php` — comando Artisan.
- `app/Services/AsistenciaTrabajadores/AsistenciaGestionService.php` (método `cerrarAsistenciasVencidas`) — lógica de negocio: busca marcaciones del día con entrada pero sin salida, resuelve el horario aplicable al usuario (`AsistenciaGestion::horarioAplicable`), y si ya pasó `hora_salida_esperada` marca la salida automáticamente y notifica por correo al trabajador y a RH.
- `deploy/royal-back-queue.service` — unidad hermana para el queue worker de emails; el mismo patrón `enable --now` aplica.
