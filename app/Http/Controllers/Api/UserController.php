<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Resources\UserCollection;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    /**
     * Display a listing of users.
     */
    public function index(Request $request): UserCollection
    {
        $users = User::with('profile')
            ->when($request->search, function ($query, $search) {
                $query->where('username', 'like', "%{$search}%")
                    ->orWhereHas('profile', function ($q) use ($search) {
                        $q->where('username', 'like', "%{$search}%");
                    });
            })
            ->when($request->featured, function ($query) {
                $query->whereHas('profile', function ($q) {
                    $q->where('is_featured_curator', true);
                });
            })
            ->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 15);

        return new UserCollection($users);
    }

    /**
     * Display the specified user.
     */
    public function show(User $user): UserResource
    {
        $user->load('profile');
        return new UserResource($user);
    }

    /**
     * Display the current user's profile.
     */
    public function profile(Request $request): UserResource
    {
        $user = $request->user()->load('profile');
        return new UserResource($user);
    }

    /**
     * Update the current user's profile.
     */
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();

        // Handle email update separately
        if (isset($data['email'])) {
            $user->update(['email' => $data['email']]);
            unset($data['email']);
        }

        // Update or create user profile
        $profile = $user->profile;
        if (!$profile) {
            $profile = $user->profile()->create([
                'user_id' => $user->id,
                'username' => $user->username,
            ]);
        }

        $profile->update($data);

        return response()->json([
            'message' => 'Profile updated successfully',
            'user' => new UserResource($user->load('profile')),
        ]);
    }

    /**
     * Display a public user profile.
     */
    public function showPublic(User $user): UserResource
    {
        $user->load('profile');
        return new UserResource($user);
    }
}
