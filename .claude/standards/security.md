# Security Standards

Diese Standards definieren Sicherheitsanforderungen für alle Microservices nach 2025-Best-Practices.

> **Hinweis**: Nutze [Context7](https://context7.com/) für aktuelle Security-Dokumentation von Libraries (NextAuth.js, FastAPI Security, Argon2, etc.). Security-Empfehlungen ändern sich häufig - immer aktuelle Docs validieren.

## 1. OWASP Top 10 (2021) Framework

### Übersicht & Mitigations

| Rang | Kategorie | Beschreibung | Mitigation | Verweis |
|------|-----------|--------------|------------|---------|
| A01 | Broken Access Control | Unzureichende Zugriffskontrolle | RBAC, JWT Validation, Least Privilege | Section 2 |
| A02 | Cryptographic Failures | Schwache Verschlüsselung, Klartext-Secrets | TLS 1.3, Argon2id, Secret Management | Section 3, environment-management.md |
| A03 | Injection | SQL, NoSQL, OS Command Injection | Parameterized Queries, ORM, Input Validation | api-design.md Section 10 |
| A04 | Insecure Design | Fehlende Security Controls im Design | Threat Modeling, Security Reviews | Section 14 |
| A05 | Security Misconfiguration | Default-Credentials, unnötige Features | Hardening, Security Headers | Section 4, Section 5 |
| A06 | Vulnerable Components | Bekannte Schwachstellen in Dependencies | npm audit, Trivy, Dependabot | Section 6+7, dependency-updates.md |
| A07 | Auth Failures | Schwache Authentifizierung | MFA, Session Management, Rate Limiting | Section 2, api-design.md |
| A08 | Software & Data Integrity | Unsichere CI/CD, fehlende Signierung | SBOM, Image Signing, Verified Builds | Section 5 |
| A09 | Security Logging Failures | Fehlende Audit-Logs | Structured Logging, Monitoring | error-handling-logging.md, monitoring-observability.md |
| A10 | SSRF | Server-Side Request Forgery | URL Validation, Allowlists, Network Policies | Section 8 |

### 2025-spezifische Risiken

```markdown
Zusätzliche Bedrohungen (nicht in OWASP Top 10 2021):
- API Security (BOLA, BFLA) - Siehe api-design.md
- AI/LLM Prompt Injection - Input Sanitization für AI-Inputs
- Supply Chain Attacks - SBOM, Sigstore (Section 5)
```

## 2. Authentication & Authorization

### JWT Best Practices

```typescript
// Empfohlene JWT-Konfiguration
const jwtConfig = {
  algorithm: 'RS256',           // Asymmetrisch bevorzugt (NICHT HS256 in Production)
  accessTokenExpiry: '15m',     // Kurze Lebensdauer
  refreshTokenExpiry: '7d',     // Längere Lebensdauer, rotieren
  issuer: 'https://auth.example.com',
  audience: 'https://api.example.com',
};
```

**Regeln:**
- **Algorithmus**: RS256 (asymmetrisch) für Production, HS256 nur für interne Services
- **Access Token**: Max. 15-30 Minuten Lebensdauer
- **Refresh Token**: 7-30 Tage, bei Nutzung rotieren
- **Speicherung**: httpOnly Cookies (NICHT localStorage)
- **Revocation**: Token Blacklist oder kurze Expiry

### NextAuth.js Konfiguration (Next.js)

```typescript
// auth.config.ts
import { AuthOptions } from 'next-auth';

export const authOptions: AuthOptions = {
  providers: [
    // OAuth Provider konfigurieren
  ],
  session: {
    strategy: 'jwt',
    maxAge: 30 * 60, // 30 Minuten
  },
  cookies: {
    sessionToken: {
      name: '__Secure-next-auth.session-token',
      options: {
        httpOnly: true,
        sameSite: 'lax',
        path: '/',
        secure: true, // IMMER true in Production
      },
    },
  },
  callbacks: {
    async jwt({ token, user }) {
      if (user) {
        token.role = user.role;
        token.permissions = user.permissions;
      }
      return token;
    },
    async session({ session, token }) {
      session.user.role = token.role;
      session.user.permissions = token.permissions;
      return session;
    },
  },
};
```

### FastAPI JWT (Python)

```python
# auth/jwt.py
from datetime import datetime, timedelta
from jose import jwt, JWTError
from pydantic import BaseModel

class TokenSettings(BaseModel):
    secret_key: str  # Aus Environment
    algorithm: str = "RS256"
    access_token_expire_minutes: int = 15
    refresh_token_expire_days: int = 7

def create_access_token(data: dict, settings: TokenSettings) -> str:
    expire = datetime.utcnow() + timedelta(minutes=settings.access_token_expire_minutes)
    to_encode = data.copy()
    to_encode.update({
        "exp": expire,
        "iat": datetime.utcnow(),
        "type": "access"
    })
    return jwt.encode(to_encode, settings.secret_key, algorithm=settings.algorithm)

def verify_token(token: str, settings: TokenSettings) -> dict:
    try:
        payload = jwt.decode(token, settings.secret_key, algorithms=[settings.algorithm])
        return payload
    except JWTError:
        raise HTTPException(status_code=401, detail="Invalid token")
```

### Session Management

| Attribut | Wert | Begründung |
|----------|------|------------|
| `httpOnly` | `true` | Verhindert JavaScript-Zugriff (XSS-Schutz) |
| `secure` | `true` | Nur über HTTPS |
| `sameSite` | `lax` oder `strict` | CSRF-Schutz |
| `path` | `/` | Scope begrenzen |
| `maxAge` | 1800 (30 min) | Session-Timeout |

### Multi-Factor Authentication (MFA)

```markdown
MFA-Anforderungen:
- TOTP (Time-based One-Time Password) - Standard
- WebAuthn/Passkeys - Bevorzugt für 2025
- SMS - NUR als Fallback (nicht empfohlen)

Empfohlene Libraries:
- Next.js: next-auth mit @simplewebauthn/server
- FastAPI: pyotp für TOTP, py_webauthn für WebAuthn
```

### Role-Based Access Control (RBAC)

```typescript
// permissions.ts
export const ROLES = {
  ADMIN: 'admin',
  USER: 'user',
  VIEWER: 'viewer',
} as const;

export const PERMISSIONS = {
  [ROLES.ADMIN]: ['read', 'write', 'delete', 'admin'],
  [ROLES.USER]: ['read', 'write'],
  [ROLES.VIEWER]: ['read'],
} as const;

// Middleware
export function requirePermission(permission: string) {
  return (req, res, next) => {
    const userPermissions = PERMISSIONS[req.user.role] || [];
    if (!userPermissions.includes(permission)) {
      return res.status(403).json({ error: 'Forbidden' });
    }
    next();
  };
}
```

```python
# FastAPI Dependency
from fastapi import Depends, HTTPException
from enum import Enum

class Role(str, Enum):
    ADMIN = "admin"
    USER = "user"
    VIEWER = "viewer"

ROLE_PERMISSIONS = {
    Role.ADMIN: {"read", "write", "delete", "admin"},
    Role.USER: {"read", "write"},
    Role.VIEWER: {"read"},
}

def require_permission(permission: str):
    def checker(current_user: User = Depends(get_current_user)):
        user_permissions = ROLE_PERMISSIONS.get(current_user.role, set())
        if permission not in user_permissions:
            raise HTTPException(status_code=403, detail="Forbidden")
        return current_user
    return checker

# Usage: @app.get("/admin", dependencies=[Depends(require_permission("admin"))])
```

## 3. Kryptographie-Standards

### TLS-Anforderungen

| Anforderung | Wert | Status |
|-------------|------|--------|
| Minimum TLS Version | 1.2 | Pflicht |
| Empfohlene TLS Version | 1.3 | Bevorzugt |
| TLS 1.0/1.1 | Deaktiviert | Pflicht |
| Cipher Suites | AEAD only (GCM, ChaCha20-Poly1305) | Pflicht |
| Perfect Forward Secrecy | ECDHE | Pflicht |

### Password Hashing

**Primär: Argon2id** (OWASP Empfehlung 2025)

```python
# Python mit argon2-cffi
from argon2 import PasswordHasher

ph = PasswordHasher(
    time_cost=3,        # Iterationen
    memory_cost=65536,  # 64 MB RAM
    parallelism=4,      # 4 Threads
    hash_len=32,        # 256 bit
    salt_len=16,        # 128 bit
)

# Hashing
hashed = ph.hash("user_password")

# Verification
try:
    ph.verify(hashed, "user_password")
    # Optional: Rehash wenn Parameter geändert
    if ph.check_needs_rehash(hashed):
        new_hash = ph.hash("user_password")
except argon2.exceptions.VerifyMismatchError:
    raise AuthenticationError("Invalid password")
```

**Fallback: bcrypt** (wenn Argon2 nicht verfügbar)

```typescript
// Node.js mit bcrypt
import bcrypt from 'bcrypt';

const SALT_ROUNDS = 12; // Minimum 10, empfohlen 12

// Hashing
const hash = await bcrypt.hash(password, SALT_ROUNDS);

// Verification
const isValid = await bcrypt.compare(password, hash);
```

### Kryptographie-Algorithmen

| Zweck | Algorithmus | Schlüssellänge |
|-------|-------------|----------------|
| Password Hashing | Argon2id | N/A (Parameter-basiert) |
| Symmetric Encryption | AES-256-GCM | 256 bit |
| Asymmetric Encryption | RSA | 4096 bit |
| Digital Signatures | Ed25519 oder RSA | 256 bit / 4096 bit |
| Hashing (Integrity) | SHA-256, SHA-3 | 256 bit |
| Key Derivation | HKDF, PBKDF2 | 256 bit output |

**Verboten:**
- MD5, SHA-1 (für Sicherheitszwecke)
- DES, 3DES, RC4
- RSA < 2048 bit
- PKCS#1 v1.5 Padding

### Schlüsselmanagement

```markdown
Anforderungen:
1. Secrets NIEMALS im Code oder Git
2. Rotation gemäß Schedule (siehe Section 12)
3. Separate Schlüssel pro Environment
4. HSM/KMS für Production (AWS KMS, GCP KMS, Azure Key Vault)
5. Audit-Logging für Schlüsselzugriffe
```

## 4. HTTP Security Headers

### Pflicht-Headers

```typescript
// next.config.js
const securityHeaders = [
  // HSTS - HTTPS erzwingen
  {
    key: 'Strict-Transport-Security',
    value: 'max-age=63072000; includeSubDomains; preload'
  },
  // Clickjacking-Schutz
  {
    key: 'X-Frame-Options',
    value: 'DENY'
  },
  // MIME-Sniffing verhindern
  {
    key: 'X-Content-Type-Options',
    value: 'nosniff'
  },
  // Referrer-Policy
  {
    key: 'Referrer-Policy',
    value: 'strict-origin-when-cross-origin'
  },
  // Permissions-Policy (Browser-Features einschränken)
  {
    key: 'Permissions-Policy',
    value: 'camera=(), microphone=(), geolocation=(), payment=()'
  },
  // XSS-Schutz (Legacy, aber schadet nicht)
  {
    key: 'X-XSS-Protection',
    value: '1; mode=block'
  },
];

module.exports = {
  async headers() {
    return [
      {
        source: '/:path*',
        headers: securityHeaders,
      },
    ];
  },
};
```

### Content-Security-Policy (CSP)

```typescript
// Basis-CSP (anpassen je nach Anforderungen)
const cspHeader = `
  default-src 'self';
  script-src 'self' 'unsafe-inline' 'unsafe-eval';
  style-src 'self' 'unsafe-inline';
  img-src 'self' data: https:;
  font-src 'self';
  connect-src 'self' https://api.example.com;
  frame-ancestors 'none';
  base-uri 'self';
  form-action 'self';
  upgrade-insecure-requests;
`.replace(/\n/g, '');

// Strikte CSP (Production-Ziel)
const strictCsp = `
  default-src 'none';
  script-src 'self' 'nonce-{RANDOM}';
  style-src 'self' 'nonce-{RANDOM}';
  img-src 'self' data:;
  font-src 'self';
  connect-src 'self';
  frame-ancestors 'none';
  base-uri 'none';
  form-action 'self';
  upgrade-insecure-requests;
`;
```

### FastAPI Security Headers Middleware

```python
# middleware/security_headers.py
from starlette.middleware.base import BaseHTTPMiddleware
from starlette.requests import Request
from starlette.responses import Response

class SecurityHeadersMiddleware(BaseHTTPMiddleware):
    async def dispatch(self, request: Request, call_next) -> Response:
        response = await call_next(request)

        # Pflicht-Headers
        response.headers["X-Content-Type-Options"] = "nosniff"
        response.headers["X-Frame-Options"] = "DENY"
        response.headers["Referrer-Policy"] = "strict-origin-when-cross-origin"
        response.headers["Permissions-Policy"] = "camera=(), microphone=(), geolocation=()"

        # HSTS (nur wenn HTTPS)
        if request.url.scheme == "https":
            response.headers["Strict-Transport-Security"] = "max-age=63072000; includeSubDomains"

        # CSP
        response.headers["Content-Security-Policy"] = "default-src 'self'; frame-ancestors 'none'"

        return response

# main.py
from fastapi import FastAPI
app = FastAPI()
app.add_middleware(SecurityHeadersMiddleware)
```

### Header-Referenz

| Header | Wert | Zweck |
|--------|------|-------|
| `Strict-Transport-Security` | `max-age=63072000; includeSubDomains; preload` | HTTPS erzwingen |
| `X-Frame-Options` | `DENY` | Clickjacking verhindern |
| `X-Content-Type-Options` | `nosniff` | MIME-Sniffing verhindern |
| `Referrer-Policy` | `strict-origin-when-cross-origin` | Referrer-Leaking begrenzen |
| `Permissions-Policy` | `camera=(), microphone=()` | Browser-APIs einschränken |
| `Content-Security-Policy` | (siehe oben) | XSS, Injection verhindern |

## 5. Container- & Supply-Chain-Sicherheit

> Für Docker-Grundlagen (non-root user, multi-stage builds) siehe `ci-cd-pipelines.md` Section 4.

### Image Scanning mit Trivy

```yaml
# .github/workflows/security.yml
name: Container Security

on:
  push:
    branches: [main, development]
  pull_request:

jobs:
  trivy-scan:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v6

      - name: Build Docker Image
        run: docker build -t app:${{ github.sha }} .

      - name: Run Trivy vulnerability scanner
        uses: aquasecurity/trivy-action@master
        with:
          image-ref: 'app:${{ github.sha }}'
          format: 'sarif'
          output: 'trivy-results.sarif'
          severity: 'CRITICAL,HIGH'
          exit-code: '1'  # Fail bei CRITICAL/HIGH

      - name: Upload Trivy scan results
        uses: github/codeql-action/upload-sarif@v3
        if: always()
        with:
          sarif_file: 'trivy-results.sarif'
```

### Filesystem Scanning

```yaml
# Scan des Repositories (nicht nur Container)
- name: Trivy filesystem scan
  uses: aquasecurity/trivy-action@master
  with:
    scan-type: 'fs'
    scan-ref: '.'
    severity: 'CRITICAL,HIGH'
    exit-code: '1'
```

### Base Image Hardening

```dockerfile
# Empfohlene Base Images (2025)

# Node.js - Alpine (klein, sicher)
FROM node:22-alpine AS base

# Node.js - Distroless (minimal, kein Shell)
FROM gcr.io/distroless/nodejs22-debian12 AS runtime

# Python - Alpine
FROM python:3.12-alpine AS base

# Python - Distroless
FROM gcr.io/distroless/python3-debian12 AS runtime
```

**Regeln:**
- IMMER spezifische Tags (NIEMALS `:latest`)
- Alpine oder Distroless bevorzugen
- Regelmäßig Base Images aktualisieren
- Multi-stage Builds für kleinere Images

### SBOM (Software Bill of Materials)

```yaml
# SBOM generieren mit Trivy
- name: Generate SBOM
  run: |
    trivy image --format cyclonedx --output sbom.json app:${{ github.sha }}

- name: Upload SBOM
  uses: actions/upload-artifact@v6
  with:
    name: sbom
    path: sbom.json
```

```bash
# Lokale SBOM-Generierung
trivy sbom --format cyclonedx --output sbom.json .
trivy sbom --format spdx-json --output sbom-spdx.json .
```

### Image Signing mit Cosign

```yaml
# Image signieren (nach erfolgreichem Build)
- name: Install Cosign
  uses: sigstore/cosign-installer@v3

- name: Sign container image
  env:
    COSIGN_EXPERIMENTAL: 1
  run: |
    cosign sign --yes ghcr.io/${{ github.repository }}:${{ github.sha }}
```

### Container Runtime Security

```yaml
# docker-compose.yml - Security-Konfiguration
services:
  app:
    image: app:1.0.0
    read_only: true                    # Readonly Filesystem
    security_opt:
      - no-new-privileges:true         # Privilege Escalation verhindern
    cap_drop:
      - ALL                            # Alle Capabilities entfernen
    user: "1001:1001"                  # Non-root User
    tmpfs:
      - /tmp                           # Temporärer Speicher wenn nötig
```

## 6. Lokale Security-Scans (ohne CI/CD)

> Für manuelles Deployment ohne CI/CD Pipeline. Diese Befehle VOR jedem Deployment ausführen.

### Installation (macOS)

```bash
# Trivy - Container & Filesystem Scanner
brew install trivy

# pip-audit für Python (npm audit ist in Node.js enthalten)
pip install pip-audit
```

### Quick-Reference

| Prüfung | Befehl | Wann |
|---------|--------|------|
| Secrets im Code | `trivy fs --scanners secret .` | Vor jedem Commit |
| npm Dependencies | `npm audit --audit-level=high` | Vor jedem Deployment |
| Python Dependencies | `pip-audit --strict` | Vor jedem Deployment |
| Code & Dependencies | `trivy fs --severity CRITICAL,HIGH .` | Vor jedem Deployment |
| Docker Image | `trivy image --severity CRITICAL,HIGH <image:tag>` | Nach jedem Build |

### Trivy Befehle

```bash
# Filesystem scannen (Code, Dependencies, Secrets, Misconfigs)
trivy fs .
trivy fs --severity CRITICAL,HIGH .
trivy fs --scanners vuln,secret,misconfig .

# Docker Image scannen
trivy image myapp:1.0.0
trivy image --severity CRITICAL,HIGH myapp:1.0.0

# Nur Dockerfile scannen
trivy config Dockerfile

# SBOM generieren
trivy image --format cyclonedx --output sbom.json myapp:1.0.0
```

### Trivy Secret Scan Befehle

```bash
# Repository auf Secrets scannen
trivy fs --scanners secret .
trivy fs --scanners secret --severity CRITICAL,HIGH .

# Nur bestimmtes Verzeichnis scannen
trivy fs --scanners secret ./src
```

### Audit Befehle

```bash
# npm (Node.js/Next.js)
npm audit                          # Alle Vulnerabilities
npm audit --audit-level=high       # Nur High+Critical
npm audit fix                      # Auto-Fix

# pip-audit (Python/FastAPI)
pip-audit -r requirements.txt
pip-audit --strict
```

## 7. npm audit Zero-Error-Policy (CI)

> Für Dependency-Update-Workflows siehe `dependency-updates.md`.

### CI-Enforcement

```yaml
# .github/workflows/security.yml
security-audit:
  runs-on: ubuntu-latest
  steps:
    - uses: actions/checkout@v6

    - name: Setup Node.js
      uses: actions/setup-node@v6
      with:
        node-version: '22'
        cache: 'npm'

    - name: Install dependencies
      run: npm ci

    - name: npm audit (MUST pass)
      run: |
        npm audit --audit-level=high
        if [ $? -ne 0 ]; then
          echo "::error::Security vulnerabilities found! Run 'npm audit' locally to see details."
          exit 1
        fi
```

### Resolution Workflow

```bash
# 1. Audit ausführen
npm audit

# 2. Automatische Fixes (wenn möglich)
npm audit fix

# 3. Bei Breaking Changes (VORSICHT)
npm audit fix --force

# 4. Manuelles Update spezifischer Packages
npm install package-name@latest

# 5. Überprüfen
npm audit
```

### Override für False Positives

```json
// package.json - NUR für verifizierte False Positives
{
  "overrides": {
    "vulnerable-package": "^2.0.0",
    "nested-vulnerable": {
      "sub-package": "^1.5.0"
    }
  }
}
```

**Regeln für Overrides:**
- Dokumentieren WARUM es ein False Positive ist
- Regelmäßig überprüfen ob Override noch nötig
- NIEMALS für echte Vulnerabilities

### pip-audit (Python)

```yaml
# Python Security Audit
python-audit:
  runs-on: ubuntu-latest
  steps:
    - uses: actions/checkout@v6

    - name: Setup Python
      uses: actions/setup-python@v6
      with:
        python-version: '3.12'

    - name: Install pip-audit
      run: pip install pip-audit

    - name: Run pip-audit
      run: |
        pip-audit -r requirements.txt --strict
```

```bash
# Lokale Nutzung
pip install pip-audit
pip-audit -r requirements.txt
pip-audit --fix  # Automatische Updates
```

### Audit-Matrix

| Severity | npm audit | pip-audit | Aktion | Zeitrahmen |
|----------|-----------|-----------|--------|------------|
| Critical | `--audit-level=critical` | `--strict` | Sofort | < 24h |
| High | `--audit-level=high` | `--strict` | Priorisiert | < 48h |
| Moderate | Default | Default | Nächster Sprint | < 2 Wochen |
| Low | Default | Default | Backlog | Nächstes Quartal |

## 8. Netzwerksicherheit

### mTLS für Service-to-Service

```yaml
# Kubernetes - Istio mTLS
apiVersion: security.istio.io/v1beta1
kind: PeerAuthentication
metadata:
  name: default
  namespace: production
spec:
  mtls:
    mode: STRICT  # mTLS erzwingen
```

### Kubernetes NetworkPolicy

```yaml
# network-policy.yaml
apiVersion: networking.k8s.io/v1
kind: NetworkPolicy
metadata:
  name: api-network-policy
  namespace: production
spec:
  podSelector:
    matchLabels:
      app: api
  policyTypes:
    - Ingress
    - Egress

  # Eingehend: Nur von Frontend und Ingress
  ingress:
    - from:
        - podSelector:
            matchLabels:
              app: frontend
        - namespaceSelector:
            matchLabels:
              name: ingress-nginx
      ports:
        - port: 8000
          protocol: TCP

  # Ausgehend: Nur zu Datenbank und externen APIs
  egress:
    - to:
        - podSelector:
            matchLabels:
              app: database
      ports:
        - port: 5432
          protocol: TCP
    - to:
        - namespaceSelector: {}
          podSelector:
            matchLabels:
              k8s-app: kube-dns
      ports:
        - port: 53
          protocol: UDP
```

### Default Deny Policy

```yaml
# Alles blockieren, dann explizit erlauben
apiVersion: networking.k8s.io/v1
kind: NetworkPolicy
metadata:
  name: default-deny-all
  namespace: production
spec:
  podSelector: {}
  policyTypes:
    - Ingress
    - Egress
```

### API Gateway Security

```markdown
Anforderungen für API Gateways:
- TLS Termination mit aktuellen Ciphers
- Rate Limiting (siehe api-design.md)
- Request Validation
- Bot/DDoS Protection
- Geographic Restrictions (wenn erforderlich)
- WAF Rules (OWASP Core Rule Set)
```

### SSRF Prevention

```python
# URL Validation für ausgehende Requests
import ipaddress
from urllib.parse import urlparse

BLOCKED_HOSTS = ['localhost', '127.0.0.1', '0.0.0.0', '169.254.169.254']
BLOCKED_NETWORKS = [
    ipaddress.ip_network('10.0.0.0/8'),
    ipaddress.ip_network('172.16.0.0/12'),
    ipaddress.ip_network('192.168.0.0/16'),
    ipaddress.ip_network('169.254.0.0/16'),  # AWS Metadata
]

def is_safe_url(url: str) -> bool:
    parsed = urlparse(url)

    # Nur HTTPS erlauben
    if parsed.scheme != 'https':
        return False

    # Blocked Hosts prüfen
    if parsed.hostname in BLOCKED_HOSTS:
        return False

    # Private IPs blockieren
    try:
        ip = ipaddress.ip_address(parsed.hostname)
        for network in BLOCKED_NETWORKS:
            if ip in network:
                return False
    except ValueError:
        pass  # Hostname, nicht IP

    return True
```

## 9. Security Testing (SAST/DAST)

> Für Bandit (Python) in CI siehe `ci-cd-pipelines.md`.

### SAST Tools

```yaml
# .github/workflows/sast.yml
name: SAST

on: [push, pull_request]

jobs:
  semgrep:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v6

      - name: Semgrep Scan
        uses: returntocorp/semgrep-action@v1
        with:
          config: >-
            p/security-audit
            p/owasp-top-ten
            p/typescript
            p/python
            p/secrets

  eslint-security:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v6
      - run: npm ci
      - run: npm install eslint-plugin-security eslint-plugin-no-secrets
      - run: npx eslint . --ext .ts,.tsx
```

### ESLint Security Config

```javascript
// .eslintrc.js
module.exports = {
  plugins: ['security', 'no-secrets'],
  extends: ['plugin:security/recommended'],
  rules: {
    'security/detect-object-injection': 'error',
    'security/detect-non-literal-regexp': 'error',
    'security/detect-unsafe-regex': 'error',
    'security/detect-buffer-noassert': 'error',
    'security/detect-eval-with-expression': 'error',
    'security/detect-no-csrf-before-method-override': 'error',
    'security/detect-possible-timing-attacks': 'warn',
    'no-secrets/no-secrets': 'error',
  },
};
```

### DAST mit OWASP ZAP

```yaml
# DAST gegen Staging-Environment
dast:
  runs-on: ubuntu-latest
  needs: deploy-staging
  steps:
    - name: OWASP ZAP Baseline Scan
      uses: zaproxy/action-baseline@v0.12.0
      with:
        target: 'https://staging.example.com'
        rules_file_name: 'zap-rules.tsv'
        fail_action: 'warn'  # 'fail' für strikte Durchsetzung

    - name: Upload ZAP Report
      uses: actions/upload-artifact@v6
      with:
        name: zap-report
        path: report_html.html
```

### ZAP Rules Konfiguration

```tsv
# zap-rules.tsv
10015	IGNORE	# Incomplete or No Cache-control
10017	IGNORE	# Cross-Domain JavaScript
10096	WARN	# Timestamp Disclosure
10055	FAIL	# CSP Header Not Set
10038	FAIL	# Content Security Policy Header Not Set
```

### Security Testing Matrix

| Test-Typ | Tool | Wann | Blocking |
|----------|------|------|----------|
| SAST | Semgrep | Push/PR | Ja (Critical) |
| SAST | ESLint Security | Push/PR | Ja |
| SAST | Bandit (Python) | Push/PR | Ja |
| Secrets | Trivy (secret scanner) | Push/PR | Ja |
| Dependency | npm audit | Push/PR | Ja (High+) |
| Container | Trivy | Build | Ja (Critical) |
| DAST | OWASP ZAP | Staging Deploy | Nein (Warn) |

### Trivy für Secret Detection

```yaml
# CI Secret Scan (kein License Key nötig)
secret-scan:
  runs-on: ubuntu-latest
  steps:
    - uses: actions/checkout@v6

    - name: Trivy Secret Scan
      uses: aquasecurity/trivy-action@master
      with:
        scan-type: 'fs'
        scanners: 'secret'
        severity: 'CRITICAL,HIGH'
```

```yaml
# trivy.yaml (Allowlist für False Positives)
# Platziere in Projekt-Root
secret:
  allow-rules:
    - description: "Example files"
      path: '.*\.example$'
```

## 10. Incident Response

### Severity Matrix

| Severity | Definition | Response Time | Beispiele |
|----------|------------|---------------|-----------|
| **S1 - Critical** | Aktiver Breach, Datenverlust, Service komplett down | < 15 min | Data Breach, Ransomware, Production Down |
| **S2 - High** | Vulnerability ausgenutzt, partielle Auswirkung | < 1 Stunde | Exploited CVE, Unauthorized Access |
| **S3 - Medium** | Potenzielle Vulnerability gefunden | < 4 Stunden | Disclosed CVE, Failed Audit |
| **S4 - Low** | Security Improvement | Nächster Sprint | Minor Hardening, Policy Update |

### Escalation Matrix

```markdown
S1 Critical:
1. On-Call Security → Sofort
2. Engineering Lead → < 5 min
3. Management → < 15 min
4. Legal/PR (bei Data Breach) → < 30 min

S2 High:
1. Security Team → < 30 min
2. Engineering Lead → < 1 Stunde

S3 Medium:
1. Security Team → Business Hours
2. Ticket erstellen → Priorisiert

S4 Low:
1. Backlog Item erstellen
2. Review im nächsten Sprint
```

### Incident Runbook Template

```markdown
# Incident: [Titel]

## Klassifikation
- **Severity**: S1/S2/S3/S4
- **Typ**: Data Breach / Unauthorized Access / Service Outage / Vulnerability
- **Entdeckt**: [Datum/Zeit]
- **Gemeldet von**: [Person/System]

## 1. Erkennung
- [ ] Wie wurde der Incident entdeckt?
- [ ] Welches Monitoring hat ausgelöst?
- [ ] Initial Impact Assessment

## 2. Containment (Eindämmung)
- [ ] Betroffene Systeme isolieren
- [ ] Kompromittierte Credentials rotieren
- [ ] Malicious IPs/Users blockieren
- [ ] Betroffene Services offline nehmen (wenn nötig)

## 3. Eradication (Beseitigung)
- [ ] Root Cause identifizieren
- [ ] Malicious Artifacts entfernen
- [ ] Vulnerability patchen
- [ ] Zusätzliche Backdoors suchen

## 4. Recovery (Wiederherstellung)
- [ ] Von sauberem Backup wiederherstellen
- [ ] System-Integrität verifizieren
- [ ] Services schrittweise wieder starten
- [ ] Monitoring verstärken

## 5. Post-Incident
- [ ] Timeline dokumentieren
- [ ] Root Cause Analysis
- [ ] Lessons Learned Meeting
- [ ] Action Items definieren
- [ ] Reporting (intern/extern wenn erforderlich)

## Kommunikation
- Interne Updates: [Kanal]
- Externe Kommunikation: [falls erforderlich]
- Data Breach Notification: [falls erforderlich, 72h GDPR]
```

### Forensics Requirements

```markdown
Log Retention für Forensics:
- Security Logs: 1 Jahr
- Access Logs: 90 Tage
- Application Logs: 30 Tage
- Audit Logs: 7 Jahre (Compliance-abhängig)

Bei Incident:
1. Logs SOFORT sichern (Read-only Copy)
2. Keine Cleanup-Jobs auf betroffenen Systemen
3. Chain of Custody dokumentieren
4. Forensic Images erstellen (bei schweren Incidents)
```

## 11. Datenschutz & Privacy

> Hinweis: Spezifische Compliance-Anforderungen (GDPR, PCI-DSS, HIPAA, SOC2)
> sind projektabhängig und müssen individuell definiert werden.

### Data Classification

| Level | Bezeichnung | Beispiele | Anforderungen |
|-------|-------------|-----------|---------------|
| **Public** | Öffentlich | Marketing, Docs | Keine Einschränkungen |
| **Internal** | Intern | Business Docs | Access Control |
| **Confidential** | Vertraulich | Kundendaten, PII | Encryption, Logging, Access Control |
| **Restricted** | Streng vertraulich | Secrets, Passwörter | HSM, Minimal Access, Audit |

### Privacy by Design Prinzipien

```markdown
1. Datenminimierung
   - Nur erforderliche Daten sammeln
   - Keine "nice-to-have" Datenfelder

2. Zweckbindung
   - Daten nur für definierten Zweck nutzen
   - Keine sekundäre Nutzung ohne Consent

3. Speicherbegrenzung
   - Retention Policies definieren
   - Automatische Löschung implementieren

4. Pseudonymisierung
   - Wo möglich, Daten pseudonymisieren
   - Mapping separat und gesichert speichern

5. Encryption by Default
   - Alle personenbezogenen Daten verschlüsseln
   - At-rest UND in-transit
```

### Data Retention Template

```yaml
# data-retention.yaml
retention_policies:
  user_accounts:
    active: "indefinite"
    after_deletion: "30 days"

  transaction_logs:
    retention: "7 years"
    reason: "Legal/Tax requirements"

  access_logs:
    retention: "90 days"
    reason: "Security monitoring"

  analytics_data:
    retention: "2 years"
    anonymize_after: "30 days"

  backups:
    retention: "90 days"
    encrypted: true
```

### Datenschutz-Checkliste

```markdown
## Neue Feature Checkliste

- [ ] Werden personenbezogene Daten verarbeitet?
- [ ] Ist die Datenverarbeitung dokumentiert?
- [ ] Gibt es eine Rechtsgrundlage?
- [ ] Sind Retention Policies definiert?
- [ ] Ist Encryption implementiert?
- [ ] Sind Lösch-Funktionen implementiert?
- [ ] Sind Export-Funktionen implementiert (Datenportabilität)?
- [ ] Wird Consent korrekt eingeholt (wenn erforderlich)?
- [ ] Sind Third Parties involviert? → DPA erforderlich
```

## 12. Vulnerability Disclosure Policy

### SECURITY.md Template

```markdown
# Security Policy

## Supported Versions

| Version | Supported          |
| ------- | ------------------ |
| 2.x.x   | :white_check_mark: |
| 1.x.x   | :x:                |

## Reporting a Vulnerability

We take security seriously. If you discover a security vulnerability:

### DO:
- Email security@example.com
- Use PGP encryption if possible (Key: [link])
- Include detailed description
- Include steps to reproduce
- Include potential impact assessment

### DON'T:
- Open a public GitHub issue
- Disclose publicly before fix is available
- Access data beyond what's needed to prove vulnerability

### What to Include:
- Description of the vulnerability
- Steps to reproduce
- Affected versions
- Potential impact
- Suggested fix (optional)

### Our Commitment:
- Acknowledge receipt within 48 hours
- Provide status update within 7 days
- Coordinate disclosure timing with you
- Credit you in release notes (unless you prefer anonymity)

### Safe Harbor:
We will not pursue legal action against researchers who:
- Act in good faith
- Avoid privacy violations and data destruction
- Do not exploit vulnerabilities beyond proof of concept
- Report findings responsibly

## Response Timeline

| Phase | Timeline |
|-------|----------|
| Acknowledgment | 48 hours |
| Initial Assessment | 7 days |
| Fix Development | Severity-dependent |
| Disclosure | Coordinated with reporter |
```

### CVE Process

```markdown
Bei bestätigten Vulnerabilities:
1. CVE ID beantragen (MITRE/GitHub Security Advisories)
2. Fix entwickeln und testen
3. Security Advisory vorbereiten
4. Coordinated Disclosure mit Reporter
5. Release + Advisory veröffentlichen
6. Betroffene Nutzer benachrichtigen
```

## 13. Secrets Rotation

> Für Secret Storage Patterns siehe `environment-management.md`.

### Rotation Schedule

| Secret Typ | Rotation | Methode | Notizen |
|------------|----------|---------|---------|
| API Keys (extern) | 90 Tage | Manuell + Monitoring | Vorher neue Keys generieren |
| API Keys (intern) | 180 Tage | Automatisch (Vault) | Zero-Downtime |
| Database Passwords | 30 Tage | Automatisch | Dual-Password während Rotation |
| JWT Signing Keys | 90 Tage | Key Rotation Pattern | Alte Keys für Verification behalten |
| SSL/TLS Certificates | 30 Tage vor Expiry | Automatisch (Let's Encrypt) | cert-manager für K8s |
| Service Account Tokens | 365 Tage | Automatisch | Workload Identity bevorzugen |
| Encryption Keys (DEK) | 365 Tage | Automatisch (KMS) | KEK seltener rotieren |
| Emergency/Break-Glass | Nach JEDER Nutzung | Manuell | Sofort nach Incident |

### Zero-Downtime Rotation Pattern

```python
# JWT Key Rotation - Beide Keys während Transition akzeptieren
import os
from typing import List

class KeyRotator:
    def __init__(self):
        self.current_key = os.getenv("JWT_SIGNING_KEY")
        self.previous_key = os.getenv("JWT_SIGNING_KEY_PREVIOUS")

    @property
    def signing_key(self) -> str:
        """Neue Tokens immer mit aktuellem Key signieren"""
        return self.current_key

    @property
    def verification_keys(self) -> List[str]:
        """Validierung mit beiden Keys erlauben"""
        keys = [self.current_key]
        if self.previous_key:
            keys.append(self.previous_key)
        return keys
```

### Database Password Rotation

```markdown
1. Neues Passwort in Vault/Secret Manager generieren
2. Neuen DB User mit neuem Passwort erstellen
3. Application auf neuen User umstellen (Rolling Update)
4. Alten User deaktivieren (nach Grace Period)
5. Alten User löschen (nach Verification)

Alternative: Dual-Password Feature (PostgreSQL 16+, MySQL 8.0.14+)
```

### Vault Auto-Rotation

```hcl
# Vault Database Secret Engine
resource "vault_database_secret_backend_role" "app" {
  backend     = vault_mount.postgres.path
  name        = "app-role"
  db_name     = vault_database_secret_backend_connection.postgres.name

  creation_statements = [
    "CREATE ROLE \"{{name}}\" WITH LOGIN PASSWORD '{{password}}' VALID UNTIL '{{expiration}}';",
    "GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO \"{{name}}\";"
  ]

  default_ttl = 3600   # 1 Stunde
  max_ttl     = 86400  # 24 Stunden
}
```

### Emergency Rotation

```markdown
## Notfall-Rotation (bei Kompromittierung)

1. SOFORT:
   - Kompromittierten Key/Secret identifizieren
   - Neuen Key generieren
   - In Production deployen (Fast-Track)

2. Innerhalb 1 Stunde:
   - Alten Key invalidieren
   - Alle Sessions mit altem Key terminieren
   - Audit: Wer hatte Zugriff?

3. Post-Incident:
   - Root Cause Analysis
   - Prozess-Verbesserungen
   - Dokumentation
```

## 14. Security Checklists

### Pre-Deployment Checklist

```markdown
## Vor jedem Production Deployment

### Authentication & Authorization
- [ ] Alle Endpoints erfordern Authentication (außer public)
- [ ] RBAC/Permissions korrekt konfiguriert
- [ ] Session Timeout konfiguriert
- [ ] MFA für Admin-Accounts aktiviert

### Data Protection
- [ ] Sensitive Daten at-rest verschlüsselt
- [ ] TLS 1.2+ für alle Verbindungen
- [ ] PII Logging deaktiviert (siehe error-handling-logging.md)
- [ ] Database Backups verschlüsselt

### Infrastructure
- [ ] Security Headers konfiguriert (Section 4)
- [ ] Rate Limiting aktiviert (siehe api-design.md)
- [ ] CORS restriktiv konfiguriert (siehe api-design.md)
- [ ] Firewall Rules / NetworkPolicies aktiv

### Code Security
- [ ] `npm audit` / `pip-audit` bestanden (0 High/Critical)
- [ ] SAST Scan bestanden (Semgrep, ESLint Security)
- [ ] Dependencies gepinnt (siehe dependency-updates.md)
- [ ] Keine Secrets im Code (Trivy Secret Scan)
- [ ] Container Scan bestanden (Trivy)

### Monitoring & Response
- [ ] Security Alerts konfiguriert (siehe monitoring-observability.md)
- [ ] Audit Logging aktiviert
- [ ] Incident Response Plan dokumentiert
- [ ] On-Call Rotation definiert
```

### New Project Security Setup

```markdown
## Neues Projekt - Security Foundation

### Repository Setup
- [ ] `.gitignore` mit `.env`, Secrets-Pattern
- [ ] Pre-commit Hooks (detect-private-key)
- [ ] Branch Protection (Reviews, Status Checks)
- [ ] Dependabot aktiviert

### CI/CD Pipeline
- [ ] npm audit / pip-audit Job
- [ ] SAST Job (Semgrep)
- [ ] Container Scanning (Trivy)
- [ ] Secret Scanning (Trivy)

### Application
- [ ] Security Headers Middleware
- [ ] Authentication Setup (NextAuth/FastAPI Security)
- [ ] Rate Limiting
- [ ] CORS Configuration
- [ ] Input Validation

### Infrastructure
- [ ] TLS/HTTPS konfiguriert
- [ ] Secrets in Vault/Secret Manager
- [ ] Network Policies (wenn K8s)
- [ ] Monitoring & Alerting

### Documentation
- [ ] SECURITY.md erstellt
- [ ] Incident Response Plan
- [ ] Runbook Template
```

### Periodic Security Review

```markdown
## Monatliche Security Review

### Dependency Check
- [ ] Dependabot PRs reviewed und gemerged
- [ ] npm audit clean
- [ ] Container Images aktualisiert
- [ ] Base Images auf aktueller Version

### Access Review
- [ ] Inaktive User deaktiviert
- [ ] Service Account Permissions geprüft
- [ ] API Key Nutzung reviewed
- [ ] Secrets Rotation durchgeführt (wenn fällig)

### Configuration Review
- [ ] Security Headers noch aktuell
- [ ] CORS Allowlist noch korrekt
- [ ] Rate Limits angemessen
- [ ] Network Policies noch passend

### Monitoring Review
- [ ] Security Alerts reviewed
- [ ] Failed Login Attempts analysiert
- [ ] Error Rates normal
- [ ] Keine ungewöhnlichen Traffic Patterns

### Documentation
- [ ] Runbooks noch aktuell
- [ ] Incident Response Plan noch gültig
- [ ] SECURITY.md aktuell
```

---

**Letzte Aktualisierung**: 2025-12
**Owner**: Security

---

## Referenzen zu anderen Standards

| Thema | Datei |
|-------|-------|
| Rate Limiting & CORS | `api-design.md` |
| npm audit in CI, Docker Basics | `ci-cd-pipelines.md` |
| Secret Storage (.env, Vault) | `environment-management.md` |
| PII Masking in Logs | `error-handling-logging.md` |
| Version Pinning, Dependabot | `dependency-updates.md` |
| Pre-commit Hooks | `code-style-linting.md` |
| Health Checks, Alerting | `monitoring-observability.md` |
