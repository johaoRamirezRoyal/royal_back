<?php

namespace Tests\Feature\Cafeteria;

use App\Enums\Cafeteria\EstadoAsignacion;
use App\Enums\TipoPerfil;
use App\Models\Cafeteria\Cafeteria;
use App\Models\Cafeteria\RolOperativo;
use App\Models\Usuarios\Perfil;
use App\Models\Usuarios\Usuario;
use App\Services\Cafeteria\EstructuraCafeteriaService;
use App\Services\Cafeteria\PersonalCafeteriaService;

/** Asignación de personal a las cafeterías (PersonalCafeteriaService). */
class PersonalCafeteriaTest extends CafeteriaTestCase
{
    private PersonalCafeteriaService $service;

    private Cafeteria $cafeteria;

    private RolOperativo $cajero;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(PersonalCafeteriaService::class);
        $this->cafeteria = app(EstructuraCafeteriaService::class)->crearCafeteria(['nombre' => 'Cafetería de prueba'], 1)['data'];
        $this->cajero = RolOperativo::where('codigo', 'CAJERO')->firstOrFail();
    }

    public function test_asigna_personal_activo_y_rechaza_la_misma_funcion_activa_dos_veces(): void
    {
        $persona = $this->usuarioDeTipo(TipoPerfil::DOCENTE);

        $asignada = $this->service->asignar($this->datos($persona), 99);
        $this->assertFalse($asignada['error']);
        $this->assertSame(99, $asignada['data']->asignado_por);
        $this->assertSame(EstadoAsignacion::ACTIVA, $asignada['data']->estado);

        $repetida = $this->service->asignar($this->datos($persona), 99);
        $this->assertTrue($repetida['error']);
        $this->assertSame(422, $repetida['status']);

        // Otra función en la misma cafetería sí se puede.
        $cocina = RolOperativo::where('codigo', 'COCINA')->firstOrFail();
        $this->assertFalse($this->service->asignar([...$this->datos($persona), 'id_rol_operativo' => $cocina->id], 99)['error']);
    }

    public function test_no_asigna_estudiantes_ni_acudientes(): void
    {
        foreach ([TipoPerfil::ESTUDIANTE, TipoPerfil::ACUDIENTE] as $tipo) {
            $persona = Usuario::where('estado', 'activo')->whereIn('perfil', Perfil::idsDeTipo($tipo))->first();
            if (! $persona) {
                continue;
            }
            $this->assertTrue($this->service->asignar($this->datos($persona), 1)['error'], "Asignó a un {$tipo->value}");
        }
        $this->assertSame(0, $this->cafeteria->asignaciones()->count());
    }

    public function test_finalizar_y_revocar_solo_desde_activa(): void
    {
        $persona = $this->usuarioDeTipo(TipoPerfil::DOCENTE);
        $id = $this->service->asignar([...$this->datos($persona), 'fecha_inicio' => '2026-01-10'], 1)['data']->id;

        $this->assertTrue($this->service->finalizar($id, '2026-01-01')['error'], 'Fin antes del inicio');
        $fin = $this->service->finalizar($id, '2026-06-30');
        $this->assertFalse($fin['error']);
        $this->assertSame(EstadoAsignacion::FINALIZADA, $fin['data']->estado);

        $this->assertTrue($this->service->revocar($id)['error'], 'Ya no está activa');
    }

    public function test_revocar_una_asignacion_futura_no_deja_el_fin_antes_del_inicio(): void
    {
        $persona = $this->usuarioDeTipo(TipoPerfil::DOCENTE);
        $inicio = now()->addMonth()->toDateString();
        $id = $this->service->asignar([...$this->datos($persona), 'fecha_inicio' => $inicio], 1)['data']->id;

        $revocada = $this->service->revocar($id);
        $this->assertFalse($revocada['error']);
        $this->assertSame($inicio, $revocada['data']->fecha_fin->toDateString());
    }

    public function test_una_vencida_no_cuenta_ni_bloquea_y_una_que_se_cruza_si(): void
    {
        $persona = $this->usuarioDeTipo(TipoPerfil::DOCENTE);
        $hoy = now()->toDateString();
        $vencida = $this->service->asignar([...$this->datos($persona),
            'fecha_inicio' => now()->subMonths(3)->toDateString(), 'fecha_fin' => now()->subDay()->toDateString()], 1)['data'];

        $this->assertSame(EstadoAsignacion::ACTIVA, $vencida->estado, 'Nada la cambia de estado');
        $this->assertFalse($vencida->vigente);
        $this->assertSame(0, $this->conteoVigente());

        // La vencida no bloquea una nueva desde hoy, y la nueva sí cuenta.
        $nueva = $this->service->asignar([...$this->datos($persona), 'fecha_inicio' => $hoy], 1);
        $this->assertFalse($nueva['error']);
        $this->assertTrue($nueva['data']->vigente);
        $this->assertSame(1, $this->conteoVigente());

        // Una futura que se cruza con la nueva (sin fin) sí se rechaza.
        $cruzada = $this->service->asignar([...$this->datos($persona), 'fecha_inicio' => now()->addMonth()->toDateString()], 1);
        $this->assertTrue($cruzada['error']);
    }

    public function test_finalizar_con_fecha_futura_solo_programa_el_fin(): void
    {
        $persona = $this->usuarioDeTipo(TipoPerfil::DOCENTE);
        $id = $this->service->asignar($this->datos($persona), 1)['data']->id;
        $enUnMes = now()->addMonth()->toDateString();

        $programada = $this->service->finalizar($id, $enUnMes);
        $this->assertFalse($programada['error']);
        $this->assertSame(EstadoAsignacion::ACTIVA, $programada['data']->estado);
        $this->assertSame($enUnMes, $programada['data']->fecha_fin->toDateString());
        $this->assertTrue($programada['data']->vigente);

        $this->assertTrue($this->service->finalizar($id, now()->addMonths(2)->toDateString())['error'], 'No se amplía');

        $hoy = $this->service->finalizar($id, null);
        $this->assertSame(EstadoAsignacion::FINALIZADA, $hoy['data']->estado);
        $this->assertSame(now()->toDateString(), $hoy['data']->fecha_fin->toDateString());
    }

    public function test_revocar_una_vencida_conserva_su_fecha_de_fin(): void
    {
        $persona = $this->usuarioDeTipo(TipoPerfil::DOCENTE);
        $finOriginal = now()->subDays(5)->toDateString();
        $id = $this->service->asignar([...$this->datos($persona),
            'fecha_inicio' => now()->subMonth()->toDateString(), 'fecha_fin' => $finOriginal], 1)['data']->id;

        $revocada = $this->service->revocar($id);
        $this->assertSame(EstadoAsignacion::REVOCADA, $revocada['data']->estado);
        $this->assertSame($finOriginal, $revocada['data']->fecha_fin->toDateString());
    }

    private function conteoVigente(): int
    {
        $lista = app(EstructuraCafeteriaService::class)->listarCafeterias()['data'];

        return (int) $lista->firstWhere('id', $this->cafeteria->id)->personal_vigente_count;
    }

    private function datos(Usuario $persona): array
    {
        return [
            'id_cafeteria' => $this->cafeteria->id,
            'id_user' => $persona->id_user,
            'id_rol_operativo' => $this->cajero->id,
            'fecha_inicio' => now()->toDateString(),
        ];
    }

    private function usuarioDeTipo(TipoPerfil $tipo): Usuario
    {
        $usuario = Usuario::where('estado', 'activo')->whereIn('perfil', Perfil::idsDeTipo($tipo))->first();
        if (! $usuario) {
            $this->markTestSkipped("No hay usuarios activos de tipo {$tipo->value}.");
        }

        return $usuario;
    }
}
