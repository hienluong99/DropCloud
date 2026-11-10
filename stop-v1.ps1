# Dung toan bo server CloudDrop V1 (Gateway port 3000 va 4 PHP Workers 8001-8004)
$ports = @(3000, 8001, 8002, 8003, 8004)
$pids = Get-NetTCPConnection -LocalPort $ports -ErrorAction SilentlyContinue | Select-Object -ExpandProperty OwningProcess -Unique

if ($pids) {
    foreach ($procId in $pids) {
        Stop-Process -Id $procId -Force -ErrorAction SilentlyContinue
    }
    Write-Host "✅ Đã dừng thành công các tiến trình CloudDrop V1 (PID: $($pids -join ', '))" -ForegroundColor Green
} else {
    Write-Host "ℹ️ Không tìm thấy tiến trình nào đang chạy trên các cổng $($ports -join ', ')." -ForegroundColor Yellow
}
