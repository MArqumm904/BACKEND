<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PageMembership extends Model
{
    use HasFactory;
    
    protected $table = 'company_membership';

    protected $fillable = [
        'page_id',
        'user_page_id',
        'company_name',
        'job_title',
        'location',
        'start_date',
        'end_date',
        'currently_working',
        'responsibilities',
        'status',
        'is_member'
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'currently_working' => 'boolean',
    ];

    public function page()
    {
        return $this->belongsTo(Page::class, 'page_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_page_id');
    }

    public function documents()
    {
        return $this->hasMany(MembershipDocument::class, 'membership_id');
    }
}
