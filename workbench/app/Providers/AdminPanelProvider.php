<?php

namespace Workbench\App\Providers;

use Filament\Http\Middleware\Authenticate;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Contracts\Auth\Authenticatable;
use SolutionForest\FilamentImportWizard\ImportWizardPlugin;
use SolutionForest\FilamentImportWizard\Models\ImportAudit;
use Workbench\App\Filament\Pages\ImportContacts;
use Workbench\App\Http\DemoLogin;
use Workbench\App\Models\User;

class AdminPanelProvider extends PanelProvider
{
    public function register(): void
    {
        parent::register();

        config(['session.driver' => 'file', 'queue.default' => 'sync', 'queue.batching.database' => 'sqlite']);
        config(['auth.providers.users.model' => User::class]);
        $this->app->bind(Authenticatable::class, User::class);
    }

    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->homeUrl('/admin/import-contacts')
            ->colors(['primary' => Color::Indigo])
            ->plugin(
                ImportWizardPlugin::make()
                    ->pages([ImportContacts::class])
                    ->chunkSize(500)
                    ->chunkedUploads()
                    // Demo: imports of more than 20 rows need a second pair of eyes. The demo has a
                    // single user, so self-approval is allowed here — never do that in production.
                    ->requireApproval(fn (ImportAudit $audit): bool => $audit->total_rows > 20)
                    ->allowSelfApproval(),
            )
            ->authMiddleware([DemoLogin::class, Authenticate::class], isPersistent: true);
    }
}
