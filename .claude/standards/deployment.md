# Deployment Standards

Diese Standards definieren einheitliches Deployment fuer alle Projekte.

> **Referenz:** Ergaenzt `ci-cd-pipelines.md` (CI) um den CD-Teil (Continuous Deployment).

---

## 1. Plattform-Entscheidung

### Entscheidungsbaum

```
Docker in techstack.md?
│
├── JA (Docker-basiertes Deployment)
│   │
│   ├── Einfaches Setup → Docker Compose auf VPS (Nginx Reverse Proxy)
│   ├── Skalierung noetig → Kubernetes / Docker Swarm
│   └── Managed → Railway, Render, Fly.io
│
├── NEIN (Natives Deployment)
│   │
│   ├── Next.js / Statisch → Vercel, Netlify, Cloudflare Pages
│   ├── PHP → Apache/Nginx + PHP-FPM (klassisch)
│   ├── Nextcloud App → Nextcloud App Store / manuelle Installation
│   ├── Java → WAR/JAR Deployment, Tomcat, Spring Boot Executable
│   ├── Go → Binary Deployment (systemd Service)
│   └── Sonstiges → Hosting-Ziel aus techstack.md verwenden
│
└── OPTIONAL (Docker fuer Dev, nativ fuer Prod)
    └── Beide Wege dokumentieren
```

> **Hinweis:** Docker ist EMPFOHLEN aber nicht mehr PFLICHT. Die Deployment-Strategie wird durch `techstack.md` Sektion "Infrastruktur" bestimmt.

### Plattform-Vergleich

| Kriterium | Nginx + VPS | Vercel |
|-----------|-------------|--------|
| **Kosten** | Fest (ab ~5€/Monat) | Variabel (Traffic-basiert) |
| **Kontrolle** | Voll (SSH, Docker, Logs) | Eingeschränkt |
| **Backend** | Beliebig (FastAPI, DB, Redis) | Nur Serverless Functions |
| **DSGVO** | DE-Server (Hetzner) | US-basiert |
| **Multi-Domain** | Nginx-Config | Pro Projekt separat |
| **Docker** | Pflicht | Nicht möglich |
| **Empfehlung** | **Default für alle Projekte** | Nur für rein statische Seiten |

---

## 2. Server-Architektur

### Ein Reverse Proxy für alle Projekte (PFLICHT)

```
Internet
    │
    ▼
┌──────────────────────────────────────────────────┐
│  Nginx (:80/:443) - Zentraler Reverse Proxy      │
│  SSL via Let's Encrypt (certbot)                  │
├──────────────────────────────────────────────────┤
│                                                    │
│  projekt-a.de     → projekt-a-frontend:3000       │
│  api.projekt-a.de → projekt-a-backend:8000        │
│                                                    │
│  projekt-b.de     → projekt-b-frontend:3000       │
│  api.projekt-b.de → projekt-b-backend:8000        │
│                                                    │
│  projekt-c.de     → projekt-c-frontend:3000       │
│                                                    │
└──────────────────────────────────────────────────┘
```

**Prinzip:** KEIN Reverse Proxy pro Projekt. Alle Projekte teilen sich EINEN Nginx (oder Caddy). Sonst gibt es Port-Konflikte auf 80/443.

### Verzeichnis-Struktur auf dem Server

```
/opt/
├── nginx-proxy/
│   ├── docker-compose.yml    # Nginx + Certbot
│   ├── nginx.conf            # Haupt-Config
│   └── conf.d/               # Domain-Configs
│       ├── projekt-a.conf
│       └── projekt-b.conf
│
├── projekt-a/
│   ├── docker-compose.yml    # Frontend, Backend, DB
│   ├── frontend/
│   └── backend/
│
├── projekt-b/
│   ├── docker-compose.yml
│   └── frontend/
│
└── ...
```

### Shared Docker Network

```yaml
# Alle Projekte müssen im selben Netzwerk sein
networks:
  web:
    external: true    # Erstellt mit: docker network create web
```

---

## 3. Nginx Reverse Proxy (Default)

### 3.1 Nginx docker-compose.yml

```yaml
# /opt/nginx-proxy/docker-compose.yml
services:
  nginx:
    image: nginx:alpine
    container_name: nginx-proxy
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - ./nginx.conf:/etc/nginx/nginx.conf:ro
      - ./conf.d:/etc/nginx/conf.d:ro
      - ./certbot/conf:/etc/letsencrypt:ro
      - ./certbot/www:/var/www/certbot:ro
    networks:
      - web
    restart: unless-stopped
    depends_on:
      - certbot

  certbot:
    image: certbot/certbot
    container_name: certbot
    volumes:
      - ./certbot/conf:/etc/letsencrypt
      - ./certbot/www:/var/www/certbot
    entrypoint: "/bin/sh -c 'trap exit TERM; while :; do certbot renew; sleep 12h & wait $${!}; done;'"
    restart: unless-stopped

networks:
  web:
    external: true
```

### 3.2 Nginx Haupt-Config

```nginx
# /opt/nginx-proxy/nginx.conf
user nginx;
worker_processes auto;
error_log /var/log/nginx/error.log warn;
pid /var/run/nginx.pid;

events {
    worker_connections 1024;
}

http {
    include /etc/nginx/mime.types;
    default_type application/octet-stream;

    log_format main '$remote_addr - $remote_user [$time_local] "$request" '
                    '$status $body_bytes_sent "$http_referer" '
                    '"$http_user_agent" "$http_x_forwarded_for"';

    access_log /var/log/nginx/access.log main;
    sendfile on;
    keepalive_timeout 65;
    gzip on;
    gzip_types text/plain text/css application/json application/javascript text/xml application/xml;

    # SSL Settings
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_prefer_server_ciphers on;
    ssl_session_cache shared:SSL:10m;
    ssl_session_timeout 10m;

    include /etc/nginx/conf.d/*.conf;
}
```

### 3.3 Domain-Config Template

```nginx
# /opt/nginx-proxy/conf.d/projekt.conf

# HTTP → HTTPS Redirect
server {
    listen 80;
    listen [::]:80;
    server_name projekt.de www.projekt.de;

    # Let's Encrypt Challenge
    location /.well-known/acme-challenge/ {
        root /var/www/certbot;
    }

    location / {
        return 301 https://$host$request_uri;
    }
}

# HTTPS Server
server {
    listen 443 ssl;
    listen [::]:443 ssl;
    http2 on;                    # NICHT "listen 443 ssl http2" (deprecated seit nginx 1.25.1)
    server_name projekt.de www.projekt.de;

    ssl_certificate /etc/letsencrypt/live/projekt.de/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/projekt.de/privkey.pem;

    # Docker DNS Resolver (PFLICHT bei Docker-Containern als Upstream)
    # Ohne resolver cached nginx die Container-IPs beim Start und 502t nach Container-Recreation
    resolver 127.0.0.11 valid=30s;
    resolver_timeout 5s;

    # Security Headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Strict-Transport-Security "max-age=63072000" always;

    # Gzip
    gzip on;
    gzip_types text/plain text/css application/json application/javascript text/xml application/xml;

    # Frontend
    location / {
        proxy_pass http://projekt-frontend:3000;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection 'upgrade';
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_cache_bypass $http_upgrade;
    }

    # Health check endpoint
    location /nginx-health {
        access_log off;
        return 200 "healthy\n";
        add_header Content-Type text/plain;
    }
}

# API (optional - nur bei Full-Stack, oder als /api/ Location im selben server-Block)
server {
    listen 443 ssl;
    listen [::]:443 ssl;
    http2 on;
    server_name api.projekt.de;

    ssl_certificate /etc/letsencrypt/live/projekt.de/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/projekt.de/privkey.pem;

    resolver 127.0.0.11 valid=30s;
    resolver_timeout 5s;

    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Strict-Transport-Security "max-age=63072000" always;

    location / {
        proxy_pass http://projekt-backend:8000;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

> **WICHTIG: Rate Limiting Zones** (`limit_req zone=...`) müssen in der zentralen `nginx.conf` im `http`-Block definiert werden, NICHT in den Domain-Configs. Referenziere keine Zones die nicht existieren — sonst schlägt `nginx -t` fehl und Reload ist unmöglich.

### 3.4 SSL-Zertifikate mit certbot

```bash
# Erstes Zertifikat holen (einmalig)
docker compose run --rm certbot certonly \
  --webroot \
  --webroot-path=/var/www/certbot \
  -d projekt.de \
  -d www.projekt.de \
  -d api.projekt.de \
  --email admin@projekt.de \
  --agree-tos \
  --no-eff-email

# Nginx neu laden nach Zertifikat
docker exec nginx-proxy nginx -s reload

# Auto-Renewal läuft automatisch (certbot Container)
```

### 3.5 Nginx-Befehle

| Aktion | Befehl |
|--------|--------|
| Config neu laden | `docker exec nginx-proxy nginx -s reload` |
| Config testen | `docker exec nginx-proxy nginx -t` |
| Logs ansehen | `docker logs nginx-proxy --tail 50` |
| Cert erneuern | `docker compose run --rm certbot renew` |

### 3.6 Ersteinrichtung auf neuem Server

```bash
# 1. Docker Network erstellen (einmalig)
docker network create web

# 2. Nginx starten
cd /opt/nginx-proxy
docker compose up -d

# 3. DNS: Domain → Server-IP (A-Record)

# 4. SSL-Zertifikat holen
docker compose run --rm certbot certonly \
  --webroot --webroot-path=/var/www/certbot \
  -d projekt.de -d www.projekt.de

# 5. Domain-Config erstellen
nano conf.d/projekt.conf

# 6. Nginx Config neu laden
docker exec nginx-proxy nginx -s reload
```

---

## 4. Projekt docker-compose.yml (Production)

### 4.1 Next.js-only Projekt

```yaml
# docker-compose.yml
name: projektname    # PFLICHT: Expliziter Projektname (siehe 4.3)

services:
  frontend:
    build:
      context: ./frontend
      dockerfile: Dockerfile
    container_name: ${PROJECT_NAME}-frontend-${VERSION}
    expose:
      - "3000"
    environment:
      - NODE_ENV=production
    networks:
      - web
    restart: unless-stopped
    healthcheck:
      test: ["CMD", "node", "-e", "fetch('http://localhost:3000').then(r => process.exit(r.ok ? 0 : 1)).catch(() => process.exit(1))"]
      interval: 10s
      timeout: 5s
      retries: 5
      start_period: 30s

networks:
  web:
    external: true
```

### 4.2 Full-Stack Projekt

```yaml
# docker-compose.yml
name: projektname    # PFLICHT: Expliziter Projektname (siehe 4.3)

services:
  frontend:
    build:
      context: ./frontend
      dockerfile: Dockerfile
    container_name: ${PROJECT_NAME}-frontend-${VERSION}
    expose:
      - "3000"
    environment:
      - NODE_ENV=production
      - NEXT_PUBLIC_API_URL=${API_URL}
    networks:
      - web
      - internal
    restart: unless-stopped
    healthcheck:
      test: ["CMD", "node", "-e", "fetch('http://localhost:3000').then(r => process.exit(r.ok ? 0 : 1)).catch(() => process.exit(1))"]
      interval: 10s
      timeout: 5s
      retries: 5
      start_period: 30s

  backend:
    build:
      context: ./backend
      dockerfile: Dockerfile
    container_name: ${PROJECT_NAME}-backend-${VERSION}
    expose:
      - "8000"
    environment:
      - DATABASE_URL=postgresql://${DB_USER}:${DB_PASS}@db:5432/${DB_NAME}
    networks:
      - web
      - internal
    depends_on:
      db:
        condition: service_healthy
    restart: unless-stopped
    healthcheck:
      test: ["CMD", "curl", "-f", "http://localhost:8000/health"]
      interval: 30s
      timeout: 10s
      retries: 3
      start_period: 40s

  db:
    image: postgres:17-alpine
    container_name: ${PROJECT_NAME}-db-${VERSION}
    volumes:
      - postgres_data:/var/lib/postgresql/data
    environment:
      - POSTGRES_USER=${DB_USER}
      - POSTGRES_PASSWORD=${DB_PASS}
      - POSTGRES_DB=${DB_NAME}
    networks:
      - internal
    restart: unless-stopped
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U ${DB_USER} -d ${DB_NAME}"]
      interval: 5s
      timeout: 5s
      retries: 5

volumes:
  postgres_data:

networks:
  web:
    external: true
  internal:
    driver: bridge
```

### 4.3 Pflicht-Regeln für docker-compose.yml

| Regel | Beschreibung |
|-------|-------------|
| **`name: projektname`** | **PFLICHT:** Expliziter Projektname nach App benannt (siehe Warnung unten) |
| **`expose` statt `ports`** | Dienste sind nur über Nginx erreichbar, nicht direkt |
| **`networks: web`** | Alle Dienste die Nginx erreichen muss im `web` Netzwerk |
| **`container_name` mit Version** | `projekt-frontend-v1.2.0` für Identifikation |
| **`healthcheck`** | PFLICHT für jeden Service |
| **`restart: unless-stopped`** | Auto-Restart bei Crash |
| **Keine `ports`-Bindings auf Host** | Außer für Development oder DB-Zugriff |

> **WARNUNG: `name:` Property ist PFLICHT!**
> Ohne expliziten `name:` leitet Docker Compose den Projektnamen vom **Verzeichnisnamen** ab.
> Wenn mehrere Apps aus gleichnamigen Verzeichnissen deployt werden (z.B. beide aus `deploy/`),
> erhalten sie denselben Projektnamen. `docker compose down --remove-orphans` löscht dann
> die Container der **anderen** App als vermeintliche "Orphans".
>
> ```yaml
> # RICHTIG: Expliziter Name nach der App
> name: feedbackcollector
> services:
>   frontend: ...    # Image: feedbackcollector-frontend
>
> # FALSCH: Kein name: → Projektname wird vom Verzeichnis abgeleitet
> services:
>   frontend: ...    # Image: deploy-frontend (wenn aus deploy/ gestartet)
> ```

---

## 5. Dockerfile Pflicht-Konfiguration

### 5.1 Next.js (PFLICHT: `output: 'standalone'`)

**`next.config.ts`** MUSS enthalten:

```typescript
const nextConfig: NextConfig = {
  output: 'standalone',    // PFLICHT für Docker
  // ...
};
```

**Dockerfile-Pflichten:**

| Pflicht | Warum |
|---------|-------|
| Multi-Stage Build | Kleineres Image (deps → builder → runner) |
| `node:XX-alpine` | Minimales Base-Image |
| Non-root User (`nextjs:nodejs`) | Security |
| `COPY .next/standalone` | Nutzt `output: 'standalone'` |
| `ENV HOSTNAME "0.0.0.0"` | Container-Zugriff von außen |
| `NEXT_TELEMETRY_DISABLED=1` | Keine Telemetrie in Production |

### 5.2 FastAPI (PFLICHT)

**Dockerfile-Pflichten:**

| Pflicht | Warum |
|---------|-------|
| Multi-Stage Build | Kleineres Image (builder → runner) |
| `python:XX-slim` | Minimales Base-Image |
| Non-root User (`appuser:appgroup`) | Security |
| `--no-cache-dir` | Kein pip-Cache im Image |
| `PYTHONDONTWRITEBYTECODE=1` | Keine .pyc Dateien |

Vollständige Dockerfile-Templates: Siehe `ci-cd-pipelines.md` Abschnitt 4.

---

## 6. GitHub Actions - Deploy Workflow

### 6.1 deploy.yml (Hetzner VPS mit Firewall)

> **Trigger-Strategie:** NUR `workflow_dispatch` — KEIN automatischer Deploy bei Push auf main.
> Branch/Tag wird über den eingebauten GitHub-Dropdown "Use workflow from" gewählt.

> **Security:** Secrets NIEMALS direkt als `${{ secrets.X }}` in `script:` verwenden (Command Injection Risiko).
> Stattdessen `env:` Block + `envs:` Parameter nutzen.

> **Deploy-Strategie:** SCP-basiert. Code wird via SCP auf den Server kopiert, dort gebaut und gestartet.
> Kein Git auf dem Server nötig.

```yaml
name: Deploy

on:
  workflow_dispatch:
    inputs:
      skip_tests:
        description: "Skip test job"
        required: false
        default: false
        type: boolean

concurrency:
  group: production-deploy
  cancel-in-progress: false

jobs:
  test:
    name: Run Tests
    runs-on: ubuntu-latest
    if: ${{ !inputs.skip_tests }}
    steps:
      - uses: actions/checkout@v6

      - uses: actions/setup-node@v6
        with:
          node-version: "22"
          cache: "npm"
          cache-dependency-path: frontend/package-lock.json

      - name: Frontend - Install & Lint & Build
        working-directory: frontend
        run: |
          npm ci
          npm run lint
          npm run build

      # === Backend (einkommentieren wenn vorhanden) ===
      # - uses: actions/setup-python@v6
      #   with:
      #     python-version: "3.12"
      # - name: Backend - Lint
      #   working-directory: backend
      #   run: |
      #     pip install ruff
      #     ruff check .
      #     ruff format --check .

  deploy:
    name: Deploy to Server
    runs-on: ubuntu-latest
    needs: [test]
    if: ${{ always() && (needs.test.result == 'success' || needs.test.result == 'skipped') }}

    steps:
      - uses: actions/checkout@v6

      - name: Get runner IP
        id: ip
        run: echo "ipv4=$(curl -s https://api.ipify.org)" >> $GITHUB_OUTPUT

      # === Hetzner Firewall (entfernen wenn kein Hetzner) ===
      - name: Whitelist IP on Hetzner Firewall
        uses: adnanjaw/ip-whitelist-on-hetznerfw@v2.2
        with:
          hetzner_api_key: ${{ secrets.HETZNER_API_KEY }}
          ip_address: ${{ steps.ip.outputs.ipv4 }}
          firewall_name: ${{ vars.HETZNER_FIREWALL_NAME }}
          direction: in
          protocol: tcp
          port: 22
          cleanup: true

      - name: Copy files to server
        uses: appleboy/scp-action@v1.0.0
        with:
          host: ${{ vars.SERVER_HOST }}
          username: ${{ vars.SERVER_USERNAME }}
          key: ${{ secrets.SERVER_SSH_KEY }}
          port: 22
          source: "."
          target: ${{ vars.DEPLOY_PATH }}
          overwrite: true
          rm: false

      - name: Deploy application
        uses: appleboy/ssh-action@v1.2.5
        env:
          DEPLOY_PATH: ${{ vars.DEPLOY_PATH }}
          # === Projekt-spezifische Secrets hier als ENV_* hinzufuegen ===
          # ENV_SECRET_KEY: ${{ secrets.SECRET_KEY }}
          # ENV_POSTGRES_PASSWORD: ${{ secrets.POSTGRES_PASSWORD }}
          # ENV_POSTGRES_USER: ${{ vars.POSTGRES_USER }}
          # ENV_POSTGRES_DB: ${{ vars.POSTGRES_DB }}
        with:
          host: ${{ vars.SERVER_HOST }}
          username: ${{ vars.SERVER_USERNAME }}
          key: ${{ secrets.SERVER_SSH_KEY }}
          port: 22
          command_timeout: 10m
          envs: DEPLOY_PATH
          # envs: DEPLOY_PATH,ENV_SECRET_KEY,ENV_POSTGRES_PASSWORD,ENV_POSTGRES_USER,ENV_POSTGRES_DB
          script: |
            set -e
            cd "$DEPLOY_PATH"

            echo "=== Generating .env.production ==="
            # .env aus Secrets generieren (envs-Parameter oben einkommentieren!)
            # cat > .env.production << EOF
            # SECRET_KEY=${ENV_SECRET_KEY}
            # POSTGRES_USER=${ENV_POSTGRES_USER}
            # POSTGRES_PASSWORD=${ENV_POSTGRES_PASSWORD}
            # POSTGRES_DB=${ENV_POSTGRES_DB}
            # EOF

            echo "=== Stopping existing containers ==="
            docker compose down --remove-orphans || true

            echo "=== Building and starting containers ==="
            docker compose build --no-cache
            docker compose up -d

            echo "=== Copying nginx config to central proxy ==="
            # Pfad anpassen je nach Projekt-Struktur:
            # cp nginx-proxy/*.conf /opt/nginx-proxy/conf.d/ 2>/dev/null || true
            # docker exec nginx-proxy nginx -t && docker exec nginx-proxy nginx -s reload || true

            echo "=== Health Check (Docker health status) ==="
            RETRIES=10
            DELAY=10
            # CONTAINER anpassen auf den primaeren Health-Check-Container
            CONTAINER="PROJEKTNAME-frontend"
            for i in $(seq 1 $RETRIES); do
              STATUS=$(docker inspect --format='{{.State.Health.Status}}' "$CONTAINER" 2>/dev/null || echo "unknown")
              if [ "$STATUS" = "healthy" ]; then
                echo "Health check passed (attempt $i/$RETRIES)"
                break
              fi
              if [ "$i" -eq "$RETRIES" ]; then
                echo "Health check failed after $RETRIES attempts (status: $STATUS)"
                docker compose logs --tail=50
                exit 1
              fi
              echo "Health check attempt $i/$RETRIES - status: $STATUS, retrying in ${DELAY}s..."
              sleep $DELAY
            done

            echo "=== Pruning old images ==="
            docker image prune -f

            echo "=== Deployment complete ==="
            docker compose ps

      - name: Deployment Summary
        if: always()
        run: |
          echo "## Deployment Summary" >> $GITHUB_STEP_SUMMARY
          echo "" >> $GITHUB_STEP_SUMMARY
          echo "| Detail | Value |" >> $GITHUB_STEP_SUMMARY
          echo "|--------|-------|" >> $GITHUB_STEP_SUMMARY
          echo "| **Branch** | \`${{ github.ref_name }}\` |" >> $GITHUB_STEP_SUMMARY
          echo "| **Tests skipped** | ${{ inputs.skip_tests }} |" >> $GITHUB_STEP_SUMMARY
          echo "| **Triggered by** | @${{ github.actor }} |" >> $GITHUB_STEP_SUMMARY
          echo "| **Status** | ${{ job.status }} |" >> $GITHUB_STEP_SUMMARY
```

### 6.2 shutdown.yml

```yaml
name: Shutdown

on:
  workflow_dispatch:

concurrency:
  group: production-deploy
  cancel-in-progress: false

jobs:
  shutdown:
    name: Shutdown Containers
    runs-on: ubuntu-latest
    steps:
      - name: Get runner IP
        id: ip
        run: echo "ipv4=$(curl -s https://api.ipify.org)" >> $GITHUB_OUTPUT

      # === Hetzner Firewall (entfernen wenn kein Hetzner) ===
      - name: Whitelist IP on Hetzner Firewall
        uses: adnanjaw/ip-whitelist-on-hetznerfw@v2.2
        with:
          hetzner_api_key: ${{ secrets.HETZNER_API_KEY }}
          ip_address: ${{ steps.ip.outputs.ipv4 }}
          firewall_name: ${{ vars.HETZNER_FIREWALL_NAME }}
          direction: in
          protocol: tcp
          port: 22
          cleanup: true

      - name: Stop containers
        uses: appleboy/ssh-action@v1.2.5
        env:
          DEPLOY_PATH: ${{ vars.DEPLOY_PATH }}
        with:
          host: ${{ vars.SERVER_HOST }}
          username: ${{ vars.SERVER_USERNAME }}
          key: ${{ secrets.SERVER_SSH_KEY }}
          port: 22
          envs: DEPLOY_PATH
          script: |
            set -e
            cd "$DEPLOY_PATH"
            echo "=== Stopping containers ==="
            docker compose down --remove-orphans
            echo "=== Container status ==="
            docker compose ps
            echo "=== Shutdown complete ==="

      - name: Shutdown Summary
        if: always()
        run: |
          echo "## Shutdown Summary" >> $GITHUB_STEP_SUMMARY
          echo "" >> $GITHUB_STEP_SUMMARY
          echo "| Detail | Value |" >> $GITHUB_STEP_SUMMARY
          echo "|--------|-------|" >> $GITHUB_STEP_SUMMARY
          echo "| **Triggered by** | @${{ github.actor }} |" >> $GITHUB_STEP_SUMMARY
          echo "| **Status** | ${{ job.status }} |" >> $GITHUB_STEP_SUMMARY
```

---

## 7. Secrets & Variables Schema

> **Prinzip:** Aufteilen in **Variables** (nicht-sensitiv) und **Secrets** (sensitiv).
> Variables sind in Logs sichtbar — Secrets werden maskiert.
> .env Dateien werden bei jedem Deploy aus GitHub Secrets/Variables generiert.

### 7.1 GitHub Variables (nicht-sensitiv, `vars.*`)

**Pfad:** GitHub → Repo → Settings → Secrets and variables → Actions → Variables

> Variables sind in Workflow-Logs sichtbar. Nur nicht-sensitive Werte!

| Variable | Beschreibung | Beispiel | Pflicht |
|----------|-------------|---------|---------|
| `SERVER_HOST` | Server IP oder Hostname | `203.0.113.1` | Ja |
| `SERVER_USERNAME` | SSH-Benutzer | `deploy` | Ja |
| `DEPLOY_PATH` | Zielverzeichnis auf Server | `/opt/mein-projekt` | Ja |
| `HETZNER_FIREWALL_NAME` | Hetzner Firewall Name | `firewall-1` | Ja (Hetzner) |
| `DOMAIN` | Öffentliche Domain | `mein-projekt.de` | Nein |
| `POSTGRES_USER` | DB-Benutzername | `app` | Nein (Full-Stack) |
| `POSTGRES_DB` | DB-Name | `app` | Nein (Full-Stack) |
| `MAIL_FROM_EMAIL` | Absender E-Mail | `noreply@cpcmomentum.com` | Nein |
| `MAIL_FROM_NAME` | Absender Name | `MeinProjekt` | Nein |

### 7.2 GitHub Secrets (sensitiv, `secrets.*`)

**Pfad:** GitHub → Repo → Settings → Secrets and variables → Actions → Secrets

> Secrets werden in Logs maskiert. Alle sensitiven Werte MÜSSEN als Secret gespeichert werden.

| Secret | Beschreibung | Pflicht |
|--------|-------------|---------|
| `SERVER_SSH_KEY` | SSH Private Key (komplett inkl. BEGIN/END) | Ja |
| `HETZNER_API_KEY` | Hetzner Cloud API Token | Ja (Hetzner) |
| `SECRET_KEY` | JWT/App Secret Key (min 32 Zeichen) | Nein (Full-Stack) |
| `POSTGRES_PASSWORD` | DB-Passwort | Nein (Full-Stack) |
| `MJ_APIKEY_PUBLIC` | Mailjet API Key | Nein |
| `MJ_APIKEY_PRIVATE` | Mailjet Secret Key | Nein |

#### Optionale Secrets (projekt-spezifisch)

| Secret | Wann nötig |
|--------|-----------|
| `STRIPE_SECRET_KEY` | Payment-Integration |
| `STRIPE_PUBLISHABLE_KEY` | Payment-Integration (Frontend) |
| `STRIPE_WEBHOOK_SECRET` | Stripe Webhooks |
| `OPENAI_API_KEY` | AI-Features (OpenAI) |
| `ANTHROPIC_API_KEY` | AI-Features (Anthropic) |
| `SENTRY_DSN` | Error Monitoring |

### 7.3 Security: env-Pattern für Secrets im Workflow

> **NIEMALS** `${{ secrets.X }}` direkt in `script:` verwenden — Command Injection Risiko!

```yaml
# RICHTIG: env-Block + envs-Parameter
- uses: appleboy/ssh-action@v1.2.5
  env:
    DEPLOY_PATH: ${{ vars.DEPLOY_PATH }}
    ENV_SECRET_KEY: ${{ secrets.SECRET_KEY }}
  with:
    envs: DEPLOY_PATH,ENV_SECRET_KEY
    script: |
      echo "Secret is: ${ENV_SECRET_KEY}"  # Sicher

# FALSCH: Direkter Zugriff in script
    script: |
      echo "Secret is: ${{ secrets.SECRET_KEY }}"  # Unsicher!
```

### 7.4 Lokale Entwicklung

Für lokale Entwicklung `.env.local` manuell erstellen (nicht committen!):

```bash
# backend/.env.local
POSTGRES_PASSWORD=localpass
SECRET_KEY=local-dev-secret
MJ_APIKEY_PUBLIC=xxx
MJ_APIKEY_PRIVATE=xxx
```

**Tipp:** `.env.local` in `.gitignore` aufnehmen.

---

## 8. Health Checks (PFLICHT)

### 8.1 Docker Compose Health Checks

Jeder Service MUSS einen Health Check haben:

| Service | Health Check |
|---------|-------------|
| **Frontend (Next.js)** | `fetch('http://localhost:3000')` |
| **Backend (FastAPI)** | `curl -f http://localhost:8000/health` |
| **PostgreSQL** | `pg_isready -U $USER -d $DB` |
| **Redis** | `redis-cli ping` |

### 8.2 Deploy Health Check

> **WICHTIG:** `curl http://localhost:PORT` funktioniert NICHT wenn Services `expose` statt `ports` verwenden (Standard bei zentralem Reverse Proxy). Stattdessen Docker Health Status prüfen.

```bash
# Docker Health Status prüfen (RICHTIG)
CONTAINER="projekt-backend"
RETRIES=10
for i in $(seq 1 $RETRIES); do
  STATUS=$(docker inspect --format='{{.State.Health.Status}}' "$CONTAINER" 2>/dev/null || echo "unknown")
  [ "$STATUS" = "healthy" ] && echo "Healthy!" && break
  [ "$i" -eq "$RETRIES" ] && echo "Failed!" && docker compose logs --tail=50 && exit 1
  echo "Attempt $i/$RETRIES - status: $STATUS"
  sleep 10
done
```

```bash
# FALSCH: curl auf localhost wenn expose statt ports
curl -f http://localhost:8000/health  # Funktioniert NICHT mit expose!
```

**Bei Failure:** Container-Logs werden ausgegeben, Workflow schlägt fehl.

---

## 9. Rollback-Strategie

### 9.1 Via GitHub Actions (empfohlen)

Den vorherigen Release Branch/Tag im Dropdown wählen und erneut deployen:

```
GitHub Actions → Deploy → Run workflow → Dropdown: release/v1.1.0
```

Das ist der einfachste und sicherste Rollback: gleicher Workflow, älterer Branch/Tag.

### 9.2 Container-Versionierung

Container-Namen enthalten die Version: `projekt-frontend-v1.2.0`. Bei Rollback wird automatisch die alte Version wiederhergestellt.

---

## 10. Multi-Domain Setup

### 10.1 Mehrere Domains → Ein Container

```nginx
# Zwei Domains zeigen auf denselben Container
server {
    listen 443 ssl http2;
    server_name domain-a.de www.domain-a.de domain-b.de www.domain-b.de;

    ssl_certificate /etc/letsencrypt/live/domain-a.de/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/domain-a.de/privkey.pem;

    location / {
        proxy_pass http://frontend:3000;
        # ... proxy headers
    }
}
```

### 10.2 DNS-Konfiguration

Alle Domains müssen per A-Record auf die Server-IP zeigen.

### 10.3 Voraussetzungen

- DNS A-Records gesetzt (kann bis zu 24h dauern)
- Port 80 und 443 offen (für ACME Challenge)
- Domain-Handling in der App implementiert (z.B. Next.js Middleware)
- SSL-Zertifikat für alle Domains: `certbot certonly -d domain-a.de -d domain-b.de`

---

## 11. Caddy als Alternative

> Für Projekte die einfacheres SSL-Management bevorzugen.

### 11.1 Warum Caddy statt Nginx

| Feature | Caddy | Nginx |
|---------|-------|-------|
| **SSL-Zertifikate** | Automatisch (Zero Config) | Manuell (certbot + cron) |
| **Konfiguration** | ~3 Zeilen pro Domain | ~30 Zeilen pro Domain |
| **Cert-Renewal** | Automatisch, unsichtbar | certbot renew + nginx reload |
| **HTTP/2 + HTTP/3** | Default | HTTP/2 möglich, HTTP/3 komplex |

### 11.2 Caddy docker-compose.yml

```yaml
# /opt/caddy/docker-compose.yml
services:
  caddy:
    image: caddy:2-alpine
    container_name: caddy-proxy
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - ./Caddyfile:/etc/caddy/Caddyfile:ro
      - caddy_data:/data
      - caddy_config:/config
    networks:
      - web
    restart: unless-stopped

volumes:
  caddy_data:
  caddy_config:

networks:
  web:
    external: true
```

### 11.3 Caddyfile Template

```caddyfile
# Caddyfile - Zentraler Reverse Proxy
# Neue Domains: Block hinzufügen, `docker exec caddy caddy reload --config /etc/caddy/Caddyfile`

# Projekt A - Frontend + Backend
projekt-a.de, www.projekt-a.de {
    encode gzip
    reverse_proxy projekt-a-frontend:3000
}

api.projekt-a.de {
    reverse_proxy projekt-a-backend:8000
}
```

### 11.4 Migration Nginx → Caddy

| Schritt | Befehl |
|---------|--------|
| 1. Caddyfile erstellen | Domains aus Nginx-Config übertragen |
| 2. Nginx stoppen | `cd /opt/nginx-proxy && docker compose down` |
| 3. Caddy starten | `cd /opt/caddy && docker compose up -d` |
| 4. Testen | Alle Domains im Browser prüfen |
| **Downtime** | ~30 Sekunden |

---

## 12. Production Troubleshooting

### 12.1 Docker Build Cache (Stale Code)

`docker compose up --build` kann alte Layer aus dem Cache verwenden. In CI **immer** `--no-cache`:

```bash
# RICHTIG: Separater Build + Start
docker compose build --no-cache
docker compose up -d

# FALSCH: Kann gecachte Layer nutzen
docker compose up -d --build
```

### 12.2 SCP .git Permission Denied

SCP kann an `.git/objects/pack` Files scheitern (owned by root von vorherigem Deploy):

```bash
# Fix: .git auf dem Server vor SCP löschen
rm -rf /opt/projekt/.git
```

### 12.3 Nginx 502 Bad Gateway nach Container-Recreation

**Ursache:** Nginx cached Container-IPs beim Start. Nach Container-Recreation haben Container neue IPs.

**Fix:** `resolver 127.0.0.11 valid=30s;` in der Nginx Domain-Config (siehe Section 3.3).

**Sofort-Fix:** `docker exec nginx-proxy nginx -s reload`

### 12.4 npm Lockfile Version Mismatch

Node-Versionen erzeugen inkompatible Lockfiles (z.B. Node 24/npm 11 vs Node 22/npm 10):

```bash
# Lockfile mit der CI-Node-Version generieren
docker run --rm -v "$(pwd)/frontend":/app -w /app node:22-alpine npm install
```

### 12.5 PostgreSQL Catalog Corruption

Partielle Migration-Runs (z.B. Container-Kill während ALTER TABLE) können `pg_class.relnatts` korrumpieren.

**Diagnose:**
```sql
SELECT c.relname, c.oid, c.relnatts,
       (SELECT count(*) FROM pg_attribute a WHERE a.attrelid = c.oid AND a.attnum > 0) as actual
FROM pg_class c
JOIN pg_namespace n ON n.oid = c.relnamespace
WHERE n.nspname = 'public' AND c.relkind = 'r'
AND c.relnatts != (SELECT count(*) FROM pg_attribute a WHERE a.attrelid = c.oid AND a.attnum > 0);
```

**Fix:**
```sql
UPDATE pg_class SET relnatts = (actual_count) WHERE oid = (oid);
```

**Prävention:** Migrationen idempotent schreiben (`CREATE TABLE IF NOT EXISTS`, `ADD COLUMN IF NOT EXISTS`).

### 12.6 Docker Compose: Cross-Project Orphan Removal

Mehrere Apps auf demselben Server, die aus gleichnamigen Verzeichnissen deployt werden (z.B. `deploy/`), erhalten denselben impliziten Projektnamen. `docker compose down --remove-orphans` löscht dann Container der **anderen** App.

**Fix:** `name:` Property in docker-compose.yml ist PFLICHT (siehe Section 4.3).

### 12.7 Alembic Migrationen: Idempotent schreiben

`op.create_table()` mit `sa.Enum(create_type=False)` funktioniert NICHT zuverlässig. Stattdessen **reines SQL**:

```python
conn = op.get_bind()

# Enum Type (idempotent)
conn.execute(sa.text("""
    DO $$ BEGIN
        CREATE TYPE my_enum AS ENUM ('a', 'b', 'c');
    EXCEPTION
        WHEN duplicate_object THEN null;
    END $$;
"""))

# Table (idempotent)
conn.execute(sa.text("""
    CREATE TABLE IF NOT EXISTS my_table (
        id SERIAL PRIMARY KEY,
        status my_enum NOT NULL
    )
"""))

# Columns (idempotent)
conn.execute(sa.text("ALTER TABLE existing_table ADD COLUMN IF NOT EXISTS new_col TEXT"))
```

---

## Referenzen

- `.claude/standards/ci-cd-pipelines.md` - CI Workflows
- `.claude/standards/reference/deployment-patterns.md` - Docker Patterns
- `.claude/standards/environment-management.md` - Secrets & Environment
- `.claude/techstack.md` - Tech Stack

---

**Letzte Aktualisierung**: 2026-02
**Owner**: DevOps Team
