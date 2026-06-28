# Haeretici Legacy Workflow Bundle for Ibexa

Ports eZ Publish legacy `kernel/workflow` and `kernel/trigger` into Ibexa using public APIs only. Workflows and triggers can be managed from the Ibexa admin UI under **Settings**.

## Supported scope (from `legacy/settings/workflow.ini`)

| Category | Supported |
|----------|-----------|
| Operation | `content_publish` |
| Event types | `event_ezapprove`, `event_ezwaituntildate`, `event_ezmultiplexer`, `event_ezfinishuserregister` |

Excluded (require kernel hacks or shop integration): shop operations, `event_ezsimpleshipping`, `event_ezpaymentgateway`.

## Features

- Admin menu entries under **Settings**: **Triggers**, **Workflows**, **Workflow processes**
- Database-backed persistence via `ibexa_setting` (default; optional YAML backend for dev/tests)
- Publish hooks via `PublishWorkflowSubscriber` (`pre_publish` / `post_publish`)
- Extensible event type registry (`haeretici.legacy_workflow.event_type` tag)
- `workflow.ini` inspection with filtering to the supported subset

## Requirements

- Ibexa DXP ^4.0 (Symfony 6/7, PHP ^8.1)
- Composer

## Installation

1. **Copy the bundle into your Ibexa project**

   Place this directory at `bundles/Haeretici/LegacyWorkflowBundle/` (or symlink it).

2. **Register PSR-4 autoloading**

   In your project `composer.json`:

   ```json
   "autoload": {
       "psr-4": {
           "App\\": "src/",
           "Haeretici\\LegacyWorkflowBundle\\": "bundles/Haeretici/LegacyWorkflowBundle/"
       }
   }
   ```

   Run:

   ```bash
   composer dump-autoload
   ```

3. **Enable the bundle**

   In `config/bundles.php`:

   ```php
   return [
       // ...
       Haeretici\LegacyWorkflowBundle\LegacyWorkflowBundle::class => ['all' => true],
   ];
   ```

4. **Register routes**

   Create `config/routes/ibexa_legacy_workflow.yaml`:

   ```yaml
   ibexa_legacy_workflow:
       resource: '%kernel.project_dir%/bundles/Haeretici/LegacyWorkflowBundle/Resources/config/routing.yaml'
   ```

5. **Add bundle configuration**

   Copy `ibexa_legacy_workflow.yaml.sample` to `config/packages/ibexa_legacy_workflow.yaml` and adjust if needed:

   ```yaml
   ibexa_legacy_workflow:
       enabled: true
       storage_backend: ibexa_setting
       setting_group: ibexa_legacy_workflow
       setting_identifier: workflow_data
       storage_path: '%kernel.project_dir%/var/ibexa_legacy_workflow/data.yaml'
   ```

   Workflow definitions are stored as JSON in `ibexa_setting` (`group` + `identifier`). If `data.yaml` exists and the DB row is empty, it is imported automatically on first boot.

6. **Grant policies to admin roles**

   The bundle registers `workflow` and `trigger` policies (`read`, `edit`, `admin`). Assign them to roles that should manage legacy workflows (typically the Administrator role via the Admin UI or `ibexa:role` commands).

7. **Clear cache**

   ```bash
   php bin/console cache:clear
   ```

8. **Verify in admin**

   Log into the Ibexa admin UI. Under **Content → Settings** you should see:

   - Triggers
   - Workflows
   - Workflow processes

## Admin usage

### Workflows

1. Open **Settings → Workflows**.
2. Create a workflow and add supported event types in order (placement defines execution sequence).
3. Configure event-specific fields (`dataText1`–`dataText5`, `dataInt1`–`dataInt4`, description) in the edit form and click **Save event configuration**.

### Triggers

1. Open **Settings → Triggers**.
2. For `content_publish`, assign a workflow to **before** (`pre_publish`) and/or **after** (`post_publish`).
3. Save each row. Assignments persist to the YAML storage file.

### Workflow processes

Lists in-flight workflow processes created during publish operations (approval pending, deferred, etc.).

## Runtime integration

Publish hooks are wired automatically:

- `BeforePublishVersionEvent` → `pre_publish` trigger
- `PublishVersionEvent` → `post_publish` trigger

Blocking workflow statuses throw `WorkflowHaltedException` on pre-publish.

## Extending with custom workflow event types

Other bundles in the `Haeretici` namespace can register additional event types via the `haeretici.legacy_workflow.event_type` service tag. There is one **On\*AfterWorkflowBundle** per supported content operation (publish, hide, delete, move, etc.) for isolated testing and per-operation log files — see **[OnPublishAfterWorkflowBundle/README.md](../OnPublishAfterWorkflowBundle/README.md)** and sibling bundle READMEs listed in **[AGENTS.md](../AGENTS.md)**.

Quick summary:

1. Create a class extending `AbstractWorkflowEventType` with a unique `TYPE_STRING` (use the `event_haeretici_*` prefix for custom types).
2. Tag the service with `haeretici.legacy_workflow.event_type` in `Resources/config/services.yaml`.
3. Enable your bundle in `config/bundles.php` (after `LegacyWorkflowBundle`).
4. Clear cache — the event appears in **Settings → Workflows → Add event** when `isAllowed()` matches the trigger connect type (`before` / `after`).

## Development / tests

From the bundle directory:

```bash
php /path/to/phpunit.phar -c phpunit.xml.dist
```

## Reference bundles

Structure follows patterns from `FirewallBundle` (menu listener, admin controllers, policies) and `MugoPage` (Ibexa integration, resources layout).