FROM maven:3.9.4-eclipse-temurin-17 AS build

WORKDIR /build
COPY pom.xml .
RUN mvn dependency:go-offline -B

COPY src ./src
RUN mvn clean package -DskipTests

FROM eclipse-temurin:17-jre-alpine

WORKDIR /app
COPY --from=build /build/target/java-service-*.jar app.jar

# Install OpenTelemetry Java agent
RUN apk add --no-cache curl
RUN curl -L https://github.com/open-telemetry/opentelemetry-java-instrumentation/releases/latest/download/opentelemetry-javaagent.jar -o /app/opentelemetry-javaagent.jar

EXPOSE 8080

HEALTHCHECK --interval=30s --timeout=3s --start-period=10s --retries=3 \
  CMD curl -f http://localhost:8080/actuator/health || exit 1

ENTRYPOINT ["java", "-javaagent:/app/opentelemetry-javaagent.jar", "-Dotel.service.name=java-service", "-Dotel.exporter.otlp.endpoint=http://jaeger:4317", "-Dotel.metrics.exporter=none", "-jar", "app.jar"]