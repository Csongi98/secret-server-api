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

    public function test_it_can_return_404_if_remainig_view_is_zero_or_below()
    {
        $secret = Secret::factory()->create([
            'secret_text' => Crypt::encryptString('This is a test secret'),
            'remaining_views' => 0,
            'expires_at' => now()->addMinutes(10),
        ]);

        $response = $this->getJson('/api/secret/' . $secret->hash);

        $response->assertStatus(404)
                ->assertJson([
                    'error' => 'Secret not found or expired'
                ]);

    }

    public function test_it_can_return_yaml_response_based_on_accept_header()
    {
        $response = $this->post('/api/secret', [
            'secret' => 'YAML response test',
            'expireAfterViews' => 5,
            'expireAfter' => 10,
        ], ['Accept' => 'application/x-yaml']);

        $response->assertStatus(201)
                 ->assertHeader('Content-Type', 'application/x-yaml');
    }

    /**
     * Test expireAfterViews validation.
     */
    public function test_expire_after_views_cannot_exceed_maximum_limit()
    {
        $response = $this->postJson('/api/secret', [
            'secret' => 'Test secret',
            'expireAfterViews' => 1500,
            'expireAfter' => 10,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['expireAfterViews']);
    }

    /**
     * Test tags validation with valid tags.
     */
    public function test_secret_can_be_created_with_valid_tags()
    {

        
        $response = $this->postJson('/api/secret', [
            'secret' => 'This is a test secret',
            'expireAfterViews' => 5,
            'expireAfter' => 10,
            'tags' => ['personal', 'work'],
        ]);

        //dd($response->json());
        $response->assertStatus(201);
        $response->assertJsonStructure([
            'id',
            'hash',
            'secret_text',
            'remaining_views',
            'expires_at',
            'tags',
        ]);

        $response->assertJson([
            'tags' => ['personal', 'work'],
        ]);
    }

    /**
     * Test tags validation with invalid tags.
     */
    public function test_tags_validation_fails_with_invalid_tags()
    {
        $response = $this->postJson('/api/secret', [
            'secret' => 'Test secret',
            'expireAfterViews' => 5,
            'expireAfter' => 10,
            'tags' => ['invalid'],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['tags.0']);
    }

    /**
     * Test creating a secret without tags.
     */
    public function test_secret_can_be_created_without_tags()
    {
        $response = $this->postJson('/api/secret', [
            'secret' => 'Test secret',
            'expireAfterViews' => 5,
            'expireAfter' => 10,
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'id',
            'hash',
            'secret_text',
            'remaining_views',
            'expires_at',
        ]);

        $this->assertDatabaseHas('secrets', [
            'tags' => null,
        ]);
    }

    public function test_export_secrets_in_json_format()
    {
        Secret::factory()->count(3)->create();

        $response = $this->getJson('/api/secrets/export?format=json');

        $response->assertStatus(200)
                ->assertHeader('Content-Type', 'application/json');
    }

    public function test_export_secrets_in_yaml_format()
    {
        Secret::factory()->count(3)->create();

        $response = $this->get('/api/secrets/export?format=yaml', [
            'Accept' => 'application/x-yaml',
        ]);

        $response->assertStatus(200)
                ->assertHeader('Content-Type', 'application/x-yaml');
    }

    public function test_export_secrets_in_csv_format()
    {
        Secret::factory()->count(3)->create();

        $response = $this->get('/api/secrets/export?format=csv');

        $response->assertStatus(200)
                ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    }

    public function test_can_paginate_secrets()
    {
        Secret::factory()->count(25)->create();

        $response = $this->getJson('/api/secrets?per_page=10');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'data',
                    'current_page',
                    'last_page',
                    'per_page',
                    'total',
                ]);
    }

    public function test_can_filter_secrets_about_to_expire()
    {
        Secret::factory()->create([
            'expires_at' => now()->addMinutes(10),
        ]);

        Secret::factory()->create([
            'expires_at' => now()->addMinutes(60),
        ]);

        $response = $this->getJson('/api/secrets?expires_soon=true');

        $response->assertStatus(200)
                ->assertJsonCount(1, 'data');
    }

    public function test_can_filter_secrets_with_low_views()
    {
        Secret::factory()->create(['remaining_views' => 3]);
        Secret::factory()->create(['remaining_views' => 6]);

        $response = $this->getJson('/api/secrets?low_views=true');

        $response->assertStatus(200)
                ->assertJsonCount(1, 'data');
    }

    public function test_can_search_secrets_by_metadata()
    {
        Secret::factory()->create([
            'created_at' => now()->subDays(2),
            'expires_at' => now()->addMinutes(30),
            'remaining_views' => 3,
        ]);

        $response = $this->getJson('/api/secrets/search?created_from=' . now()->subDays(3)->toDateString() . '&expires_to=' . now()->toDateString());

        $response->assertStatus(200)
                ->assertJsonStructure(['data', 'current_page', 'last_page']);
    }

    public function test_audit_log_is_created_for_secret_creation()
    {
        $this->postJson('/api/secret', [
            'secret' => 'This is a test secret',
            'expireAfterViews' => 5,
            'expireAfter' => 10,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'created',
        ]);
    }

    public function test_audit_log_is_created_for_secret_access()
    {
        $secret = Secret::factory()->create([
            'secret_text' => Crypt::encryptString('Test secret'),
            'remaining_views' => 5,
            'expires_at' => now()->addMinutes(10),
        ]);

        $this->getJson('/api/secret/' . $secret->hash);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'accessed',
            'secret_id' => $secret->id,
        ]);
    }
}
