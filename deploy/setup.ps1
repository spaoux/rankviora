$ErrorActionPreference = 'Stop'
$root = Split-Path $PSScriptRoot -Parent
$path = Join-Path $root '.env'
if (!(Test-Path -LiteralPath $path)) {
    $bytes = New-Object byte[] 32
    [System.Security.Cryptography.RandomNumberGenerator]::Create().GetBytes($bytes)
    $secret = ([BitConverter]::ToString($bytes)).Replace('-', '')
    [System.IO.File]::WriteAllText($path, "DB_PASSWORD=$secret`n")
    Write-Host 'Local database secret generated.'
} else { Write-Host 'Existing environment preserved.' }
