# Runs the k6 "last unit" race test against the inventory gRPC service.
# Place this file in the repo's k6/ folder, then from the repo root run:
#   .\k6\run-race.ps1
#   .\k6\run-race.ps1 -Buyers 100 -ProductId 9002

param(
    [int]$Buyers = 50,
    [int]$ProductId = 9001,
    [string]$Network = "ecommerce-platform_backend"
)

$ErrorActionPreference = "Stop"

# Repo root = parent of the folder this script lives in (k6/)
$root = Split-Path -Parent $PSScriptRoot

# 1. Is the Docker network there? (the stack must be up)
docker network inspect $Network *> $null
if ($LASTEXITCODE -ne 0) {
    Write-Host "Network '$Network' not found. Start the stack first: docker compose up -d" -ForegroundColor Red
    exit 1
}

# 2. Is the inventory container running?
$running = docker compose -f "$root\compose.yaml" ps --status running --services 2>$null
if (-not ($running -contains "inventory")) {
    Write-Host "Inventory service is not running. Run: docker compose up -d inventory" -ForegroundColor Red
    exit 1
}

Write-Host "Racing $Buyers buyers for 1 unit of product $ProductId ..." -ForegroundColor Cyan

# 3. Run k6 (the script itself sets stock to 1, races, then checks final stock = 0)
docker run --rm `
    --network $Network `
    -e "BUYERS=$Buyers" `
    -e "PRODUCT_ID=$ProductId" `
    -v "${root}/proto:/proto:ro" `
    -v "${root}/k6:/scripts:ro" `
    grafana/k6:2.3.0 run /scripts/race-last-unit.js

$code = $LASTEXITCODE
if ($code -eq 0) {
    Write-Host "PASS: exactly 1 buyer won, $($Buyers - 1) were rejected, final stock is 0." -ForegroundColor Green
} else {
    Write-Host "FAIL: k6 exited with code $code (check the thresholds above)." -ForegroundColor Red
}
exit $code
