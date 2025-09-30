<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Post;
use App\Models\Poll;
use App\Models\Media;
use Illuminate\Support\Facades\Validator;

class PollController extends Controller
{
    public function storepoll(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'content' => 'required|string|max:1000',
            'type' => 'required|string|in:text,image,video,poll',
            'visibility' => 'required|string|in:public,private,friends',
            'question' => 'required_if:type,poll|string',
            'options' => 'required_if:type,poll|array|min:2' // expecting array of options
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
    
}
