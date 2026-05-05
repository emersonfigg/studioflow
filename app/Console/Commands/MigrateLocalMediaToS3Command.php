<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\Product;
use App\Models\Service;
use App\Models\User;
use App\Support\MediaStorage;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class MigrateLocalMediaToS3Command extends Command
{
    protected $signature = 'media:migrate-local-to-s3 {--dry-run : Apenas listar o que seria migrado}';

    protected $description = 'Migrate existing local public media files to the configured persistent filesystem disk.';

    public function handle(): int
    {
        $targetDisk = MediaStorage::diskName();

        if ($targetDisk === 'public') {
            $this->components->warn(
                'Disco de mídia é "public": arquivos no filesystem do container somem no redeploy sem volume. Para Railway configure FILESYSTEM_DISK=s3 ou FILESYSTEM_MEDIA_DISK=s3 (Cloudflare R2 / S3) antes de migrar.'
            );

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $uploaded = 0;
        $missing = 0;
        $skipped = 0;

        foreach ($this->mediaPaths() as $path) {
            if (Storage::disk($targetDisk)->exists($path)) {
                $skipped++;
                $this->line("Já existe no disco {$targetDisk}: {$path}");

                continue;
            }

            if (! Storage::disk('public')->exists($path)) {
                $missing++;
                $this->warn("Não encontrado localmente: {$path}");

                continue;
            }

            if (! $dryRun) {
                Storage::disk($targetDisk)->put(
                    $path,
                    Storage::disk('public')->get($path),
                    ['visibility' => 'public'],
                );
            }

            $uploaded++;
            $this->info(($dryRun ? 'Migraria' : 'Migrado').": {$path}");
        }

        $this->components->info("Concluído. Migrados: {$uploaded}. Já existentes: {$skipped}. Não encontrados: {$missing}.");

        return self::SUCCESS;
    }

    /**
     * @return Collection<int, string>
     */
    private function mediaPaths(): Collection
    {
        return collect()
            ->merge(Company::query()->pluck('logo')->map(fn (?string $path): ?string => MediaStorage::normalizePath($path)))
            ->merge(Product::query()->pluck('image_path')->map(fn (?string $path): ?string => MediaStorage::normalizePath($path)))
            ->merge(Service::query()->pluck('image_path')->map(fn (?string $path): ?string => MediaStorage::normalizePath($path)))
            ->merge(User::query()->pluck('photo_path')->map(fn (?string $path): ?string => MediaStorage::normalizePath($path)))
            ->filter()
            ->unique()
            ->values();
    }
}
