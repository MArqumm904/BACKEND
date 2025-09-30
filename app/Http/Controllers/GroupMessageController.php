<?php

namespace App\Http\Controllers;

use App\Models\GroupChat;
use App\Models\GroupMessage;
use App\Models\User;
use App\Services\FirebaseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class GroupMessageController extends Controller
{
    protected $firebase;

    public function __construct(FirebaseService $firebase)
    {
        $this->firebase = $firebase;
    }

    public function sendMessage(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'group_id' => 'required|exists:group_chats,id',
            'message' => 'required|string',
            'type' => 'required|in:text,image,voice,file,post'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = $request->user();

            $messageData = [
                'sender_id' => $user->id,
                'text' => $request->message,
                'timestamp' => now()->timestamp,
                'type' => $request->type,
                'read_by' => [$user->id],
                'deleted_by' => []
            ];

            $messageRef = $this->firebase->database
                ->getReference("groups/{$request->group_id}/messages")
                ->push($messageData);

            $firebaseKey = $messageRef->getKey();

            $message = new GroupMessage();
            $message->group_id = $request->group_id;
            $message->sender_id = $user->id;
            $message->message = $request->message;
            $message->type = $request->type;
            $message->read_by = [$user->id];
            $message->firebase_key = $firebaseKey;
            $message->save();

            return response()->json([
                'success' => true,
                'message' => $message,
                'firebase_key' => $firebaseKey
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send message',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function sendImageMessage(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'image' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:10240',
            'caption' => 'nullable|string|max:1000',
            'group_id' => 'required|exists:group_chats,id'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $user = $request->user();
            $imagePath = $request->file('image')->store('group-chat-images', 'public');
            $imageUrl = asset('storage/' . $imagePath);

            // Firebase data
            $messageData = [
                'sender_id' => $user->id,
                'text' => $request->caption ?? '',
                'timestamp' => now()->timestamp,
                'type' => 'image',
                'image_url' => $imageUrl,
                'media_url' => $imageUrl,
                'media_path' => $imagePath,
                'read_by' => [$user->id],
                'deleted_by' => []
            ];

            $messageRef = $this->firebase->database
                ->getReference("groups/{$request->group_id}/messages")
                ->push($messageData);

            $firebaseKey = $messageRef->getKey();

            // Database record
            $message = new GroupMessage();
            $message->group_id = $request->group_id;
            $message->sender_id = $user->id;
            $message->message = $request->caption ?? '';
            $message->type = 'image';
            $message->media_url = $imageUrl;
            $message->media_path = $imagePath;
            $message->read_by = [$user->id];
            $message->firebase_key = $firebaseKey;
            $message->save();

            return response()->json([
                'success' => true,
                'message' => $message,
                'image_url' => $imageUrl
            ]);
        } catch (\Exception $e) {
            if (isset($imagePath) && Storage::disk('public')->exists($imagePath)) {
                Storage::disk('public')->delete($imagePath);
            }
            return response()->json([
                'success' => false,
                'message' => 'Failed to send image message',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function sendFileMessage(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|max:20480',
            'caption' => 'nullable|string|max:1000',
            'group_id' => 'required|exists:group_chats,id'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $user = $request->user();
            $storedPath = $request->file('file')->store('group-chat-files', 'public');
            $fileUrl = asset('storage/' . $storedPath);
            $mimeType = $request->file('file')->getMimeType();
            $originalName = $request->file('file')->getClientOriginalName();
            $sizeBytes = $request->file('file')->getSize();

            // Firebase data
            $messageData = [
                'sender_id' => $user->id,
                'text' => $request->caption ?? $originalName,
                'timestamp' => now()->timestamp,
                'type' => 'file',
                'media_url' => $fileUrl,
                'media_path' => $storedPath,
                'file' => [
                    'name' => $originalName,
                    'mime' => $mimeType,
                    'size' => $sizeBytes
                ],
                'read_by' => [$user->id],
                'deleted_by' => []
            ];

            $messageRef = $this->firebase->database
                ->getReference("groups/{$request->group_id}/messages")
                ->push($messageData);

            $firebaseKey = $messageRef->getKey();

            // Database record
            $message = new GroupMessage();
            $message->group_id = $request->group_id;
            $message->sender_id = $user->id;
            $message->message = $request->caption ?? $originalName;
            $message->type = 'file';
            $message->media_url = $fileUrl;
            $message->media_path = $storedPath;
            $message->read_by = [$user->id];
            $message->firebase_key = $firebaseKey;
            $message->save();

            return response()->json([
                'success' => true,
                'message' => $message,
                'file_url' => $fileUrl
            ]);
        } catch (\Exception $e) {
            if (isset($storedPath) && Storage::disk('public')->exists($storedPath)) {
                Storage::disk('public')->delete($storedPath);
            }
            return response()->json([
                'success' => false,
                'message' => 'Failed to send file message',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function sendVoiceMessage(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'voice' => 'required|file|mimes:webm,mp3,wav,ogg,mpeg|max:5120',
            'group_id' => 'required|exists:group_chats,id',
            'duration' => 'required|integer|min:1|max:300'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $user = $request->user();
            $storedPath = $request->file('voice')->store('group-chat-voices', 'public');
            $voiceUrl = asset('storage/' . $storedPath);

            // Firebase data
            $messageData = [
                'sender_id' => $user->id,
                'text' => 'Voice message',
                'timestamp' => now()->timestamp,
                'type' => 'voice',
                'media_url' => $voiceUrl,
                'media_path' => $storedPath,
                'duration' => $request->duration,
                'read_by' => [$user->id],
                'deleted_by' => []
            ];

            $messageRef = $this->firebase->database
                ->getReference("groups/{$request->group_id}/messages")
                ->push($messageData);

            $firebaseKey = $messageRef->getKey();

            // Database record
            $message = new GroupMessage();
            $message->group_id = $request->group_id;
            $message->sender_id = $user->id;
            $message->message = 'Voice message';
            $message->type = 'voice';
            $message->media_url = $voiceUrl;
            $message->media_path = $storedPath;
            $message->read_by = [$user->id];
            $message->firebase_key = $firebaseKey;
            $message->save();

            return response()->json([
                'success' => true,
                'message' => $message,
                'voice_url' => $voiceUrl
            ]);
        } catch (\Exception $e) {
            if (isset($storedPath) && Storage::disk('public')->exists($storedPath)) {
                Storage::disk('public')->delete($storedPath);
            }
            return response()->json([
                'success' => false,
                'message' => 'Failed to send voice message',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function markAsRead(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'group_id' => 'required|exists:group_chats,id',
            'message_id' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = $request->user();

            // Find message by firebase_key instead of id
            $message = GroupMessage::where('firebase_key', $request->message_id)
                ->where('group_id', $request->group_id)
                ->first();

            if (!$message) {
                // If not found in database, we can still update Firebase
                $this->firebase->database
                    ->getReference("groups/{$request->group_id}/messages/{$request->message_id}/read_by")
                    ->transaction(function ($currentData) use ($user) {
                        $readBy = $currentData ?? [];
                        if (!in_array($user->id, $readBy)) {
                            $readBy[] = $user->id;
                        }
                        return $readBy;
                    });

                return response()->json([
                    'success' => true,
                    'message' => 'Message marked as read in Firebase'
                ]);
            }

            $readBy = $message->read_by ?? [];
            if (!in_array($user->id, $readBy)) {
                $readBy[] = $user->id;
                $message->read_by = $readBy;
                $message->save();

                // Also update Firebase
                $this->firebase->database
                    ->getReference("groups/{$request->group_id}/messages/{$request->message_id}/read_by")
                    ->set($readBy);
            }

            return response()->json([
                'success' => true,
                'message' => 'Message marked as read'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to mark message as read',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getMembers(Request $request, $groupId)
    {
        try {
            $memberIds = $request->input('members', []);

            if (empty($memberIds)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Members array is required'
                ], 400);
            }

            // Users fetch with profile
            $users = User::query()
                ->whereIn('id', $memberIds)
                ->with('profile')
                ->get()
                ->map(function ($u) {
                    return [
                        'id' => $u->id,
                        'name' => $u->name,
                        'email' => $u->email,
                        'profile_photo' => optional($u->profile)->profile_photo,
                    ];
                });

            return response()->json([
                'success' => true,
                'group_id' => $groupId,
                'members' => $users
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch group members',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // app/Http/Controllers/GroupMessageController.php
    public function clearChat(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'group_id' => 'required|exists:group_chats,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = $request->user();
            $groupId = $request->input('group_id');

            // Get all messages in this group from Firebase
            $firebaseMessages = $this->firebase->database
                ->getReference("groups/{$groupId}/messages")
                ->getValue();

            if ($firebaseMessages) {
                foreach ($firebaseMessages as $messageId => $message) {
                    $deletedBy = $message['deleted_by'] ?? [];

                    // Add user to deleted_by array if not already there
                    if (!in_array($user->id, $deletedBy)) {
                        $deletedBy[] = $user->id;

                        // Update Firebase
                        $this->firebase->database
                            ->getReference("groups/{$groupId}/messages/{$messageId}/deleted_by")
                            ->set($deletedBy);
                    }
                }
            }

            // Also update database records
            $messages = GroupMessage::where('group_id', $groupId)->get();
            foreach ($messages as $message) {
                $deletedBy = $message->deleted_by ?? [];

                if (!in_array($user->id, $deletedBy)) {
                    $deletedBy[] = $user->id;
                    $message->deleted_by = $deletedBy;
                    $message->save();
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Chat cleared successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to clear chat',
                'error' => $e->getMessage()
            ], 500);
        }
    }


}