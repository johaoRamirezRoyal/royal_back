<?php

use App\Http\Controllers\GestionAcademica\GestionAcademicaController;
use Illuminate\Support\Facades\Route;


/**
 * JSON PARA FILTRAR (Son opcionales todos.)
{
    "nombre": "Matemáticas",
    "codigo": "MAT001",
    "abreviatura": "MAT",
    "estado": 1, //Por defecto trae todos
    "id_area": 1 //Filtra las asignaturas de esa área académica
}
 */
Route::get('/asignaturas', [GestionAcademicaController::class, 'listarAsignaturas']);

Route::get('/asignaturas/{id}', [GestionAcademicaController::class, 'obtenerAsignatura']);

/**
 * JSON para crear asignaturas
{
    "nombre": "Educación Física",
    "codigo": "EDF001",
    "abreviatura": "EDF",
    "color": "#8B5CF6",
    "id_area": 1, //Opcional — ver /areas-academicas
    "activo": true
}
 */
Route::post('/asignaturas', [GestionAcademicaController::class, 'crearAsignatura']);
Route::put('/asignaturas', [GestionAcademicaController::class, 'actualizarAsignatura']);
Route::delete('/asignaturas', [GestionAcademicaController::class, 'eliminarAsignatura']);

/**
 * Áreas académicas (agrupan asignaturas — ver id_area arriba).
 * JSON para filtrar (opcionales): { "nombre": "Ciencias", "estado": 1 }
 */
Route::get('/areas-academicas', [GestionAcademicaController::class, 'listarAreasAcademicas']);

Route::get('/areas-academicas/{id}', [GestionAcademicaController::class, 'obtenerAreaAcademica']);

/**
 * JSON para crear/actualizar área académica: { "nombre": "Ciencias Naturales", "activo": true }
 * (actualizar además requiere "id" en el body, igual que /asignaturas)
 */
Route::post('/areas-academicas', [GestionAcademicaController::class, 'crearAreaAcademica']);
Route::put('/areas-academicas', [GestionAcademicaController::class, 'actualizarAreaAcademica']);
Route::delete('/areas-academicas', [GestionAcademicaController::class, 'eliminarAreaAcademica']);

/**
 * JSON para listar las asignaturas de un docente o de todos los docentes, se puede filtrar
{
    "usuario": 24,
    "asignatura": [1,2,3],
    "s": "nombre docente",
    "per-page": 10
}
 */
Route::get('/docentes-asignaturas', [GestionAcademicaController::class, 'listarDocentesAsignaturas']);

/**
 * JSON para asignar asignaturas a un docente
{
    "id_user": 24,
    "asignaturas": [2,3,4]
}
 */
Route::post('/docentes-asignaturas', [GestionAcademicaController::class, 'asignarAsignaturasDocente']);
/** 
 * JSON para eliminar asignaturas de docente
{
    "ids": [1,2,3]
}
*/
Route::delete('/docentes-asignaturas', [GestionAcademicaController::class, 'eliminarAsignaturasDocente']);

/**
 * JSON para listar carga académica
 */
Route::get('/carga-academica', [GestionAcademicaController::class, 'listarCargaAcademica']);

/**
 * JSON para crear carga académica
{
    "id_curso": 1,
    "id_docente_asignatura": 2
}
 */
Route::post('/carga-academica', [GestionAcademicaController::class, 'crearCargaAcademica']);

/**
 * JSON para cambiar estado de carga académica
{
    "ids": [1,2,3],
    "estado": 0
}
 */
Route::put('/carga-academica/estado', [GestionAcademicaController::class, 'cambiarEstadoCargaAcademica']);


/**
http://localhost:8000/api/gestion-academica/franjas-horarias?id_esquema=3&id_dia_semana=2&disponible=1&id_carga_academica=5
'disponible=1' filtra solo las franjas que aún no tienen un horario de clase asignado.
Si además se envía 'id_carga_academica', solo se excluyen las franjas ya asignadas a ESA carga académica
(las asignadas a otras cargas académicas se siguen mostrando como disponibles).

En vez de 'id_esquema' también se puede enviar 'id_curso' + 'id_anio_escolar' — el backend
resuelve el esquema a partir del nivel de ese curso (usado por la pestaña "Horario" y por
el autoservicio de horario del docente, que solo conocen el curso, no el esquema):
http://localhost:8000/api/gestion-academica/franjas-horarias?id_curso=1&id_anio_escolar=2&disponible=1
 */
Route::get('/franjas-horarias', [GestionAcademicaController::class, 'verFranjasHorarias']);

/**
http://localhost:8000/api/gestion-academica/franjas-horarias
{
  "id_esquema": 3,
  "id_dia_semana": 2,
  "hora_inicio": "07:55:00",
  "hora_fin": "08:50:00",
  "orden": 3
}
 */
Route::post('/franjas-horarias', [GestionAcademicaController::class, 'crearFranjaHoraria']);

/**
http://localhost:8000/api/gestion-academica/franjas-horarias/tipo
{
    "ids": [3, 4],
    "id_esquema": 2
}
 */
Route::put('/franjas-horarias/tipo', [GestionAcademicaController::class, 'actualizarTipoFranjaHoraria']);

/**
http://localhost:8000/api/gestion-academica/franjas-horarias/orden

{
  "franjas": [
    {
      "id": 3,
      "orden": 3
    },
    {
      "id": 4,
      "orden": 4
    }
  ]
}
 */
Route::put('/franjas-horarias/orden', [GestionAcademicaController::class, 'actualizarOrdenFranjasHorarias']);

/**
http://localhost:8000/api/gestion-academica/franjas-horarias/horario

{
  "id": 4,
  "hora_inicio": "09:55:00",
  "hora_fin": "11:00:00"
}
*/
Route::put('/franjas-horarias/horario', [GestionAcademicaController::class, 'actualizarHorarioFranja']);

/**
http://localhost:8000/api/gestion-academica/franjas-horarias/quitar-otros-dias

Inverso de "aplicar a todos los días": vuelve a asignable=true las franjas de OTROS días
del mismo esquema con esa misma hora, dejando la franja $id (la principal) tal como está.
{
  "id": 4
}
*/
Route::put('/franjas-horarias/quitar-otros-dias', [GestionAcademicaController::class, 'quitarNoAsignableDeOtrosDias']);

/**
 * DELETE /gestion-academica/franjas-horarias

{
    "ids": [1, 2, 3]
}
*/
Route::delete('/franjas-horarias', [GestionAcademicaController::class, 'eliminarFranjaHoraria']);

/**
 * http://localhost:8000/api/gestion-academica/horario?id_docente=24&id_curso=1&id_asignatura=5&id_dia_semana=2
 */
Route::get('/horario', [GestionAcademicaController::class, 'verHorario']);

/**
 * http://localhost:8000/api/gestion-academica/horario
{
    "id_franja_horaria": 1,
    "id_carga_academica": 2,
    "tipo": "CLASE"
}
 */
Route::post('/horario', [GestionAcademicaController::class, 'crearHorarioClase']);

/**
 * http://localhost:8000/api/gestion-academica/horario
{
    "ids": [1, 2, 3]
}
 */
Route::delete('/horario', [GestionAcademicaController::class, 'eliminarHorarios']);

/**
 * http://localhost:8000/api/gestion-academica/asistencias-clase?id_horario_clase=1&fecha=2026-07-02
 *
 * id_horario_clase también acepta un array: ?id_horario_clase[]=1&id_horario_clase[]=2
 * fecha_inicio/fecha_fin (Y-m-d) filtran por rango en vez de un día exacto;
 * fecha_fin requiere fecha_inicio. Ejemplo:
 * ?id_horario_clase[]=1&id_horario_clase[]=2&fecha_inicio=2026-07-01&fecha_fin=2026-07-31
 */
Route::get('/asistencias-clase', [GestionAcademicaController::class, 'verAsistenciasClase']);

/**
 * http://localhost:8000/api/gestion-academica/asistencias-clase
{
    "id_horario_clase": 1,
    "fecha": "2026-07-02",
    "estado": "DICTADA",
    "observacion": "Clase normal"
}
 */
Route::post('/asistencias-clase', [GestionAcademicaController::class, 'crearAsistenciaClase']);

/**
 * http://localhost:8000/api/gestion-academica/asistencias-clase
{
    "id": 1,
    "estado": "CANCELADA",
    "observacion": "Feriado"
}
 */
Route::put('/asistencias-clase', [GestionAcademicaController::class, 'actualizarAsistenciaClase']);

/**
 * http://localhost:8000/api/gestion-academica/asistencias-estudiante
 * Todos los parámetros son opcionales, pero si ninguno se envía se exige id_horario_clase.
 * ?id_estudiante=1&id_curso=1&fecha=2026-07-02&id_clase=1&id_horario_clase=1
 */
Route::get('/asistencias-estudiante', [GestionAcademicaController::class, 'verAsistenciasEstudiantes']);

/**
 * http://localhost:8000/api/gestion-academica/asistencias-estudiante
{
    "id_asistencia_clase": 1,
    "estudiantes": [
        { "id_alumno": 1, "estado": "AUSENTE" },
        { "id_alumno": 2, "estado": "TARDE" }
    ]
}
 */
Route::post('/asistencias-estudiante', [GestionAcademicaController::class, 'crearAsistenciaEstudiantes']);

/**
 * http://localhost:8000/api/gestion-academica/asistencias-estudiante
{
    "ids": [1, 2, 3]
}
 */
Route::delete('/asistencias-estudiante', [GestionAcademicaController::class, 'eliminarAsistenciaEstudiante']);

/**
 * http://localhost:8000/api/gestion-academica/asistencias-metricas?fecha_inicio=2026-08-01&fecha_fin=2026-08-31&id_curso=1
 * Dashboard de solo lectura (opción 102, además de la 99): asistencia agregada por
 * curso (clases dictadas, ausencias/tardanzas/permisos, % de asistencia) y el top 10
 * de estudiantes con más ausencias en el rango. Todos los parámetros son opcionales.
 */
Route::get('/asistencias-metricas', [GestionAcademicaController::class, 'verMetricasAsistencia']);

/**
 * http://localhost:8000/api/gestion-academica/mis-cursos
 * Autoservicio del docente: cursos donde tiene carga académica activa (self-scoped por
 * $request->user()->id_user) — usado para poblar el filtro de curso del dashboard de
 * métricas en vez de listar todos los cursos del colegio.
 */
Route::get('/mis-cursos', [GestionAcademicaController::class, 'obtenerMisCursos']);

/**
 * Esquemas de horario: plantilla de franjas horarias por (nivel × año escolar) —
 * ej. "Primaria 2026", "Bachillerato 2026". Las franjas horarias (arriba) ahora
 * pertenecen a un esquema en vez de directamente a un año escolar.
 *
 * http://localhost:8000/api/gestion-academica/esquemas-horario?id_anio_escolar=2&id_nivel=3
 */
Route::get('/esquemas-horario', [GestionAcademicaController::class, 'listarEsquemasHorario']);

/**
http://localhost:8000/api/gestion-academica/esquemas-horario
{
    "nombre": "Primaria 2026",
    "id_nivel": 3,
    "id_anio_escolar": 2
}
 */
Route::post('/esquemas-horario', [GestionAcademicaController::class, 'crearEsquemaHorario']);

/**
http://localhost:8000/api/gestion-academica/esquemas-horario
{
    "id": 1,
    "nombre": "Primaria 2026 (jornada única)",
    "activo": true
}
 */
Route::put('/esquemas-horario', [GestionAcademicaController::class, 'actualizarEsquemaHorario']);

/**
http://localhost:8000/api/gestion-academica/esquemas-horario
{
    "ids": [1, 2]
}
 */
Route::delete('/esquemas-horario', [GestionAcademicaController::class, 'eliminarEsquemaHorario']);

/**
 * Autoservicio de horario del docente: el docente autenticado arma su propio horario.
 * id_docente siempre se toma del usuario autenticado (request->user()), nunca de un
 * parámetro — no hay selector de "otro docente" en estos endpoints.
 *
 * Menú lateral: todos los cursos, y dentro de cada uno las asignaturas que el docente
 * autenticado ya tiene asignadas (academico_docente_asignatura), marcando cuáles ya
 * tienen horario reservado en el año escolar dado.
 * http://localhost:8000/api/gestion-academica/mi-horario/menu?id_anio_escolar=2
 */
Route::get('/mi-horario/menu', [GestionAcademicaController::class, 'verMiMenuHorario']);

/**
 * Horario propio ya reservado (mismo formato que GET /horario filtrado por el docente
 * autenticado).
 * http://localhost:8000/api/gestion-academica/mi-horario
 */
Route::get('/mi-horario', [GestionAcademicaController::class, 'verMiHorario']);

/**
 * Aparta una franja para una asignatura propia en un curso. Solo se pueden elegir
 * franjas del esquema del nivel de ese curso en el año escolar indicado, y que estén
 * disponibles (ver GET /franjas-horarias?id_curso=&disponible=1 arriba).
http://localhost:8000/api/gestion-academica/mi-horario
{
    "id_curso": 1,
    "id_asignatura": 5,
    "id_franja_horaria": 12,
    "id_anio_escolar": 2
}
 */
Route::post('/mi-horario', [GestionAcademicaController::class, 'reservarMiHorario']);

/**
 * Actualiza la descripción de un bloque propio ya reservado (falla si el id no
 * pertenece al docente autenticado).
http://localhost:8000/api/gestion-academica/mi-horario
{
    "id": 10,
    "descripcion": "Clase de refuerzo"
}
 */
Route::put('/mi-horario', [GestionAcademicaController::class, 'actualizarDescripcionMiHorario']);

/**
 * Elimina bloques del horario propio (falla si alguno de los ids no pertenece al
 * docente autenticado).
http://localhost:8000/api/gestion-academica/mi-horario
{
    "ids": [10, 11]
}
 */
Route::delete('/mi-horario', [GestionAcademicaController::class, 'eliminarMiHorario']);

/**
 * Configuración del calendario académico (A o B — ver AnioEscolarServices) y gestión de
 * años escolares (creación manual + habilitar/deshabilitar). El listado en sí sigue
 * viviendo en GET /compartido/anio-academico/todos (compartido con Admisiones, sin gate).
 */
Route::get('/configuracion-calendario', [GestionAcademicaController::class, 'obtenerConfiguracionCalendario']);
Route::put('/configuracion-calendario', [GestionAcademicaController::class, 'actualizarConfiguracionCalendario']);

/**
 * POST /gestion-academica/anios-escolares
 * Body: { "anio_inicio": 2026 }
 */
Route::post('/anios-escolares', [GestionAcademicaController::class, 'crearAnioEscolarManual']);

/**
 * PUT /gestion-academica/anios-escolares/estado
 * Body: { "id": 8, "activo": false }
 */
Route::put('/anios-escolares/estado', [GestionAcademicaController::class, 'actualizarEstadoAnioEscolar']);
