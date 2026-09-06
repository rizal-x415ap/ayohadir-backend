<?php

namespace App\Console\Commands;

use App\Models\Wedding;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PurgeTrashedWeddings extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'weddings:purge-trashed {--days=7 : Jumlah hari masa retensi kotak sampah sebelum dihapus permanen}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Hapus permanen proyek undangan yang telah kedaluwarsa di kotak sampah beserta seluruh file storage medianya';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $days = (int) $this->option('days') ?: 7;
        $threshold = now()->subDays($days);

        $expiredWeddings = Wedding::onlyTrashed()
            ->where('deleted_at', '<=', $threshold)
            ->get();

        $count = $expiredWeddings->count();
        if ($count === 0) {
            $this->info("Tidak ada undangan kedaluwarsa di kotak sampah (> {$days} hari).");
            return Command::SUCCESS;
        }

        $this->info("Ditemukan {$count} undangan kedaluwarsa untuk dihapus permanen...");

        foreach ($expiredWeddings as $wedding) {
            // 1. Wipe media directory in storage disk
            $mediaDir = "media/weddings/{$wedding->id}";
            if (Storage::disk('public')->exists($mediaDir)) {
                Storage::disk('public')->deleteDirectory($mediaDir);
            }

            // 2. Permanently delete wedding record (cascades to child tables)
            $wedding->forceDelete();

            $this->line(" - Undangan ID #{$wedding->id} ({$wedding->bride_name} & {$wedding->groom_name}) dan media storage berhasil dihapus permanen.");
        }

        $this->info("Pembersihan selesai. {$count} undangan telah dihapus permanen dari sistem.");

        return Command::SUCCESS;
    }
}
