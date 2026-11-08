<?php

// Only the Flutter Web app on this computer (localhost / 127.0.0.1, any port) may call the API from a browser.
// For a real deployment, replace the pattern with the exact origin of the deployed Flutter site.
return [
    'paths' => ['api/*'],
    'allowed_methods' => ['GET', 'POST', 'PATCH', 'OPTIONS'],
    'allowed_origins' => [],
    'allowed_origins_patterns' => ['#^https?://(localhost|127\.0\.0\.1)(:\d+)?$#'],
    'allowed_headers' => ['Content-Type', 'Authorization', 'Accept'],
    'exposed_headers' => ['X-Reference-Id'],
    'max_age' => 600,
    'supports_credentials' => false,
];
