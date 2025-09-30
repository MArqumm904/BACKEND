<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class reelSave extends Model
{
    use HasFactory;

    protected $table = 'reel_saves';

    protected $fillable = [
        'reel_id',
        'user_id'
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
    public function reel()
    {
        return $this->belongsTo(Reel::class, 'reel_id');
    }
}
