<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserCertification extends Model
{
    use HasFactory;

    public $timestamps = false;
    protected $table = 'user_certification';
    protected $fillable = [
        'user_id',
        'title',
        'organization',
        'start_year',
        'end_year',
        'description',
        'certificate_photo',
    ];
}
