<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class MigrateTenants extends Command
{
    protected $signature   = 'migrate:tenants {--fresh : Drop all tables and re-run migrations} {--seed : Seed after migrating} {--tenant= : Only migrate this tenant host (e.g. hero-spare.lumac.cc)}';
    protected $description = 'Run migrations against every tenant database defined in config/tenants.php';

    public function handle(): int
    {
        $tenants  = config('tenants.tenants', []);
        $shared   = [
            'host'     => config('tenants.db_host'),
            'port'     => config('tenants.db_port'),
            'username' => config('tenants.db_username'),
            'password' => config('tenants.db_password'),
        ];

        if (empty($tenants)) {
            $this->warn('No tenants configured in config/tenants.php');
            return 0;
        }

        $only = $this->option('tenant');
        if ($only && !isset($tenants[$only])) {
            $this->error("Tenant '{$only}' not found in config/tenants.php");
            return 1;
        }

        foreach ($tenants as $host => $tenant) {
            if ($only && $host !== $only) continue;
            $db = $tenant['db_database'];
            $this->info("── Migrating tenant: {$host} (database: {$db})");

            Config::set('database.connections.mysql.host',     $tenant['db_host']     ?? $shared['host']);
            Config::set('database.connections.mysql.port',     $tenant['db_port']     ?? $shared['port']);
            Config::set('database.connections.mysql.username', $tenant['db_username'] ?? $shared['username']);
            Config::set('database.connections.mysql.password', $tenant['db_password'] ?? $shared['password']);
            Config::set('database.connections.mysql.database', $db);

            DB::purge('mysql');
            DB::reconnect('mysql');

            $command = $this->option('fresh') ? 'migrate:fresh' : 'migrate';
            $args    = ['--force' => true];
            if ($this->option('seed')) $args['--seed'] = true;

            $this->call($command, $args);
        }

        $this->info('All tenants migrated.');
        return 0;
    }
}
