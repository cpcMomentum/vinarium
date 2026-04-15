# Database Migration Strategy

Diese Standards definieren einheitliche Patterns für Database Migrations über alle Projekte hinweg.

## 1. Migration Tools

### Nach Datenbank-Typ

| Database | Tool | Language |
|----------|------|----------|
| PostgreSQL | Prisma Migrate | TypeScript/Node.js |
| PostgreSQL | Alembic | Python |
| PostgreSQL | golang-migrate | Go |
| MongoDB | migrate-mongo | Node.js |
| MongoDB | Custom Scripts | Python |
| Supabase | Supabase Migrations | SQL |

### Empfohlene Tools pro Stack

| Stack | Empfehlung |
|-------|------------|
| Next.js + PostgreSQL | Prisma Migrate |
| FastAPI + PostgreSQL | Alembic |
| Next.js + MongoDB | Mongoose (Schema-less) |
| Supabase | Supabase CLI |

## 2. Migration Grundprinzipien

### Goldene Regeln

1. **Migrations sind immutable** - Niemals eine committete Migration ändern
2. **Eine Richtung** - Migrations werden nur vorwärts angewandt
3. **Atomic** - Jede Migration sollte eine logische Änderung sein
4. **Tested** - Migrations müssen vor Production getestet werden
5. **Reversible** - Idealerweise mit Down-Migration (nicht immer möglich)

### Migration Naming Convention

```
# Format: YYYYMMDDHHMMSS_description.sql
# oder mit auto-increment: 001_description.sql

20250115103000_create_users_table.sql
20250115103500_add_email_index_to_users.sql
20250116090000_create_orders_table.sql
20250116090500_add_user_id_foreign_key_to_orders.sql
```

## 3. Prisma Migrate (TypeScript/Next.js)

### Setup

```bash
npm install prisma @prisma/client
npx prisma init
```

### prisma/schema.prisma

```prisma
generator client {
  provider = "prisma-client-js"
}

datasource db {
  provider = "postgresql"
  url      = env("DATABASE_URL")
}

model User {
  id        String   @id @default(cuid())
  email     String   @unique
  name      String?
  createdAt DateTime @default(now())
  updatedAt DateTime @updatedAt
  orders    Order[]

  @@index([email])
  @@map("users")
}

model Order {
  id        String   @id @default(cuid())
  userId    String
  user      User     @relation(fields: [userId], references: [id])
  total     Decimal  @db.Decimal(10, 2)
  status    OrderStatus @default(PENDING)
  createdAt DateTime @default(now())
  updatedAt DateTime @updatedAt

  @@index([userId])
  @@index([status])
  @@map("orders")
}

enum OrderStatus {
  PENDING
  PROCESSING
  COMPLETED
  CANCELLED
}
```

### Workflow

```bash
# Development: Create and apply migration
npx prisma migrate dev --name add_users_table

# Production: Apply pending migrations
npx prisma migrate deploy

# Reset database (DEVELOPMENT ONLY)
npx prisma migrate reset

# Generate client after schema changes
npx prisma generate
```

### Migration File Structure

```
prisma/
├── schema.prisma
└── migrations/
    ├── 20250115103000_init/
    │   └── migration.sql
    ├── 20250115103500_add_orders/
    │   └── migration.sql
    └── migration_lock.toml
```

## 4. Alembic (Python/FastAPI)

### Setup

```bash
pip install alembic sqlalchemy
alembic init alembic
```

### alembic.ini

```ini
[alembic]
script_location = alembic
prepend_sys_path = .
sqlalchemy.url = postgresql://user:pass@localhost/dbname

[logging]
level = INFO
```

### alembic/env.py

```python
from logging.config import fileConfig
from sqlalchemy import engine_from_config, pool
from alembic import context
import os
import sys

# Add app to path
sys.path.insert(0, os.path.dirname(os.path.dirname(__file__)))

from app.models import Base  # Import your models
from app.core.config import settings

config = context.config

# Override with environment variable
config.set_main_option("sqlalchemy.url", settings.database_url)

if config.config_file_name is not None:
    fileConfig(config.config_file_name)

target_metadata = Base.metadata

def run_migrations_offline():
    url = config.get_main_option("sqlalchemy.url")
    context.configure(
        url=url,
        target_metadata=target_metadata,
        literal_binds=True,
        dialect_opts={"paramstyle": "named"},
    )
    with context.begin_transaction():
        context.run_migrations()

def run_migrations_online():
    connectable = engine_from_config(
        config.get_section(config.config_ini_section),
        prefix="sqlalchemy.",
        poolclass=pool.NullPool,
    )
    with connectable.connect() as connection:
        context.configure(
            connection=connection,
            target_metadata=target_metadata,
        )
        with context.begin_transaction():
            context.run_migrations()

if context.is_offline_mode():
    run_migrations_offline()
else:
    run_migrations_online()
```

### Migration Commands

```bash
# Create migration
alembic revision --autogenerate -m "add_users_table"

# Apply all migrations
alembic upgrade head

# Apply specific migration
alembic upgrade +1

# Rollback
alembic downgrade -1

# Show current revision
alembic current

# Show history
alembic history
```

### Example Migration

```python
# alembic/versions/20250115103000_add_users_table.py
"""add users table

Revision ID: a1b2c3d4e5f6
Revises:
Create Date: 2025-01-15 10:30:00.000000
"""
from alembic import op
import sqlalchemy as sa

revision = 'a1b2c3d4e5f6'
down_revision = None
branch_labels = None
depends_on = None

def upgrade():
    op.create_table(
        'users',
        sa.Column('id', sa.String(36), primary_key=True),
        sa.Column('email', sa.String(255), nullable=False),
        sa.Column('name', sa.String(255)),
        sa.Column('created_at', sa.DateTime(), server_default=sa.func.now()),
        sa.Column('updated_at', sa.DateTime(), onupdate=sa.func.now()),
    )
    op.create_index('ix_users_email', 'users', ['email'], unique=True)

def downgrade():
    op.drop_index('ix_users_email', table_name='users')
    op.drop_table('users')
```

## 5. Safe Migration Patterns

### Adding a Column

```sql
-- Safe: Add nullable column
ALTER TABLE users ADD COLUMN phone VARCHAR(20);

-- Safe: Add column with default
ALTER TABLE users ADD COLUMN is_active BOOLEAN DEFAULT true;

-- Unsafe: Adding NOT NULL without default on existing table
-- ALTER TABLE users ADD COLUMN phone VARCHAR(20) NOT NULL; -- FAILS!

-- Safe alternative for NOT NULL:
-- 1. Add nullable column
ALTER TABLE users ADD COLUMN phone VARCHAR(20);
-- 2. Backfill data
UPDATE users SET phone = 'unknown' WHERE phone IS NULL;
-- 3. Add constraint
ALTER TABLE users ALTER COLUMN phone SET NOT NULL;
```

### Removing a Column

```sql
-- NEVER remove columns directly in production!
-- 1. First: Stop writing to column (code change)
-- 2. Deploy code that doesn't read column
-- 3. Wait for all instances to update
-- 4. Then remove column

-- In separate migration after code deploy:
ALTER TABLE users DROP COLUMN old_column;
```

### Renaming a Column

```sql
-- Unsafe: Direct rename breaks running code
-- ALTER TABLE users RENAME COLUMN name TO full_name;

-- Safe approach (3-step deployment):
-- Step 1: Add new column, copy data
ALTER TABLE users ADD COLUMN full_name VARCHAR(255);
UPDATE users SET full_name = name;

-- Step 2: Deploy code that writes to both, reads from new
-- Step 3: Deploy code that only uses new column
-- Step 4: Drop old column
ALTER TABLE users DROP COLUMN name;
```

### Adding an Index

```sql
-- Safe: CONCURRENTLY prevents table lock (PostgreSQL)
CREATE INDEX CONCURRENTLY ix_users_email ON users(email);

-- Standard (locks table - OK for small tables)
CREATE INDEX ix_users_email ON users(email);
```

### Large Table Migrations

```python
# For large tables, use batched updates
def upgrade():
    connection = op.get_bind()

    # Process in batches of 1000
    batch_size = 1000
    offset = 0

    while True:
        result = connection.execute(
            sa.text(f"""
                UPDATE users
                SET status = 'active'
                WHERE id IN (
                    SELECT id FROM users
                    WHERE status IS NULL
                    LIMIT {batch_size}
                )
            """)
        )

        if result.rowcount == 0:
            break

        offset += batch_size
        # Optional: Add delay to reduce load
        # time.sleep(0.1)
```

## 6. Environment-Specific Migrations

### Migration Order

```
Local → Dev → Staging → Production
```

### Pre-Production Checklist

- [ ] Migration getestet in lokaler Umgebung
- [ ] Migration getestet in Dev/Staging mit Prod-ähnlichen Daten
- [ ] Rollback-Strategie dokumentiert (wenn möglich)
- [ ] Geschätzte Laufzeit bekannt
- [ ] Backup vor Production-Migration erstellt
- [ ] Wartungsfenster geplant (wenn nötig)

### CI/CD Integration

```yaml
# .github/workflows/migrate.yml
name: Database Migration

on:
  push:
    branches: [main]
    paths:
      - 'prisma/migrations/**'
      - 'alembic/versions/**'

jobs:
  migrate-staging:
    runs-on: ubuntu-latest
    environment: staging
    steps:
      - uses: actions/checkout@v6

      - name: Run migrations
        run: npx prisma migrate deploy
        env:
          DATABASE_URL: ${{ secrets.DATABASE_URL }}

  migrate-production:
    runs-on: ubuntu-latest
    needs: migrate-staging
    environment: production
    steps:
      - uses: actions/checkout@v6

      - name: Create backup
        run: |
          pg_dump $DATABASE_URL > backup_$(date +%Y%m%d_%H%M%S).sql

      - name: Run migrations
        run: npx prisma migrate deploy
        env:
          DATABASE_URL: ${{ secrets.DATABASE_URL }}
```

## 7. Rollback Strategies

### Option 1: Down Migration (wenn verfügbar)

```bash
# Prisma - nicht direkt unterstützt, manuell
# Alembic
alembic downgrade -1
```

### Option 2: Forward-Fix Migration

```python
# Statt Rollback: Neue Migration die Problem behebt
"""fix_broken_migration

Revision ID: fix123
Revises: broken456
"""

def upgrade():
    # Undo what broken migration did
    op.drop_column('users', 'broken_column')

def downgrade():
    pass  # Optional
```

### Option 3: Database Restore

```bash
# Letztes Mittel - Restore from Backup
pg_restore -d mydb backup_20250115.sql
```

## 8. Seed Data

### Development Seeds

```typescript
// prisma/seed.ts
import { PrismaClient } from '@prisma/client';

const prisma = new PrismaClient();

async function main() {
  // Clear existing data
  await prisma.order.deleteMany();
  await prisma.user.deleteMany();

  // Create test users
  const user = await prisma.user.create({
    data: {
      email: 'test@example.com',
      name: 'Test User',
      orders: {
        create: [
          { total: 99.99, status: 'COMPLETED' },
          { total: 149.99, status: 'PENDING' },
        ],
      },
    },
  });

  console.log('Seeded:', { user });
}

main()
  .catch(console.error)
  .finally(() => prisma.$disconnect());
```

```json
// package.json
{
  "prisma": {
    "seed": "ts-node --compiler-options {\"module\":\"CommonJS\"} prisma/seed.ts"
  }
}
```

```bash
npx prisma db seed
```

### Reference Data Migrations

Für Production-notwendige Referenzdaten:

```python
# alembic migration
def upgrade():
    # Create table
    op.create_table('order_status', ...)

    # Insert reference data
    op.execute("""
        INSERT INTO order_status (code, name) VALUES
        ('PENDING', 'Pending'),
        ('PROCESSING', 'Processing'),
        ('COMPLETED', 'Completed'),
        ('CANCELLED', 'Cancelled')
    """)
```

## 9. MongoDB Migrations

### migrate-mongo Setup

```bash
npm install -g migrate-mongo
migrate-mongo init
```

### migrate-mongo-config.js

```javascript
module.exports = {
  mongodb: {
    url: process.env.DATABASE_URL,
    databaseName: 'myapp',
    options: {
      useNewUrlParser: true,
      useUnifiedTopology: true,
    },
  },
  migrationsDir: 'migrations',
  changelogCollectionName: 'changelog',
  migrationFileExtension: '.js',
};
```

### Example MongoDB Migration

```javascript
// migrations/20250115103000-add-user-indexes.js
module.exports = {
  async up(db, client) {
    await db.collection('users').createIndex(
      { email: 1 },
      { unique: true, background: true }
    );

    await db.collection('users').createIndex(
      { createdAt: -1 },
      { background: true }
    );
  },

  async down(db, client) {
    await db.collection('users').dropIndex('email_1');
    await db.collection('users').dropIndex('createdAt_-1');
  },
};
```

```bash
# Run migrations
migrate-mongo up

# Rollback
migrate-mongo down
```

## 10. Migration Checklist

### Vor dem Schreiben

- [ ] Verstehe den aktuellen Schema-Zustand
- [ ] Plane die Änderung (welche Tabellen/Columns)
- [ ] Prüfe auf Breaking Changes für laufenden Code
- [ ] Bestimme ob Backfill nötig ist

### Beim Schreiben

- [ ] Descriptive Migration Name
- [ ] Atomare Änderung (eine logische Einheit)
- [ ] Down-Migration wenn möglich
- [ ] Indexes für neue Foreign Keys
- [ ] CONCURRENTLY für große Tabellen

### Vor Production

- [ ] Lokal getestet
- [ ] In Staging getestet
- [ ] Backup-Strategie dokumentiert
- [ ] Team informiert
- [ ] Monitoring bereit

---

**Letzte Aktualisierung**: 2025-12
**Owner**: Database Team
