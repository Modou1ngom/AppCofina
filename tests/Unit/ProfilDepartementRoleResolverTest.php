<?php

use App\Support\ProfilDepartementRoleResolver;

test('departement informatique mappe vers admin', function () {
    expect(ProfilDepartementRoleResolver::resolve('Departement informatique'))->toBe('admin')
        ->and(ProfilDepartementRoleResolver::resolve('IT'))->toBe('admin')
        ->and(ProfilDepartementRoleResolver::resolve('Direction Informatique'))->toBe('admin');
});

test('departement rh mappe vers rh', function () {
    expect(ProfilDepartementRoleResolver::resolve('RH'))->toBe('rh')
        ->and(ProfilDepartementRoleResolver::resolve('Ressources Humaines'))->toBe('rh');
});

test('departement controle mappe vers controle', function () {
    expect(ProfilDepartementRoleResolver::resolve('CONTROLE'))->toBe('controle')
        ->and(ProfilDepartementRoleResolver::resolve('Contrôle'))->toBe('controle');
});

test('departement conformite mappe vers conformite', function () {
    expect(ProfilDepartementRoleResolver::resolve('CONFORMITE'))->toBe('conformite')
        ->and(ProfilDepartementRoleResolver::resolve('Conformité'))->toBe('conformite');
});

test('autre departement mappe vers metier', function () {
    expect(ProfilDepartementRoleResolver::resolve('EXPLOITATION'))->toBe('metier')
        ->and(ProfilDepartementRoleResolver::resolve('CREDIT'))->toBe('metier');
});

test('sans departement ne mappe pas de role', function () {
    expect(ProfilDepartementRoleResolver::resolve(null))->toBeNull()
        ->and(ProfilDepartementRoleResolver::resolve(''))->toBeNull();
});
