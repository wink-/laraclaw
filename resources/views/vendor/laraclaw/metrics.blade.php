<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Metrics - Laraclaw</title>
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
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .card {
            background: #16213e;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .card h2 { color: #888; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 10px; }
        .card .value { font-size: 2rem; font-weight: bold; color: #6c63ff; }
        .prometheus {
            background: #16213e;
            border-radius: 12px;
            padding: 20px;
            margin-top: 20px;
        }
        .prometheus h2 { color: #888; font-size: 0.85rem; text-transform: uppercase; margin-bottom: 15px; }
        .prometheus pre {
            background: #0d1117;
            padding: 15px;
            border-radius: 8px;
            overflow-x: auto;
            font-family: 'Fira Code', monospace;
            font-size: 0.85rem;
            line-height: 1.6;
            color: #7ee787;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>Metrics</h1>
            <nav>
                <a href="{{ route('laraclaw.dashboard') }}">Dashboard</a>
                <a href="{{ route('laraclaw.conversations') }}">Conversations</a>
                <a href="{{ route('laraclaw.memories') }}">Memories</a>
                <a href="{{ route('laraclaw.metrics') }}" class="active">Metrics</a>
            </nav>
        </header>

        <div class="grid">
            <div class="card">
                <h2>Messages Sent</h2>
                <div class="value">{{ number_format($metrics['messages_sent']) }}</div>
            </div>
            <div class="card">
                <h2>Messages Received</h2>
                <div class="value">{{ number_format($metrics['messages_received']) }}</div>
            </div>
            <div class="card">
                <h2>Errors</h2>
                <div class="value">{{ number_format($metrics['errors']) }}</div>
            </div>
            <div class="card">
                <h2>Avg Response Time</h2>
                <div class="value">{{ $metrics['avg_response_time'] }} <span style="font-size: 1rem; color: #888;">ms</span></div>
            </div>
            <div class="card">
                <h2>Active Conversations</h2>
                <div class="value">{{ number_format($metrics['active_conversations']) }}</div>
            </div>
        </div>

        <div class="prometheus">
            <h2>Prometheus Format</h2>
            <pre>{{ $prometheus }}</pre>
        </div>
    </div>
</body>
</html>
