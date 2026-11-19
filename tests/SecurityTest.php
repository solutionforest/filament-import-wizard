<?php

use Filament\Actions\Imports\Models\Import;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Auth\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use SolutionForest\FilamentImportWizard\Audit\ImportApprovals;
use SolutionForest\FilamentImportWizard\Livewire\ImportWizardModal;
use SolutionForest\FilamentImportWizard\Models\AuditStatus;
use SolutionForest\FilamentImportWizard\Models\ImportAudit;
use SolutionForest\FilamentImportWizard\Pages\ImportAuditsPage;
use SolutionForest\FilamentImportWizard\Support\ChunkedUpload;
use SolutionForest\FilamentImportWizard\Tests\Fixtures\Contact;
use SolutionForest\FilamentImportWizard\Tests\Fixtures\ContactImporter;
use SolutionForest\FilamentImportWizard\Tests\Fixtures\ImportContacts;

/*
 | Security-focused tests (`composer security`, the "security" CI job): what a hostile browser,
 | a hostile file or an unauthorised user must NOT be able to do.
 */

function securityRun(string $panel, string $csv): Testable
{
    Filament::setCurrentPanel(Filament::getPanel($panel));
    $directory = $panel === 'staff' ? 'staff-imports/staff' : "import-wizard/{$panel}";
    Storage::disk('imports')->put("{$directory}/s.csv", $csv);

    return Livewire::test(ImportContacts::class)
        ->set('data.file', ['uuid' => "{$directory}/s.csv"])
        ->call('analyzeUpload')
        ->call('confirmMapping')
        ->call('reviewNextSlice');
}

it('escapes hostile cell values everywhere they are shown (XSS)', function () {
    $this->actingAs(User::forceCreate(['name' => 'Admin']));
    $script = '<script>alert(1)</script>';
    $image = '<img src=x onerror=alert(2)>';

    $page = securityRun('admin', "name,email\n\"{$script}\",\"{$image}\"\nBen,ben@x.com\n");

    $page->assertDontSeeHtml($script)
        ->assertDontSeeHtml('<img src=x onerror=alert(2)>')
        ->assertSeeHtml('&lt;script&gt;alert(1)&lt;/script&gt;')
        ->assertSeeHtml('&lt;img src=x onerror=alert(2)&gt;');

    // The audit history modal prints user-supplied notes.
    $audit = ImportAudit::query()->create(['importer' => ContactImporter::class, 'file_name' => $script, 'disk' => 'imports', 'upload_path' => 'a', 'file_path' => 'a', 'status' => AuditStatus::Rejected, 'checksum' => $script]);
    $audit->refresh()->events()->create(['to_status' => 'rejected', 'note' => $script]);
    $html = view('filament-import-wizard::audit.history', ['audit' => $audit->load('events')])->render();

    expect($html)->not->toContain($script)->and($html)->toContain('&lt;script&gt;');
})->group('security');

it('ignores wizard steps called out of order or with nonsense arguments', function () {
    $this->actingAs(User::forceCreate(['name' => 'Admin']));
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Storage::disk('imports')->put('import-wizard/admin/o.csv', "name,email\nAna,ana@x.com\n");

    $page = Livewire::test(ImportContacts::class);

    // Nothing uploaded yet: importing, reviewing and mapping do nothing harmful.
    $page->call('startImport')->call('reviewNextSlice')->call('pollImport')->call('selectSheet', -1)->call('selectSheet', 99)->call('selectSheet', 'x')
        ->assertSet('step', 1)->assertSet('importId', null);

    // Mapped but not reviewed: the import button is inert.
    $page->set('data.file', ['uuid' => 'import-wizard/admin/o.csv'])->call('analyzeUpload')->call('confirmMapping')
        ->call('startImport')
        ->assertSet('step', 3)->assertSet('importId', null);

    expect(Import::query()->count())->toBe(0)->and(Contact::count())->toBe(0);
})->group('security');

it('does not let the client change the importer, the file or the audit of a slide-over run', function () {
    $this->actingAs(User::forceCreate(['name' => 'Admin']));

    foreach (['importer', 'uploadPath', 'filePath', 'auditId', 'importId', 'reviewToken', 'reviewBatchId', 'awaitingApproval', 'finishNotified'] as $property) {
        expect(fn () => Livewire::test(ImportWizardModal::class, ['importer' => ContactImporter::class])->set($property, $property === 'awaitingApproval' || $property === 'finishNotified' ? true : 'x'))
            ->toThrow(CannotUpdateLockedPropertyException::class, $property);
    }
})->group('security');

it('only passes importer-declared options on, never arbitrary client data', function () {
    $this->actingAs(User::forceCreate(['name' => 'Admin']));
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $page = Livewire::test(ImportContacts::class)->set('data.evil', 'x')->set('data.overwrite', true);

    $options = (fn () => $this->getImporterOptions())->call($page->instance());

    expect($options)->toBe([]);
})->group('security');

it('denies approval to users the app does not authorise, even when they are not the uploader', function () {
    $maker = User::forceCreate(['name' => 'Maker']);
    $outsider = User::forceCreate(['name' => 'Outsider']);
    $this->actingAs($maker);
    securityRun('staff', "name,email\nAna,ana@x.com\n")->call('startImport');

    $audit = ImportAudit::query()->sole();
    Filament::setCurrentPanel(Filament::getPanel('staff'));
    Gate::define('approve-imports', fn () => false);

    expect(app(ImportApprovals::class)->canDecide($audit, $outsider))->toBeFalse()
        ->and(fn () => app(ImportApprovals::class)->approve($audit, $outsider))->toThrow(AuthorizationException::class)
        ->and(Contact::count())->toBe(0)
        ->and($audit->refresh()->status)->toBe(AuditStatus::PendingApproval);

    // Nor can the approve action be reached from the audit page.
    $this->actingAs($outsider);
    Livewire::test(ImportAuditsPage::class)->assertTableActionHidden('approve', $audit);
})->group('security');

it('shows other people audits only to users the app allows to see them', function () {
    $maker = User::forceCreate(['name' => 'Maker']);
    $other = User::forceCreate(['name' => 'Other']);
    $this->actingAs($maker);
    securityRun('staff', "name,email\nAna,ana@x.com\n")->call('startImport');
    $audit = ImportAudit::query()->sole();

    Filament::setCurrentPanel(Filament::getPanel('staff'));
    Gate::define('view-all-import-audits', fn () => false);

    $this->actingAs($other);
    Livewire::test(ImportAuditsPage::class)->assertCanNotSeeTableRecords([$audit]);

    $this->actingAs($maker);
    Livewire::test(ImportAuditsPage::class)->assertCanSeeTableRecords([$audit]);
})->group('security');

it('keeps chunk uploads confined: sanitised names, allow-listed types, owner-only, no traversal', function () {
    $uploads = new ChunkedUpload('imports', 'staff-imports/staff');

    foreach (['a.csv.php', 'a.php', '.htaccess', 'a.csv.exe', 'noextension', '../../a.sh'] as $name) {
        expect(fn () => $uploads->begin($name, 10, 'u1'))->toThrow(InvalidArgumentException::class);
    }

    $begin = $uploads->begin('../../etc/pass wd?.csv', 3, 'u1');
    $uploads->put($begin['id'], 0, UploadedFile::fake()->createWithContent('c', 'a,b'), 'u1');
    $path = $uploads->complete($begin['id'], 'u1');

    expect($path)->toStartWith('staff-imports/staff/')->and($path)->not->toContain('..')->and($path)->not->toContain(' ')->and($path)->not->toContain('?');

    // Another user cannot continue, finish or probe someone else's upload id.
    $second = $uploads->begin('b.csv', 3, 'u1');
    foreach ([fn () => $uploads->complete($second['id'], 'u2'), fn () => $uploads->put($second['id'], 0, UploadedFile::fake()->createWithContent('c', 'a,b'), 'u2')] as $attempt) {
        expect($attempt)->toThrow(InvalidArgumentException::class);
    }
    expect(fn () => $uploads->complete('../../../etc', 'u1'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $uploads->complete(str_repeat('g', 32), 'u1'))->toThrow(InvalidArgumentException::class);
})->group('security');

it('refuses chunk uploads that lie about their size or exceed the cap', function () {
    $uploads = new ChunkedUpload('imports', 'staff-imports/staff');
    $begin = $uploads->begin('a.csv', 10, 'u1', maxKilobytes: 1);

    expect(fn () => $uploads->begin('big.csv', 5 * 1024, 'u1', maxKilobytes: 1))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $uploads->put($begin['id'], 0, UploadedFile::fake()->createWithContent('c', 'way more than ten bytes'), 'u1'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $uploads->complete($begin['id'], 'u1'))->toThrow(RuntimeException::class);
})->group('security');

it('rejects chunk endpoint abuse over HTTP: other users, bad ids, missing fields', function () {
    config(['app.env' => 'local']);
    $owner = User::forceCreate(['name' => 'Owner']);
    $intruder = User::forceCreate(['name' => 'Intruder']);

    $this->actingAs($owner);
    $id = $this->postJson('/staff/import-wizard/upload/start', ['name' => 'x.csv', 'size' => 3])->assertOk()->json('id');

    $this->actingAs($intruder);
    $this->postJson('/staff/import-wizard/upload/complete', ['id' => $id])->assertStatus(422);
    $this->post('/staff/import-wizard/upload/chunk', ['id' => $id, 'index' => 0, 'chunk' => UploadedFile::fake()->createWithContent('c', 'a,b')], ['Accept' => 'application/json'])->assertStatus(422);
    $this->postJson('/staff/import-wizard/upload/complete', ['id' => '../../x'])->assertStatus(422);
    $this->postJson('/staff/import-wizard/upload/start', [])->assertStatus(422);
    $this->postJson('/staff/import-wizard/upload/start', ['name' => ['a'], 'size' => 'x'])->assertStatus(422);
})->group('security');
