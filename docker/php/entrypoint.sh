#!/usr/bin/env bash
set -euo pipefail

# Selain php-fpm (artisan, composer, bash, dll) langsung dieksekusi tanpa bootstrap.
if [ "${1:-}" != "php-fpm" ]; then
  exec "$@"
fi

cd /var/www/html
export COMPOSER_MEMORY_LIMIT=-1
MARK=storage/app/.bootstrapped
log() { echo -e "\n\033[1;36m[ps2-uad]\033[0m $*"; }

rm -f "$MARK"

# 1. Scaffold Laravel kalau belum ada
if [ ! -f artisan ]; then
  log "Belum ada project Laravel -> scaffold laravel/laravel:${LARAVEL_VERSION:-^12.0}"
  tmp="$(mktemp -d)"
  chmod 755 "$tmp"
  composer create-project "laravel/laravel:${LARAVEL_VERSION:-^12.0}" "$tmp" \
    --prefer-dist --no-interaction --no-scripts
  rm -f "$tmp/.env"
  cp -an "$tmp"/. .          # -n: .env.example kita nggak ketimpa
  rm -rf "$tmp"
  touch .scaffold-pending
fi

# 2. Dependencies (kalau clone repo yang sudah ada)
if [ ! -d vendor ]; then
  log "composer install"
  composer install --no-interaction --prefer-dist
fi

# 3. .env + APP_KEY
[ -f .env ] || cp .env.example .env
if grep -qE '^APP_KEY=\s*$' .env; then
  php artisan key:generate --force
fi

# 4. Paket aplikasi - hanya untuk project baru hasil scaffold
if [ -f .scaffold-pending ]; then
  log "Install paket: Livewire 3, Spatie Permission, Excel, DomPDF, Breeze"
  composer require --no-interaction \
    "livewire/livewire:^3.0" spatie/laravel-permission maatwebsite/excel barryvdh/laravel-dompdf
  composer require --dev --no-interaction laravel/breeze
  php artisan breeze:install livewire --no-interaction
  php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider" --no-interaction || true
  # timezone app -> Asia/Jakarta
  sed -i "s#'timezone' => 'UTC'#'timezone' => 'Asia/Jakarta'#" config/app.php || true
  rm -f .scaffold-pending
fi

# 5. Frontend assets
if [ -f package.json ]; then
  [ -d node_modules ] || { log "npm install"; npm install; }
  [ -f public/build/manifest.json ] || { log "npm run build"; npm run build; }
fi

# 6. Database
log "migrate"
php artisan migrate --force
php artisan storage:link 2>/dev/null || true

touch "$MARK"
log "Siap -> http://localhost:${APP_PORT:-8000}"
exec "$@"
