<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PollVote extends Model
{
    protected $fillable = ['poll_id', 'user_id', 'option_index'];

    public function poll()
    {
        return $this->belongsTo(Poll::class);
    }
}
