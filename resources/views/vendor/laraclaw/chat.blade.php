<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat - Laraclaw</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #1a1a2e;
            color: #eee;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .container { max-width: 900px; margin: 0 auto; padding: 20px; flex: 1; display: flex; flex-direction: column; }
        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 0;
            border-bottom: 1px solid #333;
            margin-bottom: 20px;
        }
        h1 { color: #6c63ff; font-size: 1.5rem; }
        nav a {
            color: #aaa;
            text-decoration: none;
            margin-left: 15px;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 0.9rem;
        }
        nav a:hover, nav a.active { background: #6c63ff; color: white; }
        .chat-container {
            flex: 1;
            display: flex;
            flex-direction: column;
            background: #16213e;
            border-radius: 12px;
            overflow: hidden;
        }
        .messages {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        .message {
            max-width: 80%;
            padding: 12px 16px;
            border-radius: 12px;
            line-height: 1.5;
        }
        .message.user {
            align-self: flex-end;
            background: #6c63ff;
        }
        .message.assistant {
            align-self: flex-start;
            background: #0f3460;
        }
        .message .role {
            font-size: 0.7rem;
            text-transform: uppercase;
            opacity: 0.7;
            margin-bottom: 4px;
        }
        .message .content {
            white-space: pre-wrap;
        }
        .message .time {
            font-size: 0.65rem;
            opacity: 0.5;
            margin-top: 6px;
        }
        .input-area {
            padding: 20px;
            background: #0f3460;
            border-top: 1px solid #333;
        }
        .input-area form {
            display: flex;
            gap: 10px;
        }
        .input-area textarea {
            flex: 1;
            padding: 12px 16px;
            border: none;
            border-radius: 8px;
            background: #16213e;
            color: #eee;
            font-size: 1rem;
            resize: none;
            min-height: 50px;
            max-height: 150px;
        }
        .input-area textarea:focus {
            outline: 2px solid #6c63ff;
        }
        .input-area button {
            padding: 12px 24px;
            background: #6c63ff;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            cursor: pointer;
            transition: background 0.2s;
        }
        .input-area button:hover {
            background: #5b54e0;
        }
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #666;
        }
        .empty-state h2 {
            color: #888;
            margin-bottom: 10px;
        }
        a { color: #6c63ff; text-decoration: none; }
        a:hover { text-decoration: underline; }
        .new-chat-btn {
            background: transparent;
            border: 1px solid #6c63ff;
            color: #6c63ff;
            padding: 6px 12px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.9rem;
        }
        .new-chat-btn:hover {
            background: #6c63ff;
            color: white;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>Chat with Laraclaw</h1>
            <nav>
                <a href="{{ route('laraclaw.dashboard') }}">Dashboard</a>
                <a href="{{ route('laraclaw.conversations') }}">Conversations</a>
                <a href="{{ route('laraclaw.chat') }}" class="active">Chat</a>
            </nav>
        </header>

        <div class="chat-container">
            <div class="messages" id="messages">
                @forelse ($messages as $message)
                    <div class="message {{ $message->role }}">
                        <div class="role">{{ $message->role }}</div>
                        <div class="content">{{ $message->content }}</div>
                        <div class="time">{{ $message->created_at->format('M j, g:i A') }}</div>
                    </div>
                @empty
                    <div class="empty-state">
                        <h2>Start a conversation</h2>
                        <p>Ask me anything! I can help with time, calculations, web searches, memory, files, and more.</p>
                    </div>
                @endforelse
            </div>

            <div class="input-area">
                <form method="POST" action="{{ route('laraclaw.chat.send') }}">
                    @csrf
                    <input type="hidden" name="conversation_id" value="{{ $conversation->id }}">
                    <textarea name="message" placeholder="Type your message..." rows="1" required autofocus></textarea>
                    <button type="submit">Send</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Auto-scroll to bottom
        const messagesContainer = document.getElementById('messages');
        messagesContainer.scrollTop = messagesContainer.scrollHeight;

        // Auto-resize textarea
        const textarea = document.querySelector('textarea');
        textarea.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = Math.min(this.scrollHeight, 150) + 'px';
        });

        // Handle Enter key
        textarea.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                this.form.submit();
            }
        });
    </script>
</body>
</html>
