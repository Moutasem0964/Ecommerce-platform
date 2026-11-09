$ErrorActionPreference = "Continue"
Set-Location $PSScriptRoot
$base = "http://127.0.0.1:8000/api/v1"
$failed = 0

function Step($text) { Write-Host "`n=== $text ===" -ForegroundColor Cyan }

function Call($Method, $Url, $Headers = @{}, $Body = $null) {
    $p = @{ Method = $Method; Uri = $Url; Headers = $Headers; UseBasicParsing = $true; TimeoutSec = 15 }
    if ($Body) { $p.Body = $Body; $p.ContentType = "application/json" }
    try {
        $r = Invoke-WebRequest @p
        return @{ Code = [int]$r.StatusCode; Body = $r.Content; Headers = $r.Headers }
    } catch {
        $resp = $_.Exception.Response
        if ($resp) { return @{ Code = [int]$resp.StatusCode; Body = ""; Headers = @{} } }
        return @{ Code = 0; Body = $_.Exception.Message; Headers = @{} }
    }
}

function Expect($name, $actual, $expected) {
    if ($actual -eq $expected) {
        Write-Host ("  PASS  {0}  ({1})" -f $name, $actual) -ForegroundColor Green
    } else {
        Write-Host ("  FAIL  {0}  (got {1}, expected {2})" -f $name, $actual, $expected) -ForegroundColor Red
        $script:failed++
    }
}

# 1. Docker running?
Step "Docker"
docker info 2>&1 | Out-Null
if ($LASTEXITCODE -ne 0) { Write-Host "Docker Desktop is not running." -ForegroundColor Red; exit 1 }
Write-Host "  ok"

Step "First-run setup"
if (-not (Test-Path ".env")) { Copy-Item ".env.example" ".env"; Write-Host "  created .env" }
if (-not (Test-Path "app/.env")) { Copy-Item "app/.env.example" "app/.env"; Write-Host "  created app/.env" }
$appEnv = Get-Content "app/.env" -Raw
if ($appEnv -match "(?m)^APP_KEY=[ \t\r]*$") {
    $bytes = New-Object byte[] 32
    [Security.Cryptography.RandomNumberGenerator]::Create().GetBytes($bytes)
    $appEnv = $appEnv -replace "(?m)^APP_KEY=[ \t\r]*$", ("APP_KEY=base64:" + [Convert]::ToBase64String($bytes))
    Set-Content "app/.env" $appEnv -NoNewline
    Write-Host "  generated APP_KEY"
}
Write-Host "  ok"

# 2. Build + start (only changed images are rebuilt; unchanged layers come from cache)
Step "Build and start"
$sw = [Diagnostics.Stopwatch]::StartNew()
docker compose build app
if ($LASTEXITCODE -ne 0) { Write-Host "Build failed." -ForegroundColor Red; exit 1 }
docker compose up -d --build
if ($LASTEXITCODE -ne 0) { Write-Host "Build failed." -ForegroundColor Red; exit 1 }
Write-Host ("  done in {0:N0}s" -f $sw.Elapsed.TotalSeconds)

# 3. Wait until Laravel answers
Step "Waiting for the API"
$ready = $false
for ($i = 0; $i -lt 60; $i++) {
    if ((Call GET "$base/ping").Code -eq 200) { $ready = $true; break }
    Start-Sleep -Seconds 1
}
if (-not $ready) {
    Write-Host "API did not come up. Last app logs:" -ForegroundColor Red
    docker compose logs app --tail 30
    exit 1
}
Write-Host "  API is up"

# 4. Database migrations
Step "Migrations"
docker compose exec -T app php artisan migrate --force
if ($LASTEXITCODE -ne 0) { Write-Host "Migrations failed." -ForegroundColor Red; exit 1 }

Step "JWT keys and demo users"
docker compose exec -T -u www-data app php artisan jwt:keys
docker compose exec -T app php artisan db:seed --force
if ($LASTEXITCODE -ne 0) { Write-Host "Seeding failed." -ForegroundColor Red; exit 1 }

# 5. Smoke tests
Step "Architecture tests"
docker compose exec -T app vendor/bin/phpunit --testsuite Architecture
if ($LASTEXITCODE -ne 0) { Write-Host "Module boundaries are broken." -ForegroundColor Red; exit 1 }

Step "Smoke tests"
Expect "GET /ping"                        (Call GET "$base/ping").Code 200
Expect "GET /products"                    (Call GET "$base/products").Code 200
Expect "GET /products/999999 -> 404"      (Call GET "$base/products/999999").Code 404
Expect "GET /orders without token -> 401" (Call GET "$base/orders").Code 401

$newEmail = "ci" + (Get-Random) + "@test.com"
$registerBody = @{ name = "CI User"; email = $newEmail; password = "secret123" } | ConvertTo-Json -Compress
Expect "POST /auth/register -> 201"            (Call POST "$base/auth/register" @{ Accept = "application/json" } $registerBody).Code 201
$wrongBody = @{ email = $newEmail; password = "wrong-password" } | ConvertTo-Json -Compress
Expect "POST /auth/login wrong password -> 401" (Call POST "$base/auth/login" @{ Accept = "application/json" } $wrongBody).Code 401

$login = Call POST "$base/auth/login" @{ Accept = "application/json" } '{"email":"sara@test.com","password":"secret123"}'
Expect "POST /auth/login (sara)" $login.Code 200
if ($login.Code -eq 200) {
    $token = ($login.Body | ConvertFrom-Json).access_token
    $auth = @{ Authorization = "Bearer $token"; Accept = "application/json" }
    Expect "GET /auth/me"                       (Call GET "$base/auth/me" $auth).Code 200
    Expect "GET /orders"                        (Call GET "$base/orders" $auth).Code 200
    Expect "GET /notifications"                 (Call GET "$base/notifications" $auth).Code 200
    Expect "POST /products as customer -> 403"  (Call POST "$base/products" $auth '{}').Code 403

    $adminLogin = Call POST "$base/auth/login" @{ Accept = "application/json" } '{"email":"admin@test.com","password":"secret123"}'
    Expect "POST /auth/login (admin)" $adminLogin.Code 200
    $adminAuth = @{ Authorization = "Bearer " + ($adminLogin.Body | ConvertFrom-Json).access_token; Accept = "application/json" }

    $created = Call POST "$base/products" $adminAuth '{"name":"CI Test Mug","price":"9.99","category":"ci","stock":5}'
    Expect "POST /products as admin -> 201" $created.Code 201

    if ($created.Code -eq 201) {
        $productId = ($created.Body | ConvertFrom-Json).product.id
        $orderBody = @{ items = @(@{ product_id = $productId; quantity = 1 }) } | ConvertTo-Json -Depth 5 -Compress
        $placed = Call POST "$base/orders" $auth $orderBody
        Expect "POST /orders -> 201" $placed.Code 201

        if ($placed.Code -eq 201) {
            $order = ($placed.Body | ConvertFrom-Json).order
            Expect "Order is confirmed"               $order.status "confirmed"
            Expect "Item name snapshot on the order"  $order.items[0].name "CI Test Mug"

            $latest = ((Call GET "$base/notifications" $auth).Body | ConvertFrom-Json).data[0]
            Expect "Notification created by event listener" $latest.order_id $order.id
        }

        $badBody = @{ items = @(@{ product_id = 999999; quantity = 1 }) } | ConvertTo-Json -Depth 5 -Compress
        Expect "POST /orders unknown product -> 422" (Call POST "$base/orders" $auth $badBody).Code 422
    }
}

$ping = Call GET "$base/ping"
Expect "Security header X-Frame-Options" $ping.Headers["X-Frame-Options"] "DENY"
Expect "No X-Powered-By header"          ([string]$ping.Headers["X-Powered-By"]) ""

$opcache = docker compose exec -T app php-fpm -i 2>$null | Select-String "opcache.validate_timestamps"
Expect "OPcache file checks off" ([bool]($opcache -match "Off")) $true

# 6. Response times (first request warms OPcache, then 5 measured)
Step "Response times (seconds)"
foreach ($path in @("/ping", "/products")) {
    curl.exe -s -o NUL "$base$path" | Out-Null
    $times = 1..5 | ForEach-Object { [double](curl.exe -s -o NUL -w "%{time_total}" "$base$path") }
    $sorted = $times | Sort-Object
    Write-Host ("  {0,-10} min {1:N3}  median {2:N3}  max {3:N3}" -f $path, $sorted[0], $sorted[2], $sorted[4])
}

# Result
Write-Host ""
if ($failed -eq 0) {
    Write-Host "CI PASSED" -ForegroundColor Green
    exit 0
} else {
    Write-Host "CI FAILED: $failed check(s)" -ForegroundColor Red
    exit 1
}
