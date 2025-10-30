<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Artisan;

class GenerateAllSeeders extends Command
{
    protected $signature = 'make:allseeders';
    protected $description = 'Generate iSeed files for all tables in current database';

    public function handle()
    {
        $tables = DB::select('SHOW TABLES');
        $dbName = DB::getDatabaseName();
        $key = "Tables_in_{$dbName}";

        $tableNames = array_map(fn($t) => $t->$key, $tables);
        $tablesList = implode(',', $tableNames);

        $this->info("Generating seeders for tables: $tablesList");

        Artisan::call("iseed {$tablesList} --force");
        $this->info("✅ All seeders generated successfully!");
    }
}
