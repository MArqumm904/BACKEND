<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReelsComments extends Model
{
    use HasFactory;

    protected $table = 'reel_comments';
    public $timestamps = true;

    protected $fillable = [
        'reel_id',
        'user_id',
        'parent_id',
        'comment',
        'likes_count',
    ];

    // Relationship: Replies
    public function replies()
    {
        return $this->hasMany(ReelsComments::class, 'parent_id')
            ->with('user');
    }

    // Relationship: User
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function profile()
    {
        return $this->hasOne(Profile::class); 
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
