# Haeretici OnAddLocationAfter Workflow Bundle

Isolated test extension for [Haeretici LegacyWorkflowBundle](../LegacyWorkflowBundle/README.md). Registers a custom legacy workflow event type for the **after** (`post_addlocation`) hook of `content_addlocation`.

| Property | Value |
|----------|--------|
| Event type string | `event_haeretici_onaddlocationafter` |
| Admin label | On add location after |
| Allowed trigger | `content` / `addlocation` / `after` only |
| Log file | `var/log/OnAddLocationAfterEventType.log` |

Enable this bundle alone to test `content_addlocation` workflow parameters and inspect logs without other operation hooks.

## Installation

1. Register PSR-4 autoload for `Haeretici\OnAddLocationAfterWorkflowBundle\` → `OnAddLocationAfterWorkflowBundle/` in `composer.json`, then `composer dump-autoload`.
2. Enable in `config/bundles.php` **after** `LegacyWorkflowBundle`:

```php
Haeretici\OnAddLocationAfterWorkflowBundle\OnAddLocationAfterWorkflowBundle::class => ['all' => true],
```

3. `php bin/console cache:clear`

## Usage

1. Create a workflow and add event **On add location after** (`event_haeretici_onaddlocationafter`).
2. Assign the workflow to **content_addlocation** → **after** in Settings → Triggers.
3. Invoke the operation (or call `TriggerRunner` in tests with `trigger_name` = `post_addlocation`, `module_name` = `content`, `module_function` = `addlocation`, `connect_type` = `after`).
4. Inspect `var/log/OnAddLocationAfterEventType.log` for JSON lines with `triggered_at`, `event_type`, and `parameters`.
