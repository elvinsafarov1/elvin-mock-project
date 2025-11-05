<?php

namespace App\EventListener;

use Doctrine\DBAL\Event\ConnectionEventArgs;
use Doctrine\DBAL\Events;
use Doctrine\EventSubscriber;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\Context\Context;
use OpenTelemetry\Context\ScopeInterface;
use Doctrine\DBAL\Logging\SQLLogger;
use function json_encode;

class DoctrineTraceLogger implements SQLLogger
{
    private TracerInterface $tracer;
    private ?SpanInterface $currentSpan = null;
    private ?ScopeInterface $currentScope = null;

    public function __construct(TracerInterface $tracer)
    {
        $this->tracer = $tracer;
    }

    public function startQuery($sql, ?array $params = null, ?array $types = null): void
    {
        // Start a new span for the database query
        $this->currentSpan = $this->tracer->spanBuilder('db.query')
            ->setSpanKind(SpanKind::KIND_INTERNAL)
            ->startSpan();

        $this->currentSpan->setAttribute('db.system', 'postgresql');  // Database system
        $this->currentSpan->setAttribute('db.statement', $sql);
        $this->currentSpan->setAttribute('db.operation', $this->extractOperationFromSQL($sql));
        
        if ($params) {
            $this->currentSpan->setAttribute('db.statement.params', json_encode($params));
        }

        $this->currentScope = $this->currentSpan->storeInContext(Context::getCurrent())->activate();
    }

    public function stopQuery(): void
    {
        if ($this->currentSpan && $this->currentScope) {
            $this->currentSpan->setStatus(StatusCode::STATUS_OK);
            $this->currentSpan->end();
            $this->currentScope->detach();
            
            $this->currentSpan = null;
            $this->currentScope = null;
        }
    }

    private function extractOperationFromSQL(string $sql): string
    {
        $sql = trim($sql);
        $firstSpace = strpos($sql, ' ');
        
        if ($firstSpace !== false) {
            return strtoupper(substr($sql, 0, $firstSpace));
        }
        
        return 'UNKNOWN';
    }
}

class DoctrineDBListener implements EventSubscriber
{
    private TracerInterface $tracer;

    public function __construct(TracerInterface $tracer)
    {
        $this->tracer = $tracer;
    }

    public function getSubscribedEvents(): array
    {
        return [
            Events::postConnect,
        ];
    }

    public function postConnect(ConnectionEventArgs $args): void
    {
        $connection = $args->getConnection();
        
        // Set our tracing logger to capture SQL queries
        $connection->getConfiguration()->setSQLLogger(
            new DoctrineTraceLogger($this->tracer)
        );
    }
}