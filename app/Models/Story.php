<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Story extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'type',
        'duration',
        'post_id',
        'media_path',
        'media_type',
        'x_position',
        'y_position',
        'background_color',
        'expires_at',
        'scale'
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public function texts()
    {
        return $this->hasMany(StoryText::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function post()
    {
        return $this->belongsTo(Post::class);
    }   
}