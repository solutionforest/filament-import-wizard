<?php

declare(strict_types=1);

namespace SolutionForest\FilamentImportWizard\Pages;

use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use SolutionForest\FilamentImportWizard\Audit\ImportApprovals;
use SolutionForest\FilamentImportWizard\ImportWizardPlugin;
use SolutionForest\FilamentImportWizard\Models\AuditStatus;
use SolutionForest\FilamentImportWizard\Models\ImportAudit;
use Throwable;

/**
 * The audit trail of every wizard run, with the approve / reject actions of the
 * maker–checker flow. Registered automatically by {@see ImportWizardPlugin}.
 */
class ImportAuditsPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $slug = 'import-audit';

    protected static ?string $title = 'Import audit';

    protected static ?string $navigationLabel = 'Import audit';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected string $view = 'filament-import-wizard::pages.import-audits';

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => $this->scopedQuery())
            ->defaultSort('id', 'desc')
            ->poll('10s')
            ->columns([
                TextColumn::make('id')->label('#')->sortable(),
                TextColumn::make('created_at')->label('When')->dateTime()->sortable(),
                TextColumn::make('user.name')->label('Uploaded by')->placeholder('—'),
                TextColumn::make('file_name')->label('File')->searchable()->limit(30)->description(fn (ImportAudit $record): string => class_basename($record->importer)),
                TextColumn::make('total_rows')->label('Rows')->numeric(),
                TextColumn::make('invalid_rows')->label('Problems')->numeric(),
                TextColumn::make('status')->badge(),
            ])
            ->filters([
                SelectFilter::make('status')->options(collect(AuditStatus::cases())->mapWithKeys(fn (AuditStatus $status): array => [$status->value => $status->getLabel()])->all()),
            ])
            ->recordActions([
                Action::make('history')
                    ->label('History')
                    ->icon('heroicon-o-clock')
                    ->modalHeading(fn (ImportAudit $record): string => "Import #{$record->id}")
                    ->modalContent(fn (ImportAudit $record): View => view('filament-import-wizard::audit.history', ['audit' => $record->load('events')]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),
                Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn (ImportAudit $record): bool => $this->canDecide($record))
                    ->requiresConfirmation()
                    ->modalDescription('The file will be imported now, as its uploader.')
                    ->schema([Textarea::make('note')->label('Note (optional)')])
                    ->action(fn (ImportAudit $record, array $data) => $this->decide($record, true, $this->note($data))),
                Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->visible(fn (ImportAudit $record): bool => $this->canDecide($record))
                    ->schema([Textarea::make('note')->label('Reason')->required()])
                    ->action(fn (ImportAudit $record, array $data) => $this->decide($record, false, $this->note($data))),
            ]);
    }

    /**
     * @return Builder<ImportAudit>
     */
    protected function scopedQuery(): Builder
    {
        $query = ImportAudit::query()->with('user');
        $user = Filament::auth()->user();
        $panelId = Filament::getCurrentOrDefaultPanel()?->getId();

        if ($panelId !== null) {
            $query->where('panel_id', $panelId);
        }

        if ($user === null) {
            return $query->whereRaw('1 = 0');
        }

        return ImportWizardPlugin::current()->userCanViewAll($user)
            ? $query
            : $query->where('user_id', $user->getAuthIdentifier());
    }

    protected function canDecide(ImportAudit $audit): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof Authenticatable && app(ImportApprovals::class)->canDecide($audit, $user);
    }

    /**
     * @param  array<mixed>  $data
     */
    protected function note(array $data): ?string
    {
        $note = $data['note'] ?? null;

        return is_string($note) && $note !== '' ? $note : null;
    }

    protected function decide(ImportAudit $audit, bool $approve, ?string $note): void
    {
        $user = Filament::auth()->user();

        if (! $user instanceof Authenticatable) {
            return;
        }

        try {
            $approve
                ? app(ImportApprovals::class)->approve($audit, $user, $note)
                : app(ImportApprovals::class)->reject($audit, $user, $note ?? '');
        } catch (Throwable $exception) {
            Notification::make()->title($exception->getMessage())->danger()->send();

            return;
        }

        Notification::make()->title($approve ? 'Import approved and started' : 'Import rejected')->success()->send();
    }
}
