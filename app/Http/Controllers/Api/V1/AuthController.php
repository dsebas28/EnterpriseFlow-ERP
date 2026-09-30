<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\User;
use App\Support\Api\ApiResponse;
use App\Support\Logging\SecurityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Token authentication for API clients (Sanctum personal access tokens).
 */
class AuthController extends Controller
{
    public function __construct(private readonly SecurityLogger $log) {}

    /**
     * Log in and receive a bearer token.
     *
     * The plain token is returned only once; the database stores its hash.
     * Invalid credentials and inactive accounts get the same error, so the
     * response never reveals which accounts exist.
     *
     * @unauthenticated
     */
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:100'],
        ]);

        $user = User::query()->whereRaw('lower(email) = ?', [strtolower($credentials['email'])])->first();

        if ($user === null || ! $user->isActive() || ! Hash::check($credentials['password'], $user->password)) {
            $this->log->warning('api.login_failed', ['email' => $credentials['email']]);

            throw ValidationException::withMessages(['email' => [trans('auth.failed')]]);
        }

        $token = $user->createToken($credentials['device_name'], ['*'], now()->addMinutes((int) config('sanctum.expiration')));
        $user->forceFill(['last_login_at' => now()])->saveQuietly();
        $this->log->info('api.login', ['user_id' => $user->id, 'device' => $credentials['device_name']]);

        return ApiResponse::success([
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => $token->accessToken->expires_at?->toIso8601String(),
            'user' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email],
            'companies' => $user->activeCompanies()->get()->map(fn (Company $company) => [
                'id' => $company->id,
                'name' => $company->name,
                'currency' => $company->currency,
            ]),
        ], 'Logged in.');
    }

    /**
     * Revoke the token used for this request.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return ApiResponse::success(null, 'Logged out.');
    }

    /**
     * The authenticated user and the companies they can operate in.
     */
    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return ApiResponse::success([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'companies' => $user->activeCompanies()->get()->map(fn (Company $company) => [
                'id' => $company->id,
                'name' => $company->name,
                'currency' => $company->currency,
            ]),
        ]);
    }
}
