<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserOverview extends Model
{
    use HasFactory;
    public $timestamps = false;
    protected $table = 'user_overview';
    protected $fillable = [
        'user_id',
        'description',
    ];
}
