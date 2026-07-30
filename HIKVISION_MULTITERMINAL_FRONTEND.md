# Hikvision multi-terminal — contrato para frontend

Contexto: el backend ahora soporta varios terminales Hikvision (fan-out). Un
mismo empleado/estudiante puede marcar entrada desde cualquier puerta, pero
para **enrolar** una huella/tarjeta/rostro (captura física) el frontend debe
poder elegir en qué terminal físico se hace la captura.

## 1. Listar terminales disponibles

`GET /api/hikvision/devices` (requiere JWT)

```json
// 200
{
  "error": false,
  "message": "OK",
  "data": [
    { "id": "20.20.20.16:80", "name": "Puerta Principal", "host": "20.20.20.16", "port": "80" },
    { "id": "192.168.1.50:8000", "name": "192.168.1.50:8000", "host": "192.168.1.50", "port": "8000" }
  ]
}
```

`id` es el valor que hay que mandar como `deviceId` en los endpoints de abajo
(formato `"host:port"`, estable). `name` es el nombre legible para mostrar en
el selector — si nadie le puso nombre a ese terminal, `name` cae al mismo
valor que `id`. Poblar el selector con `name` mostrando, pero guardando/
enviando `id` (si solo hay 1 terminal, se puede ocultar el selector y usar
ese directo).

### Editar el nombre de un terminal

`PUT /api/hikvision/devices` (requiere JWT)

```json
// body
{ "deviceId": "20.20.20.13:80", "nombre": "Garita abajo" }
```
```json
// 200
{ "error": false, "message": "Nombre actualizado correctamente", "data": { "device_id": "20.20.20.13:80", "nombre": "Garita abajo", "id": 1 } }
```

El nombre queda guardado en BD (no en `.env`) y aparece de inmediato en el
próximo `GET /api/hikvision/devices`. `deviceId` debe ser uno de los que ya
devuelve `GET /devices` — si mandas uno que no está configurado, responde
`error: true`.

## 2. Endpoints que SÍ necesitan `deviceId` (captura física)

La captura solo puede pasar en un terminal concreto, porque la persona está
parada frente a un lector específico. Todos aceptan `deviceId` como campo
**opcional** del body — si se omite, cae al terminal principal (el primero de
la lista de `/devices`). **Para poder elegir terminal, el frontend debe
empezar a mandar `deviceId` explícitamente** en estas 4 rutas:

| Endpoint | Método | Body |
|---|---|---|
| `/api/hikvision/fingerprint/enroll` | POST | `employeeNo` (string, requerido), `fingerPrintID` (int 1-10, opcional), `deviceId` (string, opcional) |
| `/api/hikvision/card/enroll/captura` | POST | `employeeNo` (string, requerido), `cardType` (opcional), `deviceId` (string, opcional) |
| `/api/hikvision/face/enroll` | POST | `employeeNo` (string, requerido), `faceLibraryId` (opcional), `deviceId` (string, opcional) |
| `/api/hikvision/face/enroll/cancelar` | POST | `employeeNo` (string, requerido), `deviceId` (string, opcional — **debe ser el mismo terminal que se usó para iniciar la captura**, si no, no cancela nada) |

Ejemplo:
```json
POST /api/hikvision/fingerprint/enroll
{ "employeeNo": "1234", "fingerPrintID": 1, "deviceId": "192.168.1.50:8000" }
```

## 3. Endpoints que NO necesitan `deviceId`

No hay captura física, o se replican automáticamente a todos los terminales
(fan-out) — no aplica elegir uno:

- `POST /api/hikvision/card/enroll` (cardNo ya conocido)
- `POST /api/hikvision/password/enroll`
- `POST /api/hikvision` (registrar empleado), `/masivo`, `/desactivar`
- `DELETE /api/hikvision`, `/perfil`
- `PUT /api/hikvision/fingerprint/delete`, `/card/delete`

## 4. Imágenes

`GET /api/hikvision/image?path=...&deviceId=...` — `deviceId` opcional (cae
al principal). Las URLs de `faceURL` que devuelve `/api/hikvision/getList` ya
vienen con `deviceId` incluido automáticamente, no hay que armarlas a mano.

## 5. Flujo de UI sugerido

1. Al abrir el modal de "registrar huella/tarjeta/rostro", llamar a
   `GET /api/hikvision/devices` y mostrar un selector.
2. Mandar el `deviceId` elegido en la llamada de captura.
3. Si el usuario cancela, mandar **el mismo `deviceId`** a `.../cancelar`.
4. La respuesta de las operaciones fan-out (registrar/eliminar/etc.) trae una
   clave `devices` con el detalle por terminal, por ejemplo:
   ```json
   {
     "error": false,
     "message": "Proceso completado",
     "devices": {
       "20.20.20.16:80": { "error": false, "message": "OK" },
       "192.168.1.50:8000": { "error": true, "message": "cURL error 28: timeout" }
     }
   }
   ```
   Útil para mostrar "se registró en 1 de 2 terminales" en vez de solo
   éxito/fallo genérico.
