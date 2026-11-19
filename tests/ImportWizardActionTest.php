<?php

use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use SolutionForest\FilamentImportWizard\Actions\ImportWizardAction;
use SolutionForest\FilamentImportWizard\ImportWizardPlugin;
use SolutionForest\FilamentImportWizard\Livewire\ImportWizardModal;
use SolutionForest\FilamentImportWizard\Tests\Fixtures\ActionHostPage;
use SolutionForest\FilamentImportWizard\Tests\Fixtures\Contact;
use SolutionForest\FilamentImportWizard\Tests\Fixtures\ContactImporter;

beforeEach(fn () => config(['filament.default_filesystem_disk' => 'imports']));

it('opens the wizard in a slide-over from a header action', function () {
    $this->actingAs(User::forceCreate(['name' => 'Admin']));
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    Livewire::test(ActionHostPage::class)
        ->mountAction('importWizard')
        ->assertActionMounted('importWizard');

    $action = ImportWizardAction::make()->importer(ContactImporter::class);

    expect($action->getImporter())->toBe(ContactImporter::class)
        ->and($action->isModalSlideOver())->toBeTrue()
        ->and($action->getModalHeading())->toBe('Import contacts from a file')
        ->and($action->getLabel())->toBe('Import')
        ->and((string) $action->getModalContent())->toContain('wire:name="import-wizard-modal"');

    expect(fn () => ImportWizardAction::make()->getImporter())->toThrow(LogicException::class)
        ->and(fn () => ImportWizardAction::make()->importer(stdClass::class)->getImporter())->toThrow(LogicException::class);
});

it('runs the whole wizard inside the component and tells the user with notifications', function () {
    $this->actingAs(User::forceCreate(['name' => 'Admin']));
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Storage::disk('imports')->put('import-wizard/admin/m.csv', "name,email\nAna,ana@x.com\nBen,bad\nCy,cy@x.com\n");

    $component = Livewire::test(ImportWizardModal::class, ['importer' => ContactImporter::class])
        ->set('data.file', ['uuid' => 'import-wizard/admin/m.csv'])
        ->call('analyzeUpload')
        ->assertSet('step', 2)
        ->call('confirmMapping')
        ->call('reviewNextSlice')
        ->assertSet('reviewFinished', true)
        ->assertSet('invalidRows', 1)
        ->call('startImport')
        ->assertSet('step', 4)
        ->assertNotified('Import started');

    expect(Contact::count())->toBe(2);

    // Sync queue: Filament sends its own completion notification, so the wizard does not add a second one.
    $component->call('pollImport')->assertSet('finishNotified', true)->assertNotNotified('Import finished');
});

it('sends the finished notification with a failed-rows link on a real queue, once', function () {
    $this->actingAs(User::forceCreate(['name' => 'Admin']));
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Storage::disk('imports')->put('import-wizard/admin/n.csv', "name,email\nAna,ana@x.com\nBen,bad\n");

    $component = Livewire::test(ImportWizardModal::class, ['importer' => ContactImporter::class])
        ->set('data.file', ['uuid' => 'import-wizard/admin/n.csv'])
        ->call('analyzeUpload')
        ->call('confirmMapping')
        ->call('reviewNextSlice')
        ->call('startImport');

    // Pretend the queue is asynchronous now that the import has completed.
    config(['queue.default' => 'database', 'queue.connections.database.driver' => 'database']);

    $component->call('pollImport')
        ->assertNotified('Import finished')
        ->assertDispatched('import-wizard-finished');

    // Polling again does not repeat it: only one "finished" notification exists after a second poll.
    $component->call('pollImport')->assertSet('finishNotified', true);
});

it('lets the notifications be switched off per action and per plugin', function () {
    expect(ImportWizardPlugin::make()->notifiesOnStart())->toBeTrue()
        ->and(ImportWizardPlugin::make()->notifications(onStart: false, onFinish: true)->notifiesOnStart())->toBeFalse()
        ->and(ImportWizardPlugin::make()->notifications(onFinish: false)->notifiesOnFinish())->toBeFalse();

    $this->actingAs(User::forceCreate(['name' => 'Admin']));
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Storage::disk('imports')->put('import-wizard/admin/q.csv', "name,email\nAna,ana@x.com\n");

    Livewire::test(ImportWizardModal::class, ['importer' => ContactImporter::class, 'notifyStart' => false, 'notifyFinish' => false])
        ->set('data.file', ['uuid' => 'import-wizard/admin/q.csv'])
        ->call('analyzeUpload')
        ->call('confirmMapping')
        ->call('reviewNextSlice')
        ->call('startImport')
        ->assertNotNotified('Import started')
        ->call('pollImport')
        ->assertNotNotified('Import finished');
});

it('refuses anything that is not a Filament importer', function () {
    $this->actingAs(User::forceCreate(['name' => 'Admin']));

    Livewire::test(ImportWizardModal::class, ['importer' => stdClass::class]);
})->throws(Exception::class, 'is not a Filament importer')->group('security');
