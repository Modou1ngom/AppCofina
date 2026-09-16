<?php

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;

function utilisateurAvecRole(string $slug): User
{
    test()->seed(RoleSeeder::class);

    $user = User::factory()->withoutTwoFactor()->create([
        'is_active' => true,
        'must_change_password' => false,
    ]);

    $role = Role::query()->where('slug', $slug)->first();
    $user->roles()->attach($role->id);

    return $user;
}

test('un visiteur est redirige hors des exports recap missions', function () {
    $this->get('/missions/recap-logistique/export')->assertRedirect();
    $this->get('/missions/traitees/recap/export')->assertRedirect();
});

test('un collaborateur sans droit recap ne peut pas exporter la logistique', function () {
    $user = User::factory()->withoutTwoFactor()->create([
        'is_active' => true,
        'must_change_password' => false,
    ]);

    $this->actingAs($user)
        ->get('/missions/recap-logistique/export')
        ->assertForbidden();
});

test('un profil finance peut telecharger le recap logistique excel et zip', function () {
    $user = utilisateurAvecRole('finance');

    $excel = $this->actingAs($user)->get('/missions/recap-logistique/export?format=excel&context=finance');
    $excel->assertOk();
    expect($excel->headers->get('content-disposition'))->toContain('recap-logistique-finance_');

    $zip = $this->actingAs($user)->get('/missions/recap-logistique/export?format=zip&context=facilities');
    $zip->assertOk();
    expect($zip->headers->get('content-disposition'))->toContain('recap-logistique-facilities_')
        ->and($zip->headers->get('content-type'))->toContain('application/zip');
});

test('un utilisateur connecte peut exporter sa recap missionnaires', function () {
    $user = User::factory()->withoutTwoFactor()->create([
        'is_active' => true,
        'must_change_password' => false,
    ]);

    $excel = $this->actingAs($user)->get('/missions/traitees/recap/export?format=excel&periode=mois');
    $excel->assertOk();
    expect($excel->headers->get('content-disposition'))->toContain('recap-missionnaires_mois_');

    $zip = $this->actingAs($user)->get('/missions/traitees/recap/export?format=zip&periode=semaine');
    $zip->assertOk();
    expect($zip->headers->get('content-disposition'))->toContain('recap-missionnaires_semaine_');
});
