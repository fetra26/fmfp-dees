#!/bin/bash
# ═══════════════════════════════════════════════════════════════════════
# Script d'installation système pour FMFP-DEES sur Ubuntu Server 22.04 ou 24.04 LTS
# À exécuter avec sudo sur un serveur fraîchement installé.
# ═══════════════════════════════════════════════════════════════════════

set -euo pipefail

# Couleurs
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

log()  { echo -e "${GREEN}✓${NC} $1"; }
warn() { echo -e "${YELLOW}⚠${NC} $1"; }
err()  { echo -e "${RED}✗${NC} $1" >&2; exit 1; }

# Vérifier qu'on est root
if [[ $EUID -ne 0 ]]; then
    err "Ce script doit être lancé avec sudo"
fi

# ─── Vérifier la version d'Ubuntu (22.04 et 24.04 LTS validées) ───
UBUNTU_VERSION="$( . /etc/os-release && echo "${VERSION_ID:-inconnue}" )"
case "$UBUNTU_VERSION" in
    24.04)
        log "Ubuntu 24.04 LTS détecté — support jusqu'en avril 2029. Recommandé."
        ;;
    22.04)
        warn "Ubuntu 22.04 LTS : support standard jusqu'en avril 2027 seulement."
        warn "Pour un serveur neuf, préférez 24.04 LTS."
        ;;
    *)
        warn "Script validé pour Ubuntu 22.04 et 24.04 LTS. Détecté : $UBUNTU_VERSION"
        read -p "Continuer quand même ? (o/N) : " confirm
        # NB : if/fi et non « [[ ]] && exit », qui sous set -e faisait sortir le
        # script en cas de réponse « o » (la condition renvoyant 1).
        if [[ "$confirm" != "o" ]]; then
            exit 1
        fi
        ;;
esac

echo ""
echo "═══════════════════════════════════════════════════════"
echo "  Installation système pour FMFP-DEES"
echo "═══════════════════════════════════════════════════════"
echo ""

# ─── 1. Mise à jour système ───
log "Mise à jour du système..."
apt update && apt upgrade -y

# ─── 2. Paquets de base ───
log "Installation des paquets de base..."
apt install -y curl wget gnupg2 ca-certificates lsb-release apt-transport-https software-properties-common git zip unzip supervisor ufw

# ─── 3. PHP 8.2 via PPA Ondrej ───
log "Ajout du PPA PHP..."
add-apt-repository -y ppa:ondrej/php
apt update

log "Installation de PHP 8.2 + extensions Laravel/Filament..."
# NB : tokenizer et fileinfo ne sont PAS des paquets séparés sous Debian/Ubuntu,
# ils sont compilés dans le cœur de PHP. Les demander à apt faisait échouer tout
# le script (set -e) sur « Unable to locate package ». Ils sont vérifiés plus bas.
apt install -y \
    php8.2 php8.2-fpm php8.2-cli \
    php8.2-mysql php8.2-redis \
    php8.2-mbstring php8.2-xml php8.2-gd \
    php8.2-zip php8.2-curl php8.2-bcmath \
    php8.2-intl php8.2-opcache

# Imagick est facultatif (Filament s'appuie sur GD) : ne pas bloquer s'il manque.
apt install -y php8.2-imagick || warn "php8.2-imagick indisponible — ignoré (GD suffit)"

# ─── 3bis. Vérifier que les extensions sont réellement chargées ───
log "Vérification des extensions PHP requises..."
REQUISES=(pdo_mysql redis mbstring xml gd zip curl bcmath intl tokenizer fileinfo openssl)
MANQUANTES=""
for ext in "${REQUISES[@]}"; do
    php -m | grep -qix "$ext" || MANQUANTES="$MANQUANTES $ext"
done
php -m | grep -qi "opcache" || MANQUANTES="$MANQUANTES opcache"
if [[ -n "$MANQUANTES" ]]; then
    err "Extensions PHP manquantes :$MANQUANTES"
fi
log "Toutes les extensions requises sont chargées"

# ─── 4. Composer ───
log "Installation de Composer..."
EXPECTED_CHECKSUM="$(php -r 'copy("https://composer.github.io/installer.sig", "php://stdout");')"
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
ACTUAL_CHECKSUM="$(php -r "echo hash_file('sha384', 'composer-setup.php');")"
if [[ "$EXPECTED_CHECKSUM" != "$ACTUAL_CHECKSUM" ]]; then
    err "Checksum Composer invalide, installation annulée"
fi
php composer-setup.php --install-dir=/usr/local/bin --filename=composer
rm composer-setup.php

# ─── 5. Node.js 20 + npm ───
log "Installation de Node.js 20..."
curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
apt install -y nodejs

# ─── 6. MariaDB 10.11 ───
log "Installation de MariaDB..."
apt install -y mariadb-server mariadb-client

# ─── 7. Redis ───
log "Installation de Redis..."
apt install -y redis-server
# Configurer Redis en mode systemd (best practice)
sed -i -E "s/^supervised (no|auto)/supervised systemd/" /etc/redis/redis.conf

# ─── 8. Nginx ───
log "Installation de Nginx..."
apt install -y nginx

# ─── 9. Configurer PHP-FPM pour production ───
log "Optimisation PHP-FPM..."
PHP_INI="/etc/php/8.2/fpm/php.ini"
sed -i 's/^memory_limit = .*/memory_limit = 512M/' "$PHP_INI"
sed -i 's/^upload_max_filesize = .*/upload_max_filesize = 100M/' "$PHP_INI"
sed -i 's/^post_max_size = .*/post_max_size = 100M/' "$PHP_INI"
sed -i 's/^max_execution_time = .*/max_execution_time = 300/' "$PHP_INI"

# Pool www : optimiser pour Filament
POOL_CONF="/etc/php/8.2/fpm/pool.d/www.conf"
sed -i 's/^pm = .*/pm = dynamic/' "$POOL_CONF"
sed -i 's/^pm.max_children = .*/pm.max_children = 20/' "$POOL_CONF"
sed -i 's/^pm.start_servers = .*/pm.start_servers = 4/' "$POOL_CONF"
sed -i 's/^pm.min_spare_servers = .*/pm.min_spare_servers = 2/' "$POOL_CONF"
sed -i 's/^pm.max_spare_servers = .*/pm.max_spare_servers = 6/' "$POOL_CONF"

# ─── 10. Démarrer + activer les services ───
log "Activation des services au démarrage..."
systemctl enable nginx php8.2-fpm mariadb redis-server supervisor
systemctl restart nginx php8.2-fpm mariadb redis-server supervisor

# ─── 11. Vérifications ───
echo ""
echo "═══════════════════════════════════════════════════════"
echo "  Vérifications post-installation"
echo "═══════════════════════════════════════════════════════"
echo ""
php -v | head -1
nginx -v 2>&1
mysql --version
redis-cli ping && log "Redis répond"
composer --version | head -1
node -v
echo ""

log "Installation système terminée !"
echo ""
warn "PROCHAINES ÉTAPES :"
echo "  1. Sécuriser MariaDB : sudo mysql_secure_installation"
echo "  2. Créer la base fmfp_dees + utilisateur fmfp_user"
echo "  3. Cloner le repo dans /var/www/fmfp-dees"
echo "  4. Suivre la Phase 4 de DEPLOIEMENT.md"
echo ""
