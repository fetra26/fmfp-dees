# ═══════════════════════════════════════════════════════════════════════
# Script : Configurer Apache XAMPP pour servir FMFP-DEES
# Résultat : http://fmfp.local → E:\2026\IT\06.Local_Projects_Tools\fmfp-dees\public
# ═══════════════════════════════════════════════════════════════════════

$ErrorActionPreference = 'Stop'
$vhosts   = 'C:\xampp\apache\conf\extra\httpd-vhosts.conf'
$hosts    = 'C:\Windows\System32\drivers\etc\hosts'
$project  = 'E:/2026/IT/06.Local_Projects_Tools/fmfp-dees'
$domaine  = 'fmfp.local'

Write-Host ""
Write-Host "═══ Configuration Apache XAMPP → FMFP-DEES ═══" -ForegroundColor Cyan
Write-Host ""

# ─── Vérifier admin (nécessaire pour hosts) ───
$admin = ([Security.Principal.WindowsPrincipal] `
    [Security.Principal.WindowsIdentity]::GetCurrent() `
).IsInRole([Security.Principal.WindowsBuiltInRole] 'Administrator')

if (-not $admin) {
    Write-Host "❌ Ce script doit être lancé en administrateur" -ForegroundColor Red
    Write-Host "   Cliquez droit → 'Exécuter en tant qu'administrateur'" -ForegroundColor Red
    exit 1
}

# ─── 1. Sauvegardes ───
if (-not (Test-Path "$vhosts.backup")) { Copy-Item $vhosts "$vhosts.backup" }
if (-not (Test-Path "$hosts.backup"))  { Copy-Item $hosts  "$hosts.backup" }
Write-Host "✔ Sauvegardes créées (.backup)" -ForegroundColor Green

# ─── 2. Ajouter le VirtualHost ───
$vhostBlock = @"

# ═══ FMFP-DEES ═══
<VirtualHost *:80>
    ServerName $domaine
    ServerAlias www.$domaine
    DocumentRoot "$project/public"

    <Directory "$project/public">
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog "logs/$domaine-error.log"
    CustomLog "logs/$domaine-access.log" combined

    php_admin_value memory_limit 512M
    php_admin_value max_execution_time 300
</VirtualHost>
"@

$vhostsContent = Get-Content $vhosts -Raw
if ($vhostsContent -notmatch "ServerName $domaine") {
    Add-Content $vhosts $vhostBlock
    Write-Host "✔ VirtualHost ajouté pour $domaine" -ForegroundColor Green
} else {
    Write-Host "ℹ VirtualHost $domaine déjà configuré" -ForegroundColor Yellow
}

# ─── 3. Ajouter dans C:\Windows\System32\drivers\etc\hosts ───
$hostsContent = Get-Content $hosts -Raw
if ($hostsContent -notmatch "\s$domaine") {
    Add-Content $hosts "`n127.0.0.1  $domaine`n127.0.0.1  www.$domaine`n"
    Write-Host "✔ Entrée hosts ajoutée : 127.0.0.1 → $domaine" -ForegroundColor Green
} else {
    Write-Host "ℹ Entrée hosts pour $domaine déjà présente" -ForegroundColor Yellow
}

# ─── 4. Message final ───
Write-Host ""
Write-Host "═══ Prochaine étape ═══" -ForegroundColor Cyan
Write-Host ""
Write-Host "1. Ouvrez XAMPP Control Panel" -ForegroundColor White
Write-Host "2. STOP Apache (s'il tourne)" -ForegroundColor White
Write-Host "3. START Apache" -ForegroundColor White
Write-Host ""
Write-Host "Puis accédez à :  http://$domaine/admin" -ForegroundColor Green -BackgroundColor Black
Write-Host ""
Write-Host "═══ Pour annuler ═══" -ForegroundColor Cyan
Write-Host "Copy-Item '$vhosts.backup' '$vhosts' -Force" -ForegroundColor Gray
Write-Host "Copy-Item '$hosts.backup'  '$hosts'  -Force" -ForegroundColor Gray
Write-Host ""
