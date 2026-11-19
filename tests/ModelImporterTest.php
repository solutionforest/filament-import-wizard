<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use SolutionForest\FilamentImportWizard\Actions\ImportWizardAction;
use SolutionForest\FilamentImportWizard\Headless\HeadlessImport;
use SolutionForest\FilamentImportWizard\Importers\ModelImporter;
use SolutionForest\FilamentImportWizard\Importers\ModelImporters;

class QuickProduct extends Model
{
    protected $table = 'quick_products';

    protected $guarded = ['secret'];
}

beforeEach(function () {
    Schema::create('quick_products', function (Blueprint $table): void {
        $table->id();
        $table->string('sku', 20);
        $table->string('title')->nullable();
        $table->string('contact_email')->nullable();
        $table->integer('stock')->default(0);
        $table->decimal('price', 8, 2);
        $table->boolean('active')->default(true);
        $table->date('released_at')->nullable();
        $table->string('secret')->nullable();
        $table->softDeletes();
        $table->timestamps();
    });
});

it('builds import columns from the table', function () {
    $importer = ModelImporters::for(QuickProduct::class);
    $columns = collect($importer::getColumns())->keyBy(fn ($c) => $c->getName());

    expect(is_subclass_of($importer, ModelImporter::class))->toBeTrue()
        ->and($columns->keys()->all())->toBe(['sku', 'title', 'contact_email', 'stock', 'price', 'active', 'released_at'])
        ->and($columns['sku']->getDataValidationRules())->toContain('required')  // max:20 only on drivers that report the length (MySQL, Postgres)
        ->and($columns['sku']->isMappingRequired())->toBeTrue()
        ->and($columns['title']->getDataValidationRules())->toBe(['nullable'])
        ->and($columns['contact_email']->getDataValidationRules())->toBe(['nullable', 'email'])
        ->and($columns['stock']->getDataValidationRules())->toBe(['nullable', 'integer'])
        ->and($columns['price']->getDataValidationRules())->toBe(['required', 'numeric'])
        ->and($columns['active']->getDataValidationRules())->toBe(['nullable', 'boolean'])
        ->and($columns['released_at']->getDataValidationRules())->toBe(['nullable', 'date']);
});

it('gives the same class for the same model, and defines it from its name alone (as a queue worker would)', function () {
    $class = ModelImporters::for(QuickProduct::class);

    expect(ModelImporters::for(QuickProduct::class))->toBe($class)
        ->and($class::getModel())->toBe(QuickProduct::class)
        ->and(ModelImporters::basename($class))->toBe('QuickProductImporter');

    // a fresh process only knows the name
    $name = ModelImporters::NAMESPACE.'M'.bin2hex('Illuminate\\Foundation\\Auth\\User').'Importer';
    expect(class_exists($name))->toBeTrue()->and($name::getModel())->toBe(User::class);
});

it('refuses to generate an importer for anything that is not a model', function () {
    expect(fn () => ModelImporters::for(stdClass::class))->toThrow(InvalidArgumentException::class)
        ->and(class_exists(ModelImporters::NAMESPACE.'M'.bin2hex('stdClass').'Importer'))->toBeFalse()
        ->and(class_exists(ModelImporters::NAMESPACE.'Mnot-hexImporter'))->toBeFalse();
});

it('reviews and imports a file with no importer class written', function () {
    Storage::disk('imports')->put('quick/products.csv', "SKU,Title,Contact email,Stock,Price,Active,Released at\nA-1,Pen,a@x.com,5,1.50,1,2026-01-31\nB-2,,,,9.99,,\n");

    $headless = HeadlessImport::for(ModelImporters::for(QuickProduct::class))->file('quick/products.csv', 'imports')->autoMap();
    $review = $headless->review();

    expect($review->isValid())->toBeTrue()->and($review->totalRows)->toBe(2);

    $headless->user(User::forceCreate(['name' => 'Api']))->start();

    expect(QuickProduct::query()->orderBy('id')->get(['sku', 'title', 'stock', 'price', 'active'])->map->toArray()->all())->toBe([
        ['sku' => 'A-1', 'title' => 'Pen', 'stock' => 5, 'price' => 1.5, 'active' => 1],
        ['sku' => 'B-2', 'title' => null, 'stock' => 0, 'price' => 9.99, 'active' => 1],
    ]);
});

it('flags rows that break the schema-derived rules', function () {
    Storage::disk('imports')->put('quick/bad.csv', "sku,contact_email,stock,price\nA-1,not-mail,x,\n");

    $review = HeadlessImport::for(ModelImporters::for(QuickProduct::class))->file('quick/bad.csv', 'imports')->autoMap()->review();

    expect($review->isValid())->toBeFalse()->and($review->invalidRows)->toBe(1);
});

it('is available on the slide-over action', function () {
    $action = ImportWizardAction::make()->forModel(QuickProduct::class);

    expect($action->getImporter())->toBe(ModelImporters::for(QuickProduct::class));
});
