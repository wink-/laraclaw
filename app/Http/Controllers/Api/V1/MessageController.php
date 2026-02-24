<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\MessageResource;
use App\Laraclaw\Facades\Laraclaw;
use App\Models\Conversation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class MessageController extends Controller
{
    public function index(Request $request, Conversation $conversation): AnonymousResourceCollection
    {
        abort_unless($conversation->user_id === $request->user()->id, Response::HTTP_FORBIDDEN);

        $messages = $conversation->messages()
            ->latest()
            ->paginate(50);

        return MessageResource::collection($messages);
    }

    public function store(Request $request, Conversation $conversation): array
    {
        abort_unless($conversation->user_id === $request->user()->id, Response::HTTP_FORBIDDEN);

        $validated = $request->validate([
            'message' => ['required', 'string', 'max:10000'],
            'use_multi_agent' => ['nullable', 'boolean'],
        ]);

        $response = Laraclaw::chat(
            $conversation,
            $validated['message'],
            $validated['use_multi_agent'] ?? null,
        );

        $assistantMessage = $conversation->messages()->latest()->first();

        return [
            'response' => $response,
            'message' => $assistantMessage ? new MessageResource($assistantMessage) : null,
        ];
    }
}
