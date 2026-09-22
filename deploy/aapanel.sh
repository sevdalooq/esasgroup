#!/bin/bash
# aaPanel "Git Deployment" sonrası çalışan kurulum scripti.
# aaPanel'deki webhook scripti sadece `git pull` yapıp bu dosyayı çağırır;
# böylece deploy adımları repo ile birlikte güncellenir.
#
# Sunucuda gerekenler (bir kez):
#   - PHP 8.3 (aaPanel > App Store > PHP 8.3) + eklentiler: fileinfo, redis, intl, gd, zip, pcntl (reverb için), exif
#   - Composer (aaPanel PHP > Composer, ya da /usr/bin/composer)
#   - Node.js 20+ (aaPanel > Node.js Version Manager)
#   - .env dosyası site kökünde oluşturulmuş olmalı (bkz. .env.example); script .env'i ASLA üzerine yazmaz.
#   - Site kök dizini (document root) /public olarak ayarlanmalı, "Anti-XSS" (open_basedir) kapatılmalı.
#   - Supervisor (aaPanel > App Store) ile queue:work ve reverb:start süreçleri; aşağıda restart ediliyor.
set -euo pipefail

APP_DIR="${APP_DIR:-/www/wwwroot/esasgroup.fraudova.com}"
PHP_BIN="${PHP_BIN:-/www/server/php/83/bin/php}"
COMPOSER_BIN="${COMPOSER_BIN:-composer}"
WEB_USER="${WEB_USER:-www}"

# aaPanel'in Node sürüm yöneticisi PATH'e eklenmez; en yeni kurulu sürümü bul.
if ! command -v npm >/dev/null 2>&1; then
  NODE_DIR=$(ls -d /www/server/nodejs/v* 2>/dev/null | sort -V | tail -1 || true)
  [ -n "$NODE_DIR" ] && export PATH="$NODE_DIR/bin:$PATH"
fi
if ! command -v "$COMPOSER_BIN" >/dev/null 2>&1; then
  [ -x /usr/local/bin/composer ] && COMPOSER_BIN=/usr/local/bin/composer
  [ -x /www/server/php/83/bin/composer ] && COMPOSER_BIN=/www/server/php/83/bin/composer
fi

cd "$APP_DIR"
echo "== Deploy başlıyor: $(date) =="
echo "PHP: $("$PHP_BIN" -v | head -1)"
echo "Node: $(node -v 2>/dev/null || echo 'YOK')  npm: $(npm -v 2>/dev/null || echo 'YOK')"
for ext in gd zip pcntl fileinfo redis intl exif; do
  "$PHP_BIN" -m | grep -qix "$ext" && echo "PHP ext $ext: var" || echo "UYARI: PHP ext $ext YOK"
done

if [ ! -f .env ]; then
  echo "HATA: .env yok. .env.example'dan oluşturup APP_KEY, DB_*, REVERB_* ve VITE_* değerlerini doldurun." >&2
  exit 1
fi

# Bakım modu (ilk kurulumda vendor yoksa artisan çalışmaz, o yüzden koşullu)
[ -d vendor ] && "$PHP_BIN" artisan down --retry=15 || true

echo "== Composer =="
export COMPOSER_ALLOW_SUPERUSER=1 COMPOSER_HOME="${COMPOSER_HOME:-/tmp/composer-home}"
"$PHP_BIN" "$(command -v "$COMPOSER_BIN")" install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-progress

# İlk kurulumda APP_KEY boşsa üret
if ! grep -qE '^APP_KEY=base64:' .env; then
  "$PHP_BIN" artisan key:generate --force
fi

echo "== Frontend build =="
# Vite build devDependencies ister; --omit=dev KULLANMAYIN. postinstall ikon CSS'ini üretir.
npm ci --no-audit --no-fund
npm run build

echo "== Laravel =="
"$PHP_BIN" artisan storage:link --force || true
"$PHP_BIN" artisan migrate --force
"$PHP_BIN" artisan optimize:clear
"$PHP_BIN" artisan config:cache
"$PHP_BIN" artisan route:cache
"$PHP_BIN" artisan view:cache
"$PHP_BIN" artisan event:cache

echo "== İzinler =="
chown -R "$WEB_USER":"$WEB_USER" storage bootstrap/cache public/build
chmod -R ug+rwX storage bootstrap/cache

echo "== Kuyruk / websocket süreçleri =="
"$PHP_BIN" artisan queue:restart || true
if command -v supervisorctl >/dev/null 2>&1; then
  supervisorctl restart esasgroup-reverb  2>/dev/null || echo "(supervisor 'esasgroup-reverb' tanımlı değil)"
  supervisorctl restart esasgroup-queue   2>/dev/null || echo "(supervisor 'esasgroup-queue' tanımlı değil)"
fi

"$PHP_BIN" artisan up
echo "== Deploy tamamlandı: $(date) =="
