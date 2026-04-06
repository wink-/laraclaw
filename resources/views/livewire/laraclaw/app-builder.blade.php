<?php

use App\Laraclaw\Modules\ModuleManager;
use App\Laraclaw\Skills\AppBuilderSkill;
use Illuminate\View\View;
use Livewire\Volt\Component;

new class extends Component
{
    public ?string $moduleStatus = null;

    public string $newModuleName = '';

    public string $newModuleDescription = '';

    /**
     * @var array<int, array<string, mixed>>
     */
    public array $modules = [];

    /**
     * @var array<string, string>
     */
    public array $moduleDomainInputs = [];

    public function mount(): void
    {
        $this->loadModules();
    }

    public function createModuleApp(): void
    {
        $this->validate([
            'newModuleName' => ['required', 'string', 'max:120'],
            'newModuleDescription' => ['nullable', 'string', 'max:255'],
        ]);

        $builder = app(AppBuilderSkill::class);

        $this->moduleStatus = $builder->execute([
            'action' => 'create_app',
            'name' => $this->newModuleName,
            'description' => $this->newModuleDescription,
            'type' => 'blog',
        ]);

        $this->newModuleName = '';
        $this->newModuleDescription = '';

        $this->loadModules();
    }

    public function bindModuleDomain(string $slug): void
    {
        $builder = app(AppBuilderSkill::class);
        $domain = trim($this->moduleDomainInputs[$slug] ?? '');

        $this->moduleStatus = $builder->execute([
            'action' => 'set_domain',
            'slug' => $slug,
            'domain' => $domain,
        ]);

        $this->loadModules();
    }

    protected function loadModules(): void
    {
        if (! config('laraclaw.modules.enabled', true)) {
            $this->modules = [];

            return;
        }

        $manager = app(ModuleManager::class);

        $this->modules = $manager->allModules();

        foreach ($this->modules as $module) {
            $this->moduleDomainInputs[$module['slug']] = (string) ($module['domain'] ?? '');
        }
    }

    public function rendering(View $view): void
    {
        $view->layout('components.laraclaw.layout');
    }
}; ?>

<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-100">App Builder</h1>
        <p class="text-gray-400">Generate Laravel MVC modules with AI</p>
    </div>

    @if($moduleStatus)
        <div class="px-4 py-3 bg-indigo-600/20 text-indigo-300 rounded-lg text-sm">
            {{ $moduleStatus }}
        </div>
    @endif

    <div class="bg-gray-800 rounded-xl border border-gray-700 p-6">
        <h2 class="text-lg font-semibold text-gray-100 mb-4">Create New Module</h2>

        <form wire:submit="createModuleApp" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-400 mb-1">App Name</label>
                <input
                    type="text"
                    wire:model="newModuleName"
                    placeholder="e.g. Home Blog, Product Catalog, Team Wiki"
                    class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2.5 text-sm text-gray-100 focus:ring-2 focus:ring-indigo-500 focus:border-transparent placeholder-gray-500"
                >
                @error('newModuleName')
                    <p class="text-sm text-red-400 mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-400 mb-1">Description <span class="text-gray-600">(optional)</span></label>
                <input
                    type="text"
                    wire:model="newModuleDescription"
                    placeholder="Brief description of the module"
                    class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2.5 text-sm text-gray-100 focus:ring-2 focus:ring-indigo-500 focus:border-transparent placeholder-gray-500"
                >
            </div>

            <button type="submit" class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 rounded-lg font-medium transition">
                Generate Module
            </button>
        </form>
    </div>

    <div class="bg-gray-800 rounded-xl border border-gray-700 p-6">
        <h2 class="text-lg font-semibold text-gray-100 mb-4">Existing Modules</h2>

        @if(empty($modules))
            <div class="text-center py-12">
                <svg class="w-16 h-16 mx-auto mb-4 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"></path>
                </svg>
                <h3 class="text-lg font-medium text-gray-400">No modules generated yet</h3>
                <p class="mt-2 text-gray-500">Create your first module above to get started.</p>
            </div>
        @else
            <div class="space-y-4">
                @foreach($modules as $module)
                    <div class="rounded-xl bg-gray-700/40 p-4 space-y-3 border border-gray-700">
                        <div class="flex items-center justify-between gap-4">
                            <div class="min-w-0">
                                <h3 class="text-sm font-semibold text-gray-100">{{ $module['name'] }}</h3>
                                <p class="text-xs text-gray-400 mt-0.5">
                                    Slug: <span class="text-indigo-400">{{ $module['slug'] }}</span> &middot;
                                    Route: <span class="text-gray-300">{{ $module['domain'] ? $module['domain'] : '/' . $module['prefix'] }}</span>
                                </p>
                                @if($module['model_class'] ?? null)
                                    <p class="text-xs text-gray-500 mt-0.5">Model: {{ $module['model_class'] }}</p>
                                @endif
                            </div>
                            <a href="/{{ $module['prefix'] }}" class="px-3 py-1.5 text-xs bg-indigo-600/20 text-indigo-300 hover:bg-indigo-600/30 rounded-lg transition whitespace-nowrap">
                                Open &rarr;
                            </a>
                        </div>

                        <div class="flex items-center gap-2">
                            <input
                                type="text"
                                wire:model="moduleDomainInputs.{{ $module['slug'] }}"
                                placeholder="Custom domain (optional)"
                                class="flex-1 bg-gray-700 border border-gray-600 rounded-lg px-3 py-2 text-xs text-gray-100 focus:ring-2 focus:ring-indigo-500 focus:border-transparent placeholder-gray-500"
                            >
                            <button
                                type="button"
                                wire:click="bindModuleDomain('{{ $module['slug'] }}')"
                                class="px-4 py-2 text-xs bg-blue-600 hover:bg-blue-700 rounded-lg font-medium transition whitespace-nowrap"
                            >
                                Save Domain
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
