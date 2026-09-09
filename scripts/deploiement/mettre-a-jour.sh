#!/bin/bash
# ═══════════════════════════════════════════════════════════════════════
# Script de MISE À JOUR FMFP-DEES
# À exécuter chaque fois qu'un nouveau commit doit être déployé.
# ═══════════════════════════════════════════════════════════════════════

set -euo pipefail

APP_DIR="/var/www/fmfp-dees"

GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

log()  { echo -e "${GREEN}✓${NC} $1"; }
warn() { echo -e "${YELLOW}⚠${NC} $1"; }
err()  { echo -e "${RED}✗${NC} $1" >&2; exit 1; }

[[ $EUID -ne 0 ]] && err "Ce script doit être lancé avec sudo"
[[ ! -d "$APP_DIR" ]] && err "Application non trouvée dans $APP_DIR"

cd "$APP_DIR"

echo ""
echo "═══════════════════════════════════════════════════════"
echo "  Mise à jour FMFP-DEES"
echo "═══════════════════════════════════════════════════════"
echo ""

# ─── 1. Activer le mode maintenance ───
log "Activation du mode maintenance..."
sudo -u www-data php artisan down --render="errors::503" --retry=60 2>/dev/null || true

# Fonction de rollback en cas d'erreur
trap 'sudo -u www-data php artisan up 2>/dev/null || true' ERR

# ─── 2. Backup DB avant migration (précaution) ───
log "Backup DB avant migration..."
BACKUP_FILE="/var/backups/fmfp-dees/pre-update-$(date +%Y%m%d-%H%M%S).sql.gz"
mkdir -p /var/backups/fmfp-dees
DB_USER=$(grep "^DB_USERNAME=" .env | cut -d '=' -f2-)
DB_PASS=$(grep "^DB_PASSWORD=" .env | cut -d '=' -f2- | tr -d '"')
DB_NAME=$(grep "^DB_DATABASE=" .env | cut -d '=' -f2-)
mysqldump -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" 2>/dev/null | gzip > "$BACKUP_FILE"
log "Backup : $BACKUP_FILE"

# ─── 3. git pull ───
log "Récupération des dernières modifications..."
sudo -u www-data git fetch --all
sudo -u www-data git reset --hard origin/main
sudo -u www-data git pull origin main

# ─── 4. Composer install ───
log "Mise à jour des dépendances PHP..."
mkdir -p /var/www/.composer && chown www-data:www-data /var/www/.composer
sudo -u www-data env COMPOSER_HOME=/var/www/.composer composer install --no-dev --optimize-autoloader --no-interaction

# ─── 5. Build assets ───
log "Build des assets..."
npm install --no-audit --no-fund --silent
npm run build
rm -rf node_modules

# ─── 6. Migrations ───
log "Exécution des migrations..."
sudo -u www-data php artisan migrate --force

# ─── 7. Vider tous les caches ───
log "Vidage des caches..."
sudo -u www-data php artisan optimize:clear

# ─── 8. Recompiler les caches production ───
log "Compilation des caches production..."
sudo -u www-data php artisan config:cache
sudo -u www-data php artisan route:cache
sudo -u www-data php artisan view:cache
sudo -u www-data php artisan event:cache
sudo -u www-data php artisan filament:cache-components
sudo -u www-data php artisan icons:cache

# ─── 9. Redémarrer les workers pour prendre en compte le nouveau code ───
log "Redémarrage des workers..."
supervisorctl restart fmfp-worker:* 2>/dev/null || true

# ─── 10. Recharger PHP-FPM (vider OPcache) ───
log "Rechargement PHP-FPM..."
systemctl reload php8.2-fpm

# ─── 11. Désactiver le mode maintenance ───
log "Sortie du mode maintenance..."
sudo -u www-data php artisan up

# Nettoyage du trap
trap - ERR

echo ""
echo "═══════════════════════════════════════════════════════"
echo "  ✔ Mise à jour terminée avec succès !"
echo "═══════════════════════════════════════════════════════"
echo ""
echo "  Commit déployé : $(git log -1 --pretty=format:'%h - %s (%an)')"
echo ""
