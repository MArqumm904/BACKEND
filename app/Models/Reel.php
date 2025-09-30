<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Reel extends Model
{
    use HasFactory;

    protected $table = 'reels';
    protected $fillable = [
        'user_id',
        'description',
        'tags',
        'video_file',
        'thumbnail',
        'views',
        'likes',
        'comments_count',
        'visibility',
        'created_at'
    ];
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function likes()
    {
        return $this->hasMany(\App\Models\ReelLike::class, 'reel_id');
    }

    public function saves()
{
    return $this->hasMany(\App\Models\ReelSave::class, 'reel_id');
}
}
