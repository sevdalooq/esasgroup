#!/bin/bash
# Tüm yerel servisleri başlatır: Docker altyapısı, websocket (Reverb), API sunucusu ve Vite.
# Kullanım: ./dev.sh   (durdurmak için Ctrl+C)
cd "$(dirname "$0")"
docker compose up -d
pkill -9 -f "reverb:start" 2>/dev/null
pkill -f "resources/server.php" 2>/dev/null
pkill -f "artisan serve" 2>/dev/null
sleep 1
php artisan reverb:start --port=8081 > storage/logs/reverb.log 2>&1 &
REVERB=$!
./serve.sh > storage/logs/serve.log 2>&1 &
SERVE=$!
# Android emülatörü / telefon için IPv4'e açık ikinci örnek (10.0.2.2:8020)
HOST=0.0.0.0 PORT=8020 ./serve.sh > storage/logs/serve-mobile.log 2>&1 &
SERVE_MOBILE=$!
npm run dev > storage/logs/vite.log 2>&1 &
VITE=$!
sleep 4
echo "Panel:        http://localhost:8000"
echo "Canlı izleme: http://localhost:8000/canli"
echo "Saha:         http://localhost:8000/saha"
echo "Mobil API:    http://10.0.2.2:8020 (emülatör)  http://<bilgisayar-ip>:8020 (telefon)"
echo "Reverb:       ws://localhost:8081 (pid $REVERB)   API pid $SERVE   Vite pid $VITE"
echo "Loglar: storage/logs/{reverb,serve,vite}.log — durdurmak için Ctrl+C"
trap 'kill $REVERB $SERVE $SERVE_MOBILE $VITE 2>/dev/null; exit 0' INT TERM
wait
