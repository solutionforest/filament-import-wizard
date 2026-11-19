<?php

use Filament\Actions\Imports\Models\Import;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use SolutionForest\FilamentImportWizard\Audit\ImportApprovals;
use SolutionForest\FilamentImportWizard\Events\ImportAuditStatusChanged;
use SolutionForest\FilamentImportWizard\ImportWizardPlugin;
use SolutionForest\FilamentImportWizard\Models\AuditStatus;
use SolutionForest\FilamentImportWizard\Models\ImportAudit;
use SolutionForest\FilamentImportWizard\Pages\ImportAuditsPage;
use SolutionForest\FilamentImportWizard\Tests\Fixtures\Contact;
use SolutionForest\FilamentImportWizard\Tests\Fixtures\ImportContacts;

function runWizard(string $panel, string $directory, string $csv): Testable
{
    Filament::setCurrentPanel(Filament::getPanel($panel));
    Storage::disk('imports')->put("{$directory}/audit.csv", $csv);

    $page = Livewire::test(ImportContacts::class)
        ->set('data.file', ['uuid' => "{$directory}/audit.csv"])
        ->call('analyzeUpload')
        ->call('confirmMapping');

    while (! $page->get('reviewFinished')) {
        $page->call('reviewNextSlice');
    }

    return $page->call('startImport');
}

it('records the whole run in the audit trail without approval', function () {
    Event::fake([ImportAuditStatusChanged::class]);
    $this->actingAs(User::forceCreate(['name' => 'Maker']));

    runWizard('admin', 'import-wizard/admin', "name,email\nAna,ana@x.com\nBen,bad\n");

    $audit = ImportAudit::query()->sole();

    expect($audit->status)->toBe(AuditStatus::CompletedWithFailures)
        ->and($audit->checksum)->toBe(hash('sha256', "name,email\nAna,ana@x.com\nBen,bad\n"))
        ->and($audit->total_rows)->toBe(2)
        ->and($audit->invalid_rows)->toBe(1)
        ->and($audit->successful_rows)->toBe(1)
        ->and($audit->failed_rows)->toBe(1)
        ->and($audit->panel_id)->toBe('admin')
        ->and($audit->column_map)->toBe(['name' => 'name', 'email' => 'email'])
        ->and($audit->error_summary['errors_by_column'])->toBe(['email' => 1])
        ->and($audit->events->pluck('to_status')->all())->toBe(['uploaded', 'reviewed', 'importing', 'completed_with_failures']);

    Event::assertDispatched(ImportAuditStatusChanged::class, fn ($event) => $event->to === AuditStatus::Reviewed);
});

it('holds the import for approval, then runs it as the uploader when a second user approves', function () {
    $maker = User::forceCreate(['name' => 'Maker']);
    $checker = User::forceCreate(['name' => 'Checker']);
    $this->actingAs($maker);

    $page = runWizard('staff', 'staff-imports/staff', "name,email\nAna,ana@x.com\nBen,ben@x.com\n");

    expect($page->get('awaitingApproval'))->toBeTrue()
        ->and(Contact::count())->toBe(0)
        ->and(Import::query()->count())->toBe(0);

    $audit = ImportAudit::query()->sole();
    expect($audit->status)->toBe(AuditStatus::PendingApproval);

    $approvals = app(ImportApprovals::class);

    // Four-eyes: the uploader cannot approve their own import.
    expect(fn () => $approvals->approve($audit, $maker))->toThrow(AuthorizationException::class);

    Filament::setCurrentPanel(Filament::getPanel('staff'));
    $approvals->approve($audit, $checker, 'looks right');

    $audit->refresh();

    expect(Contact::count())->toBe(2)
        ->and($audit->status)->toBe(AuditStatus::Completed)
        ->and($audit->decided_by)->toBe($checker->getKey())
        ->and($audit->decision_note)->toBe('looks right')
        ->and(Import::query()->sole()->user_id)->toBe($maker->getKey())
        ->and($audit->events->pluck('to_status')->all())->toBe(['uploaded', 'reviewed', 'pending_approval', 'approved', 'importing', 'completed']);

    // A decided audit cannot be decided again.
    expect(fn () => $approvals->reject($audit, $checker, 'late'))->toThrow(AuthorizationException::class);
})->group('security');

it('can reject a pending import, which then never runs', function () {
    $maker = User::forceCreate(['name' => 'Maker']);
    $checker = User::forceCreate(['name' => 'Checker']);
    $this->actingAs($maker);

    runWizard('staff', 'staff-imports/staff', "name,email\nAna,ana@x.com\n");
    $audit = ImportAudit::query()->sole();

    Filament::setCurrentPanel(Filament::getPanel('staff'));
    app(ImportApprovals::class)->reject($audit, $checker, 'wrong file');

    expect($audit->refresh()->status)->toBe(AuditStatus::Rejected)
        ->and($audit->decision_note)->toBe('wrong file')
        ->and(Contact::count())->toBe(0);
})->group('security');

it('lets an approver decide from the audit page', function () {
    $maker = User::forceCreate(['name' => 'Maker']);
    $checker = User::forceCreate(['name' => 'Checker']);
    $this->actingAs($maker);

    runWizard('staff', 'staff-imports/staff', "name,email\nAna,ana@x.com\n");
    $audit = ImportAudit::query()->sole();

    // The maker sees the row but not the approve action.
    Filament::setCurrentPanel(Filament::getPanel('staff'));
    Livewire::test(ImportAuditsPage::class)
        ->assertCanSeeTableRecords([$audit])
        ->assertTableActionHidden('approve', $audit);

    $this->actingAs($checker);
    Filament::setCurrentPanel(Filament::getPanel('staff'));

    Livewire::test(ImportAuditsPage::class)
        ->assertTableActionVisible('approve', $audit)
        ->callTableAction('approve', $audit, ['note' => 'ok']);

    expect($audit->refresh()->status)->toBe(AuditStatus::Completed)
        ->and(Contact::count())->toBe(1);
})->group('security');

it('is off by default: a panel without the audit option writes no audit records', function () {
    $this->actingAs(User::forceCreate(['name' => 'Maker']));

    runWizard('plain', 'import-wizard/plain', "name,email\nAna,ana@x.com\n");

    expect(ImportAudit::query()->count())->toBe(0)
        ->and(Contact::count())->toBe(1);

    Filament::setCurrentPanel(Filament::getPanel('plain'));
    expect(ImportWizardPlugin::current()->isAuditEnabled())->toBeFalse()
        ->and(Filament::getPanel('plain')->getPages())->not->toContain(ImportAuditsPage::class)
        ->and(Filament::getPanel('admin')->getPages())->toContain(ImportAuditsPage::class);
});

it('switches auditing on when approval is required', function () {
    expect(ImportWizardPlugin::make()->isAuditEnabled())->toBeFalse()
        ->and(ImportWizardPlugin::make()->audit()->isAuditEnabled())->toBeTrue()
        ->and(ImportWizardPlugin::make()->requireApproval()->isAuditEnabled())->toBeTrue()
        ->and(ImportWizardPlugin::make()->requireApproval(false)->isAuditEnabled())->toBeFalse();
});
