# Monitoring & Observability Standards

Diese Standards definieren einheitliche Patterns für Monitoring und Observability über alle Microservices hinweg.

## 1. Die Drei Säulen der Observability

| Säule | Zweck | Tools |
|-------|-------|-------|
| **Logs** | Event-basierte Records | Pino, Python logging (siehe error-handling-logging.md) |
| **Metrics** | Numerische Zeitreihen | Prometheus, Grafana |
| **Traces** | Request-Flows über Services | OpenTelemetry, Jaeger |

## 2. Health Checks

### Standard Endpoints

Jeder Service MUSS diese Endpoints implementieren:

```
GET /health          # Kubernetes liveness probe
GET /health/ready    # Kubernetes readiness probe
GET /health/live     # Simple liveness (optional alias)
```

### Health Check Response Format

```json
// GET /health (Liveness)
{
  "status": "healthy",
  "timestamp": "2025-01-15T10:30:00.000Z",
  "version": "1.2.3",
  "service": "user-service"
}

// GET /health/ready (Readiness)
{
  "status": "healthy",
  "timestamp": "2025-01-15T10:30:00.000Z",
  "version": "1.2.3",
  "service": "user-service",
  "checks": {
    "database": {
      "status": "healthy",
      "latency_ms": 5
    },
    "redis": {
      "status": "healthy",
      "latency_ms": 2
    },
    "external_api": {
      "status": "degraded",
      "message": "High latency detected",
      "latency_ms": 850
    }
  }
}
```

### Status Values

| Status | HTTP Code | Meaning |
|--------|-----------|---------|
| `healthy` | 200 | All systems operational |
| `degraded` | 200 | Functional but with issues |
| `unhealthy` | 503 | Service unavailable |

### Implementation (FastAPI)

```python
# app/api/health.py
from fastapi import APIRouter, Response
from datetime import datetime
import asyncio

router = APIRouter(tags=["Health"])

@router.get("/health")
async def liveness():
    return {
        "status": "healthy",
        "timestamp": datetime.utcnow().isoformat() + "Z",
        "version": settings.app_version,
        "service": settings.service_name,
    }

@router.get("/health/ready")
async def readiness(response: Response):
    checks = {}

    # Database check
    try:
        start = datetime.utcnow()
        await db.execute("SELECT 1")
        latency = (datetime.utcnow() - start).total_seconds() * 1000
        checks["database"] = {"status": "healthy", "latency_ms": round(latency)}
    except Exception as e:
        checks["database"] = {"status": "unhealthy", "error": str(e)}

    # Redis check
    try:
        start = datetime.utcnow()
        await redis.ping()
        latency = (datetime.utcnow() - start).total_seconds() * 1000
        checks["redis"] = {"status": "healthy", "latency_ms": round(latency)}
    except Exception as e:
        checks["redis"] = {"status": "unhealthy", "error": str(e)}

    # Determine overall status
    all_healthy = all(c["status"] == "healthy" for c in checks.values())
    any_unhealthy = any(c["status"] == "unhealthy" for c in checks.values())

    if any_unhealthy:
        status = "unhealthy"
        response.status_code = 503
    elif not all_healthy:
        status = "degraded"
    else:
        status = "healthy"

    return {
        "status": status,
        "timestamp": datetime.utcnow().isoformat() + "Z",
        "version": settings.app_version,
        "service": settings.service_name,
        "checks": checks,
    }
```

### Implementation (Next.js API Route)

```typescript
// app/api/health/route.ts
import { NextResponse } from 'next/server';

export async function GET() {
  return NextResponse.json({
    status: 'healthy',
    timestamp: new Date().toISOString(),
    version: process.env.APP_VERSION || '0.0.0',
    service: process.env.SERVICE_NAME || 'unknown',
  });
}

// app/api/health/ready/route.ts
export async function GET() {
  const checks: Record<string, any> = {};

  // Database check
  try {
    const start = Date.now();
    await prisma.$queryRaw`SELECT 1`;
    checks.database = { status: 'healthy', latency_ms: Date.now() - start };
  } catch (error) {
    checks.database = { status: 'unhealthy', error: String(error) };
  }

  const allHealthy = Object.values(checks).every(c => c.status === 'healthy');
  const anyUnhealthy = Object.values(checks).some(c => c.status === 'unhealthy');

  const status = anyUnhealthy ? 'unhealthy' : allHealthy ? 'healthy' : 'degraded';

  return NextResponse.json(
    {
      status,
      timestamp: new Date().toISOString(),
      version: process.env.APP_VERSION || '0.0.0',
      service: process.env.SERVICE_NAME || 'unknown',
      checks,
    },
    { status: anyUnhealthy ? 503 : 200 }
  );
}
```

## 3. Metrics (Prometheus)

### Standard Metrics

Jeder Service SOLLTE diese Metrics exposen:

| Metric | Type | Description |
|--------|------|-------------|
| `http_requests_total` | Counter | Total HTTP requests |
| `http_request_duration_seconds` | Histogram | Request latency |
| `http_requests_in_progress` | Gauge | Current active requests |
| `app_info` | Gauge | Application metadata |

### Prometheus Endpoint

```
GET /metrics
```

### FastAPI Prometheus Integration

```python
# app/core/metrics.py
from prometheus_client import Counter, Histogram, Gauge, Info, generate_latest
from prometheus_client import CONTENT_TYPE_LATEST
from fastapi import Response
import time

# Metrics
REQUEST_COUNT = Counter(
    "http_requests_total",
    "Total HTTP requests",
    ["method", "endpoint", "status"]
)

REQUEST_LATENCY = Histogram(
    "http_request_duration_seconds",
    "HTTP request latency",
    ["method", "endpoint"],
    buckets=[0.01, 0.025, 0.05, 0.1, 0.25, 0.5, 1.0, 2.5, 5.0, 10.0]
)

REQUESTS_IN_PROGRESS = Gauge(
    "http_requests_in_progress",
    "HTTP requests currently in progress",
    ["method", "endpoint"]
)

APP_INFO = Info("app", "Application information")
APP_INFO.info({
    "version": settings.app_version,
    "service": settings.service_name,
})

# Middleware
from starlette.middleware.base import BaseHTTPMiddleware

class PrometheusMiddleware(BaseHTTPMiddleware):
    async def dispatch(self, request, call_next):
        method = request.method
        endpoint = request.url.path

        REQUESTS_IN_PROGRESS.labels(method=method, endpoint=endpoint).inc()
        start_time = time.time()

        try:
            response = await call_next(request)
            status = response.status_code
        except Exception:
            status = 500
            raise
        finally:
            REQUEST_LATENCY.labels(method=method, endpoint=endpoint).observe(
                time.time() - start_time
            )
            REQUEST_COUNT.labels(method=method, endpoint=endpoint, status=status).inc()
            REQUESTS_IN_PROGRESS.labels(method=method, endpoint=endpoint).dec()

        return response

# Endpoint
@router.get("/metrics")
async def metrics():
    return Response(
        content=generate_latest(),
        media_type=CONTENT_TYPE_LATEST
    )
```

### Custom Business Metrics

```python
# Example: User registration metrics
USER_REGISTRATIONS = Counter(
    "user_registrations_total",
    "Total user registrations",
    ["method", "status"]  # method: email, oauth; status: success, failure
)

ORDER_VALUE = Histogram(
    "order_value_euros",
    "Order value distribution",
    buckets=[10, 25, 50, 100, 250, 500, 1000]
)

ACTIVE_SESSIONS = Gauge(
    "active_sessions",
    "Currently active user sessions"
)
```

## 4. Distributed Tracing (OpenTelemetry)

### Setup (FastAPI)

```python
# app/core/tracing.py
from opentelemetry import trace
from opentelemetry.sdk.trace import TracerProvider
from opentelemetry.sdk.trace.export import BatchSpanProcessor
from opentelemetry.exporter.otlp.proto.grpc.trace_exporter import OTLPSpanExporter
from opentelemetry.instrumentation.fastapi import FastAPIInstrumentor
from opentelemetry.instrumentation.httpx import HTTPXClientInstrumentor
from opentelemetry.instrumentation.sqlalchemy import SQLAlchemyInstrumentor
from opentelemetry.sdk.resources import Resource

def setup_tracing(app):
    resource = Resource.create({
        "service.name": settings.service_name,
        "service.version": settings.app_version,
        "deployment.environment": settings.app_env,
    })

    provider = TracerProvider(resource=resource)

    # Export to OTLP collector (Jaeger, etc.)
    if settings.otlp_endpoint:
        exporter = OTLPSpanExporter(endpoint=settings.otlp_endpoint)
        provider.add_span_processor(BatchSpanProcessor(exporter))

    trace.set_tracer_provider(provider)

    # Auto-instrument
    FastAPIInstrumentor.instrument_app(app)
    HTTPXClientInstrumentor().instrument()
    SQLAlchemyInstrumentor().instrument(engine=engine)

# Manual tracing
tracer = trace.get_tracer(__name__)

async def process_order(order_id: str):
    with tracer.start_as_current_span("process_order") as span:
        span.set_attribute("order.id", order_id)

        with tracer.start_as_current_span("validate_order"):
            # validation logic
            pass

        with tracer.start_as_current_span("charge_payment"):
            # payment logic
            pass

        with tracer.start_as_current_span("send_confirmation"):
            # email logic
            pass
```

### Trace Context Propagation

Siehe `error-handling-logging.md` Section 4 für Header-Standards.

```python
# Propagate context to external calls
import httpx
from opentelemetry.propagate import inject

async def call_external_service():
    headers = {}
    inject(headers)  # Injects trace context

    async with httpx.AsyncClient() as client:
        response = await client.get(
            "https://external-service.com/api",
            headers=headers
        )
```

## 5. Alerting Rules

### Prometheus Alerting Rules

```yaml
# prometheus/alerts.yml
groups:
  - name: service-alerts
    rules:
      # High Error Rate
      - alert: HighErrorRate
        expr: |
          sum(rate(http_requests_total{status=~"5.."}[5m]))
          /
          sum(rate(http_requests_total[5m])) > 0.05
        for: 5m
        labels:
          severity: critical
        annotations:
          summary: "High error rate detected"
          description: "Error rate is {{ $value | humanizePercentage }} (threshold: 5%)"

      # High Latency
      - alert: HighLatency
        expr: |
          histogram_quantile(0.95, rate(http_request_duration_seconds_bucket[5m])) > 1
        for: 5m
        labels:
          severity: warning
        annotations:
          summary: "High latency detected"
          description: "P95 latency is {{ $value | humanizeDuration }}"

      # Service Down
      - alert: ServiceDown
        expr: up == 0
        for: 1m
        labels:
          severity: critical
        annotations:
          summary: "Service is down"
          description: "{{ $labels.instance }} has been down for more than 1 minute"

      # Database Connection Issues
      - alert: DatabaseConnectionFailed
        expr: |
          health_check_database_status == 0
        for: 2m
        labels:
          severity: critical
        annotations:
          summary: "Database connection failed"

      # Memory Usage
      - alert: HighMemoryUsage
        expr: |
          container_memory_usage_bytes / container_spec_memory_limit_bytes > 0.9
        for: 5m
        labels:
          severity: warning
        annotations:
          summary: "High memory usage"
          description: "Memory usage is above 90%"
```

### Alert Thresholds Summary

| Metric | Warning | Critical |
|--------|---------|----------|
| Error Rate (5xx) | > 1% | > 5% |
| P95 Latency | > 500ms | > 2000ms |
| P99 Latency | > 1000ms | > 5000ms |
| Memory Usage | > 80% | > 90% |
| CPU Usage | > 70% | > 90% |
| Disk Usage | > 70% | > 85% |
| Health Check Failures | 1 consecutive | 3 consecutive |

## 6. Dashboards (Grafana)

### Standard Dashboard Panels

Jedes Service-Dashboard SOLLTE enthalten:

1. **Overview Row**
   - Request Rate (req/s)
   - Error Rate (%)
   - P50/P95/P99 Latency
   - Active Requests

2. **HTTP Details Row**
   - Requests by Status Code
   - Requests by Endpoint
   - Latency Distribution

3. **Resources Row**
   - CPU Usage
   - Memory Usage
   - Network I/O

4. **Dependencies Row**
   - Database Query Time
   - External API Latency
   - Cache Hit Rate

### Grafana Dashboard JSON Template

```json
{
  "dashboard": {
    "title": "Service Overview",
    "panels": [
      {
        "title": "Request Rate",
        "type": "timeseries",
        "targets": [
          {
            "expr": "sum(rate(http_requests_total[5m]))",
            "legendFormat": "Requests/s"
          }
        ]
      },
      {
        "title": "Error Rate",
        "type": "gauge",
        "targets": [
          {
            "expr": "sum(rate(http_requests_total{status=~\"5..\"}[5m])) / sum(rate(http_requests_total[5m])) * 100"
          }
        ],
        "fieldConfig": {
          "defaults": {
            "thresholds": {
              "steps": [
                { "value": 0, "color": "green" },
                { "value": 1, "color": "yellow" },
                { "value": 5, "color": "red" }
              ]
            },
            "unit": "percent"
          }
        }
      },
      {
        "title": "Latency Percentiles",
        "type": "timeseries",
        "targets": [
          {
            "expr": "histogram_quantile(0.50, rate(http_request_duration_seconds_bucket[5m]))",
            "legendFormat": "P50"
          },
          {
            "expr": "histogram_quantile(0.95, rate(http_request_duration_seconds_bucket[5m]))",
            "legendFormat": "P95"
          },
          {
            "expr": "histogram_quantile(0.99, rate(http_request_duration_seconds_bucket[5m]))",
            "legendFormat": "P99"
          }
        ]
      }
    ]
  }
}
```

## 7. Kubernetes Probes

### Standard Probe Configuration

```yaml
# k8s/deployment.yaml
apiVersion: apps/v1
kind: Deployment
spec:
  template:
    spec:
      containers:
        - name: app
          livenessProbe:
            httpGet:
              path: /health
              port: 8000
            initialDelaySeconds: 10
            periodSeconds: 10
            timeoutSeconds: 5
            failureThreshold: 3

          readinessProbe:
            httpGet:
              path: /health/ready
              port: 8000
            initialDelaySeconds: 5
            periodSeconds: 5
            timeoutSeconds: 3
            failureThreshold: 3

          startupProbe:
            httpGet:
              path: /health
              port: 8000
            initialDelaySeconds: 0
            periodSeconds: 5
            timeoutSeconds: 3
            failureThreshold: 30  # 30 * 5s = 150s max startup time
```

## 8. Logging Correlation

### Request ID Flow

```
Client Request
    │
    ├── X-Request-Id: req-123 (generated if missing)
    │
    ▼
API Gateway
    │
    ├── Log: {"requestId": "req-123", "action": "route"}
    │
    ▼
Service A
    │
    ├── Log: {"requestId": "req-123", "traceId": "trace-456", "action": "process"}
    │
    ▼
Service B (called by A)
    │
    ├── Log: {"requestId": "req-123", "traceId": "trace-456", "parentSpanId": "span-789"}
    │
    ▼
Response
```

### Log Query Examples (Loki/Grafana)

```
# All logs for a specific request
{service="user-service"} |= "req-123"

# All errors in last hour
{service=~".*"} | json | level="error"

# Slow requests (> 1s)
{service="api-gateway"} | json | duration > 1000
```

## 9. Observability Checklist

### Vor Production Deployment

- [ ] Health endpoints implementiert (`/health`, `/health/ready`)
- [ ] Metrics endpoint implementiert (`/metrics`)
- [ ] Structured logging konfiguriert (JSON)
- [ ] Request IDs werden propagiert
- [ ] Trace context wird propagiert (wenn Microservices)
- [ ] Alerting rules definiert
- [ ] Dashboard erstellt
- [ ] Kubernetes probes konfiguriert

### Laufender Betrieb

- [ ] Alert-Benachrichtigungen funktionieren
- [ ] Logs sind durchsuchbar
- [ ] Traces sind verfolgbar
- [ ] Dashboards werden regelmäßig reviewed
- [ ] SLOs definiert und überwacht

---

**Letzte Aktualisierung**: 2025-12
**Owner**: DevOps Team
