param(
    [string]$Before = "before",
    [string]$After = "after"
)

$ErrorActionPreference = "Stop"
$dir = Join-Path $PSScriptRoot "results"

function Load($label) {
    $path = Join-Path $dir "$label.json"
    if (-not (Test-Path $path)) {
        Write-Host "No results for '$label' ($path). Run: .\k6\run-load.ps1 -Label $label -Quick" -ForegroundColor Red
        exit 1
    }
    Get-Content $path -Raw | ConvertFrom-Json
}

function Change($b, $a) {
    if (-not $b) { return "-" }
    $pct = ($a - $b) / $b * 100
    return ("{0:+0;-0}%" -f $pct)
}

$b = Load $Before
$a = Load $After

Write-Host ""
Write-Host ("R3 before/after: {0} -> {1}" -f $Before, $After) -ForegroundColor Cyan
Write-Host ("req/s {0:N1} -> {1:N1}   failed {2:P2} -> {3:P2}" -f $b.overall.rps, $a.overall.rps, $b.overall.failed_rate, $a.overall.failed_rate)
Write-Host ""
Write-Host ("{0,-22} {1,8} {2,8} {3,7}   {4,8} {5,8} {6,7}   {7,8} {8,8} {9,7}" -f "endpoint", "p50", "p50", "", "p95", "p95", "", "p99", "p99", "")
Write-Host ("{0,-22} {1,8} {2,8} {3,7}   {4,8} {5,8} {6,7}   {7,8} {8,8} {9,7}" -f "", "before", "after", "change", "before", "after", "change", "before", "after", "change")

$rows = @()
foreach ($name in $b.endpoints.PSObject.Properties.Name) {
    $rows += [pscustomobject]@{ Name = $name; B = $b.endpoints.$name; A = $a.endpoints.$name }
}
$rows += [pscustomobject]@{ Name = "ALL"; B = $b.overall; A = $a.overall }

foreach ($r in $rows) {
    if (-not $r.A) { continue }
    $line = "{0,-22} {1,8:N1} {2,8:N1} {3,7}   {4,8:N1} {5,8:N1} {6,7}   {7,8:N1} {8,8:N1} {9,7}" -f `
        $r.Name,
        $r.B.p50, $r.A.p50, (Change $r.B.p50 $r.A.p50),
        $r.B.p95, $r.A.p95, (Change $r.B.p95 $r.A.p95),
        $r.B.p99, $r.A.p99, (Change $r.B.p99 $r.A.p99)
    $color = $(if ($r.Name -eq "ALL") { "Yellow" } else { "White" })
    Write-Host $line -ForegroundColor $color
}
Write-Host ""
