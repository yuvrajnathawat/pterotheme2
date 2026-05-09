<?php
namespace Pterodactyl\Http\Requests\Admin;
use Pterodactyl\Models\User;
use Illuminate\Support\Collection;
class UserFormRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return Collection::make(
            User::getRulesForUpdate($this->route()->parameter('user'))
        )->only([
            'email',
            'username',
            'name_first',
            'name_last',
            'password',
            'language',
            'root_admin',
            'is_banned',
            'ban_reason',
            'suspended_until',
            'suspension_reason',
            'country',
            'address',
            'zip_code',
            'credit',
        ])->toArray();
    }
}
