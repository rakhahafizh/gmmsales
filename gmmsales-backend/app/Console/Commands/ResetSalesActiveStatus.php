<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class ResetSalesActiveStatus extends Command
{
    protected $signature = 'sales:reset-active';

    protected $description = 'Reset is_active = false untuk seluruh user dengan role sales (dijalankan tiap tengah malam)';

    public function handle(): int
    {
        $affected = User::where('role', 'sales')
            ->where('is_active', true)
            ->update(['is_active' => false]);

        $this->info("Berhasil mereset is_active untuk {$affected} sales.");

        return self::SUCCESS;
    }
}