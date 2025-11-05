<?php
// Script to generate database traces by creating users directly via internal network

require_once __DIR__.'/php-backend/vendor/autoload_runtime.php';

use App\OpenTelemetryBootstrap;
use App\Kernel;
use Symfony\Component\HttpFoundation\Request;

// Initialize OpenTelemetry
OpenTelemetryBootstrap::init();

$kernel = new Kernel('prod', false);

echo "Creating 20 users to generate database traces...\n";

for ($i = 1; $i <= 20; $i++) {
    $request = Request::create('/api/users', 'POST', [], [], [], [], json_encode([
        'name' => "Test User $i",
        'email' => "test$i@example.com"
    ]));
    $request->headers->set('Content-Type', 'application/json');
    
    $response = $kernel->handle($request);
    
    if ($response->getStatusCode() < 400) {
        echo "✓ User $i created (HTTP {$response->getStatusCode()})\n";
    } else {
        echo "✗ User $i creation failed (HTTP {$response->getStatusCode()})\n";
    }
    
    // Small delay between requests
    usleep(100000); // 0.1 seconds
}

echo "\nNow fetching users to generate SELECT queries...\n";

for ($i = 1; $i <= 20; $i++) {
    $request = Request::create('/api/users', 'GET');
    $response = $kernel->handle($request);
    
    if ($response->getStatusCode() < 400) {
        echo "✓ Fetched users (HTTP {$response->getStatusCode()}) - Request $i\n";
    } else {
        echo "✗ Fetch users failed (HTTP {$response->getStatusCode()}) - Request $i\n";
    }
    
    usleep(100000); // 0.1 seconds
}

echo "\nDatabase trace generation completed!\n";
$kernel->terminate($request, $response);