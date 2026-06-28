# Haeretici On Publish After Workflow Bundle

Per-operation test extension for [Haeretici LegacyWorkflowBundle](../LegacyWorkflowBundle/README.md). It registers a custom legacy workflow event type that runs on the **after** (`post_publish`) hook of `content_publish`. Sibling bundles exist for every supported content operation (`OnHideAfterWorkflowBundle`, `OnDeleteAfterWorkflowBundle`, …) — enable one at a time and inspect its dedicated log file.

| Property | Value |
|----------|--------|
| Event type string | `event_haeretici_onpublishafter` |
| Admin label | On publish after |
| Allowed trigger | `content` / `publish` / `after` only |

Use this bundle as a template when building your own `Haeretici\*` workflow extensions.

## Requirements

- Ibexa DXP 4.x (Symfony 5.4)
- [Haeretici LegacyWorkflowBundle](../LegacyWorkflowBundle/README.md) installed and configured

## Installation

### 1. Copy the bundle

Place this directory in your Ibexa project:

```
bundles/Haeretici/OnPublishAfterWorkflowBundle/
```

It must sit next to `LegacyWorkflowBundle`:

```
bundles/Haeretici/
├── LegacyWorkflowBundle/
└── OnPublishAfterWorkflowBundle/
```

### 2. Register autoloading

In your project `composer.json`:

```json
"autoload": {
    "psr-4": {
        "App\\": "src/",
        "Haeretici\\LegacyWorkflowBundle\\": "bundles/Haeretici/LegacyWorkflowBundle/",
        "Haeretici\\OnPublishAfterWorkflowBundle\\": "bundles/Haeretici/OnPublishAfterWorkflowBundle/"
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
    Haeretici\OnPublishAfterWorkflowBundle\OnPublishAfterWorkflowBundle::class => ['all' => true],
];
```

No extra routes or `config/packages/*.yaml` file is required — the bundle only registers a tagged event type service.

### 4. Clear cache

```bash
php bin/console cache:clear
```

### 5. Verify registration (optional)

```bash
php bin/console debug:container OnPublishAfterEventType
```

You should see the service tagged with `haeretici.legacy_workflow.event_type`.

## Admin usage

1. Log into the Ibexa admin UI.
2. Open **Content → Settings → Workflows**.
3. Create or edit a workflow.
4. In **Add event**, choose **On publish after** (`event_haeretici_onpublishafter`).
5. Open **Content → Settings → Triggers**.
6. For `content_publish`, assign that workflow to the **after** row (maps to `post_publish`).
7. Publish content — the event runs on `PublishVersionEvent`.

The event only appears for workflows connected to an **after** trigger; `isAllowed()` blocks it on **before** (`pre_publish`) triggers.

## How it works

```
OnPublishAfterWorkflowBundle
  OnPublishAfterEventType  ──tag──►  haeretici.legacy_workflow.event_type
                                              │
                                              ▼
                        LegacyWorkflowBundle / WorkflowEventTypePass
                                              │
                                              ▼
                        WorkflowEventTypeRegistry → admin UI + TriggerRunner
```

The event type extends `AbstractWorkflowEventType` from LegacyWorkflowBundle and returns `STATUS_ACCEPTED` after recording a short information message.

Each execution appends one JSON line to `var/log/OnPublishAfterEventType.log` with `triggered_at`, `event_type`, and the workflow `parameters`.

## Building your own extension

Copy this bundle and:

1. Rename the namespace (keep the `Haeretici\` prefix).
2. Change `TYPE_STRING` to a unique value (e.g. `event_haeretici_myfeature`).
3. Set `allowedTriggers` in the event constructor (`before`, `after`, or both).
4. Implement `execute()` with your logic.
5. Tag the service with `haeretici.legacy_workflow.event_type`.
6. Enable the new bundle in `config/bundles.php`.

Event types whose identifier starts with `event_haeretici_` are accepted by the admin UI even when they are not listed in legacy `workflow.ini`.

## Uninstall

1. Remove the bundle from `config/bundles.php`.
2. Remove the PSR-4 autoload entry and run `composer dump-autoload`.
3. Delete the bundle directory.
4. Remove any workflows in the admin UI that still reference `event_haeretici_onpublishafter`.
5. `php bin/console cache:clear`