<?php

use Illuminate\Foundation\Auth\User;
use SolutionForest\FilamentImportWizard\Headless\HeadlessImport;
use SolutionForest\FilamentImportWizard\Tests\Fixtures\Contact;
use SolutionForest\FilamentImportWizard\Tests\Fixtures\ContactImporter;

/** @return list<array{name: string, email: string, phone: string}> */
function interopRows(): array
{
    return [
        ['name' => 'Ana, "the boss"', 'email' => 'ana@x.com', 'phone' => '0071234567'],
        ['name' => "Line one\nLine two", 'email' => 'ben@x.com', 'phone' => '+14155550101'],
        ['name' => 'Zoë Müller 日本語 😀', 'email' => 'zoe@x.com', 'phone' => '(415) 555-0101'],
        ['name' => 'Plain', 'email' => 'plain@x.com', 'phone' => ''],
    ];
}

/** The rows as the importer stores them (an empty cell becomes null). */
function interopExpected(): array
{
    return array_map(fn (array $row): array => array_map(fn (string $value): ?string => $value === '' ? null : $value, $row), interopRows());
}

/** Review the file, import it, and return what ended up in the table. */
function interopImport(string $path): array
{
    Contact::query()->delete();
    $headless = HeadlessImport::for(ContactImporter::class)->file($path, 'imports')->autoMap();
    $review = $headless->review();

    expect($review->isValid())->toBeTrue("{$path}: ".json_encode($review->sample));

    $headless->user(User::forceCreate(['name' => 'Api']))->start();

    return Contact::query()->orderBy('id')->get(['name', 'email', 'phone'])->map->toArray()->all();
}

function interopNormalise(array $rows): array
{
    return array_map(fn ($row) => array_map(fn ($value) => (string) $value, array_values($row)), $rows);
}
