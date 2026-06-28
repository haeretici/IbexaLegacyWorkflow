# audit

Agent-oriented verification commands for this repository. Each command lives under `commands/` with a runnable script in `scripts/`.

Reference these files with `@audit` (or `@audit/commands/<name>.md`) when asking an agent to audit the codebase.

## Available commands

| Command | Script | Purpose |
|---------|--------|---------|
| [check-workflow-hooks](commands/check-workflow-hooks.md) | `scripts/check_workflow_hooks.php` | Compare Ibexa Repository events (`vendor/ibexa/core`) with `LegacyWorkflowBundle` wiring and extension bundle coverage (`OneForAllBeforeWorkflowBundle`, `On*AfterWorkflowBundle`); flag deprecated contracts and unwired hooks |

## Quick start

```bash
# Human-readable report
php audit/scripts/check_workflow_hooks.php --verbose

# JSON for tooling
php audit/scripts/check_workflow_hooks.php --json
```

## Adding a new check

1. Add `scripts/<name>.php` — prefer static analysis over requiring a booted kernel.
2. Add `commands/<name>.md` — describe triggers, how to run, how to interpret output, exit codes.
3. Register the command in the table above.

## Design notes

- Scripts resolve paths relative to the repository root (`audit/..`).
- Checks read `vendor/` for Ibexa CMS truth and `LegacyWorkflowBundle/` for project wiring.
- Exit code `0` means the check passed; non-zero means actionable gaps were found or prerequisites are missing.