#!/bin/bash
# ═══════════════════════════════════════════════════════════════════════
# Script de déploiement INITIAL FMFP-DEES
# À exécuter APRÈS install-server.sh + création base MariaDB
# ═══════════════════════════════════════════════════════════════════════

set -euo pipefail

APP_DIR="/var/www/fmfp-dees"
REPO_URL="${REPO_URL:-https://github.com/VOTRE-ORG/fmfp-dees.git}"

GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

log()  { echo -e "${GREEN}✓${NC} $1"; }
warn() { echo -e "${YELLOW}⚠${NC} $1"; }
err()  { echo -e "${RED}✗${NC} $1" >&2; exit 1; }

[[ $EUID -ne 0 ]] && err "Ce script doit être lancé avec sudo"

echo ""
echo "═══════════════════════════════════════════════════════"
echo "  Déploiement initial FMFP-DEES"
echo "═══════════════════════════════════════════════════════"
echo ""

# ─── 1. Cloner le repo ───
if [[ -d "$APP_DIR" ]]; then
    warn "Dossier $APP_DIR existe déjà, on skip le clone"
else
    log "Clonage du repo Git..."
    read -p "URL du repo Git (défaut: $REPO_URL) : " user_url
    REPO_URL="${user_url:-$REPO_URL}"
    git clone "$REPO_URL" "$APP_DIR"
fi

cd "$APP_DIR"

# ─── 2. Configurer .env ───
if [[ ! -f .env ]]; then
    log "Copie du .env de production..."
    cp scripts/deploiement/config/env.production.example .env

    warn "OUVREZ le .env pour éditer DB_PASSWORD et autres :"
    echo "   sudo nano $APP_DIR/.env"
    read -p "Appuyez sur Entrée quand c'est fait..."
fi

# ─── 3. Permissions ───
log "Configuration des permissions..."
chown -R www-data:www-data "$APP_DIR"
chmod -R 755 "$APP_DIR"
chmod -R 775 "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"

# ─── 4. Composer install ───
log "Installation des dépendances PHP..."
mkdir -p /var/www/.composer && chown www-data:www-data /var/www/.composer
sudo -u www-data env COMPOSER_HOME=/var/www/.composer composer install --no-dev --optimize-autoloader --no-interaction

# ─── 5. Générer la clé si absente ───
if ! grep -q "^APP_KEY=base64:" .env; then
    log "Génération de APP_KEY..."
    sudo -u www-data php artisan key:generate --force
fi

# ─── 6. Build assets ───
log "Installation Node + build assets..."
npm install --no-audit --no-fund
npm run build
rm -rf node_modules

# ─── 7. Migrations + seeders ───
log "Exécution des migrations..."
sudo -u www-data php artisan migrate --force

log "Seeder rôles + super admin..."
sudo -u www-data php artisan db:seed --class=RolesAndPermissionsSeeder --force
sudo -u www-data php artisan db:seed --class=ProductionSeeder --force

# ─── 8. Storage link ───
log "Création du lien storage..."
sudo -u www-data php artisan storage:link

# ─── 9. Cache production ───
log "Cache production..."
sudo -u www-data php artisan optimize:clear
sudo -u www-data php artisan config:cache
sudo -u www-data php artisan route:cache
sudo -u www-data php artisan view:cache
sudo -u www-data php artisan event:cache
sudo -u www-data php artisan filament:cache-components
sudo -u www-data php artisan icons:cache

# ─── 10. Copier les configs système ───
log "Installation des configs système..."
cp scripts/deploiement/config/nginx-fmfp-dees.conf /etc/nginx/sites-available/fmfp-dees
[[ ! -L /etc/nginx/sites-enabled/fmfp-dees ]] && ln -s /etc/nginx/sites-available/fmfp-dees /etc/nginx/sites-enabled/
[[ -L /etc/nginx/sites-enabled/default ]] && rm /etc/nginx/sites-enabled/default

cp scripts/deploiement/config/opcache.ini /etc/php/8.2/mods-available/opcache.ini
cp scripts/deploiement/config/supervisor-fmfp-worker.conf /etc/supervisor/conf.d/fmfp-worker.conf

# ─── 11. Tester + recharger Nginx ───
log "Vérification config Nginx..."
nginx -t || err "Config Nginx invalide, corrigez avant de continuer"

log "Redémarrage des services..."
systemctl restart nginx php8.2-fpm

# ─── 12. Supervisor : démarrer les workers ───
log "Démarrage des workers..."
supervisorctl reread
supervisorctl update
supervisorctl start fmfp-worker:* 2>/dev/null || true

# ─── 13. Cron ───
log "Configuration du cron scheduler..."
CRON_LINE="* * * * * cd $APP_DIR && php artisan schedule:run >> /dev/null 2>&1"
(crontab -u www-data -l 2>/dev/null | grep -v "schedule:run" ; echo "$CRON_LINE") | crontab -u www-data -

echo ""
echo "═══════════════════════════════════════════════════════"
echo "  ✔ Déploiement terminé !"
echo "═══════════════════════════════════════════════════════"
echo ""
IP=$(hostname -I | awk '{print $1}')
echo "  🌐 Application accessible sur :"
echo "     http://fmfp-dees.local (si DNS/hosts configuré)"
echo "     http://$IP"
echo ""
echo "  👤 Compte Super Admin par défaut :"
echo "     Email : admin@fmfp.mg"
echo "     MDP   : ChangezMoiTresVite!2026"
echo ""
warn "  ⚠️  CHANGEZ IMMÉDIATEMENT LE MOT DE PASSE via /admin/utilisateurs"
echo ""
