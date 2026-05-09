<?php

namespace Pterodactyl\Services\Users;

use Pterodactyl\Traits\Services\HasUserLevels;

use Pterodactyl\Models\User;
use Illuminate\Contracts\Hashing\Hasher;
use Pterodactyl\Events\User\PasswordChanged;

class UserUpdateService
{
    use HasUserLevels;

    /**
     * UserUpdateService constructor.
     */
    public function __construct(private Hasher $hasher)
    {
    }

    /**
     * Update the user model instance and return the updated model.
     *
     * @throws \Throwable
     */
    public function handle(User $user, array $data): User
    {
        $hasPassword = !empty(array_get($data, 'password'));
        if ($hasPassword) {
            $data['password'] = $this->hasher->make($data['password']);
        } else {
            unset($data['password']);
        }

        $user->forceFill($data)->saveOrFail();

        if ($hasPassword) {
            event(new PasswordChanged($user));
        }

        return $user->refresh();
    }
}
