<?php

require_once __DIR__ . '/../vendor/autoload.php';
\App\OpenTelemetryBootstrap::init();
use App\Kernel;
use App\OpenTelemetryBootstrap;

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

return function (array $context) {
    // Initialize OpenTelemetry
    OpenTelemetryBootstrap::init();
    
    return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
};

