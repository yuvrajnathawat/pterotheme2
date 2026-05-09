<?php
namespace Pterodactyl\Providers;
use Pterodactyl\Models\User;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\Subuser;
use Pterodactyl\Models\EggVariable;
use Pterodactyl\Observers\UserObserver;
use Pterodactyl\Observers\ServerObserver;
use Pterodactyl\Observers\SubuserObserver;
use Pterodactyl\Observers\EggVariableObserver;
use Pterodactyl\Observers\Rolexdev\ServerSplitterObserver;
use Pterodactyl\Observers\Rolexdev\ServerSplitterLimitObserver;
use Pterodactyl\Observers\Rolexdev\ReverseProxyLimitObserver;
use Pterodactyl\Models\UserIntegration;
use Pterodactyl\Observers\Rolexdev\StockObserver;
use Pterodactyl\Observers\Rolexdev\UserIntegrationObserver;
use Pterodactyl\Listeners\AuthenticationListener;
use Pterodactyl\Listeners\RevocationListener;
use Pterodactyl\Listeners\TwoFactorListener;
use Pterodactyl\Events\ActivityLogged;
use Pterodactyl\Listeners\ArmaReforgerWebhookListener;
use Illuminate\Auth\Events\Logout;
use Pterodactyl\Listeners\Auth\SessionCleanupListener;
use Pterodactyl\Events\Server\Installed as ServerInstalledEvent;
use Pterodactyl\Notifications\ServerInstalled as ServerInstalledNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        ActivityLogged::class => [
            ArmaReforgerWebhookListener::class,
        ],
        Logout::class => [
            SessionCleanupListener::class,
        ],
        ServerInstalledEvent::class => [
            ServerInstalledNotification::class,
        ],
    ];
    protected $subscribe = [
        AuthenticationListener::class,
        RevocationListener::class,
        TwoFactorListener::class,
    ];
    public function boot(): void
    {
        parent::boot();
        User::observe(UserObserver::class);
        Server::observe(ServerObserver::class);
        Server::observe(ServerSplitterObserver::class);
        Server::observe(ServerSplitterLimitObserver::class);
        Server::observe(ReverseProxyLimitObserver::class);
        Server::observe(StockObserver::class);
        Subuser::observe(SubuserObserver::class);
        EggVariable::observe(EggVariableObserver::class);
        UserIntegration::observe(UserIntegrationObserver::class);
    }
}
