<?php

namespace App\Http\Controllers\Instituciones;

use App\Http\Controllers\Controller;
use App\Http\Requests\Instituciones\InstitucionAdminRequest;
use App\Models\Instituciones\CartaRecomendacion;
use App\Models\Instituciones\ConfiguracionInstituciones;
use App\Models\Instituciones\Institucion;
use App\Services\Cloudinary\CloudinaryService;
use App\Services\Usuarios\UsuariosServices;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class InstitucionAdminController extends Controller
{
    private const OPCION_GESTION = 104;
    // Solo lectura — listado de instituciones y sus documentos, sin crear/editar/
    // activar-desactivar ni tocar la configuración (ver index()/cartas() vs. el resto de
    // métodos de este controller). Otorgada al perfil Admisiones (9), ver
    // 2026_08_31_190000_seed_opcion_ver_instituciones_documentos.
    private const OPCION_LECTURA = 105;

    private const PERFIL_SUPER_ADMIN = 1;

    public function __construct(
        private UsuariosServices $usuariosService,
        private CloudinaryService $cloudinaryService,
    ) {
    }

    /**
     * Chequeo server-side del permiso, no solo ocultar la ruta en el frontend — ver
     * docs/sistema-permisos.md. Por defecto exige la opción de gestión completa; los
     * métodos de solo lectura (index/cartas) pasan también OPCION_LECTURA para aceptar
     * cualquiera de las dos (OR).
     */
    private function sinAcceso(Request $request, ?array $opciones = null): ?JsonResponse
    {
        $perfil = $request->user()->perfil;

        foreach ($opciones ?? [self::OPCION_GESTION] as $opcion) {
            if ($this->usuariosService->tienePermiso($opcion, $perfil)['permiso'] ?? false) {
                return null;
            }
        }

        return $this->error('No tienes permiso para esta acción', 403);
    }

    private function conEstadoDerivado(Institucion $institucion): array
    {
        return [
            'id' => $institucion->id,
            'nombre' => $institucion->nombre,
            'tipo_documento' => $institucion->tipo_documento,
            'email' => $institucion->email,
            'email_verified' => ! is_null($institucion->email_verified_at),
            'activo' => $institucion->activo,
            'primer_ingreso_at' => $institucion->primer_ingreso_at?->toIso8601String(),
            'bloqueada' => $institucion->estaBloqueada(),
            'bloqueo_fecha' => $institucion->fechaBloqueo()?->toIso8601String(),
            'created_at' => $institucion->created_at?->toIso8601String(),
        ];
    }

    public function index(Request $request)
    {
        if ($rechazo = $this->sinAcceso($request, [self::OPCION_GESTION, self::OPCION_LECTURA])) {
            return $rechazo;
        }

        $instituciones = Institucion::query()
            ->orderBy('nombre')
            ->get()
            ->map(fn (Institucion $i) => $this->conEstadoDerivado($i));

        return $this->success('Instituciones obtenidas', $instituciones);
    }

    public function store(InstitucionAdminRequest $request)
    {
        if ($rechazo = $this->sinAcceso($request)) {
            return $rechazo;
        }

        $data = [
            'nombre' => $request->nombre,
            'tipo_documento' => $request->tipo_documento ?? Institucion::TIPOS_DOCUMENTO[0],
            'nit' => Hash::make($request->nit),
            'activo' => true,
        ];

        // El admin puede registrar el correo directamente — queda verificado de una vez,
        // sin pasar por el OTP que usa la propia institución (ver requestEmailOtp()).
        if ($request->filled('email')) {
            $email = strtolower($request->email);
            $data['email'] = $email;
            $data['email_verified_at'] = now();

            // El dominio de Play and Learn pre-asigna el tipo de documento solo si el
            // admin no lo eligió explícitamente en este mismo request.
            if (! $request->filled('tipo_documento')) {
                if ($tipoDocumento = ConfiguracionInstituciones::actual()->tipoDocumentoParaCorreo($email)) {
                    $data['tipo_documento'] = $tipoDocumento;
                }
            }
        }

        $institucion = Institucion::create($data);

        return $this->success('Institución creada', $this->conEstadoDerivado($institucion), 201);
    }

    public function update(InstitucionAdminRequest $request, int $id)
    {
        if ($rechazo = $this->sinAcceso($request)) {
            return $rechazo;
        }

        $institucion = Institucion::find($id);

        if (! $institucion) {
            return $this->error('Institución no encontrada.', 404);
        }

        $data = [];

        // `nombre` es "sometimes" en PUT (ver InstitucionAdminRequest) — si no llega, no
        // se debe escribir NULL encima del valor actual.
        if ($request->filled('nombre')) {
            $data['nombre'] = $request->nombre;
        }

        if ($request->filled('tipo_documento')) {
            $data['tipo_documento'] = $request->tipo_documento;
        }

        // El NIT solo se re-hashea si se envía uno nuevo — dejar el campo vacío conserva
        // el actual (no se puede "mostrar" para editar in place, es un hash).
        if ($request->filled('nit')) {
            $data['nit'] = Hash::make($request->nit);
        }

        // Igual que en store(): el admin puede fijar/corregir el correo directamente,
        // quedando verificado de una vez.
        if ($request->filled('email')) {
            $email = strtolower($request->email);
            $data['email'] = $email;
            $data['email_verified_at'] = now();

            if (! $request->filled('tipo_documento')) {
                if ($tipoDocumento = ConfiguracionInstituciones::actual()->tipoDocumentoParaCorreo($email)) {
                    $data['tipo_documento'] = $tipoDocumento;
                }
            }
        }

        $institucion->update($data);

        return $this->success('Institución actualizada', $this->conEstadoDerivado($institucion->fresh()));
    }

    public function cambiarEstado(Request $request)
    {
        if ($rechazo = $this->sinAcceso($request)) {
            return $rechazo;
        }

        $institucion = Institucion::find($request->input('id'));

        if (! $institucion) {
            return $this->error('Institución no encontrada.', 404);
        }

        $activo = (bool) $request->input('estado');
        $data = ['activo' => $activo];

        // Al deshabilitar una institución que aún no registró correo, se reinicia el
        // plazo de gracia (Institucion::fechaBloqueo) — el tiempo que estuvo deshabilitada
        // no debe contar en su contra; al reactivarse, el próximo login vuelve a marcar
        // primer_ingreso_at y arranca el conteo desde cero.
        if (! $activo && ! $institucion->email_verified_at) {
            $data['primer_ingreso_at'] = null;
        }

        $institucion->update($data);

        return $this->success('Estado actualizado', $this->conEstadoDerivado($institucion));
    }

    /**
     * Documentos (cartas de recomendación) que ha subido una institución — ver
     * InstitucionController::guardarCartaRecomendacion() en el flujo público, que es
     * quien realmente genera y sube el PDF a Cloudinary.
     */
    public function cartas(Request $request, int $id)
    {
        if ($rechazo = $this->sinAcceso($request, [self::OPCION_GESTION, self::OPCION_LECTURA])) {
            return $rechazo;
        }

        $institucion = Institucion::find($id);

        if (! $institucion) {
            return $this->error('Institución no encontrada.', 404);
        }

        $cartas = CartaRecomendacion::where('id_institucion', $id)
            ->orderByDesc('created_at')
            ->get(['id', 'idioma', 'datos', 'documento_url', 'created_at']);

        return $this->success('Documentos obtenidos', $cartas);
    }

    /**
     * Elimina un documento (carta de recomendación) ya enviado — borra el archivo en
     * Cloudinary y el registro. Exclusivo de Super Admin, chequeado por perfil
     * directamente en vez de reusar sinAcceso()/OPCION_GESTION: esa opción (104) hoy
     * coincide, por un bug de seeding ya documentado, con "Compras — Gestión de
     * compras" y también la tiene otorgada el perfil 34 — depender de ella dejaría
     * borrar documentos a un perfil que no debería poder.
     */
    public function eliminarCarta(Request $request, int $institucionId, int $cartaId)
    {
        if ((int) $request->user()->perfil !== self::PERFIL_SUPER_ADMIN) {
            return $this->error('Solo el Super Admin puede eliminar documentos.', 403);
        }

        $carta = CartaRecomendacion::where('id_institucion', $institucionId)->find($cartaId);

        if (! $carta) {
            return $this->error('Documento no encontrado.', 404);
        }

        // Los PDF se suben como resource_type "image" (ver CloudinaryService::getResourceType),
        // no "raw" (el default de deleteFile) — hay que pasarlo explícito o Cloudinary no
        // encuentra el archivo a borrar.
        if ($carta->documento_public_id) {
            $this->cloudinaryService->deleteFile($carta->documento_public_id, 'image');
        }

        $carta->delete();

        return $this->success('Documento eliminado', null);
    }

    /**
     * Días de gracia antes del bloqueo y correo(s) que reciben copia de cada carta —
     * antes vivían en config/instituciones.php (.env), ahora editables desde acá sin
     * tocar el servidor. Ver App\Models\Instituciones\ConfiguracionInstituciones.
     */
    public function configuracion(Request $request)
    {
        if ($rechazo = $this->sinAcceso($request)) {
            return $rechazo;
        }

        return $this->success('Configuración obtenida', ConfiguracionInstituciones::actual());
    }

    public function actualizarConfiguracion(Request $request)
    {
        if ($rechazo = $this->sinAcceso($request)) {
            return $rechazo;
        }

        $request->validate(
            [
                'dias_plazo_bloqueo_correo' => 'sometimes|integer|min:1|max:90',
                'correo_notificacion' => 'sometimes|nullable|string|max:500',
                'dominio_play_and_learn' => 'sometimes|nullable|string|max:255',
            ],
            [
                'dias_plazo_bloqueo_correo.integer' => 'Los días deben ser un número entero.',
                'dias_plazo_bloqueo_correo.min' => 'Los días deben ser al menos 1.',
                'dias_plazo_bloqueo_correo.max' => 'Los días no pueden superar 90.',
                'correo_notificacion.max' => 'El campo de correos no puede superar los 500 caracteres.',
                'dominio_play_and_learn.max' => 'El dominio no puede superar los 255 caracteres.',
            ]
        );

        $config = ConfiguracionInstituciones::actual();
        $config->update($request->only(['dias_plazo_bloqueo_correo', 'correo_notificacion', 'dominio_play_and_learn']));

        return $this->success('Configuración actualizada', $config->fresh());
    }
}
