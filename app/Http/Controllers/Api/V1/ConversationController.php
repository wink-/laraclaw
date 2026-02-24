<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ConversationResource;
use App\Models\Conversation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class ConversationController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $conversations = Conversation::query()
            ->where('user_id', $request->user()->id)
            ->latest('updated_at')
            ->paginate(20);

        return ConversationResource::collection($conversations);
    }

    public function show(Request $request, Conversation $conversation): ConversationResource
    {
        abort_unless($conversation->user_id === $request->user()->id, Response::HTTP_FORBIDDEN);

        return new ConversationResource($conversation->load('messages'));
    }

    public function store(Request $request): ConversationResource
    {
        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
        ]);

        $conversation = Conversation::query()->create([
            'user_id' => $request->user()->id,
            'gateway' => 'api',
            'title' => $validated['title'] ?? 'API Chat',
        ]);

        return new ConversationResource($conversation);
    }
}
