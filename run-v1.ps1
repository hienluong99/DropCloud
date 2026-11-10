# Run all servers for CloudDrop V1
# 1. Start PHP workers
Start-Process -NoNewWindow php -ArgumentList "-S 127.0.0.1:8001 -t services/core-service/public"
Start-Process -NoNewWindow php -ArgumentList "-S 127.0.0.1:8002 -t services/core-service/public"
Start-Process -NoNewWindow php -ArgumentList "-S 127.0.0.1:8003 -t services/core-service/public"
Start-Process -NoNewWindow php -ArgumentList "-S 127.0.0.1:8004 -t services/core-service/public"

# 2. Start Gateway on port 3000
Start-Process -NoNewWindow node -ArgumentList "gateway.js"

Write-Host "CloudDrop V1 is running at: http://localhost:3000" -ForegroundColor Green
