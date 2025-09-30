<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StoryText extends Model
{
    use HasFactory;

    protected $table = 'story_texts';

    protected $fillable = [
        'story_id',
        'text_content',
        'x_position',
        'y_position',
        'font_size',
        'font_color',
        'font_weight',
        'order_index',
    ];

    public function story()
    {
        return $this->belongsTo(Story::class);

    }
}