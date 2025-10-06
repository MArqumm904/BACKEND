<?php

namespace App\Http\Controllers;

use App\Models\Media;
use App\Models\Poll;
use App\Models\Post;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Page;
use App\Models\Group;
use App\Models\Profile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;


class UserProfileController extends Controller
{
    public function getProfile($user_id)
    {
        $user = User::with('profile')
            ->withCount('posts')
            ->find($user_id);

        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        // Fetch the latest page where owner_id matches this user's ID
        $latestPage = Page::where('owner_id', $user_id)
            ->latest()
            ->first();

        // Fetch the latest group where owner_id matches this user's ID
        $latestGroup = Group::where('group_created_by', $user_id)
            ->latest()
            ->first();

        return response()->json([
            'user' => $user,
            'profile' => $user->profile,
            'posts_count' => $user->posts_count,
            'latest_page' => $latestPage,
            'latest_group' => $latestGroup
        ]);
    }


    public function updateProfile(Request $request, $user_id)
    {
        // Validate inputs
        $request->validate([
            'profile_photo' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'cover_photo' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'location' => 'nullable|string|max:255',
            'headline' => 'nullable|string|max:255',
            'name' => 'nullable|string|max:255',
        ]);

        if (
            !$request->hasFile('profile_photo') &&
            !$request->hasFile('cover_photo') &&
            !$request->filled('location') &&
            !$request->filled('headline') &&
            !$request->filled('name')
        ) {
            return response()->json([
                'message' => 'No data provided to update',
                'errors' => [
                    'profile_photo' => ['Nothing to update.'],
                    'cover_photo' => ['Nothing to update.'],
                    'location' => ['Nothing to update.'],
                    'headline' => ['Nothing to update.'],
                    'name' => ['Nothing to update.'],
                ],
            ], 422);
        }

        try {
            // Get the profile
            $profile = Profile::where('user_id', $user_id)->first();
            if (!$profile) {
                return response()->json(['message' => 'Profile not found'], 404);
            }

            $updateData = [];

            // Handle profile photo
            if ($request->hasFile('profile_photo')) {
                $profileFile = $request->file('profile_photo');
                if (!$profileFile->isValid()) {
                    return response()->json([
                        'message' => 'Invalid profile photo upload',
                        'errors' => ['profile_photo' => ['The uploaded profile photo is invalid.']]
                    ], 422);
                }

                if ($profile->profile_photo) {
                    Storage::disk('public')->delete($profile->profile_photo);
                }

                $profilePath = $profileFile->store('profiles/profile_photos', 'public');
                $updateData['profile_photo'] = $profilePath;
            }

            // Handle cover photo
            if ($request->hasFile('cover_photo')) {
                $coverFile = $request->file('cover_photo');
                if (!$coverFile->isValid()) {
                    return response()->json([
                        'message' => 'Invalid cover photo upload',
                        'errors' => ['cover_photo' => ['The uploaded cover photo is invalid.']]
                    ], 422);
                }

                if ($profile->cover_photo) {
                    Storage::disk('public')->delete($profile->cover_photo);
                }

                $coverPath = $coverFile->store('profiles/cover_photos', 'public');
                $updateData['cover_photo'] = $coverPath;
            }

            // Add location and headline to updateData if present
            if ($request->filled('location')) {
                $updateData['location'] = $request->input('location');
            }

            if ($request->filled('headline')) {
                $updateData['headline'] = $request->input('headline');
            }

            // Update profile table
            $profile->update($updateData);

            // Update name in users table
            // if ($request->filled('name')) {
            //     User::with('id', $user_id)->update([
            //         'name' => $request->input('name')
            //     ]);
            // }

            $nameUpdated = null;
            if ($request->filled('name')) {
                $user = User::find($user_id);
                if ($user) {
                    $user->update([
                        'name' => $request->input('name')
                    ]);
                    $nameUpdated = $request->input('name');
                }
            }

            // return response()->json([
            //     'message' => 'Profile updated successfully',
            //     'updated_profile_fields' => $updateData,
            //     'name_updated' => $request->filled('name') ? $request->input('name') : null,
            // ]);
            return response()->json([
                'message' => 'Profile updated successfully',
                'updated_profile_fields' => $updateData,
                'name_updated' => $nameUpdated,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error updating profile: ' . $e->getMessage()
            ], 500);
        }
    }

    public function deleteProfileFields(Request $request, $user_id)
    {
        // Validate that at least one field is provided
        if (
            !$request->filled('profile_photo') &&
            !$request->filled('cover_photo') &&
            !$request->filled('location') &&
            !$request->filled('headline') &&
            !$request->filled('name')
        ) {
            return response()->json([
                'message' => 'No field provided for deletion',
            ], 422);
        }

        try {
            // Get the profile
            $profile = Profile::where('user_id', $user_id)->first();
            if (!$profile) {
                return response()->json(['message' => 'Profile not found'], 404);
            }

            $deletedFields = [];

            // Delete profile photo
            if ($request->filled('profile_photo') && $request->input('profile_photo') === 'delete') {
                if ($profile->profile_photo && \Illuminate\Support\Facades\Storage::disk('public')->exists($profile->profile_photo)) {
                    \Illuminate\Support\Facades\Storage::disk('public')->delete($profile->profile_photo);
                }
                $profile->profile_photo = null;
                $deletedFields[] = 'profile_photo';
            }

            // Delete cover photo
            if ($request->filled('cover_photo') && $request->input('cover_photo') === 'delete') {
                if ($profile->cover_photo && \Illuminate\Support\Facades\Storage::disk('public')->exists($profile->cover_photo)) {
                    \Illuminate\Support\Facades\Storage::disk('public')->delete($profile->cover_photo);
                }
                $profile->cover_photo = null;
                $deletedFields[] = 'cover_photo';
            }

            // Clear location
            if ($request->filled('location') && $request->input('location') === 'delete') {
                $profile->location = null;
                $deletedFields[] = 'location';
            }

            // Clear headline
            if ($request->filled('headline') && $request->input('headline') === 'delete') {
                $profile->headline = null;
                $deletedFields[] = 'headline';
            }

            // Save profile updates
            $profile->save();

            // Clear name in User table
            if ($request->filled('name') && $request->input('name') === 'delete') {
                $user = User::find($user_id);
                if ($user) {
                    $user->name = null;
                    $user->save();
                    $deletedFields[] = 'name';
                }
            }

            return response()->json([
                'message' => 'Selected fields deleted successfully',
                'deleted_fields' => $deletedFields,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error deleting profile fields: ' . $e->getMessage()
            ], 500);
        }
    }

    public function storetext(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'content' => 'required|string|max:1000',
            'type' => 'required|string|in:text,image,video,poll',
            'visibility' => 'required|string|in:public,private,friends',
            'profile_id' => 'required|integer|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $profile = Profile::where('user_id', $request->profile_id)->first();

        if (!$profile || $profile->user_id !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to post on this profile',
                'profile' => $profile->user_id,
                'auth_id' => auth()->id()
            ], 403);
        }

        $post = Post::create([
            'user_id' => $request->profile_id,
            'content' => $request->content,
            'type' => $request->type,
            'visibility' => $request->visibility,
        ]);


        return response()->json([
            'success' => true,
            'message' => 'Post created successfully',
            'data' => [
                'post' => $post,
            ]
        ], 201);
    }

    public function storeimage(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'content' => 'nullable|string|max:1000',
            'type' => 'required|string|in:text,image,video,poll',
            'visibility' => 'required|string|in:public,private,friends',
            'image' => 'required_if:type,image|image|mimes:jpeg,png,jpg,gif|max:2048',
            'profile_id' => 'required|integer|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $profile = Profile::where('user_id', $request->profile_id)->first();

        if (!$profile || $profile->user_id !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to post on this profile'
            ], 403);
        }

        // Create the post
        $post = Post::create([
            'user_id' => $request->profile_id,
            'content' => $request->content ?? '',
            'type' => $request->type,
            'visibility' => $request->visibility,
        ]);

        if ($request->type === 'image' && $request->hasFile('image')) {
            $imagePath = $request->file('image')->store('posts/images', 'public');

            // Create media record
            Media::create([
                'post_id' => $post->id,
                'file' => $imagePath,
                'type' => 'image',
            ]);
        }


        $post->load('media');


        return response()->json([
            'success' => true,
            'message' => 'Post created successfully',
            'data' => [
                'post' => $post,
            ]
        ], 201);
    }

    public function storevideo(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'content' => 'nullable|string|max:1000',
            'type' => 'required|string|in:text,image,video,poll',
            'visibility' => 'required|string|in:public,private,friends',
            'video' => 'required_if:type,video|file|mimes:mp4,mov,avi,wmv,flv,webm|max:20480',
            'profile_id' => 'required|integer|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $profile = Profile::where('user_id', $request->profile_id)->first();

        if (!$profile || $profile->user_id !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to post on this profile'
            ], 403);
        }

        // Create the post
        $post = Post::create([
            'user_id' => $request->profile_id,
            'content' => $request->content ?? '',
            'type' => $request->type,
            'visibility' => $request->visibility,
        ]);

        if ($request->type === 'video' && $request->hasFile('video')) {
            $videoPath = $request->file('video')->store('posts/videos', 'public');

            // Create media record
            Media::create([
                'post_id' => $post->id,
                'file' => $videoPath,
                'type' => 'video',
            ]);
        }

        $post->load('media');

        return response()->json([
            'success' => true,
            'message' => 'Video post created successfully',
            'data' => [
                'post' => $post,
            ]
        ], 201);
    }

    public function storepoll(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'content' => 'required|string|max:1000',
            'type' => 'required|string|in:text,image,video,poll',
            'visibility' => 'required|string|in:public,private,friends',
            'question' => 'required_if:type,poll|string',
            'options' => 'required_if:type,poll|array|min:2',
            'profile_id' => 'required|integer|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $profile = Profile::where('user_id', $request->profile_id)->first();

        if (!$profile || $profile->user_id !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to post on this profile'
            ], 403);
        }

        // Create the post
        $post = Post::create([
            'user_id' => $request->profile_id,
            'content' => $request->content,
            'type' => $request->type,
            'visibility' => $request->visibility,
        ]);

        // If it's a poll, create poll entry
        if ($request->type === 'poll') {
            Poll::create([
                'post_id' => $post->id,
                'question' => $request->question,
                'options' => json_encode($request->options), // ensure it's stored as JSON
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Post created successfully',
            'data' => [
                'post' => $post,
            ]
        ], 201);
    }
}
