Param(
  [Parameter(Mandatory=$true)][string]$DestinationRepo,
  [Parameter(Mandatory=$true)][string]$ManifestPath,
  [Parameter(Mandatory=$false)][string]$SecretsPath = "secrets.psd1"
)

$ErrorActionPreference = 'Stop'
if (-not $env:GITHUB_TOKEN) { throw "GITHUB_TOKEN not set" }

$headers = @{ Authorization = "Bearer $env:GITHUB_TOKEN"; 'User-Agent' = 'offroad-migrate' }
$owner,$repo = $DestinationRepo.Split('/')
$base = "https://api.github.com/repos/$owner/$repo"

Write-Host "Replaying to $DestinationRepo ..."

if (-not (Test-Path $ManifestPath)) { throw "Manifest not found: $ManifestPath" }
$manifest = Get-Content $ManifestPath | ConvertFrom-Json
$secrets  = if (Test-Path $SecretsPath) { Import-PowerShellDataFile $SecretsPath } else { @{} }

function Gh($argv){
  & gh @argv
  if ($LASTEXITCODE -ne 0) { throw "gh failed: $($argv -join ' ')" }
}

# 1) Actions Variables
foreach ($v in ($manifest.actions.variables | Where-Object { $_ })) {
  Gh @('variable','set',$v.name,'--repo',$DestinationRepo,'--body',"$($v.value)")
}

# 2) Repo-level Secrets
if ($secrets.ContainsKey('ACTIONS')) {
  foreach ($k in $secrets.ACTIONS.Keys) {
    $val = $secrets.ACTIONS[$k]
    $p = [System.Diagnostics.Process]::Start((New-Object System.Diagnostics.ProcessStartInfo -Property @{ FileName='gh'; Arguments="secret set $k --repo $DestinationRepo --body -"; RedirectStandardInput=$true; UseShellExecute=$false }))
    $p.StandardInput.Write($val)
    $p.StandardInput.Close()
    $p.WaitForExit()
    if ($p.ExitCode -ne 0) { throw "gh secret set failed: $k" }
  }
}

# 3) Environments & Env Secrets
foreach ($env in ($manifest.environments | Where-Object { $_ })) {
  Invoke-RestMethod -Method Put -Uri "$base/environments/$($env.name)" -Headers $headers | Out-Null
}
if ($secrets.ContainsKey('ENVIRONMENTS')) {
  foreach ($envName in $secrets.ENVIRONMENTS.Keys) {
    foreach ($key in $secrets.ENVIRONMENTS[$envName].Keys) {
      $val = $secrets.ENVIRONMENTS[$envName][$key]
      $p = [System.Diagnostics.Process]::Start((New-Object System.Diagnostics.ProcessStartInfo -Property @{ FileName='gh'; Arguments="secret set $key --repo $DestinationRepo -e $envName --body -"; RedirectStandardInput=$true; UseShellExecute=$false }))
      $p.StandardInput.Write($val)
      $p.StandardInput.Close()
      $p.WaitForExit()
      if ($p.ExitCode -ne 0) { throw "gh env secret set failed: $envName/$key" }
    }
  }
}

# 4) Dependabot secrets
if ($secrets.ContainsKey('DEPENDABOT')) {
  foreach ($k in $secrets.DEPENDABOT.Keys) {
    $val = $secrets.DEPENDABOT[$k]
    $p = [System.Diagnostics.Process]::Start((New-Object System.Diagnostics.ProcessStartInfo -Property @{ FileName='gh'; Arguments="secret set $k --repo $DestinationRepo --app dependabot --body -"; RedirectStandardInput=$true; UseShellExecute=$false }))
    $p.StandardInput.Write($val)
    $p.StandardInput.Close()
    $p.WaitForExit()
    if ($p.ExitCode -ne 0) { throw "gh dependabot secret set failed: $k" }
  }
}

# 5) Deploy Keys
foreach ($k in ($manifest.deployKeys | Where-Object { $_ })) {
  $form = @{
    title = $k.title
    key   = $k.key
    read_only = $k.read_only
  }
  Invoke-RestMethod -Method Post -Uri "$base/keys" -Headers $headers -Body ($form | ConvertTo-Json) -ContentType 'application/json' | Out-Null
}

# 6) Webhooks (requires secrets provided externally)
foreach ($hook in ($manifest.webhooks | Where-Object { $_ })) {
  $cfg = $hook.config
  $url = $cfg.url
  $payload = [ordered]@{
    name = 'web'
    active = $hook.active
    events = $hook.events
    config = [ordered]@{
      url = $url
      content_type = $cfg.content_type
      insecure_ssl = $cfg.insecure_ssl
    }
  }
  if ($secrets.ContainsKey('WEBHOOK_SECRETS') -and $secrets.WEBHOOK_SECRETS.ContainsKey($url)) {
    $payload.config.secret = $secrets.WEBHOOK_SECRETS[$url]
  }
  Invoke-RestMethod -Method Post -Uri "$base/hooks" -Headers $headers -Body ($payload | ConvertTo-Json -Depth 6) -ContentType 'application/json' | Out-Null
}

# 7) Branch protection (main)
if ($manifest.branchProtection.main) {
  $bp = $manifest.branchProtection.main
  $body = [ordered]@{}
  if ($bp.required_status_checks) { $body.required_status_checks = $bp.required_status_checks }
  if ($bp.enforce_admins) { $body.enforce_admins = $bp.enforce_admins.enabled }
  if ($bp.required_pull_request_reviews) { $body.required_pull_request_reviews = $bp.required_pull_request_reviews }
  if ($bp.restrictions) { $body.restrictions = $bp.restrictions }
  if ($bp.required_linear_history) { $body.required_linear_history = $bp.required_linear_history.enabled }
  if ($bp.allow_force_pushes) { $body.allow_force_pushes = $bp.allow_force_pushes.enabled }
  if ($bp.allow_deletions) { $body.allow_deletions = $bp.allow_deletions.enabled }
  if ($bp.block_creations) { $body.block_creations = $bp.block_creations.enabled }
  Invoke-RestMethod -Method Put -Uri "$base/branches/main/protection" -Headers $headers -Body ($body | ConvertTo-Json -Depth 8) -ContentType 'application/json' | Out-Null
}

Write-Host "Replay complete."
