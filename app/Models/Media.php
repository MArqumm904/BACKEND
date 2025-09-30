<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Media extends Model
{
    use HasFactory;

    protected $table = 'media';

    protected $fillable = [
        'post_id',
        'type',
        'file',
    ];

    public function post()
    {
        return $this->belongsTo(Post::class);
    }
}
