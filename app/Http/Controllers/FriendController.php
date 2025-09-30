<?php

namespace App\Http\Controllers;

use App\Models\Friend;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class FriendController extends Controller
{
    // Send request
    public function sendRequest(Request $request)
    {
        $request->validate([
            'friend_id' => 'required|exists:users,id',
        ]);

        $friend = Friend::create([
            'user_id' => auth()->id(),
            'friend_id' => $request->friend_id,
            'status' => 'pending',
        ]);

        return response()->json(['message' => 'Friend request sent', 'data' => $friend]);
    }

    // Accept request
    public function acceptRequest($id)
    {
        $request = Friend::where('id', $id)
            ->where('friend_id', auth()->id())
            ->where('status', 'pending')
            ->firstOrFail();

        $request->update(['status' => 'accepted']);

        return response()->json(['message' => 'Friend request accepted']);
    }

    // Reject request
    public function rejectRequest($id)
    {
        $request = Friend::where('id', $id)
            ->where('friend_id', auth()->id())
            ->where('status', 'pending')
            ->firstOrFail();

        $request->update(['status' => 'rejected']);

        return response()->json(['message' => 'Friend request rejected']);
    }

    // Get all friend requests (received)
    public function receivedRequests()
    {
        $requests = Friend::where('friend_id', auth()->id())
            ->where('status', 'pending')
            ->with(['user', 'user.profile'])
            ->get();

        return response()->json($requests);
    }

    // Get all sent requests
    public function sentRequests()
    {
        $requests = Friend::where('user_id', auth()->id())
            ->whereIn('status', ['accepted', 'rejected'])
            ->with(['friend.profile'])
            ->get();

        return response()->json($requests);
    }

    public function getFriends(Request $request)
    {
        $perPage = $request->input('per_page', 12);
        $page = $request->input('page', 1);

        /** @var \Illuminate\Pagination\LengthAwarePaginator $friends */
        $friends = Friend::where(function ($query) {
            $query->where('user_id', auth()->id())
                ->orWhere('friend_id', auth()->id());
        })
            ->where('status', 'accepted')
            ->with(['user.profile', 'friend.profile'])
            ->paginate($perPage, ['*'], 'page', $page);

        $transformed = $friends->getCollection()->map(function ($item) {
            $friend = $item->user_id === auth()->id() ? $item->friend : $item->user;

            return [
                'id' => $friend->id,
                'name' => $friend->name,
                'subtitle' => $friend->profile->headline ?? '',
                'profilePic' => $friend->profile->profile_photo ?? null,
                'coverImage' => $friend->profile->cover_photo ?? null
            ];
        });

        return response()->json([
            'data' => $transformed,
            'current_page' => $friends->currentPage(),
            'total' => $friends->total(),
            'per_page' => $friends->perPage(),
            'last_page' => $friends->lastPage(),
        ]);
    }

    public function getFriendsbyid(Request $request, $id)
    {
        $perPage = $request->input('per_page', 12);
        $page = $request->input('page', 1);

        /** @var \Illuminate\Pagination\LengthAwarePaginator $friends */
        if ((int)$id === (int)auth()->id()) {

            $friends = Friend::where(function ($query) {
                $query->where('user_id', auth()->id())
                    ->orWhere('friend_id', auth()->id());
            })
                ->where('status', 'accepted')
                ->with(['user.profile', 'friend.profile'])
                ->paginate($perPage, ['*'], 'page', $page);

            $transformed = $friends->getCollection()->map(function ($item) {
                $friend = $item->user_id === auth()->id() ? $item->friend : $item->user;

                return [
                    'id' => $friend->id,
                    // 'user_id' => $friend->user_id,
                    'name' => $friend->name,
                    'subtitle' => $friend->profile->headline ?? '',
                    'profilePic' => $friend->profile->profile_photo ?? null,
                    'coverImage' => $friend->profile->cover_photo ?? null
                ];
            });

        } else {
            $friends = Friend::where(function ($query) use ($id) {
                $query->where('user_id', $id)
                    ->orWhere('friend_id', $id);
            })
                ->where('status', 'accepted')
                ->with(['user.profile', 'friend.profile'])
                ->paginate($perPage, ['*'], 'page', $page);

            $transformed = $friends->getCollection()->map(function ($item) use ($id) {
                $friend = $item->user_id === $id ? $item->friend : $item->user;

                return [
                    'id' => $friend->id,
                    // 'user_id' => $friend->friend_id,
                    'name' => $friend->name,
                    'subtitle' => $friend->profile->headline ?? '',
                    'profilePic' => $friend->profile->profile_photo ?? null,
                    'coverImage' => $friend->profile->cover_photo ?? null
                ];
            });
        }
        return response()->json([
            'data' => $transformed,
            'current_page' => $friends->currentPage(),
            'total' => $friends->total(),
            'per_page' => $friends->perPage(),
            'last_page' => $friends->lastPage(),
        ]);

    }

    // public function getChats(Request $request)
    // {
    //     $perPage = $request->input('per_page', 12);
    //     $page = $request->input('page', 1);

    //     // Get all user IDs that the current user has messaged with
    //     $messagedUserIds = DB::table('messages')
    //         ->select('sender_id', 'receiver_id')
    //         ->where('sender_id', auth()->id())
    //         ->orWhere('receiver_id', auth()->id())
    //         ->groupBy('sender_id', 'receiver_id')
    //         ->pluck('sender_id', 'receiver_id')
    //         ->flatten()
    //         ->unique()
    //         ->filter(function ($userId) {
    //             return $userId != auth()->id();
    //         })
    //         ->values()
    //         ->toArray();

    //     // Get user details for all these users
    //     $users = User::whereIn('id', $messagedUserIds)
    //         ->with('profile')
    //         ->get()
    //         ->map(function ($user) {
    //             // Check if this user is a friend
    //             $isFriend = Friend::where(function ($query) use ($user) {
    //                 $query->where('user_id', auth()->id())
    //                     ->where('friend_id', $user->id)
    //                     ->where('status', 'accepted');
    //             })->orWhere(function ($query) use ($user) {
    //                 $query->where('user_id', $user->id)
    //                     ->where('friend_id', auth()->id())
    //                     ->where('status', 'accepted');
    //             })->exists();

    //             return [
    //                 'id' => $user->id,
    //                 'name' => $user->name,
    //                 'subtitle' => $user->profile->headline ?? '',
    //                 'profilePic' => $user->profile->profile_photo ?? null,
    //                 'coverImage' => $user->profile->cover_photo ?? null,
    //                 'is_friend' => $isFriend
    //             ];
    //         });

    //     // Paginate manually
    //     $paginated = new LengthAwarePaginator(
    //         $users->forPage($page, $perPage),
    //         $users->count(),
    //         $perPage,
    //         $page
    //     );

    //     return response()->json([
    //         'data' => $paginated->items(),
    //         'current_page' => $paginated->currentPage(),
    //         'total' => $paginated->total(),
    //         'per_page' => $paginated->perPage(),
    //         'last_page' => $paginated->lastPage(),
    //     ]);
    // }

    public function search(Request $request)
    {
        $request->validate([
            'q' => 'nullable|string|max:255',
            'limit' => 'nullable|integer|min:1|max:50',
        ]);

        $query = trim((string) $request->input('q', ''));
        $limit = (int) $request->input('limit', 10);
        $authId = auth()->id();

        // Get all accepted friendships of current user
        $friends = Friend::where('status', 'accepted')
            ->where(function ($q) use ($authId) {
                $q->where('user_id', $authId)
                    ->orWhere('friend_id', $authId);
            })
            ->with(['user.profile', 'friend.profile'])
            ->get()
            ->map(function ($f) use ($authId) {
                // Decide who is the "other" user in friendship
                return $f->user_id === $authId ? $f->friend : $f->user;
            });

        // Apply search on friends collection (by name or email)
        if ($query !== '') {
            $friends = $friends->filter(function ($u) use ($query) {
                return stripos($u->name, $query) !== false ||
                    stripos($u->email, $query) !== false;
            });
        }

        // Limit results
        $friends = $friends->take($limit)->values();

        $friendIds = $friends->pluck('id')->toArray();
        $friendIds[] = $authId;

        $users = User::whereNotIn('id', $friendIds)
            ->with('profile')
            ->when($query !== '', function ($q) use ($query) {
                $q->where(function ($sub) use ($query) {
                    $sub->where('name', 'like', "%$query%")
                        ->orWhere('email', 'like', "%$query%");
                });
            })
            ->limit($limit)
            ->get();

        // Transform output
        $friendsResult = $friends->map(function ($u) {
            return [
                'id' => $u->id,
                'name' => $u->name,
                'profile_photo' => optional($u->profile)->profile_photo,
                'type' => 'friend'
            ];
        });

        $usersResult = $users->map(function ($u) {
            return [
                'id' => $u->id,
                'name' => $u->name,
                'profile_photo' => optional($u->profile)->profile_photo,
                'type' => 'user'
            ];
        });

        $result = [
            'friends' => $friendsResult,
            'users' => $usersResult,
            "friendIds" => $friendIds
        ];

        return response()->json($result);
    }


}

