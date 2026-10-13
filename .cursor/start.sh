#!/usr/bin/env bash
set -euo pipefail

cd /workspace

if ! mysqladmin --protocol=tcp -h 127.0.0.1 -u root ping --silent >/dev/null 2>&1; then
    sudo mkdir -p /var/run/mysqld
    sudo chown mysql:mysql /var/run/mysqld
    sudo rm -f /var/run/mysqld/mysqld.pid /var/lib/mysql/*.pid
    sudo mysqld_safe --datadir=/var/lib/mysql --user=mysql --bind-address=127.0.0.1 >/tmp/mysqld.log 2>&1 &
    ready=0
    for _ in $(seq 1 60); do
        if mysqladmin --protocol=tcp -h 127.0.0.1 -u root ping --silent >/dev/null 2>&1; then
            ready=1
            break
        fi
        sleep 1
    done
    if [[ "${ready}" -ne 1 ]]; then
        echo "MariaDB did not become ready" >&2
        exit 1
    fi
fi

sudo mysql <<'SQL'
CREATE DATABASE IF NOT EXISTS unicooam CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS unicoloan_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS proforma CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'root'@'127.0.0.1' IDENTIFIED BY '';
ALTER USER 'root'@'127.0.0.1' IDENTIFIED BY '';
GRANT ALL PRIVILEGES ON *.* TO 'root'@'127.0.0.1' WITH GRANT OPTION;
FLUSH PRIVILEGES;
SQL

if [[ ! -f .env ]]; then
    cp .env.example .env
fi

if ! grep -q '^APP_KEY=base64:' .env; then
    php artisan key:generate --force --no-interaction
fi

php artisan migrate --force --no-interaction

php artisan tinker --execute 'if (! App\Models\User::query()->where("email", "dev@example.com")->exists()) { App\Models\User::factory()->create(["name" => "Dev Agent", "email" => "dev@example.com"]); }'

if ! curl -sf -o /dev/null --max-time 2 http://127.0.0.1:8000/admin/login; then
    tmux kill-session -t laravel-serve >/dev/null 2>&1 || true
    tmux new-session -d -s laravel-serve 'cd /workspace && php artisan serve --host 0.0.0.0 --port 8000'
fi

if ! tmux has-session -t laravel-queue >/dev/null 2>&1; then
    tmux new-session -d -s laravel-queue 'cd /workspace && php artisan queue:listen --tries=1 --timeout=0'
fi

ready=0
for _ in $(seq 1 30); do
    if curl -sf -o /dev/null --max-time 2 http://127.0.0.1:8000/admin/login; then
        ready=1
        break
    fi
    sleep 1
done

if [[ "${ready}" -ne 1 ]]; then
    echo "Laravel did not become ready on port 8000" >&2
    exit 1
fi
