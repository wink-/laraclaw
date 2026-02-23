<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Memories - Laraclaw</title>
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
        .memories-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 20px; }
        .memory {
            background: #16213e;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .memory .key {
            color: #6c63ff;
            font-size: 0.85rem;
            font-weight: 600;
            margin-bottom: 10px;
        }
        .memory .content {
            color: #ccc;
            line-height: 1.5;
            margin-bottom: 15px;
            max-height: 100px;
            overflow: hidden;
        }
        .memory .meta {
            display: flex;
            justify-content: space-between;
            font-size: 0.75rem;
            color: #666;
        }
        .pagination { display: flex; justify-content: center; gap: 10px; margin-top: 20px; }
        .pagination a, .pagination span {
            padding: 8px 12px;
            background: #16213e;
            border-radius: 6px;
            color: #aaa;
            text-decoration: none;
        }
        .pagination a:hover { background: #6c63ff; color: white; }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>Memory Fragments</h1>
            <nav>
                <a href="{{ route('laraclaw.dashboard') }}">Dashboard</a>
                <a href="{{ route('laraclaw.conversations') }}">Conversations</a>
                <a href="{{ route('laraclaw.memories') }}" class="active">Memories</a>
                <a href="{{ route('laraclaw.metrics') }}">Metrics</a>
            </nav>
        </header>

        <div class="memories-grid">
            @forelse ($memories as $memory)
                <div class="memory">
                    @if ($memory->key)
                        <div class="key">{{ $memory->key }}</div>
                    @endif
                    <div class="content">{{ Str::limit($memory->content, 200) }}</div>
                    <div class="meta">
                        <span>{{ $memory->user?->name ?? 'Anonymous' }}</span>
                        <span>{{ $memory->created_at->diffForHumans() }}</span>
                    </div>
                </div>
            @empty
                <div class="memory">
                    <div class="content">No memory fragments found.</div>
                </div>
            @endforelse
        </div>

        <div class="pagination">
            {{ $memories->links() }}
        </div>
    </div>
</body>
</html>
