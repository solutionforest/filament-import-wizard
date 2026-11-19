<?php

namespace SolutionForest\FilamentImportWizard\Tests;

use BladeUI\Heroicons\BladeHeroiconsServiceProvider;
use BladeUI\Icons\BladeIconsServiceProvider;
use Filament\Actions\ActionsServiceProvider;
use Filament\FilamentServiceProvider;
use Filament\Forms\FormsServiceProvider;
use Filament\Infolists\InfolistsServiceProvider;
use Filament\Notifications\NotificationsServiceProvider;
use Filament\Schemas\SchemasServiceProvider;
use Filament\Support\SupportServiceProvider;
use Filament\Tables\TablesServiceProvider;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use SolutionForest\FilamentImportWizard\ImportWizardServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            BladeIconsServiceProvider::class,
            BladeHeroiconsServiceProvider::class,
            LivewireServiceProvider::class,
            SupportServiceProvider::class,
            ActionsServiceProvider::class,
            FormsServiceProvider::class,
            InfolistsServiceProvider::class,
            NotificationsServiceProvider::class,
            SchemasServiceProvider::class,
            TablesServiceProvider::class,
            FilamentServiceProvider::class,
            ImportWizardServiceProvider::class,
            Fixtures\TestPanelProvider::class,
            Fixtures\StaffPanelProvider::class,
            Fixtures\PlainPanelProvider::class,
            Fixtures\ParallelPanelProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app->bind(Authenticatable::class, User::class);
        $app['config']->set('database.default', 'testing');
        $app['config']->set('queue.default', 'sync');
        $app['config']->set('cache.default', 'array');
        $app['config']->set('queue.batching.database', 'testing');
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
        $app['config']->set('filesystems.disks.imports', [
            'driver' => 'local',
            'root' => sys_get_temp_dir().'/import-wizard-tests',
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->timestamps();
        });
        Schema::create('contacts', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->timestamps();
        });
        Schema::create('job_batches', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('name');
            $table->integer('total_jobs');
            $table->integer('pending_jobs');
            $table->integer('failed_jobs');
            $table->longText('failed_job_ids');
            $table->mediumText('options')->nullable();
            $table->integer('cancelled_at')->nullable();
            $table->integer('created_at');
            $table->integer('finished_at')->nullable();
        });

        foreach (glob(base_path('../../../../vendor/filament/actions/database/migrations/*.php') ?: []) as $_) {
        }
        foreach (array_merge(glob(dirname(__DIR__).'/vendor/filament/actions/database/migrations/*.php') ?: [], glob(dirname(__DIR__).'/database/migrations/*.php') ?: []) as $migration) {
            (require $migration)->up();
        }

        @mkdir(sys_get_temp_dir().'/import-wizard-tests', recursive: true);
        File::cleanDirectory(sys_get_temp_dir().'/import-wizard-tests');
    }
}
