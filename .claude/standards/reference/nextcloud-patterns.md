# Nextcloud App Development Patterns

> **Referenz fuer:** Controller, Services, Entities, Migrations, Vue-Frontend, Release-Workflow
> **Quellen:** Erfahrungen aus worktime, contractmanager, brandmail Apps (Stand 2026-04-13)
> **NC-Versionen:** 30+ (getestet gegen NC 33)

---

## 1. App-Struktur und Bootstrapping

### Application.php (Pflicht)

```php
<?php
// lib/AppInfo/Application.php
namespace OCA\AppName\AppInfo;

use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;

class Application extends App implements IBootstrap {
    public const APP_ID = 'appname';

    public function __construct() {
        parent::__construct(self::APP_ID);
    }

    public function register(IRegistrationContext $context): void {
        // Event Listener, Notifier, etc. registrieren
    }

    public function boot(IBootContext $context): void {
        // Boot-Logic
    }
}
```

### info.xml (Pflicht-Felder)

```xml
<?xml version="1.0"?>
<info xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
      xsi:noNamespaceSchemaLocation="https://apps.nextcloud.com/schema/apps/info.xsd">
    <id>appname</id>
    <name>App Name</name>
    <summary>Kurzbeschreibung</summary>
    <description><![CDATA[Lange Beschreibung]]></description>
    <version>1.0.0</version>
    <licence>agpl</licence>
    <author>Autor</author>
    <namespace>AppName</namespace>
    <category>tools</category>
    <dependencies>
        <nextcloud min-version="30" max-version="33"/>
    </dependencies>
</info>
```

### routes.php

```php
<?php
// appinfo/routes.php
return [
    'routes' => [
        ['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],
        ['name' => 'resource#index', 'url' => '/api/resources', 'verb' => 'GET'],
        ['name' => 'resource#create', 'url' => '/api/resources', 'verb' => 'POST'],
        ['name' => 'resource#update', 'url' => '/api/resources/{id}', 'verb' => 'PUT'],
        ['name' => 'resource#destroy', 'url' => '/api/resources/{id}', 'verb' => 'DELETE'],
    ],
];
```

---

## 2. Controller Pattern

```php
<?php
// lib/Controller/ResourceController.php
namespace OCA\AppName\Controller;

use OCA\AppName\Service\ResourceService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

class ResourceController extends Controller {
    private ResourceService $service;
    private string $userId;

    public function __construct(
        string $appName,
        IRequest $request,
        ResourceService $service,
        string $userId
    ) {
        parent::__construct($appName, $request);
        $this->service = $service;
        $this->userId = $userId;
    }

    #[NoAdminRequired]
    public function index(): JSONResponse {
        return new JSONResponse($this->service->findAll($this->userId));
    }

    #[NoAdminRequired]
    public function create(string $title, string $content): JSONResponse {
        return new JSONResponse(
            $this->service->create($title, $content, $this->userId)
        );
    }

    #[NoAdminRequired]
    public function update(int $id, string $title, string $content): JSONResponse {
        return new JSONResponse(
            $this->service->update($id, $title, $content, $this->userId)
        );
    }

    #[NoAdminRequired]
    public function destroy(int $id): JSONResponse {
        return new JSONResponse(
            $this->service->delete($id, $this->userId)
        );
    }
}
```

**Wichtige Annotations/Attributes:**
- `#[NoAdminRequired]` — erlaubt Zugriff fuer normale User (ohne: nur Admins)
- `#[NoCSRFRequired]` — nur fuer oeffentliche/API-Endpoints (CSRF-Token wird nicht geprueft)
- Parameter werden automatisch aus dem Request extrahiert (Name muss mit Route-Param uebereinstimmen)

---

## 3. Service Pattern

```php
<?php
// lib/Service/ResourceService.php
namespace OCA\AppName\Service;

use OCA\AppName\Db\Resource;
use OCA\AppName\Db\ResourceMapper;
use OCP\AppFramework\Db\DoesNotExistException;

class ResourceService {
    private ResourceMapper $mapper;

    public function __construct(ResourceMapper $mapper) {
        $this->mapper = $mapper;
    }

    public function findAll(string $userId): array {
        return $this->mapper->findAll($userId);
    }

    public function create(string $title, string $content, string $userId): Resource {
        $resource = new Resource();
        $resource->setTitle($title);
        $resource->setContent($content);
        $resource->setUserId($userId);
        return $this->mapper->insert($resource);
    }

    public function update(int $id, string $title, string $content, string $userId): Resource {
        $resource = $this->mapper->find($id, $userId);
        $resource->setTitle($title);
        $resource->setContent($content);
        return $this->mapper->update($resource);
    }

    public function delete(int $id, string $userId): Resource {
        $resource = $this->mapper->find($id, $userId);
        return $this->mapper->delete($resource);
    }
}
```

---

## 4. Database Pattern (Entity + QBMapper)

### Entity

```php
<?php
// lib/Db/Resource.php
namespace OCA\AppName\Db;

use OCP\AppFramework\Db\Entity;

class Resource extends Entity {
    protected string $title = '';
    protected string $content = '';
    protected string $userId = '';
    protected int $createdAt = 0;
    protected int $updatedAt = 0;

    public function __construct() {
        $this->addType('createdAt', 'integer');
        $this->addType('updatedAt', 'integer');
    }

    // Getter/Setter werden automatisch generiert (getTitle, setTitle, etc.)
    // Nur explizit deklarieren wenn Custom-Logik noetig
}
```

### QBMapper

```php
<?php
// lib/Db/ResourceMapper.php
namespace OCA\AppName\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

class ResourceMapper extends QBMapper {
    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'appname_resources', Resource::class);
    }

    public function findAll(string $userId): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)));
        return $this->findEntities($qb);
    }

    public function find(int $id, string $userId): Resource {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('id', $qb->createNamedParameter($id)))
            ->andWhere($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)));
        return $this->findEntity($qb);
    }
}
```

**Regeln:**
- Tabellennamen: `<appname>_<tablename>` (Prefix mit App-ID)
- Nur Query Builder, **niemals raw SQL**
- Bool-Felder als `SmallInt` im Schema (MySQL-Kompatibilitaet)

---

## 5. Migration Pattern

```php
<?php
// lib/Migration/Version001000Date20260101120000.php
namespace OCA\AppName\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version001000Date20260101120000 extends SimpleMigrationStep {

    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        if (!$schema->hasTable('appname_resources')) {
            $table = $schema->createTable('appname_resources');
            $table->addColumn('id', 'integer', [
                'autoincrement' => true,
                'notnull' => true,
            ]);
            $table->addColumn('title', 'string', [
                'notnull' => true,
                'length' => 200,
            ]);
            $table->addColumn('content', 'text', [
                'notnull' => true,
                'default' => '',
            ]);
            $table->addColumn('user_id', 'string', [
                'notnull' => true,
                'length' => 64,
            ]);
            $table->addColumn('created_at', 'integer', [
                'notnull' => true,
                'default' => 0,
            ]);
            $table->setPrimaryKey(['id']);
            $table->addIndex(['user_id'], 'appname_user_id_idx');
        }

        return $schema;
    }
}
```

**Naming:** `VersionXXXYYYDateYYYYMMDDHHMMSS` — XXX=Major, YYY=Minor

### Repair Step (fuer Cleanup bei Updates)

```php
<?php
// lib/Migration/RemoveOldFiles.php
namespace OCA\AppName\Migration;

use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;

class RemoveOldFiles implements IRepairStep {
    public function getName(): string {
        return 'Remove deprecated files from previous version';
    }

    public function run(IOutput $output): void {
        // Alt-Dateien entfernen die in neuer Version nicht mehr existieren
        // → sonst Integrity-Check EXTRA_FILE
    }
}
```

---

## 6. Vue.js Frontend Pattern

### main.js (Entry)

```javascript
// src/main.js
import Vue from 'vue'
import App from './App.vue'
import router from './router'
import store from './store'

new Vue({
    el: '#content',
    router,
    store,
    render: h => h(App),
})
```

### Router (Hash-Mode Pflicht!)

```javascript
// src/router.js
import Vue from 'vue'
import Router from 'vue-router'
import { generateUrl } from '@nextcloud/router'

Vue.use(Router)

export default new Router({
    mode: 'hash',  // PFLICHT — History-Mode funktioniert NICHT in NC
    base: generateUrl('/apps/appname'),
    routes: [
        { path: '/', component: () => import('./views/Main.vue') },
        { path: '/detail/:id', component: () => import('./views/Detail.vue') },
    ],
})
```

### App.vue (mit NC-Layout)

```vue
<template>
    <NcContent app-name="appname">
        <NcAppNavigation>
            <template #list>
                <NcAppNavigationItem v-for="item in items"
                    :key="item.id"
                    :name="item.title"
                    :to="{ path: `/detail/${item.id}` }" />
            </template>
        </NcAppNavigation>
        <NcAppContent>
            <router-view />
        </NcAppContent>
    </NcContent>
</template>

<script>
import { NcContent, NcAppNavigation, NcAppNavigationItem, NcAppContent } from '@nextcloud/vue'

export default {
    name: 'App',
    components: { NcContent, NcAppNavigation, NcAppNavigationItem, NcAppContent },
    // ...
}
</script>
```

### API-Aufrufe

```javascript
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

// GET
const { data } = await axios.get(generateUrl('/apps/appname/api/resources'))

// POST
await axios.post(generateUrl('/apps/appname/api/resources'), { title, content })
```

---

## 7. NC-spezifische Fallstricke (MUST-KNOW)

### 7.1 FilenameValidator strippt Dateien
NC entfernt beim Install `.htaccess` und `.user.ini`. Wenn diese im signierten Tarball sind → `FILE_MISSING` Integrity-Fehler.
**Regel:** Vor Signatur aus Sign-Tree entfernen.

### 7.2 App-Update raeumt nicht auf
NC loescht beim Upgrade KEINE Dateien die in der neuen Version fehlen → `EXTRA_FILE`.
**Loesung:** `IRepairStep` implementieren der Alt-Dateien loescht.

### 7.3 Integrity-Check ist streng
Jede Abweichung vom signierten Zustand fuehrt zur Admin-UI-Warnung. Empfindlich gegen:
- `.DS_Store` Dateien
- Line-Ending-Unterschiede (CRLF vs. LF)
- Whitespace-Unterschiede
**Loesung:** Release **ausschliesslich** aus `git archive HEAD` bauen.

### 7.4 OCP-APIs vorab verifizieren
OCP-Interfaces aendern sich zwischen NC-Majors. **Immer** im Docker-Container pruefen:
```bash
docker exec -t nextcloud-dev cat /var/www/html/lib/public/Notification/INotification.php
```

### 7.5 @nextcloud/* Frontend-APIs verifizieren
Auch Frontend-Pakete aendern sich. Methoden-Signaturen vor Nutzung pruefen (`node_modules/@nextcloud/<pkg>/`).

### 7.6 Hash-Routing ist Pflicht
NC-Apps laufen unter `/apps/<appname>/` ohne History-API. Vue-Router **muss** Hash-Mode nutzen.

### 7.7 Deploy: apps/ vs. custom_apps/
NC laedt zuerst `apps/` (Core), dann `custom_apps/` (User). Deploy-Scripts muessen **nur** nach `custom_apps/` schreiben.

### 7.8 Bool-Handling MySQL/PostgreSQL/SQLite
Entity-Properties als `bool` typen, Schema als `SmallInt` statt `Boolean` — sonst MySQL-Fehler.

### 7.9 OC\* APIs sind VERBOTEN
`OC\*` ist privates, instabiles Core-API. Nur `OCP\*` verwenden. Verstoss → Breakage bei NC-Major-Updates.

### 7.10 Kompiliertes JS committen
`npm run build` vor jedem Commit der Vue-Code aendert. Output in `js/` muss committed werden — ohne laeuft die App nicht.

---

## 8. Release-Workflow (Kurzreferenz)

1. Pre-Checks (clean tree, branch=develop, Docker, Signing-Key)
2. Release-Branch `release/vX.Y.Z`
3. Version bump in `info.xml` + `package.json` (synchron!)
4. `npm install && npm run build`
5. Tarball aus `git archive HEAD` (nicht Worktree!)
6. `.htaccess`/`.user.ini` aus Sign-Tree entfernen
7. Signatur: `openssl dgst -sha512 -sign`
8. Upgrade-Test (Vorversion → neue Version → Integrity-Check)
9. GitHub Release mit Tarball
10. App Store Upload via REST API
11. Release-Branch → main mergen
12. main → develop zuruecksyncen

---

## 9. Nicht-anwendbare ai-first-dev Patterns

| Pattern | NC-Aequivalent |
|---------|----------------|
| Docker Compose fuer App | App laeuft IN Nextcloud |
| Vercel Deploy | App Store oder rsync |
| OpenAPI/Swagger | routes.php (intern) |
| NextAuth / JWT | OCP\IUserSession |
| Prisma / SQLAlchemy | OCP Query Builder |
| ESLint Standard-Config | @nextcloud/eslint-config |
| Webpack Standard-Config | @nextcloud/webpack-vue-config |
