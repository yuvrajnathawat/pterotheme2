<?php

namespace Pterodactyl\Http\Middleware\Api\Daemon;

use Closure;

use Illuminate\Http\Request;
use Illuminate\Contracts\Encryption\Encrypter;
use Pterodactyl\Repositories\Eloquent\NodeRepository;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Pterodactyl\Exceptions\Repository\RecordNotFoundException;

class DaemonAuthenticate
{
    
    protected array $except = [
        'daemon.configuration',
    ];

    
    public function __construct(private Encrypter $encrypter, private NodeRepository $repository)
    {
    }

    
    public function handle(Request $request, Closure $next): mixed
    {
        if (in_array($request->route()->getName(), $this->except)) {
            return $next($request);
        }

        if (is_null($bearer = $request->bearerToken())) {
            throw new HttpException(401, 'Unauthorized.', null, ['WWW-Authenticate' => 'Bearer']);
        }

        $parts = explode('.', $bearer);

        if (count($parts) !== 2 || empty($parts[0]) || empty($parts[1])) {
            throw new HttpException(401, 'Unauthorized.', null, ['WWW-Authenticate' => 'Bearer']);
        }

        try {
            $node = $this->repository->findFirstWhere([
                'daemon_token_id' => $parts[0],
            ]);

            if (hash_equals((string) $this->encrypter->decrypt($node->daemon_token), $parts[1])) {
                $request->attributes->set('node', $node);

                return $next($request);
            }
        } catch (RecordNotFoundException $exception) {
            // Intentionally swallowed — return generic 401 below.
        }

        throw new HttpException(401, 'Unauthorized.', null, ['WWW-Authenticate' => 'Bearer']);
    }
}
