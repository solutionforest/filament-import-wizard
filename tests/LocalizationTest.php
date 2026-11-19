<?php

use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use SolutionForest\FilamentImportWizard\Models\AuditStatus;
use SolutionForest\FilamentImportWizard\Tests\Fixtures\ImportContacts;

/**
 * @return array<string, string> dotted key => value
 */
function flattenLang(array $lines, string $prefix = ''): array
{
    $flat = [];

    foreach ($lines as $key => $value) {
        is_array($value) ? $flat += flattenLang($value, "{$prefix}{$key}.") : $flat["{$prefix}{$key}"] = $value;
    }

    return $flat;
}

/**
 * @return list<string> placeholders such as :count
 */
function placeholders(string $text): array
{
    preg_match_all('/:[a-z_]+/', $text, $matches);
    $found = array_unique($matches[0]);
    sort($found);

    return $found;
}

dataset('locales', ['zh_CN', 'zh_TW', 'zh_HK']);

it('ships every key of every group in each locale, with the same placeholders', function (string $locale) {
    $base = dirname(__DIR__).'/resources/lang';

    foreach (['import', 'audit', 'errors', 'rules'] as $group) {
        $english = flattenLang(require "{$base}/en/{$group}.php");
        $translated = flattenLang(require "{$base}/{$locale}/{$group}.php");

        expect(array_keys($translated))->toEqualCanonicalizing(array_keys($english), "{$locale}/{$group} keys");

        foreach ($english as $key => $text) {
            expect(placeholders((string) $translated[$key]))->toBe(placeholders((string) $text), "{$locale}/{$group}.{$key} placeholders");
        }
    }
})->with('locales');

it('uses no hard-coded English in views or UI classes', function () {
    $root = dirname(__DIR__);
    $files = [
        ...glob($root.'/resources/views/*/*.blade.php'),
        $root.'/src/Pages/ImportAuditsPage.php',
        $root.'/src/Models/AuditStatus.php',
    ];

    foreach ($files as $file) {
        $source = (string) file_get_contents($file);

        // Visible text between tags must come from a translation, never be literal words.
        $withoutBlade = (string) preg_replace(['/\{\{.*?\}\}/s', '/@[a-z]+(\(.*?\))?/s', '/<(style|script)\b.*?<\/\1>/s'], '', $source);
        preg_match_all('/>\s*([A-Za-z][A-Za-z ]{3,})\s*</', $withoutBlade, $literal);

        expect($literal[1])->toBe([], basename($file));
    }
});

it('translates the wizard, the audit labels and the error messages', function (string $locale, string $upload, string $approved, string $unknown) {
    app()->setLocale($locale);

    expect(__('filament-import-wizard::import.steps.upload'))->toBe($upload)
        ->and(AuditStatus::Approved->getLabel())->toBe($approved)
        ->and(__('filament-import-wizard::errors.unknown_upload'))->toBe($unknown);
})->with([
    ['en', 'Upload', 'Approved', 'Unknown upload.'],
    ['zh_CN', '上传', '已批准', '未知的上传。'],
    ['zh_TW', '上傳', '已核准', '未知的上傳。'],
    ['zh_HK', '上傳', '已批准', '未知的上傳。'],
]);

it('pluralises counts in English and numbers with the locale', function () {
    app()->setLocale('en');

    expect(trans_choice('filament-import-wizard::import.review.valid', 1, ['count' => '1']))->toBe('1 row is ready to import')
        ->and(trans_choice('filament-import-wizard::import.review.valid', 1200, ['count' => '1,200']))->toBe('1,200 rows are ready to import')
        ->and(trans_choice('filament-import-wizard::import.review.import', 0, ['count' => '0']))->toBe('Import 0 rows');

    app()->setLocale('zh_TW');
    expect(trans_choice('filament-import-wizard::import.review.valid', 5, ['count' => '5']))->toBe('5 列可以匯入');
});

it('renders the wizard in the active locale and lets the page label be translated', function () {
    $this->actingAs(User::forceCreate(['name' => 'Admin']));
    app()->setLocale('zh_TW');
    Storage::disk('imports')->put('import-wizard/admin/i.csv', "name,email\nAna,ana@x.com\nBen,bad\n");

    Livewire::test(ImportContacts::class)
        ->assertSee('從檔案匯入')
        ->set('data.file', ['uuid' => 'import-wizard/admin/i.csv'])
        ->call('analyzeUpload')
        ->assertSee('對應欄位')
        ->assertSee('預覽')
        ->call('confirmMapping')
        ->call('reviewNextSlice')
        ->assertSee('已檢查全部 2 列')
        ->assertSee('1 列有問題');
});
