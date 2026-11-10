param(
    [string]$Label = "baseline",
    [switch]$Quick,
    [string]$Network = "ecommerce-platform_backend"
)

$ErrorActionPreference = "Continue"
$root = Split-Path -Parent $PSScriptRoot

docker network inspect $Network 2>&1 | Out-Null
if ($LASTEXITCODE -ne 0) {
    Write-Host "Network '$Network' not found. Start the stack first: .\ci.ps1" -ForegroundColor Red
    exit 1
}

$limit = (Select-String -Path "$root\app\.env" -Pattern '^API_RATE_LIMIT=(\d+)' -CaseSensitive | ForEach-Object { [int]$_.Matches[0].Groups[1].Value }) | Select-Object -First 1
if (-not $limit -or $limit -lt 100000) {
    Write-Host "Set API_RATE_LIMIT=1000000 in app\.env, then run: docker compose up -d app" -ForegroundColor Yellow
    Write-Host "(all k6 traffic comes from one IP, so the normal 60/min limit would answer 429)" -ForegroundColor Yellow
    exit 1
}

$duration = $(if ($Quick) { "about 1 minute" } else { "about 3.5 minutes" })
Write-Host "Running R3 load test '$Label' ($duration)..." -ForegroundColor Cyan

docker run --rm `
    --network $Network `
    -e "LABEL=$Label" `
    -e "QUICK=$(if ($Quick) { '1' } else { '0' })" `
    -e "BASE_URL=http://web/api/v1" `
    -v "${root}/k6:/scripts:ro" `
    -v "${root}/k6/results:/results" `
    grafana/k6:2.3.0 run /scripts/load-r3.js

exit $LASTEXITCODE
