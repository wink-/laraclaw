<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Laraclaw\Facades\Laraclaw;
use Illuminate\Http\Request;

class SkillController extends Controller
{
    public function index(): array
    {
        return [
            'skills' => Laraclaw::listSkills(),
        ];
    }

    public function update(Request $request): array
    {
        $validated = $request->validate([
            'class_name' => ['required', 'string'],
            'enabled' => ['required', 'boolean'],
        ]);

        try {
            Laraclaw::setSkillEnabled($validated['class_name'], (bool) $validated['enabled']);
        } catch (\RuntimeException $e) {
            return [
                'message' => $e->getMessage(),
            ];
        }

        return [
            'message' => 'Skill updated.',
            'skills' => Laraclaw::listSkills(),
        ];
    }
}
