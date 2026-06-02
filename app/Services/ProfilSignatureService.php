<?php

namespace App\Services;

use App\Models\Profil;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class ProfilSignatureService
{
    /**
     * Résout la signature à apposer sur une habilitation (soumission ou profil).
     */
    public function resolveForValidation(
        ?Profil $profil,
        ?string $submitted,
        bool $useRegistered = false
    ): ?string {
        if ($useRegistered && $profil?->signature) {
            return $profil->signature;
        }

        $submitted = $this->normalizeDataUrl($submitted);
        if ($submitted !== null) {
            $this->attachToProfilAfterFirstSignature($profil, $submitted);

            return $submitted;
        }

        return $profil?->signature;
    }

    /**
     * Enregistre la première signature sur le profil (ou remplace si demandé).
     */
    public function attachToProfilAfterFirstSignature(?Profil $profil, ?string $signatureData, bool $forceReplace = false): void
    {
        if ($profil === null) {
            return;
        }

        $signatureData = $this->normalizeDataUrl($signatureData);
        if ($signatureData === null) {
            return;
        }

        if (! $forceReplace && $profil->signature) {
            return;
        }

        $profil->update([
            'signature' => $signatureData,
            'signature_enregistree_at' => now(),
        ]);
    }

    public function storeFromUpload(?Profil $profil, ?UploadedFile $file, bool $forceReplace = false): void
    {
        if ($profil === null || $file === null || ! $file->isValid()) {
            return;
        }

        $mime = $file->getMimeType() ?: 'image/png';
        if (! str_starts_with($mime, 'image/')) {
            return;
        }

        $contents = file_get_contents($file->getRealPath());
        if ($contents === false) {
            return;
        }

        $dataUrl = 'data:'.$mime.';base64,'.base64_encode($contents);
        $this->attachToProfilAfterFirstSignature($profil, $dataUrl, $forceReplace);
    }

    public function normalizeDataUrl(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (! Str::startsWith($value, 'data:image/')) {
            return null;
        }

        return $value;
    }
}
