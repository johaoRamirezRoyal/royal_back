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

Route::get("/estadisticas", [BibliotecaController::class, 'estadisticasEjemplaresBiblioteca']);
Route::post("/imagen", [BibliotecaController::class, 'subirImagenBiblioteca']);

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
Route::get("/ejemplares/verificar", [BibliotecaController::class, "verificarEjemplarBiblioteca"]);
Route::get("/ejemplares/deshabilitados", [BibliotecaController::class, "verEjemplaresDeshabilitadosLibroBiblioteca"]);
Route::put("/ejemplares", [BibliotecaController::class, 'cambiarEstadoEjemplarBiblioteca']);

Route::post("/prestamosEjemplar", [BibliotecaController::class, 'prestarEjemplarBiblioteca']);
Route::get("/prestamosEjemplar/activos", [BibliotecaController::class, 'obtenerPrestamosEjemplarActivos']);
Route::get("/prestamosEjemplar/devueltos", [BibliotecaController::class, 'obtenerPrestamosEjemplarDevueltos']);
Route::get("/prestamosEjemplar/historial", [BibliotecaController::class, 'obtenerHistorialPrestamoEjemplarUsuario']);
/**
{
  "ids": [1, 2, 3]
}
 */
Route::post("/prestamosEjemplar/recordatorio", [BibliotecaController::class, 'enviarRecordatorioPrestamosPendientes']);
/**
{
  "ids": [1, 2, 3] // opcional: si se omite y no hay "cursos", envía a todos los usuarios activos
  "cursos": [3, 7] // opcional: solo usuarios activos con id_curso en el arreglo. Si viene "ids", "ids" tiene prioridad.
}
 */
Route::post("/prestamosEjemplar/pazYSalvoGlobal", [BibliotecaController::class, 'enviarPazYSalvoGlobal']);
Route::get("/prestamosEjemplar/pazYSalvo", [BibliotecaController::class, 'generarPazYSalvoPdf']);
Route::get("/prestamosEjemplar/listadoPdf", [BibliotecaController::class, 'generarListadoPrestamosPdf']);
Route::put("/DevolverPrestamosEjemplar", [BibliotecaController::class, 'devolverPrestamoEjemplarBiblioteca']);
Route::put("/prestamosEjemplar/aplazar", [BibliotecaController::class, 'aplazarPrestamoEjemplarBiblioteca']);
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

/**
{
  "ids": [553],
  "id_paquete": 33
}
 */
Route::delete("/paquetes/contenido", [BibliotecaController::class, 'eliminarContenidoPaqueteBiblioteca']);

Route::get("/paquetes/verificar", [BibliotecaController::class, 'verificarPaqueteBiblioteca']);
Route::post("/paquetes/prestamo", [BibliotecaController::class, 'generarPrestamoPaqueteUsuario']);
Route::put("/paquetes/prestamo/devolver", [BibliotecaController::class, 'devolverPrestamoPaqueteUsuario']);
Route::put("/paquetes/prestamo/aplazar", [BibliotecaController::class, 'aplazarPrestamoPaqueteUsuario']);
Route::get("/paquetes/prestamo/historial", [BibliotecaController::class, 'mostrarHistorialPrestamosPaquetesUsuario']);
Route::get("/paquetes/prestamo/listadoPdf", [BibliotecaController::class, 'generarListadoPrestamosPaquetesPdf']);

// METRICAS
Route::get("/estadisticas/paquetes", [BibliotecaController::class, 'estadisticasPaquetesBiblioteca']);
Route::get("/estadisticas/libros-mas-prestados", [BibliotecaController::class, 'librosMasPrestados']);
Route::get("/estadisticas/categorias-mas-prestadas", [BibliotecaController::class, 'categoriasLibrosMasPrestadas']);
Route::get("/estadisticas/cursos-libros", [BibliotecaController::class, 'cursosConMasPrestamosLibros']);
Route::get("/estadisticas/paquetes-mas-prestados", [BibliotecaController::class, 'paquetesMasPrestados']);
Route::get("/estadisticas/cursos-paquetes", [BibliotecaController::class, 'cursosConMasPrestamosPaquetes']);
Route::get("/estadisticas/perfiles-libros", [BibliotecaController::class, 'perfilesConMasPrestamosLibros']);
Route::get("/estadisticas/perfiles-paquetes", [BibliotecaController::class, 'perfilesConMasPrestamosPaquetes']);
Route::get("/estadisticas/libro/{id}", [BibliotecaController::class, 'estadisticasLibro']);
Route::get("/estadisticas/paquete/{id}", [BibliotecaController::class, 'estadisticasPaquete']);


