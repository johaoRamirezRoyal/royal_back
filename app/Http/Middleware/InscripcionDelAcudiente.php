<?php

namespace App\Http\Middleware;

use App\Models\Admisiones\Aspirante;
use App\Models\Admisiones\Documento;
use App\Models\Admisiones\Estado;
use App\Models\Admisiones\Familiares;
use App\Models\Admisiones\InformacionMedica;
use App\Models\Admisiones\Inscripcion;
use App\Models\Admisiones\ReferenciasFamiliares;
use App\Models\HistoriaClinica\HceCognitivoLenguaje;
use App\Models\HistoriaClinica\HceDesarrolloPsicomotor;
use App\Models\HistoriaClinica\HceEmbarazoParto;
use App\Models\HistoriaClinica\HceEstructuraFamiliar;
use App\Models\HistoriaClinica\HceHermano;
use App\Models\HistoriaClinica\HceHistoriaEscolar;
use App\Models\HistoriaClinica\HceObservacionesFirmas;
use App\Models\HistoriaClinica\HcePsicoafectiva;
use App\Models\HistoriaClinica\HceRemision;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Un acudiente solo puede leer y modificar sus propias inscripciones.
 *
 * Los endpoints de /admisiones y /compartido reciben ids (de la inscripción, del
 * aspirante, de un familiar, de un documento...) y antes no verificaban de quién eran:
 * un acudiente que probara ids podía ver, editar o borrar aspirantes de otro.
 *
 * Solo restringe tokens de acudiente (system=admissions sin source=token_exchange). El
 * personal del sistema general y el modo admin pasan igual que antes. Para un acudiente:
 * - cada ruta declara en REGLAS qué campos traen ids y de qué tabla; todos deben llevar a
 *   inscripciones suyas (id_usuario_registro);
 * - una ruta que no esté en REGLAS queda prohibida (citas, listados de psicología... son
 *   del personal);
 * - el estado solo puede pasar a PENDIENTE (enviar la admisión);
 * - solo modifica (POST/PUT/DELETE) mientras el estado lo permite: datos generales en
 *   BORRADOR o PENDIENTE, historia clínica en EN REVISION (PSICOLOGIA). Es la misma regla
 *   que aplica la página (isLocked/isPsicoLocked en DetailView), pero en el servidor: si el
 *   personal cambia el estado mientras el acudiente tiene la página abierta, sus cambios ya
 *   no se guardan.
 */
class InscripcionDelAcudiente
{
    /** campo de la petición (admite comodines: familiares.*.id_inscripcion) => [modelo, columna]. */
    private const REGLAS = [
        'POST admisiones/inscripcion' => [],            // el acudiente lo pone el controlador
        'POST admisiones/inscripcion/usuario' => [],    // ídem
        'DELETE admisiones/inscripcion' => ['id' => [Inscripcion::class, 'id']],
        'DELETE admisiones/inscripcionDatos' => ['id' => [Inscripcion::class, 'id']],
        'GET admisiones/aspirante' => ['id' => [Aspirante::class, 'id']],
        'POST admisiones/aspirante' => ['id_inscripcion' => [Inscripcion::class, 'id']],
        'PUT admisiones/aspirante' => ['id' => [Aspirante::class, 'id'], 'id_inscripcion' => [Inscripcion::class, 'id']],
        'DELETE admisiones/aspirante' => ['id' => [Aspirante::class, 'id']],
        'POST admisiones/acudiente' => ['familiares.*.id_inscripcion' => [Inscripcion::class, 'id']],
        'PUT admisiones/acudiente' => ['id_familiar' => [Familiares::class, 'id'], 'familiares.*.id_inscripcion' => [Inscripcion::class, 'id']],
        'DELETE admisiones/acudiente' => ['id_acudiente' => [Familiares::class, 'id']],
        'POST admisiones/informacionMedica' => ['informacion_medica.*.id_inscripcion' => [Inscripcion::class, 'id']],
        'PUT admisiones/informacionMedica' => ['id_informacion' => [InformacionMedica::class, 'id'], 'informacion_medica.*.id_inscripcion' => [Inscripcion::class, 'id']],
        'DELETE admisiones/informacionMedica' => ['id_informacion' => [InformacionMedica::class, 'id'], 'ids' => [InformacionMedica::class, 'id']],
        'POST admisiones/adminsionDocumentos' => ['id_inscripcion' => [Inscripcion::class, 'id']],
        'DELETE admisiones/adminsionDocumentos' => ['ids' => [Documento::class, 'id']],
        'POST admisiones/referencias' => ['id_inscripcion' => [Inscripcion::class, 'id']],
        'PUT admisiones/referencias' => ['id' => [ReferenciasFamiliares::class, 'id'], 'id_inscripcion' => [Inscripcion::class, 'id']],
        'DELETE admisiones/referencias' => ['id_referencia' => [ReferenciasFamiliares::class, 'id']],
        'GET admisiones/visualizarDocumento' => ['public_id' => [Documento::class, 'public_id']],
        'GET compartido/estadosIncripcion' => [],
        'GET compartido/inscripcion' => ['codigo' => [Inscripcion::class, 'codigo']],
        'PUT compartido/inscripcion' => ['id' => [Inscripcion::class, 'id']],
        'PUT compartido/inscripcion/estado' => ['id_inscripcion' => [Inscripcion::class, 'id']],
    ];

    /**
     * Tipos de documento que sube el colegio (Recomendación de Coordinador, Recomendación
     * Psicólogo): el acudiente no puede subirlos ni borrarlos. Mismos ids que
     * DOCUMENTOS_DEL_COLEGIO en el frontend (documentoRequisitos.constants.ts).
     */
    public const DOCUMENTOS_DEL_COLEGIO = [24, 20];

    /** Estados en los que el acudiente puede modificar datos generales / historia clínica. */
    private const ESTADOS_EDITABLES = ['BORRADOR', 'PENDIENTE'];

    private const ESTADOS_EDITABLES_HISTORIA_CLINICA = ['EN REVISION (PSICOLOGIA)'];

    /** Escrituras que no dependen del estado de una inscripción existente. */
    private const SIN_CONTROL_DE_ESTADO = ['POST admisiones/inscripcion', 'POST admisiones/inscripcion/usuario'];

    /** Secciones de historia clínica (compartido/historia-clinica/<sección>) => modelo. */
    private const HISTORIA_CLINICA = [
        'cognitivo-lenguaje' => HceCognitivoLenguaje::class,
        'desarrollo-psicomotor' => HceDesarrolloPsicomotor::class,
        'embarazo-parto' => HceEmbarazoParto::class,
        'estructura-familiar' => HceEstructuraFamiliar::class,
        'hermanos' => HceHermano::class,
        'historia-escolar' => HceHistoriaEscolar::class,
        'observaciones-firmas' => HceObservacionesFirmas::class,
        'psicoafectiva' => HcePsicoafectiva::class,
        'remision' => HceRemision::class,
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $payload = JWTAuth::parseToken()->getPayload();

        if ($payload->get('system') !== 'admissions' || $payload->get('source') === 'token_exchange') {
            return $next($request);
        }

        $acudiente = $request->user()?->id_user;
        $reglas = $this->reglas($request);

        if (! $acudiente || $reglas === null) {
            return $this->prohibido();
        }

        $inscripciones = [];

        foreach ($reglas as $campo => [$modelo, $columna]) {
            $valores = array_values(array_filter(
                (array) data_get($request->all(), $campo),
                fn ($v) => is_scalar($v) && $v !== ''
            ));

            if (! $valores) {
                continue;
            }

            $ids = $this->inscripcionesDe($modelo, $columna, $valores);

            if ($this->hayInscripcionesAjenas($ids, $acudiente)) {
                return $this->prohibido();
            }

            array_push($inscripciones, ...$ids);
        }

        $ruta = $this->ruta($request);

        if ($request->method() !== 'GET' && ! in_array($ruta, self::SIN_CONTROL_DE_ESTADO, true)) {
            $permitidos = str_contains($ruta, ' compartido/historia-clinica/')
                ? self::ESTADOS_EDITABLES_HISTORIA_CLINICA
                : self::ESTADOS_EDITABLES;

            foreach ($this->estadosDe(array_values(array_unique($inscripciones))) as $estado) {
                // Sin estado todavía = recién creada, en la práctica BORRADOR (igual que el frontend).
                if (! in_array($estado ?? 'BORRADOR', $permitidos, true)) {
                    return response()->json([
                        'error' => true,
                        'message' => 'Esta admisión ya cambió de estado y no se puede modificar. Recarga la página para ver su estado actual.',
                    ], 409);
                }
            }
        }

        if ($ruta === 'PUT compartido/inscripcion/estado' && ! $this->esPendiente($request->input('estado'))) {
            return $this->prohibido();
        }

        if ($ruta === 'POST admisiones/adminsionDocumentos'
            && array_intersect(array_map('intval', (array) data_get($request->all(), 'documentos.*.id_tipo_documento')), self::DOCUMENTOS_DEL_COLEGIO)) {
            return $this->prohibido();
        }

        if ($ruta === 'DELETE admisiones/adminsionDocumentos' && $this->sonDocumentosDelColegio((array) $request->input('ids'))) {
            return $this->prohibido();
        }

        return $next($request);
    }

    /** Reglas de la ruta, o null si el acudiente no tiene acceso a ella. */
    private function reglas(Request $request): ?array
    {
        $ruta = $this->ruta($request);

        if (array_key_exists($ruta, self::REGLAS)) {
            return self::REGLAS[$ruta];
        }

        if (str_starts_with($ruta, 'GET compartido/anio-academico/')) {
            return [];
        }

        if (preg_match('#^(GET|POST|PUT|DELETE) compartido/historia-clinica/([a-z-]+)$#', $ruta, $m)
            && isset(self::HISTORIA_CLINICA[$m[2]])) {
            $modelo = self::HISTORIA_CLINICA[$m[2]];

            return match ($m[1]) {
                'GET', 'POST' => ['id_inscripcion' => [Inscripcion::class, 'id']],
                'PUT' => ['id' => [$modelo, 'id'], 'id_inscripcion' => [Inscripcion::class, 'id']],
                'DELETE' => ['id' => [$modelo, 'id']],
            };
        }

        return null;
    }

    /** "MÉTODO ruta" sin el prefijo api/, p. ej. "DELETE admisiones/aspirante". */
    private function ruta(Request $request): string
    {
        $uri = $request->route()?->uri() ?? $request->path();

        return $request->method() . ' ' . preg_replace('#^api/#', '', $uri);
    }

    /** Ids de inscripción a los que llevan esos valores (los que no existen se ignoran). */
    protected function inscripcionesDe(string $modelo, string $columna, array $valores): array
    {
        $campoInscripcion = $modelo === Inscripcion::class ? 'id' : 'id_inscripcion';

        return $modelo::whereIn($columna, $valores)->pluck($campoInscripcion)->filter()->unique()->values()->all();
    }

    protected function hayInscripcionesAjenas(array $idsInscripcion, int $acudiente): bool
    {
        return $idsInscripcion !== []
            && Inscripcion::whereIn('id', $idsInscripcion)->where('id_usuario_registro', '!=', $acudiente)->exists();
    }

    protected function sonDocumentosDelColegio(array $ids): bool
    {
        return $ids !== []
            && Documento::whereIn('id', $ids)->whereIn('id_tipo_documento', self::DOCUMENTOS_DEL_COLEGIO)->exists();
    }

    /** Nombre del estado de cada inscripción (null si aún no tiene). */
    protected function estadosDe(array $idsInscripcion): array
    {
        if ($idsInscripcion === []) {
            return [];
        }

        return Inscripcion::with('estadoInscripcion:id,nombre')
            ->whereIn('id', $idsInscripcion)
            ->get()
            ->map(fn ($i) => $i->estadoInscripcion?->nombre)
            ->all();
    }

    protected function esPendiente(mixed $estado): bool
    {
        return is_numeric($estado) && Estado::whereKey((int) $estado)->value('nombre') === 'PENDIENTE';
    }

    private function prohibido(): Response
    {
        return response()->json(['error' => true, 'message' => 'No tienes permiso para esta acción.'], 403);
    }
}
