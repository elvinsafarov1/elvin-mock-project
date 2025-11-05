<?php

namespace App\EventSubscriber;

use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\Context\Context;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class OpenTelemetrySubscriber implements EventSubscriberInterface
{
    private TracerInterface $tracer;
    private ?SpanInterface $currentSpan = null;
    private ?Context $currentContext = null;

    public function __construct(TracerInterface $tracer)
    {
        $this->tracer = $tracer;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 1024],
            KernelEvents::RESPONSE => ['onKernelResponse', -1024],
            KernelEvents::EXCEPTION => ['onKernelException', 0],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $spanName = sprintf('%s %s', $request->getMethod(), $request->getPathInfo());
        
        $span = $this->tracer->spanBuilder($spanName)
            ->setSpanKind(SpanKind::KIND_SERVER)
            ->startSpan();

        $span->setAttribute('http.method', $request->getMethod());
        $span->setAttribute('http.url', $request->getUri());
        $span->setAttribute('http.target', $request->getPathInfo());
        
        $this->currentSpan = $span;
        $this->currentContext = $span->storeInContext(Context::getCurrent());
        $this->currentContext->activate();
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest() || !$this->currentSpan) {
            return;
        }

        $response = $event->getResponse();
        $this->currentSpan->setAttribute('http.status_code', $response->getStatusCode());
        
        if ($response->getStatusCode() >= 400) {
            $this->currentSpan->setStatus(
                StatusCode::STATUS_ERROR,
                sprintf('HTTP %d', $response->getStatusCode())
            );
        } else {
            $this->currentSpan->setStatus(StatusCode::STATUS_OK);
        }

        $this->currentSpan->end();
        
        if ($this->currentContext) {
            $this->currentContext->detach();
        }
        
        $this->currentSpan = null;
        $this->currentContext = null;
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        if (!$this->currentSpan) {
            return;
        }

        $exception = $event->getThrowable();
        $this->currentSpan->recordException($exception);
        $this->currentSpan->setStatus(StatusCode::STATUS_ERROR, $exception->getMessage());
    }
}