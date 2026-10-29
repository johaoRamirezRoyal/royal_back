<?php

namespace Tests\Unit;

use App\Http\Middleware\InscripcionDelAcudiente;
use App\Models\Admisiones\Aspirante;
use App\Models\Admisiones\Inscripcion;
use App\Models\HistoriaClinica\HceHermano;
use App\Models\Usuarios\Usuario;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Mockery;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Un acudiente solo toca sus propias inscripciones. Sin BD: el middleware se prueba con
 * los datos simulados de abajo (la inscripción 1 es del acudiente 7, la 2 de otro).
 */
class InscripcionDelAcudienteTest extends TestCase
{
    /** [modelo][valor] => id de inscripción. */
    private const DATOS = [
        Inscripcion::class => ['1' => 1, '2' => 2, '3' => 3, '4' => 4, 'ROYAL-1' => 1, 'ROYAL-2' => 2],
        Aspirante::class => ['10' => 1, '20' => 2],
        HceHermano::class => ['30' => 1, '40' => 2, '50' => 4],
    ];

    /** La 1, 3 y 4 son del acudiente 7; la 2 de otro. */
    private const DUENO = [1 => 7, 2 => 8, 3 => 7, 4 => 7];

    private const ESTADO = [1 => 'BORRADOR', 2 => 'BORRADOR', 3 => 'EN REVISION (ADMISIONES)', 4 => 'EN REVISION (PSICOLOGIA)'];

    private function pasa(string $metodo, string $uri, array $cuerpo, array $claims = ['system' => 'admissions'], ?int $usuario = 7): bool
    {
        $payload = Mockery::mock();
        $payload->shouldReceive('get')->andReturnUsing(fn ($k) => $claims[$k] ?? null);
        // Un mock nuevo por llamada: con shouldReceive sobre la fachada, Mockery seguía
        // devolviendo el primer token del test.
        $jwt = Mockery::mock();
        $jwt->shouldReceive('parseToken')->andReturnSelf();
        $jwt->shouldReceive('getPayload')->andReturn($payload);
        JWTAuth::swap($jwt);

        $request = Request::create("/{$uri}", $metodo, $cuerpo);
        $request->setRouteResolver(fn () => new Route($metodo, $uri, []));
        $request->setUserResolver(fn () => $usuario ? tap(new Usuario(), fn ($u) => $u->id_user = $usuario) : null);

        $middleware = new class(self::DATOS, self::DUENO, self::ESTADO) extends InscripcionDelAcudiente {
            public function __construct(private array $datos, private array $dueno, private array $estado) {}

            protected function estadosDe(array $idsInscripcion): array
            {
                return array_map(fn ($id) => $this->estado[$id] ?? null, $idsInscripcion);
            }

            protected function inscripcionesDe(string $modelo, string $columna, array $valores): array
            {
                return array_values(array_unique(array_filter(array_map(fn ($v) => $this->datos[$modelo][(string) $v] ?? null, $valores))));
            }

            protected function hayInscripcionesAjenas(array $idsInscripcion, int $acudiente): bool
            {
                return (bool) array_filter($idsInscripcion, fn ($id) => ($this->dueno[$id] ?? null) !== $acudiente);
            }

            protected function sonDocumentosDelColegio(array $ids): bool
            {
                return in_array(99, array_map('intval', $ids), true); // 99 = recomendación ya subida
            }

            protected function esPendiente(mixed $estado): bool
            {
                return (int) $estado === 2; // 2 = PENDIENTE en estos datos
            }
        };

        return $middleware->handle($request, fn () => response('ok'))->getStatusCode() === 200;
    }

    public function test_el_acudiente_toca_lo_suyo_y_no_lo_ajeno(): void
    {
        $this->assertTrue($this->pasa('DELETE', 'api/admisiones/aspirante', ['id' => 10]));
        $this->assertFalse($this->pasa('DELETE', 'api/admisiones/aspirante', ['id' => 20]));
        $this->assertFalse($this->pasa('DELETE', 'api/admisiones/inscripcion', ['id' => 2]));
        $this->assertTrue($this->pasa('GET', 'api/compartido/inscripcion', ['codigo' => 'ROYAL-1']));
        $this->assertFalse($this->pasa('GET', 'api/compartido/inscripcion', ['codigo' => 'ROYAL-2']));
    }

    public function test_listas_anidadas_se_revisan_completas(): void
    {
        $this->assertTrue($this->pasa('POST', 'api/admisiones/acudiente', ['familiares' => [['id_inscripcion' => 1], ['id_inscripcion' => 1]]]));
        $this->assertFalse($this->pasa('POST', 'api/admisiones/acudiente', ['familiares' => [['id_inscripcion' => 1], ['id_inscripcion' => 2]]]));
    }

    public function test_historia_clinica_por_registro_y_por_inscripcion(): void
    {
        $this->assertTrue($this->pasa('DELETE', 'api/compartido/historia-clinica/hermanos', ['id' => 50])); // inscripción 4: en revisión de Psicología
        $this->assertFalse($this->pasa('DELETE', 'api/compartido/historia-clinica/hermanos', ['id' => 40]));
        $this->assertFalse($this->pasa('GET', 'api/compartido/historia-clinica/hermanos', ['id_inscripcion' => 2]));
    }

    public function test_el_acudiente_solo_puede_enviar_la_admision(): void
    {
        $this->assertTrue($this->pasa('PUT', 'api/compartido/inscripcion/estado', ['id_inscripcion' => 1, 'estado' => 2]));
        $this->assertFalse($this->pasa('PUT', 'api/compartido/inscripcion/estado', ['id_inscripcion' => 1, 'estado' => 5]));
    }

    public function test_el_acudiente_no_sube_ni_borra_documentos_del_colegio(): void
    {
        $doc = fn (int $tipo) => ['id_inscripcion' => 1, 'documentos' => [['id_tipo_documento' => $tipo]]];
        $this->assertTrue($this->pasa('POST', 'api/admisiones/adminsionDocumentos', $doc(16)));
        $this->assertFalse($this->pasa('POST', 'api/admisiones/adminsionDocumentos', $doc(24)));
        $this->assertFalse($this->pasa('POST', 'api/admisiones/adminsionDocumentos', $doc(20)));
        $this->assertFalse($this->pasa('DELETE', 'api/admisiones/adminsionDocumentos', ['ids' => [99]]));
        // El personal en modo admin sí.
        $this->assertTrue($this->pasa('POST', 'api/admisiones/adminsionDocumentos', $doc(24), ['system' => 'admissions', 'source' => 'token_exchange'], 1));
    }

    public function test_el_estado_de_la_inscripcion_bloquea_cambios_del_acudiente(): void
    {
        // En revisión de Admisiones: los datos generales ya no se tocan, pero se leen.
        $this->assertTrue($this->pasa('PUT', 'api/admisiones/aspirante', ['id_inscripcion' => 1]));
        $this->assertFalse($this->pasa('PUT', 'api/admisiones/aspirante', ['id_inscripcion' => 3]));
        $this->assertFalse($this->pasa('DELETE', 'api/admisiones/inscripcion', ['id' => 3]));
        $this->assertFalse($this->pasa('PUT', 'api/compartido/inscripcion/estado', ['id_inscripcion' => 3, 'estado' => 2]));
        $this->assertTrue($this->pasa('GET', 'api/compartido/inscripcion', ['codigo' => 'ROYAL-1']));

        // Historia clínica: solo en revisión de Psicología.
        $this->assertTrue($this->pasa('PUT', 'api/compartido/historia-clinica/hermanos', ['id' => 50]));
        $this->assertFalse($this->pasa('PUT', 'api/compartido/historia-clinica/hermanos', ['id' => 30]));
        $this->assertFalse($this->pasa('PUT', 'api/admisiones/aspirante', ['id_inscripcion' => 4]));
    }

    public function test_rutas_del_personal_quedan_cerradas_para_el_acudiente(): void
    {
        $this->assertFalse($this->pasa('GET', 'api/compartido/citasPsicologia', []));
        $this->assertFalse($this->pasa('GET', 'api/admisiones/inscripcionesPsicologa', []));
        $this->assertTrue($this->pasa('GET', 'api/compartido/anio-academico/todos', []));
    }

    public function test_personal_y_modo_admin_pasan_como_antes(): void
    {
        $this->assertTrue($this->pasa('DELETE', 'api/admisiones/aspirante', ['id' => 20], ['system' => 'general'], 1));
        $this->assertTrue($this->pasa('GET', 'api/compartido/citasPsicologia', [], ['system' => 'general'], 1));
        $this->assertTrue($this->pasa('DELETE', 'api/admisiones/aspirante', ['id' => 20], ['system' => 'admissions', 'source' => 'token_exchange'], 1));
    }

    public function test_sin_sesion_no_pasa(): void
    {
        $this->assertFalse($this->pasa('DELETE', 'api/admisiones/aspirante', ['id' => 10], ['system' => 'admissions'], null));
    }
}
