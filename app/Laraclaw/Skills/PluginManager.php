<?php

namespace App\Laraclaw\Skills;

use App\Models\SkillPlugin;
use Illuminate\Support\Facades\Schema;

class PluginManager
{
    /**
     * @param  array<int, string>  $defaultSkillClasses
     */
    public function __construct(protected array $defaultSkillClasses = [])
    {
        if (empty($this->defaultSkillClasses)) {
            $this->defaultSkillClasses = [
                TimeSkill::class,
                CalculatorSkill::class,
                WebSearchSkill::class,
                MemorySkill::class,
                FileSystemSkill::class,
                ExecuteSkill::class,
                EmailSkill::class,
                CalendarSkill::class,
                SchedulerSkill::class,
            ];
        }
    }

    /**
     * @param  array<int, string>|null  $skillClasses
     * @return array<int, string>
     */
    public function enabledSkillClasses(?array $skillClasses = null): array
    {
        $classes = $skillClasses ?? $this->defaultSkillClasses;

        if (! Schema::hasTable('skill_plugins')) {
            return $classes;
        }

        $this->syncSkills($classes);

        $enabled = SkillPlugin::query()
            ->whereIn('class_name', $classes)
            ->where('enabled', true)
            ->pluck('class_name')
            ->all();

        return ! empty($enabled) ? $enabled : $classes;
    }

    /**
     * @param  array<int, string>|null  $skillClasses
     * @return array<int, array<string, mixed>>
     */
    public function listSkills(?array $skillClasses = null): array
    {
        $classes = $skillClasses ?? $this->defaultSkillClasses;

        if (! Schema::hasTable('skill_plugins')) {
            return collect($classes)->map(fn (string $class) => [
                'name' => class_basename($class),
                'class_name' => $class,
                'description' => null,
                'enabled' => true,
            ])->all();
        }

        $this->syncSkills($classes);

        return SkillPlugin::query()
            ->whereIn('class_name', $classes)
            ->orderBy('name')
            ->get(['name', 'class_name', 'description', 'enabled'])
            ->toArray();
    }

    public function setEnabled(string $className, bool $enabled): void
    {
        if (! Schema::hasTable('skill_plugins')) {
            return;
        }

        SkillPlugin::query()
            ->where('class_name', $className)
            ->update(['enabled' => $enabled]);
    }

    /**
     * @param  array<int, string>  $skillClasses
     */
    protected function syncSkills(array $skillClasses): void
    {
        foreach ($skillClasses as $className) {
            $name = class_basename($className);

            SkillPlugin::query()->firstOrCreate(
                ['class_name' => $className],
                [
                    'name' => $name,
                    'description' => null,
                    'enabled' => true,
                    'metadata' => null,
                ]
            );
        }
    }
}
