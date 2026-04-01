<?php

namespace App\Http\Controllers\Laraclaw;

use App\Http\Controllers\Controller;
use App\Laraclaw\Voice\VoiceService;
use App\Models\Message;
use Illuminate\Http\Request;

class VoiceController extends Controller
{
    public function __construct(
        protected VoiceService $voiceService,
    ) {}

    /**
     * Transcribe an audio recording to text.
     */
    public function transcribe(Request $request)
    {
        $request->validate([
            'audio' => 'required|file|mimes:webm,mp3,wav,ogg,m4a|max:25600',
        ]);

        $path = $request->file('audio')->store('voice-uploads', 'local');
        $fullPath = storage_path('app/'.$path);

        try {
            $text = $this->voiceService->transcribe($fullPath);

            return response()->json(['text' => $text]);
        } finally {
            @unlink($fullPath);
        }
    }

    /**
     * Generate speech audio for an assistant message.
     */
    public function speak(Message $message)
    {
        if ($message->role !== 'assistant') {
            abort(404);
        }

        $path = $this->voiceService->speak($message->content);

        return response()->file($path, [
            'Content-Type' => 'audio/mpeg',
            'Content-Disposition' => 'inline; filename="message-'.$message->id.'.mp3"',
        ]);
    }
}
