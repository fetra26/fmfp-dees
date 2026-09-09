# 📦 Kit de déploiement FMFP-DEES

Ce dossier contient tout ce qu'il faut pour déployer FMFP-DEES sur Ubuntu Server 22.04.

## 📂 Contenu

```
scripts/deploiement/
├── README.md                          ← vous êtes ici
├── 01-install-server.sh               # Installation système (Nginx + PHP + MariaDB + Redis)
├── deployer.sh                        # Premier déploiement (git clone + config + migrate)
├── mettre-a-jour.sh                   # Mises à jour (git pull + cache + restart)
└── config/
    ├── nginx-fmfp-dees.conf           # Server block Nginx
    ├── opcache.ini                    # Config OPcache optimisée
    ├── supervisor-fmfp-worker.conf    # Workers de queue
    └── env.production.example         # Template .env
```

## 🚀 Ordre d'exécution (premier déploiement)

Sur un serveur Ubuntu 22.04 fraîchement installé :

```bash
# 1. Copier le script d'install (ou cloner temporairement le repo)
scp scripts/deploiement/01-install-server.sh fmfp@SERVEUR:~/

# 2. Sur le serveur, lancer l'install
ssh fmfp@SERVEUR
sudo bash 01-install-server.sh

# 3. Sécuriser MariaDB et créer la base
sudo mysql_secure_installation
sudo mysql -u root -p
```

```sql
CREATE DATABASE fmfp_dees CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'fmfp_user'@'localhost' IDENTIFIED BY 'CHANGEZ_CE_MDP';
GRANT ALL PRIVILEGES ON fmfp_dees.* TO 'fmfp_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

```bash
# 4. Cloner et déployer
sudo -u www-data git clone git@github.com:fetra26/fmfp-dees.git /var/www/fmfp-dees
sudo bash /var/www/fmfp-dees/scripts/deploiement/deployer.sh
```

## 🔄 Mise à jour (chaque nouveau déploiement)

```bash
sudo bash /var/www/fmfp-dees/scripts/deploiement/mettre-a-jour.sh
```

## 📖 Guide complet

Voir [DEPLOIEMENT.md](../../DEPLOIEMENT.md) à la racine du projet pour tous les détails.

## 🩺 Vérifications rapides

```bash
# Serveur répond
curl -I http://localhost

# OPcache actif ?
php -r "print_r(opcache_get_status(true) ? 'OK' : 'KO');"

# Redis actif ?
redis-cli ping

# Workers actifs ?
sudo supervisorctl status fmfp-worker:*

# Logs Laravel
tail -f /var/www/fmfp-dees/storage/logs/laravel-*.log
```
