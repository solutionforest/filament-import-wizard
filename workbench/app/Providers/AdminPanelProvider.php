<?php

namespace Workbench\App\Providers;

use Filament\Http\Middleware\Authenticate;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Contracts\Auth\Authenticatable;
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
            ->pages([ImportContacts::class])
            ->authMiddleware([DemoLogin::class, Authenticate::class], isPersistent: true);
    }
}
