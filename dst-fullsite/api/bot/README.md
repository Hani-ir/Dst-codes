# DST messenger-bot reporting API (PHP, file-based)

Endpoints (all POST, header `X-Bot-Key`): `whoami`, `report/ceo-daily`, `report/station-sales`, `report/daily-recipients`.
Admin page: `admin/`. Tests: `php tests-bot/run.php` (repo root). Setup guide (Persian): Hani-ir/claude-projects → projects/lubestation-bot.

SECURITY: this repository is public. The bot key, admin password, users and data live in `dst-bot-data/`, OUTSIDE `public_html`, created by
`lubestation-bot-data-template/make-data.php`. Never commit them. The API refuses to run if that folder is inside the web root.
