<?php
// Simple script to test database connection and tracing

require_once __DIR__.'/vendor/autoload_runtime.php';

use App\OpenTelemetryBootstrap;
use App\Kernel;

// Initialize OpenTelemetry
OpenTelemetryBootstrap::init();

$kernel = new Kernel('prod', false);

// Create a simple request to trigger database operations
use Symfony\Component\HttpFoundation\Request;

// This would test the API route that accesses the database
$request = Request::create('/api/users', 'GET');

$response = $kernel->handle($request);
echo $response->getContent();