# API Design Standards

Diese Standards definieren einheitliche Patterns für API-Design über alle Microservices hinweg.

## 1. API Versioning

### URL-basierte Versionierung (Preferred)

```
/api/v1/users
/api/v2/users
```

### Versionierungs-Regeln

| Änderungstyp | Aktion |
|--------------|--------|
| Breaking Change (Feld entfernt, Typ geändert) | Neue Major Version (v1 → v2) |
| Non-Breaking Addition (neues optionales Feld) | Gleiche Version |
| Bug Fix | Gleiche Version |
| Deprecation | Deprecation Header + Dokumentation |

### Deprecation Policy

- **Mindestens 3 Monate** Vorlaufzeit vor Abschaltung
- Deprecation Header in Responses: `Deprecation: true`
- Sunset Header: `Sunset: Sat, 01 Mar 2025 00:00:00 GMT`
- Dokumentation der Migration in API Docs

## 2. URL-Struktur & Naming

### RESTful Resource Naming

```
# Good ✓
GET    /api/v1/users              # List users
GET    /api/v1/users/{id}         # Get single user
POST   /api/v1/users              # Create user
PUT    /api/v1/users/{id}         # Update user (full)
PATCH  /api/v1/users/{id}         # Update user (partial)
DELETE /api/v1/users/{id}         # Delete user

# Nested Resources
GET    /api/v1/users/{id}/orders  # List user's orders
POST   /api/v1/users/{id}/orders  # Create order for user

# Bad ✗
GET    /api/v1/getUsers
POST   /api/v1/createUser
GET    /api/v1/user-list
```

### Naming Conventions

- **Plural Nouns** für Collections: `/users`, `/orders`, `/products`
- **Lowercase** mit Hyphens: `/user-profiles`, `/order-items`
- **Keine Verben** in URLs (außer für Actions, siehe unten)
- **Keine Dateiendungen**: `/users` nicht `/users.json`

### Actions (Non-CRUD Operations)

Für Operationen die nicht in CRUD passen:

```
POST /api/v1/users/{id}/actions/activate
POST /api/v1/orders/{id}/actions/cancel
POST /api/v1/reports/actions/generate
```

## 3. Request/Response Formats

### Standard Response Envelope

#### Success Response
```json
{
  "data": {
    "id": "user-123",
    "email": "user@example.com",
    "name": "Max Mustermann",
    "createdAt": "2025-01-15T10:30:00.000Z"
  },
  "meta": {
    "requestId": "req-abc123"
  }
}
```

#### Collection Response (with Pagination)
```json
{
  "data": [
    { "id": "user-123", "name": "Max" },
    { "id": "user-456", "name": "Anna" }
  ],
  "meta": {
    "requestId": "req-abc123",
    "pagination": {
      "page": 1,
      "pageSize": 20,
      "totalItems": 156,
      "totalPages": 8
    }
  }
}
```

#### Error Response
Siehe `error-handling-logging.md` für das vollständige Error-Schema.

### Field Naming

- **camelCase** für JSON Fields: `firstName`, `createdAt`, `userId`
- **Consistent** über alle Endpoints: Immer `createdAt`, nie mal `created_at`
- **ISO 8601** für Datumsfelder: `2025-01-15T10:30:00.000Z`

## 4. Pagination

### Offset-based Pagination (Default)

```
GET /api/v1/users?page=2&pageSize=20
```

Response Headers:
```
X-Total-Count: 156
X-Total-Pages: 8
X-Current-Page: 2
```

### Cursor-based Pagination (für große Datasets)

```
GET /api/v1/events?cursor=eyJpZCI6MTIzfQ&limit=50
```

```json
{
  "data": [...],
  "meta": {
    "pagination": {
      "nextCursor": "eyJpZCI6MTczfQ",
      "prevCursor": "eyJpZCI6MTIzfQ",
      "hasMore": true
    }
  }
}
```

### Pagination Limits

- **Default pageSize**: 20
- **Maximum pageSize**: 100
- **Requests über Maximum**: Automatisch auf Maximum reduziert

## 5. Filtering, Sorting, Search

### Filtering

```
GET /api/v1/users?status=active
GET /api/v1/users?status=active,pending     # OR
GET /api/v1/users?createdAt[gte]=2025-01-01  # Greater than or equal
GET /api/v1/users?createdAt[lte]=2025-12-31  # Less than or equal
GET /api/v1/orders?total[gt]=100             # Greater than
```

### Sorting

```
GET /api/v1/users?sort=createdAt              # Ascending (default)
GET /api/v1/users?sort=-createdAt             # Descending
GET /api/v1/users?sort=status,-createdAt      # Multiple fields
```

### Search

```
GET /api/v1/users?search=max                  # Full-text search
GET /api/v1/users?q=max                       # Alternative
GET /api/v1/users?name[contains]=müller       # Field-specific
```

## 6. Common Headers

### Request Headers

| Header | Required | Description |
|--------|----------|-------------|
| `Content-Type` | Yes | `application/json` |
| `Authorization` | Conditional | `Bearer {token}` |
| `X-Request-Id` | Recommended | Client-generated request ID |
| `Accept-Language` | Optional | `de-DE`, `en-US` |
| `X-Trace-Id` | Optional | For distributed tracing |

### Response Headers

| Header | Always | Description |
|--------|--------|-------------|
| `Content-Type` | Yes | `application/json` |
| `X-Request-Id` | Yes | Echo or generate |
| `X-RateLimit-Limit` | Yes | Max requests per window |
| `X-RateLimit-Remaining` | Yes | Remaining requests |
| `X-RateLimit-Reset` | Yes | Window reset timestamp |

## 7. OpenAPI/Swagger Documentation

### Mandatory für alle APIs

FastAPI generiert OpenAPI automatisch. Für andere Frameworks:

```yaml
# openapi.yaml
openapi: 3.1.0
info:
  title: User Service API
  version: 1.0.0
  description: API for user management

servers:
  - url: https://api.example.com/api/v1
    description: Production
  - url: https://staging-api.example.com/api/v1
    description: Staging

paths:
  /users:
    get:
      summary: List all users
      operationId: listUsers
      tags:
        - Users
      parameters:
        - name: page
          in: query
          schema:
            type: integer
            default: 1
        - name: pageSize
          in: query
          schema:
            type: integer
            default: 20
            maximum: 100
      responses:
        '200':
          description: Successful response
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/UserListResponse'
```

### TypeScript Type Generation

```bash
# Generate types from OpenAPI spec
npx openapi-typescript ./openapi.yaml -o ./src/types/api.d.ts
```

### Documentation Requirements

Jeder Endpoint MUSS dokumentieren:
- **Summary**: Kurze Beschreibung
- **Description**: Detaillierte Erklärung (wenn nötig)
- **Parameters**: Alle Query/Path/Header Parameter
- **Request Body**: Schema mit Beispielen
- **Responses**: Alle möglichen Status Codes
- **Security**: Welche Auth benötigt wird

## 8. Rate Limiting

### Standard Limits

| Tier | Limit | Window |
|------|-------|--------|
| Anonymous | 100 requests | 15 minutes |
| Authenticated | 1000 requests | 15 minutes |
| Premium | 10000 requests | 15 minutes |

### Rate Limit Response

```http
HTTP/1.1 429 Too Many Requests
X-RateLimit-Limit: 1000
X-RateLimit-Remaining: 0
X-RateLimit-Reset: 1705312800
Retry-After: 300

{
  "error": {
    "code": "RATE_LIMITED",
    "message": "Too many requests. Please retry after 300 seconds.",
    "retryAfter": 300
  }
}
```

## 9. CORS Configuration

### Allowed Origins (Production)

```typescript
// Next.js API Route
const allowedOrigins = [
  'https://app.example.com',
  'https://admin.example.com',
];

// NEVER use '*' in production
```

### Standard CORS Headers

```typescript
const corsOptions = {
  origin: allowedOrigins,
  methods: ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
  allowedHeaders: ['Content-Type', 'Authorization', 'X-Request-Id'],
  exposedHeaders: ['X-RateLimit-Limit', 'X-RateLimit-Remaining'],
  credentials: true,
  maxAge: 86400, // 24 hours
};
```

## 10. Security Checklist

Jede API MUSS:

- [ ] Authentication auf allen nicht-öffentlichen Endpoints
- [ ] Input Validation auf allen Eingaben
- [ ] Rate Limiting implementiert
- [ ] CORS strikt konfiguriert
- [ ] Keine sensiblen Daten in URLs (keine Tokens in Query Params)
- [ ] HTTPS enforced
- [ ] Request Size Limits (default: 1MB)
- [ ] SQL Injection Prevention (Parameterized Queries)
- [ ] XSS Prevention (Output Encoding)

## 11. FastAPI Implementation Template

```python
# app/main.py
from fastapi import FastAPI, Request
from fastapi.middleware.cors import CORSMiddleware
from slowapi import Limiter
from slowapi.util import get_remote_address

limiter = Limiter(key_func=get_remote_address)
app = FastAPI(
    title="Service Name",
    version="1.0.0",
    docs_url="/api/v1/docs",
    openapi_url="/api/v1/openapi.json",
)

# CORS
app.add_middleware(
    CORSMiddleware,
    allow_origins=["https://app.example.com"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# Request ID Middleware
@app.middleware("http")
async def add_request_id(request: Request, call_next):
    request_id = request.headers.get("X-Request-Id", str(uuid.uuid4()))
    response = await call_next(request)
    response.headers["X-Request-Id"] = request_id
    return response

# Rate Limited Endpoint Example
@app.get("/api/v1/users")
@limiter.limit("100/minute")
async def list_users(request: Request, page: int = 1, page_size: int = 20):
    # Implementation
    pass
```

---

**Letzte Aktualisierung**: 2025-12
**Owner**: API Design Team