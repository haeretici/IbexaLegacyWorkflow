# Haeretici OnDeleteAfter Workflow Bundle

Isolated test extension for [Haeretici LegacyWorkflowBundle](../LegacyWorkflowBundle/README.md). Registers a custom legacy workflow event type for the **after** (`post_delete`) hook of `content_delete`.

| Property | Value |
|----------|--------|
| Event type string | `event_haeretici_ondeleteafter` |
| Admin label | On delete after |
| Allowed trigger | `content` / `delete` / `after` only |
| Log file | `var/log/OnDeleteAfterEventType.log` |

Enable this bundle alone to test `content_delete` workflow parameters and inspect logs without other operation hooks.

## Installation

1. Register PSR-4 autoload for `Haeretici\OnDeleteAfterWorkflowBundle\` → `OnDeleteAfterWorkflowBundle/` in `composer.json`, then `composer dump-autoload`.
2. Enable in `config/bundles.php` **after** `LegacyWorkflowBundle`:

```php
Haeretici\OnDeleteAfterWorkflowBundle\OnDeleteAfterWorkflowBundle::class => ['all' => true],
```

3. `php bin/console cache:clear`

## Usage

1. Create a workflow and add event **On delete after** (`event_haeretici_ondeleteafter`).
2. Assign the workflow to **content_delete** → **after** in Settings → Triggers.
3. Move content to trash from the admin content tree (Ibexa calls `TrashService::trash()` on the main location, not `ContentService::deleteContent()`), or call `TriggerRunner` in tests with `trigger_name` = `post_delete`, `module_name` = `content`, `module_function` = `delete`, `connect_type` = `after`.
4. Inspect `var/log/OnDeleteAfterEventType.log` for JSON lines with `triggered_at`, `event_type`, and `parameters`.
