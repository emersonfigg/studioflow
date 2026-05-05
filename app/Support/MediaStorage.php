<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MediaStorage
{
    /**
     * Disco para uploads persistentes (produtos, logos, serviços, profissionais).
     */
    public static function diskName(): string
    {
        $configured = config('filesystems.media_disk');

        $disk = (is_string($configured) && trim($configured) !== '')
            ? trim($configured)
            : (string) config('filesystems.default', 'public');

        return $disk === 'local' ? 'public' : $disk;
    }

    public static function putFile(string $directory, UploadedFile $file): string|false
    {
        return $file->storePublicly($directory, ['disk' => self::diskName()]);
    }

    public static function put(string $path, string $contents): bool
    {
        return Storage::disk(self::diskName())->put($path, $contents, ['visibility' => 'public']);
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

    /**
     * URL absoluta ou raiz do site para servir a mídia; nunca usa APP_URL para S3/R2
     * (usa AWS_URL / disco ou, no "public" local, apenas o prefixo /storage).
     */
    public static function url(?string $path): ?string
    {
        $path = trim(str_replace('\\', '/', (string) $path));

        if ($path === '') {
            return null;
        }

        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }

        $path = self::normalizePath($path);

        if (! $path) {
            return null;
        }

        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }

        $diskName = self::diskName();
        $disk = Storage::disk($diskName);

        $configuredDriver = (string) config("filesystems.disks.{$diskName}.driver", 'local');

        /*
         * Produção: driver s3 → URL pública via AWS_URL ou endpoint S3, sem checar existência
         * (objeto pode existir no bucket mesmo se o container efêmero não enxergar).
         *
         * Tests: Storage::fake('s3') mantém driver "s3" na config, embora o adaptador seja local.
         */
        if ($configuredDriver === 's3') {
            return $disk->url($path);
        }

        if ($diskName === 'public') {
            return $disk->exists($path) ? self::publicDiskUrl($path) : null;
        }

        if ($disk->exists($path)) {
            return $disk->url($path);
        }

        if ($diskName !== 'public' && Storage::disk('public')->exists($path)) {
            return self::publicDiskUrl($path);
        }

        return null;
    }

    public static function normalizePath(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        $path = trim(str_replace('\\', '/', $path));

        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }

        return ltrim(Str::replaceFirst('storage/', '', $path), '/');
    }

    private static function publicDiskUrl(string $path): string
    {
        return '/storage/'.ltrim($path, '/');
    }
}
