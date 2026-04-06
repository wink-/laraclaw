<?php

use App\Laraclaw\Agents\CoreAgent;
use App\Laraclaw\AI\ProviderCatalog;
use Illuminate\View\View;
use Livewire\Volt\Component;

new class extends Component
{
    public bool $showPanel = false;

    public string $panelMode = 'add';

    public string $formKey = '';

    public string $formName = '';

    public string $formDriver = 'openai';

    public string $formKeyEnv = '';

    public ?string $formUrl = null;

    public ?string $status = null;

    public array $testResults = [];

    public function openAddPanel(): void
    {
        $this->panelMode = 'add';
        $this->formKey = '';
        $this->formName = '';
        $this->formDriver = 'openai';
        $this->formKeyEnv = '';
        $this->formUrl = null;
        $this->showPanel = true;
    }

    public function openEditPanel(string $key): void
    {
        $catalog = $this->getCatalog();
        $provider = $catalog->get($key);

        if (! $provider) {
            $this->status = 'Provider not found.';

            return;
        }

        $this->panelMode = 'edit';
        $this->formKey = $key;
        $this->formName = $provider['name'];
        $this->formDriver = $provider['driver'];
        $this->formKeyEnv = $provider['key_env'];
        $this->formUrl = $provider['url'];
        $this->showPanel = true;
    }

    public function save(): void
    {
        $rules = [
            'formName' => ['required', 'string', 'max:120'],
            'formDriver' => ['required', 'string', 'in:' . implode(',', array_keys(app(ProviderCatalog::class)->getAvailableDrivers()))],
            'formKeyEnv' => ['required', 'string', 'max:120'],
            'formUrl' => ['nullable', 'string', 'max:500'],
        ];

        if ($this->panelMode === 'add') {
            $rules['formKey'] = ['required', 'string', 'max:60', 'regex:/^[a-z0-9][a-z0-9\-]*[a-z0-9]$/', 'not_in:' . implode(',', $this->builtInProviders())];
        }

        $this->validate($rules);

        $catalog = $this->getCatalog();

        if ($this->panelMode === 'add') {
            if ($catalog->has($this->formKey)) {
                $this->addError('formKey', 'A provider with this key already exists.');

                return;
            }

            $catalog->addProvider($this->formKey, $this->formName, $this->formDriver, $this->formKeyEnv, $this->formUrl);
            $this->status = "Added provider {$this->formName}.";
        } else {
            $catalog->updateProvider($this->formKey, $this->formName, $this->formDriver, $this->formKeyEnv, $this->formUrl);
            $this->status = "Updated provider {$this->formName}.";
        }

        $this->reset('showPanel', 'panelMode', 'formKey', 'formName', 'formDriver', 'formKeyEnv', 'formUrl');
    }

    public function closePanel(): void
    {
        $this->reset('showPanel', 'panelMode', 'formKey', 'formName', 'formDriver', 'formKeyEnv', 'formUrl');
    }

    public function removeProvider(string $key): void
    {
        $this->getCatalog()->removeProvider($key);
        $this->status = "Removed provider {$key}.";
    }

    public function testProvider(string $key): void
    {
        $catalog = $this->getCatalog();
        $provider = $catalog->get($key);

        if (! $provider) {
            $this->testResults[$key] = [
                'text' => 'Provider not found.',
                'time' => 0,
                'status' => 'fail',
            ];

            return;
        }

        $agent = app(CoreAgent::class);
        $originalProvider = $agent->provider()->value;
        $originalModel = $agent->model();

        $start = microtime(true);

        try {
            $catalog->registerProvider($key);
            $agent->applyProviderOverride($key);

            $response = $agent->prompt('Hello, respond in one sentence.');
            $elapsed = round((microtime(true) - $start) * 1000);

            $text = (string) $response;
            if (mb_strlen($text) > 300) {
                $text = mb_substr($text, 0, 300) . '...';
            }

            $this->testResults[$key] = [
                'text' => $text,
                'time' => $elapsed,
                'status' => 'pass',
            ];
        } catch (Throwable $e) {
            $elapsed = round((microtime(true) - $start) * 1000);

            $this->testResults[$key] = [
                'text' => $e->getMessage(),
                'time' => $elapsed,
                'status' => 'fail',
            ];
        } finally {
            $agent->applyProviderOverride($originalProvider);
            $agent->applyModelOverride($originalModel);
        }
    }

    public function with(): array
    {
        $catalog = $this->getCatalog();
        $drivers = $catalog->getAvailableDrivers();

        return [
            'providers' => $catalog->all(),
            'drivers' => $drivers,
        ];
    }

    public function rendering(View $view): void
    {
        $view->layout('components.laraclaw.layout');
    }

    protected function getCatalog(): ProviderCatalog
    {
        return app(ProviderCatalog::class);
    }

    protected function builtInProviders(): array
    {
        return ProviderCatalog::BUILT_IN_PROVIDERS;
    }
}; ?>

<div class="space-y-6">
    <div class="flex justify-between items-start">
        <div>
            <h1 class="text-2xl font-bold text-gray-100">Providers</h1>
            <p class="text-gray-400">Manage custom AI providers and connections</p>
        </div>
        <button
            wire:click="openAddPanel"
            class="bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg px-4 py-2 text-sm font-medium transition whitespace-nowrap"
        >
            Add Provider
        </button>
    </div>

    @if($status)
        @php
            $statusColors = match (true) {
                str_contains($status, 'Added'),
                str_contains($status, 'Updated') => 'bg-green-600/20 text-green-300',
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

    @if(!empty($providers))
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach($providers as $key => $provider)
                @php
                    $driverLabel = $drivers[$provider['driver']] ?? ucfirst($provider['driver']);
                    $hasKey = $this->getCatalog()->hasApiKey($key);
                @endphp
                <div class="bg-gray-800 rounded-xl border border-gray-700 p-4 hover:bg-gray-700/70 transition-colors duration-150 group" wire:key="provider-{{ $key }}">
                    <div class="flex items-start justify-between gap-3 mb-3">
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-semibold text-gray-100">{{ $provider['name'] }}</p>
                            <p class="text-xs font-mono text-gray-500 mt-0.5">{{ $key }}</p>
                        </div>
                        <span class="px-2 py-0.5 text-xs rounded {{ $hasKey ? 'bg-green-600/20 text-green-400' : 'bg-yellow-600/20 text-yellow-400' }}">
                            {{ $hasKey ? 'Key set' : 'Key missing' }}
                        </span>
                    </div>

                    <div class="flex items-center gap-2 mb-3">
                        <span class="px-2 py-0.5 text-xs rounded bg-indigo-600/20 text-indigo-400">{{ $driverLabel }}</span>
                        @if($provider['url'])
                            <span class="px-2 py-0.5 text-xs rounded bg-gray-700/40 text-gray-400 truncate max-w-40" title="{{ $provider['url'] }}">Custom URL</span>
                        @endif
                    </div>

                    <div class="text-xs text-gray-500 mb-3">
                        <span class="font-mono">{{ $provider['key_env'] }}</span>
                    </div>

                    @if(isset($testResults[$key]))
                        <div class="mb-3 bg-gray-700/40 rounded-lg p-3 text-xs space-y-1">
                            <div class="flex items-center justify-between">
                                <span class="font-medium {{ $testResults[$key]['status'] === 'pass' ? 'text-green-400' : 'text-red-400' }}">
                                    {{ strtoupper($testResults[$key]['status']) }}
                                </span>
                                <span class="text-gray-500">{{ $testResults[$key]['time'] }}ms</span>
                            </div>
                            <p class="text-gray-400 break-words">{{ $testResults[$key]['text'] }}</p>
                        </div>
                    @endif

                    <div class="flex items-center gap-2 opacity-0 group-hover:opacity-100 transition-opacity duration-150">
                        <button
                            wire:click="openEditPanel('{{ $key }}')"
                            class="p-1.5 rounded-lg hover:bg-gray-600 text-gray-400 hover:text-gray-200 transition"
                            title="Edit"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path>
                            </svg>
                        </button>
                        <button
                            wire:click="testProvider('{{ $key }}')"
                            wire:loading.attr="disabled"
                            class="p-1.5 rounded-lg hover:bg-gray-600 text-gray-400 hover:text-green-300 transition"
                            title="Test Connection"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </button>
                        <button
                            wire:click="removeProvider('{{ $key }}')"
                            wire:confirm="Remove this provider?"
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
        </div>
    @else
        <div class="text-center py-16">
            <svg class="w-16 h-16 mx-auto mb-4 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01"></path>
            </svg>
            <h3 class="text-lg font-medium text-gray-400">No custom providers</h3>
            <p class="mt-2 text-gray-500">Add a custom AI provider to connect to any compatible API.</p>
        </div>
    @endif

    {{-- Slide-over panel for add/edit --}}
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
            <h3 class="text-base font-semibold text-gray-100">{{ $panelMode === 'add' ? 'Add Provider' : 'Edit Provider' }}</h3>
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
                    <label class="block text-xs font-medium text-gray-400 uppercase mb-1.5">Provider Key</label>
                    <input
                        type="text"
                        wire:model="formKey"
                        placeholder="e.g. my-openai-proxy"
                        class="bg-gray-700 border border-gray-600 rounded-lg px-3 py-2 text-sm text-gray-100 focus:ring-2 focus:ring-indigo-500 focus:border-transparent w-full placeholder-gray-500"
                    >
                    <p class="text-xs text-gray-500 mt-1">Lowercase letters, numbers, and hyphens. Used as the provider identifier.</p>
                    @error('formKey')
                        <p class="text-xs text-red-400 mt-1">{{ $message }}</p>
                    @enderror
                </div>
            @else
                <div>
                    <label class="block text-xs font-medium text-gray-400 uppercase mb-1.5">Provider Key</label>
                    <p class="text-sm text-gray-300 font-mono bg-gray-700/40 rounded-lg px-3 py-2">{{ $formKey }}</p>
                </div>
            @endif

            <div>
                <label class="block text-xs font-medium text-gray-400 uppercase mb-1.5">Display Name</label>
                <input
                    type="text"
                    wire:model="formName"
                    placeholder="e.g. My OpenAI Proxy"
                    class="bg-gray-700 border border-gray-600 rounded-lg px-3 py-2 text-sm text-gray-100 focus:ring-2 focus:ring-indigo-500 focus:border-transparent w-full placeholder-gray-500"
                >
                @error('formName')
                    <p class="text-xs text-red-400 mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-400 uppercase mb-1.5">Driver</label>
                <select
                    wire:model="formDriver"
                    class="bg-gray-700 border border-gray-600 rounded-lg px-3 py-2 text-sm text-gray-100 focus:ring-2 focus:ring-indigo-500 focus:border-transparent w-full"
                >
                    @foreach($drivers as $driverKey => $driverLabel)
                        <option value="{{ $driverKey }}">{{ $driverLabel }}</option>
                    @endforeach
                </select>
                <p class="text-xs text-gray-500 mt-1">The underlying API protocol this provider uses.</p>
                @error('formDriver')
                    <p class="text-xs text-red-400 mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-400 uppercase mb-1.5">API Key Env Variable</label>
                <input
                    type="text"
                    wire:model="formKeyEnv"
                    placeholder="e.g. MY_PROVIDER_API_KEY"
                    class="bg-gray-700 border border-gray-600 rounded-lg px-3 py-2 text-sm text-gray-100 focus:ring-2 focus:ring-indigo-500 focus:border-transparent w-full placeholder-gray-500 font-mono"
                >
                <p class="text-xs text-gray-500 mt-1">The environment variable name that holds the API key. Add it to your <code class="text-gray-400">.env</code> file.</p>
                @error('formKeyEnv')
                    <p class="text-xs text-red-400 mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-400 uppercase mb-1.5">Base URL <span class="text-gray-600">(optional)</span></label>
                <input
                    type="text"
                    wire:model="formUrl"
                    placeholder="e.g. https://api.my-proxy.com/v1"
                    class="bg-gray-700 border border-gray-600 rounded-lg px-3 py-2 text-sm text-gray-100 focus:ring-2 focus:ring-indigo-500 focus:border-transparent w-full placeholder-gray-500"
                >
                <p class="text-xs text-gray-500 mt-1">Override the default API endpoint for this driver.</p>
                @error('formUrl')
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

    {{-- Backdrop overlay --}}
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
