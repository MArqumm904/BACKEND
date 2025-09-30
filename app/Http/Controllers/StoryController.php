<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Story;
use App\Models\StoryText;
use App\Models\Friend;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Facades\Image;
use Carbon\Carbon;

class StoryController extends Controller
{
    public function createTextStory(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'text_elements' => 'required|array|min:1',
            'text_elements.*.text_content' => 'required|string|max:500',
            'text_elements.*.x_position' => 'nullable|numeric|min:0',
            'text_elements.*.y_position' => 'nullable|numeric|min:0',
            'text_elements.*.font_size' => 'required|integer|min:8|max:72',
            'text_elements.*.font_color' => 'nullable|string|max:20',
            'duration' => 'nullable|integer|min:1|max:15',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $story = Story::create([
                'user_id' => Auth::id(),
                'type' => 'text',
                'duration' => $request->duration ?? 5,
            ]);

            foreach ($request->text_elements as $index => $textElement) {
                StoryText::create([
                    'story_id' => $story->id,
                    'text_content' => $textElement['text_content'],
                    'x_position' => $textElement['x_position'] ?? 50,
                    'y_position' => $textElement['y_position'] ?? 100,
                    'font_size' => $textElement['font_size'] ?? '32px',
                    'font_color' => $textElement['font_color'] ?? '#ffffff',
                    'order_index' => $index,
                ]);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Text story created successfully',
                'data' => [
                    'story_id' => $story->id,
                    'expires_at' => $story->expires_at
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create text story',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // public function createImageStory(Request $request)
    // {

    //     $validator = Validator::make($request->all(), [
    //         'image' => 'required|image|mimes:jpeg,png,jpg,gif|max:10240',
    //         'text_elements' => 'nullable|json',
    //         'duration' => 'nullable|integer|min:1|max:15',
    //         'scale' => 'nullable|numeric|min:0.1|max:5',
    //         'position_x' => 'nullable|numeric',
    //         'position_y' => 'nullable|numeric',
    //     ]);

    //     if ($validator->fails()) {
    //         return response()->json([
    //             'status' => 'error',
    //             'message' => 'Validation failed',
    //             'errors' => $validator->errors()
    //         ], 422);
    //     }

    //     try {
    //         $image = $request->file('image');
    //         $imagePath = 'stories/' . Auth::id() . '/' . time() . '_' . $image->getClientOriginalName();

    //         $img = Image::make($image->getRealPath());
    //         $img->resize(1080, 1920, function ($constraint) {
    //             $constraint->aspectRatio();
    //             $constraint->upsize();
    //         });

    //         Storage::disk('public')->put($imagePath, (string) $img->encode());

    //         $story = Story::create([
    //             'user_id' => Auth::id(),
    //             'type' => 'image',
    //             'media_path' => $imagePath,
    //             'media_type' => $image->getMimeType(),
    //             'duration' => $request->duration ?? 5,
    //             'x_position' => $request->position_x ?? 0,
    //             'y_position' => $request->position_y ?? 0,
    //             'scale' => $request->scale ?? 0,
    //         ]);

    //         if ($request->text_elements) {
    //             $textElements = json_decode($request->text_elements, true);

    //             $textValidator = Validator::make($textElements, [
    //                 '*.text_content' => 'required|string|max:500',
    //                 '*.x_position' => 'nullable|numeric|min:0',
    //                 '*.y_position' => 'nullable|numeric|min:0',
    //                 '*.font_size' => 'required|integer|min:8|max:72',
    //                 '*.font_color' => 'nullable|string|max:20',
    //             ]);

    //             if ($textValidator->fails()) {
    //                 throw new \Exception('Invalid text elements: ' . json_encode($textValidator->errors()));
    //             }

    //             foreach ($textElements as $index => $textElement) {
    //                 StoryText::create([
    //                     'story_id' => $story->id,
    //                     'text_content' => $textElement['text_content'],
    //                     'x_position' => $textElement['x_position'] ?? 50,
    //                     'y_position' => $textElement['y_position'] ?? 100,
    //                     'font_size' => $textElement['font_size'] ?? 32,
    //                     'font_color' => $textElement['font_color'] ?? '#ffffff',
    //                     'order_index' => $index,
    //                 ]);
    //             }
    //         }

    //         return response()->json([
    //             'status' => 'success',
    //             'message' => 'Image story created successfully',
    //             'data' => [
    //                 'story_id' => $story->id,
    //                 'expires_at' => $story->expires_at,
    //                 'image_url' => Storage::url($imagePath)
    //             ]
    //         ], 201);

    //     } catch (\Exception $e) {
    //         if (isset($imagePath) && Storage::disk('public')->exists($imagePath)) {
    //             Storage::disk('public')->delete($imagePath);
    //         }

    //         return response()->json([
    //             'status' => 'error',
    //             'message' => 'Failed to create image story',
    //             'error' => $e->getMessage()
    //         ], 500);
    //     }
    // }
    
    
    public function createImageStory(Request $request)
{
    $validator = Validator::make($request->all(), [
        'image' => 'required|image|mimes:jpeg,png,jpg,gif|max:10240',
        'text_elements' => 'nullable|json',
        'duration' => 'nullable|integer|min:1|max:15',
        'scale' => 'nullable|numeric|min:0.1|max:5',
        'position_x' => 'nullable|numeric',
        'position_y' => 'nullable|numeric',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'status' => 'error',
            'message' => 'Validation failed',
            'errors' => $validator->errors()
        ], 422);
    }

    try {
        $image = $request->file('image');
        $imageName = time() . '_' . $image->getClientOriginalName();
        $imagePath = 'stories/' . Auth::id() . '/' . $imageName;

        // ✅ Original image store without resize
        Storage::disk('public')->putFileAs('stories/' . Auth::id(), $image, $imageName);

        $story = Story::create([
            'user_id' => Auth::id(),
            'type' => 'image',
            'media_path' => $imagePath,
            'media_type' => $image->getMimeType(),
            'duration' => $request->duration ?? 5,
            'x_position' => $request->position_x ?? 0,
            'y_position' => $request->position_y ?? 0,
            'scale' => $request->scale ?? 0,
        ]);

        if ($request->text_elements) {
            $textElements = json_decode($request->text_elements, true);

            $textValidator = Validator::make($textElements, [
                '*.text_content' => 'required|string|max:500',
                '*.x_position' => 'nullable|numeric|min:0',
                '*.y_position' => 'nullable|numeric|min:0',
                '*.font_size' => 'required|integer|min:8|max:72',
                '*.font_color' => 'nullable|string|max:20',
            ]);

            if ($textValidator->fails()) {
                throw new \Exception('Invalid text elements: ' . json_encode($textValidator->errors()));
            }

            foreach ($textElements as $index => $textElement) {
                StoryText::create([
                    'story_id' => $story->id,
                    'text_content' => $textElement['text_content'],
                    'x_position' => $textElement['x_position'] ?? 50,
                    'y_position' => $textElement['y_position'] ?? 100,
                    'font_size' => $textElement['font_size'] ?? 32,
                    'font_color' => $textElement['font_color'] ?? '#ffffff',
                    'order_index' => $index,
                ]);
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Image story created successfully',
            'data' => [
                'story_id' => $story->id,
                'expires_at' => $story->expires_at,
                'image_url' => Storage::url($imagePath)
            ]
        ], 201);

    } catch (\Exception $e) {
        if (isset($imagePath) && Storage::disk('public')->exists($imagePath)) {
            Storage::disk('public')->delete($imagePath);
        }

        return response()->json([
            'status' => 'error',
            'message' => 'Failed to create image story',
            'error' => $e->getMessage()
        ], 500);
    }
}


    public function createPostImageStory(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'post_id' => 'required|integer|exists:posts,id',
            'text_elements' => 'nullable|array',
            'text_elements.*.text_content' => 'required_with:text_elements|string|max:500',
            'text_elements.*.x_position' => 'nullable|numeric|min:0',
            'text_elements.*.y_position' => 'nullable|numeric|min:0',
            'text_elements.*.font_size' => 'required_with:text_elements|integer|min:8|max:72',
            'text_elements.*.font_color' => 'nullable|string|max:20',
            'duration' => 'nullable|integer|min:1|max:15',
            'scale' => 'nullable|numeric|min:0.1|max:5',
            'position_x' => 'nullable|numeric',
            'position_y' => 'nullable|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $story = Story::create([
                'user_id' => Auth::id(),
                'type' => 'postimage',
                'post_id' => $request->post_id,
                'duration' => $request->duration ?? 5,
                'x_position' => $request->position_x ?? 0,
                'y_position' => $request->position_y ?? 0,
                'scale' => $request->scale ?? 1,
            ]);

            if ($request->has('text_elements') && is_array($request->text_elements)) {
                foreach ($request->text_elements as $index => $textElement) {
                    StoryText::create([
                        'story_id' => $story->id,
                        'text_content' => $textElement['text_content'],
                        'x_position' => $textElement['x_position'] ?? 50,
                        'y_position' => $textElement['y_position'] ?? 100,
                        'font_size' => $textElement['font_size'] ?? 32,
                        'font_color' => $textElement['font_color'] ?? '#ffffff',
                        'order_index' => $index,
                    ]);
                }
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Post image story created successfully',
                'data' => [
                    'story_id' => $story->id,
                    'expires_at' => $story->expires_at
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create post image story',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function createPostTextStory(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'post_id' => 'required|integer|exists:posts,id',
            'content' => 'required|string|max:1000',
            'text_elements' => 'nullable|array',
            'text_elements.*.text_content' => 'required_with:text_elements|string|max:500',
            'text_elements.*.x_position' => 'nullable|numeric|min:0',
            'text_elements.*.y_position' => 'nullable|numeric|min:0',
            'text_elements.*.font_size' => 'required_with:text_elements|integer|min:8|max:72',
            'text_elements.*.font_color' => 'nullable|string|max:20',
            'text_elements.*.font_weight' => 'nullable|string|in:normal,bold',
            'duration' => 'nullable|integer|min:1|max:15',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $story = Story::create([
                'user_id' => Auth::id(),
                'type' => 'posttext',
                'post_id' => $request->post_id,
                // 'content' => $request->content,
                'duration' => $request->duration ?? 5,
            ]);

            if ($request->has('text_elements') && is_array($request->text_elements)) {
                foreach ($request->text_elements as $index => $textElement) {
                    StoryText::create([
                        'story_id' => $story->id,
                        'text_content' => $textElement['text_content'],
                        'x_position' => $textElement['x_position'] ?? 50,
                        'y_position' => $textElement['y_position'] ?? 100,
                        'font_size' => $textElement['font_size'] ?? 32,
                        'font_color' => $textElement['font_color'] ?? '#ffffff',
                        'order_index' => $index,
                    ]);
                }
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Post text story created successfully',
                'data' => [
                    'story_id' => $story->id,
                    'expires_at' => $story->expires_at
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create post text story',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function createPostVideoStory(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'post_id' => 'required|integer|exists:posts,id',
            'text_elements' => 'nullable|array',
            'text_elements.*.text_content' => 'required_with:text_elements|string|max:500',
            'text_elements.*.x_position' => 'nullable|numeric|min:0',
            'text_elements.*.y_position' => 'nullable|numeric|min:0',
            'text_elements.*.font_size' => 'required_with:text_elements|integer|min:8|max:72',
            'text_elements.*.font_color' => 'nullable|string|max:20',
            'duration' => 'nullable|integer|min:1|max:15',
            'scale' => 'nullable|numeric|min:0.1|max:5',
            'position_x' => 'nullable|numeric',
            'position_y' => 'nullable|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $story = Story::create([
                'user_id' => Auth::id(),
                'type' => 'postvideo',
                'post_id' => $request->post_id,
                'duration' => $request->duration ?? 5,
                'x_position' => $request->position_x ?? 0,
                'y_position' => $request->position_y ?? 0,
                'scale' => $request->scale ?? 1,
            ]);

            if ($request->has('text_elements') && is_array($request->text_elements)) {
                foreach ($request->text_elements as $index => $textElement) {
                    StoryText::create([
                        'story_id' => $story->id,
                        'text_content' => $textElement['text_content'],
                        'x_position' => $textElement['x_position'] ?? 50,
                        'y_position' => $textElement['y_position'] ?? 100,
                        'font_size' => $textElement['font_size'] ?? 32,
                        'font_color' => $textElement['font_color'] ?? '#ffffff',
                        'order_index' => $index,
                    ]);
                }
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Post video story created successfully',
                'data' => [
                    'story_id' => $story->id,
                    'expires_at' => $story->expires_at
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create post video story',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function getFriendsStories(Request $request)
    {
        try {
            $user = Auth::user();

            $userHasStory = Story::where('user_id', $user->id)
                ->where('created_at', '>=', Carbon::now()->subHours(24))
                ->exists();

            $friends = Friend::where(function ($query) use ($user) {
                $query->where('user_id', $user->id)
                    ->orWhere('friend_id', $user->id);
            })
                ->where('status', 'accepted')
                ->with(['user.profile', 'friend.profile'])
                ->get();

            $friendIds = $friends->map(function ($item) use ($user) {
                return $item->user_id === $user->id ? $item->friend_id : $item->user_id;
            });

            $friendsStories = Story::whereIn('user_id', $friendIds)
                ->where('created_at', '>=', Carbon::now()->subHours(24))
                ->with(['user:id,name', 'user.profile:user_id,profile_photo'])
                ->get()
                ->groupBy('user_id');

            $response = [];

            // Add user's own story if exists
            if ($userHasStory) {
                $response[] = [
                    'id' => $user->id,
                    'name' => $user->name,
                    'avatar' => optional($user->profile)->profile_photo,
                    'isYour' => true,
                ];
            }

            // Add friends' stories
            foreach ($friendsStories as $userId => $stories) {
                $friend = $stories->first()->user;

                $response[] = [
                    'id' => $friend->id,
                    'name' => $friend->name,
                    'avatar' => optional($friend->profile)->profile_photo,
                    'isYour' => false,
                ];
            }

            return response()->json([
                'status' => 'success',
                'data' => $response
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch stories',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getUserStories(Request $request, $userId)
    {
        try {
            $stories = Story::where('user_id', $userId)
                ->where('created_at', '>=', Carbon::now()->subHours(24))
                ->with([
                    'texts',
                    'post.media',
                    'post.user.profile',
                    'user.profile'
                ])
                ->orderBy('created_at', 'desc')
                ->get();

            if ($stories->isEmpty()) {
                return response()->json([
                    'status' => 'success',
                    'data' => [],
                    'message' => 'No stories found for this user'
                ]);
            }

            $formattedStories = $stories->map(function ($story) {
                $storyData = [
                    'id' => $story->id,
                    'user_id' => $story->user_id,
                    'type' => $story->type,
                    'duration' => $story->duration ?: 5, // Default 5 seconds
                    'post_id' => $story->post_id,
                    'media_path' => $story->media_path,
                    'media_type' => $story->media_type,
                    'x_position' => $story->x_position,
                    'y_position' => $story->y_position,
                    'background_color' => $story->background_color,
                    'expires_at' => $story->expires_at,
                    'scale' => $story->scale,
                    'texts' => $story->texts->map(function ($text) {
                        return [
                            'id' => $text->id,
                            'text_content' => $text->text_content,
                            'x_position' => $text->x_position ?: 50,
                            'y_position' => $text->y_position ?: 50,
                            'font_size' => $text->font_size ?: 24,
                            'font_color' => $text->font_color ?: '#ffffff',
                            'font_weight' => $text->font_weight ?: 'normal',
                            'order_index' => $text->order_index ?: 0,
                        ];
                    }),
                    'created_at' => $story->created_at,
                    'updated_at' => $story->updated_at,
                ];

                // Add user info
                if ($story->user) {
                    $storyData['user'] = [
                        'id' => $story->user->id,
                        'name' => $story->user->name,
                        'avatar' => optional($story->user->profile)->profile_photo,
                    ];
                }

                // Add post info if exists
                if ($story->post_id && $story->post) {
                    $storyData['post'] = [
                        'id' => $story->post->id,
                        'user_id' => $story->post->user_id,
                        'content' => $story->post->content,
                        'type' => $story->post->type,
                        'visibility' => $story->post->visibility,
                        'media' => $story->post->media ? $story->post->media->map(function ($media) {
                            return [
                                'id' => $media->id,
                                'type' => $media->type,
                                'file' => $media->file,
                            ];
                        }) : [],
                    ];

                    // Add post user info
                    if ($story->post->user) {
                        $storyData['post']['user'] = [
                            'id' => $story->post->user->id,
                            'name' => $story->post->user->name,
                            'avatar' => optional($story->post->user->profile)->profile_photo,
                        ];
                    }
                }

                return $storyData;
            });

            return response()->json([
                'status' => 'success',
                'data' => $formattedStories
            ]);

        } catch (\Exception $e) {
            \Log::error('Error fetching user stories: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch user stories',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function deleteStory(Request $request)
    {
        // \Log::info('Delete story request received:', $request->all());
        // Log::info('Authenticated user ID: ' . Auth::id());

        $validator = Validator::make($request->all(), [
            'id' => 'required|integer|exists:stories,id',
            'type' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $story = Story::where('id', $request->id)
                ->where('user_id', Auth::id())
                ->first();

            if (!$story) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Story not found or you do not have permission to delete it'
                ], 404);
            }

            // Delete associated text elements
            StoryText::where('story_id', $story->id)->delete();

            // Delete media file if it's an image story
            if ($story->type === 'image' && $story->media_path) {
                if (Storage::disk('public')->exists($story->media_path)) {
                    Storage::disk('public')->delete($story->media_path);
                }
            }

            // Delete the story
            $story->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Story deleted successfully'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete story',
                'error' => $e->getMessage()
            ], 500);
        }
    }

}