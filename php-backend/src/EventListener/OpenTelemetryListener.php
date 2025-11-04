<?php

namespace App\EventListener;

use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Trace\SpanKind;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class OpenTelemetryListener implements EventSubscriberInterface
{
    private $spans = [];

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 1000],
            KernelEvents::RESPONSE => ['onKernelResponse', -1000],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $tracer = Globals::tracerProvider()->getTracer('php-backend');

        $span = $tracer->spanBuilder($request->getMethod() . ' ' . $request->getPathInfo())
            ->setSpanKind(SpanKind::KIND_SERVER)
            ->setAttribute('http.method', $request->getMethod())
            ->setAttribute('http.url', $request->getUri())
            ->setAttribute('http.route', $request->getPathInfo())
            ->startSpan();

        $this->spans[spl_object_id($request)] = $span;
        $span->activate();
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        $response = $event->getResponse();
        $requestId = spl_object_id($request);

        if (!isset($this->spans[$requestId])) {
            return;
        }

        $span = $this->spans[$requestId];
        $span->setAttribute('http.status_code', $response->getStatusCode());
        $span->end();
        unset($this->spans[$requestId]);
    }
}

