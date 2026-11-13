#!/usr/bin/env bash
# deploy.sh — deploy / update SATU app production (versi ROOT PROYEK,
# direvisi 13 Nov 2026). File ini tinggal di ROOT proyek infra di VPS
# (/opt/apps/<slug>) dan dijalankan dari sana; konteks build compose
# menunjuk ./src, .env production ada di src/.env.
# Urutan: cek prasyarat -> build image -> up -> tunggu web merespons ->
# verifikasi lokal lewat container web.
# TIDAK ada langkah destruktif di sini: tanpa down -v, tanpa migrate:fresh,
# tanpa menyentuh stack DB / proxy / app lain.
set -euo pipefail
cd "$(dirname "$0")"

COMPOSE="docker compose -f docker-compose.prod.yml"
ENV_FILE="src/.env"

if [[ ! -f "$ENV_FILE" ]]; then
  echo "src/.env tidak ada. Salin dari src/.env.prod.example, isi, lalu chmod 600 src/.env" >&2
  exit 1
fi

PERM="$(stat -c '%a' "$ENV_FILE")"
if [[ "$PERM" != "600" ]]; then
  echo "PERINGATAN: permission src/.env adalah $PERM, seharusnya 600. Memperbaiki..."
  chmod 600 "$ENV_FILE"
fi

if [[ ! -d src/public/build ]]; then
  echo "src/public/build TIDAK ada — frontend belum ter-build atau belum terkirim." >&2
  echo "Di laptop: cd src && npm run build, lalu paksa ikut ke repo" >&2
  echo "(folder ini ter-gitignore oleh Laravel): git add -f public/build, commit, push." >&2
  echo "Atau kirim terpisah lewat scp ke src/public/build di folder ini." >&2
  exit 1
fi

if [[ "$(docker inspect -f '{{.State.Running}}' shared-db 2>/dev/null || true)" != "true" ]]; then
  echo "shared-db tidak berjalan — nyalakan stack DB dulu (/opt/stacks/db)." >&2
  exit 1
fi

if [[ -d .git ]]; then
  echo "[deploy] Commit aktif: $(git rev-parse --short HEAD 2>/dev/null || echo '?')"
fi

echo "[deploy] Build image (frontend TIDAK di-build di sini)..."
$COMPOSE build

echo "[deploy] Menjalankan / memperbarui container app..."
$COMPOSE up -d

echo "[deploy] Menunggu service web merespons..."
for i in $(seq 1 30); do
  if $COMPOSE exec -T web wget -q -O /dev/null http://localhost/ 2>/dev/null; then
    echo "[deploy] Web lokal merespons."
    break
  fi
  if [[ "$i" == "30" ]]; then
    echo "[deploy] Web tidak merespons setelah 60 detik. Cek log:" >&2
    $COMPOSE logs --tail=50 app web >&2 || true
    exit 1
  fi
  sleep 2
done

echo "[deploy] Status akhir:"
$COMPOSE ps
echo "[deploy] Selesai. Verifikasi dari luar: buka https://<domain app ini> (lihat RUNBOOK Tahap 6)."
echo "[deploy] Log app: $COMPOSE logs -f app queue"
