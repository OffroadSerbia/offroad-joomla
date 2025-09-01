Param(
  [Parameter(Mandatory = $true)][string]$SourceRepo,
  [Parameter(Mandatory = $false)][string]$OutPath = "repo-migrate-manifest.json"
)

$ErrorActionPreference = 'Stop'
if (-not $env:GITHUB_TOKEN) { throw "GITHUB_TOKEN not set" }

$headers = @{ Authorization = "Bearer $env:GITHUB_TOKEN"; 'User-Agent' = 'offroad-migrate' }
$owner, $repo = $SourceRepo.Split('/')
$base = "https://api.github.com/repos/$owner/$repo"

Write-Host "Exporting from $SourceRepo ..."

function Get-OrNull($url) {
  try { return Invoke-RestMethod -Uri $url -Headers $headers } catch { return $null }
}

$actionsVars = Get-OrNull "$base/actions/variables"
$envsIndex = Get-OrNull "$base/environments"
$hooks = Get-OrNull "$base/hooks"
$keys = Get-OrNull "$base/keys"
$protMain = Get-OrNull "$base/branches/main/protection"

$manifest = [pscustomobject]@{
  sourceRepo       = $SourceRepo
  actions          = [pscustomobject]@{ variables = $actionsVars.variables }
  environments     = $envsIndex.environments
  webhooks         = $hooks
  deployKeys       = $keys
  branchProtection = @{ main = $protMain }
}

$json = $manifest | ConvertTo-Json -Depth 8
Set-Content -Path $OutPath -Value $json -Encoding UTF8
Write-Host "Manifest saved -> $OutPath"
