<#
.SYNOPSIS
    Stage 1 / Question 2: sets up, tests, runs and live-demos the Laravel 12 Product Inventory API.

.DESCRIPTION
    1. Checks prerequisites (PHP 8.2+, pdo_sqlite) and installs Composer dependencies if needed.
    2. Prepares .env + SQLite database, then resets and seeds it with demo data.
    3. Runs the PHPUnit test suite.
    4. Starts the API on http://127.0.0.1:<Port> and runs a scripted live demo against it:
       auth, list + filters + pagination, create / show / update / soft delete / restore,
       validation errors, 401 handling, caching and rate limiting.
    5. Leaves the server running with Swagger UI (/docs) open until you press Enter.
    A copy of the console output is saved in results/.

.PARAMETER Port
    Port for the local API server (default 8000).

.PARAMETER SkipTests
    Skip the PHPUnit run.

.PARAMETER NoWait
    Stop the server right after the demo (no browser, no "press Enter"). Useful for CI.

.EXAMPLE
    powershell -ExecutionPolicy Bypass -File .\question2-inventory-api\question2.ps1
#>
[CmdletBinding()]
param(
    [int]$Port = 8000,
    [switch]$SkipTests,
    [switch]$NoWait
)

$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest

$root    = $PSScriptRoot
$app     = $root
$results = Join-Path $root 'results'
New-Item -ItemType Directory -Force $results | Out-Null
$logFile = Join-Path $results ("question2_{0}.log" -f (Get-Date -Format 'dd-MM-yy_HHmmss'))
$baseUrl = "http://127.0.0.1:$Port"
$server  = $null
$script:checks = New-Object System.Collections.Generic.List[object]

Start-Transcript -Path $logFile | Out-Null

# ============================================================================ helpers
function Write-Section([string]$title) {
    Write-Host ''
    Write-Host ('=' * 78) -ForegroundColor DarkCyan
    Write-Host "  $title" -ForegroundColor Cyan
    Write-Host ('=' * 78) -ForegroundColor DarkCyan
}

function Invoke-Native([string]$label, [scriptblock]$command) {
    Write-Host "> $label" -ForegroundColor DarkGray
    & $command
    if ($LASTEXITCODE -ne 0) { throw "'$label' failed with exit code $LASTEXITCODE." }
}

# Works on Windows PowerShell 5.1 and PowerShell 7: always returns status + parsed body, even for 4xx/5xx.
function Invoke-Api {
    param(
        [string]$Method,
        [string]$Path,
        [object]$Body = $null,
        [string]$Token = $null
    )
    $headers = @{ Accept = 'application/json' }
    if ($Token) { $headers.Authorization = "Bearer $Token" }
    $params = @{ Uri = "$baseUrl$Path"; Method = $Method; Headers = $headers; UseBasicParsing = $true }
    if ($null -ne $Body) {
        $params.ContentType = 'application/json'
        $params.Body = ($Body | ConvertTo-Json -Depth 10 -Compress)
    }
    try {
        $resp = Invoke-WebRequest @params
        $status = [int]$resp.StatusCode
        $raw = $resp.Content
    } catch {
        if ($null -eq $_.Exception.Response) { throw }
        $status = [int]$_.Exception.Response.StatusCode
        $raw = ''
        if ($_.ErrorDetails -and $_.ErrorDetails.Message) {
            $raw = $_.ErrorDetails.Message                       # PowerShell 7
        } elseif ($_.Exception.Response -is [System.Net.HttpWebResponse]) {
            $stream = $_.Exception.Response.GetResponseStream()  # Windows PowerShell 5.1
            $stream.Position = 0
            $raw = (New-Object System.IO.StreamReader($stream)).ReadToEnd()
        }
    }
    $json = $null
    if ($raw) { try { $json = $raw | ConvertFrom-Json } catch { $json = $null } }
    [pscustomobject]@{ Status = $status; Json = $json; Raw = $raw }
}

function Show-Request([string]$Method, [string]$Path, [object]$Body = $null) {
    Write-Host ''
    Write-Host ("  {0} {1}" -f $Method.ToUpper(), $Path) -ForegroundColor Yellow
    if ($null -ne $Body) {
        Write-Host ('  body: ' + ($Body | ConvertTo-Json -Depth 10 -Compress)) -ForegroundColor DarkYellow
    }
}

function Assert-Check([string]$name, [bool]$ok, [string]$detail) {
    $script:checks.Add([pscustomobject]@{ Check = $name; Result = $(if ($ok) { 'PASS' } else { 'FAIL' }); Detail = $detail })
    $color = if ($ok) { 'Green' } else { 'Red' }
    $mark  = if ($ok) { '[PASS]' } else { '[FAIL]' }
    Write-Host ("  $mark $name - $detail") -ForegroundColor $color
}

function Format-ProductRow($p) {
    '{0,-5} {1,-30} {2,-16} {3,10} {4,6}  {5,-13} {6}' -f $p.id, $p.name, $p.sku, $p.price, $p.stock_quantity, $p.stock_status, $p.category.name
}

function Show-ProductTable($products) {
    Write-Host ('  {0,-5} {1,-30} {2,-16} {3,10} {4,6}  {5,-13} {6}' -f 'ID', 'NAME', 'SKU', 'PRICE', 'STOCK', 'STATUS', 'CATEGORY') -ForegroundColor DarkGray
    foreach ($p in $products) { Write-Host ('  ' + (Format-ProductRow $p)) }
}

function Get-Composer {
    $global = Get-Command composer -ErrorAction SilentlyContinue
    if ($global) { return @($global.Source) }
    $localPhar = Join-Path $root '.tools\composer.phar'
    if (-not (Test-Path $localPhar)) {
        Write-Host 'Composer not found on PATH: downloading composer.phar into .tools\ ...' -ForegroundColor Yellow
        New-Item -ItemType Directory -Force (Split-Path $localPhar) | Out-Null
        $setup = Join-Path (Split-Path $localPhar) 'composer-setup.php'
        Invoke-WebRequest -Uri 'https://getcomposer.org/installer' -OutFile $setup -UseBasicParsing
        & php $setup --quiet "--install-dir=$(Split-Path $localPhar)" --filename=composer.phar
        if ($LASTEXITCODE -ne 0) { throw 'Composer installation failed.' }
        Remove-Item $setup
    }
    return @('php', $localPhar)
}

# ============================================================================ main
try {
    Write-Section 'Senior PHP Engineer Assessment - Stage 1 / Question 2'
    Write-Host 'Product Inventory REST API - Laravel 12, Sanctum, filters, soft deletes, caching,'
    Write-Host 'rate limiting, Swagger docs.'

    # -------------------------------------------------------------- 1. prerequisites
    Write-Section '1. Prerequisites & setup'
    if (-not (Get-Command php -ErrorAction SilentlyContinue)) { throw 'php was not found on PATH (PHP 8.2+ required).' }
    $phpVersion = & php -r 'echo PHP_VERSION;'
    if ((& php -r 'echo (int) (PHP_VERSION_ID >= 80200);') -ne '1') { throw "PHP 8.2+ required, found $phpVersion." }
    if (-not (& php -m | Where-Object { $_ -eq 'pdo_sqlite' })) { throw 'The pdo_sqlite PHP extension is required (enable it in php.ini).' }
    Write-Host "PHP $phpVersion with pdo_sqlite: OK" -ForegroundColor Green

    $busy = Get-NetTCPConnection -LocalPort $Port -State Listen -ErrorAction SilentlyContinue
    if ($busy) { throw "Port $Port is already in use. Re-run with -Port <free port>." }

    Push-Location $app

    if (-not (Test-Path 'vendor\autoload.php')) {
        $composer = Get-Composer
        $composerArgs = @()
        if ($composer.Count -gt 1) { $composerArgs = $composer[1..($composer.Count - 1)] }
        Invoke-Native 'composer install' { & $composer[0] @composerArgs install --no-interaction --prefer-dist }
    } else {
        Write-Host 'Composer dependencies: already installed' -ForegroundColor Green
    }

    if (-not (Test-Path '.env')) {
        Copy-Item '.env.example' '.env'
        Write-Host 'Created .env from .env.example' -ForegroundColor Green
    }
    if (-not (Select-String -Path '.env' -Pattern '^APP_KEY=base64:' -Quiet)) {
        Invoke-Native 'php artisan key:generate' { & php artisan key:generate --ansi }
    }
    if (-not (Test-Path 'database\database.sqlite')) {
        New-Item -ItemType File 'database\database.sqlite' | Out-Null
        Write-Host 'Created database\database.sqlite' -ForegroundColor Green
    }
    Invoke-Native 'php artisan migrate:fresh --seed (resets demo data)' { & php artisan migrate:fresh --seed --force --ansi }

    # -------------------------------------------------------------- 2. tests
    if (-not $SkipTests) {
        Write-Section '2. Automated test suite (PHPUnit)'
        & php artisan test --ansi
        $testsOk = ($LASTEXITCODE -eq 0)
        Assert-Check 'PHPUnit test suite' $testsOk $(if ($testsOk) { 'all tests passed' } else { "exit code $LASTEXITCODE" })
    }

    # -------------------------------------------------------------- 3. server
    Write-Section "3. Starting API server on $baseUrl"
    # Laravel's dev router resolves index.php from the working directory, so start from public/.
    $router = Join-Path $app 'vendor\laravel\framework\src\Illuminate\Foundation\resources\server.php'
    $server = Start-Process -FilePath 'php' -ArgumentList @('-S', "127.0.0.1:$Port", '-t', '.', "`"$router`"") `
        -WorkingDirectory (Join-Path $app 'public') -WindowStyle Hidden -PassThru
    $ready = $false
    for ($i = 0; $i -lt 40 -and -not $ready; $i++) {
        Start-Sleep -Milliseconds 250
        try {
            $up = Invoke-WebRequest "$baseUrl/up" -UseBasicParsing -TimeoutSec 2
            $ready = ($up.StatusCode -eq 200 -and $up.Content -match 'Application up')
        } catch { $ready = $false }
    }
    if (-not $ready) { throw "Server did not become ready on $baseUrl." }
    Write-Host "Server running (PID $($server.Id))" -ForegroundColor Green

    # -------------------------------------------------------------- 4. live demo
    Write-Section '4. Live API demo'

    Write-Host "`n--- Authentication (Sanctum) ---" -ForegroundColor Magenta
    Show-Request 'GET' '/api/products   (no token)'
    $r = Invoke-Api GET '/api/products'
    Assert-Check 'Unauthenticated request rejected' ($r.Status -eq 401) "HTTP $($r.Status): $($r.Json.message)"

    $login = @{ email = 'demo@example.com'; password = 'password'; device_name = 'demo-script' }
    Show-Request 'POST' '/api/auth/login' $login
    $r = Invoke-Api POST '/api/auth/login' $login
    $token = $r.Json.token
    Assert-Check 'Login returns bearer token' ($r.Status -eq 200 -and $token) ("HTTP $($r.Status), token " + $token.Substring(0, 12) + '...')

    Show-Request 'GET' '/api/auth/me'
    $r = Invoke-Api GET '/api/auth/me' -Token $token
    Assert-Check 'Token authenticates /me' ($r.Status -eq 200 -and $r.Json.data.email -eq 'demo@example.com') "HTTP $($r.Status), user = $($r.Json.data.name) <$($r.Json.data.email)>"

    Write-Host "`n--- Listing, pagination & filters ---" -ForegroundColor Magenta
    Show-Request 'GET' '/api/products?per_page=5'
    $r = Invoke-Api GET '/api/products?per_page=5' -Token $token
    Show-ProductTable $r.Json.data
    Assert-Check 'Paginated list' ($r.Status -eq 200 -and @($r.Json.data).Count -eq 5) ("page $($r.Json.meta.current_page)/$($r.Json.meta.last_page), $($r.Json.meta.total) products total")

    $cats = (Invoke-Api GET '/api/categories' -Token $token).Json.data
    $cat = $cats[0]
    Show-Request 'GET' "/api/products?category_id=$($cat.id)&per_page=50"
    $r = Invoke-Api GET "/api/products?category_id=$($cat.id)&per_page=50" -Token $token
    $allInCat = @($r.Json.data | Where-Object { $_.category.id -ne $cat.id }).Count -eq 0
    Assert-Check 'Filter by category' ($r.Status -eq 200 -and $allInCat -and $r.Json.meta.total -gt 0) "$($r.Json.meta.total) products, all in '$($cat.name)'"

    Show-Request 'GET' '/api/products?min_price=100&max_price=500&sort=price&per_page=50'
    $r = Invoke-Api GET '/api/products?min_price=100&max_price=500&sort=price&per_page=50' -Token $token
    $prices = @($r.Json.data | ForEach-Object { [decimal]$_.price })
    $inRange = @($prices | Where-Object { $_ -lt 100 -or $_ -gt 500 }).Count -eq 0
    $sorted = ($prices -join ',') -eq (($prices | Sort-Object) -join ',')
    $range = if ($prices.Count) { "$($prices[0])..$($prices[-1])" } else { 'none' }
    Assert-Check 'Filter by price range + sort' ($r.Status -eq 200 -and $inRange -and $sorted) "$($prices.Count) products, prices $range, ascending"

    Show-Request 'GET' '/api/products?stock_level=low_stock&per_page=5'
    $r = Invoke-Api GET '/api/products?stock_level=low_stock&per_page=5' -Token $token
    Show-ProductTable $r.Json.data
    $allLow = @($r.Json.data | Where-Object { $_.stock_status -ne 'low_stock' }).Count -eq 0
    Assert-Check 'Filter by stock level' ($r.Status -eq 200 -and $allLow -and $r.Json.meta.total -gt 0) "$($r.Json.meta.total) low-stock products"

    Show-Request 'GET' '/api/products?min_price=500&max_price=10&stock_level=plenty'
    $r = Invoke-Api GET '/api/products?min_price=500&max_price=10&stock_level=plenty' -Token $token
    $errFields = if ($r.Json -and $r.Json.errors) { ($r.Json.errors.PSObject.Properties.Name -join ', ') } else { '' }
    Assert-Check 'Invalid filters rejected' ($r.Status -eq 422) "HTTP $($r.Status), errors on: $errFields"

    Write-Host "`n--- CRUD with soft delete ---" -ForegroundColor Magenta
    $suppliers = (Invoke-Api GET '/api/suppliers' -Token $token).Json.data
    $newProduct = @{
        category_id    = $cat.id
        name           = 'Demo Wireless Mouse'
        sku            = ' demo-wm-001 '
        price          = 49.9
        stock_quantity = 120
        suppliers      = @(@{ id = $suppliers[0].id; cost_price = 30 }, @{ id = $suppliers[1].id })
    }
    Show-Request 'POST' '/api/products' $newProduct
    $r = Invoke-Api POST '/api/products' $newProduct $token
    $p = $r.Json.data
    Show-ProductTable @($p)
    Assert-Check 'Create product (201)' ($r.Status -eq 201) "HTTP $($r.Status), id $($p.id)"
    Assert-Check 'SKU mutator normalises value' ($p.sku -eq 'DEMO-WM-001') "' demo-wm-001 ' stored as '$($p.sku)'"
    Assert-Check 'Suppliers attached (belongsToMany)' (@($p.suppliers).Count -eq 2) ("$(@($p.suppliers).Count) suppliers, cost_price " + (@($p.suppliers | ForEach-Object { if ($null -eq $_.cost_price) { 'null' } else { $_.cost_price } }) -join ' / '))
    Assert-Check 'Timestamps in GMT+8' ($p.created_at -like '*+08:00') "created_at = $($p.created_at)"

    Show-Request 'POST' '/api/products' @{ sku = 'DEMO-WM-001'; price = -5 }
    $r = Invoke-Api POST '/api/products' @{ sku = 'DEMO-WM-001'; price = -5 } $token
    $errFields = if ($r.Json -and $r.Json.errors) { ($r.Json.errors.PSObject.Properties.Name -join ', ') } else { '' }
    Assert-Check 'Validation via Form Request (422)' ($r.Status -eq 422) "HTTP $($r.Status), errors on: $errFields"

    Show-Request 'GET' "/api/products/$($p.id)"
    $r = Invoke-Api GET "/api/products/$($p.id)" -Token $token
    Assert-Check 'Show product' ($r.Status -eq 200 -and $r.Json.data.id -eq $p.id) "HTTP $($r.Status), $($r.Json.data.name)"

    Show-Request 'GET' '/api/products?search=Demo+Wireless   (first call: result is cached)'
    $r = Invoke-Api GET '/api/products?search=Demo+Wireless' -Token $token
    Assert-Check 'Search finds the new product' ($r.Status -eq 200 -and $r.Json.data[0].price -eq '49.90') "cached price = $($r.Json.data[0].price)"

    $patch = @{ stock_quantity = 3; price = 39.9 }
    Show-Request 'PATCH' "/api/products/$($p.id)" $patch
    $r = Invoke-Api PATCH "/api/products/$($p.id)" $patch $token
    Show-ProductTable @($r.Json.data)
    Assert-Check 'Partial update + stock_status accessor' ($r.Status -eq 200 -and $r.Json.data.stock_status -eq 'low_stock' -and $r.Json.data.price -eq '39.90') "stock 3 -> '$($r.Json.data.stock_status)', price $($r.Json.data.price)"

    Show-Request 'GET' '/api/products?search=Demo+Wireless   (same query again: the update must have invalidated the cache)'
    $r = Invoke-Api GET '/api/products?search=Demo+Wireless' -Token $token
    Assert-Check 'Cache invalidated on update' ($r.Status -eq 200 -and $r.Json.data[0].price -eq '39.90') "price in list = $($r.Json.data[0].price) (not the stale 49.90)"

    Show-Request 'DELETE' "/api/products/$($p.id)"
    $r = Invoke-Api DELETE "/api/products/$($p.id)" -Token $token
    Assert-Check 'Delete product (204)' ($r.Status -eq 204) "HTTP $($r.Status)"
    $r = Invoke-Api GET "/api/products/$($p.id)" -Token $token
    Assert-Check 'Deleted product hidden' ($r.Status -eq 404) "GET after delete -> HTTP $($r.Status)"
    $deletedAt = & php artisan tinker --execute="echo App\Models\Product::withTrashed()->find($($p.id))?->deleted_at;"
    Assert-Check 'Row kept with deleted_at (soft delete)' ([bool]"$deletedAt".Trim()) "deleted_at = $("$deletedAt".Trim()) (UTC in DB)"

    Show-Request 'POST' "/api/products/$($p.id)/restore"
    $r = Invoke-Api POST "/api/products/$($p.id)/restore" -Token $token
    Assert-Check 'Restore soft-deleted product' ($r.Status -eq 200 -and $r.Json.data.id -eq $p.id) "HTTP $($r.Status), product is back"

    Write-Host "`n--- Rate limiting ---" -ForegroundColor Magenta
    $bad = @{ email = 'attacker@example.com'; password = 'guess'; device_name = 'demo-script' }
    Show-Request 'POST' '/api/auth/login  x6 with wrong password' $bad
    $codes = @()
    for ($i = 1; $i -le 6; $i++) { $codes += (Invoke-Api POST '/api/auth/login' $bad).Status }
    Assert-Check 'Login throttled after 5 attempts/min' ($codes[-1] -eq 429) ("status codes: " + ($codes -join ', '))

    Show-Request 'POST' '/api/auth/logout'
    $r = Invoke-Api POST '/api/auth/logout' -Token $token
    $after = Invoke-Api GET '/api/auth/me' -Token $token
    Assert-Check 'Logout revokes token' ($r.Status -eq 204 -and $after.Status -eq 401) "logout HTTP $($r.Status), reuse token -> HTTP $($after.Status)"

    $docs = Invoke-WebRequest "$baseUrl/docs" -UseBasicParsing
    Assert-Check 'Swagger UI served' ($docs.StatusCode -eq 200) "$baseUrl/docs"

    # -------------------------------------------------------------- 5. summary
    Write-Section '5. Summary'
    $script:checks | Format-Table -AutoSize | Out-String -Width 220 | Write-Host
    $failed = @($script:checks | Where-Object Result -eq 'FAIL')
    if ($failed.Count -gt 0) {
        Write-Host "OVERALL: FAIL ($($failed.Count) of $($script:checks.Count) checks failed)" -ForegroundColor Red
        $script:exit = 1
    } else {
        Write-Host "OVERALL: PASS ($($script:checks.Count) checks)" -ForegroundColor Green
        $script:exit = 0
    }

    if (-not $NoWait) {
        Write-Host ''
        Write-Host "API      : $baseUrl/api" -ForegroundColor Cyan
        Write-Host "Swagger  : $baseUrl/docs" -ForegroundColor Cyan
        Write-Host 'Login    : demo@example.com / password' -ForegroundColor Cyan
        Start-Process "$baseUrl/docs"
        Read-Host "`nServer is running. Press Enter to stop it"
    }
}
catch {
    Write-Host ''
    Write-Host "ERROR: $($_.Exception.Message)" -ForegroundColor Red
    $script:exit = 1
}
finally {
    if ($server -and -not $server.HasExited) {
        Stop-Process -Id $server.Id -Force
        Write-Host 'Server stopped.' -ForegroundColor DarkGray
    }
    if ((Get-Location).Path -eq $app) { Pop-Location }
    Stop-Transcript | Out-Null
    Write-Host "Log saved to $logFile" -ForegroundColor DarkGray
}
exit $script:exit
