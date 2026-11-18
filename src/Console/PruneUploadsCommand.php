<?php

declare(strict_types=1);

namespace SolutionForest\FilamentImportWizard\Console;

use Filament\Facades\Filament;
use Illuminate\Console\Command;
use SolutionForest\FilamentImportWizard\ImportWizardPlugin;
use SolutionForest\FilamentImportWizard\Support\ChunkedUpload;

/**
 * Removes the chunks of uploads that were started and never finished. Schedule
 * it daily: `$schedule->command('import-wizard:prune-uploads')->daily()`.
 */
class PruneUploadsCommand extends Command
{
    protected $signature = 'import-wizard:prune-uploads {--hours=24 : Remove unfinished uploads older than this}';

    protected $description = 'Delete abandoned chunked-upload fragments from every panel.';

    public function handle(): int
    {
        $removed = 0;

        foreach (Filament::getPanels() as $panel) {
            Filament::setCurrentPanel($panel);
            $plugin = ImportWizardPlugin::current();

            $removed += (new ChunkedUpload($plugin->getDisk(), $plugin->getDirectory()))->prune((int) $this->option('hours'));
        }

        $this->components->info("Removed {$removed} abandoned upload(s).");

        return self::SUCCESS;
    }
}
