<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Conversations - Laraclaw</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #1a1a2e;
            color: #eee;
            min-height: 100vh;
        }
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
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
        nav a:hover, nav a.active { background: #6c63ff; color: white; }
        .card {
            background: #16213e;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .filters { display: flex; gap: 15px; margin-bottom: 20px; }
        .filters a {
            color: #aaa;
            text-decoration: none;
            padding: 8px 16px;
            border-radius: 6px;
            background: #16213e;
        }
        .filters a:hover, .filters a.active { background: #6c63ff; color: white; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: 12px; border-bottom: 1px solid #333; }
        th { color: #888; font-weight: 500; font-size: 0.85rem; text-transform: uppercase; }
        a { color: #6c63ff; text-decoration: none; }
        a:hover { text-decoration: underline; }
        .pagination { display: flex; justify-content: center; gap: 10px; margin-top: 20px; }
        .pagination a, .pagination span {
            padding: 8px 12px;
            background: #16213e;
            border-radius: 6px;
            color: #aaa;
            text-decoration: none;
        }
        .pagination a:hover { background: #6c63ff; color: white; }
        .pagination .active { background: #6c63ff; color: white; }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>Conversations</h1>
            <nav>
                <a href="{{ route('laraclaw.dashboard') }}">Dashboard</a>
                <a href="{{ route('laraclaw.conversations') }}" class="active">Conversations</a>
                <a href="{{ route('laraclaw.memories') }}">Memories</a>
                <a href="{{ route('laraclaw.metrics') }}">Metrics</a>
            </nav>
        </header>

        <div class="filters">
            <a href="{{ route('laraclaw.conversations') }}" class="{{ request()->gateway ? '' : 'active' }}">All</a>
            <a href="{{ route('laraclaw.conversations', ['gateway' => 'telegram']) }}" class="{{ request()->gateway === 'telegram' ? 'active' : '' }}">Telegram</a>
            <a href="{{ route('laraclaw.conversations', ['gateway' => 'discord']) }}" class="{{ request()->gateway === 'discord' ? 'active' : '' }}">Discord</a>
            <a href="{{ route('laraclaw.conversations', ['gateway' => 'cli']) }}" class="{{ request()->gateway === 'cli' ? 'active' : '' }}">CLI</a>
        </div>

        <div class="card">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Title</th>
                        <th>Gateway</th>
                        <th>User</th>
                        <th>Messages</th>
                        <th>Created</th>
                        <th>Last Active</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($conversations as $conversation)
                        <tr>
                            <td><a href="{{ route('laraclaw.conversation', $conversation->id) }}">#{{ $conversation->id }}</a></td>
                            <td>{{ $conversation->title ?? 'Untitled' }}</td>
                            <td>{{ ucfirst($conversation->gateway) }}</td>
                            <td>{{ $conversation->user?->name ?? 'Anonymous' }}</td>
                            <td>{{ $conversation->messages()->count() }}</td>
                            <td>{{ $conversation->created_at->diffForHumans() }}</td>
                            <td>{{ $conversation->updated_at->diffForHumans() }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="pagination">
                {{ $conversations->links() }}
            </div>
        </div>
    </div>
</body>
</html>
