<?php

// Lets the Flutter Web app (running on another port) call the API from the browser.
return [
    'paths' => ['api/*'],
    'allowed_methods' => ['*'],
    'allowed_origins' => ['*'],
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],   // includes Content-Type and X-Demo-Role
    'exposed_headers' => ['X-Reference-Id'],
    'max_age' => 0,
    'supports_credentials' => false,
];
