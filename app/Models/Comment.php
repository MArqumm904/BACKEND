<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Comment extends Model
{
    use HasFactory;

    protected $table = 'comments';
    public $timestamps = true;

    protected $fillable = [
        'post_id',
        'user_id',
        'parent_id',
        'content',
        'likes_count',
        'liked_by_users',
    ];

    // Relationship: Replies
    public function replies()
    {
        return $this->hasMany(Comment::class, 'parent_id')
            ->with('user');
    }

    // Relationship: User
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function post()
    {
        return $this->belongsTo(Post::class, 'post_id');
    }

    // Recursive delete
    public function deleteWithReplies()
    {
        foreach ($this->replies as $reply) {
            $reply->deleteWithReplies();
        }
        $this->delete();
    }
}
