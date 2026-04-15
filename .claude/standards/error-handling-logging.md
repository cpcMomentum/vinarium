# Error Handling & Logging Standards

Diese Standards definieren einheitliche Patterns für Error Handling und Logging über alle Microservices hinweg.

## 1. Logging Standards

### Log Format (JSON Structured Logging)

Alle Logs MÜSSEN im JSON-Format strukturiert sein:

```json
{
  "timestamp": "2025-01-15T10:30:00.000Z",
  "level": "error",
  "service": "user-service",
  "version": "1.2.3",
  "traceId": "abc123-def456",
  "spanId": "span-789",
  "message": "Failed to process user request",
  "context": {
    "userId": "user-123",
    "action": "updateProfile"
  },
  "error": {
    "name": "ValidationError",
    "message": "Email format invalid",
    "stack": "..."
  }
}
```

### Log Levels

| Level | Verwendung | Beispiel |
|-------|------------|----------|
| `error` | Unerwartete Fehler, die Intervention erfordern | Database connection lost, External API failure |
| `warn` | Potentielle Probleme, degraded functionality | Rate limit approaching, Deprecated API usage |
| `info` | Wichtige Business Events | User registered, Order completed, Deployment started |
| `debug` | Detaillierte Informationen für Debugging | Request/Response details, Cache hits/misses |
| `trace` | Sehr detaillierte Informationen | Function entry/exit, Variable states |

**Production**: `info` und höher
**Development/Staging**: `debug` und höher

### Verbotene Log-Inhalte (PII/Secrets)

NIEMALS loggen:
- Passwörter oder Tokens
- Vollständige Kreditkartennummern
- Sozialversicherungsnummern
- Vollständige Email-Adressen in Bulk (einzelne für Debugging OK)
- API Keys oder Secrets
- Session IDs in Kombination mit User-Identifikatoren

**Erlaubte Maskierung**:
```
Email: j***@example.com
Card: ****-****-****-1234
```

## 2. Error Handling Patterns

### Error Response Format (API)

Alle API Error Responses folgen diesem Schema:

```json
{
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "The request contains invalid data",
    "details": [
      {
        "field": "email",
        "message": "Invalid email format",
        "code": "INVALID_FORMAT"
      }
    ],
    "traceId": "abc123-def456",
    "timestamp": "2025-01-15T10:30:00.000Z"
  }
}
```

### HTTP Status Code Mapping

| Code | Error Type | Verwendung |
|------|------------|------------|
| 400 | `BAD_REQUEST` | Malformed request syntax |
| 401 | `UNAUTHORIZED` | Missing/invalid authentication |
| 403 | `FORBIDDEN` | Authenticated but not authorized |
| 404 | `NOT_FOUND` | Resource does not exist |
| 409 | `CONFLICT` | Resource state conflict |
| 422 | `VALIDATION_ERROR` | Semantic validation failed |
| 429 | `RATE_LIMITED` | Too many requests |
| 500 | `INTERNAL_ERROR` | Unexpected server error |
| 502 | `BAD_GATEWAY` | Upstream service error |
| 503 | `SERVICE_UNAVAILABLE` | Temporary unavailability |

### Standard Error Codes

```typescript
// TypeScript Error Codes
enum ErrorCode {
  // Validation
  VALIDATION_ERROR = 'VALIDATION_ERROR',
  INVALID_FORMAT = 'INVALID_FORMAT',
  REQUIRED_FIELD = 'REQUIRED_FIELD',

  // Authentication
  UNAUTHORIZED = 'UNAUTHORIZED',
  TOKEN_EXPIRED = 'TOKEN_EXPIRED',
  INVALID_CREDENTIALS = 'INVALID_CREDENTIALS',

  // Authorization
  FORBIDDEN = 'FORBIDDEN',
  INSUFFICIENT_PERMISSIONS = 'INSUFFICIENT_PERMISSIONS',

  // Resources
  NOT_FOUND = 'NOT_FOUND',
  ALREADY_EXISTS = 'ALREADY_EXISTS',
  CONFLICT = 'CONFLICT',

  // Rate Limiting
  RATE_LIMITED = 'RATE_LIMITED',

  // External Services
  EXTERNAL_SERVICE_ERROR = 'EXTERNAL_SERVICE_ERROR',
  TIMEOUT = 'TIMEOUT',

  // Internal
  INTERNAL_ERROR = 'INTERNAL_ERROR',
  DATABASE_ERROR = 'DATABASE_ERROR',
}
```

## 3. Implementation Templates

### TypeScript/Next.js

```typescript
// lib/logger.ts
import pino from 'pino';

export const logger = pino({
  level: process.env.LOG_LEVEL || 'info',
  formatters: {
    level: (label) => ({ level: label }),
  },
  base: {
    service: process.env.SERVICE_NAME,
    version: process.env.SERVICE_VERSION,
    env: process.env.NODE_ENV,
  },
  timestamp: () => `,"timestamp":"${new Date().toISOString()}"`,
});

// Usage with trace context
export const createChildLogger = (traceId: string, spanId?: string) => {
  return logger.child({ traceId, spanId });
};
```

```typescript
// lib/errors.ts
export class AppError extends Error {
  constructor(
    public code: ErrorCode,
    message: string,
    public statusCode: number = 500,
    public details?: Record<string, unknown>[]
  ) {
    super(message);
    this.name = 'AppError';
  }

  toJSON() {
    return {
      error: {
        code: this.code,
        message: this.message,
        details: this.details,
        timestamp: new Date().toISOString(),
      },
    };
  }
}

// Predefined errors
export const Errors = {
  notFound: (resource: string) =>
    new AppError(ErrorCode.NOT_FOUND, `${resource} not found`, 404),

  unauthorized: () =>
    new AppError(ErrorCode.UNAUTHORIZED, 'Authentication required', 401),

  forbidden: () =>
    new AppError(ErrorCode.FORBIDDEN, 'Access denied', 403),

  validation: (details: Record<string, unknown>[]) =>
    new AppError(ErrorCode.VALIDATION_ERROR, 'Validation failed', 422, details),
};
```

### Python/FastAPI

```python
# app/core/logging.py
import logging
import json
from datetime import datetime
from typing import Any
import os

class JSONFormatter(logging.Formatter):
    def format(self, record: logging.LogRecord) -> str:
        log_obj = {
            "timestamp": datetime.utcnow().isoformat() + "Z",
            "level": record.levelname.lower(),
            "service": os.getenv("SERVICE_NAME", "unknown"),
            "version": os.getenv("SERVICE_VERSION", "0.0.0"),
            "message": record.getMessage(),
        }

        if hasattr(record, "trace_id"):
            log_obj["traceId"] = record.trace_id

        if hasattr(record, "context"):
            log_obj["context"] = record.context

        if record.exc_info:
            log_obj["error"] = {
                "name": record.exc_info[0].__name__,
                "message": str(record.exc_info[1]),
                "stack": self.formatException(record.exc_info),
            }

        return json.dumps(log_obj)

def setup_logging():
    handler = logging.StreamHandler()
    handler.setFormatter(JSONFormatter())

    logger = logging.getLogger()
    logger.handlers = [handler]
    logger.setLevel(os.getenv("LOG_LEVEL", "INFO").upper())

    return logger
```

```python
# app/core/errors.py
from enum import Enum
from typing import Any, Optional
from fastapi import HTTPException
from datetime import datetime

class ErrorCode(str, Enum):
    VALIDATION_ERROR = "VALIDATION_ERROR"
    UNAUTHORIZED = "UNAUTHORIZED"
    FORBIDDEN = "FORBIDDEN"
    NOT_FOUND = "NOT_FOUND"
    CONFLICT = "CONFLICT"
    RATE_LIMITED = "RATE_LIMITED"
    INTERNAL_ERROR = "INTERNAL_ERROR"

class AppError(HTTPException):
    def __init__(
        self,
        code: ErrorCode,
        message: str,
        status_code: int = 500,
        details: Optional[list[dict[str, Any]]] = None,
        trace_id: Optional[str] = None,
    ):
        self.code = code
        self.details = details
        self.trace_id = trace_id
        super().__init__(
            status_code=status_code,
            detail={
                "error": {
                    "code": code.value,
                    "message": message,
                    "details": details,
                    "traceId": trace_id,
                    "timestamp": datetime.utcnow().isoformat() + "Z",
                }
            },
        )

# FastAPI exception handler
from fastapi import Request
from fastapi.responses import JSONResponse

async def app_error_handler(request: Request, exc: AppError):
    return JSONResponse(
        status_code=exc.status_code,
        content=exc.detail,
    )
```

## 4. Trace Context Propagation

Für verteilte Systeme muss der Trace Context propagiert werden:

### Headers
```
X-Trace-Id: abc123-def456
X-Span-Id: span-789
X-Parent-Span-Id: parent-456
```

### Middleware Example (FastAPI)
```python
import uuid
from starlette.middleware.base import BaseHTTPMiddleware

class TraceMiddleware(BaseHTTPMiddleware):
    async def dispatch(self, request, call_next):
        trace_id = request.headers.get("X-Trace-Id", str(uuid.uuid4()))
        span_id = str(uuid.uuid4())[:8]

        # Add to request state
        request.state.trace_id = trace_id
        request.state.span_id = span_id

        response = await call_next(request)
        response.headers["X-Trace-Id"] = trace_id
        return response
```

## 5. Alerting Thresholds

| Metric | Warning | Critical |
|--------|---------|----------|
| Error Rate (5xx) | > 1% | > 5% |
| P95 Latency | > 500ms | > 2000ms |
| Failed Health Checks | 1 consecutive | 3 consecutive |
| Log Error Burst | > 10/min | > 50/min |

---

**Letzte Aktualisierung**: 2025-12
**Owner**: DevOps Team