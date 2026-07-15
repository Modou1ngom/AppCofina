<?php

namespace App\Support;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class MissionRapport
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function sections(): array
    {
        return config('mission_rapport.sections', []);
    }

    /**
     * @return array<string, string>
     */
    public static function sectionsPourVue(): array
    {
        return collect(self::sections())
            ->mapWithKeys(fn (array $s) => [$s['cle'] => $s['libelle']])
            ->all();
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function reglesValidation(): array
    {
        $rules = [
            'contenu' => ['nullable', 'string', 'max:20000'],
            'questions_supplementaires' => ['sometimes', 'boolean'],
            'reponses' => ['nullable', 'array'],
        ];

        foreach (self::sections() as $section) {
            $rules['reponses.'.$section['cle']] = ['nullable', 'string', 'max:5000'];
        }

        return $rules;
    }

    /**
     * @param  array<string, mixed>  $reponses
     */
    public static function aAuMoinsUneReponse(array $reponses): bool
    {
        foreach (self::sections() as $section) {
            if (trim((string) ($reponses[$section['cle']] ?? '')) !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, string>  $reponses
     */
    public static function compilerContenu(string $contenuLibre, array $reponses = []): string
    {
        $blocs = [];
        $contenuLibre = trim($contenuLibre);

        if ($contenuLibre !== '') {
            $blocs[] = "Compte-rendu de mission\n".$contenuLibre;
        }

        foreach (self::sections() as $section) {
            $texte = trim($reponses[$section['cle']] ?? '');
            if ($texte === '') {
                continue;
            }

            $blocs[] = $section['libelle']."\n".$texte;
        }

        return implode("\n\n", $blocs);
    }

    /**
     * @param  array<string, string>|null  $reponses
     * @return array<int, array{cle: string, libelle: string, contenu: string}>
     */
    public static function sectionsAffichables(?array $reponses): array
    {
        if ($reponses === null || $reponses === []) {
            return [];
        }

        $result = [];

        foreach (self::sections() as $section) {
            $contenu = trim($reponses[$section['cle']] ?? '');
            if ($contenu === '') {
                continue;
            }

            $result[] = [
                'cle' => $section['cle'],
                'libelle' => $section['libelle'],
                'contenu' => $contenu,
            ];
        }

        return $result;
    }
}
