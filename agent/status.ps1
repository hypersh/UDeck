# UDeck 자동 기록 상태 확인

$ErrorActionPreference = 'Stop'
[Console]::OutputEncoding = [System.Text.Encoding]::UTF8
$env:PYTHONIOENCODING = 'utf-8'
$here = Split-Path -Parent $MyInvocation.MyCommand.Path

# Python 찾기: py 런처 → python
function Find-Python {
    $cands = @(
        @{ exe = 'py';     pre = @('-3') },
        @{ exe = 'python'; pre = @() }
    )
    foreach ($c in $cands) {
        try {
            $pre = $c.pre
            $out = & $c.exe @pre -c "import sys; print(sys.executable)" 2>$null
            if ($LASTEXITCODE -eq 0 -and $out) {
                $exe = ([string]($out | Select-Object -Last 1)).Trim()
                if (Test-Path $exe) { return $exe }
            }
        } catch { }
    }
    return $null
}
$py = Find-Python
if (-not $py) {
    Write-Host 'Python을 찾지 못했습니다. https://www.python.org 에서 설치한 뒤 다시 실행하세요.' -ForegroundColor Red
    exit 1
}
Write-Host "Python: $py"

& $py (Join-Path $here 'udeck_setup.py') status
& $py (Join-Path $here 'udeck_setup.py') diagnose
Write-Host ''
Read-Host '엔터를 누르면 창을 닫습니다'
