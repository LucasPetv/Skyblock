# SkyBlock Ironman Progression Assistant

A full PHP web application for tracking Hypixel SkyBlock Ironman accounts, syncing profile data server-side, and turning that data into progression goals, bottleneck reports, history charts, and dungeon notes.

## Features

- Server-side Hypixel and Mojang API integration with caching
- Multi-account and multi-profile tracking
- Goal creation with requirement analysis and resource bottlenecks
- Snapshot history for networth, skill average, magical power, and catacombs
- Manual dungeon run logging
- Dark-mode responsive UI with vanilla JS enhancements
- CLI sync script for cron automation
- PHPUnit coverage for the main service classes

## Requirements

- PHP 8.2+
- Composer
- MySQL 8+ or MariaDB 10.5+
- cURL extension
- PDO MySQL extension
- Web server pointing document root at `public/`

## Installation

1. Clone the repository.
2. Install dependencies:
   ```bash
   composer install
   ```
3. Copy environment variables:
   ```bash
   cp .env.example .env
   ```
4. Fill in `.env`:
   ```env
   HYPIXEL_API_KEY=your_hypixel_key
   DB_HOST=localhost
   DB_NAME=skyblock
   DB_USER=skyblock
   DB_PASSWORD=secret
   APP_ENV=production
   APP_DEBUG=false
   ```
5. Create the database and import the schema:
   ```bash
   mysql -u skyblock -p skyblock < database/schema.sql
   ```
6. Make sure `storage/cache` and `storage/logs` are writable by PHP.

## Running Locally

Use PHP's built-in server for development:

```bash
php -S 127.0.0.1:8000 -t public
```

Then visit <http://127.0.0.1:8000>.

## Web Server Configuration

### Apache

Use `public/` as the document root and enable `mod_rewrite`.

```apache
<VirtualHost *:80>
    ServerName skyblock.local
    DocumentRoot /path/to/Skyblock/public

    <Directory /path/to/Skyblock/public>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

### Nginx

```nginx
server {
    listen 80;
    server_name skyblock.local;
    root /path/to/Skyblock/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
    }
}
```

## Usage Guide

1. Open **Accounts** and add a Minecraft username.
2. Use **Sync** to fetch Hypixel profiles and snapshots.
3. Choose an account/profile from the dashboard selectors.
4. Create goals with skill, collection, dungeon, item, or stat requirements.
5. Review **Progression**, **Resources**, and **History** for planning.
6. Log dungeon runs through the API if you want drop history.

## Cron Sync

Run the CLI sync script every 15 minutes:

```cron
*/15 * * * * cd /path/to/Skyblock && php scripts/sync.php >> storage/logs/cron-sync.log 2>&1
```

## Testing

```bash
composer test
```

## Security Notes

- Keep `HYPIXEL_API_KEY` only in `.env`
- All external API calls are server-side only
- State-changing routes require CSRF tokens
- All SQL uses PDO prepared statements
- All HTML output is escaped with `htmlspecialchars`

## Project Structure

- `public/` web entrypoints, API endpoints, CSS, JS, layout
- `src/` database wrapper, models, services, Hypixel client
- `database/schema.sql` full relational schema
- `scripts/sync.php` cron-friendly sync entrypoint
- `tests/` PHPUnit coverage for critical services
