<?php

declare(strict_types=1);

namespace SolutionForest\FilamentImportWizard\Headless;

use RuntimeException;

/**
 * Thrown by {@see HeadlessImport::start()} when the review found problems and the caller did not opt in to importing the valid rows anyway.
 */
final class ImportBlockedException extends RuntimeException
{
    public function __construct(public readonly ReviewResult $review)
    {
        parent::__construct("{$review->invalidRows} of {$review->totalRows} rows have problems; nothing was imported.");
    }
}
