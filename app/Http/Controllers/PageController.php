<?php

namespace App\Http\Controllers;

use App\Models\Like;
use DB;
use Illuminate\Support\Facades\Log;
use App\Models\Media;
use App\Models\Page;
use App\Models\Poll;
use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class PageController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'page_name' => 'required|string|max:255',
            'page_description' => 'nullable|string',
            'page_category' => 'nullable|string|max:255',
            'page_type' => 'nullable|string|max:255',
            'page_profile_photo' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            'page_cover_photo' => 'nullable|image|mimes:jpg,jpeg,png|max:4096',
        ]);

        $user = $request->user();

        if ($request->hasFile('page_profile_photo')) {
            $validated['page_profile_photo'] = $request->file('page_profile_photo')->store('pages/profile_photos', 'public');
        }

        if ($request->hasFile('page_cover_photo')) {
            $validated['page_cover_photo'] = $request->file('page_cover_photo')->store('pages/cover_images', 'public');
        }

        $validated['owner_id'] = $user->id;

        $page = Page::create($validated);

        return response()->json([
            'message' => 'Page created successfully',
            'data' => $page
        ], 201);
    }

    public function show(Request $request, $pageid)
    {
        $userId = $request->owner_id;

        $page = Page::where('id', $pageid)
            ->where('owner_id', $userId)
            ->first();

        if (!$page) {
            return response()->json(['message' => 'Page not found'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $page
        ]);
    }

    public function deletePageFields(Request $request, $pageId)
    {
        // Check if at least one field is requested for deletion
        if (
            !$request->filled('page_profile_photo') &&
            !$request->filled('page_cover_photo')
        ) {
            return response()->json([
                'message' => 'No field provided for deletion',
            ], 422);
        }

        try {
            // Get the group created by the current user
            $page = Page::where('id', $pageId)
                ->where('owner_id', Auth::id())
                ->first();

            if (!$page) {
                return response()->json(['message' => 'Page not found'], 404);
            }

            $deletedFields = [];

            // Delete profile photo if requested
            if ($request->filled('page_profile_photo') && $request->input('page_profile_photo') === 'delete') {
                if ($page->page_profile_photo && Storage::disk('public')->exists($page->page_profile_photo)) {
                    Storage::disk('public')->delete($page->page_profile_photo);
                }
                $page->page_profile_photo = null;
                $deletedFields[] = 'page_profile_photo';
            }

            // Delete banner image if requested
            if ($request->filled('page_cover_photo') && $request->input('page_cover_photo') === 'delete') {
                if ($page->page_cover_photo && Storage::disk('public')->exists($page->page_cover_photo)) {
                    Storage::disk('public')->delete($page->page_cover_photo);
                }
                $page->page_cover_photo = null;
                $deletedFields[] = 'page_cover_photo';
            }

            // Save changes
            $page->save();

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

    public function update(Request $request, $pageid)
    {

        $page = Page::where('id', $pageid)
            ->where('owner_id', auth()->id())
            ->first();

        if (!$page) {
            return response()->json(['message' => 'No page found to update'], 404);
        }

        $validated = $request->validate([
            'page_name' => 'sometimes|required|string|max:255',
            'page_location' => 'nullable|string|max:255',
            'page_description' => 'nullable|string',
            'page_category' => 'nullable|string|max:255',
            'page_type' => 'nullable|string|max:255',
            'page_profile_photo' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            'page_cover_photo' => 'nullable|image|mimes:jpg,jpeg,png|max:4096',
        ]);

        // Handle profile photo
        if ($request->hasFile('page_profile_photo')) {
            if ($page->page_profile_photo) {
                Storage::disk('public')->delete($page->page_profile_photo);
            }
            $validated['page_profile_photo'] = $request->file('page_profile_photo')->store('pages/profile_photos', 'public');
        }

        // Handle banner image
        if ($request->hasFile('page_cover_photo')) {
            if ($page->page_cover_photo) {
                Storage::disk('public')->delete($page->page_cover_photo);
            }
            $validated['page_cover_photo'] = $request->file('page_cover_photo')->store('pages/cover_photo', 'public');
        }

        $page->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'page updated successfully.',
            'data' => $page,
        ]);
    }

    public function fetch()
    {
        $data = Page::where('owner_id', auth()->id())->get();

        if (!$data) {
            return response()->json(['message' => 'No page found'], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'pages Found SuccessFully.',
            'data' => $data,
        ]);
    }
    public function storetext(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'content' => 'required|string|max:1000',
            'type' => 'required|string|in:text,image,video,poll',
            'visibility' => 'required|string|in:public,private,friends',
            'page_id' => 'required|integer|exists:pages,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $page = Page::find($request->page_id);

        if (!$page || $page->owner_id !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to post on this page'
            ], 403);
        }

        $post = Post::create([
            'page_id' => $request->page_id,
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
            'page_id' => 'required|integer|exists:pages,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $page = Page::find($request->page_id);

        if (!$page || $page->owner_id !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to post on this page'
            ], 403);
        }

        // Create the post
        $post = Post::create([
            'page_id' => $request->page_id,
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
            'page_id' => 'required|integer|exists:pages,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $page = Page::find($request->page_id);

        if (!$page || $page->owner_id !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to post on this page'
            ], 403);
        }

        // Create the post
        $post = Post::create([
            'page_id' => $request->page_id,
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
            'page_id' => 'required|integer|exists:pages,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $page = Page::find($request->page_id);

        if (!$page || $page->owner_id !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to post on this page'
            ], 403);
        }

        // Create the post
        $post = Post::create([
            'page_id' => $request->page_id,
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

    public function getAllPosts(Request $request)
    {
        $currentUserId = auth()->id();
        $debugMessages = [];

        // Get already fetched IDs from request
        $fetchedPostIds = $request->input('already_fetched_ids', []);
        $debugMessages[] = 'Fetched from request: ' . json_encode($fetchedPostIds);

        // Original query for other users' posts
        $postsToReturn = Post::with(['page.owner.profile', 'media', 'poll'])
            ->where('page_id', $request->page_id)
            ->whereNotIn('id', $fetchedPostIds)
            ->inRandomOrder()
            ->take(3)
            ->get();

        Log::debug("post to return " . $postsToReturn);
        // 🆕 NEW: Combine posts - recent user posts first, then others
        // $allPosts = $recentUserPosts->concat($postsToReturn);
        $allPosts = $postsToReturn;

        // $debugMessages[] = 'Recent user posts: ' . $recentUserPosts->count();
        $debugMessages[] = 'Other posts returned: ' . $postsToReturn->count();

        // Prepare response
        $response = [
            'text_posts' => [],
            'image_posts' => [],
            'video_posts' => [],
            'poll_posts' => [],
            'fetched_ids' => [],
            'debug' => $debugMessages
        ];

        // 🆕 Optimized: Get all reactions in ONE query
        $postIds = $allPosts->pluck('id');
        $allReactions = Like::whereIn('post_id', $postIds)
            ->selectRaw('post_id, reaction_type, COUNT(*) as count')
            ->groupBy('post_id', 'reaction_type')
            ->get()
            ->groupBy('post_id');

        $userReactions = Like::whereIn('post_id', $postIds)
            ->where('user_id', $currentUserId)
            ->pluck('reaction_type', 'post_id');

        // Loop through all posts
        foreach ($allPosts as $post) {
            // 🆕 Get reaction counts for this post
            $reactionData = isset($allReactions[$post->id]) ? $allReactions[$post->id] : collect();
            $reactionsCount = $reactionData->pluck('count', 'reaction_type');
            $totalReactions = $reactionsCount->sum();
            $userReaction = $userReactions[$post->id] ?? null;

            $postData = [
                'id' => $post->id,
                'user_id' => $post->page->owner_id,
                'page_id' => $post->page_id,
                'group_id' => $post->group_id,
                'content' => $post->content,
                'type' => $post->type,
                'visibility' => $post->visibility,
                'created_at' => $post->created_at,
                'updated_at' => $post->updated_at,
                'page' => [
                    'id' => $post->page->id ?? null,
                    'name' => $post->page->page_name ?? null,
                    'username' => $post->page->username ?? null,
                    'avatar' => $post->page->page_profile_photo ?? null,
                    'cover_photo' => $post->page->page_cover_photo ?? null,
                    'description' => $post->page->page_description ?? null,
                    'category' => $post->page->page_category ?? null,
                    'location' => $post->page->page_location ?? null,
                    'type' => $post->page->page_type ?? null,
                    'owner_id' => $post->page->owner_id ?? null,
                ],
                'user' => [
                    'id' => $post->page->owner->id ?? null,
                    'name' => $post->page->owner->name ?? null,
                    'email' => $post->page->owner->email ?? null,
                    'profile_photo' => $post->page->owner->profile->profile_photo ?? null,
                ],

                'is_page_owner' => ($post->page->owner_id ?? null) === $currentUserId,

                // 🆕 Added Reactions Info
                'reactions_count' => $reactionsCount,
                'total_reactions' => $totalReactions,
                'current_user_reaction' => $userReaction
            ];

            $response['fetched_ids'][] = $post->id;

            // Handle poll posts
            if ($post->type === 'poll' && $post->poll) {
                $pollData = $postData;
                $pollData['poll'] = [
                    'id' => $post->poll->id,
                    'question' => $post->poll->question,
                    'options' => json_decode($post->poll->options, true),
                    'created_at' => $post->poll->created_at,
                    'updated_at' => $post->poll->updated_at,
                ];
                $response['poll_posts'][] = $pollData;
            }
            // Handle media posts (image/video)
            elseif (!$post->media->isEmpty()) {
                foreach ($post->media as $media) {
                    $mediaPost = $postData;
                    $mediaPost['media'] = [
                        'id' => $media->id,
                        'type' => $media->type,
                        'file' => $media->file,
                    ];
                    if ($media->type === 'image') {
                        $response['image_posts'][] = $mediaPost;
                    } elseif ($media->type === 'video') {
                        $response['video_posts'][] = $mediaPost;
                    }
                }
            }
            // Handle text posts
            else {
                $response['text_posts'][] = $postData;
            }
        }

        return response()->json($response);
    }

    public function storereaction(Request $request)
    {
        $validated = $request->validate([
            'post_id' => 'required|exists:posts,id',
            'reaction_type' => 'required|string|max:50',
        ]);

        $userId = auth()->id();

        $reaction = Like::updateOrCreate(
            [
                'post_id' => $validated['post_id'],
                'user_id' => $userId,
            ],
            [
                'reaction_type' => $validated['reaction_type'],
            ]
        );

        return response()->json([
            'message' => 'Reaction stored successfully',
            'data' => $reaction
        ], 201);
    }

    // public function UpdatePost(Request $request)
    // {
    //     $validator = Validator::make($request->all(), [
    //         'content' => 'required|string|max:1000',
    //         'type' => 'required|string|in:text,image,video,poll',
    //         'visibility' => 'required|string|in:public,private,friends',
    //         'post_id' => 'required|integer|exists:posts,id',
    //         'page_id' => 'required|integer|exists:pages,id',
    //         'poll_question' => 'required_if:type,poll|string|max:255',
    //         'poll_options' => 'required_if:type,poll|array|min:2',
    //         'poll_options.*' => 'string|max:255',
    //     ]);

    //     if ($validator->fails()) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Validation failed',
    //             'errors' => $validator->errors()
    //         ], 422);
    //     }

    //     $page = Page::find($request->page_id);

    //     if (!$page || $page->owner_id !== auth()->id()) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Unauthorized to post on this page'
    //         ], 403);
    //     }

    //     if ($request->type === "poll") {
    //         $postData = Post::find($request->post_id);
    //         $pollData = Poll::where('post_id', $request->post_id)->first();

    //         if ($postData->page_id === $request->page_id && $pollData->post_id === $request->post_id) {
    //             $postData->content = $request->content;
    //             $pollData->question = $request->question;
    //             $pollData->options = json_encode($request->options);
    //             $postData->save();
    //             $pollData->save();

    //             return response()->json([
    //                 'success' => true,
    //                 'message' => 'Post updated successfully',
    //                 'data' => [
    //                     'post' => $postData,
    //                     'poll' => $pollData,
    //                 ]
    //             ], 201);
    //         }

    //     } else {
    //         $postData = Post::find($request->post_id);

    //         if ($postData->page_id === $request->page_id) {
    //             $postData->content = $request->content;
    //             $postData->save();

    //             return response()->json([
    //                 'success' => true,
    //                 'message' => 'Post updated successfully',
    //                 'data' => [
    //                     'post' => $postData,
    //                 ]
    //             ], 201);
    //         }
    //     }



    //     return response()->json([
    //         'success' => false,
    //         'message' => 'Error updating',
    //         'data' => [
    //             'post' => Null,
    //         ]
    //     ], 500);
    // }


    public function UpdatePost(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'content' => 'required|string|max:1000',
            'post_id' => 'required|integer|exists:posts,id',
            'page_id' => 'required|integer|exists:pages,id',
            'poll_question' => 'required_if:type,poll|string|max:255',
            'poll_options' => 'required_if:type,poll|array|min:2',
            'poll_options.*' => 'string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Check page ownership
        $page = Page::find($request->page_id);
        if (!$page || $page->owner_id !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to post on this page'
            ], 403);
        }

        // Find the post and verify it belongs to the page
        $post = Post::where('id', $request->post_id)
            ->where('page_id', $request->page_id)
            ->first();

        if (!$post) {
            return response()->json([
                'success' => false,
                'message' => 'Post not found or does not belong to this page'
            ], 404);
        }

        try {
            DB::beginTransaction();

            // Update post content
            if ($post->type === "poll") {
                $post->content = $request->poll_question;
            } else {
                $post->content = $request->content;
            }
            $post->save();

            // Handle poll type specifically
            if ($post->type === "poll") {
                $poll = Poll::where('post_id', $post->id)->first();

                if (!$poll) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Poll not found for this post'
                    ], 404);
                }

                $poll->question = $request->poll_question;
                $poll->options = json_encode($request->poll_options);
                $poll->save();
            }

            DB::commit();

            // Prepare response data
            $responseData = ['post' => $post];
            if ($post->type === "poll") {
                $responseData['poll'] = Poll::where('post_id', $post->id)->first();
            }

            return response()->json([
                'success' => true,
                'message' => 'Post updated successfully',
                'data' => $responseData
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Error updating post: ' . $e->getMessage(),
                'data' => ['post' => null]
            ], 500);
        }
    }

    public function getMedia(Request $request)
    {
        try {
            // Validate the request
            $validator = Validator::make($request->all(), [
                'pageId' => 'required|integer|exists:pages,id',
                'type' => 'required|string|in:photos,videos'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = auth()->user();
            $pageId = $request->input('pageId');
            $type = $request->input('type');

            // Check if user owns the page
            $page = Page::where('id', $pageId)->first();
            // ->where('owner_id', $user->id)

            if (!$page) {
                return response()->json([
                    'success' => false,
                    'message' => 'Page not found or unauthorized access'
                ], 404);
            }

            // Get posts with media for this page
            $posts = Post::where('page_id', $pageId)
                ->where('type', $type === 'photos' ? 'image' : 'video')
                ->with('media')
                ->get();

            $media = [];

            foreach ($posts as $post) {
                foreach ($post->media as $mediaItem) {
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

    public function getOwner($pageid)
    {

        // Specific group fetch karein jo current user ne banaya ho
        $page = Page::where('id', $pageid)->first();

        if (!$page) {
            return response()->json(['message' => 'Page not found'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $page->owner_id
        ]);
    }

}
