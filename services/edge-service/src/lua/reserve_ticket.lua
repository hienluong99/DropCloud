-- KEYS[1]: event:{id}:stock (String integer)
-- KEYS[2]: event:{id}:buyers (Set of user_ids)
-- ARGV[1]: user_id
--
-- Return codes:
--  -2 : Cache chưa được Warm-up (stock key không tồn tại)
--  -1 : User đã đặt vé cho sự kiện này rồi (Duplicate prevention)
--   0 : Hết vé (Sold out)
-- >=1 : Đặt giữ chỗ thành công (trả về số vé còn lại + 1 để phân biệt với 0, tức là remaining = result - 1)

local stockKey = KEYS[1]
local buyersKey = KEYS[2]
local userId = ARGV[1]

-- 1. Kiểm tra user đã mua chưa (chống 1 người bấm nhiều lần / bot spam)
if redis.call('SISMEMBER', buyersKey, userId) == 1 then
    return -1
end

-- 2. Kiểm tra tồn kho trong RAM
local stockStr = redis.call('GET', stockKey)
if not stockStr then
    return -2
end

local stock = tonumber(stockStr)
if stock <= 0 then
    return 0
end

-- 3. Trừ tồn kho nguyên tử (Atomic) và ghi nhận user
local remaining = redis.call('DECR', stockKey)
redis.call('SADD', buyersKey, userId)

-- Trả về remaining + 1 (để remaining = 0 vẫn phân biệt được với mã lỗi 0 = SOLD_OUT)
return remaining + 1
