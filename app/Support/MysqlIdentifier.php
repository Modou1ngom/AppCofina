<?php

namespace App\Support;

/**
 * Noms d'index / contraintes courts pour MySQL (limite 64 caractères).
 */
final class MysqlIdentifier
{
    public const MAX_LENGTH = 64;

    public static function index(string $prefix, string $label): string
    {
        return self::make($prefix, $label, 'idx');
    }

    public static function unique(string $prefix, string $label): string
    {
        return self::make($prefix, $label, 'uq');
    }

    public static function foreign(string $prefix, string $label): string
    {
        return self::make($prefix, $label, 'fk');
    }

    private static function make(string $prefix, string $label, string $suffix): string
    {
        $name = "{$prefix}_{$label}_{$suffix}";

        if (strlen($name) <= self::MAX_LENGTH) {
            return $name;
        }

        return substr($prefix, 0, 24).'_'.substr(hash('crc32b', $label), 0, 8)."_{$suffix}";
    }
}
