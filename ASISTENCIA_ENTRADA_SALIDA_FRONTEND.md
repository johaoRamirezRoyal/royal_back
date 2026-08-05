# Asistencia trabajadores: entrada/salida — contrato para frontend

Contexto: `asistencia_gestion` ahora registra **dos marcaciones por día** (entrada
y salida) en la misma fila, en vez de una sola. Esto afecta a todos los
endpoints bajo `/api/asistencia-gestion` que ya estaban en uso.

## 1. Campo nuevo: `hora_salida`

Toda fila que devuelven `GET /api/asistencia-gestion`, `/ultimos-registros`,
etc. ahora trae `hora_salida` junto a `hora_asistencia`:

```json
{
  "id": 501,
  "id_user": 100,
  "fecha_asistencia": "2026-08-05",
  "hora_asistencia": "07:05:00",
  "hora_salida": null,
  "fechareg": "2026-08-05T07:05:03.000000Z",
  "puntualidad": "a tiempo",
  "estado": "llegada"
}
```

- `hora_asistencia` sigue siendo la **hora de entrada** (nombre histórico, no
  cambió — no lo confundan con "cualquier asistencia").
- `hora_salida` es **nullable**: `null` significa que el trabajador ya marcó
  entrada hoy pero todavía no ha marcado salida. Se llena solo cuando marca
  salida en el dispositivo.
- No hay un campo `tipo`/`Entry`/`Exit` en la fila — el estado se lee
  directamente de si `hora_salida` está o no está presente:

| `hora_asistencia` | `hora_salida` | Interpretación en UI |
|---|---|---|
| presente | `null` | Marcó entrada, aún no ha marcado salida ("en turno") |
| presente | presente | Jornada completa (entrada y salida marcadas) |

## 2. Reglas de negocio (ya aplicadas en el backend, no requieren validación extra en frontend)

- Solo se puede marcar **una entrada y una salida por día** por usuario.
- No se puede marcar salida sin haber marcado entrada ese mismo día.
- Marcaciones extra el mismo día (3ra, 4ta, ...) se descartan silenciosamente
  en el backend — no generan fila nueva ni sobrescriben la existente.

## 3. Registro manual (`POST /api/asistencia-gestion`)

Sigue aceptando solo `id_user`, `fecha_asistencia`, `hora_asistencia` (registra
la entrada). **No admite `hora_salida` todavía** — para marcar la salida de un
registro manual hay que pedir ese endpoint si se necesita desde UI; por ahora
`hora_salida` solo la completa el push automático del dispositivo Hikvision.

## 4. Filtros existentes

`hora_desde`/`hora_hasta` en `GET /api/asistencia-gestion` siguen filtrando
por `hora_asistencia` (hora de entrada), no por `hora_salida`.
