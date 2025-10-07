<?php

namespace App\Http\Controllers;

use App\Models\GroupMember;
use Illuminate\Http\Request;

class GroupMemberController extends Controller
{
    // Display the list of groups the current authenticated user has joined
    public function FetchJoinedGroups()
    {
        $user_id = auth()->id(); // Get the current authenticated user's ID
        $groupMembers = GroupMember::with('group') // Load the related group data
            ->where('user_id', $user_id)
            ->get();

        return response()->json($groupMembers); // Return the list of groups
    }

    // Add a user to a group
    public function store(Request $request)
    {
        $validated = $request->validate([
            'group_id' => 'required|exists:groups,id', // Ensure the group exists
            'role' => 'required|string|max:255',
            'status' => 'required|string|max:255',
        ]);

        $groupMember = new GroupMember();
        $groupMember->user_id = auth()->id(); // Assign the current authenticated user
        $groupMember->group_id = $validated['group_id'];
        $groupMember->role = $validated['role'];
        $groupMember->status = $validated['status'];
        $groupMember->joined_at = now();
        $groupMember->save();

        return response()->json(['message' => 'User added to group successfully'], 201);
    }

    // Remove a user from a group
    public function destroy($id)
    {
        $groupMember = GroupMember::find($id);

        if (!$groupMember || $groupMember->user_id !== auth()->id()) {
            return response()->json(['message' => 'Unauthorized or group member not found'], 404);
        }

        $groupMember->delete();
        return response()->json(['message' => 'User removed from group successfully'], 200);
    }
}
