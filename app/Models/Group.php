<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class Group extends Model
{
    use HasFactory;

    protected $fillable = [
        'group_name',
        'group_description',
        'group_created_by',
        'group_type',
        'group_industry',
        'group_history',
        'group_profile_photo',
        'group_banner_image',
        'location',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // Relationship with User who created the group
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Relationship with group members (you can add this later if needed)
    // public function members()
    // {
    //     return $this->belongsToMany(User::class, 'group_members', 'group_id', 'user_id');
    // }
}
