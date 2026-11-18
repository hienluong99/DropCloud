Write-Host "=== Stopping CloudDrop V4 Services ===" -ForegroundColor Yellow
Get-Process -Name php, node -ErrorAction SilentlyContinue | Stop-Process -Force
docker compose down
Write-Host "All CloudDrop V4 processes & containers stopped." -ForegroundColor Green
