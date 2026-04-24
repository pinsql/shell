# siteshell.ps1 — Windows PowerShell site helper (dev serve, backup, doctor).
# Legitimate site ops only — not a webshell or Gecko.php clone.

$ErrorActionPreference = "Stop"
$Version = "1.0.0"
$Prog = "siteshell.ps1"

function Show-Usage {
    @"
$Prog $Version — site management helper (Windows)

Usage:
  .\$Prog doctor              Check git, php, node, curl, ssh
  .\$Prog serve [url]         Start PHP built-in server (default http://127.0.0.1:8080/)
  .\$Prog backup [path]       Zip backup of path (default: current directory)
  .\$Prog logs <file>         Follow a log file (Get-Content -Wait)
  .\$Prog deploy-print        Show example scp/rsync (use WSL or Git Bash for rsync)
  .\$Prog version             Print version

On Linux use: bin/siteshell (Bash)
"@ | Write-Output
}

function Test-Cmd($Name) {
    $c = Get-Command $Name -ErrorAction SilentlyContinue
    if ($c) { "  OK  $Name  $($c.Source)" } else { "  --  $Name  (not found)" }
}

function Invoke-Doctor {
    Write-Output "=== $Prog doctor ==="
    @("git", "php", "node", "npm", "curl", "ssh", "scp") | ForEach-Object { Write-Output (Test-Cmd $_) }
    $wp = Get-Command wp -ErrorAction SilentlyContinue
    if ($wp) { Write-Output "  OK  wp  $($wp.Source)" } else { Write-Output "  --  wp  (optional)" }
    Write-Output "  cwd: $(Get-Location)"
    $drv = (Get-Location).Drive.Name
    Get-PSDrive $drv -ErrorAction SilentlyContinue | ForEach-Object {
        Write-Output "  disk: $($_.Used / 1GB | ForEach-Object { '{0:N1} GB used' -f $_ }) on $drv`:"
    }
}

function Invoke-Serve {
    param([string]$Url = "http://127.0.0.1:8080/")
    $php = Get-Command php -ErrorAction SilentlyContinue
    if (-not $php) { throw "php not found in PATH" }
    $u = [Uri]$Url
    $bindHost = $u.Host
    $port = if ($u.Port -gt 0) { $u.Port } else { 8080 }
    $docRoot = (Get-Location).Path
    Write-Output "PHP built-in server at $Url (Ctrl+C to stop), root: $docRoot"
    & php -S "${bindHost}:${port}" -t $docRoot
}

function Invoke-Backup {
    param([string]$Path = ".")
    $full = (Resolve-Path $Path).Path
    $name = Split-Path $full -Leaf
    $ts = Get-Date -Format "yyyyMMdd-HHmmss"
    $zip = Join-Path (Get-Location).Path "${name}-backup-${ts}.zip"
    if (Test-Path $zip) { Remove-Item $zip -Force }
    $items = @(Get-ChildItem -LiteralPath $full -Force | ForEach-Object { $_.FullName })
    if ($items.Count -eq 0) {
        throw "nothing to archive under: $full"
    }
    Compress-Archive -LiteralPath $items -DestinationPath $zip -Force
    Write-Output "Done: $zip"
}

function Invoke-Logs {
    param([string]$File)
    if (-not $File) { throw "usage: .\$Prog logs <file>" }
    if (-not (Test-Path $File -PathType Leaf)) { throw "not a file: $File" }
    Get-Content $File -Wait -Tail 50
}

function Invoke-DeployPrint {
    $user = if ($env:REMOTE_USER) { $env:REMOTE_USER } else { "user" }
    $remoteHost = if ($env:REMOTE_HOST) { $env:REMOTE_HOST } else { "example.com" }
    $rpath = if ($env:REMOTE_PATH) { $env:REMOTE_PATH } else { "/var/www/html" }
    $lpath = if ($env:LOCAL_PATH) { $env:LOCAL_PATH } else { "." }
    @"

# From PowerShell (OpenSSH), recursive copy example:
# scp -r ${lpath}\* ${user}@${remoteHost}:${rpath}/

# For rsync, use WSL or Git Bash:
# rsync -avz --delete --exclude '.git' --exclude 'node_modules' ${lpath}/ ${user}@${remoteHost}:${rpath}/

Set env: REMOTE_USER, REMOTE_HOST, REMOTE_PATH, LOCAL_PATH for your host.
"@
}

$sub = $args[0]
$rest = @()
if ($args.Count -gt 1) { $rest = $args[1..($args.Count - 1)] }

switch ($sub) {
    $null { Show-Usage }
    "" { Show-Usage }
    "-h" { Show-Usage }
    "--help" { Show-Usage }
    "help" { Show-Usage }
    "doctor" { Invoke-Doctor }
    "serve" {
        $u = if ($rest.Count -gt 0) { $rest[0] } else { "http://127.0.0.1:8080/" }
        Invoke-Serve -Url $u
    }
    "backup" {
        $p = if ($rest.Count -gt 0) { $rest[0] } else { "." }
        Invoke-Backup -Path $p
    }
    "logs" { Invoke-Logs -File $rest[0] }
    "deploy-print" { Invoke-DeployPrint }
    "version" { Write-Output $Version }
    "--version" { Write-Output $Version }
    default { throw "$Prog: unknown command: $sub (try --help)" }
}
