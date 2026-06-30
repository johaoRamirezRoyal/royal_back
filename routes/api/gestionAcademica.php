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
