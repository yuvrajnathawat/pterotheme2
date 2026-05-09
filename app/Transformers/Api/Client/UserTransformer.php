<?php

namespace Pterodactyl\Transformers\Api\Client;

use Illuminate\Support\Str;
use Pterodactyl\Models\User;

class UserTransformer extends BaseClientTransformer
{
    
    public function getResourceName(): string
    {
        return User::RESOURCE_NAME;
    }

    
    public function transform(User $model): array
    {
        return [
            'uuid' => $model->uuid,
            'identifier' => $model->identifier,
            'username' => $model->username,
            'email' => $model->email,
            'first_name' => $model->name_first,
            'last_name' => $model->name_last,
            'image' => 'https://gravatar.com/avatar/' . md5(Str::lower($model->email)),
            '2fa_enabled' => $model->use_totp,
            'created_at' => $model->created_at->toAtomString(),
        ];
    }
}
