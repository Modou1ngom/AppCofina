<?php

namespace Tests\Unit;

use App\Support\ProfilExcelImport;
use Tests\TestCase;

class ProfilExcelImportTest extends TestCase
{
    public function test_map_columns_from_export_headings(): void
    {
        $header = [
            'Matricule',
            'Nom',
            'Prénom',
            'Email',
            'Téléphone',
            'Fonction',
            'Département',
            'Site',
            'Type de contrat',
            'Statut',
        ];

        $mapped = ProfilExcelImport::mapColumns($header);

        $this->assertSame(0, $mapped['matricule']);
        $this->assertSame(1, $mapped['nom']);
        $this->assertSame(2, $mapped['prenom']);
        $this->assertSame(3, $mapped['email']);
        $this->assertSame(8, $mapped['type_contrat']);
    }

    public function test_normalize_email_from_string(): void
    {
        $this->assertSame('jean.dupont@cofina.sn', ProfilExcelImport::normalizeEmail(' Jean.Dupont@cofina.sn '));
        $this->assertSame('modou.ngom@cofina.sn', ProfilExcelImport::normalizeEmail('Modou NGOM <modou.ngom@cofina.sn>'));
    }

    public function test_find_email_in_row_scans_other_columns(): void
    {
        $row = ['Dupont', 'Jean', 'jean.dupont@cofina.sn', 'CDI'];

        $this->assertSame(
            'jean.dupont@cofina.sn',
            ProfilExcelImport::findEmailInRow($row, null, [0, 1])
        );
    }

    public function test_email_from_login_and_generated_name(): void
    {
        config(['cofina.email_domain' => 'cofinacorp.com']);

        $this->assertSame(
            'aidaraa@cofinacorp.com',
            ProfilExcelImport::emailFromLogin('aidaraa')
        );

        $this->assertSame(
            'amsatou.aidara@cofinacorp.com',
            ProfilExcelImport::generateEmailFromName('Amsatou', 'AIDARA', 'M0766')
        );
    }

    public function test_normalize_type_contrat_variants(): void
    {
        $this->assertSame('CDI', ProfilExcelImport::normalizeTypeContrat('cdi'));
        $this->assertSame('CDD', ProfilExcelImport::normalizeTypeContrat('CDD'));
        $this->assertSame('CDD', ProfilExcelImport::normalizeTypeContrat('C.D.D.'));
        $this->assertSame('CDI', ProfilExcelImport::normalizeTypeContrat('C.D.I.'));
        $this->assertSame('CDD', ProfilExcelImport::normalizeTypeContrat('C D D'));
        $this->assertSame('CDD', ProfilExcelImport::normalizeTypeContrat('Contrat à durée déterminée'));
        $this->assertSame('CDI', ProfilExcelImport::normalizeTypeContrat('Contrat à durée indéterminée'));
        $this->assertSame('Stagiaire', ProfilExcelImport::normalizeTypeContrat('stagiaire'));
        $this->assertSame('Autre', ProfilExcelImport::normalizeTypeContrat('Vacataire'));
    }
}
