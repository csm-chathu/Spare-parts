<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class TenantMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $host    = $request->getHost();   // e.g. hero-spare.lumac.cc

        // Skip bare localhost or explicitly excluded hosts
        if ($host === 'localhost' || in_array($host, config('tenants.excluded', []))) {
            return $next($request);
        }

        $tenants = config('tenants.tenants', []);

        if (! array_key_exists($host, $tenants)) {
            abort(404, "Tenant '{$host}' not found.");
        }

        $tenant = $tenants[$host];

        // Switch the default MySQL connection to this tenant's database
        Config::set('database.connections.mysql.host',     $tenant['db_host']     ?? config('tenants.db_host'));
        Config::set('database.connections.mysql.port',     $tenant['db_port']     ?? config('tenants.db_port'));
        Config::set('database.connections.mysql.username', $tenant['db_username'] ?? config('tenants.db_username'));
        Config::set('database.connections.mysql.password', $tenant['db_password'] ?? config('tenants.db_password'));
        Config::set('database.connections.mysql.database', $tenant['db_database']);

        // Drop any cached connection so the new config takes effect
        DB::purge('mysql');
        DB::reconnect('mysql');

        return $next($request);
    }
}
