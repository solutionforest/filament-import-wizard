<?php

namespace Workbench\App\Filament\Imports;

use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Database\Eloquent\Model;
use Workbench\App\Models\Contact;
use Workbench\App\Rules\ContactRules;

class ContactImporter extends Importer
{
    protected static ?string $model = Contact::class;

    public static function getColumns(): array
    {
        return ContactRules::import([
            ImportColumn::make('name')->requiredMapping(),
            ImportColumn::make('email')->guess(['mail', 'email address'])->requiredMapping(),
            ImportColumn::make('phone')->guess(['mobile', 'tel']),
            ImportColumn::make('job_title')->label('Job title')->guess(['position', 'role']),
            ImportColumn::make('company'),
        ]);
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
