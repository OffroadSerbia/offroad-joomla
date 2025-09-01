# Repo migration helper

Automates export and replay of non-secret settings between GitHub repositories.

Contents:

- repo-migrate-export.ps1 — export manifest (variables, environments index, webhooks metadata, deploy keys, branch protection) WITHOUT secrets
- repo-migrate-replay.ps1 — replay settings on destination using manifest + local secrets
- secrets.sample.psd1 — template for secrets you must provide locally (not committed)

Prereqs:

- PowerShell, gh CLI, env GITHUB_TOKEN with proper scopes (repo, and optionally admin:org)

Usage:

1. Export from source repo:
   pwsh ./tools/migrate/repo-migrate-export.ps1 -SourceRepo "Org/Repo" -OutPath "./repo-migrate-manifest.json"

2. Fill secrets based on secrets.sample.psd1 and save as ./secrets.psd1 (keep it local)

3. Replay into destination repo:
   pwsh ./tools/migrate/repo-migrate-replay.ps1 -DestinationRepo "User/Repo" -ManifestPath "./repo-migrate-manifest.json" -SecretsPath "./secrets.psd1"

Notes:

- GitHub does not allow reading secret values via API. You must supply them via secrets.psd1.
- Webhook config.secret cannot be read; provide it in WEBHOOK_SECRETS keyed by webhook URL.
- Branch protection is applied to main only by default; extend if needed.
- After replay, manually verify Actions, webhooks delivery, and branch protections.
