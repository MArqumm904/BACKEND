<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Page extends Model
{
    use HasFactory;

    protected $fillable = [
        'owner_id',
        'page_name',
        'page_description',
        'page_profile_photo',
        'page_cover_photo',
        'page_category',
        'page_location',
        'page_type',
    ];


    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }
}
