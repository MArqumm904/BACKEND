<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    use HasFactory;

    protected $table = 'posts';
    protected $fillable = [
        'user_id',
        'page_id',
        'group_id',
        'content',
        'type',
        'visibility',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function media()
    {
        return $this->hasMany(Media::class);
    }

    public function page()
    {
        return $this->belongsTo(Page::class);
    }

    public function savedPosts()
    {
        return $this->hasMany(SavedPost::class, 'post_id');
    }

    public function poll()
    {
        return $this->hasOne(Poll::class);
    }
    public function stories()
    {
        return $this->hasMany(Story::class);
    }
}
