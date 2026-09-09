# ═══════════════════════════════════════════════════════════════════════
# Script : Activer OPcache dans XAMPP PHP
# Usage  : Clic droit → "Exécuter avec PowerShell"
#          OU dans terminal : powershell -File .\scripts\activer_opcache.ps1
# ═══════════════════════════════════════════════════════════════════════

$ErrorActionPreference = 'Stop'
$phpIni = 'C:\xampp\php\php.ini'
$backup = 'C:\xampp\php\php.ini.backup-avant-opcache'

Write-Host ""
Write-Host "═══ Activation OPcache pour XAMPP PHP ═══" -ForegroundColor Cyan
Write-Host ""

# ─── 1. Vérifier que php.ini existe ───
if (-not (Test-Path $phpIni)) {
    Write-Host "❌ Fichier introuvable : $phpIni" -ForegroundColor Red
    Write-Host "   Vérifiez que XAMPP est installé dans C:\xampp" -ForegroundColor Red
    exit 1
}

# ─── 2. Créer sauvegarde si pas encore faite ───
if (-not (Test-Path $backup)) {
    Copy-Item $phpIni $backup
    Write-Host "✔ Sauvegarde créée : $backup" -ForegroundColor Green
} else {
    Write-Host "ℹ Sauvegarde déjà existante : $backup" -ForegroundColor Yellow
}

# ─── 3. Lire le contenu ───
$content = Get-Content $phpIni -Raw

# ─── 4. Décommenter zend_extension=opcache ───
if ($content -match '(?m)^;zend_extension=opcache$') {
    $content = $content -replace '(?m)^;zend_extension=opcache$', 'zend_extension=opcache'
    Write-Host "✔ Ligne 'zend_extension=opcache' décommentée" -ForegroundColor Green
} elseif ($content -match '(?m)^zend_extension=opcache$') {
    Write-Host "ℹ 'zend_extension=opcache' déjà activé" -ForegroundColor Yellow
} else {
    Write-Host "⚠ Ligne 'zend_extension=opcache' introuvable — sera ajoutée" -ForegroundColor Yellow
}

# ─── 5. Ajouter le bloc de config OPcache s'il n'existe pas ───
if ($content -notmatch '(?m)^\[opcache\]') {
    $opcacheBlock = @"

; ═══════════════════════════════════════════════
; OPcache — activé par script FMFP-DEES le $(Get-Date -Format 'yyyy-MM-dd')
; ═══════════════════════════════════════════════
[opcache]
opcache.enable=1
opcache.enable_cli=0
opcache.memory_consumption=256
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=20000
opcache.revalidate_freq=2
opcache.validate_timestamps=1
opcache.save_comments=1
opcache.jit_buffer_size=100M
opcache.jit=1255

"@
    $content += $opcacheBlock
    Write-Host "✔ Bloc [opcache] ajouté avec config optimale (256Mo, 20000 fichiers, JIT activé)" -ForegroundColor Green
} else {
    Write-Host "ℹ Bloc [opcache] déjà présent" -ForegroundColor Yellow
}

# ─── 6. Écrire le fichier ───
Set-Content $phpIni $content -NoNewline
Write-Host "✔ php.ini mis à jour" -ForegroundColor Green

Write-Host ""
Write-Host "═══ Prochaine étape ═══" -ForegroundColor Cyan
Write-Host ""
Write-Host "1. Ouvrez XAMPP Control Panel" -ForegroundColor White
Write-Host "2. STOP Apache (s'il tourne)" -ForegroundColor White
Write-Host "3. START Apache" -ForegroundColor White
Write-Host ""
Write-Host "OU redémarrez `artisan serve` :" -ForegroundColor White
Write-Host "   Ctrl+C dans le terminal puis php artisan serve" -ForegroundColor Gray
Write-Host ""
Write-Host "Test OPcache actif : http://localhost/dashboard/phpinfo.php" -ForegroundColor White
Write-Host "   ou : php -r `"echo function_exists('opcache_get_status') ? 'OPcache OK' : 'KO';`"" -ForegroundColor Gray
Write-Host ""
Write-Host "═══ Pour annuler ═══" -ForegroundColor Cyan
Write-Host "Copier-coller :  Copy-Item '$backup' '$phpIni' -Force" -ForegroundColor Gray
Write-Host ""
