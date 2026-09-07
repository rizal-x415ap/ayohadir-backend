<?php

namespace App\Console\Commands;

use App\Models\Template;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class ClearTemplatesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'templates:clear {--force : Paksa hapus tanpa konfirmasi interaktif}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Mengosongkan seluruh data template master dari database';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (!$this->option('force') && !$this->confirm('Apakah Anda yakin ingin mengosongkan seluruh template dari database?')) {
            $this->info('Operasi dibatalkan.');
            return 0;
        }

        $count = Template::count();

        Schema::disableForeignKeyConstraints();
        Template::truncate();
        Schema::enableForeignKeyConstraints();

        $this->info("✓ Berhasil mengosongkan {$count} template dari database.");
        return 0;
    }
}
