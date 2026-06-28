# Haeretici OnUpdateSectionAfter Workflow Bundle

Isolated test extension for [Haeretici LegacyWorkflowBundle](../LegacyWorkflowBundle/README.md). Registers a custom legacy workflow event type for the **after** (`post_updatesection`) hook of `content_updatesection`.

| Property | Value |
|----------|--------|
| Event type string | `event_haeretici_onupdatesectionafter` |
| Admin label | On update section after |
| Allowed trigger | `content` / `updatesection` / `after` only |
| Log file | `var/log/OnUpdateSectionAfterEventType.log` |

Enable this bundle alone to test `content_updatesection` workflow parameters and inspect logs without other operation hooks.

## Installation

1. Register PSR-4 autoload for `Haeretici\OnUpdateSectionAfterWorkflowBundle\` → `OnUpdateSectionAfterWorkflowBundle/` in `composer.json`, then `composer dump-autoload`.
2. Enable in `config/bundles.php` **after** `LegacyWorkflowBundle`:

```php
Haeretici\OnUpdateSectionAfterWorkflowBundle\OnUpdateSectionAfterWorkflowBundle::class => ['all' => true],
```

3. `php bin/console cache:clear`

## Usage

1. Create a workflow and add event **On update section after** (`event_haeretici_onupdatesectionafter`).
2. Assign the workflow to **content_updatesection** → **after** in Settings → Triggers.
3. Invoke the operation (or call `TriggerRunner` in tests with `trigger_name` = `post_updatesection`, `module_name` = `content`, `module_function` = `updatesection`, `connect_type` = `after`).
4. Inspect `var/log/OnUpdateSectionAfterEventType.log` for JSON lines with `triggered_at`, `event_type`, and `parameters`.
