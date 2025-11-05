<?php

namespace App\DBAL;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;
use Doctrine\DBAL\Connection;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\Context\Context;
use OpenTelemetry\API\Globals;
use function json_encode;

class TracingDriver extends AbstractDriverMiddleware
{
    private TracerInterface $tracer;

    public function __construct(Driver $driver, TracerInterface $tracer)
    {
        parent::__construct($driver);
        $this->tracer = $tracer;
    }

    public function connect(array $params): Driver\Connection
    {
        $connection = parent::connect($params);

        return new TracingConnection($connection, $this->tracer);
    }
}

class TracingConnection implements Driver\Connection
{
    private Driver\Connection $connection;
    private TracerInterface $tracer;

    public function __construct(Driver\Connection $connection, TracerInterface $tracer)
    {
        $this->connection = $connection;
        $this->tracer = $tracer;
    }

    public function prepare(string $sql): Driver\Statement
    {
        $statement = $this->connection->prepare($sql);

        return new TracingStatement($statement, $sql, $this->tracer);
    }

    public function query(string $sql): Driver\Result
    {
        $span = $this->tracer->spanBuilder('db.query')
            ->setSpanKind(SpanKind::KIND_INTERNAL)
            ->startSpan();

        $span->setAttribute('db.operation', 'query');
        $span->setAttribute('db.statement', $sql);

        $scope = $span->storeInContext(Context::getCurrent())->activate();

        try {
            $result = $this->connection->query($sql);
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

    public function quote(string $value): string
    {
        return $this->connection->quote($value);
    }

    public function exec(string $sql): int
    {
        $span = $this->tracer->spanBuilder('db.exec')
            ->setSpanKind(SpanKind::KIND_INTERNAL)
            ->startSpan();

        $span->setAttribute('db.operation', 'exec');
        $span->setAttribute('db.statement', $sql);

        $scope = $span->storeInContext(Context::getCurrent())->activate();

        try {
            $result = $this->connection->exec($sql);
            $span->setStatus(StatusCode::STATUS_OK);
            $span->setAttribute('db.rows_affected', $result);
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

    public function lastInsertId($name = null): string
    {
        return $this->connection->lastInsertId($name);
    }

    public function beginTransaction(): bool
    {
        return $this->connection->beginTransaction();
    }

    public function commit(): bool
    {
        return $this->connection->commit();
    }

    public function rollBack(): bool
    {
        return $this->connection->rollBack();
    }

    public function errorCode(): ?string
    {
        return $this->connection->errorCode();
    }

    public function errorInfo(): array
    {
        return $this->connection->errorInfo();
    }
}

class TracingStatement implements Driver\Statement
{
    private Driver\Statement $statement;
    private string $sql;
    private TracerInterface $tracer;

    public function __construct(Driver\Statement $statement, string $sql, TracerInterface $tracer)
    {
        $this->statement = $statement;
        $this->sql = $sql;
        $this->tracer = $tracer;
    }

    public function bindValue($param, $value, $type = null): bool
    {
        return $this->statement->bindValue($param, $value, $type);
    }

    public function bindParam($param, &$variable, $type = null, $length = null): bool
    {
        return $this->statement->bindParam($param, $variable, $type, $length);
    }

    public function execute($params = null): Driver\Result
    {
        $span = $this->tracer->spanBuilder('db.statement.execute')
            ->setSpanKind(SpanKind::KIND_INTERNAL)
            ->startSpan();

        $span->setAttribute('db.operation', 'execute');
        $span->setAttribute('db.statement', $this->sql);

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
}