<?php

namespace App\Http\Controllers;

use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use App\Models\Group;
use Illuminate\Support\Facades\Validator;

class GroupController extends Controller
{
    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'group_name' => 'required|string|max:255|unique:groups,group_name',
            'group_description' => 'nullable|string',
            'group_type' => 'required|in:public,private,secret',
            'group_industry' => 'nullable|string|max:255',
            'location' => 'nullable|string|max:255',
            'group_history' => 'nullable|string',
            'group_profile_photo' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'group_banner_image' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ]);


        $validated['group_created_by'] = Auth::id();

        // Handle profile photo upload
        if ($request->hasFile('group_profile_photo')) {
            $photoPath = $request->file('group_profile_photo')->store('groups/profile_photos', 'public');
            $validated['group_profile_photo'] = $photoPath;
        }

        // Handle banner image upload
        if ($request->hasFile('group_banner_image')) {
            $bannerPath = $request->file('group_banner_image')->store('groups/banner_images', 'public');
            $validated['group_banner_image'] = $bannerPath;
        }

        $group = Group::create($validated);
        $group->load('creator');

        return response()->json($group, 201);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($groupid)
    {
        // Authenticated user ki ID
        $userId = request()->header('user_id');

        // Specific group fetch karein jo current user ne banaya ho
        $group = Group::with('creator')
            ->where('id', $groupid)
            ->where('group_created_by', $userId)
            ->first();

        if (!$group) {
            return response()->json(['message' => 'Group not found'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $group
        ]);
    }
    public function showall()
    {
        // Current authenticated user ki ID get karenge
        $userId = auth()->id();

        // Groups retrieve karenge jo current user ne create kiye hain
        $groups = Group::with('creator')
            ->where('group_created_by', $userId)
            ->latest()
            ->get();

        if ($groups->isEmpty()) {
            return response()->json(['message' => 'No groups found for this user'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $groups,
            'total' => $groups->count()
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $groupid)
    {
        $group = Group::where('id', $groupid)
            ->where('group_created_by', Auth::id())
            ->first();

        if (!$group) {
            return response()->json(['message' => 'No group found to update'], 404);
        }

        $validated = $request->validate([
            'group_name' => 'sometimes|required|string|max:255',
            'group_description' => 'nullable|string',
            'group_type' => 'nullable|string|max:255',
            'group_industry' => 'nullable|string|max:255',
            'group_about' => 'nullable|string',
            'group_history' => 'nullable|string',
            'group_profile_photo' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'group_banner_image' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'location' => 'nullable|string|max:255',
            'is_active' => 'sometimes|boolean',
        ]);

        // Handle profile photo
        if ($request->hasFile('group_profile_photo')) {
            if ($group->group_profile_photo) {
                Storage::disk('public')->delete($group->group_profile_photo);
            }
            $validated['group_profile_photo'] = $request->file('group_profile_photo')->store('groups/profile_photos', 'public');
        }

        // Handle banner image
        if ($request->hasFile('group_banner_image')) {
            if ($group->group_banner_image) {
                Storage::disk('public')->delete($group->group_banner_image);
            }
            $validated['group_banner_image'] = $request->file('group_banner_image')->store('groups/banner_images', 'public');
        }

        $group->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Group updated successfully.',
            'data' => $group,
        ]);
    }

    public function deleteGroupFields(Request $request, $groupid)
    {
        // Check if at least one field is requested for deletion
        if (
            !$request->filled('group_profile_photo') &&
            !$request->filled('group_banner_image')
        ) {
            return response()->json([
                'message' => 'No field provided for deletion',
            ], 422);
        }

        try {
            // Get the group created by the current user
            $group = Group::where('id', $groupid)
                ->where('group_created_by', Auth::id())
                ->first();

            if (!$group) {
                return response()->json(['message' => 'Group not found'], 404);
            }

            $deletedFields = [];

            // Delete profile photo if requested
            if ($request->filled('group_profile_photo') && $request->input('group_profile_photo') === 'delete') {
                if ($group->group_profile_photo && Storage::disk('public')->exists($group->group_profile_photo)) {
                    Storage::disk('public')->delete($group->group_profile_photo);
                }
                $group->group_profile_photo = null;
                $deletedFields[] = 'group_profile_photo';
            }

            // Delete banner image if requested
            if ($request->filled('group_banner_image') && $request->input('group_banner_image') === 'delete') {
                if ($group->group_banner_image && Storage::disk('public')->exists($group->group_banner_image)) {
                    Storage::disk('public')->delete($group->group_banner_image);
                }
                $group->group_banner_image = null;
                $deletedFields[] = 'group_banner_image';
            }

            // Save changes
            $group->save();

            return response()->json([
                'message' => 'Selected fields deleted successfully',
                'deleted_fields' => $deletedFields,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error deleting group fields: ' . $e->getMessage()
            ], 500);
        }
    }


    public function getMedia(Request $request)
    {
        try {
            // Validate the request
            $validator = Validator::make($request->all(), [
                'groupId' => 'required|integer|exists:groups,id',
                'type' => 'required|string|in:photos,videos'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $groupId = $request->input('groupId');
            $type = $request->input('type');

            // Check if user owns the page
            $group = Group::where('id', $groupId)->first();
            // ->where('owner_id', $user->id)

            if (!$group) {
                return response()->json([
                    'success' => false,
                    'message' => 'group not found or unauthorized access'
                ], 404);
            }

            // Get posts with media for this page
            $groups = Post::where('group_id', $groupId)
                ->where('type', $type === 'photos' ? 'image' : 'video')
                ->with('media')
                ->get();

            $media = [];

            foreach ($groups as $group) {
                foreach ($group->media as $mediaItem) {
                    if ($type === 'photos' && $mediaItem->type === 'image') {
                        $media[] = [
                            'url' => $mediaItem->file,
                            'created_at' => $mediaItem->created_at
                        ];
                    } elseif ($type === 'videos' && $mediaItem->type === 'video') {
                        // For videos, we might want to extract a thumbnail
                        // For now, we'll use a placeholder or the first frame extraction could be implemented later
                        $media[] = [
                            'thumbnail' => $mediaItem->file,
                            'url' => $mediaItem->file,
                            // 'views' => $this->getVideoViews($post->id),
                            'created_at' => $mediaItem->created_at
                        ];
                    }
                }
            }

            return response()->json([
                'success' => true,
                'media' => $media
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching media: ' . $e->getMessage()
            ], 500);
        }
    }


    public function getOwner($groupid)
    {

        // Specific group fetch karein jo current user ne banaya ho
        $group = Group::with('creator')
            ->where('id', $groupid)
            ->first();

        if (!$group) {
            return response()->json(['message' => 'Group not found'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $group->group_created_by
        ]);
    }
}
