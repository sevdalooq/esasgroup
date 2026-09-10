#!/bin/bash
# Yerel API sunucusu: `php artisan serve` -d ini ayarlarını iletmediği için yerleşik sunucu doğrudan başlatılır.
cd "$(dirname "$0")"
exec php -d upload_max_filesize=20M -d post_max_size=64M -d memory_limit=512M \
  -S localhost:${PORT:-8000} -t public vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php
