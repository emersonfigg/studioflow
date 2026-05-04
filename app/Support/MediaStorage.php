<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MediaStorage
{
    public static function diskName(): string
    {
        $disk = (string) config('filesystems.default', 'public');

        return $disk === 'local' ? 'public' : $disk;
    }

    public static function putFile(string $directory, UploadedFile $file): string
    {
        return $file->store($directory, self::diskName());
    }

    public static function put(string $path, string $contents): bool
    {
        return Storage::disk(self::diskName())->put($path, $contents);
    }

    /**
     * @param  string|array<int, string|null>|null  $paths
     */
    public static function delete(string|array|null $paths): void
    {
        $paths = is_array($paths) ? $paths : [$paths];
        $paths = array_values(array_unique(array_filter($paths)));

        if ($paths === []) {
            return;
        }

        Storage::disk(self::diskName())->delete($paths);

        if (self::diskName() !== 'public') {
            Storage::disk('public')->delete($paths);
        }
    }

    public static function url(?string $path): ?string
    {
        $path = self::normalizePath($path);

        if (! $path) {
            return null;
        }

        $disk = Storage::disk(self::diskName());

        if ($disk->exists($path)) {
            return $disk->url($path);
        }

        if (self::diskName() !== 'public' && Storage::disk('public')->exists($path)) {
            return Storage::disk('public')->url($path);
        }

        return null;
    }

    public static function normalizePath(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        return ltrim(Str::replaceFirst('storage/', '', str_replace('\\', '/', $path)), '/');
    }
}
