<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GroupMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'group_id',
        'sender_id',
        'message',
        'type',
        'media_url',
        'media_path',
        'read_by',
    ];

    protected $casts = [
        'read_by' => 'array',
        'deleted_by' => 'array',
    ];

    public function group()
    {
        return $this->belongsTo(GroupChat::class, 'group_id');
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }
}
