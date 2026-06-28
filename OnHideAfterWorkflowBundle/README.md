# Haeretici OnHideAfter Workflow Bundle

Isolated test extension for [Haeretici LegacyWorkflowBundle](../LegacyWorkflowBundle/README.md). Registers a custom legacy workflow event type for the **after** (`post_hide`) hook of `content_hide`.

| Property | Value |
|----------|--------|
| Event type string | `event_haeretici_onhideafter` |
| Admin label | On hide after |
| Allowed trigger | `content` / `hide` / `after` only |
| Log file | `var/log/OnHideAfterEventType.log` |

Enable this bundle alone to test `content_hide` workflow parameters and inspect logs without other operation hooks.

## Installation

1. Register PSR-4 autoload for `Haeretici\OnHideAfterWorkflowBundle\` → `OnHideAfterWorkflowBundle/` in `composer.json`, then `composer dump-autoload`.
2. Enable in `config/bundles.php` **after** `LegacyWorkflowBundle`:

```php
Haeretici\OnHideAfterWorkflowBundle\OnHideAfterWorkflowBundle::class => ['all' => true],
```

3. `php bin/console cache:clear`

## Usage

1. Create a workflow and add event **On hide after** (`event_haeretici_onhideafter`).
2. Assign the workflow to **content_hide** → **after** in Settings → Triggers.
3. Invoke the operation (or call `TriggerRunner` in tests with `trigger_name` = `post_hide`, `module_name` = `content`, `module_function` = `hide`, `connect_type` = `after`).
4. Inspect `var/log/OnHideAfterEventType.log` for JSON lines with `triggered_at`, `event_type`, and `parameters`.
