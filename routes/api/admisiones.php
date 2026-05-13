<?php

/** Ejemplo JSON para registrar una inscripcion:
 * 
        {
            "estado": "PENDIENTE",
            "id_usuario_registro": 3123,
            "anio_academico": 8,
            "fecha_inscripcion": "2026-08-15"
        }
 */

use App\Http\Controllers\Admissions\AdmissionsController;
use Illuminate\Support\Facades\Route;

Route::post('/inscripcion', [AdmissionsController::class, 'registrarInscripcion']);

/**
 * JSON para mostrar inscripciones de acudiente:
        {
            "id_acudiente": 3123
        }
 */
Route::get('/inscripcion/usuario', [AdmissionsController::class, 'mostrarTodasIncripcionesAcudiente']);
Route::get('/inscripcion', [AdmissionsController::class, 'obtenerInformacionCompletaDeInscripcionMedianteCodigo']);
Route::put('/inscripcion/estado', [AdmissionsController::class, 'actualizarEstadoDeInscripcionAspirante']);

/** Ejemplo JSON para actualizar datos de un aspirante:
        {
            "id": 2,
            "lugar_nacimiento": "Bogotá, Colombia",
            "fecha_nacimiento": "2015-05-20",
            "edad": 9,
            "sexo": "Femenino",
            "lengua_materna": "Español",
            "otros_idiomas": "Inglés básico",
            "religion": "Católica",
            "vive_con": "Ambos Padres", //-> Es un ENUM con los valores ["Padre", "Madre", "Ambos Padres", "Acudiente"]
            "num_hermanos": 2,
            "posicion_entre_hermanos": 1,
            "tiene_hermanos_colegio": true,
            "info_hermanos_colegio": "Su hermano mayor está en 5to grado.",
            "antecedentes_escolares": "Viene del Jardín Infantil 'Los Pinos'."
        }
 */
Route::put('/aspirante', [AdmissionsController::class, 'actualizarRegistroAspirante']);

Route::get('/aspirante', [AdmissionsController::class, 'mostrarInformacionAspiranteId']);

/** Ejemplo JSON para agregar al aspirante (Datos minimos necesarios): 
        {
            "nombre_completo": "Juan Pérez García",
            "grado_aplica": "Primero de Primaria",
            "id_inscripcion": 1,
            "anio_academico": 8
        }
 */
Route::post('/aspirante', [AdmissionsController::class, 'registrarAspirante']);

Route::delete('/aspirante', [AdmissionsController::class, 'eliminarRegistroAspirante']);

/**
 * Ejemplo JSON para registrar a un acudiente (Datos minimos):
        {
            "id_aspirante": 1,
            "id_inscripcion": 1,
            "tipo_parentesco": "Acudiente",
            "nombre_completo": "María López"
        }
 */
Route::post('/acudiente', [AdmissionsController::class, 'agregarFamiliarAspirante']);

/**
 * Ejemplo para actualizar a un acudiente completamente:
        {
            "id": 1,
            "aspirante_id": 1,
            "id_inscripcion": 1,
            "tipo_parentesco": "Padre",
            "nombre_completo": "María López",
            "documento_identidad": "52456789",
            "lugar_expedicion_doc": "Medellín",
            "estado_civil": "Casada",
            "idiomas": "Español",
            "direccion_residencia": "Calle 45 # 20 - 10",
            "telefono_fijo": "6044589632",
            "celular": "3001234567",
            "email": "laura.gomez@gmail.com",
            "profesion": "Psicóloga",
            "empresa_labora": "Centro Integral Familiar",
            "cargo_ocupacion": "Psicóloga Clínica",
            "telefono_oficina": "6047894563",
            "fecha_registro": "2026-05-08T10:40:42.000000Z"
        }
 */
Route::put("/acudiente", [AdmissionsController::class, 'actualizarFamiliarAspirante']);

Route::post('/correoInformativo', [AdmissionsController::class, 'correoInformativoSolicitudInicial']);


/**
 * JSON Para añadir información médica
            {
                "aspirante_id": 125,
                "id_inscripcion": 450,
                "medico_nombre": "Dr. Camilo Andrés Pérez",
                "medico_telefono": "+57 300 123 4567",
                "tiene_alergias": true,
                "detalle_alergias": "Alérgico a la penicilina y al polen de flores primaverales.",
                "necesita_cuidados": false,
                "detalle_cuidados": null,
                "recibe_ayuda": true,
                "terapia_ocupacional": true,
                "terapia_lenguaje": false,
                "terapia_psicologica": true,
                "fonoaudiologia": false,
                "terapia_otros": false,
                "profesional_nombre": "Lic. Martha Lucía Gómez",
                "profesional_telefono": "601 234 5678"
            }
 */
Route::post("/informacionMedica", [AdmissionsController::class, 'agregarInformacionMedicaAspirante']);
Route::put("/informacionMedica", [AdmissionsController::class, 'actualizarInformacionMedicaAspirante']);
Route::delete("/informacionMedica", [AdmissionsController::class, 'eliminarInformacionMedicaAspirante']);

/**
 * JSON Para subir documentos, evidentemente es necesario el file.
        {
            "id_inscripcion": 1025,
            "tipo_documento": "registro_civil"
        }
 */
Route::post("/adminsionDocumentos", [AdmissionsController::class, 'subirDocumentoInscripcion']);

Route::post('/testArchivo', [AdmissionsController::class, 'testArchivoGuardar']);
Route::delete('/testArchivo', [AdmissionsController::class, 'testArchivoEliminar']);
