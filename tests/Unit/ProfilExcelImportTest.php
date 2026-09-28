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

    public function test_map_sirh_profile_headers(): void
    {
        $header = [
            'Matricule',
            'Matricule SIRH',
            'Nom',
            'Prénom',
            'Entité',
            'Nationalité',
            'Departement',
            'Site',
            'Genre',
            'Date de naissance',
            'Diplôme',
            'Age',
            'Situation Matrimoniale',
            'Nbre d\'enfant',
            'N° CNI',
            'Numéro de téléphone',
            'Fonction',
            'Catégorie',
            'Front/ Back',
            'N+1',
            'Type de contrat',
            'Durée',
            'Date de début contrat',
            'Date de fin contrat',
            'Date d\'embauche',
            'Date d\'entrée dans l\'Etablissement',
            'Dossier a jour',
            'ANCIENNETE',
            'DATE DE DEPART',
            'MOTIFS',
            'GRADE',
            'H',
            'Adresses Mail',
            'N° Carte Assurance',
        ];

        $mapped = ProfilExcelImport::mapColumns($header);

        $this->assertSame(0, $mapped['matricule']);
        $this->assertSame(1, $mapped['matricule_sirh']);
        $this->assertSame(4, $mapped['entite']);
        $this->assertSame(18, $mapped['type_office']);
        $this->assertSame(19, $mapped['n_plus_1']);
        $this->assertSame(24, $mapped['date_embauche']);
        $this->assertSame(25, $mapped['date_entree']);
        $this->assertSame(28, $mapped['date_sortie']);
        $this->assertSame(29, $mapped['motif_depart']);
        $this->assertSame(31, $mapped['h']);
        $this->assertSame(32, $mapped['email']);
        $this->assertSame(33, $mapped['numero_carte_assurance']);
        $this->assertArrayNotHasKey('login', $mapped);
    }

    public function test_duplicate_matricule_headers_keep_first_as_matricule_and_second_as_sirh(): void
    {
        $mapped = ProfilExcelImport::mapColumns(['Matricule', 'Matricule', 'Nom', 'Prénom']);

        $this->assertSame(0, $mapped['matricule']);
        $this->assertSame(1, $mapped['matricule_sirh']);
        $this->assertSame(2, $mapped['nom']);
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
