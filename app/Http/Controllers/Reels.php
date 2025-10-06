<?php

namespace App\Http\Controllers;

use App\Models\ReelLike;
use App\Models\reelSave;
use Illuminate\Http\Request;
use App\Models\Reel;
use App\Models\ReelsComments;

class Reels extends Controller
{
    public function uploadreel(Request $request)
    {
        // Validate request
        $request->validate([
            'description' => 'nullable|string',
            'tags' => 'nullable|array',
            'video' => 'required|file|mimes:mp4,mov,avi,wmv', // max 20MB
            'thumbnail' => 'image|mimes:jpg,jpeg,png|max:5000', // max 5MB
            'visibility' => 'required|string|in:public,private',
        ]);

        // Authenticated user
        $user = auth()->user();

        // Save video file
        $videoPath = null;
        if ($request->hasFile('video')) {
            $videoPath = $request->file('video')->store('reels/videos', 'public');
        }

        // Save thumbnail image
        $thumbnailPath = null;
        if ($request->hasFile('thumbnail')) {
            $thumbnailPath = $request->file('thumbnail')->store('reels/thumbnails', 'public');
        }

        // Create reel record using Eloquent
        Reel::create([
            'user_id' => $user->id,
            'description' => $request->description,
            'tags' => $request->tags ? json_encode($request->tags) : null,
            'video_file' => $videoPath,
            'thumbnail' => $thumbnailPath,
            'views' => 0,
            'likes' => 0,
            'comments_count' => 0,
            'visibility' => $request->visibility,
            'created_at' => now(),
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Reel uploaded successfully!',
        ]);
    }

    public function getreels(Request $request)
    {
        $currentUserId = auth()->id();

        // frontend se aaye IDs jo sirf is user ke liye already fetched hain
        $fetchedReelIds = $request->input('already_fetched_ids', []);

        $reels = Reel::with(['user.profile'])
            ->whereNotIn('id', $fetchedReelIds)
            ->inRandomOrder()
            ->take(3)
            ->get();


        $reels->transform(function ($reel) use ($currentUserId) {
            $reel->isLiked = $reel->likes()
                ->where('user_id', $currentUserId)
                ->exists();

            $reel->isSaved = $reel->saves()
                ->where('user_id', $currentUserId)
                ->exists();

            return $reel;
        });

        return response()->json([
            'status' => true,
            'fetched_ids' => $reels->pluck('id'),
            'data' => $reels
        ]);
    }

    public function likeReel(Request $request)
    {
        // Validate request
        $request->validate([
            'reel_id' => 'required|integer|exists:reels,id',
            'user_id' => 'required|integer|exists:users,id',
        ]);

        try {
            $reelId = $request->input('reel_id');
            $userId = $request->input('user_id');

            $reel = Reel::find($reelId);

            // Check if like already exists
            $existingLike = ReelLike::where('reel_id', $reelId)
                ->where('user_id', $userId)
                ->first();

            if ($existingLike) {
                // Unlike → delete record
                $existingLike->delete();


                $reel->likes = max(0, $reel->likes - 1);

                $reel->save();

                return response()->json([
                    'success' => true,
                    'message' => 'Reel unliked successfully',
                    'liked' => false,
                ]);
            } else {
                // Like → create record
                ReelLike::create([
                    'reel_id' => $reelId,
                    'user_id' => $userId,
                ]);

                $reel->likes += 1;

                $reel->save();
                return response()->json([
                    'success' => true,
                    'message' => 'Reel liked successfully',
                    'liked' => true,
                ]);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function saveReel(Request $request)
    {
        // Validate request
        $request->validate([
            'reel_id' => 'required|integer|exists:reels,id',
            'user_id' => 'required|integer|exists:users,id',
        ]);

        try {
            $reelId = $request->input('reel_id');
            $userId = $request->input('user_id');

            // Check if like already exists
            $existingSave = reelSave::where('reel_id', $reelId)
                ->where('user_id', $userId)
                ->first();

            if ($existingSave) {
                // Unlike → delete record
                $existingSave->delete();

                return response()->json([
                    'success' => true,
                    'message' => 'Reel unSaved successfully',
                    'saved' => false,
                ]);
            } else {
                // Like → create record
                reelSave::create([
                    'reel_id' => $reelId,
                    'user_id' => $userId,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Reel Saved successfully',
                    'saved' => true,
                ]);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function reelscomments(Request $request)
    {
        $reelId = $request->input('reel_id');
        $userId = auth()->id();

        // Pehle check karo reel saved hai ya nahi
        $isSaved = reelSave::where('reel_id', $reelId)
            ->where('user_id', $userId)
            ->exists();

        // Root comments (parent_id = null)
        $comments = ReelsComments::where('reel_id', $reelId)
            ->whereNull('parent_id')
            ->with([
                'user:id,name,email',
                'user.profile:user_id,profile_photo',
                'replies.user:id,name',
                'replies.user.profile:user_id,profile_photo'
            ])
            ->orderBy('created_at', 'desc')
            ->get();

        // Har comment me saved_reel add karo
        $comments->transform(function ($comment) use ($isSaved) {
            $comment->saved_reel = $isSaved ? 'saved' : 'not_saved';
            return $comment;
        });

        // Agar comments empty hain to bhi saved_reel ka status wapas bhejo
        if ($comments->isEmpty()) {
            return response()->json([
                'success' => true,
                'data' => [],
                'saved_reel' => $isSaved ? 'saved' : 'not_saved'
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $comments
        ]);
    }

    public function storereelcommentreply(Request $request)
    {
        // Get authenticated user ID
        $userId = auth()->id(); // or Auth::id()

        // Validation
        $request->validate([
            'reel_id' => 'required|integer',
            'comment' => 'required|string',
            'parent_id' => 'nullable|integer'
        ]);

        // Create new reel comment/reply
        $reelComment = ReelsComments::create([
            'reel_id' => $request->reel_id,
            'user_id' => $userId,
            'parent_id' => $request->parent_id, // null if root comment, has value if reply
            'comment' => $request->comment,
            'likes_count' => 0
        ]);

        // Load user relationship
        $reelComment->load('user:id,name');

        return response()->json([
            'success' => true,
            'message' => 'Reel comment added successfully',
            'data' => $reelComment
        ]);
    }

    public function storereelreply(Request $request)
    {
        // Get authenticated user ID
        $userId = auth()->id();

        // Validation (no need to validate user_id anymore)
        $request->validate([
            'reel_id' => 'required|integer',
            'parent_id' => 'required|integer',
            'comment' => 'required|string'
        ]);

        // Reply create
        $reply = ReelsComments::create([
            'reel_id' => $request->reel_id,
            'user_id' => $userId, // Use authenticated user ID
            'parent_id' => $request->parent_id,
            'comment' => $request->comment,
            'likes_count' => 0
        ]);

        // Reply ka user load karke bhej do
        $reply->load('user:id,name');

        return response()->json([
            'success' => true,
            'message' => 'Reel reply added successfully',
            'data' => $reply
        ]);
    }
    public function unsavedreel(Request $request)
    {
        try {
            // Validation
            $request->validate([
                'reel_id' => 'required|integer'
            ]);

            $userId = auth()->id();
            $reelId = $request->reel_id;

            // Check if already saved
            $savedReel = reelSave::where('user_id', $userId)
                ->where('reel_id', $reelId)
                ->first();

            if ($savedReel) {
                // If exists → delete
                $savedReel->delete();
                $action = 'unsaved';
            } else {
                // If not exists → create
                reelSave::create([
                    'user_id' => $userId,
                    'reel_id' => $reelId,
                    'created_at' => now(),
                ]);
                $action = 'saved';
            }

            return response()->json([
                'success' => true,
                'message' => "Reel {$action} successfully",
                'data' => [
                    'reel_id' => $reelId,
                    'user_saved' => $action === 'saved'
                ]
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Something went wrong',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
