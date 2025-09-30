<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use App\Models\Education;
use App\Models\UserCertification;
use App\Models\UserInfo;
use App\Models\UserOverview;
use App\Models\UserSkill;

class AboutController extends Controller
{
    // ================================== Create Education ==================================
    public function createEducation(Request $request)
    {
        $validated = $request->validate([
            'schooluniname' => 'required|string',
            'qualification' => 'required|string',
            'field_of_study' => 'required|string',
            'location' => 'required|string',
            'start_year' => 'required|integer',
            'end_year' => 'required|integer',
            'description' => 'required|string'
        ]);
        $validated['user_id'] = Auth::id();
        $education = Education::create($validated);
        return response()->json($education, 201);
    }

    public function getUserEducation($id)
    {
        $education = Education::where('user_id', $id)->get();
        if (!$education) {
            return response()->json(['message' => 'Education not found'], 404);
        }
        return response()->json($education);
    }

    public function updateUserEducation(Request $request, $educationId)
    {
        $education = Education::find($educationId);
        if (!$education) {
            return response()->json(['message' => 'Education not found'], 404);
        }

        if ($education->user_id != Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'schooluniname' => 'required|string',
            'qualification' => 'required|string',
            'field_of_study' => 'required|string',
            'location' => 'required|string',
            'start_year' => 'required|integer',
            'end_year' => 'required|integer',
            'description' => 'required|string'
        ]);

        $education->update($validated);

        return response()->json($education);
    }

    public function deleteUserEducation($educationId)
    {
        $education = Education::find($educationId);
        if (!$education) {
            return response()->json(['message' => 'Education not found'], 404);
        }
        if ($education->user_id != Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $education->delete();

        return response()->json(['message' => 'Education deleted successfully']);
    }

    // ================================== Create Certification ==================================
    public function createCertification(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string',
            'organization' => 'required|string',
            'start_year' => 'required|integer',
            'end_year' => 'required|integer',
            'description' => 'required|string',
            'certificate_photo' => 'required|image|mimes:jpeg,png,jpg|max:2048',
        ]);
        $imagePath = $request->file('certificate_photo')->store('certificates', 'public');
        $certification = UserCertification::create([
            'user_id' => Auth::id(),
            'title' => $validated['title'],
            'organization' => $validated['organization'],
            'start_year' => $validated['start_year'],
            'end_year' => $validated['end_year'],
            'description' => $validated['description'],
            'certificate_photo' => $imagePath,
        ]);
        return response()->json($certification, 201);
    }

    public function getUserCertification($id)
    {
        $certification = UserCertification::where('user_id', $id)->get();
        if (!$certification) {
            return response()->json(['message' => 'Certification not found'], 404);
        }
        return response()->json($certification);
    }

    public function updateUserCertification(Request $request, $certificationId)
    {
        $certification = UserCertification::find($certificationId);
        if (!$certification) {
            return response()->json(['message' => 'Certification not found'], 404);
        }
        if ($certification->user_id != Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'title' => 'required|string',
            'organization' => 'required|string',
            'start_year' => 'required|integer',
            'end_year' => 'required|integer',
            'description' => 'required|string',
            'certificate_photo' => 'sometimes|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        if ($request->hasFile('certificate_photo')) {
            $imagePath = $request->file('certificate_photo')->store('certificates', 'public');
            $validated['certificate_photo'] = $imagePath;
        }

        $certification->update($validated);

        return response()->json($certification);
    }

    public function deleteUserCertification($certificationId)
    {
        $certification = UserCertification::find($certificationId);
        if (!$certification) {
            return response()->json(['message' => 'Certification not found'], 404);
        }
        if ($certification->user_id != Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $certification->delete();

        return response()->json(['message' => 'Certification deleted successfully']);
    }
    // ================================== Create UserInfo ==================================
    public function getUserInfo($id)
    {
        $userInfo = UserInfo::where('user_id', $id)->first();
        if (!$userInfo) {
            return response()->json(['message' => 'User info not found'], 404);
        }
        return response()->json($userInfo);
    }
    public function createUserInfo(Request $request)
    {
        $validated = $request->validate([
            'contact' => 'required|string',
            'email' => 'required|email',
            'languages_spoken' => 'required|array',
            'languages_spoken.*' => 'string',
            'website' => 'required|string',
            'social_link' => 'required|string',
            'gender' => 'required|string',
            'date_of_birth' => 'required|date',
        ]);
        $validated['user_id'] = Auth::id();
        $userInfo = UserInfo::create($validated);
        return response()->json($userInfo, 201);
    }

    public function updateUserInfo(Request $request, $infoId)
    {
        $userInfo = UserInfo::find($infoId);
        if (!$userInfo) {
            return response()->json(['message' => 'User info not found'], 404);
        }
        if ($userInfo->user_id != Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'contact' => 'sometimes|nullable|string',
            'email' => 'sometimes|nullable|email',
            'website' => 'sometimes|nullable',
            'social_link' => 'sometimes|nullable',
            'gender' => 'sometimes|nullable|string',
            'date_of_birth' => 'sometimes|nullable|date',
            'languages_spoken' => 'sometimes|nullable',
        ]);

        $arrayFields = ['languages_spoken', 'website', 'social_link'];

        foreach ($arrayFields as $field) {
            if ($request->has($field)) {
                $value = $request->input($field);

                if (is_array($value)) {
                    $stringValue = implode(',', array_filter($value));
                } else {
                    $stringValue = (string) $value;
                }

                $userInfo->$field = $stringValue;
            }
        }

        $simpleFields = ['contact', 'email', 'gender', 'date_of_birth'];
        foreach ($simpleFields as $field) {
            if ($request->has($field)) {
                $userInfo->$field = $request->input($field);
            }
        }

        $saved = $userInfo->save();

        if (!$saved) {
            return response()->json(['message' => 'Failed to save'], 500);
        }

        return response()->json($userInfo->fresh());
    }

    public function deleteUserInfo($infoId)
    {
        $userInfo = UserInfo::find($infoId);
        if (!$userInfo) {
            return response()->json(['message' => 'User info not found'], 404);
        }
    }

    // ================================== Create UserOverview ==================================
    public function createUserOverview(Request $request)
    {
        $validated = $request->validate([
            'description' => 'required|string',
        ]);
        $validated['user_id'] = Auth::id();
        $overview = UserOverview::create($validated);
        return response()->json($overview, 201);
    }

    public function getUserOverview($id)
    {

        $overview = UserOverview::where('user_id', $id)->first();

        if (!$overview) {
            return response()->json(['message' => 'Overview not found'], 404);
        }

        return response()->json($overview);
    }
    public function updateUserOverview(Request $request, $id)
    {
        $overview = UserOverview::where('user_id', $id)->first();

        if (!$overview) {
            return response()->json(['message' => 'Overview not found'], 404);
        }

        $validated = $request->validate([
            'description' => 'required|string',
        ]);

        $overview->description = $validated['description'];
        $overview->save();

        return response()->json($overview);
    }

    public function deleteUserOverview($id)
    {
        $overview = UserOverview::where('user_id', $id)->first();
        if (!$overview) {
            return response()->json(['message' => 'Overview not found'], 404);
        }
        if ($overview->user_id != Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $overview->delete();

        return response()->json(['message' => 'Overview deleted successfully']);
    }

    // ================================== Create UserSkill ==================================
    public function createUserSkill(Request $request)
    {
        $validated = $request->validate([
            'skill' => 'required|string',
            'proficiency' => 'required|string',
            'description' => 'required|string',
        ]);
        $validated['user_id'] = Auth::id();
        $skill = UserSkill::create($validated);
        return response()->json($skill, 201);
    }

    public function getUserSkill($id)
    {
        $skill = UserSkill::where('user_id', $id)->get();
        if (!$skill) {
            return response()->json(['message' => 'Skill not found'], 404);
        }
        return response()->json($skill);
    }

    public function updateUserSkill(Request $request, $skillId)
    {
        $skill = UserSkill::find($skillId);
        if (!$skill) {
            return response()->json(['message' => 'Skill not found'], 404);
        }
        if ($skill->user_id != Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'skill' => 'required|string',
            'proficiency' => 'required|string',
            'description' => 'required|string',
        ]);

        $skill->update($validated);

        return response()->json($skill);
    }

    public function deleteUserSkill($skillId)
    {
        $skill = UserSkill::find($skillId);
        if (!$skill) {
            return response()->json(['message' => 'Skill not found'], 404);
        }
        if ($skill->user_id != Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $skill->delete();

        return response()->json(['message' => 'Skill deleted successfully']);
    }
}
