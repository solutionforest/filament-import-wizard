<?php

use Filament\Facades\Filament;
use Illuminate\Foundation\Auth\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use SolutionForest\FilamentImportWizard\Support\ChunkedUpload;
use SolutionForest\FilamentImportWizard\Tests\Fixtures\ImportContacts;

it('derives a chunk size that fits PHP limits', function () {
    expect(ChunkedUpload::safeChunkSize())->toBeGreaterThan(0)->toBeLessThanOrEqual(8 * 1024 * 1024);
});

it('joins chunks into the final file on the disk', function () {
    $uploads = new ChunkedUpload('imports', 'staff-imports/staff');
    $content = str_repeat("name,email\nAna,ana@x.com\n", 50);

    $begin = $uploads->begin('people.csv', strlen($content), 'u1');
    $parts = str_split($content, $begin['chunkSize']);

    expect($parts)->toHaveCount($begin['chunks']);

    foreach ($parts as $index => $part) {
        $file = UploadedFile::fake()->createWithContent('chunk', $part);
        $uploads->put($begin['id'], $index, $file, 'u1');
    }

    $path = $uploads->complete($begin['id'], 'u1');

    expect(Storage::disk('imports')->get($path))->toBe($content)
        ->and($path)->toStartWith('staff-imports/staff/')
        ->and(Storage::disk('imports')->directories('staff-imports/staff/.chunks'))->toBe([]);
});

it('keeps uploads private to the user who started them and rejects bad input', function () {
    $uploads = new ChunkedUpload('imports', 'staff-imports/staff');
    $begin = $uploads->begin('a.csv', 10, 'u1');

    expect(fn () => $uploads->put($begin['id'], 0, UploadedFile::fake()->createWithContent('c', '0123456789'), 'u2'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $uploads->put($begin['id'], 3, UploadedFile::fake()->createWithContent('c', '0123456789'), 'u1'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $uploads->put($begin['id'], 0, UploadedFile::fake()->createWithContent('c', '01'), 'u1'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $uploads->complete($begin['id'], 'u1'))->toThrow(RuntimeException::class)
        ->and(fn () => $uploads->begin('evil.php', 10, 'u1'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $uploads->begin('big.csv', 10 * 1024 * 1024, 'u1', 1024))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $uploads->complete('../../etc', 'u1'))->toThrow(InvalidArgumentException::class);
});

it('prunes abandoned uploads', function () {
    $uploads = new ChunkedUpload('imports', 'staff-imports/staff');
    $uploads->begin('old.csv', 10, 'u1');

    expect($uploads->prune(24))->toBe(0)
        ->and($uploads->prune(0))->toBe(1);
});

it('serves the three endpoints on a panel that enabled chunked uploads', function () {
    config(['app.env' => 'local']);
    $this->actingAs(User::forceCreate(['name' => 'Maker']));

    $content = "name,email\nAna,ana@x.com\nBen,ben@x.com\n";

    $start = $this->postJson('/staff/import-wizard/upload/start', ['name' => 'web.csv', 'size' => strlen($content)])->assertOk();
    $id = $start->json('id');

    foreach (str_split($content, $start->json('chunkSize')) as $index => $part) {
        $this->post('/staff/import-wizard/upload/chunk', [
            'id' => $id,
            'index' => $index,
            'chunk' => UploadedFile::fake()->createWithContent('chunk', $part),
        ], ['Accept' => 'application/json'])->assertOk();
    }

    $path = $this->postJson('/staff/import-wizard/upload/complete', ['id' => $id])->assertOk()->json('path');

    expect(Storage::disk('imports')->get($path))->toBe($content);

    $this->postJson('/staff/import-wizard/upload/start', ['name' => 'x.exe', 'size' => 5])->assertStatus(422);
});

it('does not expose the endpoints on panels that did not enable them', function () {
    config(['app.env' => 'local']);

    $this->postJson('/admin/import-wizard/upload/start', ['name' => 'a.csv', 'size' => 5])->assertNotFound();
});

it('rejects guests', function () {
    config(['app.env' => 'local']);

    $this->postJson('/staff/import-wizard/upload/start', ['name' => 'a.csv', 'size' => 5])->assertUnauthorized();
});

it('continues into the wizard once the browser reports a finished chunked upload', function () {
    $this->actingAs(User::forceCreate(['name' => 'Maker']));
    Filament::setCurrentPanel(Filament::getPanel('staff'));

    $uploads = new ChunkedUpload('imports', 'staff-imports/staff');
    $content = "name,email\nAna,ana@x.com\n";
    $begin = $uploads->begin('people.csv', strlen($content), (string) auth()->id());
    $uploads->put($begin['id'], 0, UploadedFile::fake()->createWithContent('c', $content), (string) auth()->id());
    $path = $uploads->complete($begin['id'], (string) auth()->id());

    Livewire::test(ImportContacts::class)
        ->call('chunkedUploadFinished', $path)
        ->assertSet('step', 2)
        ->assertSet('fileName', 'people.csv');

    // Paths outside the directory or inside the chunk area are ignored.
    Livewire::test(ImportContacts::class)
        ->call('chunkedUploadFinished', 'staff-imports/staff/.chunks/x/manifest.json')
        ->assertSet('step', 1)
        ->call('chunkedUploadFinished', 'other/people.csv')
        ->assertSet('step', 1);
});
