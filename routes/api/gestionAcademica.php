<?php

use App\Http\Controllers\GestionAcademica\GestionAcademicaController;
use Illuminate\Support\Facades\Route;


/**
 * JSON PARA FILTRAR (Son opcionales todos.)
{
    "nombre": "Matemáticas",
    "codigo": "MAT001",
    "abreviatura": "MAT",
    "estado": 1 //Por defecto trae todos
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
    "activo": true
}
 */
Route::post('/asignaturas', [GestionAcademicaController::class, 'crearAsignatura']);
Route::put('/asignaturas', [GestionAcademicaController::class, 'actualizarAsignatura']);
Route::delete('/asignaturas', [GestionAcademicaController::class, 'eliminarAsignatura']);

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
http://localhost:8000/api/gestion-academica/franjas-horarias?id_anio_escolar=2
 */
Route::get('/franjas-horarias', [GestionAcademicaController::class, 'verFranjasHorarias']);

/**
http://localhost:8000/api/gestion-academica/franjas-horarias
{
  "id_anio_escolar": 7,
  "id_dia_semana": 2,
  "hora_inicio": "07:55:00",
  "hora_fin": "08:50:00",
  "orden": 3,
  "tipo": "CLASE"
}
 */
Route::post('/franjas-horarias', [GestionAcademicaController::class, 'crearFranjaHoraria']);

/**
http://localhost:8000/api/gestion-academica/franjas-horarias/tipo
{
    "ids": [3, 4],
    "id_anio_escolar": 2,
    "tipo": "RECESO"
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
