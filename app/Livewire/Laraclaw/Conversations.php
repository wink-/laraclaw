<?php

namespace App\Livewire\Laraclaw;

use App\Models\Conversation;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

class Conversations extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $gateway = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedGateway(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function conversations()
    {
        return Conversation::query()
            ->withCount('messages')
            ->when($this->search, fn ($q) => $q->where('title', 'like', "%{$this->search}%"))
            ->when($this->gateway, fn ($q) => $q->where('gateway', $this->gateway))
            ->latest()
            ->paginate(15);
    }

    public function delete(int $id): void
    {
        Conversation::destroy($id);
    }

    public function exportMarkdown(int $id): StreamedResponse
    {
        $conversation = Conversation::with('messages')->findOrFail($id);

        $markdown = "# {$conversation->title}\n\n";
        $markdown .= "> Gateway: {$conversation->gateway}\n";
        $markdown .= "> Created: {$conversation->created_at->format('Y-m-d H:i:s')}\n\n";
        $markdown .= "---\n\n";

        foreach ($conversation->messages as $message) {
            $role = ucfirst($message->role);
            $time = $message->created_at->format('H:i:s');
            $markdown .= "### {$role} ({$time})\n\n";
            $markdown .= $message->content."\n\n";
        }

        $filename = str_replace(' ', '-', strtolower($conversation->title)).'.md';

        return response()->streamDownload(function () use ($markdown) {
            echo $markdown;
        }, $filename, [
            'Content-Type' => 'text/markdown',
        ]);
    }

    public function exportJson(int $id): StreamedResponse
    {
        $conversation = Conversation::with('messages')->findOrFail($id);

        $data = [
            'id' => $conversation->id,
            'title' => $conversation->title,
            'gateway' => $conversation->gateway,
            'created_at' => $conversation->created_at->toIso8601String(),
            'messages' => $conversation->messages->map(fn ($m) => [
                'role' => $m->role,
                'content' => $m->content,
                'created_at' => $m->created_at->toIso8601String(),
            ]),
        ];

        $filename = str_replace(' ', '-', strtolower($conversation->title)).'.json';

        return response()->streamDownload(function () use ($data) {
            echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }, $filename, [
            'Content-Type' => 'application/json',
        ]);
    }

    public function render()
    {
        return view('livewire.laraclaw.conversations')->layout('components.laraclaw.layout');
    }
}
