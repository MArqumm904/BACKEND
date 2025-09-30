<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserInfo extends Model
{
    use HasFactory;
    public $timestamps = false;
    protected $table = 'user_info';
    protected $fillable = [
        'user_id',
        'email',
        'contact',
        'date_of_birth',
    ];
}
