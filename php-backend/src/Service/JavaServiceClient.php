<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\API\Globals;

class JavaServiceClient
{
    private HttpClientInterface $httpClient;
    private string $javaServiceUrl;
    private TracerInterface $tracer;

    public function __construct(HttpClientInterface $httpClient, string $javaServiceUrl)
    {
        $this->httpClient = $httpClient;
        $this->javaServiceUrl = $javaServiceUrl;
        $this->tracer = Globals::tracerProvider()->getTracer('php-backend');
    }

    public function getUserData(int $userId): array
    {
        $span = $this->tracer->spanBuilder('call_java_service')
            ->setSpanKind(SpanKind::KIND_CLIENT)
            ->setAttribute('http.method', 'GET')
            ->setAttribute('http.url', $this->javaServiceUrl . '/api/external/user/' . $userId)
            ->startSpan();
        
        $scope = $span->activate();
        
        try {
            $response = $this->httpClient->request('GET', $this->javaServiceUrl . '/api/external/user/' . $userId);
            
            $statusCode = $response->getStatusCode();
            $span->setAttribute('http.status_code', $statusCode);
            
            if ($statusCode === 200) {
                $data = $response->toArray();
                $span->setAttribute('response.success', true);
                return $data;
            }
            
            return [];
        } catch (\Exception $e) {
            $span->recordException($e);
            $span->setStatus(StatusCode::STATUS_ERROR, $e->getMessage());
            return [];
        } finally {
            $span->end();
            $scope->detach();
        }
    }
}

