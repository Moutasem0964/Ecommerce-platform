param(
    [string]$Label = "distribution",
    [switch]$StopOne,
    [int]$StopAfter = 4,
    [string]$Victim = "ecommerce-platform-app-2",
    [int]$Rate = 50,
    [string]$Duration = "10s",
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
    Write-Host "Set API_RATE_LIMIT=1000000 in app\.env, then run: docker compose up -d --force-recreate app" -ForegroundColor Yellow
    exit 1
}

$instances = docker compose ps -q app | ForEach-Object {
    docker inspect -f '{{.Name}}={{range .NetworkSettings.Networks}}{{.IPAddress}}{{end}}' $_
} | ForEach-Object { $_.TrimStart('/') }

if (-not $instances) {
    Write-Host "No app instances are running." -ForegroundColor Red
    exit 1
}

Write-Host "Instances:" -ForegroundColor Cyan
$instances | ForEach-Object { Write-Host "  $_" }

$job = $null
if ($StopOne) {
    Write-Host "Will stop $Victim after $StopAfter s" -ForegroundColor Yellow
    $job = Start-Job -ScriptBlock {
        param($seconds, $name)
        Start-Sleep -Seconds $seconds
        docker stop $name | Out-Null
        "stopped $name at $(Get-Date -Format HH:mm:ss)"
    } -ArgumentList $StopAfter, $Victim
}

docker run --rm `
    --network $Network `
    -e "LABEL=$Label" `
    -e "RATE=$Rate" `
    -e "DURATION=$Duration" `
    -e "INSTANCES=$($instances -join ',')" `
    -e "BASE_URL=http://web/api/v1" `
    -v "${root}/k6:/scripts:ro" `
    -v "${root}/k6/results:/results" `
    grafana/k6:2.3.0 run --quiet /scripts/load-r5.js
$code = $LASTEXITCODE

if ($job) {
    Wait-Job $job | Out-Null
    Receive-Job $job | ForEach-Object { Write-Host $_ -ForegroundColor Yellow }
    Remove-Job $job
    docker start $Victim | Out-Null
    Write-Host "started $Victim again" -ForegroundColor Yellow
}

exit $code
