<?php

namespace App\Laraclaw\Skills;

use App\Laraclaw\Security\SecurityManager;
use App\Laraclaw\Skills\Contracts\SkillInterface;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\File;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class FileSystemSkill implements SkillInterface, Tool
{
    protected string $basePath;

    protected bool $readOnly = false;

    public function __construct(
        protected SecurityManager $security
    ) {
        $this->basePath = $security->getFilesystemScope();
        $this->readOnly = ! $security->getAutonomyLevel()->canWrite();
    }

    public function name(): string
    {
        return 'filesystem';
    }

    public function description(): Stringable|string
    {
        return 'Read, list, and manage files within the allowed directory scope. Use this to access files, list directories, or create simple files.';
    }

    public function execute(array $parameters): string
    {
        $action = $parameters['action'] ?? 'list';

        // Check write permissions for write operations
        if (in_array($action, ['write', 'delete', 'mkdir']) && $this->readOnly) {
            return 'Error: Filesystem is in read-only mode due to security settings.';
        }

        return match ($action) {
            'read' => $this->readFile($parameters),
            'list' => $this->listDirectory($parameters),
            'write' => $this->writeFile($parameters),
            'delete' => $this->deleteFile($parameters),
            'mkdir' => $this->makeDirectory($parameters),
            'exists' => $this->fileExists($parameters),
            'info' => $this->fileInfo($parameters),
            default => "Unknown action: {$action}. Use 'read', 'list', 'write', 'delete', 'mkdir', 'exists', or 'info'.",
        };
    }

    protected function resolvePath(string $path): string
    {
        // Remove leading slashes to prevent absolute path injection
        $path = ltrim($path, '/');

        // Combine with base path
        $fullPath = $this->basePath.'/'.$path;

        // Resolve and verify it's within scope
        $realBase = realpath($this->basePath);
        $realPath = realpath(dirname($fullPath));

        if ($realPath === false || ! str_starts_with($realPath, $realBase)) {
            return '';
        }

        return $fullPath;
    }

    protected function isPathSafe(string $path): bool
    {
        $resolved = $this->resolvePath($path);

        return ! empty($resolved);
    }

    protected function readFile(array $parameters): string
    {
        $path = $parameters['path'] ?? '';

        if (! $this->isPathSafe($path)) {
            return 'Error: Access denied. Path is outside allowed scope.';
        }

        $fullPath = $this->resolvePath($path);

        if (! File::exists($fullPath)) {
            return "Error: File not found: {$path}";
        }

        if (! File::isFile($fullPath)) {
            return "Error: Not a file: {$path}";
        }

        // Check file size (limit to 1MB for safety)
        $size = File::size($fullPath);
        if ($size > 1048576) {
            return "Error: File too large ({$size} bytes). Maximum size is 1MB.";
        }

        $content = File::get($fullPath);

        return "Contents of {$path}:\n".$content;
    }

    protected function listDirectory(array $parameters): string
    {
        $path = $parameters['path'] ?? '';

        if (! $this->isPathSafe($path)) {
            return 'Error: Access denied. Path is outside allowed scope.';
        }

        $fullPath = $this->resolvePath($path);

        if (! File::exists($fullPath)) {
            return "Error: Directory not found: {$path}";
        }

        if (! File::isDirectory($fullPath)) {
            return "Error: Not a directory: {$path}";
        }

        $items = File::files($fullPath);
        $dirs = File::directories($fullPath);

        $output = "Contents of {$path}:\n\n";

        if (! empty($dirs)) {
            $output .= "Directories:\n";
            foreach ($dirs as $dir) {
                $output .= '  📁 '.basename($dir)."\n";
            }
            $output .= "\n";
        }

        if (! empty($items)) {
            $output .= "Files:\n";
            foreach ($items as $file) {
                $size = $this->formatSize($file->getSize());
                $output .= '  📄 '.$file->getBasename()." ({$size})\n";
            }
        }

        if (empty($dirs) && empty($items)) {
            $output .= "(empty directory)\n";
        }

        return $output;
    }

    protected function writeFile(array $parameters): string
    {
        $path = $parameters['path'] ?? '';
        $content = $parameters['content'] ?? '';

        if (! $this->isPathSafe($path)) {
            return 'Error: Access denied. Path is outside allowed scope.';
        }

        $fullPath = $this->resolvePath($path);

        // Check if file already exists
        if (File::exists($fullPath)) {
            return "Error: File already exists: {$path}. Use a different name.";
        }

        // Ensure parent directory exists
        $parentDir = dirname($fullPath);
        if (! File::isDirectory($parentDir)) {
            return "Error: Parent directory does not exist: {$path}";
        }

        // Write the file
        File::put($fullPath, $content);

        return "Successfully created file: {$path} (".strlen($content).' bytes)';
    }

    protected function deleteFile(array $parameters): string
    {
        $path = $parameters['path'] ?? '';

        if (! $this->isPathSafe($path)) {
            return 'Error: Access denied. Path is outside allowed scope.';
        }

        $fullPath = $this->resolvePath($path);

        if (! File::exists($fullPath)) {
            return "Error: File not found: {$path}";
        }

        // Only allow deleting files, not directories
        if (File::isDirectory($fullPath)) {
            return 'Error: Cannot delete directories. Only files are allowed.';
        }

        File::delete($fullPath);

        return "Successfully deleted file: {$path}";
    }

    protected function makeDirectory(array $parameters): string
    {
        $path = $parameters['path'] ?? '';

        if (! $this->isPathSafe($path)) {
            return 'Error: Access denied. Path is outside allowed scope.';
        }

        $fullPath = $this->resolvePath($path);

        if (File::exists($fullPath)) {
            return "Error: Already exists: {$path}";
        }

        File::makeDirectory($fullPath, 0755, true);

        return "Successfully created directory: {$path}";
    }

    protected function fileExists(array $parameters): string
    {
        $path = $parameters['path'] ?? '';

        if (! $this->isPathSafe($path)) {
            return 'Error: Access denied. Path is outside allowed scope.';
        }

        $fullPath = $this->resolvePath($path);

        $exists = File::exists($fullPath);

        return $exists ? "Yes, {$path} exists." : "No, {$path} does not exist.";
    }

    protected function fileInfo(array $parameters): string
    {
        $path = $parameters['path'] ?? '';

        if (! $this->isPathSafe($path)) {
            return 'Error: Access denied. Path is outside allowed scope.';
        }

        $fullPath = $this->resolvePath($path);

        if (! File::exists($fullPath)) {
            return "Error: File not found: {$path}";
        }

        $isDir = File::isDirectory($fullPath);
        $type = $isDir ? 'Directory' : 'File';

        $info = "{$type}: {$path}\n";
        $info .= 'Size: '.$this->formatSize(File::size($fullPath))."\n";
        $info .= 'Modified: '.date('Y-m-d H:i:s', File::lastModified($fullPath))."\n";

        if (! $isDir) {
            $info .= 'Extension: '.pathinfo($fullPath, PATHINFO_EXTENSION)."\n";
        }

        return $info;
    }

    protected function formatSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;

        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 1).' '.$units[$i];
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema->string()
                ->enum(['read', 'list', 'write', 'delete', 'mkdir', 'exists', 'info'])
                ->description('The filesystem action to perform'),
            'path' => $schema->string()
                ->description('The relative path (within allowed scope)'),
            'content' => $schema->string()
                ->description('Content to write (for write action)'),
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
