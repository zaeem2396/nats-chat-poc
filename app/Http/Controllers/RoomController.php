<?php

namespace App\Http\Controllers;

use App\Jobs\SendChatMessageJob;
use App\Models\Room;
use App\Services\Chat\ChatMessageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoomController extends Controller
{
    public function index(): JsonResponse
    {
        $rooms = Room::orderBy('created_at', 'desc')->get();

        return response()->json($rooms);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate(['name' => 'required|string|max:255']);

        $room = Room::create($validated);

        return response()->json($room, 201);
    }

    public function message(Request $request, Room $room, ChatMessageService $chatMessageService): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|integer|min:1',
            'content' => 'required|string|max:10000',
        ]);

        $message = $chatMessageService->send(
            $room,
            (int) $validated['user_id'],
            $validated['content']
        );

        return response()->json($message->fresh(), 201);
    }

    public function schedule(Request $request, Room $room): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|integer|min:1',
            'content' => 'required|string|max:10000',
            'delay_minutes' => 'sometimes|integer|min:1|max:1440',
        ]);

        $delayMinutes = $validated['delay_minutes'] ?? 1;

        try {
            SendChatMessageJob::dispatch(
                $room->id,
                (int) $validated['user_id'],
                $validated['content']
            )->delay(now()->addMinutes($delayMinutes));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Schedule message failed', [
                'room_id' => $room->id,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Failed to schedule message. Ensure NATS is running with JetStream (-js) and the app can connect.',
                'error' => $e->getMessage(),
            ], 503);
        }

        return response()->json([
            'message' => 'Message scheduled',
            'room_id' => $room->id,
            'delay_minutes' => $delayMinutes,
        ], 202);
    }

    public function history(Room $room): JsonResponse
    {
        $messages = $room->messages()->orderBy('timestamp')->get();

        return response()->json($messages);
    }
}
