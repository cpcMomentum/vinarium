# Email Service Standards

Standards für transaktionale E-Mails in allen Projekten. Verwendet Mailjet als Email-Provider.

> **Anwendungsfälle:** Password Reset, Account Verification, Magic Link Login, Welcome Email, Notifications

---

## 1. Provider: Mailjet

### Warum Mailjet?

| Kriterium | Mailjet | SendGrid | AWS SES |
|-----------|---------|----------|---------|
| **Free Tier** | 200/Tag, 6.000/Monat | 100/Tag | 62.000/Monat (12 Monate) |
| **Pricing** | Ab 15€/Monat (15k Emails) | Ab $20/Monat | $0.10 pro 1.000 |
| **EU-Server** | ✅ (DSGVO-konform) | ❌ (US) | ✅ (eu-west-1) |
| **Setup** | Einfach | Einfach | Komplex |
| **API Quality** | Sehr gut | Sehr gut | Gut |

**Entscheidung:** Mailjet als Default wegen EU-Server und einfachem Setup.

---

## 2. Mailjet Account Setup

### 2.1 Account erstellen

1. Registriere dich auf [mailjet.com](https://www.mailjet.com)
2. Verifiziere deine E-Mail-Adresse
3. Wähle Plan (Free Tier reicht für Entwicklung)

### 2.2 API-Credentials holen

1. **Dashboard** → **Account Settings** → **API Keys**
2. Kopiere:
   - `API Key` (Public Key)
   - `Secret Key` (Private Key)

### 2.3 Sender-Domain hinzufügen

1. **Dashboard** → **Account Settings** → **Sender domains & addresses**
2. **Add domain** → Domain eingeben (z.B. `example.com`)
3. Mailjet zeigt dir die benötigten DNS-Records

---

## 3. DNS-Konfiguration (PFLICHT)

### 3.1 Ownership Token (Domain-Verifizierung)

```
Typ:      TXT
Hostname: [OwnerShipTokenRecordName].[domain]
Wert:     [OwnerShipToken]
TTL:      300
```

*Werte werden von Mailjet bereitgestellt.*

### 3.2 SPF-Record (Autorisierung)

```
Typ:      TXT
Hostname: @ (root domain)
Wert:     v=spf1 include:spf.mailjet.com ~all
TTL:      3600
```

**Falls bereits ein SPF-Record existiert**, `include:spf.mailjet.com` hinzufügen:
```
v=spf1 include:spf.mailjet.com include:_spf.google.com ~all
```

### 3.3 DKIM-Record (Signatur)

```
Typ:      TXT
Hostname: mailjet._domainkey.[domain]
Wert:     k=rsa; p=[LANGER_KEY_VON_MAILJET]
TTL:      3600
```

*Der DKIM-Key wird von Mailjet generiert (2048-bit seit April 2024).*

### 3.4 DMARC-Record (Policy)

```
Typ:      TXT
Hostname: _dmarc.[domain]
Wert:     v=DMARC1; p=none; rua=mailto:dmarc-reports@[domain]
TTL:      3600
```

**DMARC Policies:**
| Policy | Bedeutung | Empfehlung |
|--------|-----------|------------|
| `p=none` | Nur Monitoring | Start |
| `p=quarantine` | Spam-Ordner | Nach 2 Wochen |
| `p=reject` | Ablehnen | Nach 1 Monat |

### 3.5 DNS-Records verifizieren

1. **Mailjet Dashboard** → **Sender domains** → **Check DNS**
2. Warten bis SPF und DKIM auf **"OK"** stehen
3. DNS-Propagation kann bis zu 48h dauern

---

## 4. SMTP-Einstellungen (Alternative zu API)

Falls du SMTP statt API nutzen willst:

| Setting | Wert |
|---------|------|
| **Host** | `in-v3.mailjet.com` |
| **Port** | `587` (STARTTLS) oder `465` (SSL/TLS) |
| **Username** | API Key (Public) |
| **Password** | Secret Key (Private) |
| **Encryption** | STARTTLS oder SSL/TLS |

Alternative Ports: `25` (STARTTLS), `2525` (Fallback)

---

## 5. FastAPI Integration

### 5.1 Dependencies

```bash
pip install mailjet-rest
```

**pyproject.toml / requirements.txt:**
```
mailjet-rest==1.3.4
```

### 5.2 Environment Variables

```bash
# backend/.env
MJ_APIKEY_PUBLIC=your_api_key
MJ_APIKEY_PRIVATE=your_secret_key
MAIL_FROM_EMAIL=noreply@cpcmomentum.com
MAIL_FROM_NAME=Example App
BASE_URL=https://example.com
```

### 5.3 Email Service

```python
# backend/app/services/email.py
from mailjet_rest import Client
from pydantic import EmailStr
import os
import logging

logger = logging.getLogger(__name__)


class EmailService:
    """Service für transaktionale E-Mails via Mailjet."""

    def __init__(self):
        self.client = Client(
            auth=(
                os.environ['MJ_APIKEY_PUBLIC'],
                os.environ['MJ_APIKEY_PRIVATE']
            ),
            version='v3.1'
        )
        self.from_email = os.environ['MAIL_FROM_EMAIL']
        self.from_name = os.environ['MAIL_FROM_NAME']
        self.base_url = os.environ['BASE_URL']

    def send_email(
        self,
        to_email: str,
        to_name: str,
        subject: str,
        html_content: str,
        text_content: str | None = None
    ) -> bool:
        """Sendet eine E-Mail via Mailjet API."""

        data = {
            'Messages': [
                {
                    "From": {
                        "Email": self.from_email,
                        "Name": self.from_name
                    },
                    "To": [
                        {
                            "Email": to_email,
                            "Name": to_name
                        }
                    ],
                    "Subject": subject,
                    "HTMLPart": html_content,
                }
            ]
        }

        if text_content:
            data['Messages'][0]['TextPart'] = text_content

        try:
            result = self.client.send.create(data=data)

            if result.status_code == 200:
                logger.info(f"Email sent to {to_email}: {subject}")
                return True
            else:
                logger.error(f"Email failed: {result.status_code} - {result.json()}")
                return False

        except Exception as e:
            logger.error(f"Email error: {e}")
            return False

    # === TRANSAKTIONALE E-MAILS ===

    def send_password_reset(self, to_email: str, to_name: str, reset_token: str) -> bool:
        """Password Reset E-Mail."""

        reset_url = f"{self.base_url}/reset-password?token={reset_token}"

        subject = "Passwort zurücksetzen"
        html = f"""
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="utf-8">
            <style>
                body {{ font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; line-height: 1.6; color: #333; }}
                .container {{ max-width: 600px; margin: 0 auto; padding: 20px; }}
                .button {{ display: inline-block; padding: 12px 24px; background-color: #0070f3; color: white; text-decoration: none; border-radius: 6px; margin: 20px 0; }}
                .footer {{ margin-top: 40px; padding-top: 20px; border-top: 1px solid #eee; font-size: 12px; color: #666; }}
            </style>
        </head>
        <body>
            <div class="container">
                <h1>Passwort zurücksetzen</h1>
                <p>Hallo {to_name},</p>
                <p>Du hast angefordert, dein Passwort zurückzusetzen. Klicke auf den Button unten, um ein neues Passwort zu erstellen:</p>
                <a href="{reset_url}" class="button">Passwort zurücksetzen</a>
                <p>Dieser Link ist <strong>1 Stunde</strong> gültig.</p>
                <p>Falls du diese Anfrage nicht gestellt hast, kannst du diese E-Mail ignorieren.</p>
                <div class="footer">
                    <p>Falls der Button nicht funktioniert, kopiere diesen Link in deinen Browser:</p>
                    <p>{reset_url}</p>
                </div>
            </div>
        </body>
        </html>
        """

        return self.send_email(to_email, to_name, subject, html)

    def send_verification_email(self, to_email: str, to_name: str, verification_token: str) -> bool:
        """Account Verification E-Mail."""

        verify_url = f"{self.base_url}/verify-email?token={verification_token}"

        subject = "E-Mail-Adresse bestätigen"
        html = f"""
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="utf-8">
            <style>
                body {{ font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; line-height: 1.6; color: #333; }}
                .container {{ max-width: 600px; margin: 0 auto; padding: 20px; }}
                .button {{ display: inline-block; padding: 12px 24px; background-color: #0070f3; color: white; text-decoration: none; border-radius: 6px; margin: 20px 0; }}
                .footer {{ margin-top: 40px; padding-top: 20px; border-top: 1px solid #eee; font-size: 12px; color: #666; }}
            </style>
        </head>
        <body>
            <div class="container">
                <h1>Willkommen!</h1>
                <p>Hallo {to_name},</p>
                <p>Vielen Dank für deine Registrierung! Bitte bestätige deine E-Mail-Adresse, um deinen Account zu aktivieren:</p>
                <a href="{verify_url}" class="button">E-Mail bestätigen</a>
                <p>Dieser Link ist <strong>24 Stunden</strong> gültig.</p>
                <div class="footer">
                    <p>Falls der Button nicht funktioniert, kopiere diesen Link in deinen Browser:</p>
                    <p>{verify_url}</p>
                </div>
            </div>
        </body>
        </html>
        """

        return self.send_email(to_email, to_name, subject, html)

    def send_magic_link(self, to_email: str, to_name: str, magic_token: str) -> bool:
        """Magic Link Login E-Mail."""

        login_url = f"{self.base_url}/auth/magic-link?token={magic_token}"

        subject = "Dein Login-Link"
        html = f"""
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="utf-8">
            <style>
                body {{ font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; line-height: 1.6; color: #333; }}
                .container {{ max-width: 600px; margin: 0 auto; padding: 20px; }}
                .button {{ display: inline-block; padding: 12px 24px; background-color: #0070f3; color: white; text-decoration: none; border-radius: 6px; margin: 20px 0; }}
                .footer {{ margin-top: 40px; padding-top: 20px; border-top: 1px solid #eee; font-size: 12px; color: #666; }}
                .warning {{ background-color: #fff3cd; border: 1px solid #ffc107; padding: 12px; border-radius: 6px; margin: 20px 0; }}
            </style>
        </head>
        <body>
            <div class="container">
                <h1>Login-Link</h1>
                <p>Hallo {to_name},</p>
                <p>Klicke auf den Button unten, um dich anzumelden:</p>
                <a href="{login_url}" class="button">Jetzt anmelden</a>
                <div class="warning">
                    <strong>Sicherheitshinweis:</strong> Dieser Link ist <strong>15 Minuten</strong> gültig und kann nur einmal verwendet werden.
                </div>
                <p>Falls du diesen Link nicht angefordert hast, kannst du diese E-Mail ignorieren.</p>
                <div class="footer">
                    <p>Falls der Button nicht funktioniert, kopiere diesen Link in deinen Browser:</p>
                    <p>{login_url}</p>
                </div>
            </div>
        </body>
        </html>
        """

        return self.send_email(to_email, to_name, subject, html)

    def send_welcome_email(self, to_email: str, to_name: str) -> bool:
        """Welcome E-Mail nach erfolgreicher Registrierung."""

        subject = "Willkommen bei uns!"
        html = f"""
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="utf-8">
            <style>
                body {{ font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; line-height: 1.6; color: #333; }}
                .container {{ max-width: 600px; margin: 0 auto; padding: 20px; }}
                .button {{ display: inline-block; padding: 12px 24px; background-color: #0070f3; color: white; text-decoration: none; border-radius: 6px; margin: 20px 0; }}
                .features {{ background-color: #f5f5f5; padding: 20px; border-radius: 6px; margin: 20px 0; }}
                .footer {{ margin-top: 40px; padding-top: 20px; border-top: 1px solid #eee; font-size: 12px; color: #666; }}
            </style>
        </head>
        <body>
            <div class="container">
                <h1>Willkommen, {to_name}! 🎉</h1>
                <p>Dein Account wurde erfolgreich erstellt. Wir freuen uns, dich an Bord zu haben!</p>

                <div class="features">
                    <h3>Nächste Schritte:</h3>
                    <ul>
                        <li>Vervollständige dein Profil</li>
                        <li>Erkunde die Funktionen</li>
                        <li>Bei Fragen: Schreib uns!</li>
                    </ul>
                </div>

                <a href="{self.base_url}/dashboard" class="button">Zum Dashboard</a>

                <div class="footer">
                    <p>Du erhältst diese E-Mail, weil du dich bei uns registriert hast.</p>
                </div>
            </div>
        </body>
        </html>
        """

        return self.send_email(to_email, to_name, subject, html)


# Singleton-Instanz
email_service = EmailService()
```

### 5.4 Usage in FastAPI Endpoints

```python
# backend/app/api/auth.py
from fastapi import APIRouter, HTTPException
from app.services.email import email_service
from app.services.token import create_reset_token, create_verification_token

router = APIRouter()


@router.post("/forgot-password")
async def forgot_password(email: str):
    """Sendet Password Reset E-Mail."""

    user = await get_user_by_email(email)
    if not user:
        # Aus Sicherheitsgründen keine Info ob User existiert
        return {"message": "Falls ein Account existiert, wurde eine E-Mail gesendet."}

    reset_token = create_reset_token(user.id)

    email_service.send_password_reset(
        to_email=user.email,
        to_name=user.name,
        reset_token=reset_token
    )

    return {"message": "Falls ein Account existiert, wurde eine E-Mail gesendet."}


@router.post("/register")
async def register(email: str, name: str, password: str):
    """Registriert neuen User und sendet Verification E-Mail."""

    user = await create_user(email, name, password)
    verification_token = create_verification_token(user.id)

    email_service.send_verification_email(
        to_email=user.email,
        to_name=user.name,
        verification_token=verification_token
    )

    return {"message": "Registrierung erfolgreich. Bitte bestätige deine E-Mail."}


@router.post("/magic-link")
async def request_magic_link(email: str):
    """Sendet Magic Link Login E-Mail."""

    user = await get_user_by_email(email)
    if not user:
        return {"message": "Falls ein Account existiert, wurde eine E-Mail gesendet."}

    magic_token = create_magic_link_token(user.id)

    email_service.send_magic_link(
        to_email=user.email,
        to_name=user.name,
        magic_token=magic_token
    )

    return {"message": "Falls ein Account existiert, wurde eine E-Mail gesendet."}
```

---

## 6. Next.js Integration (Alternative)

Falls E-Mails vom Frontend gesendet werden sollen (z.B. bei Next.js Server Actions):

```typescript
// frontend/lib/email.ts
import Mailjet from 'node-mailjet';

const mailjet = Mailjet.apiConnect(
  process.env.MJ_APIKEY_PUBLIC!,
  process.env.MJ_APIKEY_PRIVATE!
);

interface SendEmailParams {
  toEmail: string;
  toName: string;
  subject: string;
  htmlContent: string;
}

export async function sendEmail({ toEmail, toName, subject, htmlContent }: SendEmailParams) {
  const result = await mailjet.post('send', { version: 'v3.1' }).request({
    Messages: [
      {
        From: {
          Email: process.env.MAIL_FROM_EMAIL,
          Name: process.env.MAIL_FROM_NAME,
        },
        To: [
          {
            Email: toEmail,
            Name: toName,
          },
        ],
        Subject: subject,
        HTMLPart: htmlContent,
      },
    ],
  });

  return result.response.status === 200;
}
```

**Dependencies:**
```bash
npm install node-mailjet
```

---

## 7. Environment Variables Schema

### 7.1 GitHub Secrets (Production)

> **Prinzip:** Alle Variablen werden aus GitHub Secrets generiert. Keine `.env.example` mehr!

Diese Secrets müssen im GitHub Repository eingerichtet werden:

**Pfad:** GitHub → Repo → Settings → Secrets and variables → Actions

| Secret | Beschreibung | Beispiel |
|--------|-------------|---------|
| `MJ_APIKEY_PUBLIC` | Mailjet API Key | `abc123...` |
| `MJ_APIKEY_PRIVATE` | Mailjet Secret Key | `xyz789...` |
| `MAIL_FROM_EMAIL` | Absender E-Mail | `noreply@cpcmomentum.com` |
| `MAIL_FROM_NAME` | Absender Name | `MeinProjekt` |
| `BASE_URL` | App-URL für Links | `https://meinprojekt.de` |

Die `.env` Datei wird bei jedem Deploy automatisch aus den Secrets generiert (siehe `deployment.md`).

### 7.2 Lokale Entwicklung

Für lokale Entwicklung `.env.local` manuell erstellen (nicht committen!):

```bash
# backend/.env.local
MJ_APIKEY_PUBLIC=dein_api_key
MJ_APIKEY_PRIVATE=dein_secret_key
MAIL_FROM_EMAIL=noreply@cpcmomentum.com
MAIL_FROM_NAME=LocalDev
BASE_URL=http://localhost:3000
```

**Wichtig:** `.env.local` in `.gitignore` aufnehmen!

---

## 8. Token-Gültigkeiten

| E-Mail-Typ | Token-Gültigkeit | Einmal verwendbar |
|------------|------------------|-------------------|
| Password Reset | 1 Stunde | Ja |
| Account Verification | 24 Stunden | Ja |
| Magic Link | 15 Minuten | Ja |

---

## 9. Security Best Practices

### 9.1 Keine Info-Leaks

```python
# FALSCH - verrät ob E-Mail existiert
if not user:
    raise HTTPException(400, "E-Mail nicht gefunden")

# RICHTIG - gleiche Antwort für alle
return {"message": "Falls ein Account existiert, wurde eine E-Mail gesendet."}
```

### 9.2 Rate Limiting

```python
from slowapi import Limiter

limiter = Limiter(key_func=get_remote_address)

@router.post("/forgot-password")
@limiter.limit("3/minute")  # Max 3 Anfragen pro Minute
async def forgot_password(email: str):
    ...
```

### 9.3 Token-Invalidierung

- Token nach Verwendung invalidieren
- Token bei Passwort-Änderung invalidieren
- Alte Tokens regelmäßig cleanen

---

## 10. Lokales Testen

### 10.1 Setup

Dieselben Mailjet-Keys wie Production verwenden - Free-Tier hat 200 E-Mails/Tag, das reicht für Entwicklung.

```bash
# backend/.env.local (nicht committen!)
MJ_APIKEY_PUBLIC=euer_echter_api_key
MJ_APIKEY_PRIVATE=euer_echter_secret_key
MAIL_FROM_EMAIL=noreply@cpcmomentum.com
MAIL_FROM_NAME=LocalDev
BASE_URL=http://localhost:3000
```

### 10.2 Best Practices

| Regel | Warum |
|-------|-------|
| **Nur an eigene Adressen senden** | Keine Test-Mails an Kunden/Externe |
| **"LocalDev" im Absender-Namen** | Sofort erkennbar dass es Test ist |
| **Echte E-Mails statt Mocking** | Testet das komplette System inkl. Zustellung |

**Empfohlene Test-Adressen:**
- `vorname@cpcmomentum.com` (eigene Team-Adressen)
- Persönliche E-Mail-Adressen der Entwickler

### 10.3 Sandbox Mode (Optional)

Falls ihr keine echten E-Mails senden wollt (z.B. in automatisierten Tests):

```python
# E-Mail wird validiert aber NICHT gesendet
data = {
    'Messages': [...],
    'SandboxMode': True
}
```

**Wann Sandbox nutzen:**
- CI/CD Pipeline Tests
- Unit Tests
- Load Tests

**Wann echte E-Mails nutzen:**
- Manuelle Feature-Tests
- E2E Tests
- Layout-Prüfung im echten Mail-Client

### 10.4 Test-Checkliste

Vor dem Merge prüfen:

- [ ] E-Mail kommt an (nicht im Spam)
- [ ] Links funktionieren (Token, BASE_URL korrekt)
- [ ] Layout sieht gut aus (Desktop + Mobile)
- [ ] Absender korrekt (`noreply@cpcmomentum.com`)
- [ ] Unsubscribe-Link vorhanden (bei Marketing-Mails)

### 10.5 Mailjet Test-Adressen

Mailjet akzeptiert diese Adressen ohne echten Versand:
- `test@mailjet.com` - Wird akzeptiert, nicht gesendet
- Nützlich für automatisierte Tests

---

## 11. Monitoring & Debugging

### 11.1 Mailjet Dashboard

- **Statistics** → Delivery-Rate, Opens, Clicks
- **Messages** → Einzelne E-Mails tracken
- **Events** → Bounces, Complaints

### 11.2 Logging

```python
logger.info(f"Email sent: {to_email}, Subject: {subject}, Status: {result.status_code}")
logger.error(f"Email failed: {to_email}, Error: {result.json()}")
```

---

## Referenzen

- [Mailjet API Documentation](https://dev.mailjet.com/)
- [Mailjet Python SDK](https://github.com/mailjet/mailjet-apiv3-python)
- [SPF/DKIM Authentication Guide](https://documentation.mailjet.com/hc/en-us/articles/360042412734)

---

**Letzte Aktualisierung:** 2026-02
**Owner:** Backend Team
