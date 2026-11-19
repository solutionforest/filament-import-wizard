<?php

declare(strict_types=1);

namespace SolutionForest\FilamentImportWizard\Importers;

use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Hands out an importer class for a model. A queue worker only receives the importer's class
 * *name*, so the class is generated on demand from that name: the model class is encoded in it
 * and an autoloader (registered by the service provider) defines it the first time it is used —
 * in the web request and in every worker alike.
 */
final class ModelImporters
{
    public const NAMESPACE = 'SolutionForest\\FilamentImportWizard\\Importers\\Generated\\';

    /**
     * @param  class-string<Model>  $model
     * @return class-string<ModelImporter>
     */
    public static function for(string $model): string
    {
        if (! is_subclass_of($model, Model::class)) {
            throw new InvalidArgumentException("[{$model}] is not an Eloquent model.");
        }

        $class = self::NAMESPACE.'M'.bin2hex($model).'Importer';

        if (! class_exists($class)) {
            self::define($class);
        }

        /** @var class-string<ModelImporter> $class */
        return $class;
    }

    /**
     * `class_basename()` for importers: a generated class reads `ProductImporter`, not a hex name.
     */
    public static function basename(string $importer): string
    {
        if (is_subclass_of($importer, ModelImporter::class)) {
            return class_basename($importer::getModel()).'Importer';
        }

        return class_basename($importer);
    }

    public static function register(): void
    {
        spl_autoload_register(static function (string $class): void {
            if (str_starts_with($class, self::NAMESPACE)) {
                self::define($class);
            }
        });
    }

    protected static function define(string $class): void
    {
        $short = substr($class, strlen(self::NAMESPACE));

        if (preg_match('/^M((?:[0-9a-f]{2})+)Importer$/', $short, $match) !== 1) {
            return;
        }

        $model = (string) hex2bin($match[1]);

        // Only ever generated for real models, never for an arbitrary class name.
        if (! class_exists($model) || ! is_subclass_of($model, Model::class)) {
            return;
        }

        $name = substr($short, 0, -strlen('Importer')).'Importer';
        $exported = var_export($model, true);

        eval('namespace '.rtrim(self::NAMESPACE, '\\').'; final class '.$name.' extends \\'.ModelImporter::class.' { protected static ?string $model = '.$exported.'; }');
    }
}
