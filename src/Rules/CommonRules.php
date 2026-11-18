<?php

declare(strict_types=1);

namespace SolutionForest\FilamentImportWizard\Rules;

use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Ready-made rule sets for `ImportColumn::rules()`, so the same validation is
 * written once and reused across importers:
 *
 *     ImportColumn::make('email')->rules(CommonRules::email()),
 *     ImportColumn::make('phone')->rules(CommonRules::phone()),
 *
 * Every method returns a plain list that can be merged with extra rules.
 */
final class CommonRules
{
    /**
     * @return list<string|ValidationRule>
     */
    public static function email(bool $required = true, int $max = 254): array
    {
        return [...self::presence($required), 'email:rfc', "max:{$max}"];
    }

    /**
     * @return list<string|ValidationRule>
     */
    public static function phone(bool $required = false): array
    {
        return [...self::presence($required), new PhoneNumber];
    }

    /**
     * @return list<string|ValidationRule>
     */
    public static function name(bool $required = true, int $max = 255): array
    {
        return [...self::presence($required), 'string', "max:{$max}"];
    }

    /**
     * @return list<string|ValidationRule>
     */
    public static function url(bool $required = false): array
    {
        return [...self::presence($required), 'url', 'max:2048'];
    }

    /**
     * @return list<string|ValidationRule>
     */
    public static function integer(bool $required = true, ?int $min = null, ?int $max = null): array
    {
        return [
            ...self::presence($required),
            'integer',
            ...($min !== null ? ["min:{$min}"] : []),
            ...($max !== null ? ["max:{$max}"] : []),
        ];
    }

    /**
     * @param  list<string>  $formats
     * @return list<string|ValidationRule>
     */
    public static function date(bool $required = false, array $formats = ['Y-m-d', 'Y-m-d H:i:s', 'd/m/Y', 'm/d/Y']): array
    {
        return [...self::presence($required), new DateInFormats($formats)];
    }

    /**
     * @param  list<string>  $allowed
     * @return list<string|ValidationRule>
     */
    public static function oneOf(array $allowed, bool $required = true): array
    {
        return [...self::presence($required), new OneOfIgnoringCase($allowed)];
    }

    /**
     * @return list<string>
     */
    protected static function presence(bool $required): array
    {
        return [$required ? 'required' : 'nullable'];
    }
}
