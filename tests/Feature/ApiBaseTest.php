<?php

namespace Tests\Feature;

use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Pruebas de la base de la API. No tocan la base de datos.
 * IMPORTANTE: no usar RefreshDatabase contra study_track: borraría las tablas
 * y los datos importados. Para pruebas que usen SP, crear una BD aparte
 * (study_track_test) importando study_track.sql y usar DatabaseTransactions.
 */
class ApiBaseTest extends TestCase
{
    public function test_ping_es_publico(): void
    {
        $this->getJson('/api/ping')
            ->assertOk()
            ->assertJsonPath('data.status', 'ok');
    }

    public function test_me_sin_token_responde_401_en_json(): void
    {
        $this->getJson('/api/me')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'No autenticado.');
    }

    public function test_me_con_token_devuelve_el_usuario(): void
    {
        $user = new User(['name' => 'Prueba', 'email' => 'prueba@test.com']);
        $user->id = 99;

        Sanctum::actingAs($user);

        $this->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('data.email', 'prueba@test.com');
    }

    public function test_ruta_inexistente_responde_404_en_json(): void
    {
        $this->getJson('/api/no-existe')
            ->assertNotFound()
            ->assertJsonPath('message', 'Recurso no encontrado.');
    }

    public function test_me_sin_token_y_sin_header_accept_responde_401_json(): void
    {
        $this->get('/api/me')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'No autenticado.');
    }
}
