<#
.SYNOPSIS
  Scrubuje procurele tajne iz GIT istorije i forsira push na remote.

.UPOZORENJE
  - OVO JE DESTRUKTIVNO po istoriju: re-snapshot celog repozitorijuma.
  - Svi saradnici moraju da pauziraju rad, i posle će morati da re-clone.
  - Pre pokretanja: obavezno REVOKE token(a) u GitHub Settings.

.PREDUSLOVI
  - Preporučen alat: git filter-repo (brži, pouzdaniji od filter-branch).
    Instalacija opcije:
      1) Python: pip install git-filter-repo
      2) ili preuzmi single-file skriptu: https://github.com/newren/git-filter-repo
  - Alternativa: BFG Repo-Cleaner (zahteva Java + BFG .jar)

.KORIŠĆENJE
  Pokreni iz root foldera repozitorijuma:
    pwsh -File tools/git-history-scrub.ps1 -Patterns @('docs/Untitled-1.txt') -Remote origin -MainBranch main -DryRun
  Ako je diff OK, pokreni bez -DryRun da primeni izmene i force-push.

.PARAMETER Patterns
  Niz putanja/pattern-a koji treba ukloniti iz SVIH commit-a (npr. 'docs/Untitled-1.txt').

.PARAMETER Remote
  Ime remote-a (podrazumevano 'origin').

.PARAMETER MainBranch
  Glavna grana (podrazumevano 'main').

.PARAMETER DryRun
  Ako je postavljeno, radi samo simulaciju (bez menjanja istorije).
#>
param(
  [string[]]$Patterns = @('docs/Untitled-1.txt'),
  [string]$Remote = 'origin',
  [string]$MainBranch = 'main',
  [switch]$DryRun
)

function Assert-CleanWorkspace {
  $status = (git status --porcelain)
  if ($status) {
    throw 'Workspace nije čist. Commit/stash ili revert pre pokretanja.'
  }
}

function Set-BranchIfNeeded([string]$branch) {
  $current = (git rev-parse --abbrev-ref HEAD).Trim()
  if ($current -ne $branch) {
    git checkout -B $branch | Out-Null
  }
}

function Backup-Refs {
  $ts = Get-Date -Format 'yyyyMMdd-HHmmss'
  $backupRef = "backup/pre-scrub-$ts"
  git tag $backupRef | Out-Null
  Write-Host "Kreiran tag: $backupRef" -ForegroundColor Yellow
}

function Test-FilterRepoInstalled {
  try {
    git filter-repo --version | Out-Null
    return $true
  } catch {
    return $false
  }
}

function Invoke-FilterRepo([string[]]$paths, [switch]$dry) {
  $cmdArgs = @('--force')
  foreach ($p in $paths) { $cmdArgs += @('--path', $p) }
  $cmdArgs += '--invert-paths'
  if ($dry) { $cmdArgs += '--dry-run' }
  Write-Host "git filter-repo $($cmdArgs -join ' ')" -ForegroundColor Cyan
  git filter-repo @cmdArgs
}

try {
  Assert-CleanWorkspace
  Set-BranchIfNeeded -branch $MainBranch
  Backup-Refs

  if (-not (Test-FilterRepoInstalled)) {
    throw 'git filter-repo nije instaliran. Instaliraj ga (pip install git-filter-repo) ili pokreni BFG ručno.'
  }

  Invoke-FilterRepo -paths $Patterns -dry:$DryRun

  if ($DryRun) {
    Write-Host 'Dry-run završen. Ako je sve OK, pokreni bez -DryRun da primeniš promene.' -ForegroundColor Green
    exit 0
  }

  # Garbage collection preporuka posle filtera
  git reflog expire --expire=now --all | Out-Null
  git gc --prune=now --aggressive | Out-Null

  # Force-push svih grana i tagova
  git push $Remote --force --all
  git push $Remote --force --tags

  Write-Host 'Scrub istorije završen. Svi saradnici treba da re-clone repozitorijum.' -ForegroundColor Green
}
catch {
  Write-Error $_
  exit 1
}
