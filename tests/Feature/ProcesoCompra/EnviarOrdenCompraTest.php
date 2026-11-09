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

        Mail::fake();

        $this->enviar($solicitud->id)->assertOk();

        Mail::assertSent(OrdenCompraMail::class, fn (OrdenCompraMail $mail) => $mail->hasTo(trim($solicitud->proveedor->correo))
            && $mail->correosContacto === Mails::COMPRAS_VENTAS->recipients());
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

    private function enviar(int $id): TestResponse
    {
        $usuario = Usuario::where('estado', 'activo')->where('perfil', Permiso::PERFIL_SUPER_ADMIN)->firstOrFail();
        $token = app(JwtService::class)->generateToken($usuario);

        return $this->post("/api/solicitudes/{$id}/enviar-orden", [
            'orden_pdf' => UploadedFile::fake()->create('orden.pdf', 20, 'application/pdf'),
        ], ['Authorization' => "Bearer {$token}", 'Accept' => 'application/json']);
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
        $token = app(JwtService::class)->generateToken($usuario);

        $this->post("/api/solicitudes/{$id}/enviar-orden", [
            'orden_pdf' => UploadedFile::fake()->create('orden.pdf', 20, 'application/pdf'),
        ], ['Authorization' => "Bearer {$token}", 'Accept' => 'application/json'])->assertForbidden();

        Mail::assertNothingSent();
    }
}
