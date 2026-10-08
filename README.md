# Master Broadcast Bot — Railway

This is a starter PHP + MySQL Telegram multi-bot broadcast manager.

## Features
- Admin-only master bot
- Add/verify child bot tokens with `getMe`
- Stores child tokens encrypted with AES-256-CBC
- Connects each child bot to `/child-webhook.php`
- Collects active users when they message a child bot
- Text/photo/video/audio/document/voice/animation broadcast
- Inline keyboard JSON from an incoming master message is preserved
- Basic success/fail statistics
- MySQL schema auto-created on first request

## Railway variables
Set these on the MASTER BOT service:
- `ADMIN_IDS=8045367594`
- `MASTER_BOT_TOKEN=YOUR_MASTER_BOT_TOKEN`
- `TOKEN_ENCRYPTION_KEY=LONG_RANDOM_SECRET`
- `TELEGRAM_WEBHOOK_SECRET=LONG_RANDOM_SECRET`

Railway's MySQL service automatically supplies:
`MYSQLHOST`, `MYSQLPORT`, `MYSQLUSER`, `MYSQLPASSWORD`, `MYSQLDATABASE`.

Generate a public domain for the app service. The code uses `RAILWAY_PUBLIC_DOMAIN`.

## Important
This starter is not a claim that Telegram allows unlimited sending speed. Telegram rate limits, blocked users, API failures, and media constraints still apply.

For a production-grade system, add a persistent queue/worker, retry/backoff, pagination, media groups, polls, locations, contact, dice, paid media, and a proper visual inline-button builder.

## Webhook
After deployment, open:
`https://YOUR-DOMAIN/`

Then set the master bot webhook:
`https://api.telegram.org/botMASTER_TOKEN/setWebhook?url=https://YOUR-DOMAIN/&secret_token=YOUR_TELEGRAM_WEBHOOK_SECRET`

The child bot webhooks are set automatically when a child bot is added, using:
`https://YOUR-DOMAIN/child-webhook.php?bot=ID&secret=...`
