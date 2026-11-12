<?php

namespace Tests\Feature\ProcesoCompra;

use App\Enums\Mails;
use App\Mail\ProcesoCompra\OrdenCompraMail;
use App\Models\ProcesoCompra\Solicitudes\Solicitud;
use App\Models\Usuarios\Usuario;
use App\Services\JwtService;
use App\Services\ProcesoCompra\SolicitudesServices;
use App\Support\Permiso;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Contra la base real (ver InventarioFechaReporteTest). No escribe nada: el envío de la
 * orden de compra no guarda registro y el correo va a Mail::fake().
 */
class EnviarOrdenCompraTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $env = file_get_contents(base_path('.env'));
        preg_match('/^DB_DATABASE=(.*)$/m', $env, $match);
        $baseDeDatosReal = trim($match[1] ?? 'laravel', " \t\n\r\0\x0B\"'");

        Config::set('database.default', 'mysql');
        Config::set('database.connections.mysql.database', $baseDeDatosReal);
        DB::purge('mysql');
    }

    public function test_envia_la_orden_al_proveedor_con_ventas_como_contacto(): void
    {
        $solicitud = $this->solicitudEnviable();
        Mail::fake();

        $this->enviar($solicitud->id, $this->datos($solicitud))->assertOk();

        Mail::assertSent(OrdenCompraMail::class, fn (OrdenCompraMail $mail) => $mail->hasTo($this->correoProveedor($solicitud))
            && $mail->correosContacto === Mails::COMPRAS_VENTAS->recipients()
            && $mail->hasSubject('Asunto de prueba')
            && $mail->hasCc('copia@example.com')
            && $mail->pdfContenido !== null);
    }

    public function test_no_envia_a_un_correo_que_no_es_del_proveedor(): void
    {
        $solicitud = $this->solicitudEnviable();
        Mail::fake();

        $this->enviar($solicitud->id, [...$this->datos($solicitud), 'destinatarios' => ['otro@example.com']])
            ->assertStatus(422);

        Mail::assertNothingSent();
    }

    public function test_sin_pdf_no_adjunta_nada_y_el_html_queda_saneado(): void
    {
        $solicitud = $this->solicitudEnviable();
        Mail::fake();

        $this->enviar($solicitud->id, [
            ...$this->datos($solicitud),
            'adjuntar_pdf' => '0',
            'mensaje' => '<p onclick="x()">Hola <strong>proveedor</strong></p><script>alert(1)</script>',
        ], conPdf: false)->assertOk();

        Mail::assertSent(OrdenCompraMail::class, fn (OrdenCompraMail $mail) => $mail->pdfContenido === null
            && $mail->attachments() === []
            && str_contains($mail->mensajeHtml, '<strong>proveedor</strong>')
            && ! str_contains($mail->mensajeHtml, 'script')
            && ! str_contains($mail->mensajeHtml, 'onclick'));
    }

    public function test_vista_previa_devuelve_el_html_con_los_bloques_pedidos(): void
    {
        $solicitud = $this->solicitudEnviable();
        Mail::fake();

        $html = $this->post("/api/solicitudes/{$solicitud->id}/vista-previa-orden", [
            ...$this->datos($solicitud),
            'incluir' => ['productos'],
        ], $this->headers())->assertOk()->json('data.html');

        $this->assertStringContainsString('Mensaje de prueba', $html);
        $this->assertStringContainsString(e($solicitud->productos->first()->producto), $html);
        $this->assertStringNotContainsString('Datos de la orden', $html);
        Mail::assertNothingSent();
    }

    public function test_no_envia_una_solicitud_cerrada(): void
    {
        $id = DB::table('solicitudes')->where('estado', SolicitudesServices::ESTADO_CERRADA)
            ->where('anulada', 0)->whereNotNull('id_proveedor')->value('id');

        if (! $id) {
            $this->markTestSkipped('No hay solicitud cerrada con proveedor.');
        }

        Mail::fake();

        $this->enviar($id)->assertStatus(422);

        Mail::assertNothingSent();
    }

    public function test_no_envia_si_algun_producto_no_tiene_precio(): void
    {
        $id = DB::table('solicitudes as s')
            ->join('solicitud_productos as p', 'p.id_solicitud', '=', 's.id')
            ->whereIn('s.estado', [SolicitudesServices::ESTADO_FORMALIZADA, SolicitudesServices::ESTADO_DEVOLUCION])
            ->where('s.anulada', 0)
            ->whereNotNull('s.id_proveedor')
            ->whereNull('p.precio')
            ->value('s.id');

        if (! $id) {
            $this->markTestSkipped('No hay solicitud formalizada/en devolución con un producto sin precio.');
        }

        Mail::fake();

        $this->enviar($id)->assertStatus(422);

        Mail::assertNothingSent();
    }

    private function solicitudEnviable(): Solicitud
    {
        $positivo = fn ($v): bool => is_numeric($v) && (float) $v > 0;
        $solicitud = Solicitud::with('productos', 'proveedor')
            ->whereIn('estado', [SolicitudesServices::ESTADO_FORMALIZADA, SolicitudesServices::ESTADO_DEVOLUCION])
            ->where('anulada', 0)
            ->whereHas('proveedor', fn ($q) => $q->where('correo', 'like', '%@%'))
            ->whereHas('productos')
            ->orderByDesc('id')
            ->limit(200)
            ->get()
            ->first(fn (Solicitud $s) => filter_var(trim((string) $s->proveedor->correo), FILTER_VALIDATE_EMAIL)
                && $s->productos->every(fn ($p) => $positivo($p->cantidad) && $positivo($p->precio)));

        if (! $solicitud) {
            $this->markTestSkipped('No hay solicitud formalizada/en devolución con productos y correo de proveedor válidos.');
        }

        return $solicitud;
    }

    private function correoProveedor(Solicitud $solicitud): string
    {
        return mb_strtolower(trim((string) $solicitud->proveedor->correo));
    }

    /** @return array<string, mixed> */
    private function datos(?Solicitud $solicitud = null): array
    {
        return [
            'asunto' => 'Asunto de prueba',
            'mensaje' => '<p>Mensaje de prueba</p>',
            'destinatarios' => [$solicitud ? $this->correoProveedor($solicitud) : 'proveedor@example.com'],
            'cc' => ['copia@example.com'],
            'incluir' => SolicitudesServices::BLOQUES_ORDEN,
            'adjuntar_pdf' => '1',
        ];
    }

    /** @return array<string, string> */
    private function headers(?Usuario $usuario = null): array
    {
        $usuario ??= Usuario::where('estado', 'activo')->where('perfil', Permiso::PERFIL_SUPER_ADMIN)->firstOrFail();
        $token = app(JwtService::class)->generateToken($usuario);

        return ['Authorization' => "Bearer {$token}", 'Accept' => 'application/json'];
    }

    /** @param  array<string, mixed>|null  $datos */
    private function enviar(int $id, ?array $datos = null, bool $conPdf = true): TestResponse
    {
        return $this->post("/api/solicitudes/{$id}/enviar-orden", [
            ...($datos ?? $this->datos()),
            ...($conPdf ? ['orden_pdf' => UploadedFile::fake()->create('orden.pdf', 20, 'application/pdf')] : []),
        ], $this->headers());
    }

    public function test_sin_opcion_gestion_de_compras_no_puede_enviar(): void
    {
        $perfilesConGestion = DB::table('cron_permisos')->where('id_opcion', Permiso::COMPRAS_GESTION)
            ->where('activo', 1)->pluck('id_perfil');
        $usuario = Usuario::where('estado', 'activo')
            ->where('perfil', '!=', Permiso::PERFIL_SUPER_ADMIN)
            ->whereNotIn('perfil', $perfilesConGestion)
            ->first();
        $id = DB::table('solicitudes')->whereNotNull('id_proveedor')->value('id');

        if (! $usuario || ! $id) {
            $this->markTestSkipped('Faltan datos reales (usuario sin Gestión de compras o solicitud con proveedor).');
        }

        Mail::fake();

        $this->post("/api/solicitudes/{$id}/enviar-orden", [
            ...$this->datos(),
            'orden_pdf' => UploadedFile::fake()->create('orden.pdf', 20, 'application/pdf'),
        ], $this->headers($usuario))->assertForbidden();

        Mail::assertNothingSent();
    }
}
