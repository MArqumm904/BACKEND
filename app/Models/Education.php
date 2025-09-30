<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Education extends Model
{
    use HasFactory;
    public $timestamps = false;
    protected $table = 'user_education';
    protected $fillable = [
        'user_id',
        'schooluniname',
        'qualification',
        'field_of_study',
        'location',
        'start_year',
        'end_year',
        'description',
    ];
}
