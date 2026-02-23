<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laraclaw Dashboard</title>
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
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .card {
            background: #16213e;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .card h2 { color: #888; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 10px; }
        .card .value { font-size: 2.5rem; font-weight: bold; color: #6c63ff; }
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .status-healthy { background: #10b981; color: white; }
        .status-degraded { background: #f59e0b; color: white; }
        .status-unhealthy { background: #ef4444; color: white; }
        .health-checks { list-style: none; }
        .health-checks li { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #333; }
        .health-checks li:last-child { border-bottom: none; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: 12px; border-bottom: 1px solid #333; }
        th { color: #888; font-weight: 500; font-size: 0.85rem; text-transform: uppercase; }
        .gateway-status { display: flex; gap: 15px; margin-top: 10px; }
        .gateway { display: flex; align-items: center; gap: 6px; }
        .gateway-dot { width: 8px; height: 8px; border-radius: 50%; }
        .gateway-dot.active { background: #10b981; }
        .gateway-dot.inactive { background: #6b7280; }
        footer { text-align: center; padding: 20px; color: #666; margin-top: 40px; }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>Laraclaw Dashboard</h1>
            <nav>
                <a href="{{ route('laraclaw.dashboard') }}" class="active">Dashboard</a>
                <a href="{{ route('laraclaw.conversations') }}">Conversations</a>
                <a href="{{ route('laraclaw.chat') }}">Chat</a>
                <a href="{{ route('laraclaw.memories') }}">Memories</a>
                <a href="{{ route('laraclaw.metrics') }}">Metrics</a>
            </nav>
        </header>

        <div class="grid">
            <div class="card">
                <h2>Conversations</h2>
                <div class="value">{{ number_format($stats['conversations']) }}</div>
            </div>
            <div class="card">
                <h2>Messages</h2>
                <div class="value">{{ number_format($stats['messages']) }}</div>
            </div>
            <div class="card">
                <h2>Memory Fragments</h2>
                <div class="value">{{ number_format($stats['memories']) }}</div>
            </div>
            <div class="card">
                <h2>Users</h2>
                <div class="value">{{ number_format($stats['users']) }}</div>
            </div>
        </div>

        <div class="grid">
            <div class="card">
                <h2>System Health</h2>
                <div style="margin: 15px 0;">
                    <span class="status-badge status-{{ $health['status'] }}">
                        {{ ucfirst($health['status']) }}
                    </span>
                </div>
                <ul class="health-checks">
                    @foreach ($health['checks'] as $name => $check)
                        <li>
                            <span>{{ ucfirst(str_replace('_', ' ', $name)) }}</span>
                            <span class="status-badge status-{{ $check['status'] }}" style="font-size: 0.7rem;">
                                {{ $check['message'] }}
                            </span>
                        </li>
                    @endforeach
                </ul>
            </div>
            <div class="card">
                <h2>Gateways</h2>
                <div class="gateway-status">
                    <div class="gateway">
                        <span class="gateway-dot {{ $stats['gateways']['telegram'] ? 'active' : 'inactive' }}"></span>
                        <span>Telegram</span>
                    </div>
                    <div class="gateway">
                        <span class="gateway-dot {{ $stats['gateways']['discord'] ? 'active' : 'inactive' }}"></span>
                        <span>Discord</span>
                    </div>
                    <div class="gateway">
                        <span class="gateway-dot active"></span>
                        <span>CLI</span>
                    </div>
                </div>
            </div>
            <div class="card">
                <h2>Performance</h2>
                <ul class="health-checks">
                    <li><span>Messages Sent</span><span>{{ number_format($metrics['messages_sent']) }}</span></li>
                    <li><span>Messages Received</span><span>{{ number_format($metrics['messages_received']) }}</span></li>
                    <li><span>Avg Response Time</span><span>{{ $metrics['avg_response_time'] }} ms</span></li>
                    <li><span>Errors</span><span>{{ number_format($metrics['errors']) }}</span></li>
                </ul>
            </div>
        </div>

        <div class="card">
            <h2>Recent Conversations</h2>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Title</th>
                        <th>Gateway</th>
                        <th>User</th>
                        <th>Last Active</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($recentConversations as $conversation)
                        <tr>
                            <td><a href="{{ route('laraclaw.conversation', $conversation['id']) }}" style="color: #6c63ff;">#{{ $conversation['id'] }}</a></td>
                            <td>{{ $conversation['title'] }}</td>
                            <td>{{ ucfirst($conversation['gateway']) }}</td>
                            <td>{{ $conversation['user'] }}</td>
                            <td>{{ $conversation['updated'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <footer>
            Laraclaw v1.0 | Powered by Laravel {{ app()->version() }}
        </footer>
    </div>
</body>
</html>
