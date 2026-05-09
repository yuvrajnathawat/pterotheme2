<?php

namespace Pterodactyl\Http\Middleware\Api\Client;

use Closure;

use Pterodactyl\Models\Server;
use Illuminate\Routing\Middleware\SubstituteBindings;

class SubstituteClientBindings extends SubstituteBindings
{
    /**
     * @param \Illuminate\Http\Request $request
     */
    public function handle($request, Closure $next): mixed
    {
        $route = $request->route();

        if ($route && $route->hasParameter('server')) {
            $value = $route->parameter('server');
            if (is_string($value)) {
                if (str_starts_with($value, 'serv_')) {
                    $server = Server::query()->where('uuid', $value)->firstOrFail();
                } else {
                    $server = Server::query()->where(strlen($value) === 8 ? 'uuidShort' : 'uuid', $value)->firstOrFail();
                }
                $route->setParameter('server', $server);
            }
        }

        if ($route && $route->hasParameter('user')) {
            $value = $route->parameter('user');
            if (is_string($value)) {
                $server = $route->parameter('server');
                if ($server instanceof Server) {
                    $match = $server->subusers()
                        ->whereRelation('user', 'uuid', '=', $value)
                        ->firstOrFail();
                    $route->setParameter('user', $match->user);
                }
            }
        }

        return parent::handle($request, $next);
    }
}
