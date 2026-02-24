<div class="space-y-6">
    <!-- Header -->
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-2xl font-bold text-gray-100">Dashboard</h1>
            <p class="text-gray-400">Monitor your Laraclaw instance</p>
        </div>
        <a href="{{ route('laraclaw.chat.live') }}" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 rounded-lg font-medium transition">
            Open Chat
        </a>
    </div>

    <!-- Stats Grid -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
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
                        <button
                            wire:click='setSkillEnabled(@js($skill["class_name"]), {{ $skill["enabled"] ? "false" : "true" }})'
                            class="px-3 py-1.5 text-xs rounded-lg {{ $skill['enabled'] ? 'bg-red-600/20 text-red-300 hover:bg-red-600/30' : 'bg-green-600/20 text-green-300 hover:bg-green-600/30' }}"
                        >
                            {{ $skill['enabled'] ? 'Disable' : 'Enable' }}
                        </button>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
