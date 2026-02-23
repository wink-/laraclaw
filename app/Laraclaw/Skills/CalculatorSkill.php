<?php

namespace App\Laraclaw\Skills;

use App\Laraclaw\Skills\Contracts\SkillInterface;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class CalculatorSkill implements SkillInterface, Tool
{
    public function name(): string
    {
        return 'calculator';
    }

    public function description(): Stringable|string
    {
        return 'Perform mathematical calculations. Supports basic arithmetic operations (+, -, *, /) and more complex expressions.';
    }

    public function execute(array $parameters): string
    {
        $expression = $parameters['expression'] ?? '';

        if (empty($expression)) {
            return 'Error: No expression provided.';
        }

        // Sanitize the expression to only allow safe characters
        $sanitized = preg_replace('/[^0-9+\-*\/().%\s^]/', '', $expression);

        if ($sanitized !== $expression) {
            return 'Error: Expression contains invalid characters. Only numbers, +, -, *, /, (), %, ^ and spaces are allowed.';
        }

        try {
            // Convert ^ to ** for PHP
            $phpExpression = str_replace('^', '**', $sanitized);

            // Evaluate the expression safely
            $result = eval("return {$phpExpression};");

            if (! is_numeric($result)) {
                return "Error: Could not evaluate expression '{$expression}'.";
            }

            // Format the result nicely
            if (floor($result) == $result) {
                return "Result: {$expression} = {$result}";
            }

            $rounded = round($result, 10);

            return "Result: {$expression} = {$rounded}";
        } catch (\Throwable $e) {
            return "Error: Could not evaluate expression '{$expression}'. Please check the syntax.";
        }
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'expression' => $schema->string()->required()->description('The mathematical expression to evaluate (e.g., "2 + 2", "(10 * 5) / 2")'),
        ];
    }

    public function toTool(): Tool
    {
        return $this;
    }

    public function handle(Request $request): Stringable|string
    {
        return $this->execute($request->all());
    }
}
