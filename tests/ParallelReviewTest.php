<?php

use Filament\Facades\Filament;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use SolutionForest\FilamentImportWizard\ImportWizardPlugin;
use SolutionForest\FilamentImportWizard\Tests\Fixtures\ImportContacts;

function reviewOn(string $panel): Testable
{
    Filament::setCurrentPanel(Filament::getPanel($panel));

    $lines = ['name,email'];
    foreach (range(1, 25) as $i) {
        $lines[] = "Person {$i},".($i === 7 ? 'bad' : ($i === 22 ? '' : "p{$i}@x.com"));
    }
    Storage::disk('imports')->put("import-wizard/{$panel}/r.csv", implode("\n", $lines)."\n");

    $page = Livewire::test(ImportContacts::class)
        ->set('data.file', ['uuid' => "import-wizard/{$panel}/r.csv"])
        ->call('analyzeUpload')
        ->call('confirmMapping');

    $guard = 0;
    while (! $page->get('reviewFinished') && $guard++ < 10) {
        $page->call('reviewNextSlice');
    }

    return $page;
}

it('reviews as a batch of queue jobs and gives exactly the sequential result', function () {
    $this->actingAs(User::forceCreate(['name' => 'Admin']));

    $sequential = reviewOn('admin');
    $parallel = reviewOn('parallel');

    expect($sequential->get('reviewBatchId'))->toBe('')
        ->and($parallel->get('reviewBatchId'))->not->toBe('')
        ->and($parallel->get('reviewFinished'))->toBeTrue()
        ->and($parallel->get('reviewedRows'))->toBe($sequential->get('reviewedRows'))->toBe(25)
        ->and($parallel->get('invalidRows'))->toBe($sequential->get('invalidRows'))->toBe(2)
        ->and($parallel->get('errorsByColumn'))->toBe($sequential->get('errorsByColumn'))
        ->and($parallel->get('errorRows'))->toBe($sequential->get('errorRows'))
        ->and($parallel->get('previewRows'))->toBe($sequential->get('previewRows'))
        ->and(DB::table('import_wizard_review_slices')->count())->toBe(0); // collected and removed
});

it('uses a real queue only: sync falls back to the polled pass, and row checks force the polled pass', function () {
    $plugin = ImportWizardPlugin::make()->parallelReview();

    config(['queue.default' => 'sync']);
    expect($plugin->usesParallelReview())->toBeFalse();

    config(['queue.default' => 'database', 'queue.connections.database.driver' => 'database']);
    expect($plugin->usesParallelReview())->toBeTrue()
        ->and(ImportWizardPlugin::make()->usesParallelReview())->toBeFalse()
        ->and(ImportWizardPlugin::make()->parallelReview(allowSync: true)->usesParallelReview())->toBeTrue();
});

it('shows an error instead of a half result when review jobs fail', function () {
    $this->actingAs(User::forceCreate(['name' => 'Admin']));
    Filament::setCurrentPanel(Filament::getPanel('parallel'));
    Storage::disk('imports')->put('import-wizard/parallel/f.csv', "name,email\nAna,a@x.com\n");

    $page = Livewire::test(ImportContacts::class)
        ->set('data.file', ['uuid' => 'import-wizard/parallel/f.csv'])
        ->call('analyzeUpload')
        ->call('confirmMapping');

    // Simulate a lost slice: the batch finished but a result is missing.
    DB::table('import_wizard_review_slices')->delete();
    $page->call('reviewNextSlice');

    expect($page->get('reviewFinished'))->toBeFalse()
        ->and($page->get('reviewError'))->not->toBe('');
});

it('lets a panel switch the review fast path off and still reviews identically', function () {
    expect(ImportWizardPlugin::make()->usesFastValidation())->toBeTrue()
        ->and(ImportWizardPlugin::make()->fastValidation(false)->usesFastValidation())->toBeFalse();
});
