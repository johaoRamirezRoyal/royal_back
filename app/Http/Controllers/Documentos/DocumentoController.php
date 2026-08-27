<?php

namespace App\Http\Controllers\Documentos;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DocumentoController extends Controller
{
    /**
     * Descarga o visualiza un documento del storage público.
     * Query params: directorio, nombre_documento, download (opcional).
     */
    public function descargar(Request $request)
    {
        $request->validate([
            'directorio' => 'required|string|max:500',
            'nombre_documento' => 'required|string|max:500',
            'download' => 'nullable|boolean',
        ]);

        $directorio = trim($request->input('directorio'), '/');
        $nombreDocumento = $request->input('nombre_documento');

        if (str_contains($directorio, '..') || str_contains($nombreDocumento, '..')) {
            return $this->error('Ruta no válida.', 400);
        }

        $ruta = $directorio . '/' . $nombreDocumento;
        $disk = Storage::disk(config('filesystems.uploads_disk', 'public'));

        if (!$disk->exists($ruta)) {
            return $this->error('Documento no encontrado.', 404);
        }

        if ($request->boolean('download')) {
            return $disk->download($ruta);
        }

        return $disk->response($ruta);
    }

    /**
     * Sirve cualquier archivo del disco de uploads (imagen, pdf, etc.).
     * Requiere auth:api. La ruta puede incluir subdirectorios: /api/public/{directorio/archivo}
     */
    public function verPublico(string $ruta)
    {
        if (str_contains($ruta, '..')) {
            abort(404);
        }

        $disk = Storage::disk(config('filesystems.uploads_disk', 'public'));

        if (!$disk->exists($ruta)) {
            abort(404);
        }

        // `private` (no `public`): esta ruta va detrás de auth:api, no debe cachearse en
        // proxies/CDNs compartidos, solo en el navegador del propio usuario. Fotos de
        // carnet y demás archivos acá casi nunca cambian una vez subidos — evita que se
        // vuelvan a descargar completas cada vez que se abre el mismo curso/roster.
        return $disk->response($ruta, null, ['Cache-Control' => 'private, max-age=604800']);
    }
}
