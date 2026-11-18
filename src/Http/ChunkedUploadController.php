<?php

declare(strict_types=1);

namespace SolutionForest\FilamentImportWizard\Http;

use Filament\Facades\Filament;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use InvalidArgumentException;
use RuntimeException;
use SolutionForest\FilamentImportWizard\ImportWizardPlugin;
use SolutionForest\FilamentImportWizard\Support\ChunkedUpload;

/**
 * The three endpoints behind the chunked uploader. They are registered as
 * authenticated routes of each panel that enabled it, so the panel's login,
 * guard and middleware apply.
 */
class ChunkedUploadController
{
    public function start(Request $request): JsonResponse
    {
        $name = $request->input('name');
        $size = $request->input('size');

        if (! is_string($name) || ! is_numeric($size)) {
            return response()->json(['message' => 'Invalid request.'], 422);
        }

        return $this->guard(fn (): array => $this->uploads()->begin(
            $name,
            (int) $size,
            $this->owner(),
            ImportWizardPlugin::current()->getMaxUploadSizeInKilobytes(),
        ));
    }

    public function chunk(Request $request): JsonResponse
    {
        $id = $request->input('id');
        $index = $request->input('index');
        $chunk = $request->file('chunk');

        if (! is_string($id) || ! is_numeric($index) || ! $chunk instanceof UploadedFile) {
            return response()->json(['message' => 'Invalid request.'], 422);
        }

        return $this->guard(function () use ($id, $index, $chunk): array {
            $this->uploads()->put($id, (int) $index, $chunk, $this->owner());

            return ['ok' => true];
        });
    }

    public function complete(Request $request): JsonResponse
    {
        $id = $request->input('id');

        if (! is_string($id)) {
            return response()->json(['message' => 'Invalid request.'], 422);
        }

        return $this->guard(fn (): array => ['path' => $this->uploads()->complete($id, $this->owner())]);
    }

    /**
     * @param  callable(): array<string, mixed>  $callback
     */
    protected function guard(callable $callback): JsonResponse
    {
        // Belt and braces: a panel without auth middleware must not accept anonymous uploads.
        if (! Filament::auth()->check()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        try {
            return response()->json($callback());
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        } catch (RuntimeException $exception) {
            report($exception);

            return response()->json(['message' => $exception->getMessage()], 500);
        }
    }

    protected function uploads(): ChunkedUpload
    {
        $plugin = ImportWizardPlugin::current();

        return new ChunkedUpload($plugin->getDisk(), $plugin->getDirectory());
    }

    protected function owner(): string
    {
        $id = Filament::auth()->id();

        return is_int($id) || is_string($id) ? (string) $id : '';
    }
}
