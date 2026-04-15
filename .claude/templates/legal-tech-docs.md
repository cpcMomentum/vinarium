# Technische Dokumentation für Rechtliche Dokumente

> **Generiert:** [DATUM]
> **Projekt:** [PROJEKT-NAME]
> **Version:** [VERSION]
> **Erstellt durch:** Claude Code `/ai-first-dev:generate-legal-docs`

---

## WICHTIGER HINWEIS

Diese Dokumentation ist eine **technische Bestandsaufnahme** und ersetzt KEINE rechtliche Beratung.
Sie dient als Input für PrimeLegalAI zur Erstellung von:
- Allgemeine Geschäftsbedingungen (AGB)
- Datenschutzerklärung (DSE)
- Impressum
- Auftragsverarbeitungsvertrag (AVV)
- Cookie-Banner / Cookie-Policy

---

## 1. Unternehmensdaten

| Feld | Wert |
|------|------|
| Firmenname | [FIRMENNAME] |
| Rechtsform | [Einzelunternehmer / GbR / UG / GmbH / GmbH & Co. KG / AG] |
| Vertretungsberechtigte | [NAME(N)] |
| Anschrift | [STRASSE, PLZ ORT, LAND] |
| E-Mail | [EMAIL] |
| Telefon | [TELEFON] |
| Registergericht | [GERICHT] (falls vorhanden) |
| Registernummer | [HRB/HRA NUMMER] (falls vorhanden) |
| USt-IdNr. | [USTID] (falls vorhanden) |
| Aufsichtsbehörde | [FALLS REGULIERT] |
| Berufsbezeichnung | [FALLS ZUTREFFEND] |
| Zuständige Kammer | [FALLS ZUTREFFEND] |

---

## 2. Service-Beschreibung

### 2.1 Allgemein

| Feld | Wert |
|------|------|
| Service-Name | [NAME] |
| Service-Art | [SaaS / Mobile App / E-Commerce / Plattform / API / Portal] |
| Domain(s) | [DOMAIN.TLD] |
| Zielgruppe | [B2C / B2B / B2B2C] |
| Mindestalter | [16 / 18 / Kein Limit] |
| Kostenpflichtig | [Nein / Einmalig / Abo / Freemium] |
| Widerrufsrecht | [Ja (14 Tage) / Nein (digitale Inhalte) / Nicht anwendbar (B2B)] |

### 2.2 Kernfunktionen

1. [Funktion 1 - Kurzbeschreibung]
2. [Funktion 2 - Kurzbeschreibung]
3. [Funktion 3 - Kurzbeschreibung]

### 2.3 Technischer Stack

| Komponente | Technologie | Hosting-Provider | Standort |
|------------|-------------|------------------|----------|
| Frontend | [z.B. Next.js 15] | [z.B. Vercel] | [z.B. EU - Frankfurt] |
| Backend | [z.B. FastAPI] | [z.B. Railway] | [z.B. EU - Amsterdam] |
| Datenbank | [z.B. PostgreSQL] | [z.B. Supabase] | [z.B. EU - Frankfurt] |
| File Storage | [z.B. S3] | [z.B. AWS] | [z.B. EU - Ireland] |
| CDN | [z.B. Cloudflare] | [z.B. Cloudflare] | [Global] |
| Cache | [z.B. Redis] | [z.B. Upstash] | [z.B. EU - Frankfurt] |

---

## 3. Datenverarbeitung

### 3.1 Erhobene Daten - Übersicht

| Kategorie | Beispieldaten | Rechtsgrundlage | Aufbewahrung | Zweck |
|-----------|---------------|-----------------|--------------|-------|
| Account-Daten | E-Mail, Name, Passwort (Hash) | Art. 6(1)(b) Vertragserfüllung | Bis Accountlöschung | Nutzerkonto |
| Profil-Daten | Profilbild, Bio, Telefon | Art. 6(1)(b) Vertragserfüllung | Bis Accountlöschung | Personalisierung |
| Nutzungsdaten | Seitenaufrufe, Klicks | Art. 6(1)(f) Berecht. Interesse | 90 Tage | Produktverbesserung |
| Zahlungsdaten | Über Zahlungsdienstleister | Art. 6(1)(b) Vertragserfüllung | Beim Dienstleister | Abrechnung |
| Technische Daten | IP, User-Agent, Device | Art. 6(1)(f) Berecht. Interesse | 30 Tage | Sicherheit |
| Kommunikation | Support-Anfragen | Art. 6(1)(b) Vertragserfüllung | 3 Jahre | Kundenservice |

### 3.2 Datenmodelle im Detail

#### Model: User

| Feld | Datentyp | PII | Pflicht | Zweck | Fundstelle |
|------|----------|-----|---------|-------|------------|
| id | UUID | Nein | Ja | Identifikation | `[Datei:Zeile]` |
| email | String | Ja | Ja | Login, Kommunikation | `[Datei:Zeile]` |
| passwordHash | String | Ja | Ja | Authentifizierung | `[Datei:Zeile]` |
| name | String | Ja | Nein | Personalisierung | `[Datei:Zeile]` |
| phone | String | Ja | Nein | Kontakt | `[Datei:Zeile]` |
| avatar | URL | Nein | Nein | Profilbild | `[Datei:Zeile]` |
| createdAt | DateTime | Nein | Ja | Audit | `[Datei:Zeile]` |
| updatedAt | DateTime | Nein | Ja | Audit | `[Datei:Zeile]` |

[Weitere Models analog...]

### 3.3 Aufbewahrungsfristen

| Datenkategorie | Aufbewahrungsfrist | Rechtsgrundlage | Löschmechanismus |
|----------------|-------------------|-----------------|------------------|
| Account-Daten | Bis Kündigung + 30 Tage | Vertragserfüllung | Auf Anfrage / Self-Service |
| Rechnungsdaten | 10 Jahre | § 147 AO, § 257 HGB | Automatisch nach Ablauf |
| Verträge | 10 Jahre nach Vertragsende | § 147 AO | Automatisch nach Ablauf |
| Server-Logs | 30 Tage | Berechtigtes Interesse | Log-Rotation |
| Analytics | 26 Monate | Einwilligung | GA-Einstellung |
| Support-Tickets | 3 Jahre | Berechtigtes Interesse | Manuell |
| Marketing-Einwilligungen | Bis Widerruf + 3 Jahre Nachweis | Nachweispflicht | Manuell |

---

## 4. Cookies & Tracking

### 4.1 Cookie-Übersicht nach Kategorie

#### Essenzielle Cookies (Technisch notwendig - Keine Einwilligung erforderlich)

| Name | Domain | Lebensdauer | HttpOnly | Secure | SameSite | Zweck |
|------|--------|-------------|----------|--------|----------|-------|
| `session_id` | [domain.tld] | Session | Ja | Ja | Strict | Session-Management |
| `csrf_token` | [domain.tld] | Session | Ja | Ja | Strict | CSRF-Schutz |
| `auth_token` | [domain.tld] | 7 Tage | Ja | Ja | Strict | Authentifizierung |

#### Funktionale Cookies (Einwilligung empfohlen)

| Name | Domain | Lebensdauer | HttpOnly | Secure | SameSite | Zweck |
|------|--------|-------------|----------|--------|----------|-------|
| `theme` | [domain.tld] | 1 Jahr | Nein | Ja | Lax | UI-Präferenz |
| `language` | [domain.tld] | 1 Jahr | Nein | Ja | Lax | Spracheinstellung |
| `cookie_consent` | [domain.tld] | 1 Jahr | Nein | Ja | Lax | Consent-Speicherung |

#### Analytics Cookies (Einwilligung ERFORDERLICH)

| Name | Domain | Lebensdauer | Anbieter | Zweck | Opt-Out |
|------|--------|-------------|----------|-------|---------|
| `_ga` | .google.com | 2 Jahre | Google Analytics | Nutzer-Unterscheidung | [Link] |
| `_gid` | .google.com | 24 Stunden | Google Analytics | Session-ID | [Link] |
| `_gat` | .google.com | 1 Minute | Google Analytics | Rate Limiting | [Link] |

#### Marketing Cookies (Einwilligung ERFORDERLICH)

| Name | Domain | Lebensdauer | Anbieter | Zweck | Opt-Out |
|------|--------|-------------|----------|-------|---------|
| _Keine vorhanden_ | - | - | - | - | - |

### 4.2 Tracking-Services

| Service | Tracking-ID (Env) | Gesammelte Daten | IP-Anonymisierung | Datenstandort |
|---------|-------------------|------------------|-------------------|---------------|
| Google Analytics 4 | `GA_TRACKING_ID` | Seitenaufrufe, Events, Gerät, Standort (Land) | Ja | USA |
| [Weitere] | [ENV_VAR] | [Daten] | [Ja/Nein] | [Standort] |

### 4.3 Cookie-Consent

| Feld | Wert |
|------|------|
| Consent-Management-System | [Eigenentwicklung / CookieYes / Usercentrics / Keine] |
| Consent vor Tracking | [Ja / Nein] |
| Granulare Auswahl möglich | [Ja / Nein] |
| Opt-Out jederzeit möglich | [Ja / Nein] |
| Consent-Nachweis gespeichert | [Ja (wo) / Nein] |

---

## 5. Third-Party Services

### 5.1 Datenfluss-Diagramm

```
                        ┌─────────────────────────────────────┐
                        │        EIGENE INFRASTRUKTUR         │
                        │           (EU - Frankfurt)           │
                        └─────────────────┬───────────────────┘
                                          │
            ┌─────────────────────────────┼─────────────────────────────┐
            │                             │                             │
            ▼                             ▼                             ▼
┌───────────────────┐       ┌───────────────────┐       ┌───────────────────┐
│     STRIPE        │       │    SENDGRID       │       │  GOOGLE ANALYTICS │
│   (USA - DPF)     │       │   (USA - SCC)     │       │    (USA - DPF)    │
│                   │       │                   │       │                   │
│ Name, Email,      │       │ Email-Adresse     │       │ Anonyme Nutzungs- │
│ Zahlungsdaten     │       │                   │       │ daten             │
└───────────────────┘       └───────────────────┘       └───────────────────┘
```

### 5.2 Service-Details

#### Zahlungsdienstleister

| Feld | Wert |
|------|------|
| **Anbieter** | Stripe Inc. |
| **Kategorie** | Payment |
| **Zweck** | Zahlungsabwicklung |
| **Weitergegebene Daten** | Name, E-Mail, Rechnungsadresse, Zahlungsdaten |
| **Rechtsgrundlage** | Art. 6(1)(b) DSGVO - Vertragserfüllung |
| **Datenstandort** | USA |
| **Transfermechanismus** | EU-US Data Privacy Framework (DPF) |
| **DPA** | In Stripe Services Agreement enthalten |
| **Datenschutz-URL** | https://stripe.com/de/privacy |

[Weitere Services analog...]

### 5.3 Auftragsverarbeiter (AVV erforderlich)

| Dienstleister | Kategorie | Zweck | Datenstandort | DPA Status | Aktion |
|---------------|-----------|-------|---------------|------------|--------|
| Supabase | Datenbank | Datenspeicherung | EU | In ToS | Prüfen |
| Vercel | Hosting | Frontend-Hosting | Global | In ToS | Prüfen |
| SendGrid | E-Mail | E-Mail-Versand | USA | Separat | Abschließen |
| Sentry | Monitoring | Error Tracking | EU | In ToS | Prüfen |

### 5.4 Datentransfer in Drittländer

| Zielland | Services | Transfermechanismus | Status |
|----------|----------|---------------------|--------|
| USA | Stripe, Google Analytics | EU-US Data Privacy Framework | Aktiv |
| USA | SendGrid | Standardvertragsklauseln (SCC) | Zu prüfen |

---

## 6. Technische Sicherheitsmaßnahmen

### 6.1 Übersicht (Art. 32 DSGVO)

| Maßnahme | Status | Details |
|----------|--------|---------|
| **Verschlüsselung (Transport)** | ✅ | TLS 1.3 für alle Verbindungen |
| **Verschlüsselung (Speicherung)** | ✅ | AES-256 at-rest (Supabase) |
| **Pseudonymisierung** | ✅ | UUIDs statt sequentieller IDs |
| **Zugriffskontrolle** | ✅ | RBAC implementiert |
| **Passwort-Hashing** | ✅ | Argon2id |
| **Rate Limiting** | ✅ | 100 req/min pro IP |
| **CORS** | ✅ | Whitelist konfiguriert |
| **CSRF-Schutz** | ✅ | Token-basiert |
| **Input Validation** | ✅ | Zod/Pydantic Schemas |
| **SQL Injection Prevention** | ✅ | ORM mit parametrisierten Queries |
| **XSS Prevention** | ✅ | React Auto-Escaping, CSP Header |
| **Security Headers** | ✅ | HSTS, X-Frame-Options, etc. |

### 6.2 Authentifizierung

| Feld | Wert |
|------|------|
| Auth-Methode | E-Mail + Passwort |
| OAuth-Provider | Google, GitHub |
| MFA/2FA | Optional (TOTP) |
| Session-Dauer | 7 Tage (erneuerbar) |
| Token-Typ | JWT (httpOnly Secure Cookie) |
| Passwort-Policy | Min. 8 Zeichen, 1 Zahl, 1 Sonderzeichen |
| Account-Sperrung | Nach 5 Fehlversuchen für 15 Minuten |
| Passwort-Reset | Via E-Mail-Link (24h gültig) |

### 6.3 Incident Response

| Feld | Wert |
|------|------|
| Monitoring | [Ja - Tool / Nein] |
| Alerting | [Ja - Kanal / Nein] |
| Backup-Strategie | [Täglich / Wöchentlich / etc.] |
| Meldepflicht (72h) | [Prozess vorhanden / Zu definieren] |

---

## 7. Logging & Monitoring

### 7.1 Server-Logs

| Geloggte Daten | Aufbewahrung | Zweck | PII |
|----------------|--------------|-------|-----|
| Timestamp | 30 Tage | Debugging | Nein |
| Request-URL | 30 Tage | Analyse | Nein |
| HTTP-Methode | 30 Tage | Analyse | Nein |
| Response-Status | 30 Tage | Monitoring | Nein |
| Response-Zeit | 30 Tage | Performance | Nein |
| IP-Adresse | 30 Tage | Security | Ja |
| User-Agent | 30 Tage | Debugging | Nein |
| User-ID | 30 Tage | Audit | Ja (pseudonymisiert) |

### 7.2 Application Logs

| Log-Level | Geloggte Daten | PII-Risiko |
|-----------|----------------|------------|
| ERROR | Stack Traces, Error Context | Mittel (prüfen) |
| WARN | Validierungsfehler | Niedrig |
| INFO | Business Events | Niedrig |
| DEBUG | Detaillierte Ausführung | Hoch (nur Dev) |

### 7.3 Externe Log-Services

| Service | Gesammelte Daten | Datenstandort | DPA |
|---------|------------------|---------------|-----|
| [z.B. Sentry] | Errors, Stack Traces, User Context | EU | Ja |
| [z.B. Datadog] | Logs, Metrics, Traces | EU | Ja |

### 7.4 PII-Warnungen in Logs

| Problem | Fundstelle | Schweregrad | Empfehlung |
|---------|------------|-------------|------------|
| [Falls PII gefunden] | [Datei:Zeile] | [HOCH/MITTEL/NIEDRIG] | [Aktion] |

---

## 8. E-Mail-Kommunikation

### 8.1 E-Mail-Service

| Feld | Wert |
|------|------|
| Anbieter | [SendGrid / Mailgun / SES / etc.] |
| Datenstandort | [EU / USA] |
| Transfermechanismus | [SCC / DPF / etc.] |
| DPA | [Ja / Nein / In ToS] |

### 8.2 E-Mail-Arten

| E-Mail-Typ | Zweck | Rechtsgrundlage | Abmeldung |
|------------|-------|-----------------|-----------|
| Transaktional | Bestellbestätigung, Passwort-Reset | Vertragserfüllung | Nicht möglich |
| Service | Sicherheitswarnungen, Updates | Berechtigtes Interesse | Nicht möglich |
| Newsletter | Marketing | Einwilligung | Link in jeder E-Mail |

### 8.3 Newsletter

| Feld | Wert |
|------|------|
| Double-Opt-In | [Ja / Nein] |
| Abmelde-Link | [Ja / Nein] |
| Einwilligungsnachweis | [Ja (wie) / Nein] |

---

## 9. Betroffenenrechte - Technische Umsetzung

### 9.1 Implementierungsstatus

| Recht (DSGVO) | Status | Methode | Antwortzeit |
|---------------|--------|---------|-------------|
| **Auskunft** (Art. 15) | ✅ | `GET /api/user/data-export` | Sofort |
| **Berichtigung** (Art. 16) | ✅ | Profil-Einstellungen | Sofort |
| **Löschung** (Art. 17) | ✅ | `DELETE /api/user` | 30 Tage Soft-Delete |
| **Einschränkung** (Art. 18) | ⚠️ | E-Mail an Support | 30 Tage |
| **Datenportabilität** (Art. 20) | ✅ | JSON-Export | Sofort |
| **Widerspruch** (Art. 21) | ⚠️ | E-Mail an Support | 30 Tage |
| **Widerruf Einwilligung** | ✅ | Cookie-Banner / Einstellungen | Sofort |

### 9.2 Kontakt für Anfragen

| Kanal | Details |
|-------|---------|
| E-Mail | datenschutz@[domain.tld] |
| Formular | [URL falls vorhanden] |
| Antwortfrist | 30 Tage (gesetzlich), Ziel: 7 Tage |
| Identitätsprüfung | Account-E-Mail oder Ausweiskopie |

### 9.3 Datenschutzbeauftragter

| Feld | Wert |
|------|------|
| DSB bestellt | [Ja / Nein / Nicht erforderlich] |
| Name | [NAME] (falls bestellt) |
| Kontakt | [E-MAIL] (falls bestellt) |

---

## 10. Zusammenfassung für PrimeLegalAI

### 10.1 Benötigte Rechtliche Dokumente

| Dokument | Datenquelle | Priorität |
|----------|-------------|-----------|
| **Impressum** | Abschnitt 1 | Hoch |
| **Datenschutzerklärung** | Abschnitte 3-9 | Hoch |
| **AGB** | Abschnitt 2 | Hoch |
| **Cookie-Policy** | Abschnitt 4 | Hoch |
| **AVV-Template** | Abschnitt 5.3 | Mittel |

### 10.2 Kritische Punkte

| Thema | Status | Anmerkung |
|-------|--------|-----------|
| US-Datentransfer | ⚠️ | DPF/SCC für jeden Service prüfen |
| Cookie-Consent | ⚠️ | Banner vor Analytics erforderlich |
| Aufbewahrungsfristen | ⚠️ | Automatische Löschung implementieren |
| DSB-Pflicht | ✅/⚠️ | Prüfen ob > 20 MA mit Datenverarbeitung |
| Minderjährigenschutz | ✅/⚠️ | Altersverifikation wenn Zielgruppe < 16 |

### 10.3 Offene Fragen

1. Sind alle Rechtsgrundlagen korrekt zugeordnet?
2. Welche AVVs müssen separat abgeschlossen werden?
3. Ist ein Verzeichnis von Verarbeitungstätigkeiten erforderlich?
4. Sind zusätzliche Einwilligungen erforderlich?
5. Welche branchenspezifischen Regelungen gelten?

---

## Anhang A: API-Endpoints mit PII

| Methode | Pfad | Auth | PII-Input | PII-Output | Zweck |
|---------|------|------|-----------|------------|-------|
| POST | `/api/auth/register` | Nein | Email, Password, Name | - | Registrierung |
| POST | `/api/auth/login` | Nein | Email, Password | Token | Login |
| GET | `/api/user/me` | Ja | - | Profildaten | Profil abrufen |
| PATCH | `/api/user/me` | Ja | Name, Email, etc. | - | Profil bearbeiten |
| DELETE | `/api/user/me` | Ja | - | - | Account löschen |
| GET | `/api/user/data-export` | Ja | - | Alle Nutzerdaten | DSGVO-Export |

---

## Anhang B: Checkliste für rechtliche Prüfung

- [ ] Impressum vollständig (§ 5 TMG, § 18 MStV)
- [ ] Datenschutzerklärung vollständig (Art. 13/14 DSGVO)
- [ ] Cookie-Banner DSGVO/TTDSG-konform
- [ ] AGB auf Wirksamkeit geprüft
- [ ] Widerrufsbelehrung korrekt (wenn B2C)
- [ ] AVVs mit allen Auftragsverarbeitern
- [ ] Verzeichnis von Verarbeitungstätigkeiten (wenn erforderlich)
- [ ] TOM dokumentiert (Art. 32 DSGVO)
- [ ] Datenschutz-Folgenabschätzung (wenn erforderlich)

---

*Dokumentation erstellt am [DATUM] durch Claude Code `/ai-first-dev:generate-legal-docs`*
*Version: 1.0*
*Diese Datei sollte NICHT in das Repository committed werden.*
