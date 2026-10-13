#!/usr/bin/env bash
set -euo pipefail

cd /workspace

export COMPOSER_NO_INTERACTION=1
export DEBIAN_FRONTEND=noninteractive

need_apt=0
if ! php -r 'exit(PHP_VERSION_ID >= 80400 ? 0 : 1);' >/dev/null 2>&1; then
    need_apt=1
fi
if ! command -v mysqld_safe >/dev/null 2>&1; then
    need_apt=1
fi
if ! command -v pdftk >/dev/null 2>&1; then
    need_apt=1
fi

if [[ "${need_apt}" -eq 1 ]]; then
    sudo add-apt-repository -y ppa:ondrej/php
    sudo apt-get update
    sudo apt-get install -y \
        php8.4-cli \
        php8.4-mbstring \
        php8.4-xml \
        php8.4-curl \
        php8.4-mysql \
        php8.4-zip \
        php8.4-bcmath \
        php8.4-intl \
        php8.4-gd \
        php8.4-readline \
        php8.4-opcache \
        php8.4-sqlite3 \
        unzip \
        mariadb-server \
        pdftk-java
    sudo update-alternatives --set php /usr/bin/php8.4 || true
fi

if ! command -v composer >/dev/null 2>&1; then
    curl -fsSL https://getcomposer.org/installer -o /tmp/composer-setup.php
    php /tmp/composer-setup.php --install-dir=/tmp --filename=composer.phar
    sudo mv /tmp/composer.phar /usr/local/bin/composer
    rm -f /tmp/composer-setup.php
fi

core_dir="/unico-core"
lock_ref="$(php -r '
    $lock = json_decode(file_get_contents("composer.lock"), true);
    foreach ($lock["packages"] as $package) {
        if ($package["name"] === "meo-icar/unico-core") {
            echo $package["dist"]["reference"];
            exit(0);
        }
    }
    fwrite(STDERR, "meo-icar/unico-core is missing from composer.lock\n");
    exit(1);
')"

if [[ ! -d "${core_dir}/.git" ]]; then
    sudo mkdir -p "${core_dir}"
    sudo chown "$(id -u):$(id -g)" "${core_dir}"
    git clone https://github.com/Meo-ICAR/unico-core.git "${core_dir}"
fi

git -C "${core_dir}" fetch --tags origin
git -C "${core_dir}" checkout "${lock_ref}"

composer install --no-interaction --prefer-dist
npm ci --ignore-scripts
npm run build

if [[ ! -f .env ]]; then
    cp .env.example .env
fi

if ! grep -q '^APP_KEY=base64:' .env; then
    php artisan key:generate --force --no-interaction
fi

mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
if [[ ! -e public/storage ]]; then
    php artisan storage:link --no-interaction
fi
