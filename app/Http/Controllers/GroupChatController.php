<?php

namespace App\Http\Controllers;

use App\Models\GroupChat;
use App\Services\FirebaseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class GroupChatController extends Controller
{
    protected $firebase;

    public function __construct(FirebaseService $firebase)
    {
        $this->firebase = $firebase;
    }

    public function index(Request $request)
    {
        try {
            $user = $request->user();

            // Get groups where the user is a member
            $groups = GroupChat::whereJsonContains('members', (string) $user->id)
                ->orWhere('created_by', $user->id)
                ->get()
                ->map(function ($group) {
                    return [
                        'id' => $group->id,
                        'name' => $group->name,
                        'photo' => $group->photo ? Storage::url($group->photo) : null,
                        'created_by' => $group->created_by,
                        'members' => $group->members,
                        'created_at' => $group->created_at,
                        'updated_at' => $group->updated_at,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $groups
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch groups',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'members' => 'required|array|min:2',
            'members.*' => 'integer|exists:users,id',
            'photo' => 'nullable|image|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = $request->user();
            $members = $request->input('members');

            // Add creator to members if not already included
            if (!in_array($user->id, $members)) {
                $members[] = $user->id;
            }

            $groupChat = new GroupChat();
            $groupChat->name = $request->input('name');
            $groupChat->created_by = $user->id;
            $groupChat->members = $members;

            if ($request->hasFile('photo')) {
                $path = $request->file('photo')->store('group-photos', 'public');
                $groupChat->photo = $path;
            }

            $groupChat->save();

            // Prepare data for Firebase
            $firebaseData = [
                'info' => [
                    'groupId' => (string) $groupChat->id,
                    'groupName' => $groupChat->name,
                    'createdBy' => $user->id,
                    'createdAt' => now()->timestamp,
                    'photo' => $groupChat->photo ? Storage::url($groupChat->photo) : null,
                ],
                'members' => array_fill_keys($members, true),
                'messages' => []
            ];

            // Save to Firebase
            $this->firebase->database
                ->getReference("groups/{$groupChat->id}")
                ->set($firebaseData);

            return response()->json([
                'success' => true,
                'group' => $groupChat,
                'firebase_data' => $firebaseData
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create group chat',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show(GroupChat $groupChat)
    {
        $groupChat->load('members');
        return response()->json([
            'success' => true,
            'group' => $groupChat
        ]);
    }

    public function updateDetails(Request $request, $groupId)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'photo' => 'nullable|image|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = $request->user();
            $group = GroupChat::findOrFail($groupId);

            // Check if user is the creator or admin
            if ($group->created_by != $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only group creator can update details'
                ], 403);
            }

            // Update name
            $group->name = $request->input('name');

            // Handle photo update
            if ($request->hasFile('photo')) {
                // Delete old photo if exists
                if ($group->photo) {
                    Storage::delete('public/' . $group->photo);
                }
                $path = $request->file('photo')->store('group-photos', 'public');
                $group->photo = $path;
            }

            $group->save();

            // Prepare Firebase update data
            $updateData = [
                'groupName' => $group->name,
                'photo' => $group->photo ? Storage::url($group->photo) : null,
            ];

            // Update Firebase
            $this->firebase->database
                ->getReference("groups/{$group->id}/info")
                ->update($updateData);

            return response()->json([
                'success' => true,
                'group' => $group,
                'message' => 'Group details updated successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update group details',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function addMembers(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'group_id' => 'required|integer|exists:group_chats,id',
            'members' => 'required|array|min:1',
            'members.*' => 'integer|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = $request->user();
            $groupId = $request->input('group_id');
            $newMemberIds = $request->input('members');

            // Get the group
            $group = GroupChat::findOrFail($groupId);

            // Check if user has permission to add members (creator or admin)
            if ($group->created_by != $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only group creator can add members'
                ], 403);
            }

            // Get current members
            $currentMembers = $group->members ?? [];

            // Filter out members that are already in the group
            $membersToAdd = array_diff($newMemberIds, $currentMembers);

            if (empty($membersToAdd)) {
                return response()->json([
                    'success' => false,
                    'message' => 'All selected members are already in the group'
                ], 400);
            }

            // Add new members to the group
            $updatedMembers = array_merge($currentMembers, $membersToAdd);
            $group->members = $updatedMembers;
            $group->save();

            // Prepare Firebase update data
            $firebaseUpdates = [];
            foreach ($membersToAdd as $memberId) {
                $firebaseUpdates["members/{$memberId}"] = true;
            }

            // Update Firebase
            $this->firebase->database
                ->getReference("groups/{$groupId}")
                ->update($firebaseUpdates);

            return response()->json([
                'success' => true,
                'message' => 'Members added successfully',
                'added_members' => $membersToAdd,
                'total_members' => count($updatedMembers)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to add members to group',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function removeMember(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'group_id' => 'required|integer|exists:group_chats,id',
            'member_id' => 'required|integer|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = $request->user();
            $groupId = $request->input('group_id');
            $memberId = $request->input('member_id');

            // Get the group
            $group = GroupChat::findOrFail($groupId);

            // Check if user has permission to remove members (creator or admin)
            if ($group->created_by != $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only group creator can remove members'
                ], 403);
            }

            // Check if trying to remove self
            if ($memberId == $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot remove yourself from group'
                ], 400);
            }

            // Check if trying to remove another admin (if you have admin roles)
            // This depends on your admin structure

            // Get current members
            $currentMembers = $group->members ?? [];

            // Check if member exists in group
            if (!in_array((string) $memberId, $currentMembers)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Member not found in group'
                ], 404);
            }

            // Remove member from the group
            $updatedMembers = array_filter($currentMembers, function ($id) use ($memberId) {
                return $id != (string) $memberId;
            });

            $group->members = array_values($updatedMembers); // Reindex array
            $group->save();

            // Update Firebase - ONLY remove from members list, keep messages
            $this->firebase->database
                ->getReference("groups/{$groupId}/members/{$memberId}")
                ->remove();

            // ✅ Messages preserve rahenge - Firebase se messages nahi delete honge

            return response()->json([
                'success' => true,
                'message' => 'Member removed successfully',
                'remaining_members' => count($updatedMembers)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to remove member from group',
                'error' => $e->getMessage()
            ], 500);
        }
    }

}