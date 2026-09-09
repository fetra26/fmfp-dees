# 🚀 Déploiement FMFP-DEES sur serveur dédié

**Cible** : Ubuntu Server 24.04 LTS (22.04 encore supporté), réseau LAN interne FMFP.

**Stack final** :
- Ubuntu Server 24.04 LTS
- Nginx 1.18 (reverse proxy + serveur statique)
- PHP-FPM 8.2 + OPcache + JIT
- MariaDB 10.11
- Redis 7 (cache + session + queue)
- Supervisor (workers de queue)
- Git (déploiement + updates)

**Résultat attendu** : app accessible à `http://fmfp-dees.local` ou `http://<IP-serveur>` depuis n'importe quel poste du réseau interne.

---

## 📋 Prérequis

- **Le serveur existe déjà**, Ubuntu installé, à jour et joignable en SSH. Si ce n'est pas le cas, commencer par la [Phase 0 — Créer le serveur](scripts/deploiement/00-preparer-serveur.md) : choix de la machine, installation d'Ubuntu, IP fixe, accès SSH.
- Compte utilisateur sudoer (ex: `fmfp`)
- Accès SSH depuis un poste admin (ou console directe)
- IP fixe attribuée au serveur (via DHCP réservation ou config manuelle)
- Repo Git accessible (GitHub, GitLab, ou serveur Git interne)

---

## 🎯 Vue d'ensemble en 7 phases

```
Phase 0 : Créer le serveur (voir 00-preparer-serveur.md)  (~1 h 30, 1 seule fois)
Phase 1 : Installation du système       (~15 min, à faire 1 seule fois)
Phase 2 : Configuration MariaDB         (~5 min)
Phase 3 : Configuration Nginx + PHP      (~10 min)
Phase 4 : Premier déploiement du code   (~10 min)
Phase 5 : Post-déploiement (cron, backup, monitoring)
Phase 6 : Utilisation quotidienne (updates)
```

---

## 📦 Phase 1 — Installation du système

Ouvrez un terminal SSH sur le serveur :

```bash
# Se placer dans le home
cd ~

# Télécharger le script d'installation
wget https://raw.githubusercontent.com/VOTRE-ORG/fmfp-dees/main/scripts/deploiement/01-install-server.sh
# OU si le code n'est pas encore sur Git : copier le script manuellement

chmod +x 01-install-server.sh
sudo ./01-install-server.sh
```

Ce script installe :
- Nginx
- PHP 8.2 + toutes les extensions (mbstring, xml, mysql, redis, gd, zip, curl, bcmath, intl, opcache, jit)
- MariaDB 10.11
- Redis 7
- Composer 2
- Node.js 20 + npm
- Supervisor
- Git

Vérification post-install :
```bash
php -v          # Doit afficher PHP 8.2.x
nginx -v        # nginx/1.18+
mysql --version # mariadb 10.11+
redis-cli ping  # PONG
composer --version # Composer 2.x
node -v         # v20.x
```

---

## 🗄️ Phase 2 — Configuration MariaDB

```bash
# Sécuriser MariaDB
sudo mysql_secure_installation
# Répondre :
#   Enter current password: [Enter, vide au départ]
#   Switch to unix_socket: n
#   Change root password: Y → mot de passe fort
#   Remove anonymous users: Y
#   Disallow root login remotely: Y
#   Remove test database: Y
#   Reload privileges: Y

# Créer la base + l'utilisateur applicatif
sudo mysql -u root -p
```

Dans le shell MariaDB :
```sql
CREATE DATABASE fmfp_dees CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'fmfp_user'@'localhost' IDENTIFIED BY 'MOT_DE_PASSE_TRES_FORT_ICI';
GRANT ALL PRIVILEGES ON fmfp_dees.* TO 'fmfp_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

**⚠️ Notez** le mot de passe `fmfp_user`, vous en aurez besoin pour le `.env`.

---

## 🌐 Phase 3 — Configuration Nginx + PHP

### 3.1 Copier les fichiers de config

Les configs sont dans `scripts/deploiement/config/` du projet. Copier :

```bash
# Server block Nginx
sudo cp /var/www/fmfp-dees/scripts/deploiement/config/nginx-fmfp-dees.conf /etc/nginx/sites-available/fmfp-dees

# Créer le lien d'activation
sudo ln -s /etc/nginx/sites-available/fmfp-dees /etc/nginx/sites-enabled/

# Désactiver le site par défaut
sudo rm /etc/nginx/sites-enabled/default

# Config OPcache (essentiel pour perfs)
sudo cp /var/www/fmfp-dees/scripts/deploiement/config/opcache.ini /etc/php/8.2/mods-available/opcache.ini
```

### 3.2 Tester + redémarrer

```bash
sudo nginx -t                   # Doit afficher "syntax is ok"
sudo systemctl restart nginx
sudo systemctl restart php8.2-fpm
sudo systemctl enable nginx php8.2-fpm mariadb redis-server
```

---

## 🚀 Phase 4 — Premier déploiement du code

### 4.1 Créer le dossier et cloner

```bash
# Créer le dossier applicatif
sudo mkdir -p /var/www
cd /var/www

# Cloner le repo (remplacer par votre URL Git)
sudo git clone https://github.com/VOTRE-ORG/fmfp-dees.git

# Alternative : si Git privé avec token
# sudo git clone https://TOKEN@github.com/VOTRE-ORG/fmfp-dees.git

# Rendre l'utilisateur www-data propriétaire
sudo chown -R www-data:www-data /var/www/fmfp-dees
sudo chmod -R 755 /var/www/fmfp-dees
sudo chmod -R 775 /var/www/fmfp-dees/storage /var/www/fmfp-dees/bootstrap/cache
```

### 4.2 Configurer le `.env`

```bash
cd /var/www/fmfp-dees
sudo cp scripts/deploiement/config/env.production.example .env
sudo nano .env
```

Modifier au minimum :
```env
APP_NAME="FMFP-DEES"
APP_ENV=production
APP_DEBUG=false
APP_URL=http://fmfp-dees.local

DB_DATABASE=fmfp_dees
DB_USERNAME=fmfp_user
DB_PASSWORD=MOT_DE_PASSE_MARIADB_ICI

SESSION_DRIVER=redis
CACHE_STORE=redis
QUEUE_CONNECTION=redis
```

Puis :
```bash
sudo -u www-data php artisan key:generate
```

### 4.3 Installer les dépendances

```bash
cd /var/www/fmfp-dees

# Composer sans dev, optimisé
sudo -u www-data composer install --no-dev --optimize-autoloader --no-interaction

# Node + build assets (peut prendre 2-3 min)
sudo npm install --production=false
sudo npm run build

# Nettoyer node_modules (pas nécessaire en prod)
sudo rm -rf node_modules
```

### 4.4 Migrer + seeder

```bash
# Migration DB
sudo -u www-data php artisan migrate --force

# Seeder rôles/permissions + Super Admin uniquement
sudo -u www-data php artisan db:seed --class=RolesAndPermissionsSeeder --force
sudo -u www-data php artisan db:seed --class=ProductionSeeder --force

# Lien symbolique pour storage
sudo -u www-data php artisan storage:link
```

### 4.5 Cache production

```bash
sudo -u www-data php artisan config:cache
sudo -u www-data php artisan route:cache
sudo -u www-data php artisan view:cache
sudo -u www-data php artisan event:cache
sudo -u www-data php artisan filament:cache-components
sudo -u www-data php artisan icons:cache
```

### 4.6 Ajouter le nom d'hôte dans le DNS local (ou hosts)

**Option A** : sur chaque poste utilisateur, ajouter dans `C:\Windows\System32\drivers\etc\hosts` (Windows) ou `/etc/hosts` (Linux) :
```
192.168.X.Y    fmfp-dees.local
```
(remplacer par l'IP réelle du serveur)

**Option B** (mieux) : configurer une entrée DNS sur votre routeur/serveur DNS interne.

### 4.7 Tester

Depuis un poste du LAN, ouvrez `http://fmfp-dees.local` — vous devriez voir la page de connexion.

**Compte Super Admin par défaut créé par ProductionSeeder** :
- Email : `admin@fmfp.mg`
- Mot de passe : `ChangezMoiTresVite!2026` ← **CHANGEZ CE MOT DE PASSE IMMÉDIATEMENT** via `/admin/utilisateurs`

---

## 🔧 Phase 5 — Post-déploiement

### 5.1 Cron pour le scheduler Laravel

```bash
sudo crontab -e -u www-data
```

Ajouter :
```
* * * * * cd /var/www/fmfp-dees && php artisan schedule:run >> /dev/null 2>&1
```

### 5.2 Supervisor pour les queue workers

```bash
sudo cp /var/www/fmfp-dees/scripts/deploiement/config/supervisor-fmfp-worker.conf /etc/supervisor/conf.d/fmfp-worker.conf
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start fmfp-worker:*
sudo supervisorctl status
```

### 5.3 Backup DB automatique

```bash
# Créer le dossier de backup
sudo mkdir -p /var/backups/fmfp-dees
sudo chown www-data:www-data /var/backups/fmfp-dees

# Ajouter au cron root
sudo crontab -e
```

Ajouter :
```
# Backup DB tous les jours à 2h du matin
0 2 * * * mysqldump -u fmfp_user -p'MOT_DE_PASSE' fmfp_dees | gzip > /var/backups/fmfp-dees/backup-$(date +\%Y\%m\%d).sql.gz

# Garder 30 jours d'historique
0 3 * * * find /var/backups/fmfp-dees -name "backup-*.sql.gz" -mtime +30 -delete
```

### 5.4 Firewall UFW (recommandé)

```bash
sudo ufw allow OpenSSH
sudo ufw allow 'Nginx Full'
sudo ufw enable
sudo ufw status
```

### 5.5 Rotation des logs

Laravel gère déjà la rotation via `LOG_CHANNEL=daily` dans le `.env`. Vérifiez que `LOG_DAILY_DAYS=14` (2 semaines de log) dans le `.env`.

---

## 🔄 Phase 6 — Utilisation quotidienne : mises à jour

Quand vous poussez des changements sur Git :

```bash
cd /var/www/fmfp-dees
sudo /var/www/fmfp-dees/scripts/deploiement/mettre-a-jour.sh
```

Ce script fait :
1. `git pull`
2. `composer install --no-dev --optimize-autoloader`
3. `npm install && npm run build`
4. `php artisan migrate --force`
5. `php artisan config:cache && route:cache && view:cache && filament:cache-components`
6. `sudo supervisorctl restart fmfp-worker:*`
7. `sudo systemctl reload nginx php8.2-fpm`

---

## 🩺 Vérifications de santé

### Le serveur répond-il ?
```bash
curl -I http://localhost
# HTTP/1.1 200 OK attendu
```

### OPcache actif ?
```bash
php -r "echo function_exists('opcache_get_status') ? 'OK' : 'KO';"
```

### Queue worker actif ?
```bash
sudo supervisorctl status fmfp-worker:*
```

### Logs Laravel
```bash
tail -f /var/www/fmfp-dees/storage/logs/laravel.log
```

### Logs Nginx
```bash
tail -f /var/log/nginx/fmfp-dees-error.log
tail -f /var/log/nginx/fmfp-dees-access.log
```

---

## 🚨 Dépannage courant

| Symptôme | Cause | Solution |
|---|---|---|
| Page blanche / 500 | `.env` mal configuré | Vérifier `APP_KEY`, permissions storage/ |
| "Failed to open database" | MDP DB incorrect | Vérifier `DB_PASSWORD` dans `.env` |
| CSS/JS cassé | Assets pas compilés | `npm run build` puis recharger |
| Session déconnecte | Redis inactif | `sudo systemctl restart redis-server` |
| Lent (>5s) | OPcache désactivé | Vérifier `/etc/php/8.2/mods-available/opcache.ini` |
| Filament cache vieux | Non re-cached après update | `php artisan filament:cache-components` |

---

## 📚 Ressources

- Laravel Deployment : https://laravel.com/docs/deployment
- Filament Deployment : https://filamentphp.com/docs/deployment
- Nginx tuning : https://www.nginx.com/blog/tuning-nginx/
