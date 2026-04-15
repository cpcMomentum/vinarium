# Environment Management Standards

Diese Standards definieren einheitliche Patterns für Environment-Management über alle Microservices hinweg.

## 1. Environment Tiers

### Standard Environments

| Environment | Zweck | Daten | Zugriff |
|-------------|-------|-------|---------|
| `local` | Entwicklung auf lokaler Maschine | Mock/Seed Data | Developer |
| `dev` | Shared Development & Integration | Synthetic Data | Team |
| `staging` | Pre-Production Testing | Anonymized Prod Copy | Team + QA |
| `production` | Live System | Real Data | Restricted |

### Environment Naming Convention

```
# Kubernetes Namespaces / Docker Networks
myapp-local
myapp-dev
myapp-staging
myapp-production

# DNS Pattern
myapp.local.internal
myapp.dev.example.com
myapp.staging.example.com
myapp.example.com
```

## 2. Environment Variables

### Naming Convention

```bash
# Format: [SERVICE]_[CATEGORY]_[NAME]
# All uppercase, underscore separated

# Database
DATABASE_HOST=localhost
DATABASE_PORT=5432
DATABASE_NAME=myapp
DATABASE_USER=myapp_user
DATABASE_PASSWORD=secret

# External Services
STRIPE_API_KEY=sk_test_xxx
OPENAI_API_KEY=sk-xxx

# Application
APP_ENV=development
APP_DEBUG=true
APP_LOG_LEVEL=debug
APP_VERSION=1.2.3

# URLs
APP_URL=http://localhost:3000
API_URL=http://localhost:8000
```

### Required Environment Variables

Jedes Projekt MUSS diese definieren:

```bash
# Mandatory for ALL projects
APP_ENV=                    # local | dev | staging | production
APP_VERSION=                # Semantic version
LOG_LEVEL=                  # error | warn | info | debug
SERVICE_NAME=               # For logging/tracing

# Mandatory if using Database
DATABASE_URL=               # Connection string OR individual vars

# Mandatory if using Auth
AUTH_SECRET=                # JWT/Session secret (min 32 chars)
```

## 3. Configuration Files Structure

### .env File Hierarchy

```
project/
├── .env.example           # Template with ALL variables (committed)
├── .env                   # Local overrides (NOT committed)
├── .env.local             # Local development (NOT committed)
├── .env.development       # Development defaults (committed)
├── .env.staging           # Staging defaults (committed, no secrets)
├── .env.production        # Production defaults (committed, no secrets)
└── .env.test              # Test environment (committed)
```

### .env.example Template

```bash
# ===========================================
# Application Configuration
# ===========================================
# Copy this file to .env and fill in values

# Environment: local | dev | staging | production
APP_ENV=local

# Application
APP_URL=http://localhost:3000
APP_DEBUG=false
APP_VERSION=0.0.0

# Logging
LOG_LEVEL=info
SERVICE_NAME=myapp

# ===========================================
# Database
# ===========================================
DATABASE_HOST=localhost
DATABASE_PORT=5432
DATABASE_NAME=myapp
DATABASE_USER=
DATABASE_PASSWORD=

# OR use connection string
# DATABASE_URL=postgresql://user:pass@host:5432/db

# ===========================================
# Authentication
# ===========================================
# Generate with: openssl rand -base64 32
AUTH_SECRET=

# ===========================================
# External Services
# ===========================================
# STRIPE_API_KEY=
# OPENAI_API_KEY=

# ===========================================
# Feature Flags
# ===========================================
FEATURE_NEW_DASHBOARD=false
```

## 4. Secret Management

### Local Development

```bash
# Use .env file (NEVER commit)
# Add to .gitignore:
.env
.env.local
*.local.env
```

### Production Secrets

**NIEMALS** Secrets in:
- Git Repository
- Docker Images
- Environment Files im Repo
- Logs

**Empfohlene Secret Managers:**

| Provider | Tool | Use Case |
|----------|------|----------|
| AWS | Secrets Manager | AWS Infrastructure |
| GCP | Secret Manager | GCP Infrastructure |
| Azure | Key Vault | Azure Infrastructure |
| Self-Hosted | HashiCorp Vault | Multi-Cloud / On-Prem |
| Vercel | Environment Variables | Vercel Deployments |
| GitHub | Actions Secrets | CI/CD Only |

### Docker Secret Injection

```yaml
# docker-compose.yml (Development)
services:
  api:
    env_file:
      - .env
    environment:
      - APP_ENV=development

# docker-compose.prod.yml (Production)
services:
  api:
    environment:
      - DATABASE_URL=${DATABASE_URL}
      - AUTH_SECRET=${AUTH_SECRET}
    # Secrets injected from orchestrator (K8s, Docker Swarm)
```

### Kubernetes Secrets

```yaml
# k8s/secrets.yaml (Template - actual values from Secret Manager)
apiVersion: v1
kind: Secret
metadata:
  name: myapp-secrets
  namespace: myapp-production
type: Opaque
stringData:
  DATABASE_URL: "${DATABASE_URL}"
  AUTH_SECRET: "${AUTH_SECRET}"
---
apiVersion: v1
kind: ConfigMap
metadata:
  name: myapp-config
  namespace: myapp-production
data:
  APP_ENV: "production"
  LOG_LEVEL: "info"
  SERVICE_NAME: "myapp"
```

## 5. Environment-Specific Configuration

### TypeScript/Next.js

```typescript
// config/index.ts
const config = {
  env: process.env.APP_ENV || 'local',
  isProduction: process.env.APP_ENV === 'production',
  isDevelopment: process.env.APP_ENV === 'development' || process.env.APP_ENV === 'local',

  app: {
    url: process.env.APP_URL || 'http://localhost:3000',
    version: process.env.APP_VERSION || '0.0.0',
    debug: process.env.APP_DEBUG === 'true',
  },

  api: {
    url: process.env.API_URL || 'http://localhost:8000',
    timeout: parseInt(process.env.API_TIMEOUT || '30000', 10),
  },

  database: {
    url: process.env.DATABASE_URL,
    poolSize: parseInt(process.env.DATABASE_POOL_SIZE || '10', 10),
  },

  logging: {
    level: process.env.LOG_LEVEL || 'info',
    serviceName: process.env.SERVICE_NAME || 'unknown',
  },
} as const;

// Validation
function validateConfig() {
  const required = ['APP_ENV', 'DATABASE_URL', 'AUTH_SECRET'];
  const missing = required.filter(key => !process.env[key]);

  if (missing.length > 0) {
    throw new Error(`Missing required environment variables: ${missing.join(', ')}`);
  }
}

if (config.isProduction) {
  validateConfig();
}

export default config;
```

### Python/FastAPI

```python
# app/core/config.py
from pydantic_settings import BaseSettings
from functools import lru_cache
from typing import Literal

class Settings(BaseSettings):
    # Environment
    app_env: Literal["local", "dev", "staging", "production"] = "local"
    app_version: str = "0.0.0"
    app_debug: bool = False

    # Logging
    log_level: str = "info"
    service_name: str = "myapp"

    # Database
    database_url: str
    database_pool_size: int = 10

    # Auth
    auth_secret: str

    # Computed
    @property
    def is_production(self) -> bool:
        return self.app_env == "production"

    @property
    def is_development(self) -> bool:
        return self.app_env in ("local", "dev")

    class Config:
        env_file = ".env"
        env_file_encoding = "utf-8"

@lru_cache()
def get_settings() -> Settings:
    return Settings()

settings = get_settings()
```

## 6. Docker Environment Handling

### docker-compose.yml (Development)

```yaml
version: '3.8'

services:
  app:
    build:
      context: .
      dockerfile: Dockerfile
      args:
        - APP_ENV=development
    env_file:
      - .env
    environment:
      - APP_ENV=development
      - APP_DEBUG=true
    volumes:
      - .:/app
      - /app/node_modules
    ports:
      - "3000:3000"

  db:
    image: postgres:16.1
    environment:
      POSTGRES_DB: ${DATABASE_NAME:-myapp}
      POSTGRES_USER: ${DATABASE_USER:-postgres}
      POSTGRES_PASSWORD: ${DATABASE_PASSWORD:-postgres}
    volumes:
      - postgres_data:/var/lib/postgresql/data
    ports:
      - "5432:5432"

volumes:
  postgres_data:
```

### docker-compose.prod.yml

```yaml
version: '3.8'

services:
  app:
    image: myapp:${APP_VERSION:-latest}
    environment:
      - APP_ENV=production
      - APP_DEBUG=false
      - LOG_LEVEL=info
    # Secrets from external source (K8s, Swarm, etc.)
    secrets:
      - database_url
      - auth_secret
    deploy:
      replicas: 3
      resources:
        limits:
          cpus: '0.5'
          memory: 512M

secrets:
  database_url:
    external: true
  auth_secret:
    external: true
```

## 7. Environment Validation Checklist

### Before Deployment

- [ ] Alle required Environment Variables gesetzt
- [ ] Secrets nicht hardcoded oder im Repo
- [ ] DATABASE_URL zeigt auf korrektes Environment
- [ ] API URLs korrekt konfiguriert
- [ ] Log Level angemessen (nicht debug in prod)
- [ ] Feature Flags korrekt gesetzt
- [ ] CORS Origins auf Environment angepasst

### Environment Parity

Halte Environments so ähnlich wie möglich:

| Aspect | Local | Dev | Staging | Production |
|--------|-------|-----|---------|------------|
| Database | PostgreSQL 16 | PostgreSQL 16 | PostgreSQL 16 | PostgreSQL 16 |
| Cache | Redis 7 | Redis 7 | Redis 7 | Redis 7 |
| OS | Docker Linux | Docker Linux | Linux | Linux |
| Node | 20.x | 20.x | 20.x | 20.x |

## 8. Feature Flags

### Simple Environment-based Flags

```bash
# .env
FEATURE_NEW_DASHBOARD=false
FEATURE_BETA_API=true
```

```typescript
// utils/features.ts
export const features = {
  newDashboard: process.env.FEATURE_NEW_DASHBOARD === 'true',
  betaApi: process.env.FEATURE_BETA_API === 'true',
};

// Usage
if (features.newDashboard) {
  // New implementation
}
```

### Per-Environment Feature Matrix

```typescript
// config/features.ts
const featureMatrix = {
  local: {
    newDashboard: true,  // Enable for testing
    betaApi: true,
  },
  dev: {
    newDashboard: true,
    betaApi: true,
  },
  staging: {
    newDashboard: true,  // QA testing
    betaApi: false,
  },
  production: {
    newDashboard: false, // Not released yet
    betaApi: false,
  },
};

export const features = featureMatrix[process.env.APP_ENV || 'local'];
```

---

**Letzte Aktualisierung**: 2025-12
**Owner**: DevOps Team
