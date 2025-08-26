param(
  [string]$Message = "chore: quick push"
)

# Fail on errors
$ErrorActionPreference = 'Stop'

# Detect current branch
$branch = (git rev-parse --abbrev-ref HEAD).Trim()
if (-not $branch) { throw "Cannot detect git branch" }

# Stage changed files
git add -A

# Commit if there are staged changes
if ($null -ne (git diff --cached --name-only) -and (git diff --cached --name-only).Trim() -ne '') {
  git commit -m $Message | Out-Host
} else {
  Write-Host "Nothing to commit."
}

# Push to origin/<branch>
 git push origin $branch | Out-Host

Write-Host "Done: pushed to origin/$branch"
