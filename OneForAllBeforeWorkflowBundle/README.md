# Haeretici One For All Before Workflow Bundle

Consolidated test extension for [Haeretici LegacyWorkflowBundle](../LegacyWorkflowBundle/README.md). It registers **twelve** custom legacy workflow event types — one per supported content operation — all scoped to the **before** (`pre_*`) hook. Every event type class lives under `Workflow/EventType/` in this single bundle (unlike the per-operation `On*AfterWorkflowBundle/` siblings).

| Property | Value |
|----------|--------|
| Event type strings | `event_haeretici_on{function}before` (see table below) |
| Allowed trigger | `content` / `{function}` / `before` only (each type) |
| Log file | `var/log/OneForAllBeforeEventType.log` (shared) |

## Bundle structure

```
OneForAllBeforeWorkflowBundle/
├── OneForAllBeforeWorkflowBundle.php
├── DependencyInjection/OneForAllBeforeWorkflowExtension.php
├── README.md
├── Resources/config/services.yaml
└── Workflow/
    ├── EventType/          # 12 before event type classes
    │   ├── OnPublishBeforeEventType.php
    │   ├── OnHideBeforeEventType.php
    │   └── ... (10 more)
    └── Service/
        └── OneForAllBeforeEventTypeLogger.php
```

## Key design choices

- **12 event types** under `Workflow/EventType/`, each with a unique `event_haeretici_on{function}before` identifier and `content/{function}/['before']` trigger restriction
- **Shared logger** — `OneForAllBeforeEventTypeLogger` writes JSON lines to `var/log/OneForAllBeforeEventType.log`; each event type passes its own `event_type` at log time
- **Services wiring** — all 12 types are tagged with `haeretici.legacy_workflow.event_type` in `Resources/config/services.yaml`

## Registered before event types

All classes are in `Workflow/EventType/`:

| Operation | Class | Event type string | Admin label |
|-----------|-------|-------------------|-------------|
| `content_publish` | `OnPublishBeforeEventType` | `event_haeretici_onpublishbefore` | On publish before |
| `content_hide` | `OnHideBeforeEventType` | `event_haeretici_onhidebefore` | On hide before |
| `content_show` | `OnShowBeforeEventType` | `event_haeretici_onshowbefore` | On show before |
| `content_delete` | `OnDeleteBeforeEventType` | `event_haeretici_ondeletebefore` | On delete before |
| `content_move` | `OnMoveBeforeEventType` | `event_haeretici_onmovebefore` | On move before |
| `content_addlocation` | `OnAddLocationBeforeEventType` | `event_haeretici_onaddlocationbefore` | On add location before |
| `content_removelocation` | `OnRemoveLocationBeforeEventType` | `event_haeretici_onremovelocationbefore` | On remove location before |
| `content_swap` | `OnSwapBeforeEventType` | `event_haeretici_onswapbefore` | On swap before |
| `content_updatepriority` | `OnUpdatePriorityBeforeEventType` | `event_haeretici_onupdateprioritybefore` | On update priority before |
| `content_removetranslation` | `OnRemoveTranslationBeforeEventType` | `event_haeretici_onremovetranslationbefore` | On remove translation before |
| `content_updateobjectstate` | `OnUpdateObjectStateBeforeEventType` | `event_haeretici_onupdateobjectstatebefore` | On update object state before |
| `content_updatesection` | `OnUpdateSectionBeforeEventType` | `event_haeretici_onupdatesectionbefore` | On update section before |

Use this bundle as a template when you want all before-action test hooks in one place.

## Requirements

- Ibexa DXP 4.x (Symfony 5.4)
- [Haeretici LegacyWorkflowBundle](../LegacyWorkflowBundle/README.md) installed and configured

## Installation

### 1. Copy the bundle

Place this directory in your Ibexa project:

```
bundles/Haeretici/OneForAllBeforeWorkflowBundle/
```

It must sit next to `LegacyWorkflowBundle`:

```
bundles/Haeretici/
├── LegacyWorkflowBundle/
└── OneForAllBeforeWorkflowBundle/
```

### 2. Register autoloading

In your project `composer.json`:

```json
"autoload": {
    "psr-4": {
        "App\\": "src/",
        "Haeretici\\LegacyWorkflowBundle\\": "bundles/Haeretici/LegacyWorkflowBundle/",
        "Haeretici\\OneForAllBeforeWorkflowBundle\\": "bundles/Haeretici/OneForAllBeforeWorkflowBundle/"
    }
}
```

Run:

```bash
composer dump-autoload
```

### 3. Enable the bundle

In `config/bundles.php`, register **after** `LegacyWorkflowBundle`:

```php
return [
    // ...
    Haeretici\LegacyWorkflowBundle\LegacyWorkflowBundle::class => ['all' => true],
    Haeretici\OneForAllBeforeWorkflowBundle\OneForAllBeforeWorkflowBundle::class => ['all' => true],
];
```

No extra routes or `config/packages/*.yaml` file is required — the bundle only registers tagged event type services.

### 4. Clear cache

```bash
php bin/console cache:clear
```

### 5. Verify registration (optional)

```bash
php bin/console debug:container OnPublishBeforeEventType
```

You should see the service tagged with `haeretici.legacy_workflow.event_type`.

## Admin usage

1. Log into the Ibexa admin UI.
2. Open **Content → Settings → Workflows**.
3. Create or edit a workflow.
4. In **Add event**, choose the desired before type (e.g. **On publish before**).
5. Open **Content → Settings → Triggers**.
6. For the matching operation (e.g. `content_publish`), assign that workflow to the **before** row (maps to `pre_publish`).
7. Perform the content operation — the event runs on the corresponding `Before*` Repository event.

Each event type only appears for workflows connected to a **before** trigger; `isAllowed()` blocks it on **after** (`post_*`) triggers.

## How it works

```
OneForAllBeforeWorkflowBundle
  Workflow/EventType/On*BeforeEventType  ──tag──►  haeretici.legacy_workflow.event_type
                                                              │
                                                              ▼
                                    LegacyWorkflowBundle / WorkflowEventTypePass
                                                              │
                                                              ▼
                                    WorkflowEventTypeRegistry → admin UI + TriggerRunner
```

All twelve types extend `AbstractWorkflowEventType` from LegacyWorkflowBundle and return `STATUS_ACCEPTED` after recording a short information message. A single `OneForAllBeforeEventTypeLogger` appends JSON lines to `var/log/OneForAllBeforeEventType.log` with `triggered_at`, `event_type`, and workflow `parameters`.

## Building your own extension

Copy this bundle (or a single class from `Workflow/EventType/`) and:

1. Rename the namespace (keep the `Haeretici\` prefix).
2. Change `TYPE_STRING` to a unique value (e.g. `event_haeretici_myfeature`).
3. Set `allowedTriggers` in the event constructor (`before`, `after`, or both).
4. Implement `execute()` with your logic.
5. Tag the service with `haeretici.legacy_workflow.event_type`.
6. Enable the bundle in `config/bundles.php`.

Event types whose identifier starts with `event_haeretici_` are accepted by the admin UI even when they are not listed in legacy `workflow.ini`.

## Verification

For automated cross-checks against Ibexa and `LegacyWorkflowBundle`, run the repository audit:

```bash
php CHECK_FEATURES/scripts/check_workflow_hooks.php --verbose
```

See [CHECK_FEATURES/commands/check-workflow-hooks.md](../CHECK_FEATURES/commands/check-workflow-hooks.md).

When developing or after installation, you can also confirm the bundle is wired correctly:

1. **PHP syntax** — lint every file under the bundle:

   ```bash
   find OneForAllBeforeWorkflowBundle -name '*.php' -exec php -l {} \;
   ```

2. **Event type behaviour** — each before type should allow `content` / `{function}` / `before`, reject `after`, return `STATUS_ACCEPTED` with representative parameters, and return `STATUS_WORKFLOW_CANCELLED` when required keys (`object_id`, `version`) are missing.

3. **LegacyWorkflowBundle tests** — the core suite should remain green:

   ```bash
   cd LegacyWorkflowBundle && php phpunit.phar -c phpunit.xml.dist
   ```

   Expected: all tests pass (66 tests, 203 assertions as of bundle creation).

4. **Runtime registration** (Ibexa project):

   ```bash
   php bin/console debug:container OnPublishBeforeEventType
   ```

   Confirm the service is tagged with `haeretici.legacy_workflow.event_type`.

After a content operation with a before workflow assigned, inspect `var/log/OneForAllBeforeEventType.log` for JSON lines containing `triggered_at`, `event_type`, and `parameters`.

## Uninstall

1. Remove the bundle from `config/bundles.php`.
2. Remove the PSR-4 autoload entry and run `composer dump-autoload`.
3. Delete the bundle directory.
4. Remove any workflows in the admin UI that still reference `event_haeretici_on*before` types.
5. `php bin/console cache:clear`