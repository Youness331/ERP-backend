<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\UpdateProfileRequest;
use App\Http\Resources\ProfileResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        /** @var User|null $user */
        $user = User::query()->where('email', $request->validated('email'))->first();

        if ($user === null || ! Hash::check($request->validated('password'), $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Ces identifiants ne correspondent à aucun compte.'],
            ]);
        }

        if (! $user->is_active) {
            return response()->json(['message' => 'Ce compte est désactivé. Contactez un administrateur.'], 403);
        }

        $token = $user->createToken($request->validated('device_name') ?? 'web')->plainTextToken;

        $user->forceFill(['last_login_at' => now()])->save();

        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => new ProfileResource($user->load('role.permissions')),
        ]);
    }

    public function me(Request $request): ProfileResource
    {
        return new ProfileResource($request->user()->load('role.permissions'));
    }

    public function updateProfile(UpdateProfileRequest $request): ProfileResource
    {
        $user = $request->user();
        $user->fill($request->validated())->save();

        return new ProfileResource($user->load('role.permissions'));
    }

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->update(['password' => $request->validated('password')]);

        // Revoke every other device; keep the token used for this request.
        $currentTokenId = $user->currentAccessToken()?->getKey();
        $user->tokens()->when($currentTokenId, fn ($query) => $query->whereKeyNot($currentTokenId))->delete();

        return response()->json(['message' => 'Mot de passe mis à jour.']);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => 'Déconnexion effectuée.']);
    }
}
