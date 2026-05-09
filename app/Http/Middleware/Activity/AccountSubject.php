<?php

namespace Pterodactyl\Http\Middleware\Activity;

use Closure;

use Illuminate\Http\Request;
use Pterodactyl\Facades\LogTarget;
use Pterodactyl\Models\Server;

class AccountSubject
{
    /**
     * Attempts to automatically scope all of the activity log events registered
     * within the request instance to the given user and server. This only sets
     * the actor and subject if there is a server present on the request.
     *
     * If no server is found this is a no-op as the activity log service can always
     * set the user based on the authmanager response.
     */
    /**
     * Sets the actor and default subject for all requests passing through this
     * middleware to be the currently logged in user.
     */
    public function handle(Request $request, Closure $next)
    {
        LogTarget::setActor($request->user());
        LogTarget::setSubject($request->user());

        return $next($request);
    }
}
