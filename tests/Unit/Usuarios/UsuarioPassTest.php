<?php

namespace Tests\Unit\Usuarios;

use App\Models\Usuarios\Usuario;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * AuthController::register y el alta por Google pasan la contraseña en claro al crear el
 * usuario: el hash lo pone Usuario::setPassAttribute. Antes la hasheaban también ellos
 * y quedaba con doble hash (nadie podía entrar con la contraseña con la que se registró).
 */
class UsuarioPassTest extends TestCase
{
    public function test_la_contrasena_en_claro_se_guarda_hasheada_una_sola_vez(): void
    {
        $usuario = new Usuario(['pass' => 'Secreto2026!']);

        $this->assertNotSame('Secreto2026!', $usuario->pass);
        $this->assertTrue(Hash::check('Secreto2026!', $usuario->pass));
    }
}
