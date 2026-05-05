<?php

namespace App\Support;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

final class ServiceImageLibrary
{
    public const RELATIVE_FOLDER = 'service-library/services';

    /**
     * @return list<string> caminhos relativos sob public/ ou storage/app/public (ex.: service-library/services/foo.svg)
     */
    public static function relativePaths(): array
    {
        $extensions = ['jpg', 'jpeg', 'png', 'webp', 'svg'];

        $fromStoragePublic = [];
        try {
            $fromStoragePublic = Storage::disk('public')->files(self::RELATIVE_FOLDER);
        } catch (\Throwable) {
            $fromStoragePublic = [];
        }

        $dir = public_path(str_replace('/', DIRECTORY_SEPARATOR, self::RELATIVE_FOLDER));
        $fromWebPublic = [];
        if (File::isDirectory($dir)) {
            $fromWebPublic = collect(File::files($dir))
                ->map(fn (\SplFileInfo $f): string => self::RELATIVE_FOLDER.'/'.ltrim(str_replace('\\', '/', $f->getFilename()), '/'))
                ->all();
        }

        return collect(array_merge($fromStoragePublic, $fromWebPublic))
            ->filter(fn (string $path): bool => in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), $extensions, true))
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /**
     * @throws \RuntimeException
     */
    public static function getContents(string $relativePath): string
    {
        if (Storage::disk('public')->exists($relativePath)) {
            return Storage::disk('public')->get($relativePath);
        }

        $publicFile = public_path(str_replace('\\', '/', ltrim(str_replace('\\', '/', $relativePath), '/')));
        if (File::isFile($publicFile)) {
            return File::get($publicFile);
        }

        throw new \RuntimeException('Biblioteca de servico nao encontrou o arquivo: '.$relativePath);
    }

    public static function publicWebUrl(string $relativePath): ?string
    {
        $normalized = MediaStorage::normalizePath($relativePath);
        if (! $normalized) {
            return null;
        }

        $publicFile = public_path(str_replace('/', DIRECTORY_SEPARATOR, ltrim(str_replace('\\', '/', $normalized), '/')));
        if (File::isFile($publicFile)) {
            return asset(str_replace('\\', '/', $normalized));
        }

        return null;
    }
}
