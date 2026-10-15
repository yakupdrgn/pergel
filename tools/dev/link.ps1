# Links this repo into a local WordPress install (no admin rights needed).
#
#   tools\dev\link.ps1 [-WpRoot C:\xampp\htdocs\pergel-tema] [-Unlink]
#
# - wp-content\themes\pergel           -> <repo>\theme   (directory junction)
# - wp-content\plugins\pergel-engine   -> <repo>\engine  (directory junction)
# - wp-content\mu-plugins\pergel-perf-probe.php  loads tools\budget\perf-probe.php
#   (dev only; it records nothing unless a request sends X-Pergel-Perf)
param(
    [string]$WpRoot = 'C:\xampp\htdocs\pergel-tema',
    [switch]$Unlink
)
$ErrorActionPreference = 'Stop'
$Repo    = (Resolve-Path "$PSScriptRoot\..\..").Path
$Content = Join-Path $WpRoot 'wp-content'
if (-not (Test-Path "$WpRoot\wp-config.php")) { throw "No WordPress install at $WpRoot" }

$Links = @(
    @{ Path = "$Content\themes\pergel";         Target = "$Repo\theme" },
    @{ Path = "$Content\plugins\pergel-engine"; Target = "$Repo\engine" }
)
$Probe = "$Content\mu-plugins\pergel-perf-probe.php"

foreach ($l in $Links) {
    $item = Get-Item $l.Path -Force -ErrorAction SilentlyContinue
    if ($item -and $item.LinkType -ne 'Junction') { throw "$($l.Path) exists and is not a junction; move it away first." }
    if ($item) { $item.Delete() }  # removes the junction only, never the target
    if (-not $Unlink) {
        New-Item -ItemType Junction -Path $l.Path -Target $l.Target | Out-Null
        "linked  $($l.Path) -> $($l.Target)"
    } else { "removed $($l.Path)" }
}

if ($Unlink) {
    Remove-Item $Probe -ErrorAction SilentlyContinue; "removed $Probe"
} else {
    New-Item -ItemType Directory -Force "$Content\mu-plugins" | Out-Null
    $src = ("$Repo\tools\budget\perf-probe.php") -replace '\\', '/'
    Set-Content -Path $Probe -Encoding ascii -Value @"
<?php
// Pergel dev: performance probe from the repo (tools/dev/link.ps1). Delete to disable.
if ( is_file( '$src' ) ) {
	require '$src';
}
"@
    "loader  $Probe"
}
