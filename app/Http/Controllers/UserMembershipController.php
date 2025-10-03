<?php

namespace App\Http\Controllers;

use App\Models\UserMembership;
use Illuminate\Http\Request;
use App\Models\Page;
use App\Models\User;
use App\Models\Profile;
use App\Models\PageMembership;
use App\Models\MembershipDocument;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
class UserMembershipController extends Controller
{

    public function getUserPendingMemberships(Request $request)
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            $pendingMemberships = UserMembership::where('user_id', $user->id)
                ->whereIn('status', ['pending', 'company_approved'])
                ->with(['page', 'documents'])
                ->get();

            return response()->json([
                'success' => true,
                'data' => $pendingMemberships
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch pending memberships',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function getMembership($id)
    {
        try {
            $membership = UserMembership::with(['documents', 'page'])
                ->where('id', $id)
                ->first();

            if (!$membership) {
                return response()->json([
                    'success' => false,
                    'message' => 'Membership not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $membership
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch membership',
                'error' => $e->getMessage()
            ], 500);
        }
    }


   public function updateMembership(Request $request, $id)
    {
        DB::beginTransaction();
        
        try {
            $validator = Validator::make($request->all(), [
                'job_title' => 'required|string|max:255',
                'location' => 'required|string|max:255',
                'start_date' => 'required|date',
                'end_date' => 'nullable|date|after:start_date',
                'currently_working' => 'boolean',
                'responsibilities' => 'required|string',
                'confirmation_letter' => 'nullable|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:10240',
                'proof_document' => 'nullable|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:10240',
            ], [
                'currently_working.boolean' => 'The currently working field must be true or false.',
                'confirmation_letter.mimes' => 'Confirmation letter must be a PDF, JPG, PNG, DOC, or DOCX file.',
                'proof_document.mimes' => 'Proof document must be a PDF, JPG, PNG, DOC, or DOCX file.',
                'confirmation_letter.max' => 'Confirmation letter must not exceed 10MB.',
                'proof_document.max' => 'Proof document must not exceed 10MB.',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $membership = UserMembership::where('id', $id)->first();

            if (!$membership) {
                return response()->json([
                    'success' => false,
                    'message' => 'Membership not found'
                ], 404);
            }

            $currentlyWorking = filter_var($request->currently_working, FILTER_VALIDATE_BOOLEAN);

            $updateData = [
                'job_title' => $request->job_title,
                'location' => $request->location,
                'start_date' => $request->start_date,
                'currently_working' => $currentlyWorking,
                'responsibilities' => $request->responsibilities,
            ];

            if (!$currentlyWorking && $request->end_date) {
                $updateData['end_date'] = $request->end_date;
            } else {
                $updateData['end_date'] = null;
            }

            $membership->update($updateData);

            if ($request->hasFile('confirmation_letter')) {
                $confirmationLetter = $request->file('confirmation_letter');
                $confirmationLetterPath = $confirmationLetter->store('membership_documents', 'public');
                
                $document = MembershipDocument::where('membership_id', $membership->id)->first();
                if ($document) {
                    if ($document->confirmation_letter && Storage::disk('public')->exists($document->confirmation_letter)) {
                        Storage::disk('public')->delete($document->confirmation_letter);
                    }
                    
                    $document->update(['confirmation_letter' => $confirmationLetterPath]);
                } else {
                    MembershipDocument::create([
                        'membership_id' => $membership->id,
                        'confirmation_letter' => $confirmationLetterPath,
                        'proof_document' => null,
                        'uploaded_by_company' => null,
                        'status' => 'pending'
                    ]);
                }
            }

            if ($request->hasFile('proof_document')) {
                $proofDocument = $request->file('proof_document');
                $proofDocumentPath = $proofDocument->store('membership_documents', 'public');
                
                $document = MembershipDocument::where('membership_id', $membership->id)->first();
                if ($document) {
                    if ($document->proof_document && Storage::disk('public')->exists($document->proof_document)) {
                        Storage::disk('public')->delete($document->proof_document);
                    }
                    
                    $document->update(['proof_document' => $proofDocumentPath]);
                } else {
                    MembershipDocument::create([
                        'membership_id' => $membership->id,
                        'confirmation_letter' => null,
                        'proof_document' => $proofDocumentPath,
                        'uploaded_by_company' => null,
                        'status' => 'pending'
                    ]);
                }
            }

            DB::commit();

            $updatedMembership = UserMembership::with(['documents', 'page'])
                ->where('id', $id)
                ->first();

            return response()->json([
                'success' => true,
                'message' => 'Membership updated successfully',
                'data' => $updatedMembership
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update membership',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function getUserConfirmedMemberships(Request $request)
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            // Get UserMemberships with documents
            $userMemberships = UserMembership::where('user_id', $user->id)
                ->where('status', 'admin_verified')
                ->with([
                    'page:id,page_name',
                    'documents' => function ($query) {
                        $query->select('membership_id', 'confirmation_letter', 'proof_document');
                    }
                ])
                ->get();

            // Get PageMemberships with documents
            $pageMemberships = PageMembership::where('user_page_id', $user->id)
                ->where('status', 'company_approved')
                ->with([
                    'page:id,page_name',
                    'documents' => function ($query) {
                        $query->select('membership_id', 'confirmation_letter', 'proof_document');
                    }
                ])
                ->get()
                ->map(function ($membership) {
                    return [
                        'id' => $membership->id,
                        'page_id' => $membership->page_id,
                        'user_id' => $membership->user_page_id,
                        'company_name' => $membership->company_name,
                        'job_title' => $membership->job_title,
                        'location' => $membership->location,
                        'start_date' => $membership->start_date,
                        'end_date' => $membership->end_date,
                        'currently_working' => $membership->currently_working,
                        'responsibilities' => $membership->responsibilities,
                        'status' => $membership->status,
                        'created_at' => $membership->created_at,
                        'updated_at' => $membership->updated_at,
                        'page' => $membership->page,
                        'documents' => $membership->documents,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => [
                    'user_memberships' => $userMemberships,
                    'page_memberships' => $pageMemberships,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch confirmed memberships',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function RemoveMember(Request $request)
    {
        try {
            $request->validate([
                'page_id' => 'required|integer',
                'user_id' => 'required|integer',
            ]);

            $pageId = $request->input('page_id');
            $userId = $request->input('user_id');

            $membership = UserMembership::where('page_id', $pageId)
                ->where('user_id', $userId)
                ->first();

            if ($membership) {
                $membership->delete();

                return response()->json([
                    'success' => true,
                    'message' => 'Member removed successfully (from UserMembership)',
                ]);
            }

            $pageMembership = PageMembership::where('page_id', $pageId)
                ->where('user_page_id', $userId)
                ->first();

            if ($pageMembership) {
                $pageMembership->delete();

                return response()->json([
                    'success' => true,
                    'message' => 'Member removed successfully (from PageMembership)',
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Membership not found in both tables',
            ], 404);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error removing member: ' . $e->getMessage(),
            ], 500);
        }
    }


    public function getUserApprovedMemberships(Request $request)
    {
        $request->validate([
            'page_id' => 'required|integer|exists:pages,id'
        ]);

        $pageId = $request->input('page_id');

        $userMemberships = UserMembership::where('page_id', $pageId)
            ->where('status', 'admin_verified')
            ->get();

        $pageMemberships = PageMembership::where('page_id', $pageId)
            ->get();

        $members = [];

        foreach ($userMemberships as $membership) {
            $user = User::find($membership->user_id);

            if (!$user)
                continue;

            $profile = Profile::where('user_id', $user->id)->first();

            $members[] = [
                'membership_id' => $membership->id,
                'user_id' => $user->id,
                'name' => $user->name,
                'job_title' => $membership->job_title,
                'profile_photo' => $profile->profile_photo ?? null,
                'cover_photo' => $profile->cover_photo ?? null,
                'verified' => $profile->verified ?? false,
                'headline' => $profile->headline ?? null,
                'type' => 'user',
                'membership_type' => 'user_membership'
            ];
        }

        foreach ($pageMemberships as $membership) {
            $pageExists = Page::where('id', $membership->user_page_id)->exists();

            if ($pageExists) {
                if (
                    $membership->status === 'admin_verified' &&
                    $membership->page_id == $membership->is_member
                ) {
                    $page = Page::find($membership->user_page_id);
                    if ($page) {
                        $members[] = [
                            'membership_id' => $membership->id,
                            'user_id' => $page->id,
                            'name' => $page->page_name,
                            'job_title' => $membership->job_title,
                            'profile_photo' => $page->page_profile_photo ?? null,
                            'cover_photo' => $page->page_cover_photo ?? null,
                            'verified' => false,
                            'headline' => $page->page_description ?? null,
                            'type' => 'page',
                            'membership_type' => 'page_membership'
                        ];
                    }
                }
            } else {
                if ($membership->status === 'company_approved') {
                    $user = User::find($membership->user_page_id);

                    if ($user) {
                        $profile = Profile::where('user_id', $user->id)->first();

                        $members[] = [
                            'membership_id' => $membership->id,
                            'user_id' => $user->id,
                            'name' => $user->name,
                            'job_title' => $membership->job_title,
                            'profile_photo' => $profile->profile_photo ?? null,
                            'cover_photo' => $profile->cover_photo ?? null,
                            'verified' => $profile->verified ?? false,
                            'headline' => $profile->headline ?? null,
                            'type' => 'user',
                            'membership_type' => 'page_membership'
                        ];
                    }
                }
            }
        }

        return response()->json([
            'success' => true,
            'members' => $members
        ]);
    }

    public function getCompanyAffiliations(Request $request)
    {
        $pageId = $request->input('page_id');

        $memberships = PageMembership::where('user_page_id', $pageId)
            ->whereColumn('page_id', 'is_member')
            ->with([
                'page:id,page_profile_photo',
                'documents'
            ])
            ->get();

        return response()->json([
            'status' => true,
            'data' => $memberships
        ]);
    }


    public function getCompanies(Request $request)
    {

        $perPage = $request->input('per_page', 2);
        $page = $request->input('page', 1);

        $userId = auth()->id();

        // Get page IDs where current user has sent membership requests (only pending ones)
        $requestedPageIds = UserMembership::where('user_id', $userId)
            ->where('status', 'pending') // Only exclude pending requests
            ->pluck('page_id')
            ->toArray();

        // Get pages excluding the ones user has already sent requests to
        $pages = Page::whereNotIn('id', $requestedPageIds)
            ->paginate($perPage);

        return response()->json($pages);
    }


    public function getCompaniesandUsers(Request $request)
    {
        $request->validate([
            'page_id' => 'required|integer|exists:pages,id'
        ]);

        $pageId = $request->input('page_id');
        $perPage = $request->input('per_page', 10);
        $page = $request->input('page', 1);
        $userId = auth()->id();

        $requestedPageUserIds = PageMembership::where('page_id', $pageId)
            ->pluck('user_page_id')
            ->toArray();

        $excludedUserIds = UserMembership::where('page_id', $pageId)
            ->pluck('user_id')
            ->toArray();

        $pages = Page::whereNotIn('id', $requestedPageUserIds)
            ->paginate($perPage, ['*'], 'page_page', $page);

        $users = User::whereNotIn('id', $requestedPageUserIds)
            ->whereNotIn('id', $excludedUserIds)
            ->where('id', '!=', $userId)
            ->paginate($perPage, ['*'], 'user_page', $page);

        $response = [
            'pages' => [
                'data' => $pages->items(),
                'current_page' => $pages->currentPage(),
                'last_page' => $pages->lastPage(),
                'per_page' => $pages->perPage(),
                'total' => $pages->total(),
            ],
            'users' => [
                'data' => $users->items(),
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
            ]
        ];

        return response()->json($response);
    }



    public function requestMembership(Request $request)
    {
        try {
            // Validation
            $request->validate([
                'companyId' => 'required|integer|exists:pages,id',
                'companyName' => 'required|string|max:255',
                'jobTitle' => 'required|string|max:255',
                'location' => 'required|string|max:255',
                'startDate' => 'required|string|max:255',
                'endDate' => 'nullable|string|max:255',
                'currentlyWorking' => 'boolean',
                'responsibilities' => 'required|string',
            ]);

            $userId = auth()->id();
            $companyId = $request->companyId;

            // Check if user already has a pending or approved request for this company
            $existingRequest = UserMembership::where('user_id', $userId)
                ->where('page_id', $companyId)
                ->whereIn('status', ['pending', 'approved'])
                ->first();

            if ($existingRequest) {
                return response()->json([
                    'success' => false,
                    'message' => "You already have a {$existingRequest->status} request for this company"
                ], 409);
            }

            // Create membership request
            $membership = UserMembership::create([
                'user_id' => $userId,
                'page_id' => $companyId,
                'company_name' => $request->companyName,
                'job_title' => $request->jobTitle,
                'location' => $request->location,
                'start_date' => $request->startDate,
                'end_date' => $request->currentlyWorking ? null : $request->endDate,
                'currently_working' => $request->currentlyWorking ? 1 : 0,
                'responsibilities' => $request->responsibilities,
                'status' => 'pending'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Membership request submitted successfully',
                'data' => [
                    'membership_id' => $membership->id,
                    'status' => 'pending',
                    'company_name' => $request->companyName,
                    'page_id' => $companyId
                ]
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Something went wrong',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function requestMembershipCompanySide(Request $request)
    {
        try {
            $request->validate([
                'companyId' => 'required|integer|exists:pages,id',
                'companyName' => 'required|string|max:255',
                'user_page_id' => 'required|integer',
                'jobTitle' => 'required|string|max:255',
                'location' => 'required|string|max:255',
                'startDate' => 'required|string|max:255',
                'endDate' => 'nullable|string|max:255',
                'currentlyWorking' => 'boolean',
                'responsibilities' => 'required|string',

                'confirmation_letter' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
                'proof_document' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
            ]);

            $userId = auth()->id();
            $companyId = $request->companyId;
            $userPageId = $request->user_page_id;

            $pageExists = Page::where('id', $userPageId)->exists();

            $isMember = null;

            if ($pageExists) {
                $isMember = $companyId;
            }

            $membership = PageMembership::create([
                'page_id' => $companyId,
                'user_page_id' => $userPageId,
                'company_name' => $request->companyName,
                'job_title' => $request->jobTitle,
                'location' => $request->location,
                'start_date' => $request->startDate,
                'end_date' => $request->currentlyWorking ? null : $request->endDate,
                'currently_working' => $request->currentlyWorking ? 1 : 0,
                'responsibilities' => $request->responsibilities,
                'status' => 'pending',
                'is_member' => $isMember,
            ]);

            $confirmationLetterPath = null;
            if ($request->hasFile('confirmation_letter')) {
                $confirmationLetter = $request->file('confirmation_letter');
                $confirmationLetterName = time() . '_confirmation_' . $confirmationLetter->getClientOriginalName();
                $confirmationLetterPath = $confirmationLetter->storeAs('membership_documents', $confirmationLetterName, 'public');
            }

            $proofDocumentPath = null;
            if ($request->hasFile('proof_document')) {
                $proofDocument = $request->file('proof_document');
                $proofDocumentName = time() . '_proof_' . $proofDocument->getClientOriginalName();
                $proofDocumentPath = $proofDocument->storeAs('membership_documents', $proofDocumentName, 'public');
            }

            $membershipDocument = MembershipDocument::create([
                'membership_id' => $membership->id,
                'confirmation_letter' => $confirmationLetterPath,
                'proof_document' => $proofDocumentPath,
                'uploaded_by_company' => $companyId,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Membership request created successfully',
                'data' => [
                    'membership' => $membership,
                    'documents' => $membershipDocument
                ]
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Something went wrong',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function getCompanyInvitations(Request $request)
    {
        try {
            $request->validate([
                'page_id' => 'required|integer|exists:pages,id',
            ]);

            $pageId = $request->page_id;

            $memberships = PageMembership::where('user_page_id', $pageId)
                ->whereColumn('page_id', 'is_member')
                ->where('status', 'pending')
                ->with([
                    'page' => function ($query) {
                        $query->select('id', 'page_profile_photo');
                    }
                ])
                ->get();

            if ($memberships->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No pending invitations found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $memberships
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Something went wrong',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function approvedCompanyInvitations(Request $request)
    {
        try {
            $request->validate([
                'id' => 'required|integer|exists:company_membership,id',
            ]);

            $membership = PageMembership::findOrFail($request->id);
            $membership->status = 'company_approved';
            $membership->save();

            return response()->json([
                'success' => true,
                'message' => 'Invitation approved successfully',
                'data' => $membership
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Something went wrong',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function rejectCompanyInvitations(Request $request)
    {
        try {
            $request->validate([
                'id' => 'required|integer|exists:company_membership,id',
            ]);

            $membership = PageMembership::findOrFail($request->id);
            $membership->status = 'rejected';
            $membership->save();

            return response()->json([
                'success' => true,
                'message' => 'Invitation rejected successfully',
                'data' => $membership
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Something went wrong',
                'error' => $e->getMessage()
            ], 500);
        }
    }



    public function getUserMemberships(Request $request)
    {
        try {
            $userId = auth()->id();

            $ownedPageIds = Page::where('owner_id', $userId)->pluck('id');

            if ($ownedPageIds->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'message' => 'No memberships found because user does not own any pages',
                    'data' => []
                ], 200);
            }

            $memberships = UserMembership::with([
                'user:id,name,email',
                'user.profile:id,user_id,profile_photo,cover_photo,location,dob'
            ])
                ->select([
                    'id',
                    'user_id',
                    'page_id',
                    'company_name',
                    'job_title',
                    'location',
                    'start_date',
                    'end_date',
                    'currently_working',
                    'responsibilities',
                    'status',
                    'created_at',
                    'updated_at'
                ])
                ->whereIn('page_id', $ownedPageIds)
                ->where('status', 'pending')
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Pending user memberships retrieved successfully',
                'data' => $memberships
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Something went wrong',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function getCompaniesMemberships(Request $request)
    {
        try {
            $userId = auth()->id();

            // $ownedPageIds = Page::where('owner_id', $userId)->pluck('id');

            // if ($ownedPageIds->isEmpty()) {
            //     return response()->json([
            //         'success' => true,
            //         'message' => 'No memberships found because user does not own any pages',
            //         'data' => []
            //     ], 200);
            // }

            $memberships = PageMembership::with([
                'user:id,name,email',
                'user.profile:id,user_id,profile_photo,cover_photo,location,dob',
                'documents',
                'page:id,page_name'
            ])
                ->select([
                    'id',
                    'user_page_id',
                    'page_id',
                    'company_name',
                    'job_title',
                    'location',
                    'start_date',
                    'end_date',
                    'currently_working',
                    'responsibilities',
                    'status',
                    'created_at',
                    'updated_at'
                ])
                ->where('user_page_id', $userId)
                ->where('status', 'pending')
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Pending user memberships retrieved successfully',
                'data' => $memberships
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Something went wrong',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function membershipstatususerside(Request $request)
    {
        // Validate the request
        $validator = Validator::make($request->all(), [
            'user_page_id' => 'required|integer',
            'page_id' => 'required|integer',
            'status' => 'required|in:company_approved,rejected',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Find the membership record
            $membership = PageMembership::where('user_page_id', $request->user_page_id)
                ->where('page_id', $request->page_id)
                ->first();

            if (!$membership) {
                return response()->json([
                    'success' => false,
                    'message' => 'Membership record not found'
                ], 404);
            }

            // Update the status
            $membership->status = $request->status;
            $membership->save();

            return response()->json([
                'success' => true,
                'message' => 'Membership status updated successfully',
                'data' => $membership
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update membership status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function storecompaniesresponses(Request $request)
    {
        try {
            // Validation
            $request->validate([
                'membership_id' => 'required|exists:user_membership,id',
                'confirmation_letter' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
                'proof_document' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
            ]);

            // Get current authenticated user
            $currentUserId = Auth::id();

            if (!$currentUserId) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            // Find the page owned by current user
            $userPage = Page::where('owner_id', $currentUserId)->first();

            if (!$userPage) {
                return response()->json([
                    'success' => false,
                    'message' => 'No page found for current user'
                ], 404);
            }

            // Check membership_id
            $userMembership = UserMembership::find($request->membership_id);

            if (!$userMembership) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid membership ID'
                ], 404);
            }

            // Upload confirmation letter
            $confirmationLetterPath = null;
            if ($request->hasFile('confirmation_letter')) {
                $confirmationLetter = $request->file('confirmation_letter');
                $confirmationLetterName = time() . '_confirmation_' . $confirmationLetter->getClientOriginalName();
                $confirmationLetterPath = $confirmationLetter->storeAs('membership_documents', $confirmationLetterName, 'public');
            }

            // Upload proof document
            $proofDocumentPath = null;
            if ($request->hasFile('proof_document')) {
                $proofDocument = $request->file('proof_document');
                $proofDocumentName = time() . '_proof_' . $proofDocument->getClientOriginalName();
                $proofDocumentPath = $proofDocument->storeAs('membership_documents', $proofDocumentName, 'public');
            }

            // Insert record into membership_documents (status field removed)
            $membershipDocument = MembershipDocument::create([
                'membership_id' => $request->membership_id,
                'confirmation_letter' => $confirmationLetterPath,
                'proof_document' => $proofDocumentPath,
                'uploaded_by_company' => $userPage->id,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // Update user_membership status to "company_approved"
            $userMembership->status = 'company_approved';
            $userMembership->save();

            return response()->json([
                'success' => true,
                'message' => 'Documents uploaded successfully and membership updated to company_approved',
                'data' => $membershipDocument
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred: ' . $e->getMessage()
            ], 500);
        }
    }

    // Method to cancel/remove a pending membership request                  I DONT USE IT
    public function cancelMembershipRequest(Request $request)
    {
        try {
            // Validation
            $request->validate([
                'membership_id' => 'required|integer|exists:user_memberships,id'
            ]);

            $userId = auth()->id();
            $membershipId = $request->membership_id;

            // Check if the membership request belongs to the authenticated user and is pending
            $membership = UserMembership::where('id', $membershipId)
                ->where('user_id', $userId)
                ->where('status', 'pending')
                ->first();

            if (!$membership) {
                return response()->json([
                    'success' => false,
                    'message' => 'Membership request not found or cannot be cancelled'
                ], 404);
            }

            // Delete the membership request
            $membership->delete();

            return response()->json([
                'success' => true,
                'message' => 'Membership request cancelled successfully',
                'data' => [
                    'membership_id' => $membershipId,
                    'company_name' => $membership->company_name
                ]
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Something went wrong',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function cancelmembership(Request $request)
    {
        try {
            $validated = $request->validate([
                'user_id' => 'required|integer',
                'page_id' => 'required|integer',
            ]);

            $membership = UserMembership::where('user_id', $validated['user_id'])
                ->where('page_id', $validated['page_id'])
                ->first();

            if (!$membership) {
                return response()->json([
                    'success' => false,
                    'message' => 'Membership not found'
                ], 404);
            }

            $membership->status = 'rejected';
            $membership->save();

            return response()->json([
                'success' => true,
                'message' => 'Membership request rejected successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to reject membership: ' . $e->getMessage()
            ], 500);
        }
    }
}
