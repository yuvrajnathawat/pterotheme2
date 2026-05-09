<?php

namespace Pterodactyl\Notifications;

use Illuminate\Bus\Queueable;

use Throwable;

use Pterodactyl\Models\User;
use Pterodactyl\Events\Event;
use Pterodactyl\Models\Server;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\Log;
use Pterodactyl\Events\Server\Installed;
use Illuminate\Notifications\Notification;
use Pterodactyl\Contracts\Core\ReceivesEvents;
use Illuminate\Contracts\Notifications\Dispatcher;
use Illuminate\Notifications\Messages\MailMessage;

class ServerInstalled extends Notification implements ReceivesEvents
{
    use Queueable;

    public Server $server;

    public User $user;

    
    public function handle(Event|Installed $event): void
    {
        $event->server->loadMissing('user');

        $this->server = $event->server;
        $this->user = $event->server->user;

        try {
            Container::getInstance()->make(Dispatcher::class)->sendNow($this->user, $this);
        } catch (Throwable $e) {
            Log::warning('[ServerInstalled] Failed to send install notification email: ' . $e->getMessage(), [
                'server_id' => $this->server->id,
                'user_id'   => $this->user->id,
            ]);
        }
    }

    
    public function via(): array
    {
        return ['mail'];
    }

    
    public function toMail(): MailMessage
    {
        return (new MailMessage())
            ->greeting('Hello ' . $this->user->username . '.')
            ->line('Your server has finished installing and is now ready for you to use.')
            ->line('Server Name: ' . $this->server->name)
            ->line('Server UUID: ' . $this->server->uuid)
            ->action('Login and Begin Using', route('index'));
    }
}
