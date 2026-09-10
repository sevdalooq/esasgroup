#!/bin/bash
# Yerel API sunucusu: `php artisan serve` -d ini ayarlarını iletmediği için yerleşik sunucu doğrudan başlatılır.
cd "$(dirname "$0")/public"
exec env PHP_CLI_SERVER_WORKERS=4 php -d upload_max_filesize=20M -d post_max_size=64M -d memory_limit=512M \
  -S localhost:${PORT:-8000} ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php
