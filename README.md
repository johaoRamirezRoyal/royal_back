# Royal Backend 

## Endpoints
### AUTH
- /api/auth/register: registrar un usuario (Publico)
- /api/auth/login: Iniciar sesión devolviendo token e información (Publico)
- api/auth/logout: Cerrar Sesión (Privado)

### Usuarios
- /api/usuarios?per-page=10&page=1: Devolver lista de usuarios paginados (Privado)
- /api/usuarios/all/activos: Devuelve todos los usuarios activos (Privado)
- GET /api/usuarios/all/general: Devuelve todos los usuarios generales (Privado)
- GET /api/usuarios/permiso?opt={id_opcion}&per={id_perfil}: Devuelve true si tiene permiso o false si no cuenta con permiso (privado)
- GET /api/usuarios/{id} = Devuelve datos relevantes del usuario (Privado)
- PUT /api/usuarios/{id} = Actualiza el usuario. Debe tener un body tal que: (Privado)
```json
{
  "documento": 1193377218,
  "nombre": "Johao Ramirez 2 --",
  "correo": "johaoRamirez2@royalschool.edu.co",
  "user": "joao zzz",
  "pass": "password aquí",
  "perfil": 2,
  "id_nivel": 2
}
```
### Permisos
- GET api/permisos/listado?perfil=2: Muestra el listado de permisos disponibles para un perfil (Privado)
- 