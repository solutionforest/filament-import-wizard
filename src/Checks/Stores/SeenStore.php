<?php

declare(strict_types=1);

namespace SolutionForest\FilamentImportWizard\Checks\Stores;

/**
 * Remembers which row keys a review run has already seen. Implementations must
 * keep their state outside the PHP process, because Review is spread over many
 * requests that may be served by different machines.
 */
interface SeenStore
{
    /**
     * Attach to a run. A $fresh run starts empty; a resumed run keeps its state.
     */
    public function begin(string $runKey, bool $fresh): void;

    /**
     * Record a key.
     *
     * @return bool true when the key is new, false when it was seen before
     */
    public function markSeen(string $key): bool;

    /**
     * Drop the state of a run.
     */
    public function finish(string $runKey): void;
}
