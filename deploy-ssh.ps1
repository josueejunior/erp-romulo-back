param(
    [string]$RemotePath = "/root/erp-romulo-back",
    [int]$Port = 22,
    [switch]$RunSetup
)

Set-StrictMode -Version Latest
$ErrorActionPreference = "Stop"

# Server credentials provided by project owner
$ServerHost = "191.101.18.238"
$ServerUser = "root"
$ServerPassword = "sshRomulo1@2"

$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$Timestamp = Get-Date -Format "yyyyMMdd-HHmmss"
$ArchivePath = Join-Path $env:TEMP "erp-romulo-back-$Timestamp.tar.gz"
$RemoteArchive = "/tmp/erp-romulo-back-$Timestamp.tar.gz"

$PlinkPath = "C:\Program Files\PuTTY\plink.exe"
$PscpPath = "C:\Program Files\PuTTY\pscp.exe"
$TarPath = "C:\Windows\System32\tar.exe"

if (-not (Test-Path -LiteralPath $PlinkPath)) {
    throw "plink.exe not found at '$PlinkPath'. Install PuTTY or adjust the path in deploy-ssh.ps1."
}

if (-not (Test-Path -LiteralPath $PscpPath)) {
    throw "pscp.exe not found at '$PscpPath'. Install PuTTY or adjust the path in deploy-ssh.ps1."
}

if (-not (Test-Path -LiteralPath $TarPath)) {
    throw "Windows tar.exe not found at '$TarPath'."
}

Write-Host "Creating deployment archive from '$ScriptDir'..."
Push-Location $ScriptDir
try {
    if (Test-Path -LiteralPath $ArchivePath) {
        Remove-Item -LiteralPath $ArchivePath -Force
    }

    $tarArgs = @(
        "-czf", $ArchivePath,
        "--exclude=.git",
        "--exclude=.github",
        "--exclude=node_modules",
        "--exclude=vendor",
        "--exclude=.env",
        "--exclude=storage/logs/*",
        "--exclude=storage/framework/cache/data/*",
        "."
    )

    & $TarPath @tarArgs
    if ($LASTEXITCODE -ne 0) {
        throw "tar failed to create archive."
    }
}
finally {
    Pop-Location
}

Write-Host "Ensuring remote directory exists: $RemotePath"
& $PlinkPath -batch -P $Port -l $ServerUser -pw $ServerPassword $ServerHost "mkdir -p '$RemotePath'"
if ($LASTEXITCODE -ne 0) {
    throw "Could not create remote directory."
}

Write-Host "Uploading archive to server..."
& $PscpPath -batch -P $Port -pw $ServerPassword $ArchivePath "$ServerUser@$ServerHost`:$RemoteArchive"
if ($LASTEXITCODE -ne 0) {
    throw "Upload failed."
}

Write-Host "Extracting files on server..."
$extractCmd = "set -e; tar -xzf '$RemoteArchive' -C '$RemotePath'; rm -f '$RemoteArchive'; echo 'Files uploaded to $RemotePath'"
& $PlinkPath -batch -P $Port -l $ServerUser -pw $ServerPassword $ServerHost $extractCmd
if ($LASTEXITCODE -ne 0) {
    throw "Remote extraction failed."
}

if ($RunSetup) {
    Write-Host "Running optional setup on server (composer/npm/artisan)..."
    $setupCmd = @"
set -e
cd '$RemotePath'

if [ ! -f .env ] && [ -f .env.example ]; then
  cp .env.example .env
fi

if command -v composer >/dev/null 2>&1; then
  composer install --no-interaction --prefer-dist --optimize-autoloader
fi

if command -v npm >/dev/null 2>&1 && [ -f package.json ]; then
  npm install
  npm run build
fi

if command -v php >/dev/null 2>&1 && [ -f artisan ]; then
  php artisan key:generate --force || true
  php artisan migrate --force || true
  php artisan optimize:clear || true
fi

chmod -R ug+rw storage bootstrap/cache >/dev/null 2>&1 || true
echo "Server setup complete."
"@
    & $PlinkPath -batch -P $Port -l $ServerUser -pw $ServerPassword $ServerHost $setupCmd
    if ($LASTEXITCODE -ne 0) {
        throw "Optional setup step failed."
    }
}

Write-Host "Deploy finished successfully."
