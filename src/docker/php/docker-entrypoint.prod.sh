#!/usr/bin/env bash
# Entrypoint production MINIMAL. Yang TIDAK dilakukan file ini (sengaja,
# berbeda dari entrypoint dev): menulis ulang .env, create-project,
# composer install, npm install/build, seeding, toggle Xdebug.
#
# Urutan: tunggu DB sentral -> perbaiki ownership storage ->
# migration HANYA bila RUN_MIGRATIONS=1 -> storage:link -> optimize -> exec.
# Service queue/scheduler TIDAK diberi RUN_MIGRATIONS=1 di compose,
# jadi migration hanya pernah dijalankan service app.
set -e

cd /var/www/html

DB_HOST="${DB_HOST:-shared-db}"
DB_PORT="${DB_PORT:-3306}"

echo "[prod] Menunggu database ${DB_HOST}:${DB_PORT} ..."
php -r '
$host = getenv("DB_HOST") ?: "shared-db";
$port = (int) (getenv("DB_PORT") ?: 3306);
for ($i = 1; $i <= 30; $i++) {
    $s = @fsockopen($host, $port, $errno, $errstr, 2);
    if ($s) { fclose($s); exit(0); }
    fwrite(STDOUT, "[prod] DB belum siap (percobaan {$i}/30)\n");
    sleep(2);
}
fwrite(STDERR, "[prod] Timeout menunggu database. Keluar.\n");
exit(1);
'
echo "[prod] Database siap."

# File yang terlanjur dibuat root (mis. lewat exec manual) dikembalikan
# ke www-data, pola perbaikan yang sama seperti insiden log di dev.
mkdir -p storage/framework/cache storage/framework/sessions \
         storage/framework/views storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache || true
chmod -R 775 storage bootstrap/cache || true

# storage:link idempoten; abaikan kegagalan bila link sudah ada.
php artisan storage:link || true

if [ "${RUN_MIGRATIONS:-0}" = "1" ]; then
  echo "[prod] RUN_MIGRATIONS=1 — menjalankan migration ..."
  php artisan migrate --force
  php artisan optimize
else
  echo "[prod] RUN_MIGRATIONS tidak aktif — migration dilewati (mode queue/scheduler/web)."
fi

echo "[prod] Siap. Menjalankan: $*"
exec "$@"
