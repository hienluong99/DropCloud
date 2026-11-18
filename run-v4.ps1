Write-Host "=== Starting CloudDrop V4 (Full AWS SAA: Edge + SQS + DLQ + Delay Queue + S3 Presigned URL) ===" -ForegroundColor Cyan

# 0. Check Docker Desktop is running
docker info *> $null
if ($LASTEXITCODE -ne 0) {
    Write-Host "[ERROR] Docker Desktop chua duoc khoi dong! Hay mo Docker Desktop va thu lai." -ForegroundColor Red
    exit 1
}

# 1. Start Redis & LocalStack (AWS SQS + S3) via Docker Compose
Write-Host "[1/6] Starting Redis & LocalStack containers..." -ForegroundColor Yellow
docker compose up -d
if ($LASTEXITCODE -ne 0) {
    Write-Host "[ERROR] docker compose up -d that bai." -ForegroundColor Red
    exit 1
}

Write-Host "      Waiting for LocalStack SQS & S3 to be ready..." -ForegroundColor DarkGray
for ($i = 0; $i -lt 20; $i++) {
    try {
        $health = Invoke-RestMethod -Uri "http://127.0.0.1:4566/_localstack/health" -TimeoutSec 2 -ErrorAction Stop
        if ($health.services.sqs -eq "available" -or $health.services.sqs -eq "running") {
            break
        }
    } catch {}
    Start-Sleep -Seconds 2
}

# Ensure SQS Queues & S3 Bucket exist (safe idempotent setup on Windows)
docker exec clouddrop-localstack awslocal sqs create-queue --queue-name clouddrop-orders-dlq *> $null
docker exec clouddrop-localstack awslocal sqs create-queue --queue-name clouddrop-orders-queue *> $null
docker exec clouddrop-localstack awslocal sqs create-queue --queue-name clouddrop-expiration-queue --attributes '{"DelaySeconds":"300"}' *> $null
docker exec clouddrop-localstack awslocal s3 mb s3://clouddrop-tickets *> $null

# 2. Start 4 Laravel Core HTTP Workers
Write-Host "[2/6] Starting 4 PHP Laravel Core Workers (8001-8004)..." -ForegroundColor Yellow
Start-Process -NoNewWindow php -ArgumentList "-S 127.0.0.1:8001 -t services/core-service/public"
Start-Process -NoNewWindow php -ArgumentList "-S 127.0.0.1:8002 -t services/core-service/public"
Start-Process -NoNewWindow php -ArgumentList "-S 127.0.0.1:8003 -t services/core-service/public"
Start-Process -NoNewWindow php -ArgumentList "-S 127.0.0.1:8004 -t services/core-service/public"

# 3. Start Laravel SQS Order Consumer Worker
Write-Host "[3/6] Starting Laravel SQS Order Consumer Worker..." -ForegroundColor Yellow
Start-Process -NoNewWindow php -ArgumentList "services/core-service/artisan clouddrop:consume-orders"

# 4. Start Laravel SQS Delay Queue Expiration Worker (V4)
Write-Host "[4/6] Starting Laravel SQS Expiration Worker (V4 Anti-Hoarding)..." -ForegroundColor Yellow
Start-Process -NoNewWindow php -ArgumentList "services/core-service/artisan clouddrop:consume-expirations"

# 5. Start Node.js Edge Service (port 3001)
Write-Host "[5/6] Starting Node.js Edge Service (3001)..." -ForegroundColor Yellow
Start-Process -NoNewWindow node -ArgumentList "services/edge-service/src/server.js"

# 6. Start ALB Path-Based Gateway (port 3000)
Write-Host "[6/6] Starting CloudDrop ALB Gateway (3000)..." -ForegroundColor Yellow
Start-Process -NoNewWindow node -ArgumentList "gateway.js"

Write-Host ""
Write-Host "CloudDrop V4 (AWS SAA Edition) is READY!" -ForegroundColor Green
Write-Host "  - Customer UI:     http://localhost:3000"
Write-Host "  - Stress Test Lab: http://localhost:3000/stress-test"
Write-Host "  - Edge Health:     http://localhost:3000/api/v3/health"
Write-Host "  - DLQ Redrive CLI: php services/core-service/artisan clouddrop:redrive-dlq"
