# inspect-v2rayn.ps1 — Stage 0, READ-ONLY.
# Reports the installed v2rayN version and folder layout. Changes nothing.
# It never prints subscription links: those live in guiNDB.db, which this script does not open.
#
# How to run (in PowerShell):
#   powershell -ExecutionPolicy Bypass -File .\inspect-v2rayn.ps1
#   powershell -ExecutionPolicy Bypass -File .\inspect-v2rayn.ps1 -V2rayNDir "D:\Apps\v2rayN"
# Output: v2rayn-inspect.txt next to this script. Send that file back.

param([string]$V2rayNDir = "")

$out = Join-Path $PSScriptRoot "v2rayn-inspect.txt"
$lines = New-Object System.Collections.Generic.List[string]
function W($s) { $lines.Add([string]$s) }

W "=== v2rayN inspection $(Get-Date -Format 'yyyy-MM-dd HH:mm') ==="
W "Windows: $([Environment]::OSVersion.VersionString)  64-bit: $([Environment]::Is64BitOperatingSystem)"

# 1) Find the v2rayN folder: argument, then running process, then common locations
if (-not $V2rayNDir) {
    $p = Get-Process -Name v2rayN -ErrorAction SilentlyContinue | Select-Object -First 1
    if ($p -and $p.Path) { $V2rayNDir = Split-Path $p.Path }
}
if (-not $V2rayNDir) {
    $candidates = @("$env:USERPROFILE\Desktop\v2rayN", "$env:USERPROFILE\Downloads\v2rayN",
                    "C:\v2rayN", "D:\v2rayN", "$env:LOCALAPPDATA\v2rayN", "$env:ProgramFiles\v2rayN")
    $V2rayNDir = $candidates | Where-Object { Test-Path (Join-Path $_ "v2rayN.exe") } | Select-Object -First 1
}
if (-not $V2rayNDir -or -not (Test-Path (Join-Path $V2rayNDir "v2rayN.exe"))) {
    W "v2rayN.exe NOT FOUND. Re-run with -V2rayNDir <folder that contains v2rayN.exe>"
    $lines | Set-Content -Encoding UTF8 $out; Write-Host "Saved: $out"; exit 1
}
W "v2rayN folder: $V2rayNDir"
W "Running now: $([bool](Get-Process -Name v2rayN -ErrorAction SilentlyContinue))"

$exe = Get-Item (Join-Path $V2rayNDir "v2rayN.exe")
W "v2rayN.exe version: $($exe.VersionInfo.ProductVersion)  file: $($exe.VersionInfo.FileVersion)"

# 2) Folder layout (top level + guiConfigs + bin, names and sizes only)
foreach ($sub in @("", "guiConfigs", "bin", "binConfigs")) {
    $d = Join-Path $V2rayNDir $sub
    if (Test-Path $d) {
        W ""; W "--- [$(if ($sub) { $sub } else { 'root' })] ---"
        Get-ChildItem $d -Force | ForEach-Object {
            W ("{0,-40} {1,12} {2}" -f $_.Name, $(if ($_.PSIsContainer) { "<DIR>" } else { $_.Length }), $_.LastWriteTime)
        }
    }
}

# 3) Cores bundled in bin (xray / sing-box) and their versions
W ""; W "--- cores ---"
Get-ChildItem (Join-Path $V2rayNDir "bin") -Recurse -Include xray.exe, sing-box.exe, mihomo*.exe -ErrorAction SilentlyContinue |
    ForEach-Object {
        $v = try { (& $_.FullName version 2>&1 | Select-Object -First 1) } catch { "?" }
        W "$($_.FullName.Replace($V2rayNDir, '.'))  ->  $v"
    }

# 4) guiNConfig.json: only non-sensitive keys (structure + active server id + proxy mode)
$cfgPath = Join-Path $V2rayNDir "guiConfigs\guiNConfig.json"
W ""; W "--- guiNConfig.json ---"
if (Test-Path $cfgPath) {
    try {
        $cfg = Get-Content $cfgPath -Raw -Encoding UTF8 | ConvertFrom-Json
        W "Top-level keys: $(($cfg.PSObject.Properties.Name) -join ', ')"
        W "IndexId (active server) present: $([bool]$cfg.IndexId)"
        W "SystemProxyItem.SysProxyType: $($cfg.SystemProxyItem.SysProxyType)"
        W "TunModeItem.EnableTun: $($cfg.TunModeItem.EnableTun)"
        W "GuiItem.AutoRun: $($cfg.GuiItem.AutoRun)"
        W "SpeedTestItem: $(($cfg.SpeedTestItem | ConvertTo-Json -Compress))"
        W "Inbound local port: $(($cfg.Inbound | Select-Object -First 1).LocalPort)"
    } catch { W "Could not parse: $($_.Exception.Message)" }
} else { W "Not found at $cfgPath" }

# 5) Tools available for the automation
W ""; W "--- tools ---"
$py = Get-Command python -ErrorAction SilentlyContinue
W "python: $(if ($py) { (& python --version 2>&1) } else { 'not installed' })"
W "PowerShell: $($PSVersionTable.PSVersion)"

$lines | Set-Content -Encoding UTF8 $out
Write-Host "Saved: $out"
