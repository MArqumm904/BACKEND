<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Post;
use App\Models\SavedPost;
use App\Models\Media;
use App\Models\Comment;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Session;
use App\Models\Like;
use Illuminate\Support\Facades\Storage;


class PostController extends Controller
{

    public function storetext(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'content' => 'required|string|max:1000',
            'type' => 'required|string|in:text,image,video,poll',
            'visibility' => 'required|string|in:public,private,friends',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $post = Post::create([
            'user_id' => auth()->id(),
            'content' => $request->content,
            'type' => $request->type,
            'visibility' => $request->visibility,
        ]);

        $post->load('user');

        $user = auth()->user();

        return response()->json([
            'success' => true,
            'message' => 'Post created successfully',
            'data' => [
                'post' => $post,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email
                ]
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
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Create the post
        $post = Post::create([
            'user_id' => auth()->id(),
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


        $post->load('user', 'media');

        $user = auth()->user();

        return response()->json([
            'success' => true,
            'message' => 'Post created successfully',
            'data' => [
                'post' => $post,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email
                ]
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
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Create the post
        $post = Post::create([
            'user_id' => auth()->id(),
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

        $post->load('user', 'media');

        $user = auth()->user();

        return response()->json([
            'success' => true,
            'message' => 'Video post created successfully',
            'data' => [
                'post' => $post,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email
                ]
            ]
        ], 201);
    }

    public function getAllPosts(Request $request)
    {
        $currentUserId = auth()->id();
        $debugMessages = [];

        $fetchedPostIds = $request->input('already_fetched_ids', []);
        $debugMessages[] = 'Fetched from request: ' . json_encode($fetchedPostIds);

        // Eager load user.profile for both queries
        $recentUserPosts = Post::with(['user.profile', 'media', 'poll'])
            ->where('user_id', $currentUserId)
            ->where('created_at', '>=', now()->subSeconds(30))
            ->whereNotIn('id', $fetchedPostIds)
            ->orderBy('created_at', 'desc')
            ->get();

        $postsToReturn = Post::with(['user.profile', 'media', 'poll'])
            ->where('user_id', '!=', $currentUserId)
            ->whereNotIn('id', $fetchedPostIds)
            ->inRandomOrder()
            ->take(3)
            ->get();

        $allPosts = $recentUserPosts->concat($postsToReturn);

        $debugMessages[] = 'Recent user posts: ' . $recentUserPosts->count();
        $debugMessages[] = 'Other posts returned: ' . $postsToReturn->count();

        $postIds = $allPosts->pluck('id');

        $commentsCount = Comment::whereIn('post_id', $postIds)
            ->selectRaw('post_id, COUNT(*) as count')
            ->groupBy('post_id')
            ->pluck('count', 'post_id');

        $allReactions = Like::whereIn('post_id', $postIds)
            ->selectRaw('post_id, reaction_type, COUNT(*) as count')
            ->groupBy('post_id', 'reaction_type')
            ->get()
            ->groupBy('post_id');

        $userReactions = Like::whereIn('post_id', $postIds)
            ->where('user_id', $currentUserId)
            ->pluck('reaction_type', 'post_id');

        $response = [
            'text_posts' => [],
            'image_posts' => [],
            'video_posts' => [],
            'poll_posts' => [],
            'fetched_ids' => [],
            'debug' => $debugMessages
        ];

        foreach ($allPosts as $post) {
            $reactionData = isset($allReactions[$post->id]) ? $allReactions[$post->id] : collect();
            $reactionsCount = $reactionData->pluck('count', 'reaction_type');
            $totalReactions = $reactionsCount->sum();
            $userReaction = $userReactions[$post->id] ?? null;

            $postCommentsCount = $commentsCount[$post->id] ?? 0;

            // Format user data to include profile photo
            $userData = [
                'id' => $post->user->id,
                'name' => $post->user->name,
                'email' => $post->user->email,
                'phone' => $post->user->phone,
                'created_at' => $post->user->created_at,
                'updated_at' => $post->user->updated_at,
                'profile_photo' => $post->user->profile ? $post->user->profile->profile_photo : null,
                // Add other profile fields if needed
                // 'cover_photo' => $post->user->profile ? $post->user->profile->cover_photo : null,
                // 'location' => $post->user->profile ? $post->user->profile->location : null,
            ];

            $postData = [
                'id' => $post->id,
                'user_id' => $post->user_id,
                'page_id' => $post->page_id,
                'group_id' => $post->group_id,
                'content' => $post->content,
                'type' => $post->type,
                'visibility' => $post->visibility,
                'created_at' => $post->created_at,
                'updated_at' => $post->updated_at,
                'user' => $userData, // Use the formatted user data
                'is_current_user' => $post->user_id === $currentUserId,

                'reactions_count' => $reactionsCount,
                'total_reactions' => $totalReactions,
                'current_user_reaction' => $userReaction,
                'comments_count' => $postCommentsCount
            ];

            $response['fetched_ids'][] = $post->id;

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
            } elseif (!$post->media->isEmpty()) {
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
            } else {
                $response['text_posts'][] = $postData;
            }
        }

        return response()->json($response);
    }
    public function getauthenticatedPosts(Request $request)
    {
        $currentUserId = $request->user_id;
        $debugMessages = [];

        $fetchedPostIds = $request->input('already_fetched_ids', []);
        $debugMessages[] = 'Fetched from request: ' . json_encode($fetchedPostIds);

        $postsToReturn = Post::with(['user.profile', 'media'])
            ->where('user_id', $currentUserId) // Only current user's posts
            ->whereNotIn('id', $fetchedPostIds)
            ->inRandomOrder()
            ->take(3)
            ->get();

        $debugMessages[] = 'Posts returned: ' . $postsToReturn->count();

        $response = [
            'text_posts' => [],
            'image_posts' => [],
            'video_posts' => [],
            'poll_posts' => [],
            'fetched_ids' => [],
            'debug' => $debugMessages
        ];

        foreach ($postsToReturn as $post) {
            $postData = [
                'id' => $post->id,
                'user_id' => $post->user_id,
                'page_id' => $post->page_id,
                'group_id' => $post->group_id,
                'content' => $post->content,
                'visibility' => $post->visibility,
                'created_at' => $post->created_at,
                'updated_at' => $post->updated_at,
                'user' => $post->user,
            ];

            $response['fetched_ids'][] = $post->id;

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
            } else if ($post->media->isEmpty()) {
                $response['text_posts'][] = $postData;
            } else {
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
        }

        return response()->json($response);
    }

    public function getcomments(Request $request)
    {
        $postId = $request->input('post_id');
        $userId = auth()->id();

        // Pehle check karo post saved hai ya nahi
        $isSaved = SavedPost::where('post_id', $postId)
            ->where('user_id', $userId)
            ->exists();

        // Root comments (parent_id = null)
        $comments = Comment::where('post_id', $postId)
            ->whereNull('parent_id')
            ->with([
                    'user:id,name,email',
                    'replies.user:id,name'
                ])
            ->orderBy('created_at', 'desc')
            ->get();

        // Har comment me saved_post add karo
        $comments->transform(function ($comment) use ($isSaved) {
            $comment->saved_post = $isSaved ? 'saved' : 'not_saved';
            return $comment;
        });

        // Agar comments empty hain to bhi saved_post ka status wapas bhejo
        if ($comments->isEmpty()) {
            return response()->json([
                'success' => true,
                'data' => [],
                'saved_post' => $isSaved ? 'saved' : 'not_saved'
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $comments
        ]);
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

    public function storecomment(Request $request)
    {
        // Get authenticated user ID
        $userId = auth()->id(); // or Auth::id()

        // Validation (no need to validate user_id anymore)
        $request->validate([
            'post_id' => 'required|integer',
            'content' => 'required|string',
            'parent_id' => 'nullable|integer'
        ]);

        // Create new comment
        $comment = Comment::create([
            'post_id' => $request->post_id,
            'user_id' => $userId,
            'parent_id' => $request->parent_id, // null if root comment
            'content' => $request->content,
            'likes_count' => 0
        ]);

        $comment->load('user:id,name');

        return response()->json([
            'success' => true,
            'message' => 'Comment added successfully',
            'data' => $comment
        ]);
    }

    public function storereply(Request $request)
    {
        // Validation
        $request->validate([
            'post_id' => 'required|integer',
            'user_id' => 'required|integer',
            'parent_id' => 'required|integer',
            'content' => 'required|string'
        ]);

        // Reply create
        $reply = Comment::create([
            'post_id' => $request->post_id,
            'user_id' => $request->user_id,
            'parent_id' => $request->parent_id,
            'content' => $request->content,
            'likes_count' => 0
        ]);

        // Reply ka user load karke bhej do
        $reply->load('user:id,name');

        return response()->json([
            'success' => true,
            'message' => 'Reply added successfully',
            'data' => $reply
        ]);
    }

    public function likeacomment(Request $request)
    {
        try {
            $request->validate([
                'comment_id' => 'required|integer|exists:comments,id'
            ]);

            $commentId = $request->comment_id;
            $userId = auth()->id();

            $comment = Comment::find($commentId);

            if (!$comment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Comment not found'
                ], 404);
            }

            $likedByUsers = $comment->liked_by_users ? json_decode($comment->liked_by_users, true) : [];

            if (($key = array_search($userId, $likedByUsers)) !== false) {
                unset($likedByUsers[$key]);
                $likedByUsers = array_values($likedByUsers);
                $comment->decrement('likes_count');
                $action = 'unliked';
            } else {
                $likedByUsers[] = $userId;
                $comment->increment('likes_count');
                $action = 'liked';
            }

            $comment->update([
                'liked_by_users' => json_encode($likedByUsers)
            ]);

            return response()->json([
                'success' => true,
                'message' => "Comment {$action} successfully",
                'data' => [
                        'comment_id' => $commentId,
                        'new_likes_count' => $comment->likes_count,
                        'user_liked' => $action === 'liked',
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

    public function savedapost(Request $request)
    {
        try {
            // Validation
            $request->validate([
                'post_id' => 'required|integer'
            ]);

            $userId = auth()->id();
            $postId = $request->post_id;

            // Check if already saved
            $savedPost = SavedPost::where('user_id', $userId)
                ->where('post_id', $postId)
                ->first();

            if ($savedPost) {
                // If exists → delete
                $savedPost->delete();
                $action = 'unsaved';
            } else {
                // If not exists → create
                SavedPost::create([
                    'user_id' => $userId,
                    'post_id' => $postId,
                    'created_at' => now(),
                ]);
                $action = 'saved';
            }

            return response()->json([
                'success' => true,
                'message' => "Post {$action} successfully",
                'data' => [
                        'post_id' => $postId,
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

    public function getSavedPosts(Request $request)
    {
        $currentUserId = auth()->id();

        // 1️⃣ Fetch saved_posts with post and saved_by_user relationships
        $savedPosts = SavedPost::with([
            'post.user',    // post ka owner
            'post.media',   // post ka media
            'post.poll',    // poll data agar hai
            'user'          // jis user ne save kiya
        ])
            ->where('user_id', $currentUserId)
            ->orderBy('created_at', 'desc')
            ->get();

        // 2️⃣ Prepare response
        $response = [];

        foreach ($savedPosts as $savedPost) {
            $post = $savedPost->post;

            // Reactions
            $allReactions = Like::where('post_id', $post->id)
                ->selectRaw('reaction_type, COUNT(*) as count')
                ->groupBy('reaction_type')
                ->pluck('count', 'reaction_type');

            $userReaction = Like::where('post_id', $post->id)
                ->where('user_id', $currentUserId)
                ->value('reaction_type');

            $response[] = [
                'saved_post' => [
                    'id' => $savedPost->id,
                    'saved_at' => $savedPost->created_at,
                    'saved_by' => $savedPost->user, // jis user ne save kiya
                ],
                'post' => [
                    'id' => $post->id,
                    'user' => $post->user, // post ka owner
                    'content' => $post->content,
                    'type' => $post->type,
                    'visibility' => $post->visibility,
                    'created_at' => $post->created_at,
                    'updated_at' => $post->updated_at,
                    'media' => $post->media,
                    'poll' => $post->poll,
                    'reactions_count' => $allReactions,
                    'total_reactions' => $allReactions->sum(),
                    'current_user_reaction' => $userReaction,
                    'is_current_user' => $post->user_id === $currentUserId
                ]
            ];
        }

        return response()->json($response);
    }

    public function show(Request $request, $postId)
    {
        $post = Post::with(['user.profile', 'media', 'poll'])->find($postId);
        if (!$post) {
            return response()->json(['message' => 'Post not found'], 404);
        }

        $base = [
            'id' => $post->id,
            'user_id' => $post->user_id,
            'content' => $post->content,
            'type' => $post->type,
            'created_at' => $post->created_at,
            'user' => [
                    'id' => optional($post->user)->id,
                    'name' => optional($post->user)->name,
                    'profile_photo' => data_get($post, 'user.profile.profile_photo'),
                ],
        ];

        if ($post->type === 'poll' && $post->poll) {
            $base['poll'] = [
                'id' => $post->poll->id,
                'question' => $post->poll->question,
                'options' => json_decode($post->poll->options, true),
            ];
        }

        if (!$post->media->isEmpty()) {
            $media = $post->media->first();
            $base['media'] = [
                'id' => $media->id,
                'type' => $media->type,
                'file' => $media->file,
            ];
        }

        return response()->json(['data' => $base]);
    }

    public function deletePost(Request $request, $postId)
    {
        try {
            $user = auth()->user();
            $post = Post::findOrFail($postId);

            // Check if the user owns the post
            if ($post->user_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: You can only delete your own posts'
                ], 403);
            }

            // Delete related data
            $this->deletePostRelatedData($post);

            // Delete the post itself
            $post->delete();

            return response()->json([
                'success' => true,
                'message' => 'Post deleted successfully'
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Post not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete post: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete all data related to a post
     */
    private function deletePostRelatedData(Post $post)
    {
        // Delete likes
        Like::where('post_id', $post->id)->delete();

        $comments = Comment::where('post_id', $post->id)->get();
        foreach ($comments as $comment) {
            $comment->deleteWithReplies();
        }

        if ($post->media->isNotEmpty()) {
            foreach ($post->media as $media) {
                if ($media->file && Storage::exists($media->file)) {
                    Storage::delete($media->file);
                }
                $media->delete();
            }
        }

        if ($post->poll) {
            $post->poll->delete();
        }

        \DB::table('saved_posts')->where('post_id', $post->id)->delete();
    }
}
