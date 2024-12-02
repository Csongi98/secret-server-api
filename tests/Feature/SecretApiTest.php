<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Secret;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;

class SecretApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that a secret can be created and returns JSON response.
     */
    public function test_it_can_create_a_secret_and_return_json()
    {
        $response = $this->postJson('/api/secret', [
            'secret' => 'This is a test secret',
            'expireAfterViews' => 5,
            'expireAfter' => 10,
        ]);

        $response->assertStatus(201)
                 ->assertJsonStructure([
                     'id',
                     'hash',
                     'secret_text',
                     'remaining_views',
                     'expires_at',
                 ]);
    }

    /**
     * Test that a secret can be retrieved by its hash.
     */
    public function test_it_can_retrieve_a_secret_by_hash()
    {
        $secret = Secret::factory()->create([
            'secret_text' => Crypt::encryptString('This is a test secret'),
            'remaining_views' => 5,
            'expires_at' => now()->addMinutes(10),
        ]);

        $response = $this->getJson('/api/secret/' . $secret->hash);

        $response->assertStatus(200)
                 ->assertJson([
                     'secret_text' => 'This is a test secret',
                     'remaining_views' => 4,
                 ]);
    }

    /**
     * Test that a 404 is returned if a secret is not found.
     */
    public function test_it_returns_404_if_secret_is_not_found()
    {
        $response = $this->getJson('/api/secret/nonexistenthash');

        $response->assertStatus(404)
                 ->assertJson([
                     'error' => 'Secret not found or expired',
                 ]);
    }

    /**
     * Test that the API can return an XML response based on the Accept header.
     */
    public function test_it_can_return_xml_response_based_on_accept_header()
    {
        $response = $this->post('/api/secret', [
            'secret' => 'XML response test',
            'expireAfterViews' => 5,
            'expireAfter' => 10,
        ], ['Accept' => 'application/xml']);

        $response->assertStatus(201)
                 ->assertHeader('Content-Type', 'application/xml');
    }
}
