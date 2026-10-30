<?php

namespace Tests\Unit\Admisiones;

use App\Events\EstadoInscripcionCambiado;
use App\Listeners\NotificarEstadoInscripcionCambiado;
use App\Services\Admisiones\AdmisionesServices;
use App\Services\MailService;
use Illuminate\Support\Facades\Event;
use Mockery;
use Tests\TestCase;

/** Sin BD: MailService simulado y los destinatarios (ADMISIONES_ESTADO, psicólogas) fijos. */
class EstadoInscripcionCambiadoTest extends TestCase
{
    private function evento(string $estadoNuevo, ?string $grado = 'Primero de primaria'): EstadoInscripcionCambiado
    {
        return new EstadoInscripcionCambiado(1, 'ADM-1', 'PENDIENTE', $estadoNuevo, 'Ana Pérez', $grado, 'acudiente@correo.com', '2026-10-29 10:00:00');
    }

    /** @return array<int, array{0: mixed, 1: string}> correos enviados: [destinatarios, asunto] */
    private function enviar(EstadoInscripcionCambiado $evento): array
    {
        $enviados = [];
        $mail = Mockery::mock(MailService::class);
        $mail->shouldReceive('sendGeneric')->andReturnUsing(function ($to, $asunto) use (&$enviados) {
            $enviados[] = [$to, $asunto];

            return [];
        });

        $listener = new class($mail) extends NotificarEstadoInscripcionCambiado {
            protected function destinatariosDelEstado(): array
            {
                return ['admisiones@colegio.com'];
            }

            protected function correosDelPerfil(int $perfil): array
            {
                return ["psicologa{$perfil}@colegio.com"];
            }
        };
        $listener->handle($evento);

        return $enviados;
    }

    public function test_avisa_al_acudiente_del_cambio(): void
    {
        $enviados = $this->enviar($this->evento('EN REVISION (ADMISIONES)'));

        $this->assertCount(1, $enviados);
        $this->assertContains('acudiente@correo.com', $enviados[0][0]);
        $this->assertStringContainsString('ADM-1', $enviados[0][1]);
    }

    public function test_en_revision_de_psicologia_avisa_tambien_a_la_psicologa_del_nivel(): void
    {
        $enviados = $this->enviar($this->evento('EN REVISION (PSICOLOGIA)'));
        $perfil = AdmisionesServices::perfilPsicologaParaGrado('Primero de primaria');

        $this->assertNotNull($perfil);
        $this->assertCount(2, $enviados);
        $this->assertSame(["psicologa{$perfil}@colegio.com"], $enviados[1][0]);
    }

    public function test_el_listener_escucha_el_evento_en_cola(): void
    {
        Event::fake();
        Event::assertListening(EstadoInscripcionCambiado::class, NotificarEstadoInscripcionCambiado::class);
        $this->assertSame('emails', (new \ReflectionClass(NotificarEstadoInscripcionCambiado::class))->getProperty('queue')->getDefaultValue());
    }
}
