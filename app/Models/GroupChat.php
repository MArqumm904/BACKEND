<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GroupChat extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'photo',
        'created_by',
        'members'
    ];

    protected $casts = [
        'members' => 'array', // json → array
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function messages()
    {
        return $this->hasMany(GroupMessage::class, 'group_id');
    }
}
