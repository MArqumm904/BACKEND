<?php

namespace App\Http\Controllers;

use App\Models\Message;
use Illuminate\Http\Request;
use App\Services\FirebaseService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class MessageController extends Controller
{
    protected $database;

    public function __construct(FirebaseService $firebase)
    {
        $this->database = $firebase->database;
    }
    public function getMessages($friendId)
    {
        $messages = Message::where(function ($q) use ($friendId) {
            $q->where('sender_id', auth()->id())
                ->where('receiver_id', $friendId);
        })
            ->orWhere(function ($q) use ($friendId) {
                $q->where('sender_id', $friendId)
                    ->where('receiver_id', auth()->id());
            })
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json($messages);
    }

    public function sendMessage(Request $request)
    {
        $data = $request->validate([
            'receiver_id' => 'required|integer',
            'message' => 'required|string'
        ]);

        $message = Message::create([
            'sender_id' => auth()->id(),
            'receiver_id' => $data['receiver_id'],
            'message' => $data['message'],
            'deleted_by' => []
        ]);

        // Firebase Push
        $chatId = $this->getChatId(auth()->id(), $data['receiver_id']);

        $this->database->getReference("chats/{$chatId}/messages")->push([
            'text' => $data['message'],
            'sender_id' => auth()->id(),
            'timestamp' => now()->timestamp,
            'type' => 'text',
            'deleted_by' => []
        ]);

        return response()->json($message);
    }

    public function sendImageMessage(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'image' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:10240',
            'caption' => 'nullable|string|max:1000',
            'receiver_id' => 'required|integer|exists:users,id'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $imagePath = $request->file('image')->store('chat-images', 'public');
            $imageUrl = asset('storage/' . $imagePath);

            $message = Message::create([
                'sender_id' => auth()->id(),
                'receiver_id' => $request->receiver_id,
                'message' => $request->caption ?? '',
                'type' => 'image',
                'media_url' => $imageUrl,
                'media_path' => $imagePath,
                'deleted_by' => []
            ]);

            $chatId = $this->getChatId(auth()->id(), $request->receiver_id);
            $this->database->getReference("chats/{$chatId}/messages")->push([
                'text' => $request->caption ?? '',
                'sender_id' => auth()->id(),
                'timestamp' => now()->timestamp,
                'type' => 'image',
                'image_url' => $imageUrl,
                'media_url' => $imageUrl,
                'media_path' => $imagePath,
                'deleted_by' => []
            ]);

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
            'file' => 'required|file',
            'caption' => 'nullable|string|max:1000',
            'receiver_id' => 'required|integer|exists:users,id'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $storedPath = $request->file('file')->store('chat-files', 'public');
            $fileUrl = asset('storage/' . $storedPath);
            $mimeType = $request->file('file')->getMimeType();
            $originalName = $request->file('file')->getClientOriginalName();
            $sizeBytes = $request->file('file')->getSize();

            $message = Message::create([
                'sender_id' => auth()->id(),
                'receiver_id' => $request->receiver_id,
                'message' => $request->caption ?? $originalName,
                'type' => 'file',
                'media_url' => $fileUrl,
                'media_path' => $storedPath,
                'deleted_by' => []
            ]);

            $chatId = $this->getChatId(auth()->id(), $request->receiver_id);
            $this->database->getReference("chats/{$chatId}/messages")->push([
                'text' => $request->caption ?? $originalName,
                'sender_id' => auth()->id(),
                'timestamp' => now()->timestamp,
                'type' => 'file',
                'media_url' => $fileUrl,
                'media_path' => $storedPath,
                'file' => [
                    'name' => $originalName,
                    'mime' => $mimeType,
                    'size' => $sizeBytes
                ],
                'deleted_by' => []
            ]);

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

    // Optional: send a post reference as a message (image/video/text/poll)
    public function sendPostMessage(Request $request)
    {
        $data = $request->validate([
            'receiver_id' => 'required|integer|exists:users,id',
            'post_id' => 'required|integer',
            'post_type' => 'required|string|in:text,image,video,poll',
            'message' => 'nullable|string|max:1000',
            'thumbnail' => 'nullable|string' // optional media thumb url (frontend-resolved)
        ]);

        $message = Message::create([
            'sender_id' => auth()->id(),
            'receiver_id' => $data['receiver_id'],
            'message' => $data['message'] ?? '',
            'type' => 'post',
            'media_url' => $data['thumbnail'] ?? null,
            'media_path' => null,
            'deleted_by' => []
        ]);

        $chatId = $this->getChatId(auth()->id(), $data['receiver_id']);
        $this->database->getReference("chats/{$chatId}/messages")->push([
            'text' => $data['message'] ?? '',
            'sender_id' => auth()->id(),
            'timestamp' => now()->timestamp,
            'type' => 'post',
            'post' => [
                'id' => $data['post_id'],
                'post_type' => $data['post_type'],
                'thumbnail' => $data['thumbnail'] ?? null,
            ],
            'deleted_by' => []
        ]);

        return response()->json($message);
    }

    private function getChatId($user1, $user2)
    {
        return $user1 < $user2 ? "chat_{$user1}_{$user2}" : "chat_{$user2}_{$user1}";
    }

    public function sendVoiceMessage(Request $request)
    {
        $validator = Validator::make($request->all(), [
            // 'voice' => 'required|file|mimes:audio/webm,audio/mpeg,audio/wav,audio/ogg|max:5120', // 5MB max
            'voice' => 'required|file|mimes:webm,mp3,wav,ogg,mpeg|max:5120',
            'receiver_id' => 'required|integer|exists:users,id',
            'duration' => 'required|integer|min:1|max:300' // 5 minutes max
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $storedPath = $request->file('voice')->store('chat-voices', 'public');
            $voiceUrl = asset('storage/' . $storedPath);

            $message = Message::create([
                'sender_id' => auth()->id(),
                'receiver_id' => $request->receiver_id,
                'message' => 'Voice message', // Default text
                'type' => 'voice',
                'media_url' => $voiceUrl,
                'media_path' => $storedPath,
                'deleted_by' => []
            ]);

            $chatId = $this->getChatId(auth()->id(), $request->receiver_id);
            $this->database->getReference("chats/{$chatId}/messages")->push([
                'sender_id' => auth()->id(),
                'timestamp' => now()->timestamp,
                'type' => 'voice',
                'media_url' => $voiceUrl,
                'media_path' => $storedPath,
                'duration' => $request->duration,
                'deleted_by' => []
            ]);

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

    public function clearChat(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'friend_id' => 'required|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = $request->user();
            $friendId = $request->input('friend_id');

            // Generate chat ID (same logic as your frontend)
            $chatId = $user->id < $friendId
                ? "chat_{$user->id}_{$friendId}"
                : "chat_{$friendId}_{$user->id}";

            // Get all messages in this chat from Firebase
            $firebaseMessages = $this->database
                ->getReference("chats/{$chatId}/messages")
                ->getValue();

            if ($firebaseMessages) {
                foreach ($firebaseMessages as $messageId => $message) {
                    $deletedBy = $message['deleted_by'] ?? [];

                    // Add user to deleted_by array if not already there
                    if (!in_array($user->id, $deletedBy)) {
                        $deletedBy[] = $user->id;

                        // Update Firebase
                        $this->database
                            ->getReference("chats/{$chatId}/messages/{$messageId}/deleted_by")
                            ->set($deletedBy);
                    }
                }
            }

            // Also update database records if you store messages there
            $messages = Message::where(function ($query) use ($user, $friendId) {
                $query->where('sender_id', $user->id)
                    ->where('receiver_id', $friendId);
            })->orWhere(function ($query) use ($user, $friendId) {
                $query->where('sender_id', $friendId)
                    ->where('receiver_id', $user->id);
            })->get();

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
