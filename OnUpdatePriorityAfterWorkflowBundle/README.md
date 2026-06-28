# Haeretici OnUpdatePriorityAfter Workflow Bundle

Isolated test extension for [Haeretici LegacyWorkflowBundle](../LegacyWorkflowBundle/README.md). Registers a custom legacy workflow event type for the **after** (`post_updatepriority`) hook of `content_updatepriority`.

| Property | Value |
|----------|--------|
| Event type string | `event_haeretici_onupdatepriorityafter` |
| Admin label | On update priority after |
| Allowed trigger | `content` / `updatepriority` / `after` only |
| Log file | `var/log/OnUpdatePriorityAfterEventType.log` |

Enable this bundle alone to test `content_updatepriority` workflow parameters and inspect logs without other operation hooks.

## Installation

1. Register PSR-4 autoload for `Haeretici\OnUpdatePriorityAfterWorkflowBundle\` → `OnUpdatePriorityAfterWorkflowBundle/` in `composer.json`, then `composer dump-autoload`.
2. Enable in `config/bundles.php` **after** `LegacyWorkflowBundle`:

```php
Haeretici\OnUpdatePriorityAfterWorkflowBundle\OnUpdatePriorityAfterWorkflowBundle::class => ['all' => true],
```

3. `php bin/console cache:clear`

## Usage

1. Create a workflow and add event **On update priority after** (`event_haeretici_onupdatepriorityafter`).
2. Assign the workflow to **content_updatepriority** → **after** in Settings → Triggers.
3. Invoke the operation (or call `TriggerRunner` in tests with `trigger_name` = `post_updatepriority`, `module_name` = `content`, `module_function` = `updatepriority`, `connect_type` = `after`).
4. Inspect `var/log/OnUpdatePriorityAfterEventType.log` for JSON lines with `triggered_at`, `event_type`, and `parameters`.
