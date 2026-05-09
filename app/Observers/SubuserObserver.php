<?php

namespace Pterodactyl\Observers;

use Exception;
use Pterodactyl\Events;

use Pterodactyl\Models\Subuser;
use Pterodactyl\Notifications\AddedToServer;
use Pterodactyl\Notifications\RemovedFromServer;

class SubuserObserver
{
    
    public function creating(Subuser $subuser): void
    {
        event(new Events\Subuser\Creating($subuser));
    }

    
    public function created(Subuser $subuser): void
    {
        event(new Events\Subuser\Created($subuser));

        try {
            $subuser->user->notify(new AddedToServer([
                'user' => $subuser->user->name_first,
                'name' => $subuser->server->name,
                'uuidShort' => $subuser->server->uuidShort,
            ]));
        } catch (Exception $e) {
            // Do not fail subuser creation if mail delivery fails.
        }
    }

    
    public function deleting(Subuser $subuser): void
    {
        event(new Events\Subuser\Deleting($subuser));
    }

    
    public function deleted(Subuser $subuser): void
    {
        event(new Events\Subuser\Deleted($subuser));

        try {
            $subuser->user->notify(new RemovedFromServer([
                'user' => $subuser->user->name_first,
                'name' => $subuser->server->name,
            ]));
        } catch (Exception $e) {
            // Do not fail subuser removal if mail delivery fails.
        }
    }
}
