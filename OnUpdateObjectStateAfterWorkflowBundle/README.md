# Haeretici OnUpdateObjectStateAfter Workflow Bundle

Isolated test extension for [Haeretici LegacyWorkflowBundle](../LegacyWorkflowBundle/README.md). Registers a custom legacy workflow event type for the **after** (`post_updateobjectstate`) hook of `content_updateobjectstate`.

| Property | Value |
|----------|--------|
| Event type string | `event_haeretici_onupdateobjectstateafter` |
| Admin label | On update object state after |
| Allowed trigger | `content` / `updateobjectstate` / `after` only |
| Log file | `var/log/OnUpdateObjectStateAfterEventType.log` |

Enable this bundle alone to test `content_updateobjectstate` workflow parameters and inspect logs without other operation hooks.

## Installation

1. Register PSR-4 autoload for `Haeretici\OnUpdateObjectStateAfterWorkflowBundle\` → `OnUpdateObjectStateAfterWorkflowBundle/` in `composer.json`, then `composer dump-autoload`.
2. Enable in `config/bundles.php` **after** `LegacyWorkflowBundle`:

```php
Haeretici\OnUpdateObjectStateAfterWorkflowBundle\OnUpdateObjectStateAfterWorkflowBundle::class => ['all' => true],
```

3. `php bin/console cache:clear`

## Usage

1. Create a workflow and add event **On update object state after** (`event_haeretici_onupdateobjectstateafter`).
2. Assign the workflow to **content_updateobjectstate** → **after** in Settings → Triggers.
3. Invoke the operation (or call `TriggerRunner` in tests with `trigger_name` = `post_updateobjectstate`, `module_name` = `content`, `module_function` = `updateobjectstate`, `connect_type` = `after`).
4. Inspect `var/log/OnUpdateObjectStateAfterEventType.log` for JSON lines with `triggered_at`, `event_type`, and `parameters`.
