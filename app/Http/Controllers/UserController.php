<?php


namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Profile;
use App\Models\UserSkill;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\UserInfo;
use Illuminate\Support\Facades\Auth;
use App\Models\Friend;

class UserController extends Controller
{
    public function signup(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required_without:phone|nullable|email|max:255',
            'phone' => 'required_without:email|nullable|string|max:20',
            'password' => 'required|string|min:6',
            'skills' => 'nullable|array',
            'skills.*' => 'string',
            'dob' => 'required|date',
            'location' => 'required|string|max:255',
        ]);

        if (empty($validated['email']) && empty($validated['phone'])) {
            return response()->json(['error' => 'Either email or phone is required.'], 422);
        }

        // Check if email already exists
        if (!empty($validated['email'])) {
            $emailExists = User::where('email', $validated['email'])->exists();
            if ($emailExists) {
                return response()->json(['error' => 'This email is already registered.'], 422);
            }
        }

        $userData = [
            'name' => $validated['name'],
            'password' => Hash::make($validated['password']),
        ];
        if (!empty($validated['email'])) {
            $userData['email'] = $validated['email'];
        }
        if (!empty($validated['phone'])) {
            $userData['phone'] = $validated['phone'];
        }
        $user = User::create($userData);

        // Create user_info
        $userInfo = new UserInfo();
        $userInfo->user_id = $user->id;
        $userInfo->email = $user->email ?? null;
        $userInfo->contact = $user->phone ?? null;
        $userInfo->date_of_birth = $validated['dob'];
        $userInfo->save();

        // Create profile
        $profile = new Profile();
        $profile->user_id = $user->id;
        $profile->dob = $validated['dob'];
        $profile->location = $validated['location'];
        $profile->save();

        // Create skills if provided
        if (!empty($validated['skills'])) {
            foreach ($validated['skills'] as $skill) {
                UserSkill::create([
                    'user_id' => $user->id,
                    'skill' => $skill,
                    'proficiency' => 'none',
                ]);
            }
        }

        return response()->json(['message' => 'User registered successfully.'], 201);
    }

    public function login(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required_without:phone|nullable|email',
            'phone' => 'required_without:email|nullable|string',
            'password' => 'required|string',
        ]);

        if (empty($validated['email']) && empty($validated['phone'])) {
            return response()->json(['error' => 'Either email or phone is required.'], 422);
        }

        $userQuery = User::query();
        if (!empty($validated['email'])) {
            $userQuery->where('email', $validated['email']);
        } else {
            $userQuery->where('phone', $validated['phone']);
        }
        $user = $userQuery->first();

        if (!$user || !\Illuminate\Support\Facades\Hash::check($validated['password'], $user->password)) {
            return response()->json(['error' => 'Invalid credentials.'], 401);
        }

        // Generate Sanctum token
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful',
            'token' => $token,
            'user' => $user,
        ]);
    }

    public function checkAuth(Request $request)
    {
        return response()->json([
            'authenticated' => true,
            'user' => $request->user(),
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully']);
    }

    public function getrandomusers(Request $request)
    {
        $userId = Auth::id();
        $limit = $request->input('limit', 10);

        $friendIds = Friend::where(function ($query) use ($userId) {
            $query->where('user_id', $userId)
                ->orWhere('friend_id', $userId);
        })
            ->get()
            ->flatMap(function ($friend) use ($userId) {
                return [$friend->user_id, $friend->friend_id];
            })
            ->unique()
            ->filter(fn($id) => $id != $userId)
            ->values();

        $fofIds = Friend::where(function ($query) use ($friendIds) {
            $query->whereIn('user_id', $friendIds)
                ->orWhereIn('friend_id', $friendIds);
        })
            ->get()
            ->flatMap(function ($friend) {
                return [$friend->user_id, $friend->friend_id];
            })
            ->unique()
            ->filter(fn($id) => $id != Auth::id() && !$friendIds->contains($id))
            ->values();

        $usersFromFof = collect();
        if ($fofIds->isNotEmpty()) {
            $usersFromFof = User::with('profile') // Include profile relationship
                ->whereIn('id', $fofIds)
                ->inRandomOrder()
                ->take($limit)
                ->get();
        }

        $remaining = $limit - $usersFromFof->count();
        $usersFromRandom = collect();

        if ($remaining > 0) {
            $excludedIds = $usersFromFof->pluck('id')
                ->merge($friendIds)
                ->push($userId);

            $usersFromRandom = User::with('profile') // Include profile relationship
                ->whereNotIn('id', $excludedIds)
                ->inRandomOrder()
                ->take($remaining)
                ->get();
        }

        $finalUsers = $usersFromFof->merge($usersFromRandom);

        return response()->json([
            'status' => true,
            'users' => $finalUsers
        ]);
    }


}
