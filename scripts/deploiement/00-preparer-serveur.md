# 🖥 Phase 0 — Créer le serveur FMFP-DEES

> Cette phase précède le `DEPLOIEMENT.md`, qui suppose un Ubuntu déjà installé et joignable en SSH.
> À la fin de ce document, vous aurez exactement ça — et vous pourrez enchaîner sur la Phase 1.

**Durée** : environ 1 h 30, dont ~40 min d'attente (téléchargement + installation).

---

## 1. Choisir la machine

Le serveur doit être une **machine dédiée qui reste allumée en permanence** — pas un poste de travail, pas un portable. Elle sera branchée au réseau local du FMFP en Ethernet filaire.

### Dimensionnement

L'application sert la DEES : une trentaine d'utilisateurs, dont rarement plus de dix simultanés. C'est une charge modeste, mais les imports Excel sont gourmands en mémoire (`memory_limit` est monté à 512 Mo, et un import peut mobiliser plusieurs centaines de Mo).

| | Minimum viable | Recommandé |
|---|---|---|
| Processeur | 2 cœurs x86-64 | 4 cœurs |
| RAM | 4 Go | **8 Go** |
| Disque | 60 Go SSD | 240 Go SSD, idéalement 2 disques en RAID 1 |
| Réseau | Ethernet 1 Gb/s | Ethernet 1 Gb/s |

Un ancien poste bureautique correct (Core i5 8ᵉ génération, 8 Go de RAM, SSD) fait parfaitement l'affaire et coûte zéro. Inutile d'acheter un serveur rack pour cette charge.

### Deux points à ne pas négliger

**Onduleur (UPS).** Une coupure franche pendant une écriture MariaDB peut corrompre la base. C'est le risque numéro un sur ce type de déploiement, et un onduleur d'entrée de gamme suffit à l'écarter. Prévoyez-le dès le départ, pas après le premier incident.

**Emplacement physique.** La machine héberge des données nominatives de bénéficiaires. Elle doit être dans un local fermé à clé, pas sous un bureau en open space.

---

## 2. Préparer le support d'installation

Depuis votre poste Windows :

1. Télécharger l'image **Ubuntu Server 24.04 LTS** (et non Desktop) : <https://ubuntu.com/download/server> — environ 2,5 Go.

   > **Pourquoi 24.04 et pas 22.04 ?** Le support standard de la 22.04 s'arrête en **avril 2027**. Pour une machine installée aujourd'hui, la 24.04 offre un support jusqu'en **avril 2029**. Le script `01-install-server.sh` gère les deux versions, mais choisissez la 24.04.

2. Télécharger **Rufus** (<https://rufus.ie>), gratuit et sans installation.

3. Insérer une clé USB d'au moins 4 Go — **son contenu sera effacé**. Dans Rufus : sélectionner la clé, choisir l'ISO téléchargée, laisser les options par défaut (GPT / UEFI), puis « Démarrer ».

4. Vérifier l'empreinte SHA256 de l'ISO avant de graver, depuis PowerShell :

   ```powershell
   Get-FileHash .\ubuntu-24.04-live-server-amd64.iso -Algorithm SHA256
   ```

   La comparer à celle publiée sur la page de téléchargement Ubuntu. Une ISO corrompue produit des erreurs d'installation incompréhensibles.

---

## 3. Installer Ubuntu Server

Démarrer la machine sur la clé USB (touche `F12`, `F9`, `Esc` ou `Suppr` selon le fabricant, pour ouvrir le menu de démarrage).

L'installateur pose une série de questions. Les réponses qui comptent :

| Étape | Réponse |
|---|---|
| Langue / clavier | Français (AZERTY) si le clavier physique l'est |
| Type d'installation | **Ubuntu Server** (pas « minimized ») |
| Réseau | Laisser en DHCP pour l'instant — on fixera l'IP à l'étape 4 |
| Proxy | Vide, sauf si le FMFP en impose un |
| Miroir | Laisser par défaut |
| Disque | **Use an entire disk** + cocher **Set up this disk as an LVM group** |
| Nom du serveur | `fmfp-dees` |
| Nom d'utilisateur | `fmfp` (c'est le compte sudoer que suppose la documentation) |
| Mot de passe | Fort, et **noté dans le gestionnaire de mots de passe du FMFP** |
| **Install OpenSSH server** | ☑ **À COCHER** — sans ça, aucun accès à distance |
| Snaps proposés | **N'en cocher aucun** |

> **Sur LVM** : ça ne change rien à l'usage quotidien, mais le jour où le disque sature, agrandir une partition LVM prend deux commandes au lieu d'une réinstallation. C'est cinq secondes de clic maintenant contre une soirée perdue plus tard.

L'installation dure 15 à 30 min. À la fin, retirer la clé USB et redémarrer.

---

## 4. Fixer l'adresse IP

Le serveur doit garder la même adresse en permanence, sinon les postes ne le retrouveront plus.

Se connecter sur la machine (écran + clavier) avec le compte `fmfp`, puis relever le nom de l'interface réseau et la passerelle :

```bash
ip -brief address        # ex. « enp3s0 » avec 192.168.100.57/24
ip route | grep default  # ex. « default via 192.168.100.1 »
```

**Choisir une IP libre hors de la plage DHCP de votre routeur** — sinon le routeur pourra l'attribuer à un autre poste et provoquer un conflit. En cas de doute, demandez au responsable réseau du FMFP, ou réservez l'adresse par DHCP à partir de l'adresse MAC (souvent le plus simple et le plus robuste).

Éditer la configuration réseau (adapter le nom de fichier si besoin, `ls /etc/netplan/`) :

```bash
sudo nano /etc/netplan/50-cloud-init.yaml
```

```yaml
network:
  version: 2
  ethernets:
    enp3s0:                          # ← le nom relevé plus haut
      dhcp4: no
      addresses:
        - 192.168.100.50/24          # ← l'IP fixe choisie
      routes:
        - to: default
          via: 192.168.100.1         # ← la passerelle relevée plus haut
      nameservers:
        addresses: [192.168.100.1, 8.8.8.8]
```

Appliquer :

```bash
sudo chmod 600 /etc/netplan/50-cloud-init.yaml   # netplan refuse les fichiers lisibles par tous
sudo netplan apply
ip -brief address                                 # vérifier la nouvelle IP
ping -c3 8.8.8.8                                  # vérifier l'accès Internet (nécessaire pour apt)
```

> ⚠️ **Attention à l'indentation YAML** : deux espaces par niveau, jamais de tabulation. Une erreur ici coupe le réseau du serveur, et il faudra corriger au clavier physique.

---

## 5. Vérifier l'accès SSH depuis votre poste

Depuis PowerShell sur votre poste Windows :

```powershell
ssh fmfp@192.168.100.50
```

Si la connexion aboutit, le serveur est prêt et **toute la suite se fait à distance**, sans retourner devant la machine.

### Recommandé : authentification par clé

Plus sûr et plus commode qu'un mot de passe tapé à chaque connexion.

```powershell
ssh-keygen -t ed25519 -C "poste-admin-dees"     # valider les 3 questions par Entrée
type $env:USERPROFILE\.ssh\id_ed25519.pub | ssh fmfp@192.168.100.50 "mkdir -p ~/.ssh && chmod 700 ~/.ssh && cat >> ~/.ssh/authorized_keys && chmod 600 ~/.ssh/authorized_keys"
```

Reconnectez-vous pour confirmer que la clé fonctionne **avant** de désactiver quoi que ce soit.

---

## 6. Mettre à jour, puis enchaîner

```bash
sudo apt update && sudo apt upgrade -y
sudo reboot
```

Le serveur est prêt. Vous pouvez maintenant :

1. Récupérer les scripts — soit `git clone` du dépôt si vous l'avez déjà poussé, soit copie manuelle du dossier `scripts/deploiement/` via `scp` ;
2. Lancer `sudo ./01-install-server.sh` (Nginx, PHP 8.2, MariaDB, Redis, Composer, Node, Supervisor) ;
3. Reprendre le [`DEPLOIEMENT.md`](../../DEPLOIEMENT.md) à partir de la **Phase 2** (configuration MariaDB).

---

## 7. Récapitulatif à conserver

Notez ces informations dans le gestionnaire de mots de passe du FMFP — elles seront nécessaires à chaque intervention :

| Élément | Valeur |
|---|---|
| IP fixe du serveur | `192.168.100.__` |
| Nom d'hôte | `fmfp-dees` |
| Utilisateur sudoer | `fmfp` |
| Mot de passe sudoer | *(gestionnaire de mots de passe)* |
| Mot de passe root MariaDB | *(défini en Phase 2)* |
| Mot de passe `fmfp_user` MariaDB | *(défini en Phase 2)* |
| Compte admin applicatif | *(défini via `INITIAL_ADMIN_*` dans le `.env`)* |
