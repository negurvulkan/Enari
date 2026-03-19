[CmdletBinding()]
param(
    [switch]$Force,
    [string]$AdminUsername = 'admin',
    [string]$AdminPassword,
    [switch]$SkipReleaseCheck
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

function Write-Step {
    param(
        [Parameter(Mandatory = $true)]
        [string]$Message
    )

    Write-Host "[setup] $Message" -ForegroundColor Cyan
}

function Get-PhpPath {
    $command = Get-Command php -ErrorAction SilentlyContinue
    if ($null -eq $command) {
        throw "PHP CLI wurde nicht gefunden. Stelle sicher, dass 'php' im PATH verfuegbar ist."
    }

    return $command.Source
}

function Test-IsInteractiveSession {
    try {
        return [Environment]::UserInteractive -and -not [Console]::IsInputRedirected
    } catch {
        return $Host.Name -ne 'Default Host'
    }
}

function ConvertTo-PlainText {
    param(
        [Parameter(Mandatory = $true)]
        [Security.SecureString]$SecureValue
    )

    $bstr = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($SecureValue)
    try {
        return [Runtime.InteropServices.Marshal]::PtrToStringBSTR($bstr)
    } finally {
        [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($bstr)
    }
}

function Read-ConfirmedPassword {
    $first = Read-Host 'Admin-Passwort' -AsSecureString
    $second = Read-Host 'Admin-Passwort wiederholen' -AsSecureString

    $firstPlain = ConvertTo-PlainText -SecureValue $first
    $secondPlain = ConvertTo-PlainText -SecureValue $second

    if ([string]::IsNullOrWhiteSpace($firstPlain)) {
        throw 'Das Admin-Passwort darf nicht leer sein.'
    }

    if ($firstPlain -cne $secondPlain) {
        throw 'Die eingegebenen Passwoerter stimmen nicht ueberein.'
    }

    return $firstPlain
}

function ConvertTo-PhpSingleQuoted {
    param(
        [Parameter(Mandatory = $true)]
        [AllowEmptyString()]
        [string]$Value
    )

    $escaped = $Value.Replace('\', '\\').Replace("'", "\'")
    return "'$escaped'"
}

function Replace-ConfigSettingLine {
    param(
        [Parameter(Mandatory = $true)]
        [AllowEmptyString()]
        [string[]]$Lines,
        [Parameter(Mandatory = $true)]
        [string]$Key,
        [Parameter(Mandatory = $true)]
        [string]$Value,
        [Parameter(Mandatory = $true)]
        [string]$Label
    )

    $updated = $false
    for ($index = 0; $index -lt $Lines.Length; $index++) {
        $line = $Lines[$index]
        if (-not $line.TrimStart().StartsWith("'$Key' =>")) {
            continue
        }

        $indent = ([regex]::Match($line, '^\s*')).Value
        $Lines[$index] = "$indent'$Key' => $Value,"
        $updated = $true
        break
    }

    if (-not $updated) {
        throw "Die Einstellung '$Label' konnte in site.config.php nicht gefunden werden."
    }

    return $Lines
}

function Write-Utf8NoBomFile {
    param(
        [Parameter(Mandatory = $true)]
        [string]$Path,
        [Parameter(Mandatory = $true)]
        [string]$Content
    )

    $encoding = New-Object System.Text.UTF8Encoding($false)
    [System.IO.File]::WriteAllText($Path, $Content, $encoding)
}

function Invoke-PhpCli {
    param(
        [Parameter(Mandatory = $true)]
        [string]$PhpPath,
        [Parameter(Mandatory = $true)]
        [string[]]$Arguments
    )

    $output = & $PhpPath @Arguments 2>&1
    $exitCode = $LASTEXITCODE

    if ($exitCode -ne 0) {
        $details = if ($output) { ($output | Out-String).Trim() } else { 'Keine weitere Ausgabe.' }
        throw "PHP-Aufruf fehlgeschlagen: php $($Arguments -join ' ')`n$details"
    }

    return ($output | Out-String).Trim()
}

$repoRoot = [System.IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
$sampleConfigPath = Join-Path $repoRoot 'site.config.sample.php'
$configPath = Join-Path $repoRoot 'site.config.php'
$phpPath = Get-PhpPath

if (-not (Test-Path $sampleConfigPath)) {
    throw "Die Beispielkonfiguration fehlt: $sampleConfigPath"
}

$configWasGenerated = $false
$passwordWasPrompted = $false

if ((Test-Path $configPath) -and -not $Force) {
    Write-Step 'site.config.php existiert bereits und bleibt unveraendert. Fuer eine frische Webspace-Konfiguration erneut mit -Force ausfuehren.'
    if ($PSBoundParameters.ContainsKey('AdminPassword') -or ($AdminUsername -ne 'admin')) {
        Write-Warning 'Vorhandene site.config.php wurde nicht ueberschrieben. Uebergebene Admin-Zugangsdaten wurden ignoriert.'
    }
} else {
    if (-not $PSBoundParameters.ContainsKey('AdminPassword') -or [string]::IsNullOrWhiteSpace($AdminPassword)) {
        if (-not (Test-IsInteractiveSession)) {
            throw "Fuer nicht-interaktive Aufrufe ist -AdminPassword erforderlich."
        }

        $AdminPassword = Read-ConfirmedPassword
        $passwordWasPrompted = $true
    }

    if ([string]::IsNullOrWhiteSpace($AdminUsername)) {
        $AdminUsername = 'admin'
    }

    Write-Step 'Erzeuge lokale Runtime-Konfiguration aus site.config.sample.php.'
    Copy-Item -Path $sampleConfigPath -Destination $configPath -Force
    $configWasGenerated = $true

    Write-Step 'Erzeuge password_hash() ueber die lokale PHP-CLI.'
    $passwordHash = Invoke-PhpCli -PhpPath $phpPath -Arguments @(
        '-r',
        'echo password_hash($argv[1], PASSWORD_DEFAULT);',
        $AdminPassword
    )

    $configContent = [System.IO.File]::ReadAllText($configPath)
    $lineEnding = if ($configContent.Contains("`r`n")) { "`r`n" } else { "`n" }
    $hasTrailingLineEnding = $configContent.EndsWith("`r`n") -or $configContent.EndsWith("`n")
    $configLines = $configContent -split "\r?\n"

    $configLines = Replace-ConfigSettingLine -Lines $configLines -Key 'username' -Value (ConvertTo-PhpSingleQuoted $AdminUsername) -Label 'admin.username'
    $configLines = Replace-ConfigSettingLine -Lines $configLines -Key 'password' -Value "''" -Label 'admin.password'
    $configLines = Replace-ConfigSettingLine -Lines $configLines -Key 'passwordHash' -Value (ConvertTo-PhpSingleQuoted $passwordHash) -Label 'admin.passwordHash'
    $configLines = Replace-ConfigSettingLine -Lines $configLines -Key 'trustedLocalFallback' -Value 'false' -Label 'admin.trustedLocalFallback'

    $configContent = [string]::Join($lineEnding, $configLines)
    if ($hasTrailingLineEnding) {
        $configContent += $lineEnding
    }
    Write-Utf8NoBomFile -Path $configPath -Content $configContent

    Write-Step 'Lokale site.config.php wurde gehaertet: direkter Username, gehashter Admin-Zugang und kein trustedLocalFallback.'
}

Push-Location $repoRoot
try {
    Write-Step 'Pruefe die lokale Runtime-Konfiguration.'
    & $phpPath 'scripts/validate-config.php'
    if ($LASTEXITCODE -ne 0) {
        throw 'validate-config.php ist fehlgeschlagen.'
    }

    if (-not $SkipReleaseCheck) {
        Write-Step 'Fuehre den strikten Release-Check aus.'
        & $phpPath 'scripts/release-check.php' '--strict'
        if ($LASTEXITCODE -ne 0) {
            throw 'release-check.php --strict ist fehlgeschlagen.'
        }
    } else {
        Write-Step 'Release-Check wurde mit -SkipReleaseCheck uebersprungen.'
    }
} finally {
    Pop-Location
}

Write-Host ''
Write-Host 'Webspace-Checkliste' -ForegroundColor Green
Write-Host '- Apache-Upload: .htaccess, index.php, site.config.php, assets/, cms/, config/, content/, pages/ und themes/.'
Write-Host '- site.config.php ist instanzbezogen und muss mit hochgeladen, aber nie ins Repo committed werden.'
Write-Host '- docs/, scripts/, .git/, .codex_tmp/, private-content/, private-pages/, private-backups/ und cache/ gehoeren nicht in einen normalen Webspace-Upload.'
Write-Host '- router.php ist fuer den lokalen PHP-Entwicklungsserver gedacht und auf Apache-Webspace normalerweise nicht noetig.'
Write-Host '- Stelle sicher, dass PHP auf dem Webspace das Verzeichnis cache/ anlegen bzw. beschreiben darf.'
Write-Host ''
Write-Host 'Setup abgeschlossen.' -ForegroundColor Green

if ($configWasGenerated) {
    Write-Host "- site.config.php wurde neu erzeugt."
}

if ($passwordWasPrompted) {
    Write-Host '- Das Admin-Passwort wurde interaktiv abgefragt und nur gehasht gespeichert.'
}
