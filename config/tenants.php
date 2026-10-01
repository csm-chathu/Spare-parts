<?php

return [

    /*
     * Map subdomain → database name.
     * Subdomain is the first segment of the host: e.g. "siril" from siril.yourdomain.com
     *
     * Per-tenant db_username / db_password are optional;
     * they fall back to the shared credentials below.
     */
    'tenants' => [
        'siril.lumac.cc' => [
            'db_database' => 'siril_motors',
        ],
        'hero-spare.lumac.cc' => [
            'db_database' => 'hero_spare',
        ],
    ],

    // Shared DB credentials (override per-tenant if needed)
    'db_host'     => env('DB_HOST', '127.0.0.1'),
    'db_port'     => env('DB_PORT', '3306'),
    'db_username' => env('DB_USERNAME', 'root'),
    'db_password' => env('DB_PASSWORD', ''),

    /*
     * Subdomains that should bypass tenant resolution entirely.
     * Useful for landing pages, admin panels, etc.
     */
    'excluded' => [
        'localhost',
        '127.0.0.1',
    ],
];
