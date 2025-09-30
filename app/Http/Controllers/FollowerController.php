<?php

namespace App\Http\Controllers;

use App\Models\Follower;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
class FollowerController extends Controller
{
    // Follow user
    public function follow(Request $request, $userId)
    {
        $authUser = Auth::user();

        if ($authUser->id == $userId) {
            return response()->json(['message' => 'You cannot follow yourself'], 400);
        }

        // Check if already following   
        $exists = Follower::where('follower_id', $authUser->id)
            ->where('following_id', $userId)
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'Already following'], 400);
        }

        Follower::create([
            'follower_id' => $authUser->id,
            'following_id' => $userId,
        ]);

        return response()->json(['message' => 'Followed successfully']);
    }

    public function isFollowing($userId)
    {
        $authUserId = Auth::id();

        $isFollowing = Follower::where('follower_id', $authUserId)
            ->where('following_id', $userId)
            ->exists();

        return response()->json([
            'is_following' => $isFollowing
        ]);
    }


    // Unfollow user
    public function unfollow(Request $request, $userId)
    {
        $authUser = Auth::user();

        $deleted = Follower::where('follower_id', $authUser->id)
            ->where('following_id', $userId)
            ->delete();

        if (!$deleted) {
            return response()->json(['message' => 'Not following this user'], 400);
        }

        return response()->json(['message' => 'Unfollowed successfully']);
    }

    // Get followers of a user
    public function getFollowers($userId)
    {
        $user = User::findOrFail($userId);
        return response()->json($user->followers);
    }

    // Get followings of a user
    public function getFollowings($userId)
    {
        $user = User::findOrFail($userId);
        return response()->json($user->followings);
    }
}
