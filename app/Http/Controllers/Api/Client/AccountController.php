<?php
namespace Pterodactyl\Http\Controllers\Api\Client;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Auth\AuthManager;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Facades\Activity;
use Pterodactyl\Services\Users\UserUpdateService;
use Pterodactyl\Transformers\Api\Client\AccountTransformer;
use Pterodactyl\Http\Requests\Api\Client\Account\UpdateEmailRequest;
use Pterodactyl\Http\Requests\Api\Client\Account\UpdatePasswordRequest;
use Pterodactyl\Http\Requests\Api\Client\Account\UpdateAccountInfoRequest;
class AccountController extends ClientApiController
{
    /**
     * AccountController constructor.
     */
    public function __construct(private AuthManager $manager, private UserUpdateService $updateService)
    {
        parent::__construct();
    }

    /**
     * Return the authenticated user information.
     */
    public function index(Request $request): array
    {
        return $this->fractal->item($request->user())
            ->transformWith($this->getTransformer(AccountTransformer::class))
            ->toArray();
    }

    /**
     * Update the authenticated user's account information. (Custom Hyper)
     */
    public function updateAccountInfo(UpdateAccountInfoRequest $request): JsonResponse
    {
        if (strtolower($request->user()->username) === 'demo') {
            return new JsonResponse(['error' => 'Demo user cannot modify account information.'], Response::HTTP_FORBIDDEN);
        }
        $original = $request->user()->only(['email', 'username', 'name_first', 'name_last', 'country', 'address', 'zip_code']);
        $data = [
            'email' => $request->input('email'),
            'username' => $request->input('username'),
            'name_first' => $request->input('first_name'),
            'name_last' => $request->input('last_name'),
            'country' => $request->input('country'),
            'address' => $request->input('address'),
            'zip_code' => $request->input('zip_code'),
        ];
        $this->updateService->handle($request->user(), $data);
        foreach ($data as $key => $value) {
            if ($original[$key] !== $value) {
                Activity::event('user:account.' . $key . '-changed')
                    ->property(['old' => $original[$key], 'new' => $value])
                    ->log();
            }
        }
        return new JsonResponse([], Response::HTTP_NO_CONTENT);
    }

    /**
     * Update the authenticated user's email address.
     */
    public function updateEmail(UpdateEmailRequest $request): JsonResponse
    {
        if (strtolower($request->user()->username) === 'demo') {
            return new JsonResponse(['error' => 'Demo user cannot modify account information.'], Response::HTTP_FORBIDDEN);
        }
        $original = $request->user()->email;
        $this->updateService->handle($request->user(), $request->validated());
        if ($original !== $request->input('email')) {
            Activity::event('user:account.email-changed')
                ->property(['old' => $original, 'new' => $request->input('email')])
                ->log();
        }
        return new JsonResponse([], Response::HTTP_NO_CONTENT);
    }

    /**
     * Update the authenticated user's password. All existing sessions will be logged
     * out immediately.
     *
     * @throws \Throwable
     */
    public function updatePassword(UpdatePasswordRequest $request): JsonResponse
    {
        if (strtolower($request->user()->username) === 'demo') {
            return new JsonResponse(['error' => 'Demo user cannot modify account information.'], Response::HTTP_FORBIDDEN);
        }
        $user = $this->updateService->handle($request->user(), $request->validated());
        $guard = $this->manager->guard();
        // If you do not update the user in the session you'll end up working with a
        // cached copy of the user that does not include the updated password. Do this
        // to correctly store the new user details in the guard and allow the logout
        // other devices functionality to work.
        $guard->setUser($user);

        // This method doesn't exist in the stateless Sanctum world.
        if (method_exists($guard, 'logoutOtherDevices')) {
            $guard->logoutOtherDevices($request->input('password'));
        }
        Activity::event('user:account.password-changed')->log();
        return new JsonResponse([], Response::HTTP_NO_CONTENT);
    }
}
