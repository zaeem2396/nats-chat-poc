<?php

namespace App\Http\Controllers;

use App\Models\FailedMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DlqController extends Controller
{
    /**
     * List failed messages (from DLQ, stored in failed_messages table).
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->get('per_page', 20), 100);
        $items = FailedMessage::query()
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return response()->json($items);
    }
}
