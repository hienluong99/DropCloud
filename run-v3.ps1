Write-Host "=== Starting CloudDrop V3 (Edge + Redis Lua + AWS SQS + Laravel Worker) ===" -ForegroundColor Cyan

# 0. Check Docker Desktop is running
docker info *> $null
if ($LASTEXITCODE -ne 0) {
    Write-Host "[ERROR] Docker Desktop chua duoc khoi dong! Hay mo Docker Desktop va thu lai." -ForegroundColor Red
    exit 1
}

# 1. Start Redis & LocalStack (AWS SQS + S3) via Docker Compose
Write-Host "[1/5] Starting Redis & LocalStack containers..." -ForegroundColor Yellow
docker compose up -d
if ($LASTEXITCODE -ne 0) {
    Write-Host "[ERROR] docker compose up -d that bai." -ForegroundColor Red
    exit 1
}

# Wait for LocalStack SQS queue to be initialized
Write-Host "      Waiting for LocalStack SQS & Redis to be ready..." -ForegroundColor DarkGray
$ready = $false
for ($i = 0; $i -lt 20; $i++) {
    try {
        $health = Invoke-RestMethod -Uri "http://127.0.0.1:4566/_localstack/health" -TimeoutSec 2 -ErrorAction Stop
        if ($health.services.sqs -eq "available" -or $health.services.sqs -eq "running") {
            $ready = $true
            break
        }
    } catch {}
    Start-Sleep -Seconds 2
}

if (-not $ready) {
    Write-Host "[WARN] LocalStack mat nhieu thoi gian khoi dong, tiep tuc khoi tao..." -ForegroundColor Yellow
}

# Ensure SQS queues exist (phong truong hop init-aws.sh bi loi CRLF tren Windows)
docker exec clouddrop-localstack awslocal sqs create-queue --queue-name clouddrop-orders-dlq *> $null
docker exec clouddrop-localstack awslocal sqs create-queue --queue-name clouddrop-orders-queue *> $null
docker exec clouddrop-localstack awslocal sqs create-queue --queue-name clouddrop-expiration-queue --attributes '{"DelaySeconds":"300"}' *> $null

# 2. Start 4 Laravel Core HTTP Workers
Write-Host "[2/5] Starting 4 PHP Laravel Core Workers (8001-8004)..." -ForegroundColor Yellow
Start-Process -NoNewWindow php -ArgumentList "-S 127.0.0.1:8001 -t services/core-service/public"
Start-Process -NoNewWindow php -ArgumentList "-S 127.0.0.1:8002 -t services/core-service/public"
Start-Process -NoNewWindow php -ArgumentList "-S 127.0.0.1:8003 -t services/core-service/public"
Start-Process -NoNewWindow php -ArgumentList "-S 127.0.0.1:8004 -t services/core-service/public"

# 3. Start Laravel SQS Order Consumer Worker
Write-Host "[3/5] Starting Laravel SQS Consumer Worker..." -ForegroundColor Yellow
Start-Process -NoNewWindow php -ArgumentList "services/core-service/artisan clouddrop:consume-orders"

# 4. Start Node.js Edge Service (port 3001)
Write-Host "[4/5] Starting Node.js Edge Service (3001)..." -ForegroundColor Yellow
Start-Process -NoNewWindow node -ArgumentList "services/edge-service/src/server.js"

# 5. Start ALB Path-Based Gateway (port 3000)
Write-Host "[5/5] Starting CloudDrop ALB Gateway (3000)..." -ForegroundColor Yellow
Start-Process -NoNewWindow node -ArgumentList "gateway.js"

Write-Host ""
Write-Host "CloudDrop V3 is READY!" -ForegroundColor Green
Write-Host "  - Customer UI:     http://localhost:3000"
Write-Host "  - Stress Test Lab: http://localhost:3000/stress-test"
Write-Host "  - Edge Health:     http://localhost:3000/api/v3/health"
