<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Conversation #{{ $conversation->id }} - Laraclaw</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #1a1a2e;
            color: #eee;
            min-height: 100vh;
        }
        .container { max-width: 900px; margin: 0 auto; padding: 20px; }
        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 0;
            border-bottom: 1px solid #333;
            margin-bottom: 30px;
        }
        h1 { color: #6c63ff; font-size: 1.8rem; }
        nav a {
            color: #aaa;
            text-decoration: none;
            margin-left: 20px;
            padding: 8px 16px;
            border-radius: 6px;
            transition: all 0.2s;
        }
        nav a:hover { background: #6c63ff; color: white; }
        .meta {
            background: #16213e;
            border-radius: 12px;
            padding: 15px 20px;
            margin-bottom: 20px;
            display: flex;
            gap: 30px;
        }
        .meta-item { display: flex; flex-direction: column; }
        .meta-item .label { color: #888; font-size: 0.75rem; text-transform: uppercase; }
        .meta-item .value { color: #eee; margin-top: 4px; }
        .messages { display: flex; flex-direction: column; gap: 15px; }
        .message {
            background: #16213e;
            border-radius: 12px;
            padding: 15px 20px;
            max-width: 80%;
        }
        .message.user { align-self: flex-end; background: #6c63ff; }
        .message.assistant { align-self: flex-start; }
        .message .role {
            font-size: 0.7rem;
            text-transform: uppercase;
            color: #888;
            margin-bottom: 8px;
        }
        .message.user .role { color: rgba(255,255,255,0.7); }
        .message .content { line-height: 1.5; white-space: pre-wrap; }
        .message .time { font-size: 0.7rem; color: #666; margin-top: 8px; }
        a { color: #6c63ff; text-decoration: none; }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>Conversation #{{ $conversation->id }}</h1>
            <nav>
                <a href="{{ route('laraclaw.conversations') }}">Back to List</a>
            </nav>
        </header>

        <div class="meta">
            <div class="meta-item">
                <span class="label">Title</span>
                <span class="value">{{ $conversation->title ?? 'Untitled' }}</span>
            </div>
            <div class="meta-item">
                <span class="label">Gateway</span>
                <span class="value">{{ ucfirst($conversation->gateway) }}</span>
            </div>
            <div class="meta-item">
                <span class="label">User</span>
                <span class="value">{{ $conversation->user?->name ?? 'Anonymous' }}</span>
            </div>
            <div class="meta-item">
                <span class="label">Created</span>
                <span class="value">{{ $conversation->created_at->format('M j, Y g:i A') }}</span>
            </div>
        </div>

        <div class="messages">
            @foreach ($conversation->messages as $message)
                <div class="message {{ $message->role }}">
                    <div class="role">{{ $message->role }}{{ $message->tool_name ? ' (' . $message->tool_name . ')' : '' }}</div>
                    <div class="content">{{ $message->content }}</div>
                    <div class="time">{{ $message->created_at->format('M j, Y g:i:s A') }}</div>
                </div>
            @endforeach
        </div>
    </div>
</body>
</html>
