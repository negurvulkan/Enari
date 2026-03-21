param(
    [string]$TargetPath = '..\loreroot-public-rewrite',
    [string]$BundlePath = '..\loreroot-pre-public.bundle',
    [string]$MainBranch = 'main',
    [string]$ReleaseTag = 'v1.2'
)

$ErrorActionPreference = 'Stop'

function Invoke-Git {
    param(
        [Parameter(Mandatory = $true)]
        [string[]]$Arguments
    )

    & git @Arguments
    if ($LASTEXITCODE -ne 0) {
        throw "Git command failed: git $($Arguments -join ' ')"
    }
}

$repoRoot = Resolve-Path (Join-Path $PSScriptRoot '..')
$resolvedTarget = [System.IO.Path]::GetFullPath((Join-Path $repoRoot $TargetPath))
$resolvedBundle = [System.IO.Path]::GetFullPath((Join-Path $repoRoot $BundlePath))

if (Test-Path $resolvedTarget) {
    throw "Target path already exists: $resolvedTarget"
}

Push-Location $repoRoot
try {
    if (-not (Test-Path '.git')) {
        throw "No Git repository found at $repoRoot"
    }

    Write-Host "Creating safety bundle at $resolvedBundle" -ForegroundColor Cyan
    Invoke-Git -Arguments @('bundle', 'create', $resolvedBundle, '--all')

    Write-Host "Cloning rewrite workspace to $resolvedTarget" -ForegroundColor Cyan
    Invoke-Git -Arguments @('clone', '--no-local', '.', $resolvedTarget)

    Push-Location $resolvedTarget
    try {
        Write-Host 'Creating fresh public root commit' -ForegroundColor Cyan
        Invoke-Git -Arguments @('checkout', '--orphan', 'public-main')
        Invoke-Git -Arguments @('add', '-A')
        Invoke-Git -Arguments @('commit', '-m', 'Initial public release of LoreRoot')

        foreach ($tag in @('v1.1', $ReleaseTag)) {
            & git tag -d $tag *> $null
        }

        Invoke-Git -Arguments @('tag', '-a', $ReleaseTag, '-m', "Public release $ReleaseTag")
        Invoke-Git -Arguments @('branch', '-M', 'public-main', $MainBranch)

        Write-Host 'Scanning rewritten history for private legacy markers' -ForegroundColor Cyan
        $auditMatches = git rev-list --objects --all | Select-String -Pattern 'Enari|01_Weltbau|01_Worldbuilding|99_Medien'
        if ($auditMatches) {
            Write-Warning 'Legacy markers are still present in the rewrite clone. Review before any push.'
            $auditMatches | ForEach-Object { Write-Host $_.Line }
        } else {
            Write-Host 'No legacy audit markers found in rewritten history.' -ForegroundColor Green
        }

        Write-Host ''
        Write-Host 'Rewrite clone prepared. Review it carefully, then push manually if it is clean:' -ForegroundColor Green
        Write-Host "  Set-Location $resolvedTarget"
        Write-Host "  git push --force origin $MainBranch"
        Write-Host '  git push --force origin :refs/tags/v1.1'
        Write-Host "  git push --force origin $ReleaseTag"
    } finally {
        Pop-Location
    }
} finally {
    Pop-Location
}
