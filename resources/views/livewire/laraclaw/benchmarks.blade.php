<?php

use App\Laraclaw\AI\ModelBenchmarkRepository;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Volt\Component;

new class extends Component
{
    public function with(): array
    {
        $repository = app(ModelBenchmarkRepository::class);
        $modelRankings = $repository->rankedModels();
        $providerRankings = $repository->rankedProviders();
        $recentFailures = $repository->recentFailures();

        /** @var array<string, mixed>|null $fastestModel */
        $fastestModel = $modelRankings->first(fn (array $benchmark) => $benchmark['successful_runs'] > 0);
        /** @var array<string, mixed>|null $fastestProvider */
        $fastestProvider = $providerRankings->first(fn (array $benchmark) => $benchmark['average_response_time_ms'] !== null);

        return [
            'modelRankings' => $modelRankings,
            'providerRankings' => $providerRankings,
            'recentFailures' => $recentFailures,
            'benchmarkedModels' => $modelRankings->count(),
            'fastestModel' => $fastestModel,
            'fastestProvider' => $fastestProvider,
        ];
    }

    public function rendering(View $view): void
    {
        $view->layout('components.laraclaw.layout');
    }
}; ?>

<div class="space-y-6">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-100">Benchmarks</h1>
            <p class="text-gray-400">Track the fastest and most reliable model and provider combinations from your live test runs.</p>
        </div>
        <a
            href="{{ route('laraclaw.models.live') }}"
            class="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-indigo-700"
        >
            Back to Models
        </a>
    </div>

    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
        <div class="rounded-2xl border border-gray-700 bg-gray-800 p-5">
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-gray-500">Benchmarked Models</p>
            <p class="mt-3 text-3xl font-semibold text-gray-100">{{ $benchmarkedModels }}</p>
            <p class="mt-2 text-sm text-gray-400">Models with at least one persisted test result.</p>
        </div>
        <div class="rounded-2xl border border-gray-700 bg-gray-800 p-5">
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-gray-500">Fastest Model</p>
            @if($fastestModel)
                <p class="mt-3 text-xl font-semibold text-gray-100">{{ $fastestModel['model_name'] }}</p>
                <p class="mt-1 text-sm text-indigo-300">{{ $fastestModel['provider'] }} / {{ $fastestModel['model_id'] }}</p>
                <p class="mt-3 text-sm text-gray-400">Avg {{ $fastestModel['average_response_time_ms'] }}ms, best {{ $fastestModel['fastest_response_time_ms'] }}ms</p>
            @else
                <p class="mt-3 text-sm text-gray-400">Run a few model tests to see the leaderboard.</p>
            @endif
        </div>
        <div class="rounded-2xl border border-gray-700 bg-gray-800 p-5">
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-gray-500">Fastest Provider</p>
            @if($fastestProvider)
                <p class="mt-3 text-xl font-semibold text-gray-100">{{ ucfirst(str_replace('-', ' ', $fastestProvider['provider'])) }}</p>
                <p class="mt-1 text-sm text-indigo-300">Avg {{ $fastestProvider['average_response_time_ms'] }}ms</p>
                <p class="mt-3 text-sm text-gray-400">{{ $fastestProvider['models_tested'] }} models benchmarked, {{ $fastestProvider['success_rate'] }}% success rate</p>
            @else
                <p class="mt-3 text-sm text-gray-400">No successful provider benchmarks recorded yet.</p>
            @endif
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-[1.4fr_1fr]">
        <section class="rounded-2xl border border-gray-700 bg-gray-800">
            <div class="flex items-center justify-between border-b border-gray-700 px-5 py-4">
                <div>
                    <h2 class="text-lg font-semibold text-gray-100">Model Leaderboard</h2>
                    <p class="text-sm text-gray-400">Sorted by average successful response time.</p>
                </div>
            </div>

            @if($modelRankings->isEmpty())
                <div class="px-5 py-12 text-center text-sm text-gray-400">No benchmark data yet.</div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-700 text-sm">
                        <thead class="bg-gray-900/40 text-left text-xs uppercase tracking-[0.2em] text-gray-500">
                            <tr>
                                <th class="px-5 py-3">Rank</th>
                                <th class="px-5 py-3">Model</th>
                                <th class="px-5 py-3">Provider</th>
                                <th class="px-5 py-3">Avg</th>
                                <th class="px-5 py-3">Best</th>
                                <th class="px-5 py-3">Last</th>
                                <th class="px-5 py-3">Success</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-700/80">
                            @foreach($modelRankings as $benchmark)
                                <tr>
                                    <td class="px-5 py-4 text-gray-500">#{{ $loop->iteration }}</td>
                                    <td class="px-5 py-4">
                                        <p class="font-semibold text-gray-100">{{ $benchmark['model_name'] }}</p>
                                        <p class="font-mono text-xs text-gray-500">{{ $benchmark['model_id'] }}</p>
                                    </td>
                                    <td class="px-5 py-4 text-gray-300">{{ $benchmark['provider'] }}</td>
                                    <td class="px-5 py-4 text-gray-100">{{ $benchmark['average_response_time_ms'] !== null ? $benchmark['average_response_time_ms'].'ms' : 'n/a' }}</td>
                                    <td class="px-5 py-4 text-gray-300">{{ $benchmark['fastest_response_time_ms'] !== null ? $benchmark['fastest_response_time_ms'].'ms' : 'n/a' }}</td>
                                    <td class="px-5 py-4 text-gray-300">{{ $benchmark['last_response_time_ms'] !== null ? $benchmark['last_response_time_ms'].'ms' : 'n/a' }}</td>
                                    <td class="px-5 py-4 text-gray-300">{{ $benchmark['success_rate'] }}% ({{ $benchmark['successful_runs'] }}/{{ $benchmark['total_runs'] }})</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        <div class="space-y-6">
            <section class="rounded-2xl border border-gray-700 bg-gray-800">
                <div class="border-b border-gray-700 px-5 py-4">
                    <h2 class="text-lg font-semibold text-gray-100">Provider Leaderboard</h2>
                    <p class="text-sm text-gray-400">Average across successfully benchmarked models.</p>
                </div>

                @if($providerRankings->isEmpty())
                    <div class="px-5 py-8 text-sm text-gray-400">No provider stats yet.</div>
                @else
                    <div class="space-y-3 p-5">
                        @foreach($providerRankings as $provider)
                            <div class="rounded-xl border border-gray-700 bg-gray-900/30 p-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <p class="font-semibold text-gray-100">{{ ucfirst(str_replace('-', ' ', $provider['provider'])) }}</p>
                                        <p class="mt-1 text-sm text-gray-400">{{ $provider['models_tested'] }} models, {{ $provider['total_runs'] }} runs</p>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-sm font-semibold text-indigo-300">{{ $provider['average_response_time_ms'] !== null ? $provider['average_response_time_ms'].'ms avg' : 'No passes yet' }}</p>
                                        <p class="mt-1 text-xs text-gray-500">{{ $provider['success_rate'] }}% success</p>
                                    </div>
                                </div>
                                <div class="mt-3 flex items-center justify-between text-xs text-gray-500">
                                    <span>Best {{ $provider['fastest_response_time_ms'] !== null ? $provider['fastest_response_time_ms'].'ms' : 'n/a' }}</span>
                                    <span>{{ $provider['latest_tested_at_human'] ?? 'Never tested' }}</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>

            <section class="rounded-2xl border border-gray-700 bg-gray-800">
                <div class="border-b border-gray-700 px-5 py-4">
                    <h2 class="text-lg font-semibold text-gray-100">Recent Failures</h2>
                    <p class="text-sm text-gray-400">Most recent failing test runs.</p>
                </div>

                @if($recentFailures->isEmpty())
                    <div class="px-5 py-8 text-sm text-gray-400">No recent failures.</div>
                @else
                    <div class="space-y-3 p-5">
                        @foreach($recentFailures as $failure)
                            <div class="rounded-xl border border-red-500/20 bg-red-500/5 p-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <p class="font-semibold text-gray-100">{{ $failure['model_name'] }}</p>
                                        <p class="mt-1 font-mono text-xs text-gray-500">{{ $failure['provider'] }} / {{ $failure['model_id'] }}</p>
                                    </div>
                                    <div class="text-right text-sm text-red-300">
                                        <p>{{ $failure['last_response_time_ms'] !== null ? $failure['last_response_time_ms'].'ms' : 'n/a' }}</p>
                                        <p class="mt-1 text-xs text-gray-500">{{ $failure['last_tested_at_human'] ?? 'Unknown time' }}</p>
                                    </div>
                                </div>
                                <p class="mt-3 wrap-break-word text-sm text-gray-400">{{ $failure['last_error_message'] }}</p>
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>
        </div>
    </div>
</div>