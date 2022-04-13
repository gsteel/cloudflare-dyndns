<?php

declare(strict_types=1);

return [
    'token' => 'Some Cloudflare API Token with zone:read and zone.dns:edit permission',
    'zones' => [
        /**
         * Each key is the zone name (Domain Name) and the values are the subdomains to target
         */
        'example.com' => [
            '@', // <- "@" targets the apex A record
            '*', // <- "*" targets the wildcard A record
            'something', // <- A specific subdomain, i.e. something.example.com
        ],
    ],
];
