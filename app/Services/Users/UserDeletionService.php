<?php

namespace Pterodactyl\Services\Users;

use Pterodactyl\Models\User;
use Pterodactyl\Models\Server;
use Pterodactyl\Exceptions\DisplayException;

class UserDeletionService
{
    /**
     * Delete a user from the panel only if they have no servers attached to their account.
     *
     * @throws \Pterodactyl\Exceptions\DisplayException
     */
    public function handle(User $user): void
    {
        if (Server::query()->where('owner_id', $user->id)->count() > 0) {
            throw new DisplayException(trans('admin/user.exceptions.user_has_servers'));
        }

        $user->delete();
    }
}
