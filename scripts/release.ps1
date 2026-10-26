# =====================================================================
#  ManuTrack - prepara il pacchetto di rilascio (Windows PowerShell 5.1 o 7)
#
#  Uso (dalla cartella del progetto):
#     powershell -ExecutionPolicy Bypass -File scripts\release.ps1
#     powershell -ExecutionPolicy Bypass -File scripts\release.ps1 -Dirty
#
#  Requisiti sul PC: git, PHP 8.3 con zip, Composer, Node 20+ con npm (tutti nel PATH).
#  Risultato: dist\manutrack-<versione>.zip (+ .sha256).
# =====================================================================
param(
    [switch]$Dirty
)

$ErrorActionPreference = 'Stop'
$Root = Resolve-Path (Join-Path $PSScriptRoot '..')
Set-Location $Root

function Invoke-Step([string]$Command, [string[]]$Arguments) {
    & $Command @Arguments
    if ($LASTEXITCODE -ne 0) { throw "Comando non riuscito: $Command $($Arguments -join ' ')" }
}

$Version = (Get-Content VERSION -Raw).Trim()
$Build = Join-Path $Root "build\manutrack-$Version"
$Dist = Join-Path $Root 'dist'

Write-Host "ManuTrack $Version - preparazione del pacchetto"

if (-not $Dirty) {
    $status = git status --porcelain
    if ($status) { throw 'Ci sono modifiche non salvate in git. Fai un commit oppure usa -Dirty per una prova.' }
}

if (-not (Select-String -Path CHANGELOG.md -Pattern "^## \[$([regex]::Escape($Version))\]" -Quiet)) {
    throw "CHANGELOG.md non ha una sezione per la versione $Version."
}

if (Test-Path $Build) { Remove-Item $Build -Recurse -Force }
New-Item -ItemType Directory -Path $Build | Out-Null

Write-Host '-> Copia dei sorgenti'
$Archive = Join-Path $Root "build\sorgenti-$Version.zip"
if ($Dirty) {
    # Copia l'albero di lavoro rispettando .gitignore
    $files = git ls-files --cached --others --exclude-standard
    foreach ($f in $files) {
        $dest = Join-Path $Build $f
        New-Item -ItemType Directory -Path (Split-Path $dest) -Force | Out-Null
        Copy-Item -LiteralPath (Join-Path $Root $f) -Destination $dest -Force
    }
} else {
    Invoke-Step git @('archive', '--format=zip', "--output=$Archive", 'HEAD')
    Expand-Archive -Path $Archive -DestinationPath $Build -Force
    Remove-Item $Archive -Force
}

Set-Location $Build
try {
    Write-Host '-> Dipendenze PHP di produzione'
    Invoke-Step composer @('install', '--no-dev', '--optimize-autoloader', '--classmap-authoritative', '--no-interaction', '--no-progress', '--prefer-dist')

    Write-Host '-> Compilazione del frontend'
    Invoke-Step npm @('ci', '--no-audit', '--no-fund')
    Invoke-Step npm @('run', 'build')

    Write-Host '-> Pulizia'
    $remove = @('node_modules', 'tests', 'scripts', '.github', 'docker', 'docker-compose.yml',
        'resources\js', 'resources\css', 'phpunit.xml', 'phpstan.neon', 'pint.json',
        'package.json', 'package-lock.json', 'vite.config.js', '.editorconfig', '.gitattributes', '.gitignore', 'public\hot')
    foreach ($r in $remove) { if (Test-Path $r) { Remove-Item $r -Recurse -Force } }
    Get-ChildItem storage -Recurse -File | Where-Object { $_.Name -ne '.gitignore' } | Remove-Item -Force
    Get-ChildItem bootstrap\cache -Filter *.php -File | Remove-Item -Force
    # Cartelle .git lasciate da composer quando scarica i pacchetti con git
    Get-ChildItem vendor -Directory -Recurse -Force -Filter .git | Remove-Item -Recurse -Force
} finally {
    Set-Location $Root
}

Write-Host '-> Pacchetto'
Invoke-Step php @('scripts\release\package.php', $Build, $Dist)
Write-Host 'Fatto.'
