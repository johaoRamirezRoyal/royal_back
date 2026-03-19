# Royal Backend 

## Endpoints
### AUTH
- /api/auth/register: registrar un usuario (Publico)
- /api/auth/login: Iniciar sesión devolviendo token e información (Publico)
- api/auth/logout: Cerrar Sesión (Privado)

### Usuarios
- /api/usuarios?per-page=10&page=1: Devolver lista de usuarios paginados (Privado)
- /api/usuarios/all: Devuelve todos los usuarios (Privado)