<?php

declare(strict_types=1);

namespace SolutionForest\FilamentImportWizard\Actions;

use Closure;
use Filament\Actions\Action;
use Filament\Actions\Imports\Importer;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use LogicException;

/**
 * Opens the import wizard in a slide-over from anywhere an action can live — a resource
 * list page header, a table header, a widget — like Filament's own ImportAction, but with
 * the Upload → Map → Review → Import flow, live preview and pre-import validation.
 *
 *     protected function getHeaderActions(): array
 *     {
 *         return [
 *             ImportWizardAction::make()->importer(ContactImporter::class),
 *         ];
 *     }
 *
 * Filament notifications mark the start and the end of the import (optional, see
 * {@see notifications()}); the slide-over shows live progress in between.
 */
class ImportWizardAction extends Action
{
    /** @var class-string<Importer>|Closure|null */
    protected string|Closure|null $importer = null;

    protected ?bool $notifyOnStart = null;

    protected ?bool $notifyOnFinish = null;

    public static function getDefaultName(): ?string
    {
        return 'importWizard';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label(__('filament-import-wizard::import.action.label'))
            ->icon('heroicon-o-arrow-up-tray')
            ->color('gray')
            ->slideOver()
            ->modalWidth(Width::FiveExtraLarge)
            ->modalHeading(fn (): string => __('filament-import-wizard::import.title', ['label' => $this->getEntityLabel()]))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('filament-import-wizard::import.action.close'))
            ->closeModalByClickingAway(false)
            ->modalContent(fn (): View => view('filament-import-wizard::action.modal', [
                'importer' => $this->getImporter(),
                'notifyStart' => $this->notifyOnStart,
                'notifyFinish' => $this->notifyOnFinish,
            ]));
    }

    /**
     * @param  class-string<Importer>|Closure  $importer
     */
    public function importer(string|Closure $importer): static
    {
        $this->importer = $importer;

        return $this;
    }

    /**
     * Filament notifications when the import starts and when it finishes. Defaults follow
     * `ImportWizardPlugin::notifications()` (both on); the finish toast is skipped on the
     * `sync` queue, where Filament already sends its own completion notification.
     */
    public function notifications(?bool $onStart = true, ?bool $onFinish = true): static
    {
        $this->notifyOnStart = $onStart;
        $this->notifyOnFinish = $onFinish;

        return $this;
    }

    /**
     * @return class-string<Importer>
     */
    public function getImporter(): string
    {
        $importer = $this->evaluate($this->importer);

        if (! is_string($importer) || ! is_subclass_of($importer, Importer::class)) {
            throw new LogicException('ImportWizardAction needs a Filament importer: ->importer(ContactImporter::class).');
        }

        return $importer;
    }

    protected function getEntityLabel(): string
    {
        return Str::of(class_basename($this->getImporter()))->beforeLast('Importer')->headline()->lower()->plural()->toString();
    }
}
