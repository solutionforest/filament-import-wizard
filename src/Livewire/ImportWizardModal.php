<?php

declare(strict_types=1);

namespace SolutionForest\FilamentImportWizard\Livewire;

use Filament\Actions\Imports\Importer;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Illuminate\Contracts\View\View;
use InvalidArgumentException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use SolutionForest\FilamentImportWizard\Actions\ImportWizardAction;
use SolutionForest\FilamentImportWizard\Concerns\ManagesImportWizard;

/**
 * The import wizard as a self-contained Livewire component, rendered inside the
 * slide-over of {@see ImportWizardAction}.
 * It runs exactly the same wizard as the full page (the logic is the shared
 * {@see ManagesImportWizard} concern), it just takes the importer as a parameter.
 *
 * @property-read Schema $content
 */
class ImportWizardModal extends Component implements HasSchemas
{
    use InteractsWithSchemas;
    use ManagesImportWizard;

    /** @var class-string<Importer> */
    #[Locked]
    public string $importer;

    /** null = follow the plugin setting */
    #[Locked]
    public ?bool $notifyStart = null;

    #[Locked]
    public ?bool $notifyFinish = null;

    public function mount(string $importer, ?bool $notifyStart = null, ?bool $notifyFinish = null): void
    {
        if (! is_subclass_of($importer, Importer::class)) {
            throw new InvalidArgumentException("[{$importer}] is not a Filament importer.");
        }

        $this->importer = $importer;
        $this->notifyStart = $notifyStart;
        $this->notifyFinish = $notifyFinish;

        $this->content->fill();
    }

    protected function importerClass(): string
    {
        return $this->importer;
    }

    protected function notifiesOnStart(): bool
    {
        return $this->notifyStart ?? $this->getPlugin()->notifiesOnStart();
    }

    protected function notifiesOnFinish(): bool
    {
        return $this->notifyFinish ?? $this->getPlugin()->notifiesOnFinish();
    }

    public function render(): View
    {
        return view('filament-import-wizard::livewire.import-wizard-modal');
    }
}
