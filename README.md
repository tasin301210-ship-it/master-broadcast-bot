# Render Master Broadcast Bot
PHP 8.3 + PostgreSQL + Telegram Bot API.

Features:
- Admin-only master bot
- Add child bot tokens
- Automatic child webhook setup
- Collect child-bot private users
- Broadcast text/photo/video/audio/document/voice/animation
- Inline keyboard preservation
- PostgreSQL auto schema
- Encrypted child bot tokens

Render environment variables:
ADMIN_IDS, MASTER_BOT_TOKEN, TOKEN_ENCRYPTION_KEY, TELEGRAM_WEBHOOK_SECRET, PUBLIC_URL, DATABASE_URL

After deploy set the master webhook:
https://api.telegram.org/botMASTER_BOT_TOKEN/setWebhook?url=PUBLIC_URL/&secret_token=TELEGRAM_WEBHOOK_SECRET
