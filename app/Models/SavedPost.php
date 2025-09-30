<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SavedPost extends Model
{
    use HasFactory;

    protected $table = 'saved_posts';
    public $timestamps = false;
    protected $fillable = [
        'user_id',
        'post_id',
        'created_at',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function post()
    {
        return $this->belongsTo(Post::class);
    }

    public function media()
    {
        return $this->hasMany(Media::class);
    }

    public function poll()
    {
        return $this->hasOne(Poll::class);
    }
}
