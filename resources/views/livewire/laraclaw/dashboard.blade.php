<div class="space-y-6">
    <!-- Header -->
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-2xl font-bold text-gray-100">Dashboard</h1>
            <p class="text-gray-400">Monitor your Laraclaw instance</p>
        </div>
        <a href="{{ route('laraclaw.chat') }}" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 rounded-lg font-medium transition">
            Open Chat
        </a>
    </div>

    <!-- Stats Grid -->
    <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-4 gap-4">
        <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-400 text-sm">Total Conversations</p>
                    <p class="text-3xl font-bold text-gray-100">{{ $stats['conversations'] }}</p>
                </div>
                <div class="w-12 h-12 bg-indigo-600/20 rounded-lg flex items-center justify-center">
                    <svg class="w-6 h-6 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                    </svg>
                </div>
            </div>
        </div>

        <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-400 text-sm">Total Messages</p>
                    <p class="text-3xl font-bold text-gray-100">{{ $stats['messages'] }}</p>
                </div>
                <div class="w-12 h-12 bg-green-600/20 rounded-lg flex items-center justify-center">
                    <svg class="w-6 h-6 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"></path>
                    </svg>
                </div>
            </div>
        </div>

        <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-400 text-sm">Memory Fragments</p>
                    <p class="text-3xl font-bold text-gray-100">{{ $stats['memories'] }}</p>
                </div>
                <div class="w-12 h-12 bg-purple-600/20 rounded-lg flex items-center justify-center">
                    <svg class="w-6 h-6 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z"></path>
                    </svg>
                </div>
            </div>
        </div>

        <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-400 text-sm">Today's Chats</p>
                    <p class="text-3xl font-bold text-gray-100">{{ $stats['today_conversations'] }}</p>
                </div>
                <div class="w-12 h-12 bg-yellow-600/20 rounded-lg flex items-center justify-center">
                    <svg class="w-6 h-6 text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
            </div>
        </div>

        <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-400 text-sm">Agent Collaborations</p>
                    <p class="text-3xl font-bold text-gray-100">{{ $stats['agent_collaborations'] }}</p>
                </div>
                <div class="w-12 h-12 bg-cyan-600/20 rounded-lg flex items-center justify-center">
                    <svg class="w-6 h-6 text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5V4H2v16h5m10 0v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6m10 0H7"></path>
                    </svg>
                </div>
            </div>
        </div>

        <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-400 text-sm">Scheduled Tasks</p>
                    <p class="text-3xl font-bold text-gray-100">{{ $stats['scheduled_tasks'] }}</p>
                </div>
                <div class="w-12 h-12 bg-emerald-600/20 rounded-lg flex items-center justify-center">
                    <svg class="w-6 h-6 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10m-11 9h12a2 2 0 002-2V7a2 2 0 00-2-2H6a2 2 0 00-2 2v11a2 2 0 002 2z"></path>
                    </svg>
                </div>
            </div>
        </div>

        <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-400 text-sm">Tokens (7d)</p>
                    <p class="text-3xl font-bold text-gray-100">{{ number_format($stats['tokens_7d']) }}</p>
                </div>
                <div class="w-12 h-12 bg-blue-600/20 rounded-lg flex items-center justify-center">
                    <svg class="w-6 h-6 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6M7 4h10a2 2 0 012 2v12a2 2 0 01-2 2H7a2 2 0 01-2-2V6a2 2 0 012-2z"></path>
                    </svg>
                </div>
            </div>
        </div>

        <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-400 text-sm">Estimated Cost (7d)</p>
                    <p class="text-3xl font-bold text-gray-100">${{ number_format($stats['cost_7d'], 3) }}</p>
                </div>
                <div class="w-12 h-12 bg-rose-600/20 rounded-lg flex items-center justify-center">
                    <svg class="w-6 h-6 text-rose-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-2.21 0-4 1.79-4 4m8 0a4 4 0 00-4-4m0 0V5m0 15v-3m-7-5H3m18 0h-2"></path>
                    </svg>
                </div>
            </div>
        </div>

        <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-400 text-sm">Pending Notifications</p>
                    <p class="text-3xl font-bold text-gray-100">{{ $stats['pending_notifications'] }}</p>
                </div>
                <div class="w-12 h-12 bg-orange-600/20 rounded-lg flex items-center justify-center">
                    <svg class="w-6 h-6 text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V4a2 2 0 10-4 0v1.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0a3 3 0 11-6 0h6z"></path>
                    </svg>
                </div>
            </div>
        </div>
    </div>

    <!-- System Health & Recent Conversations -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- System Health -->
        <div class="bg-gray-800 rounded-xl border border-gray-700 p-6">
            <h2 class="text-lg font-semibold text-gray-100 mb-4">System Health</h2>
            <div class="space-y-3">
                @foreach($health as $key => $value)
                    <div class="flex items-center justify-between py-2 border-b border-gray-700 last:border-0">
                        <span class="text-gray-400 capitalize">{{ str_replace('_', ' ', $key) }}</span>
                        <span class="px-2 py-1 text-xs rounded-full {{ $value === 'healthy' || $value === 'enabled' ? 'bg-green-600/20 text-green-400' : 'bg-gray-700 text-gray-300' }}">
                            {{ $value }}
                        </span>
                    </div>
                @endforeach
            </div>
        </div>

        <!-- Recent Conversations -->
        <div class="bg-gray-800 rounded-xl border border-gray-700 p-6">
            <h2 class="text-lg font-semibold text-gray-100 mb-4">Recent Conversations</h2>
            <div class="space-y-2">
                @forelse($recentConversations as $conv)
                    <div class="flex items-center justify-between py-2 px-3 rounded-lg hover:bg-gray-700/50 transition">
                        <div>
                            <p class="text-gray-200">{{ $conv->title }}</p>
                            <p class="text-xs text-gray-500">{{ $conv->messages_count }} messages</p>
                        </div>
                        <span class="text-xs text-gray-500">{{ $conv->created_at->diffForHumans() }}</span>
                    </div>
                @empty
                    <p class="text-gray-500 text-center py-4">No conversations yet</p>
                @endforelse
            </div>
        </div>
    </div>

    <div class="bg-gray-800 rounded-xl border border-gray-700 p-6">
        <h2 class="text-lg font-semibold text-gray-100 mb-4">Ops Signals (24h)</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-6 gap-3">
            <div class="py-3 px-3 rounded-lg bg-gray-700/40">
                <p class="text-xs text-gray-500">Failed Scheduled Jobs</p>
                <p class="text-xl font-semibold {{ ($opsSignals['failed_scheduled_jobs'] ?? 0) > 0 ? 'text-red-300' : 'text-gray-100' }}">
                    {{ $opsSignals['failed_scheduled_jobs'] ?? 0 }}
                </p>
            </div>
            <div class="py-3 px-3 rounded-lg bg-gray-700/40">
                <p class="text-xs text-gray-500">Webhook Failures</p>
                <p class="text-xl font-semibold {{ ($opsSignals['webhook_failures'] ?? 0) > 0 ? 'text-red-300' : 'text-gray-100' }}">
                    {{ $opsSignals['webhook_failures'] ?? 0 }}
                </p>
            </div>
            <div class="py-3 px-3 rounded-lg bg-gray-700/40">
                <p class="text-xs text-gray-500">Collaborations (24h)</p>
                <p class="text-xl font-semibold text-gray-100">{{ $opsSignals['collaborations_last_24h'] ?? 0 }}</p>
            </div>
            <div class="py-3 px-3 rounded-lg bg-gray-700/40">
                <p class="text-xs text-gray-500">Errors Metric</p>
                <p class="text-xl font-semibold {{ ($opsSignals['errors_metric'] ?? 0) > 0 ? 'text-yellow-300' : 'text-gray-100' }}">
                    {{ $opsSignals['errors_metric'] ?? 0 }}
                </p>
            </div>
            <div class="py-3 px-3 rounded-lg bg-gray-700/40">
                <p class="text-xs text-gray-500">API Rate Limited</p>
                <p class="text-xl font-semibold {{ ($opsSignals['api_rate_limited'] ?? 0) > 0 ? 'text-yellow-300' : 'text-gray-100' }}">
                    {{ $opsSignals['api_rate_limited'] ?? 0 }}
                </p>
            </div>
            <div class="py-3 px-3 rounded-lg bg-gray-700/40">
                <p class="text-xs text-gray-500">Notification Failures</p>
                <p class="text-xl font-semibold {{ ($opsSignals['notifications_failed_24h'] ?? 0) > 0 ? 'text-red-300' : 'text-gray-100' }}">
                    {{ $opsSignals['notifications_failed_24h'] ?? 0 }}
                </p>
            </div>
        </div>
        <p class="mt-3 text-xs text-gray-500">Total collaborations recorded: {{ $opsSignals['collaborations_total'] ?? 0 }}</p>
    </div>

    <div class="bg-gray-800 rounded-xl border border-gray-700 p-6">
        <h2 class="text-lg font-semibold text-gray-100 mb-4">Token Usage Analytics (7d)</h2>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            <div class="bg-gray-700/40 rounded-lg p-4">
                <h3 class="text-sm text-gray-300 mb-3">Daily Usage</h3>
                @forelse($tokenUsageAnalytics['daily'] ?? [] as $entry)
                    <div class="flex items-center justify-between text-xs py-1 border-b border-gray-700/60 last:border-0">
                        <span class="text-gray-400">{{ $entry['day'] }}</span>
                        <span class="text-gray-200">{{ number_format($entry['tokens']) }} • ${{ number_format($entry['cost'], 4) }}</span>
                    </div>
                @empty
                    <p class="text-xs text-gray-500">No token usage recorded in the last 7 days.</p>
                @endforelse
            </div>

            <div class="bg-gray-700/40 rounded-lg p-4">
                <h3 class="text-sm text-gray-300 mb-3">By Provider</h3>
                @forelse($tokenUsageAnalytics['providers'] ?? [] as $entry)
                    <div class="flex items-center justify-between text-xs py-1 border-b border-gray-700/60 last:border-0">
                        <span class="text-gray-400">{{ $entry['provider'] }}</span>
                        <span class="text-gray-200">{{ number_format($entry['tokens']) }} • ${{ number_format($entry['cost'], 4) }}</span>
                    </div>
                @empty
                    <p class="text-xs text-gray-500">No provider breakdown available yet.</p>
                @endforelse
            </div>

            <div class="bg-gray-700/40 rounded-lg p-4">
                <h3 class="text-sm text-gray-300 mb-3">Top Conversations</h3>
                @forelse($tokenUsageAnalytics['conversations'] ?? [] as $entry)
                    <div class="py-1 border-b border-gray-700/60 last:border-0">
                        <p class="text-xs text-gray-300 truncate">{{ $entry['title'] }}</p>
                        <p class="text-xs text-gray-500">{{ number_format($entry['tokens']) }} tokens • ${{ number_format($entry['cost'], 4) }}</p>
                    </div>
                @empty
                    <p class="text-xs text-gray-500">No conversation breakdown available yet.</p>
                @endforelse
            </div>
        </div>
    </div>

    <!-- Tailscale Network Status & Heartbeat Engine -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Tailscale Network Status -->
        <div class="bg-gray-800 rounded-xl border border-gray-700 p-6">
            <h2 class="text-lg font-semibold text-gray-100 mb-4">Tailscale Network</h2>
            @if(!($tailscaleStatus['enabled'] ?? false))
                <p class="text-sm text-gray-500">Tailscale networking is disabled. Set <code class="text-xs bg-gray-700 px-1.5 py-0.5 rounded">LARACLAW_TAILSCALE_ENABLED=true</code> to enable.</p>
            @elseif(!($tailscaleStatus['connected'] ?? false))
                <div class="flex items-center gap-2 text-yellow-400 mb-3">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <span class="text-sm font-medium">Not connected to Tailnet</span>
                </div>
                <p class="text-xs text-gray-500">Run <code class="bg-gray-700 px-1.5 py-0.5 rounded">tailscale up</code> to connect.</p>
            @else
                <div class="space-y-3">
                    <div class="flex items-center gap-2 text-green-400 mb-2">
                        <div class="w-2 h-2 rounded-full bg-green-400 animate-pulse"></div>
                        <span class="text-sm font-medium">Connected to {{ $tailscaleStatus['tailnet_name'] ?? 'tailnet' }}</span>
                    </div>
                    <div class="grid grid-cols-2 gap-2 text-sm">
                        <div class="bg-gray-700/40 px-3 py-2 rounded">
                            <p class="text-xs text-gray-500">Hostname</p>
                            <p class="text-gray-200 truncate">{{ $tailscaleStatus['self']['hostname'] ?? '-' }}</p>
                        </div>
                        <div class="bg-gray-700/40 px-3 py-2 rounded">
                            <p class="text-xs text-gray-500">Serve</p>
                            <p class="text-gray-200">{{ ($tailscaleStatus['serve_active'] ?? false) ? 'Active' : 'Inactive' }}</p>
                        </div>
                    </div>
                    @if(!empty($tailscaleStatus['self']['tailscale_ips'] ?? []))
                        <div class="bg-gray-700/40 px-3 py-2 rounded text-sm">
                            <p class="text-xs text-gray-500 mb-1">IPs</p>
                            @foreach($tailscaleStatus['self']['tailscale_ips'] as $ip)
                                <span class="inline-block mr-2 text-gray-300">{{ $ip }}</span>
                            @endforeach
                        </div>
                    @endif
                    @if(count($tailscaleStatus['peers'] ?? []) > 0)
                        <div>
                            <p class="text-xs text-gray-500 mb-1">Peers ({{ count($tailscaleStatus['peers']) }})</p>
                            <div class="space-y-1 max-h-32 overflow-y-auto">
                                @foreach($tailscaleStatus['peers'] as $peer)
                                    <div class="flex items-center justify-between text-xs bg-gray-700/30 px-2 py-1 rounded">
                                        <span class="text-gray-300">{{ $peer['hostname'] }}</span>
                                        <span class="{{ $peer['online'] ? 'text-green-400' : 'text-gray-500' }}">
                                            {{ $peer['online'] ? 'online' : 'offline' }}
                                        </span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            @endif
        </div>

        <!-- Heartbeat Engine -->
        <div class="bg-gray-800 rounded-xl border border-gray-700 p-6">
            <h2 class="text-lg font-semibold text-gray-100 mb-4">Heartbeat Engine</h2>
            @if(empty($heartbeatItems))
                <p class="text-sm text-gray-500">No heartbeat tasks found. Create <code class="text-xs bg-gray-700 px-1.5 py-0.5 rounded">storage/laraclaw/HEARTBEAT.md</code> to add autonomous tasks.</p>
            @else
                <div class="space-y-2 mb-4">
                    @foreach($heartbeatItems as $item)
                        <div class="flex items-center gap-3 text-sm bg-gray-700/40 px-3 py-2 rounded">
                            <span class="{{ $item['enabled'] ? 'text-green-400' : 'text-gray-500' }}">
                                {{ $item['enabled'] ? '●' : '○' }}
                            </span>
                            <span class="text-gray-200 flex-1 truncate">{{ $item['instruction'] }}</span>
                            <span class="text-xs text-gray-500 whitespace-nowrap">{{ $item['schedule'] }}</span>
                        </div>
                    @endforeach
                </div>
            @endif

            @if(!empty($recentHeartbeatRuns))
                <h3 class="text-sm font-semibold text-gray-300 mb-2 mt-4">Recent Runs</h3>
                <div class="space-y-1">
                    @foreach($recentHeartbeatRuns as $run)
                        <div class="flex items-center justify-between text-xs bg-gray-700/30 px-2 py-1.5 rounded">
                            <span class="text-gray-300 truncate mr-2">{{ $run['instruction'] }}</span>
                            <div class="flex items-center gap-2 shrink-0">
                                <span class="{{ $run['status'] === 'success' ? 'text-green-400' : 'text-red-400' }}">
                                    {{ $run['status'] }}
                                </span>
                                <span class="text-gray-500">{{ $run['executed_at'] }}</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    <div class="bg-gray-800 rounded-xl border border-gray-700 p-6">
        <h2 class="text-lg font-semibold text-gray-100 mb-4">Document Ingestion</h2>

        <form wire:submit="ingestDocument" class="space-y-3">
            <input
                type="file"
                wire:model="document"
                accept=".pdf,.txt,.doc,.docx,.md"
                class="block w-full text-sm text-gray-300 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-indigo-600 file:text-white hover:file:bg-indigo-700"
            >

            @error('document')
                <p class="text-sm text-red-400">{{ $message }}</p>
            @enderror

            @if($uploadStatus)
                <p class="text-sm text-gray-300">{{ $uploadStatus }}</p>
            @endif

            <button
                type="submit"
                class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 rounded-lg font-medium transition disabled:opacity-50"
            >
                Upload & Index
            </button>
        </form>

        <div class="mt-6">
            <h3 class="text-sm font-semibold text-gray-300 mb-3">Recent Documents</h3>
            <div class="space-y-2">
                @forelse($recentDocuments as $doc)
                    <div class="flex items-center justify-between py-2 px-3 rounded-lg bg-gray-700/40">
                        <div>
                            <p class="text-gray-200 text-sm">{{ $doc->original_name }}</p>
                            <p class="text-xs text-gray-500">{{ $doc->created_at->diffForHumans() }}</p>
                        </div>
                        <span class="px-2 py-1 text-xs rounded-full {{ $doc->indexed ? 'bg-green-600/20 text-green-400' : 'bg-yellow-600/20 text-yellow-400' }}">
                            {{ $doc->indexed ? 'Indexed' : 'Pending' }}
                        </span>
                    </div>
                @empty
                    <p class="text-sm text-gray-500">No uploaded documents yet.</p>
                @endforelse
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-gray-800 rounded-xl border border-gray-700 p-6">
            <h2 class="text-lg font-semibold text-gray-100 mb-4">Shopping List Agent</h2>
            @if(empty($shoppingListItems))
                <p class="text-sm text-gray-500">No shopping items yet. Ask the assistant to add items to your list.</p>
            @else
                <div class="space-y-2">
                    @foreach($shoppingListItems as $item)
                        <div class="flex items-center justify-between py-2 px-3 rounded-lg bg-gray-700/40 text-sm">
                            <div class="min-w-0">
                                <p class="text-gray-200 truncate">{{ $item['content'] }}</p>
                                <p class="text-xs text-gray-500">List: {{ $item['list_name'] }}</p>
                            </div>
                            <div class="text-right shrink-0 ml-3">
                                <p class="text-xs text-gray-400">{{ $item['quantity'] ?: '1x' }}</p>
                                <p class="text-xs text-gray-500">{{ $item['created_at'] }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="bg-gray-800 rounded-xl border border-gray-700 p-6">
            <h2 class="text-lg font-semibold text-gray-100 mb-4">Memory Categories</h2>
            @if(empty($memoryCategoryCounts))
                <p class="text-sm text-gray-500">No categorized memories yet. Ask the assistant to remember something.</p>
            @else
                <div class="space-y-2">
                    @foreach($memoryCategoryCounts as $category => $count)
                        <div class="flex items-center justify-between py-2 px-3 rounded-lg bg-gray-700/40">
                            <span class="text-sm text-gray-200 capitalize">{{ str_replace('_', ' ', $category) }}</span>
                            <span class="px-2 py-1 text-xs rounded-full bg-indigo-600/20 text-indigo-300">{{ $count }}</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    <div class="bg-gray-800 rounded-xl border border-gray-700 p-6 space-y-5">
        <h2 class="text-lg font-semibold text-gray-100">Module App Builder (Laravel MVC)</h2>

        @if($moduleStatus)
            <p class="text-sm text-gray-300">{{ $moduleStatus }}</p>
        @endif

        <form wire:submit="createModuleApp" class="grid grid-cols-1 md:grid-cols-3 gap-3">
            <input
                type="text"
                wire:model="newModuleName"
                placeholder="App name (e.g. Home Blog)"
                class="bg-gray-700 border border-gray-600 rounded-lg px-3 py-2 text-sm text-gray-100"
            >
            <input
                type="text"
                wire:model="newModuleDescription"
                placeholder="Description (optional)"
                class="bg-gray-700 border border-gray-600 rounded-lg px-3 py-2 text-sm text-gray-100"
            >
            <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 rounded-lg text-sm font-medium">
                Create Blog App
            </button>
        </form>

        @error('newModuleName')
            <p class="text-sm text-red-400">{{ $message }}</p>
        @enderror

        @if(empty($modules))
            <p class="text-sm text-gray-500">No generated Laravel MVC modules yet.</p>
        @else
            <div class="space-y-3">
                @foreach($modules as $module)
                    <div class="rounded-lg bg-gray-700/40 p-3 space-y-2">
                        <div class="flex items-center justify-between gap-4">
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-gray-100 truncate">{{ $module['name'] }} ({{ $module['slug'] }})</p>
                                <p class="text-xs text-gray-400">Route: {{ $module['domain'] ? $module['domain'] : '/' . $module['prefix'] }}</p>
                                <p class="text-xs text-gray-500 truncate">Model: {{ $module['model_class'] ?? 'n/a' }}</p>
                            </div>
                            <a href="/{{ $module['prefix'] }}" class="text-xs text-indigo-300 hover:text-indigo-200">Open</a>
                        </div>

                        <div class="flex items-center gap-2">
                            <input
                                type="text"
                                wire:model="moduleDomainInputs.{{ $module['slug'] }}"
                                placeholder="Domain (optional)"
                                class="flex-1 bg-gray-700 border border-gray-600 rounded-lg px-3 py-2 text-xs text-gray-100"
                            >
                            <button
                                type="button"
                                wire:click="bindModuleDomain('{{ $module['slug'] }}')"
                                class="px-3 py-2 text-xs bg-blue-600 hover:bg-blue-700 rounded-lg"
                            >
                                Save Domain
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <div class="bg-gray-800 rounded-xl border border-gray-700 p-6">
        <h2 class="text-lg font-semibold text-gray-100 mb-4">Skill Marketplace</h2>

        @if($marketplaceStatus)
            <p class="text-sm text-gray-300 mb-4">{{ $marketplaceStatus }}</p>
        @endif

        @if(empty($skills))
            <p class="text-sm text-gray-500">Marketplace is disabled or no skills are registered yet.</p>
        @else
            <div class="space-y-2">
                @foreach($skills as $skill)
                    <div class="flex items-center justify-between py-2 px-3 rounded-lg bg-gray-700/40">
                        <div>
                            <p class="text-gray-200 text-sm">{{ $skill['name'] }}</p>
                            <p class="text-xs text-gray-500">{{ $skill['class_name'] }}</p>
                        </div>
                        @if(($skill['is_required'] ?? false) && $skill['enabled'])
                            <span class="px-3 py-1.5 text-xs rounded-lg bg-gray-700 text-gray-300">
                                Required
                            </span>
                        @else
                            <button
                                wire:click='setSkillEnabled(@js($skill["class_name"]), {{ $skill["enabled"] ? "false" : "true" }})'
                                class="px-3 py-1.5 text-xs rounded-lg {{ $skill['enabled'] ? 'bg-red-600/20 text-red-300 hover:bg-red-600/30' : 'bg-green-600/20 text-green-300 hover:bg-green-600/30' }}"
                            >
                                {{ $skill['enabled'] ? 'Disable' : 'Enable' }}
                            </button>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <div class="bg-gray-800 rounded-xl border border-gray-700 p-6">
        <h2 class="text-lg font-semibold text-gray-100 mb-4">Scheduled Tasks</h2>

        @if($schedulerStatus)
            <p class="text-sm text-gray-300 mb-4">{{ $schedulerStatus }}</p>
        @endif

        @if(empty($scheduledTasks))
            <p class="text-sm text-gray-500">No scheduled tasks yet.</p>
        @else
            <div class="space-y-2">
                @foreach($scheduledTasks as $task)
                    <div class="py-3 px-3 rounded-lg bg-gray-700/40 space-y-2">
                        <div class="flex items-center justify-between gap-4">
                            <div class="min-w-0">
                                <p class="text-gray-200 text-sm truncate">{{ $task['action'] }}</p>
                                <p class="text-xs text-gray-500">{{ $task['cron_expression'] }}</p>
                            </div>
                            <span class="px-2 py-1 text-xs rounded-full {{ $task['is_active'] ? 'bg-green-600/20 text-green-400' : 'bg-gray-700 text-gray-300' }}">
                                {{ $task['is_active'] ? 'Active' : 'Paused' }}
                            </span>
                        </div>

                        <div class="flex items-center justify-between gap-3">
                            <p class="text-xs text-gray-500">
                                Last run: {{ $task['last_run_at'] ? \Illuminate\Support\Carbon::parse($task['last_run_at'])->diffForHumans() : 'Never' }}
                            </p>

                            <div class="flex items-center gap-2">
                                <button
                                    wire:click="toggleScheduledTask({{ $task['id'] }})"
                                    class="px-3 py-1.5 text-xs rounded-lg {{ $task['is_active'] ? 'bg-yellow-600/20 text-yellow-300 hover:bg-yellow-600/30' : 'bg-green-600/20 text-green-300 hover:bg-green-600/30' }}"
                                >
                                    {{ $task['is_active'] ? 'Pause' : 'Resume' }}
                                </button>
                                <button
                                    wire:click="removeScheduledTask({{ $task['id'] }})"
                                    class="px-3 py-1.5 text-xs rounded-lg bg-red-600/20 text-red-300 hover:bg-red-600/30"
                                >
                                    Remove
                                </button>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
