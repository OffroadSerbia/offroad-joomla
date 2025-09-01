param(
    [Parameter(Mandatory = $true, Position = 0)]
    [string]$BaseUrl
)

# Normalize base URL
if ($BaseUrl.EndsWith('/')) { $BaseUrl = $BaseUrl.TrimEnd('/') }

function Get-Head {
    param([string]$Url)
    try {
        return Invoke-WebRequest -Uri $Url -Method Head -MaximumRedirection 5 -ErrorAction Stop
    }
    catch {
        # Return the response object if available (for status code/headers), else $null
        if ($_.Exception.Response) { return $_.Exception.Response }
        return $null
    }
}

function Get-Body {
    param([string]$Url)
    try {
        return Invoke-WebRequest -Uri $Url -Method Get -MaximumRedirection 5 -ErrorAction Stop
    }
    catch {
        if ($_.Exception.Response) { return $_.Exception.Response }
        return $null
    }
}

$pass = $true

Write-Host "=== OffroadSEO verify on $BaseUrl ===" -ForegroundColor Cyan

$targets = @(
    @{ name = 'robots'; url = "$BaseUrl/robots.txt"; expectType = 'text/plain' },
    @{ name = 'sitemap'; url = "$BaseUrl/sitemap.xml"; expectType = 'application/xml' },
    @{ name = 'sitemap_index'; url = "$BaseUrl/sitemap_index.xml"; expectType = 'application/xml' },
    @{ name = 'diag'; url = "$BaseUrl/offseo-diag"; expectType = 'text/plain' }
)

$results = @()

foreach ($t in $targets) {
    # Try HEAD first; if not 200, try GET to inspect content-type/body
    $h = Get-Head -Url $t.url
    $code = $null
    $ct = $null
    $etag = $null
    $lm = $null
    if ($h) {
        $code = $h.StatusCode.value__
        $ct = ($h.Headers['Content-Type'] | Select-Object -First 1)
        $etag = $h.Headers['ETag']
        $lm = $h.Headers['Last-Modified']
    }
    if ($null -eq $h -or $code -ne 200) {
        $g = Get-Body -Url $t.url
        if ($g) {
            if (-not $code) { $code = $g.StatusCode.value__ }
            if (-not $ct) { $ct = ($g.Headers['Content-Type'] | Select-Object -First 1) }
            if (-not $etag) { $etag = $g.Headers['ETag'] }
            if (-not $lm) { $lm = $g.Headers['Last-Modified'] }
        }
    }
    if (-not $code) {
        $results += [pscustomobject]@{ Name = $t.name; Url = $t.url; Status = 'FAIL'; Detail = 'No response' }
        $pass = $false
        continue
    }

    $typeOk = $false
    if ($t.name -eq 'robots') { $typeOk = ($ct -like 'text/plain*') }
    elseif ($t.name -eq 'diag') { $typeOk = ($ct -like 'text/plain*' -or -not $ct) } # some stacks omit type on raw
    else { $typeOk = ($ct -like '*xml*') }

    $statusOk = ($code -eq 200)

    $cacheOk = $false
    if ($etag) {
        try {
            $h2 = Invoke-WebRequest -Uri $t.url -Method Head -Headers @{ 'If-None-Match' = $etag } -ErrorAction Stop
            $cacheOk = ($h2.StatusCode.value__ -eq 304)
        }
        catch { $cacheOk = $false }
    }
    elseif ($lm) {
        try {
            $h2 = Invoke-WebRequest -Uri $t.url -Method Head -Headers @{ 'If-Modified-Since' = $lm } -ErrorAction Stop
            $cacheOk = ($h2.StatusCode.value__ -eq 304)
        }
        catch { $cacheOk = $false }
    }

    $detail = "${code} $ct" + ($(if ($etag) { ", ETag=$etag" } else { '' })) + ($(if ($lm) { ", Last-Modified=$lm" } else { '' }))

    $ok = $statusOk -and $typeOk -and ($t.name -eq 'diag' -or $cacheOk)
    if (-not $ok) { $pass = $false }
    $results += [pscustomobject]@{ Name = $t.name; Url = $t.url; Status = ($(if ($ok) { 'PASS' } else { 'FAIL' })); Detail = $detail }
}

# Extra: inspect diag body
$diag = Get-Body -Url "$BaseUrl/offseo-diag"
if ($diag) {
    $body = $diag.Content
    $am = if ($body -match 'active_match=(\d)') { $Matches[1] } else { '' }
    $ver = if ($body -match 'version=([^\r\n]+)') { $Matches[1] } else { '' }
    Write-Host "diag: version=$ver active_match=$am" -ForegroundColor DarkCyan
}

$results | Format-Table -AutoSize

if ($pass) {
    Write-Host "All checks PASS" -ForegroundColor Green
    exit 0
}
else {
    Write-Host "Some checks FAILED" -ForegroundColor Red
    # Hints based on observations
    $robots = $results | Where-Object { $_.Name -eq 'robots' } | Select-Object -First 1
    if ($robots -and $robots.Detail -match 'application/octet-stream') {
        Write-Host "Hint: robots.txt izgleda kao fizički fajl (application/octet-stream + Last-Modified). Ukloniti ga i dozvoliti rewrite ka index.php." -ForegroundColor Yellow
    }
    $smap = $results | Where-Object { $_.Name -eq 'sitemap_index' -or $_.Name -eq 'sitemap' } | Where-Object { $_.Status -eq 'FAIL' }
    if ($smap) {
        Write-Host "Hint: sitemap rute ne vraćaju 200. Dodaj .htaccess pravila i WAF/CDN izuzetke za /sitemap*.xml i /offseo-diag." -ForegroundColor Yellow
    }
    $diagR = $results | Where-Object { $_.Name -eq 'diag' } | Select-Object -First 1
    if ($diagR -and $diagR.Status -eq 'FAIL') {
        Write-Host "Hint: /offseo-diag blokiran ili plugin ne radi na ovom hostu (active_domain?)." -ForegroundColor Yellow
    }
    exit 1
}
