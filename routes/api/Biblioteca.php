<?php

use App\Http\Controllers\Biblioteca\BibliotecaController;
use Illuminate\Support\Facades\Route;

// CATEGORIAS
Route::get("/categorias", [BibliotecaController::class, "mostrarCategoriasBiblioteca"]);
Route::post("/categoria", [BibliotecaController::class, "agregarCategoriaBiblioteca"]);
Route::put("/categoria/estado", [BibliotecaController::class, "cambiarEstadoCategoriaBiblioteca"]);
Route::get("/subcategorias", [BibliotecaController::class, "mostrarSubcategoriasBiblioteca"]);
Route::post("/subcategorias", [BibliotecaController::class, "agregarSubcategoriaBiblioteca"]);
Route::put("/subcategorias/estado", [BibliotecaController::class, "cambiarEstadoSubcategoriaBiblioteca"]);

//LIBROS
/**
 * JSON para consultrar los libros:
    {
        "search": "Johao Prueba",
        "categoria": [2],
        "subcategoria": [4],
        "activo": 1,
        "page": 3
        }
 */
Route::get("/libros", [BibliotecaController::class, "obtenerTodosLosLibrosBiblioteca"]);

/**
 * JSON para editar un libro
        {
          "id_libro": 2066,
          "titulo": "Prueba imagen Libro de Johao actualizado",
          "id_categoria": 2,
          "autor": "Gabriel García Márquez",
          "foto": "captura_png.png"
        }
 */
Route::post("/libro/actualizar", [BibliotecaController::class, 'editarLibro']);

/**
 * JSON para agregar un libro, con todos los datos: 
    {
        "titulo": "Cien años de soledad", //Obligatorio
        "autor": "Gabriel García Márquez", //Obligatorio
        "editorial": "Editorial Sudamericana",
        "edicion": "Primera edición",
        "id_categoria": 1, //Obligatorio
        "id_subcategoria": 2,
        "observacion": "Libro disponible para préstamo en excelente estado.",
        "foto": "https://res.cloudinary.com/demo/image/upload/libros/cien-anos-soledad.jpg",
        "activo": 1
    }
 */
Route::post("/libro", [BibliotecaController::class, "agregarNuevoLibroBiblioteca"]);

Route::put("/libro/estado", [BibliotecaController::class, "cambiarEstadoLibro"]);



/**
 *JSON para añadir ejemplares de un libro
    {
        "id_libro": 2479,
        "cantidad": 6,
        "id_log": 3123
     }
 */
Route::post("/ejemplares", [BibliotecaController::class, "agregarEjemplarLibroBiblioteca"]);
/**
 * JSON para filtrar ejemplares de libros
    {
        "id_libro": "2479",
        "autor": "Johao",
        "page": 1   
    }
 */
Route::get("/ejemplares", [BibliotecaController::class, "verEjemplaresLibroBiblioteca"]);
Route::put("/ejemplares", [BibliotecaController::class, 'cambiarEstadoEjemplarBiblioteca']);

Route::post("/prestamosEjemplar", [BibliotecaController::class, 'prestarEjemplarBiblioteca']);
Route::get("/prestamosEjemplar/historial", [BibliotecaController::class, 'obtenerHistorialPrestamoEjemplarUsuario']);
Route::put("/DevolverPrestamosEjemplar", [BibliotecaController::class, 'devolverPrestamoEjemplarBiblioteca']);
Route::get("/prestamosEjemplar", [BibliotecaController::class, 'verPrestamosDeEjemplar']);
Route::get("/prestamosLibros", [BibliotecaController::class, 'verPrestamosLibro']);


/**
{
  "perpage": 6,
  "search": "Johao"
}
 */
Route::get("/paquetes", [BibliotecaController::class, 'listarPaquetesBiblioteca']);

/**
{
  "nombre": "Paquete de Johao :D",
  "id_log": 3123
}
 */
Route::post("/paquetes", [BibliotecaController::class, 'crearNuevoPaqueteBiblioteca']);

/** 
{
  "ids": [33],
  "estado": 0
}
 */
Route::put("/paquetes", [BibliotecaController::class, 'cambiarEstadoPaqueteBiblioteca']);

/**
{
  "titulo": "Segundo contenido de paquete Johao",
  "id_paquete": 33,
  "id_log": 3123
}
*/
Route::post("/paquetes/contenido", [BibliotecaController::class, 'agregarContenidoPaqueteBiblioteca']);

/**
{
  "ids": [553],
  "estado": 0,
  "id_paquete": 33
}
 */
Route::put("/paquetes/contenido/estado", [BibliotecaController::class, 'cambiarEstadoContenidoPaqueteBiblioteca']);

/**
{
    "id_contenido": 553,
    "titulo": "Nuevo titulo"
}
 */
Route::put("/paquetes/contenido", [BibliotecaController::class, 'editarDatosContenidoPaqueteBiblioteca']);

Route::post("/paquetes/prestamo", [BibliotecaController::class, 'generarPrestamoPaqueteUsuario']);
Route::put("/paquetes/prestamo/devolver", [BibliotecaController::class, 'devolverPrestamoPaqueteUsuario']);
Route::get("/paquetes/prestamo/historial", [BibliotecaController::class, 'mostrarHistorialPrestamosPaquetesUsuario']);


