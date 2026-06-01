<?php

namespace App\Support;

final class PointageQrPayload
{
    /**
     * Extrait le code public depuis une chaîne brute ou un JSON (clés code_public / codePublic).
     */
    public static function parse(string $qrPayload): ?string
    {
        $raw = trim($qrPayload);
        if ($raw === '') {
            return null;
        }

        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            if (isset($decoded['code_public']) && is_string($decoded['code_public'])) {
                return trim($decoded['code_public']);
            }
            if (isset($decoded['codePublic']) && is_string($decoded['codePublic'])) {
                return trim($decoded['codePublic']);
            }
        }

        return $raw;
    }

    /**
     * Priorité au contenu scanné (qr_payload) puis au code saisi (code_public).
     */
    public static function resolve(?string $qrPayload, ?string $codePublic): ?string
    {
        if ($qrPayload !== null && trim($qrPayload) !== '') {
            $fromQr = self::parse($qrPayload);
            if ($fromQr !== null && $fromQr !== '') {
                return $fromQr;
            }
        }

        if ($codePublic !== null) {
            $t = trim($codePublic);
            if ($t !== '') {
                return $t;
            }
        }

        return null;
    }
}
