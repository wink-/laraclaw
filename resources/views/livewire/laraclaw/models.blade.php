<?php

use App\Laraclaw\Agents\CoreAgent;
use App\Laraclaw\AI\ModelBenchmarkRepository;
use App\Laraclaw\AI\ModelCatalog;
use Illuminate\View\View;
use Livewire\Volt\Component;

new class extends Component
{
    public string $activeTab = 'all';

    public string $search = '';

    public bool $showPanel = false;

    public string $panelMode = 'add';

    public string $formProvider = '';

    public string $formModelId = '';

    public string $formName = '';

    public int $formContext = 128000;

    public ?string $status = null;

    public function mount(): void
    {
        $this->formProvider = $this->getCatalog()->getProviders()[0] ?? '';
    }

    public function setActiveTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    public function openAddPanel(): void
    {
        $this->panelMode = 'add';
        $this->formProvider = $this->getCatalog()->getProviders()[0] ?? '';
        $this->formModelId = '';
        $this->formName = '';
        $this->formContext = 128000;
        $this->showPanel = true;
    }

    public function openEditPanel(string $provider, string $modelId): void
    {
        $catalog = $this->getCatalog();
        $info = $catalog->getModelInfo($provider, $modelId);

        if (! $info) {
            $this->status = 'Model not found.';

            return;
        }

        $this->panelMode = 'edit';
        $this->formProvider = $provider;
        $this->formModelId = $modelId;
        $this->formName = $info['name'];
        $this->formContext = $info['context'];
        $this->showPanel = true;
    }

    public function save(): void
    {
        $this->validate([
            'formProvider' => ['required', 'string'],
            'formModelId' => ['required', 'string'],
            'formName' => ['required', 'string', 'max:120'],
            'formContext' => ['required', 'integer', 'min:1'],
        ]);

        $catalog = $this->getCatalog();

        if ($this->panelMode === 'add') {
            if ($catalog->hasModel($this->formProvider, $this->formModelId)) {
                $this->addError('formModelId', 'This model ID already exists for the selected provider.');

                return;
            }

            $catalog->addModel($this->formProvider, $this->formModelId, $this->formName, $this->formContext);
            $this->status = "Added model {$this->formName} to {$this->formProvider}.";
        } else {
            $catalog->updateModel($this->formProvider, $this->formModelId, $this->formName, $this->formContext);
            $this->status = "Updated model {$this->formName}.";
        }

        $this->reset('showPanel', 'panelMode', 'formModelId', 'formName', 'formContext');
    }

    public function closePanel(): void
    {
        $this->reset('showPanel', 'panelMode', 'formModelId', 'formName', 'formContext');
    }

    public function removeModel(string $provider, string $modelId): void
    {
        $this->getCatalog()->removeModel($provider, $modelId);
        $this->status = "Removed model {$modelId} from {$provider}.";
    }

    public function testModel(string $provider, string $modelId): void
    {
        $catalog = $this->getCatalog();
        $agent = app(CoreAgent::class);
        $modelInfo = $catalog->getModelInfo($provider, $modelId);
        $modelName = $modelInfo['name'] ?? $modelId;

        $originalProvider = $agent->provider()->value;
        $originalModel = $agent->model();

        $start = microtime(true);

        try {
            $agent->applyProviderOverride($provider);
            $agent->applyModelOverride($modelId);

            $response = $agent->prompt('Hello, respond in one sentence.');
            $elapsed = round((microtime(true) - $start) * 1000);

            $text = (string) $response;
            if (mb_strlen($text) > 300) {
                $text = mb_substr($text, 0, 300).'...';
            }

            $this->getBenchmarkRepository()->recordResult(
                provider: $provider,
                modelId: $modelId,
                modelName: $modelName,
                responseTimeMs: $elapsed,
                status: 'pass',
                responseExcerpt: $text,
            );

            $this->status = "Benchmark recorded for {$modelName} ({$elapsed}ms).";
        } catch (Throwable $e) {
            $elapsed = round((microtime(true) - $start) * 1000);

            $this->getBenchmarkRepository()->recordResult(
                provider: $provider,
                modelId: $modelId,
                modelName: $modelName,
                responseTimeMs: $elapsed,
                status: 'fail',
                errorMessage: $e->getMessage(),
            );

            $this->status = "Benchmark failed for {$modelName}.";
        } finally {
            $agent->applyProviderOverride($originalProvider);
            $agent->applyModelOverride($originalModel);
        }
    }

    public function with(): array
    {
        $catalog = $this->getCatalog();
        $providers = $catalog->getProviders();

        if ($this->search !== '') {
            $models = $catalog->search($this->search);
        } elseif ($this->activeTab === 'all') {
            $models = $catalog->all();
        } else {
            $providerModels = $catalog->getModels($this->activeTab);
            $models = empty($providerModels) ? [] : [$this->activeTab => $providerModels];
        }

        return [
            'providers' => $providers,
            'models' => $models,
            'benchmarks' => $this->getBenchmarkRepository()->getIndexedStats(),
            'catalog' => $catalog,
        ];
    }

    public function rendering(View $view): void
    {
        $view->layout('components.laraclaw.layout');
    }

    protected function getCatalog(): ModelCatalog
    {
        return app(ModelCatalog::class);
    }

    protected function getBenchmarkRepository(): ModelBenchmarkRepository
    {
        return app(ModelBenchmarkRepository::class);
    }
}; ?>

<div class="space-y-6">
    <div class="flex justify-between items-start">
        <div>
            <h1 class="text-2xl font-bold text-gray-100">Model Catalog</h1>
            <p class="text-gray-400">Manage AI models across providers</p>
        </div>
        <div class="flex items-center gap-3">
            <a
                href="{{ route('laraclaw.benchmarks.live') }}"
                class="rounded-lg border border-gray-600 px-4 py-2 text-sm font-medium text-gray-200 transition hover:border-indigo-500 hover:text-white whitespace-nowrap"
            >
                View Benchmarks
            </a>
            <input
                type="text"
                wire:model.live.debounce.300ms="search"
                placeholder="Search models..."
                class="bg-gray-700 border border-gray-600 rounded-lg px-3 py-2 text-sm text-gray-100 focus:ring-2 focus:ring-indigo-500 focus:border-transparent placeholder-gray-500 w-56"
            >
            <button
                wire:click="openAddPanel"
                class="bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg px-4 py-2 text-sm font-medium transition whitespace-nowrap"
            >
                Add Model
            </button>
        </div>
    </div>

    @if($status)
        @php
            $statusColors = match (true) {
                str_contains($status, 'Added'),
                str_contains($status, 'Updated'),
                str_contains($status, 'success') => 'bg-green-600/20 text-green-300',
                str_contains($status, 'error'),
                str_contains($status, 'not found'),
                str_contains($status, 'Removed') => 'bg-red-600/20 text-red-300',
                default => 'bg-indigo-600/20 text-indigo-300',
            };
        @endphp
        <div class="px-4 py-3 rounded-lg text-sm {{ $statusColors }}">
            {{ $status }}
        </div>
    @endif

    <div class="border-b border-gray-700">
        <nav class="flex gap-4 -mb-px overflow-x-auto" role="tablist">
            <button
                wire:click="setActiveTab('all')"
                role="tab"
                class="px-1 py-3 text-sm font-medium border-b-2 transition whitespace-nowrap {{ $activeTab === 'all' ? 'border-indigo-500 text-indigo-400' : 'border-transparent text-gray-400 hover:text-gray-200 hover:border-gray-500' }}"
            >
                All
                <span class="ml-1.5 px-2 py-0.5 text-xs rounded bg-gray-700 text-gray-300">{{ array_sum(array_map('count', $models)) }}</span>
            </button>
            @foreach($providers as $provider)
                <button
                    wire:click="setActiveTab('{{ $provider }}')"
                    role="tab"
                    class="px-1 py-3 text-sm font-medium border-b-2 transition whitespace-nowrap {{ $activeTab === $provider ? 'border-indigo-500 text-indigo-400' : 'border-transparent text-gray-400 hover:text-gray-200 hover:border-gray-500' }}"
                >
                    {{ ucfirst(str_replace('-', ' ', $provider)) }}
                    @php
                        $providerCount = count($catalog->getModels($provider));
                    @endphp
                    <span class="ml-1.5 px-2 py-0.5 text-xs rounded {{ $activeTab === $provider ? 'bg-indigo-600/20 text-indigo-400' : 'bg-gray-700 text-gray-300' }}">{{ $providerCount }}</span>
                </button>
            @endforeach
        </nav>
    </div>

    @if(!empty($models))
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach($models as $provider => $providerModels)
                @foreach($providerModels as $modelId => $meta)
                    @php
                        $testKey = "{$provider}|{$modelId}";
                        $benchmark = $benchmarks[$testKey] ?? null;
                        $contextFormatted = $meta['context'] >= 1000000
                            ? round($meta['context'] / 1000000, 1) . 'M'
                            : round($meta['context'] / 1000) . 'K';
                    @endphp
                    <div class="bg-gray-800 rounded-xl border border-gray-700 p-4 hover:bg-gray-700/70 transition-colors duration-150 group" wire:key="model-{{ $provider }}-{{ $modelId }}">
                        <div class="flex items-start justify-between gap-3 mb-2">
                            <div class="min-w-0 flex-1">
                                <p class="text-xs font-mono text-gray-500 truncate">{{ $modelId }}</p>
                                <p class="text-sm font-semibold text-gray-100 mt-0.5">{{ $meta['name'] }}</p>
                            </div>
                            <span class="px-2 py-0.5 text-xs rounded bg-indigo-600/20 text-indigo-400 whitespace-nowrap">{{ $contextFormatted }}</span>
                        </div>

                        <div class="flex items-center gap-2 mb-3">
                            @if($activeTab === 'all' || $search !== '')
                                <span class="px-2 py-0.5 text-xs rounded bg-gray-700/40 text-gray-400">{{ $provider }}</span>
                            @endif
                            @if($benchmark && $benchmark['last_response_time_ms'] !== null)
                                <span class="px-2 py-0.5 text-xs rounded {{ $benchmark['last_status'] === 'pass' ? 'bg-green-500/10 text-green-300' : 'bg-red-500/10 text-red-300' }}">
                                    Last {{ $benchmark['last_response_time_ms'] }}ms
                                </span>
                            @endif
                        </div>

                        @if($benchmark)
                            <div class="mb-3 rounded-xl border border-gray-700 bg-gray-700/30 p-3 text-xs space-y-2">
                                <div class="flex items-center justify-between gap-3">
                                    <div>
                                        <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-gray-500">Last benchmark</p>
                                        <span class="font-medium {{ $benchmark['last_status'] === 'pass' ? 'text-green-400' : 'text-red-400' }}">
                                            {{ strtoupper($benchmark['last_status']) }}
                                        </span>
                                    </div>
                                    <span class="text-gray-400">{{ $benchmark['last_response_time_ms'] !== null ? $benchmark['last_response_time_ms'].'ms' : 'n/a' }}</span>
                                </div>
                                <div class="flex items-center justify-between text-[11px] text-gray-500">
                                    <span>Avg {{ $benchmark['average_response_time_ms'] !== null ? $benchmark['average_response_time_ms'].'ms' : 'n/a' }}</span>
                                    <span>Best {{ $benchmark['fastest_response_time_ms'] !== null ? $benchmark['fastest_response_time_ms'].'ms' : 'n/a' }}</span>
                                </div>
                                <p class="wrap-break-word text-gray-400">{{ $benchmark['last_response_excerpt'] ?? $benchmark['last_error_message'] }}</p>
                                <p class="text-[11px] text-gray-500">{{ $benchmark['successful_runs'] }}/{{ $benchmark['total_runs'] }} successful{{ $benchmark['last_tested_at_human'] ? ' • '.$benchmark['last_tested_at_human'] : '' }}</p>
                            </div>
                        @else
                            <div class="mb-3 rounded-xl border border-dashed border-gray-700 bg-gray-900/20 p-3 text-xs text-gray-500">
                                No benchmark stored yet.
                            </div>
                        @endif

                        <div class="flex items-center gap-2 opacity-0 group-hover:opacity-100 transition-opacity duration-150">
                            <button
                                wire:click="openEditPanel('{{ $provider }}', '{{ $modelId }}')"
                                class="p-1.5 rounded-lg hover:bg-gray-600 text-gray-400 hover:text-gray-200 transition"
                                title="Edit"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path>
                                </svg>
                            </button>
                            <button
                                wire:click="testModel('{{ $provider }}', '{{ $modelId }}')"
                                wire:loading.attr="disabled"
                                class="p-1.5 rounded-lg hover:bg-gray-600 text-gray-400 hover:text-green-300 transition"
                                title="Test"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </button>
                            <button
                                wire:click="removeModel('{{ $provider }}', '{{ $modelId }}')"
                                wire:confirm="Remove this model?"
                                class="p-1.5 rounded-lg hover:bg-gray-600 text-gray-400 hover:text-red-300 transition"
                                title="Delete"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                </svg>
                            </button>
                        </div>
                    </div>
                @endforeach
            @endforeach
        </div>
    @else
        <div class="text-center py-16">
            <svg class="w-16 h-16 mx-auto mb-4 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"></path>
            </svg>
            @if($search !== '')
                <h3 class="text-lg font-medium text-gray-400">No models match your search</h3>
                <p class="mt-2 text-gray-500">Try adjusting your search query.</p>
            @else
                <h3 class="text-lg font-medium text-gray-400">No models configured</h3>
                <p class="mt-2 text-gray-500">Add your first AI model to get started.</p>
            @endif
        </div>
    @endif

    <div
        x-show="$wire.showPanel"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="translate-x-full opacity-0"
        x-transition:enter-end="translate-x-0 opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="translate-x-0 opacity-100"
        x-transition:leave-end="translate-x-full opacity-0"
        x-cloak
        class="fixed top-0 right-0 h-full w-96 bg-gray-800 border-l border-gray-700 shadow-2xl z-50 flex flex-col"
    >
        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-700">
            <h3 class="text-base font-semibold text-gray-100">{{ $panelMode === 'add' ? 'Add Model' : 'Edit Model' }}</h3>
            <button
                wire:click="closePanel"
                class="p-1.5 rounded-lg hover:bg-gray-700 text-gray-400 hover:text-gray-200 transition-colors"
            >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>

        <div class="flex-1 overflow-y-auto px-5 py-4 space-y-5">
            @if($panelMode === 'add')
                <div>
                    <label class="block text-xs font-medium text-gray-400 uppercase mb-1.5">Provider</label>
                    <select
                        wire:model="formProvider"
                        class="bg-gray-700 border border-gray-600 rounded-lg px-3 py-2 text-sm text-gray-100 focus:ring-2 focus:ring-indigo-500 focus:border-transparent w-full"
                    >
                        @foreach($providers as $provider)
                            <option value="{{ $provider }}">{{ ucfirst(str_replace('-', ' ', $provider)) }}</option>
                        @endforeach
                    </select>
                    @error('formProvider')
                        <p class="text-xs text-red-400 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-400 uppercase mb-1.5">Model ID</label>
                    <input
                        type="text"
                        wire:model="formModelId"
                        placeholder="e.g. gpt-4o, claude-3-opus"
                        class="bg-gray-700 border border-gray-600 rounded-lg px-3 py-2 text-sm text-gray-100 focus:ring-2 focus:ring-indigo-500 focus:border-transparent w-full placeholder-gray-500"
                    >
                    @error('formModelId')
                        <p class="text-xs text-red-400 mt-1">{{ $message }}</p>
                    @enderror
                </div>
            @else
                <div>
                    <label class="block text-xs font-medium text-gray-400 uppercase mb-1.5">Provider</label>
                    <p class="text-sm text-gray-300 bg-gray-700/40 rounded-lg px-3 py-2">{{ ucfirst(str_replace('-', ' ', $formProvider)) }}</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-400 uppercase mb-1.5">Model ID</label>
                    <p class="text-sm text-gray-300 font-mono bg-gray-700/40 rounded-lg px-3 py-2">{{ $formModelId }}</p>
                </div>
            @endif

            <div>
                <label class="block text-xs font-medium text-gray-400 uppercase mb-1.5">Display Name</label>
                <input
                    type="text"
                    wire:model="formName"
                    placeholder="e.g. GPT-4o, Claude 3 Opus"
                    class="bg-gray-700 border border-gray-600 rounded-lg px-3 py-2 text-sm text-gray-100 focus:ring-2 focus:ring-indigo-500 focus:border-transparent w-full placeholder-gray-500"
                >
                @error('formName')
                    <p class="text-xs text-red-400 mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-400 uppercase mb-1.5">Context Window (tokens)</label>
                <input
                    type="number"
                    wire:model="formContext"
                    placeholder="128000"
                    class="bg-gray-700 border border-gray-600 rounded-lg px-3 py-2 text-sm text-gray-100 focus:ring-2 focus:ring-indigo-500 focus:border-transparent w-full placeholder-gray-500"
                >
                @error('formContext')
                    <p class="text-xs text-red-400 mt-1">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <div class="px-5 py-4 border-t border-gray-700 flex items-center gap-3">
            <button
                wire:click="save"
                wire:loading.attr="disabled"
                class="flex-1 px-4 py-2 text-sm font-medium rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white transition-colors disabled:opacity-50"
            >
                <span wire:loading.remove wire:target="save">Save</span>
                <span wire:loading wire:target="save">Saving...</span>
            </button>
            <button
                wire:click="closePanel"
                class="px-4 py-2 text-sm font-medium rounded-lg bg-gray-700 hover:bg-gray-600 text-gray-300 transition-colors"
            >
                Cancel
            </button>
        </div>
    </div>

    <div
        x-show="$wire.showPanel"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        x-cloak
        class="fixed inset-0 bg-black/50 z-40"
        wire:click="closePanel"
    ></div>
</div>
