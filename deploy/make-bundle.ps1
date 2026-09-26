# Rebuilds glamorous-bundle.zip from public_html/ for HestiaCP upload.
# Run from repo root: .\deploy\make-bundle.ps1
$root = Split-Path -Parent $MyInvocation.MyCommand.Path
$repo = Split-Path -Parent $root
$src = Join-Path $repo 'public_html'
$dst = Join-Path $repo 'glamorous-bundle.zip'
if (Test-Path -LiteralPath $dst) { Remove-Item -LiteralPath $dst }
Compress-Archive -Path (Join-Path $src '*') -DestinationPath $dst
Write-Output "Wrote $dst"
