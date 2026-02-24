<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\MemoryResource;
use App\Models\MemoryFragment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MemoryController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $memories = MemoryFragment::query()
            ->where('user_id', $request->user()->id)
            ->latest()
            ->paginate(50);

        return MemoryResource::collection($memories);
    }

    public function store(Request $request): MemoryResource
    {
        $validated = $request->validate([
            'content' => ['required', 'string', 'max:4000'],
            'key' => ['nullable', 'string', 'max:255'],
        ]);

        $memory = MemoryFragment::query()->create([
            'user_id' => $request->user()->id,
            'key' => $validated['key'] ?? null,
            'content' => $validated['content'],
        ]);

        return new MemoryResource($memory);
    }
}
