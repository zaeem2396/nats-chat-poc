<?php

namespace App\Http\Controllers;

use App\Models\Room;
use Illuminate\Http\JsonResponse;

class AnalyticsController extends Controller
{
    public function room(Room $room): JsonResponse
    {
        $analytic = $room->analytic;

        return response()->json([
            'room_id' => $room->id,
            'room_name' => $room->name,
            'message_count' => $analytic?->message_count ?? 0,
        ]);
    }
}
