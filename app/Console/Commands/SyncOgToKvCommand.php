<?php

namespace App\Console\Commands;

use App\Models\Template;
use App\Models\Wedding;
use App\Services\CloudflareOgSyncService;
use Illuminate\Console\Command;

class SyncOgToKvCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'og:sync-kv {--slug= : Specific slug to sync}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync Open Graph metadata for templates and weddings to Cloudflare KV for 0ms instant edge rendering';

    /**
     * Execute the console command.
     */
    public function handle(CloudflareOgSyncService $syncService): int
    {
        $specificSlug = $this->option('slug');

        $this->info('Starting Cloudflare KV OG synchronization...');

        // 1. Templates
        $templateQuery = Template::where('is_active', true);
        if ($specificSlug) {
            $templateQuery->where('slug', $specificSlug);
        }
        $templates = $templateQuery->get();

        foreach ($templates as $template) {
            $this->output->write("Syncing template [{$template->slug}]... ");
            $ok = $syncService->syncTemplate($template);
            if ($ok) {
                $this->info('DONE');
            } else {
                $this->warn('FAILED / SKIPPED');
            }
        }

        // 2. Weddings
        $weddingQuery = Wedding::where('status', 'published');
        if ($specificSlug) {
            $weddingQuery->where('slug', $specificSlug);
        }
        $weddings = $weddingQuery->get();

        foreach ($weddings as $wedding) {
            $this->output->write("Syncing wedding [{$wedding->slug}]... ");
            $ok = $syncService->syncWedding($wedding);
            if ($ok) {
                $this->info('DONE');
            } else {
                $this->warn('FAILED / SKIPPED');
            }
        }

        $this->info('Cloudflare KV OG synchronization finished.');

        return Command::SUCCESS;
    }
}
