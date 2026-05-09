<?php

namespace Pterodactyl\Http\Controllers\Api\Public;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Http\Controllers\Controller;

class LicenseController extends Controller
{
    /**
     * GET /api/public/license/verify
     * Always returns valid — no license required.
     */
    public function verify(Request $request): JsonResponse
    {
        return new JsonResponse([
            'valid'   => true,
            'status'  => 'active',
            'message' => 'License valid',
        ]);
    }
}
