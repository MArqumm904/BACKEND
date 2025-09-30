<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Poll extends Model
{
    protected $fillable = ['post_id', 'question', 'options'];
    protected $table = 'polls';
    protected $casts = [
        'options' => 'array',
    ];

    public function post()
    {
        return $this->belongsTo(Post::class);
    }

    public function votes()
    {
        return $this->hasMany(PollVote::class);
    }
}
