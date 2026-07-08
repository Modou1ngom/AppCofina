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
            'reponses' => ['required', 'array'],
        ];

        foreach (self::sections() as $section) {
            $cle = $section['cle'];
            $field = "reponses.{$cle}";
            $fieldRules = ['nullable', 'string', 'max:5000'];

            if ($section['obligatoire'] ?? false) {
                $min = max(1, (int) ($section['min_length'] ?? 1));
                $fieldRules = ['required', 'string', 'min:'.$min, 'max:5000'];
            } elseif (($section['min_length'] ?? 0) > 0) {
                $fieldRules[] = 'min:'.(int) $section['min_length'];
            }

            $rules[$field] = $fieldRules;
        }

        return $rules;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, string>
     */
    public static function validerReponses(array $payload): array
    {
        $validator = Validator::make($payload, self::reglesValidation(), self::messagesValidation());

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        /** @var array<string, string> $reponses */
        $reponses = $validator->validated()['reponses'] ?? [];

        return collect($reponses)
            ->map(fn ($v) => trim((string) $v))
            ->all();
    }

    /**
     * @return array<string, string>
     */
    private static function messagesValidation(): array
    {
        $messages = [];

        foreach (self::sections() as $section) {
            $field = "reponses.{$section['cle']}";
            $libelle = $section['libelle'];
            $messages["{$field}.required"] = "La rubrique « {$libelle} » est obligatoire.";
            $messages["{$field}.min"] = "La rubrique « {$libelle} » est trop courte.";
        }

        return $messages;
    }

    /**
     * @param  array<string, string>  $reponses
     */
    public static function compilerContenu(array $reponses): string
    {
        $blocs = [];

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
