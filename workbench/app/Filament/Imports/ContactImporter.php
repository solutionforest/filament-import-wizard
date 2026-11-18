<?php

namespace Workbench\App\Filament\Imports;

use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Database\Eloquent\Model;
use SolutionForest\FilamentImportWizard\Rules\CommonRules;
use Workbench\App\Models\Contact;

class ContactImporter extends Importer
{
    protected static ?string $model = Contact::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('name')->requiredMapping()->rules(CommonRules::name(max: 80)),
            ImportColumn::make('email')->guess(['mail', 'email address'])->requiredMapping()->rules(CommonRules::email()),
            ImportColumn::make('phone')->guess(['mobile', 'tel'])->rules(CommonRules::phone()),
            ImportColumn::make('job_title')->label('Job title')->guess(['position', 'role']),
            ImportColumn::make('company'),
        ];
    }

    public function resolveRecord(): ?Model
    {
        return Contact::firstOrNew(['email' => $this->data['email']]);
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        return 'Imported '.number_format($import->successful_rows).' contacts.';
    }
}
