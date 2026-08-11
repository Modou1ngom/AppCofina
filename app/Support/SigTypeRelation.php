<?php

namespace App\Support;

use Illuminate\Validation\Rule;

final class SigTypeRelation
{
    /**
     * @return list<string>
     */
    public static function all(): array
    {
        /** @var list<string> $types */
        $types = config('sig.types_relation', []);

        return $types;
    }

    public static function rule(): \Illuminate\Validation\Rules\In
    {
        return Rule::in(self::all());
    }
}
