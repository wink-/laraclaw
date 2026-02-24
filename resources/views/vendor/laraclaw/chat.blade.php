<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
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
        .message.streaming .content {
            position: relative;
        }
        .message.streaming .content::after {
            content: '▋';
            animation: blink 1s infinite;
        }
        @keyframes blink {
            0%, 50% { opacity: 1; }
            51%, 100% { opacity: 0; }
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
        .input-area textarea:disabled {
            opacity: 0.5;
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
        .input-area button:hover:not(:disabled) {
            background: #5b54e0;
        }
        .input-area button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
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
        .options {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .stream-toggle {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.85rem;
            color: #888;
        }
        .stream-toggle input {
            accent-color: #6c63ff;
        }
        .error-message {
            background: #ef4444;
            color: white;
            padding: 10px 15px;
            border-radius: 8px;
            margin: 10px 20px;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>Chat with Laraclaw</h1>
            <div class="options">
                <label class="stream-toggle">
                    <input type="checkbox" id="streamToggle">
                    <span>Streaming</span>
                </label>
                <nav>
                    <a href="{{ route('laraclaw.dashboard') }}">Dashboard</a>
                    <a href="{{ route('laraclaw.conversations') }}">Conversations</a>
                    <a href="{{ route('laraclaw.chat') }}" class="active">Chat</a>
                </nav>
            </div>
        </header>

        <div class="chat-container">
            <div class="messages" id="messages">
                @forelse ($messages as $message)
                    <div class="message {{ $message->role }}" data-id="{{ $message->id }}">
                        <div class="role">
                            {{ $message->role }}
                            @if($message->role === 'assistant' && filled($message->metadata['response_mode'] ?? null))
                                <span style="margin-left: 8px; padding: 2px 6px; border-radius: 10px; background: #334155; color: #e2e8f0; font-size: 10px; text-transform: none;">
                                    {{ ($message->metadata['response_mode'] ?? 'single') === 'multi' ? 'Multi-Agent' : 'Single-Agent' }}
                                </span>
                            @endif
                        </div>
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

            <div id="errorArea"></div>

            <div class="input-area">
                <form id="chatForm">
                    @csrf
                    <input type="hidden" name="conversation_id" value="{{ $conversation->id }}">
                    <textarea name="message" id="messageInput" placeholder="Type your message..." rows="1" required autofocus></textarea>
                    <button type="submit" id="sendButton">Send</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        const messagesContainer = document.getElementById('messages');
        const chatForm = document.getElementById('chatForm');
        const messageInput = document.getElementById('messageInput');
        const sendButton = document.getElementById('sendButton');
        const streamToggle = document.getElementById('streamToggle');
        const errorArea = document.getElementById('errorArea');
        const conversationId = "{{ $conversation->id }}";

        // Auto-scroll to bottom
        messagesContainer.scrollTop = messagesContainer.scrollHeight;

        // Auto-resize textarea
        messageInput.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = Math.min(this.scrollHeight, 150) + 'px';
        });

        // Handle form submission
        chatForm.addEventListener('submit', async function(e) {
            e.preventDefault();

            const message = messageInput.value.trim();
            if (!message) return;

            // Clear input and disable while processing
            messageInput.value = '';
            messageInput.style.height = 'auto';
            messageInput.disabled = true;
            sendButton.disabled = true;
            errorArea.innerHTML = '';

            // Add user message to UI
            addMessage('user', message);

            // Create placeholder for assistant response
            const assistantMsg = addMessage('assistant', '', true);

            try {
                if (streamToggle.checked) {
                    await streamMessage(message, assistantMsg);
                } else {
                    await sendMessageStandard(message, assistantMsg);
                }
            } catch (error) {
                showError(error.message);
                assistantMsg.querySelector('.content').textContent = 'Sorry, an error occurred.';
                assistantMsg.classList.remove('streaming');
            }

            // Re-enable input
            messageInput.disabled = false;
            sendButton.disabled = false;
            messageInput.focus();
        });

        // Handle Enter key
        messageInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                chatForm.dispatchEvent(new Event('submit'));
            }
        });

        function addMessage(role, content, isStreaming = false) {
            // Remove empty state if exists
            const emptyState = messagesContainer.querySelector('.empty-state');
            if (emptyState) emptyState.remove();

            const div = document.createElement('div');
            div.className = `message ${role}${isStreaming ? ' streaming' : ''}`;
            div.innerHTML = `
                <div class="role">${role}</div>
                <div class="content">${escapeHtml(content)}</div>
                <div class="time">${new Date().toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}</div>
            `;
            messagesContainer.appendChild(div);
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
            return div;
        }

        async function streamMessage(message, assistantMsg) {
            const response = await fetch('{{ route("laraclaw.chat.stream.vercel") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'text/plain',
                },
                body: JSON.stringify({
                    conversation_id: conversationId,
                    message: message,
                }),
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            const reader = response.body.getReader();
            const decoder = new TextDecoder();
            let buffer = '';
            let fullContent = '';
            const contentEl = assistantMsg.querySelector('.content');

            while (true) {
                const { done, value } = await reader.read();
                if (done) {
                    break;
                }

                buffer += decoder.decode(value, { stream: true });
                const lines = buffer.split('\n');
                buffer = lines.pop() ?? '';

                for (const line of lines) {
                    const trimmedLine = line.trim();
                    if (trimmedLine.startsWith('0:')) {
                        try {
                            const payload = JSON.parse(trimmedLine.substring(2));
                            const text = typeof payload === 'string' ? payload : (payload?.text ?? '');

                            if (text) {
                                fullContent += text;
                            }

                            contentEl.textContent = fullContent;
                            messagesContainer.scrollTop = messagesContainer.scrollHeight;
                        } catch (e) {
                            // Ignore non-token lines
                        }
                    }
                }
            }

            const finalLine = buffer.trim();
            if (finalLine.startsWith('0:')) {
                try {
                    const payload = JSON.parse(finalLine.substring(2));
                    const text = typeof payload === 'string' ? payload : (payload?.text ?? '');

                    if (text) {
                        fullContent += text;
                        contentEl.textContent = fullContent;
                    }
                } catch (e) {
                    // Ignore malformed trailing payload
                }
            }

            if (!fullContent.trim()) {
                throw new Error('No content returned from streaming response. Try sending with Streaming off.');
            }

            assistantMsg.classList.remove('streaming');
        }

        async function sendMessageStandard(message, assistantMsg) {
            const response = await fetch('{{ route("laraclaw.chat.send") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: new URLSearchParams({
                    conversation_id: conversationId,
                    message: message,
                    _token: document.querySelector('meta[name="csrf-token"]').content,
                }),
            });

            if (response.redirected) {
                // Standard form submission redirects, reload page
                window.location.href = response.url;
                return;
            }

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            assistantMsg.classList.remove('streaming');
        }

        function showError(message) {
            errorArea.innerHTML = `<div class="error-message">${escapeHtml(message)}</div>`;
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    </script>
</body>
</html>
