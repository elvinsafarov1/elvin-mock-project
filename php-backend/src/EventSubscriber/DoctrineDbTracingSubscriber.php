<?php

namespace App\EventSubscriber;

use Doctrine\Common\EventSubscriber;
use Doctrine\DBAL\Event\ConnectionEventArgs;
use Doctrine\DBAL\Events;
use Doctrine\DBAL\Driver\Middleware\AbstractStatementMiddleware;
use Doctrine\DBAL\Driver\Statement as DriverStatement;
use Doctrine\DBAL\Statement;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\Context\Context;
use OpenTelemetry\API\Globals;
use function json_encode;

class DatabaseStatementTracingMiddleware extends AbstractStatementMiddleware
{
    private SpanInterface $parentSpan;
    private TracerInterface $tracer;

    public function __construct(DriverStatement $statement, SpanInterface $parentSpan, TracerInterface $tracer)
    {
        parent::__construct($statement);
        $this->parentSpan = $parentSpan;
        $this->tracer = $tracer;
    }

    public function execute($params = null): \Doctrine\DBAL\Driver\Result
    {
        $span = $this->tracer->spanBuilder('db.query')
            ->setSpanKind(SpanKind::KIND_INTERNAL)
            ->startSpan();

        $span->setAttribute('db.operation', 'execute');
        if ($this->statement instanceof \Doctrine\DBAL\Driver\PDO\Statement) {
            $span->setAttribute('db.statement', $this->statement->getSQL());
        }

        if ($params) {
            $span->setAttribute('db.statement.params', json_encode($params));
        }

        $scope = $span->storeInContext(Context::getCurrent())->activate();

        try {
            $result = $this->statement->execute($params);
            $span->setStatus(StatusCode::STATUS_OK);
            return $result;
        } catch (\Exception $e) {
            $span->recordException($e);
            $span->setStatus(StatusCode::STATUS_ERROR, $e->getMessage());
            throw $e;
        } finally {
            $span->end();
            $scope->detach();
        }
    }

    public function executeQuery(?array $params = null, ?array $types = null): \Doctrine\DBAL\Driver\Result
    {
        $span = $this->tracer->spanBuilder('db.query')
            ->setSpanKind(SpanKind::KIND_INTERNAL)
            ->startSpan();

        $span->setAttribute('db.operation', 'query');
        if ($this->statement instanceof \Doctrine\DBAL\Driver\PDO\Statement) {
            $span->setAttribute('db.statement', $this->statement->getSQL());
        }

        if ($params) {
            $span->setAttribute('db.statement.params', json_encode($params));
        }

        $scope = $span->storeInContext(Context::getCurrent())->activate();

        try {
            $result = $this->statement->executeQuery($params, $types);
            $span->setStatus(StatusCode::STATUS_OK);
            return $result;
        } catch (\Exception $e) {
            $span->recordException($e);
            $span->setStatus(StatusCode::STATUS_ERROR, $e->getMessage());
            throw $e;
        } finally {
            $span->end();
            $scope->detach();
        }
    }
}

class StatementTracingMiddleware implements \Doctrine\DBAL\Driver\Middleware
{
    public function wrap(DriverStatement $statement): DriverStatement
    {
        // Get the current span from context
        $currentContext = Context::getCurrent();
        $parentSpan = $currentContext->get(Context::getCurrent()->getSlot('otel_span'));
        
        if (!$parentSpan) {
            // If no span in context, try to get a default span
            $tracer = Globals::tracerProvider()->getTracer('php-backend');
            // In this case, we'll only trace if there's an active span
            return $statement;
        }
        
        return new DatabaseStatementTracingMiddleware($statement, $parentSpan, 
            Globals::tracerProvider()->getTracer('php-backend'));
    }
}

class DoctrineDbTracingSubscriber implements EventSubscriber
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
        // This is where we could potentially add tracing middleware to the connection
        $connection = $args->getConnection();
        
        // For now, we're implementing the tracing through statement middleware
        // which is attached when statements are prepared
    }
}