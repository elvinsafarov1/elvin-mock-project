package com.elvin.javaservice.controller;

import io.opentelemetry.api.OpenTelemetry;
import io.opentelemetry.api.trace.Span;
import io.opentelemetry.api.trace.SpanKind;
import io.opentelemetry.api.trace.Tracer;
import io.opentelemetry.context.Scope;
import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.http.ResponseEntity;
import org.springframework.web.bind.annotation.*;

import java.util.HashMap;
import java.util.Map;
import java.util.Random;

@RestController
@RequestMapping("/api/external")
public class ExternalController {

    @Autowired
    private OpenTelemetry openTelemetry;

    private final Tracer tracer;

    public ExternalController(OpenTelemetry openTelemetry) {
        this.openTelemetry = openTelemetry;
        this.tracer = openTelemetry.getTracer("java-service");
    }

    @GetMapping("/user/{id}")
    public ResponseEntity<Map<String, Object>> getUserData(@PathVariable Integer id) {
        Span span = tracer.spanBuilder("get_external_user_data")
                .setSpanKind(SpanKind.SERVER)
                .setAttribute("user.id", id)
                .startSpan();

        try (Scope scope = span.makeCurrent()) {
            // Simulate some processing
            Thread.sleep(50 + new Random().nextInt(100));

            Map<String, Object> data = new HashMap<>();
            data.put("external_id", id);
            data.put("external_score", new Random().nextInt(100));
            data.put("external_status", "active");
            data.put("processed_at", System.currentTimeMillis());

            span.setAttribute("external.score", (Integer) data.get("external_score"));
            span.setAttribute("external.status", (String) data.get("external_status"));

            return ResponseEntity.ok(data);
        } catch (InterruptedException e) {
            span.recordException(e);
            span.setStatus(io.opentelemetry.api.trace.StatusCode.ERROR, "Processing interrupted");
            Thread.currentThread().interrupt();
            return ResponseEntity.internalServerError().build();
        } finally {
            span.end();
        }
    }

    @GetMapping("/health")
    public ResponseEntity<Map<String, String>> health() {
        return ResponseEntity.ok(Map.of("status", "UP", "service", "java-service"));
    }
}

