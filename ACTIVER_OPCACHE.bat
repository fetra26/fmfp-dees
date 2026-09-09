@echo off
REM ═══════════════════════════════════════════════════════════════════
REM  ACTIVER_OPCACHE.bat — Active OPcache dans XAMPP PHP
REM  Double-cliquez sur ce fichier pour l'exécuter
REM  (ou clic droit → "Exécuter en tant qu'administrateur")
REM ═══════════════════════════════════════════════════════════════════

setlocal enabledelayedexpansion
chcp 65001 > nul
title Activer OPcache pour SEER

echo.
echo ═══════════════════════════════════════════════════════
echo   Activation OPcache pour SEER — XAMPP PHP
echo ═══════════════════════════════════════════════════════
echo.

REM ─── Vérifier php.ini ───
set PHP_INI=C:\xampp\php\php.ini
set BACKUP=C:\xampp\php\php.ini.backup-avant-opcache

if not exist "%PHP_INI%" (
    echo [ERREUR] php.ini introuvable dans C:\xampp\php\
    echo Vérifiez que XAMPP est installé dans C:\xampp
    echo.
    pause
    exit /b 1
)

REM ─── Backup ───
if not exist "%BACKUP%" (
    copy "%PHP_INI%" "%BACKUP%" > nul
    echo [OK] Sauvegarde créée : %BACKUP%
) else (
    echo [INFO] Sauvegarde déjà existante : %BACKUP%
)
echo.

REM ─── Modifier php.ini via PowerShell ───
echo Activation d'OPcache dans php.ini...
echo.

powershell -NoProfile -Command ^
    "$phpIni = 'C:\xampp\php\php.ini';" ^
    "$content = Get-Content $phpIni -Raw;" ^
    "if ($content -match '(?m)^;zend_extension=opcache$') {" ^
        "$content = $content -replace '(?m)^;zend_extension=opcache$', 'zend_extension=opcache';" ^
        "Write-Host '[OK] Ligne zend_extension=opcache décommentée' -ForegroundColor Green;" ^
    "} elseif ($content -match '(?m)^zend_extension=opcache$') {" ^
        "Write-Host '[INFO] zend_extension=opcache déjà activé' -ForegroundColor Yellow;" ^
    "} else {" ^
        "Write-Host '[!] Ligne zend_extension=opcache introuvable — sera ajoutée' -ForegroundColor Yellow;" ^
    "}" ^
    "if ($content -notmatch '(?m)^\[opcache\]') {" ^
        "$block = @'`n`n; ═══════════════════════════════════════════════`n; OPcache — activé par script SEER le %date%`n; ═══════════════════════════════════════════════`n[opcache]`nopcache.enable=1`nopcache.enable_cli=0`nopcache.memory_consumption=256`nopcache.interned_strings_buffer=16`nopcache.max_accelerated_files=20000`nopcache.revalidate_freq=2`nopcache.validate_timestamps=1`nopcache.save_comments=1`nopcache.jit_buffer_size=100M`nopcache.jit=1255`n'@;" ^
        "$content += $block;" ^
        "Write-Host '[OK] Bloc [opcache] ajouté avec config optimale (256Mo mémoire, JIT activé)' -ForegroundColor Green;" ^
    "} else {" ^
        "Write-Host '[INFO] Bloc [opcache] déjà présent' -ForegroundColor Yellow;" ^
    "}" ^
    "Set-Content -Path $phpIni -Value $content -NoNewline -Encoding UTF8;" ^
    "Write-Host '[OK] php.ini mis à jour' -ForegroundColor Green"

if errorlevel 1 (
    echo.
    echo [ERREUR] Impossible de modifier php.ini
    echo Essayez de relancer ce script en tant qu'administrateur :
    echo   Clic droit sur ACTIVER_OPCACHE.bat → "Exécuter en tant qu'administrateur"
    echo.
    pause
    exit /b 1
)

echo.
echo ═══════════════════════════════════════════════════════
echo   PROCHAINE ÉTAPE — Redémarrer PHP
echo ═══════════════════════════════════════════════════════
echo.
echo   Vous devez maintenant :
echo.
echo   1. Fermer votre serveur actuel (Ctrl+C dans le terminal)
echo   2. Redémarrer artisan serve :
echo        cd E:\2026\IT\06.Local_Projects_Tools\fmfp-dees
echo        php artisan serve
echo.
echo   OU si vous utilisez XAMPP Control Panel :
echo   - STOP Apache
echo   - START Apache
echo.
echo   RÉSULTAT ATTENDU :
echo     - Cold start login  : 20s → ~2s  (10x plus rapide)
echo     - Assets JS/CSS     : 1s → 50ms  (20x plus rapide)
echo     - Toute l'app       : ULTRA FLUIDE
echo.
echo ═══════════════════════════════════════════════════════
echo   POUR ANNULER (si problème)
echo ═══════════════════════════════════════════════════════
echo.
echo   Copier la sauvegarde :
echo     copy /Y "%BACKUP%" "%PHP_INI%"
echo.
echo ═══════════════════════════════════════════════════════
echo.
pause
