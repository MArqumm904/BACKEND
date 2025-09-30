<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MembershipDocument extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'membership_documents';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'membership_id',
        'confirmation_letter',
        'proof_document',
        'uploaded_by_company',
        'status',
        'created_at',
        'updated_at'
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the user membership that owns the document.
     */
    public function userMembership()
    {
        return $this->belongsTo(UserMembership::class, 'membership_id');
    }

    public function pageMembership()
    {
        return $this->belongsTo(PageMembership::class, 'membership_id');
    }

    public function page()
    {
        return $this->belongsTo(Page::class, 'uploaded_by_company');
    }
}
