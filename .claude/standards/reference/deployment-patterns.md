# Deployment Patterns

Best-Practice Patterns für Container-basiertes Deployment.

> **Referenz:** Ergänzt `.claude/standards/ci-cd-pipelines.md` mit Deployment-spezifischen Patterns.

---

## 1. Docker Patterns

### 1.1 Multi-Stage Build (Node.js)

```dockerfile
# Stage 1: Dependencies
FROM node:22-alpine AS deps
WORKDIR /app
COPY package*.json ./
RUN npm ci --only=production

# Stage 2: Builder
FROM node:22-alpine AS builder
WORKDIR /app
COPY package*.json ./
RUN npm ci
COPY . .
RUN npm run build

# Stage 3: Runner
FROM node:22-alpine AS runner
WORKDIR /app

ENV NODE_ENV=production

# Security: Non-root user
RUN addgroup --system --gid 1001 nodejs
RUN adduser --system --uid 1001 nextjs

COPY --from=builder /app/public ./public
COPY --from=builder --chown=nextjs:nodejs /app/.next/standalone ./
COPY --from=builder --chown=nextjs:nodejs /app/.next/static ./.next/static

USER nextjs

EXPOSE 3000
ENV PORT=3000

CMD ["node", "server.js"]
```

### 1.2 Multi-Stage Build (Python)

```dockerfile
# Stage 1: Builder
FROM python:3.12-slim AS builder

WORKDIR /app

# Install build dependencies
RUN apt-get update && apt-get install -y --no-install-recommends \
    build-essential \
    && rm -rf /var/lib/apt/lists/*

# Install Python dependencies
COPY requirements.txt .
RUN pip wheel --no-cache-dir --no-deps --wheel-dir /app/wheels -r requirements.txt

# Stage 2: Runner
FROM python:3.12-slim AS runner

WORKDIR /app

# Security: Non-root user
RUN addgroup --system --gid 1001 appgroup \
    && adduser --system --uid 1001 appuser

# Copy wheels and install
COPY --from=builder /app/wheels /wheels
RUN pip install --no-cache /wheels/*

# Copy application
COPY --chown=appuser:appgroup . .

USER appuser

EXPOSE 8000

CMD ["uvicorn", "app.main:app", "--host", "0.0.0.0", "--port", "8000"]
```

### 1.3 .dockerignore

```
# Git
.git
.gitignore

# Dependencies
node_modules
__pycache__
.venv
venv

# Build artifacts
.next
dist
build
*.egg-info

# Environment
.env
.env.*
!.env.example

# IDE
.idea
.vscode

# Tests
tests
**/*.test.ts
**/*.spec.ts
coverage
.pytest_cache

# Documentation
docs
*.md
!README.md

# Docker
Dockerfile*
docker-compose*
```

---

## 2. Docker Compose Patterns

### 2.1 Development Setup

```yaml
# docker-compose.yml
services:
  frontend:
    build:
      context: ./frontend
      dockerfile: Dockerfile.dev
    ports:
      - "3000:3000"
    volumes:
      - ./frontend:/app
      - /app/node_modules
    environment:
      - NEXT_PUBLIC_API_URL=http://localhost:8000
    depends_on:
      - backend

  backend:
    build:
      context: ./backend
      dockerfile: Dockerfile.dev
    ports:
      - "8000:8000"
    volumes:
      - ./backend:/app
    environment:
      - DATABASE_URL=postgresql://user:pass@db:5432/app
      - REDIS_URL=redis://cache:6379
    depends_on:
      db:
        condition: service_healthy
      cache:
        condition: service_started

  db:
    image: postgres:16-alpine
    ports:
      - "5432:5432"
    volumes:
      - postgres_data:/var/lib/postgresql/data
    environment:
      - POSTGRES_USER=user
      - POSTGRES_PASSWORD=pass
      - POSTGRES_DB=app
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U user -d app"]
      interval: 5s
      timeout: 5s
      retries: 5

  cache:
    image: redis:7-alpine
    ports:
      - "6379:6379"
    volumes:
      - redis_data:/data

volumes:
  postgres_data:
  redis_data:
```

### 2.2 Production Override

```yaml
# docker-compose.prod.yml
services:
  frontend:
    build:
      context: ./frontend
      dockerfile: Dockerfile
    ports:
      - "3000:3000"
    restart: unless-stopped
    deploy:
      resources:
        limits:
          cpus: '1'
          memory: 512M

  backend:
    build:
      context: ./backend
      dockerfile: Dockerfile
    ports:
      - "8000:8000"
    restart: unless-stopped
    deploy:
      resources:
        limits:
          cpus: '2'
          memory: 1G
    healthcheck:
      test: ["CMD", "curl", "-f", "http://localhost:8000/health"]
      interval: 30s
      timeout: 10s
      retries: 3
      start_period: 40s
```

### 2.3 Projekt- und Container-Benennung

```yaml
# PFLICHT: Expliziter Projektname nach der App
name: myapp

# Versionierte Container-Namen
services:
  backend:
    container_name: myapp-backend-v1.2.3
    image: myapp/backend:1.2.3
```

**Wichtig:**
- `name:` ist PFLICHT — ohne leitet Docker Compose den Namen vom Verzeichnis ab. Bei mehreren Apps im gleichen Verzeichnisnamen (z.B. `deploy/`) löscht `--remove-orphans` die Container der anderen App.
- Version bei jedem Deployment inkrementieren!

---

## 3. Environment Management

### 3.1 Environment Files Struktur

```
environments/
├── .env.example       # Template (committed)
├── .env.development   # Local development
├── .env.staging       # Staging environment
└── .env.production    # Production (secrets via CI/CD)
```

### 3.2 .env.example

```bash
# Application
NODE_ENV=development
APP_VERSION=0.0.0

# API
API_URL=http://localhost:8000
API_KEY=your-api-key-here

# Database
DATABASE_URL=postgresql://user:password@localhost:5432/dbname

# Redis
REDIS_URL=redis://localhost:6379

# Auth (never commit actual values!)
JWT_SECRET=generate-secure-secret-here
SESSION_SECRET=generate-secure-secret-here

# External Services
STRIPE_API_KEY=sk_test_xxx
SENDGRID_API_KEY=SG.xxx
```

### 3.3 Docker Compose mit Environment

```yaml
services:
  backend:
    env_file:
      - .env
      - .env.${ENVIRONMENT:-development}
    environment:
      # Override specific vars
      - LOG_LEVEL=${LOG_LEVEL:-info}
```

---

## 4. Health Checks

### 4.1 FastAPI Health Endpoint

```python
from fastapi import APIRouter
from pydantic import BaseModel

router = APIRouter(tags=["health"])

class HealthResponse(BaseModel):
    status: str
    version: str
    database: str
    cache: str

@router.get("/health", response_model=HealthResponse)
async def health_check():
    return {
        "status": "healthy",
        "version": settings.APP_VERSION,
        "database": await check_database(),
        "cache": await check_cache(),
    }

@router.get("/health/live")
async def liveness():
    """Kubernetes liveness probe"""
    return {"status": "alive"}

@router.get("/health/ready")
async def readiness():
    """Kubernetes readiness probe"""
    if not await is_database_ready():
        raise HTTPException(503, "Database not ready")
    return {"status": "ready"}
```

### 4.2 Next.js Health Endpoint

```typescript
// app/api/health/route.ts
import { NextResponse } from 'next/server';

export async function GET() {
  return NextResponse.json({
    status: 'healthy',
    timestamp: new Date().toISOString(),
    version: process.env.NEXT_PUBLIC_APP_VERSION || '0.0.0',
  });
}
```

---

## 5. Reverse Proxy (Nginx)

### 5.1 Nginx Configuration

```nginx
# nginx/nginx.conf
upstream frontend {
    server frontend:3000;
}

upstream backend {
    server backend:8000;
}

server {
    listen 80;
    server_name example.com;

    # Redirect HTTP to HTTPS
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name example.com;

    # SSL Configuration
    ssl_certificate /etc/nginx/ssl/cert.pem;
    ssl_certificate_key /etc/nginx/ssl/key.pem;
    ssl_protocols TLSv1.2 TLSv1.3;

    # Security Headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;

    # Frontend
    location / {
        proxy_pass http://frontend;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection 'upgrade';
        proxy_set_header Host $host;
        proxy_cache_bypass $http_upgrade;
    }

    # API
    location /api/ {
        proxy_pass http://backend/;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }

    # Health Check (no logging)
    location /health {
        access_log off;
        proxy_pass http://backend/health;
    }
}
```

### 5.2 Docker Compose mit Nginx

```yaml
services:
  nginx:
    image: nginx:alpine
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - ./nginx/nginx.conf:/etc/nginx/conf.d/default.conf:ro
      - ./nginx/ssl:/etc/nginx/ssl:ro
    depends_on:
      - frontend
      - backend
    restart: unless-stopped
```

---

## 6. CI/CD Integration

### 6.1 GitHub Actions Deployment

Deployment wird **manuell** von `release/*` Branches getriggert (`workflow_dispatch`).
Kein automatisches Deployment bei Push auf main. Der Server baut direkt aus dem Source Code.

```
Flow: release/v1.2.3 Branch erstellen
      → GitHub Actions > Deploy > Run workflow > Branch + Environment waehlen
      → SSH auf Server: git checkout release branch
      → docker compose down → docker compose up --build
      → Health Check
```

**Vollstaendiges Template:** `.claude/templates/github/workflows/deploy.yml`

**Benoetigte Secrets:**

| Secret | Beschreibung |
|--------|-------------|
| `SERVER_HOST` | IP oder Hostname des Servers |
| `SERVER_USER` | SSH-Benutzer (z.B. root) |
| `SSH_PRIVATE_KEY` | Privater SSH-Key |
| `SERVER_APP_DIR` | Pfad zur App auf dem Server (z.B. /opt/<projektname>) |

**Repository Variables** (Settings > Variables > Actions):

| Variable | Beschreibung |
|----------|-------------|
| `DEPLOY_DIR` | Unterordner mit docker-compose.yml (z.B. deploy) - leer wenn im Root |
| `HEALTH_CHECK_URL` | Health Check URL (z.B. http://localhost:8000/health) |

### 6.2 Version Tagging

```yaml
# In GitHub Actions
- name: Get version
  id: version
  run: echo "version=$(date +%Y%m%d)-${GITHUB_SHA::7}" >> $GITHUB_OUTPUT

- name: Build with version
  run: |
    docker build \
      --build-arg APP_VERSION=${{ steps.version.outputs.version }} \
      -t myapp:${{ steps.version.outputs.version }} \
      .
```

---

## 7. Rollback Strategy

### 7.1 Container Versioning

```bash
# Deployment mit Version
docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d

# Rollback zu vorheriger Version
export APP_VERSION=1.2.2
docker compose up -d

# Oder: Explizites Image Tag
docker compose pull myapp:1.2.2
docker compose up -d
```

### 7.2 Blue-Green Deployment

```yaml
# docker-compose.blue-green.yml
services:
  frontend-blue:
    image: myapp/frontend:${BLUE_VERSION}
    # ... config

  frontend-green:
    image: myapp/frontend:${GREEN_VERSION}
    # ... config

  nginx:
    # Switch zwischen blue/green via config
```

---

## 8. Monitoring Integration

### 8.1 Prometheus Metrics

```yaml
# docker-compose.monitoring.yml
services:
  prometheus:
    image: prom/prometheus:latest
    volumes:
      - ./prometheus.yml:/etc/prometheus/prometheus.yml
      - prometheus_data:/prometheus
    ports:
      - "9090:9090"

  grafana:
    image: grafana/grafana:latest
    volumes:
      - grafana_data:/var/lib/grafana
    ports:
      - "3001:3000"
    environment:
      - GF_SECURITY_ADMIN_PASSWORD=${GRAFANA_PASSWORD}

volumes:
  prometheus_data:
  grafana_data:
```

### 8.2 Log Aggregation

```yaml
services:
  backend:
    logging:
      driver: "json-file"
      options:
        max-size: "10m"
        max-file: "3"
```

---

## 9. Security Checklist

### 9.1 Pre-Deployment

- [ ] Non-root user in Dockerfile
- [ ] No secrets in images
- [ ] Minimal base images (alpine)
- [ ] Dependency vulnerabilities scanned
- [ ] .dockerignore korrekt

### 9.2 Runtime

- [ ] Resource limits gesetzt
- [ ] Health checks konfiguriert
- [ ] Logging aktiviert
- [ ] Network isolation (Docker networks)
- [ ] Secrets via environment oder secrets manager

### 9.3 Network

- [ ] HTTPS/TLS konfiguriert
- [ ] CORS restriktiv konfiguriert
- [ ] Rate limiting aktiv
- [ ] Security headers gesetzt

---

## 10. Production Troubleshooting (Learnings)

Gesammelte Erkenntnisse aus realen Deployments.

### 10.1 Docker Build Cache

**Problem**: `docker compose up --build` kann gecachte Layer mit altem Code verwenden.

```bash
# FALSCH: Kann veralteten Code deployen
docker compose up --build

# RICHTIG: In CI/CD immer ohne Cache bauen
docker compose build --no-cache && docker compose up -d
```

### 10.2 Health Checks: `expose` vs `ports`

**Problem**: Backend mit `expose` (nicht `ports`) ist vom Host nicht erreichbar.
`curl localhost:8000` funktioniert nicht.

```bash
# FALSCH: Timeout wenn Service nur "expose" nutzt
curl http://localhost:8000/health

# RICHTIG: Health-Status via Docker inspect pruefen
docker inspect --format='{{.State.Health.Status}}' container-name
```

### 10.3 Nginx DNS Caching in Docker

**Problem**: Nginx cached DNS-Aufloesungen permanent. Nach Container-Recreation
zeigt Nginx noch auf die alte IP → 502 Bad Gateway.

```nginx
# LOESUNG: Docker DNS Resolver mit TTL konfigurieren
server {
    resolver 127.0.0.11 valid=30s;  # Docker interner DNS

    set $upstream_backend backend:8000;
    location /api/ {
        proxy_pass http://$upstream_backend;
    }
}
```

**Wichtig**: Bei Verwendung von `set $upstream` muss die Variable im `proxy_pass` stehen,
nicht der Service-Name direkt. Sonst wird DNS trotzdem beim Start gecached.

### 10.4 Nginx Rate Limiting

**Problem**: `limit_req zone=api` in Server-Block erfordert Zone-Definition im `http`-Block.

```nginx
# FALSCH: Zone nur in server/location definiert
server {
    limit_req zone=api burst=20;  # ERROR: zone "api" is unknown
}

# RICHTIG: Zone muss im http-Block der zentralen nginx.conf stehen
http {
    limit_req_zone $binary_remote_addr zone=api:10m rate=10r/s;

    server {
        limit_req zone=api burst=20 nodelay;
    }
}
```

### 10.5 Frontend Docker Health Check (Next.js)

**Problem**: `wget http://localhost:3000` schlaegt fehl weil Next.js auf `0.0.0.0` lauscht,
aber wget IPv6 `[::1]` versucht. Container meldet "unhealthy" obwohl App funktioniert.

```yaml
# LOESUNG: IPv4 explizit erzwingen
healthcheck:
  test: ["CMD", "wget", "--spider", "-q", "http://127.0.0.1:3000"]
  # NICHT: http://localhost:3000 (kann zu IPv6 resolven)
```

### 10.6 SCP Deployment: .git Permissions

**Problem**: `.git/objects/pack` Dateien koennen nach SCP `root` gehoeren.
Git-Operationen auf dem Server schlagen dann fehl.

```bash
# LOESUNG: .git auf Server loeschen vor SCP
ssh server "rm -rf /app/.git"
scp -r . server:/app/
```

### 10.7 Alembic Migrationen: Partial Run Corruption

**Problem**: Abgebrochene Alembic-Migrationen koennen `pg_catalog.pg_class.relnatts` korrumpieren.
Symptom: `INSERT` schlaegt fehl mit falscher Spaltenanzahl.

```sql
-- Diagnose: Pruefe ob relnatts mit tatsaechlicher Spaltenanzahl uebereinstimmt
SELECT c.relname, c.relnatts,
  (SELECT count(*) FROM pg_attribute a WHERE a.attrelid = c.oid AND a.attnum > 0 AND NOT a.attisdropped) as actual
FROM pg_class c
JOIN pg_namespace n ON c.relnamespace = n.oid
WHERE n.nspname = 'public' AND c.relkind = 'r';

-- Fix: relnatts korrigieren
UPDATE pg_class SET relnatts = <actual_count> WHERE oid = '<table_oid>';
```

**Praevention**: Alembic-Migrationen immer mit Raw SQL (`sa.text()`) statt `op.create_table()` fuer
komplexe DDL (besonders mit Enums). SQLAlchemy's `create_type=False` wird teilweise ignoriert.

### 10.8 Docker Compose: Cross-Project Orphan Removal

**Problem**: Mehrere Apps deployen aus gleichnamigen Verzeichnissen (z.B. beide aus `deploy/`).
Docker Compose leitet den Projektnamen vom Verzeichnisnamen ab → beide haben Projektname `deploy`.
`docker compose down --remove-orphans` der einen App loescht die Container der anderen App.

```yaml
# LOESUNG: Expliziten Projektnamen in docker-compose.yml setzen
name: feedbackcollector    # PFLICHT

services:
  frontend: ...    # Image: feedbackcollector-frontend (statt deploy-frontend)
```

**Ohne `name:`**: Images heissen `deploy-frontend`, `deploy-backend` — nicht unterscheidbar.
**Mit `name:`**: Images heissen `feedbackcollector-frontend`, `teachview-frontend` — klar zugeordnet.

## Referenzen

- `.claude/standards/ci-cd-pipelines.md` - CI/CD Workflows
- `.claude/standards/monitoring-observability.md` - Monitoring Setup
- `.claude/standards/security.md` - Security Best Practices
- `techstack.md` - Tech Stack Spezifikation
