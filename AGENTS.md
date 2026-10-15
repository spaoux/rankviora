# RankViora

- Work on feature branches; commit and push require user approval.
- Laravel source is in app/. Use Docker Compose from the repository root.
- Never commit .env files or credentials. Never call paid APIs in Sprint 1.
- All tenant resources must be scoped through the authenticated user's workspace membership.
- Run `docker compose exec app php artisan test` after application changes.
- Do not deploy or connect to the VPS without explicit authorization.
