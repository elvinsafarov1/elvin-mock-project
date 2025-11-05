<?php
// Simple script to manually test database connection and trigger the tracing listener

require_once '/var/www/vendor/autoload.php';

use App\OpenTelemetryBootstrap;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Configuration;

// Initialize OpenTelemetry
OpenTelemetryBootstrap::init();

// Get database connection parameters from environment
$databaseUrl = getenv('DATABASE_URL') ?: 'postgresql://app:!ChangeMe!@db:5432/app?serverVersion=16&charset=utf8';

try {
    // Create DBAL connection with our tracing middleware
    $config = new Configuration();
    
    // Create a custom logger to test our tracing
    $tracer = \OpenTelemetry\API\Globals::tracerProvider()->getTracer('php-backend');
    
    // Create a test logger that implements SQLLogger
    $logger = new class($tracer) implements \Doctrine\DBAL\Logging\SQLLogger {
        private $tracer;
        private $currentSpan;
        
        public function __construct($tracer) {
            $this->tracer = $tracer;
        }
        
        public function startQuery($sql, ?array $params = null, ?array $types = null): void {
            echo "Starting query: $sql\n";
            
            $this->currentSpan = $this->tracer->spanBuilder('manual.db.query')
                ->setAttribute('db.statement', $sql)
                ->setAttribute('db.system', 'postgresql')
                ->startSpan();
        }
        
        public function stopQuery(): void {
            if ($this->currentSpan) {
                $this->currentSpan->end();
                $this->currentSpan = null;
                echo "Query completed and trace sent\n";
            }
        }
    };
    
    $config->setSQLLogger($logger);
    
    // Connect to database
    $connectionParams = ['url' => $databaseUrl];
    $conn = DriverManager::getConnection($connectionParams, $config);
    
    echo "Connected to database successfully!\n";
    
    // Execute some test queries to trigger tracing
    echo "Executing SELECT query to test tracing...\n";
    $stmt = $conn->prepare('SELECT COUNT(*) FROM users');
    $result = $stmt->executeQuery();
    $count = $result->fetchOne();
    echo "User count: $count\n";
    
    // Another query
    echo "Executing another query to test tracing...\n";
    $stmt2 = $conn->prepare('SELECT * FROM users LIMIT 3');
    $result2 = $stmt2->executeQuery();
    $users = $result2->fetchAllAssociative();
    echo "Retrieved " . count($users) . " users\n";
    
    $conn->close();
    echo "Database test completed - traces should be sent to Jaeger!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}