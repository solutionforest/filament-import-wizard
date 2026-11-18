<?php

use Filament\Actions\Imports\Models\Import;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use SolutionForest\FilamentImportWizard\Tests\Fixtures\Contact;
use SolutionForest\FilamentImportWizard\Tests\Fixtures\ImportContacts;

function wizardCsv(): string
{
    $lines = ['Full Name,E-mail'];
    foreach (range(1, 25) as $i) {
        $lines[] = "Person {$i},".($i === 7 ? 'bad' : "p{$i}@x.com");
    }

    return implode("\n", $lines)."\n";
}

it('walks upload → map → review → import', function () {
    $this->actingAs(User::forceCreate(['name' => 'Admin']));

    Storage::disk('imports')->put('import-wizard/people.csv', wizardCsv());

    // FileUpload has stored the file by the time analyzeUpload() runs.
    $page = Livewire::test(ImportContacts::class)
        ->set('data.file', ['uuid' => 'import-wizard/people.csv'])
        ->call('analyzeUpload');

    expect($page->get('step'))->toBe(2)
        ->and($page->get('headers'))->toBe(['Full Name', 'E-mail'])
        ->and($page->get('data.columnMap'))->toHaveKeys(['name', 'email']);

    $page->call('confirmMapping')->assertHasNoErrors();
    expect($page->get('step'))->toBe(3)->and($page->get('totalRows'))->toBe(25);

    $page->call('reviewNextSlice')->call('reviewNextSlice')->call('reviewNextSlice');

    expect($page->get('reviewFinished'))->toBeTrue()
        ->and($page->get('reviewedRows'))->toBe(25)
        ->and($page->get('invalidRows'))->toBe(1)
        ->and($page->get('errorRows.0.row'))->toBe(7);

    $page->call('startImport');

    $import = Import::query()->latest('id')->first();

    expect($page->get('step'))->toBe(4)
        ->and($import->successful_rows)->toBe(24)
        ->and(Contact::count())->toBe(24);
});

it('blocks continuing while a required column is unmapped', function () {
    $this->actingAs(User::forceCreate(['name' => 'Admin']));

    Storage::disk('imports')->put('import-wizard/x.csv', "foo,bar\n1,2\n");

    Livewire::test(ImportContacts::class)
        ->set('data.file', ['uuid' => 'import-wizard/x.csv'])
        ->call('analyzeUpload')
        ->call('confirmMapping')
        ->assertHasErrors(['data.columnMap.name'])
        ->assertSet('step', 2);
});

it('rejects file paths outside the upload directory and tampered mappings', function () {
    $this->actingAs(User::forceCreate(['name' => 'Admin']));
    Storage::disk('imports')->put('secret.csv', "name,email\nA,a@x.com\n");

    Livewire::test(ImportContacts::class)
        ->set('data.file', ['uuid' => 'secret.csv'])
        ->call('analyzeUpload')
        ->assertSet('step', 1)
        ->assertSet('filePath', '');

    Storage::disk('imports')->put('import-wizard/ok.csv', "name,email\nA,a@x.com\n");

    Livewire::test(ImportContacts::class)
        ->set('data.file', ['uuid' => 'import-wizard/ok.csv'])
        ->call('analyzeUpload')
        ->set('data.columnMap.name', 'nonexistent')
        ->call('confirmMapping')
        ->assertSet('step', 2);
});

it('does not let the client set locked state', function () {
    $this->actingAs(User::forceCreate(['name' => 'Admin']));

    Livewire::test(ImportContacts::class)->set('filePath', '/etc/passwd');
})->throws(CannotUpdateLockedPropertyException::class);
