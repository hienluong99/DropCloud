#!/bin/bash
set -e

echo "=== [LocalStack] Initializing CloudDrop AWS Resources ==="

# 1. Dead-Letter Queue (DLQ) cho các message lỗi quá 3 lần
awslocal sqs create-queue --queue-name clouddrop-orders-dlq

DLQ_ARN=$(awslocal sqs get-queue-attributes \
  --queue-url http://localhost:4566/000000000000/clouddrop-orders-dlq \
  --attribute-names QueueArn \
  --query "Attributes.QueueArn" --output text)

# 2. Hàng đợi chính (Order Created Queue) gắn RedrivePolicy tới DLQ
awslocal sqs create-queue \
  --queue-name clouddrop-orders-queue \
  --attributes "{\"RedrivePolicy\":\"{\\\"deadLetterTargetArn\\\":\\\"$DLQ_ARN\\\",\\\"maxReceiveCount\\\":\\\"3\\\"}\",\"VisibilityTimeout\":\"30\"}"

# 3. Delay Queue (5 phút) để tự động hoàn vé nếu khách không thanh toán
awslocal sqs create-queue \
  --queue-name clouddrop-expiration-queue \
  --attributes '{"DelaySeconds":"300"}'

# 4. S3 Bucket lưu vé PDF & QR Code (Private Bucket)
awslocal s3 mb s3://clouddrop-tickets

echo "=== [LocalStack] AWS SQS & S3 Ready! ==="
awslocal sqs list-queues
awslocal s3 ls
