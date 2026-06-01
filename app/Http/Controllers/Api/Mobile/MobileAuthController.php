<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\Fortify;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Throwable;

class MobileAuthController extends Controller
{
    private static function otpCacheKey(string $identifier): string
    {
        return 'mobile_otp:'.strtolower(trim($identifier));
    }

    /**
     * Connexion mobile : jeton Sanctum (Bearer) ou étape OTP si 2FA activée.
     *
     * @throws ValidationException
     */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:128'],
        ]);

        /** @var User|null $user */
        $user = User::query()->where('email', $validated['email'])->first();

        if ($user === null || ! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Identifiants incorrects.'],
            ]);
        }

        if (! $user->is_active) {
            return response()->json([
                'message' => 'Votre compte a été désactivé.',
            ], 403);
        }

        if ($user->must_change_password) {
            return response()->json([
                'message' => 'Vous devez d’abord changer votre mot de passe depuis l’application web.',
                'code' => 'must_change_password',
            ], 403);
        }

        if ($user->two_factor_secret !== null && $user->two_factor_confirmed_at !== null) {
            Cache::put(self::otpCacheKey($validated['email']), $user->id, now()->addMinutes(10));

            return response()->json([
                'requires_otp' => true,
                'requiresOtp' => true,
                'identifier' => $user->email,
                'message' => 'Code d’authentification à deux facteurs requis.',
                'user' => MobileUserPresenter::summary($user),
            ]);
        }

        $device = $validated['device_name'] ?? 'mobile';

        return $this->issueTokenResponse($user, $device);
    }

    public function verifyOtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'identifier' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:128'],
            'device_name' => ['nullable', 'string', 'max:128'],
        ]);

        $identifier = $validated['identifier'];
        $emailNorm = strtolower(trim($identifier));
        $user = User::query()
            ->whereRaw('LOWER(TRIM(email)) = ?', [$emailNorm])
            ->first();
        if ($user === null) {
            return response()->json([
                'message' => 'Session de vérification invalide ou expirée.',
            ], 422);
        }

        $cachedId = Cache::get(self::otpCacheKey($identifier));
        if ($cachedId === null || (int) $cachedId !== $user->id) {
            return response()->json([
                'message' => 'Session de vérification invalide ou expirée.',
            ], 422);
        }

        if (! $user->is_active) {
            return response()->json([
                'message' => 'Votre compte a été désactivé.',
            ], 403);
        }

        $code = trim($validated['code']);
        if (! $this->twoFactorOrRecoveryValid($user, $code)) {
            return response()->json([
                'message' => 'Code incorrect.',
            ], 422);
        }

        Cache::forget(self::otpCacheKey($identifier));

        $device = $validated['device_name'] ?? 'mobile';

        return $this->issueTokenResponse($user, $device);
    }

    /**
     * Enregistrement d’appareil (no-op côté serveur pour l’instant ; 2xx attendu par le client).
     */
    public function registerDevice(Request $request): SymfonyResponse
    {
        $request->validate([
            'device_id' => ['required', 'string', 'max:128'],
            'model' => ['required', 'string', 'max:128'],
            'os_version' => ['required', 'string', 'max:64'],
            'app_version' => ['required', 'string', 'max:32'],
        ]);

        return response()->noContent();
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json(['message' => 'Déconnecté.']);
    }

    private function issueTokenResponse(User $user, string $deviceName): JsonResponse
    {
        $device = mb_substr($deviceName !== '' ? $deviceName : 'mobile', 0, 128);
        $token = $user->createToken($device)->plainTextToken;

        return response()->json([
            'token' => $token,
            'access_token' => $token,
            'accessToken' => $token,
            'token_type' => 'Bearer',
            'requires_otp' => false,
            'requiresOtp' => false,
            'requires_device_registration' => false,
            'requiresDeviceRegistration' => false,
            'user' => MobileUserPresenter::profile($user),
        ]);
    }

    private function twoFactorOrRecoveryValid(User $user, string $code): bool
    {
        if ($user->two_factor_secret === null) {
            return false;
        }

        try {
            $secret = Fortify::currentEncrypter()->decrypt($user->two_factor_secret);
        } catch (Throwable) {
            return false;
        }

        /** @var TwoFactorAuthenticationProvider $provider */
        $provider = app(TwoFactorAuthenticationProvider::class);
        if ($provider->verify($secret, $code)) {
            return true;
        }

        try {
            $recoveryCodes = $user->recoveryCodes();
        } catch (Throwable) {
            return false;
        }

        foreach ($recoveryCodes as $recovery) {
            if (hash_equals((string) $recovery, $code)) {
                $user->replaceRecoveryCode($recovery);

                return true;
            }
        }

        return false;
    }
}
