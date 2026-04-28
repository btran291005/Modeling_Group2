<?php
/**
 * api/devices.php
 * Backend xử lý TẤT CẢ nghiệp vụ liên quan đến Master Data:
 *   - get_brands      (Admin: lấy danh sách hãng + số dòng máy liên kết)
 *   - create_brand    (Admin: tạo hãng mới)
 *   - update_brand    (Admin: sửa tên hãng)
 *   - delete_brand    (Admin: xóa hãng — chỉ khi không còn dòng máy liên kết)
 *   - get_models      (Admin: lấy danh sách dòng máy với JOIN brands)
 *   - create_model    (Admin: tạo dòng máy mới)
 *   - update_model    (Admin: cập nhật dòng máy)
 *   - delete_model    (Admin: xóa dòng máy)
 *
 * Tất cả response là JSON chuẩn: { success: bool, message: string, data?: any }
 *
 * Quy tắc R03: TUYỆT ĐỐI KHÔNG có lệnh DELETE ở tầng Staff.
 *   -> File này chỉ Admin mới được gọi (requireAdmin() guard).
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db_connect.php';

header('Content-Type: application/json; charset=utf-8');

// ---- GET: Staff tra cứu dòng máy theo hãng (dùng trong trang valuation) ----
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    requireLogin();
    $action = trim($_GET['action'] ?? '');
    if ($action === 'get_models') {
        $brandId = (int) ($_GET['brand_id'] ?? 0);
        if (!$brandId) {
            exit(json_encode(['success' => false, 'models' => []]));
        }
        $stmt = $pdo->prepare("
            SELECT model_id, model_name, ram_gb, rom_gb, base_price
            FROM device_models
            WHERE brand_id = ?
            ORDER BY model_name ASC
        ");
        $stmt->execute([$brandId]);
        $models = $stmt->fetchAll(PDO::FETCH_ASSOC);
        exit(json_encode(['success' => true, 'models' => $models]));
    }
    http_response_code(400);
    exit(json_encode(['success' => false, 'message' => 'Action không hợp lệ.']));
}

// ---- POST: Chỉ Admin mới được dùng các actions bên dưới ----
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['success' => false, 'message' => 'Method not allowed.']));
}

// Tất cả POST actions trong file này đều yêu cầu Admin
requireAdmin();

$action = trim($_POST['action'] ?? '');

match ($action) {
    // --- BRANDS ---
    'get_brands'    => handleGetBrands(),
    'create_brand'  => handleCreateBrand(),
    'update_brand'  => handleUpdateBrand(),
    'delete_brand'  => handleDeleteBrand(),
    // --- MODELS ---
    'get_models'    => handleGetModels(),
    'create_model'  => handleCreateModel(),
    'update_model'  => handleUpdateModel(),
    'delete_model'  => handleDeleteModel(),
    // Fallback
    default         => sendJson(false, 'Action không hợp lệ.', 400),
};


/* ============================================================
 * HELPER: Gửi JSON response
 * ============================================================ */
function sendJson(bool $success, string $message, int $httpCode = 200, mixed $data = null): void
{
    http_response_code($httpCode);
    $payload = ['success' => $success, 'message' => $message];
    if ($data !== null) $payload['data'] = $data;
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}


/* ============================================================
 * NHÓM 1: BRANDS
 * ============================================================ */

/**
 * Lấy danh sách tất cả hãng kèm số dòng máy liên kết
 * GET request mimic qua POST với action=get_brands
 */
function handleGetBrands(): void
{
    global $pdo;

    $sql = "
        SELECT
            b.brand_id,
            b.brand_name,
            COUNT(dm.model_id) AS model_count
        FROM brands b
        LEFT JOIN device_models dm ON b.brand_id = dm.brand_id
        GROUP BY b.brand_id, b.brand_name
        ORDER BY b.brand_name ASC
    ";

    $stmt = $pdo->query($sql);
    $brands = $stmt->fetchAll(PDO::FETCH_ASSOC);

    sendJson(true, 'OK', 200, $brands);
}


/**
 * Tạo hãng mới
 * Required POST: brand_name
 */
function handleCreateBrand(): void
{
    global $pdo;

    $brandName = trim($_POST['brand_name'] ?? '');

    // Validate
    if (empty($brandName)) {
        sendJson(false, 'Tên hãng không được để trống.');
    }
    if (mb_strlen($brandName) > 80) {
        sendJson(false, 'Tên hãng không được vượt quá 80 ký tự.');
    }

    // Kiểm tra trùng tên (case-insensitive)
    $check = $pdo->prepare("SELECT brand_id FROM brands WHERE LOWER(brand_name) = LOWER(:name) LIMIT 1");
    $check->execute([':name' => $brandName]);
    if ($check->fetch()) {
        sendJson(false, "Hãng \"{$brandName}\" đã tồn tại trong hệ thống.");
    }

    // Insert
    $stmt = $pdo->prepare("INSERT INTO brands (brand_name) VALUES (:name)");
    $stmt->execute([':name' => $brandName]);

    // Audit log
    writeAuditLog($pdo, 'CREATE', 'brands', "Tạo hãng mới: {$brandName}");

    sendJson(true, "Đã thêm hãng \"{$brandName}\" thành công.");
}


/**
 * Cập nhật tên hãng
 * Required POST: brand_id, brand_name
 */
function handleUpdateBrand(): void
{
    global $pdo;

    $brandId   = (int) ($_POST['brand_id']   ?? 0);
    $brandName = trim($_POST['brand_name'] ?? '');

    if (!$brandId)       sendJson(false, 'Thiếu brand_id.');
    if (empty($brandName)) sendJson(false, 'Tên hãng không được để trống.');
    if (mb_strlen($brandName) > 80) sendJson(false, 'Tên hãng không được vượt quá 80 ký tự.');

    // Kiểm tra tồn tại
    $check = $pdo->prepare("SELECT brand_id FROM brands WHERE brand_id = :id LIMIT 1");
    $check->execute([':id' => $brandId]);
    if (!$check->fetch()) sendJson(false, 'Không tìm thấy hãng này.', 404);

    // Kiểm tra trùng tên với hãng khác
    $dup = $pdo->prepare(
        "SELECT brand_id FROM brands WHERE LOWER(brand_name) = LOWER(:name) AND brand_id != :id LIMIT 1"
    );
    $dup->execute([':name' => $brandName, ':id' => $brandId]);
    if ($dup->fetch()) sendJson(false, "Tên hãng \"{$brandName}\" đã được dùng.");

    $stmt = $pdo->prepare("UPDATE brands SET brand_name = :name WHERE brand_id = :id");
    $stmt->execute([':name' => $brandName, ':id' => $brandId]);

    writeAuditLog($pdo, 'UPDATE', 'brands', "Cập nhật hãng ID={$brandId}: {$brandName}");

    sendJson(true, "Đã cập nhật tên hãng thành \"{$brandName}\".");
}


/**
 * Xóa hãng
 * Required POST: brand_id
 * Điều kiện: hãng không còn dòng máy nào liên kết
 */
function handleDeleteBrand(): void
{
    global $pdo;

    $brandId = (int) ($_POST['brand_id'] ?? 0);
    if (!$brandId) sendJson(false, 'Thiếu brand_id.');

    // Kiểm tra tồn tại
    $checkBrand = $pdo->prepare("SELECT brand_name FROM brands WHERE brand_id = :id LIMIT 1");
    $checkBrand->execute([':id' => $brandId]);
    $brand = $checkBrand->fetch();
    if (!$brand) sendJson(false, 'Không tìm thấy hãng này.', 404);

    // Ràng buộc: không xóa nếu còn dòng máy
    $countModels = $pdo->prepare("SELECT COUNT(*) FROM device_models WHERE brand_id = :id");
    $countModels->execute([':id' => $brandId]);
    $count = (int) $countModels->fetchColumn();

    if ($count > 0) {
        sendJson(false, "Không thể xóa: hãng này còn {$count} dòng máy liên kết. Hãy xóa các dòng máy trước.");
    }

    $stmt = $pdo->prepare("DELETE FROM brands WHERE brand_id = :id");
    $stmt->execute([':id' => $brandId]);

    writeAuditLog($pdo, 'DELETE', 'brands', "Xóa hãng: {$brand['brand_name']} (ID={$brandId})");

    sendJson(true, "Đã xóa hãng \"{$brand['brand_name']}\".");
}


/* ============================================================
 * NHÓM 2: DEVICE MODELS
 * ============================================================ */

/**
 * Lấy danh sách tất cả dòng máy kèm thông tin hãng
 */
function handleGetModels(): void
{
    global $pdo;

    $sql = "
        SELECT
            dm.model_id,
            dm.brand_id,
            dm.model_name,
            dm.ram_gb,
            dm.rom_gb,
            dm.base_price,
            b.brand_name
        FROM device_models dm
        JOIN brands b ON dm.brand_id = b.brand_id
        ORDER BY b.brand_name ASC, dm.model_name ASC
    ";

    $stmt   = $pdo->query($sql);
    $models = $stmt->fetchAll(PDO::FETCH_ASSOC);

    sendJson(true, 'OK', 200, $models);
}


/**
 * Tạo dòng máy mới
 * Required POST: model_name, brand_id, ram_gb, rom_gb, base_price
 */
function handleCreateModel(): void
{
    global $pdo;

    $modelName  = trim($_POST['model_name'] ?? '');
    $brandId    = (int) ($_POST['brand_id']   ?? 0);
    $ramGb      = (int) ($_POST['ram_gb']     ?? 0);
    $romGb      = (int) ($_POST['rom_gb']     ?? 0);
    $basePrice  = (int) ($_POST['base_price'] ?? 0);

    // Validate
    if (empty($modelName))   sendJson(false, 'Tên dòng máy không được để trống.');
    if (mb_strlen($modelName) > 120) sendJson(false, 'Tên dòng máy không quá 120 ký tự.');
    if (!$brandId)           sendJson(false, 'Vui lòng chọn hãng sản xuất.');
    if ($ramGb <= 0)         sendJson(false, 'RAM không hợp lệ.');
    if ($romGb <= 0)         sendJson(false, 'ROM không hợp lệ.');
    if ($basePrice <= 0)     sendJson(false, 'Giá cơ sở phải lớn hơn 0.');

    // Kiểm tra hãng tồn tại
    $checkBrand = $pdo->prepare("SELECT brand_id FROM brands WHERE brand_id = :id LIMIT 1");
    $checkBrand->execute([':id' => $brandId]);
    if (!$checkBrand->fetch()) sendJson(false, 'Hãng sản xuất không tồn tại.', 404);

    // Kiểm tra trùng tên dòng máy trong cùng hãng
    $dup = $pdo->prepare(
        "SELECT model_id FROM device_models
         WHERE brand_id = :bid AND LOWER(model_name) = LOWER(:name) LIMIT 1"
    );
    $dup->execute([':bid' => $brandId, ':name' => $modelName]);
    if ($dup->fetch()) sendJson(false, "Dòng máy \"{$modelName}\" đã tồn tại trong hãng này.");

    $stmt = $pdo->prepare(
        "INSERT INTO device_models (brand_id, model_name, ram_gb, rom_gb, base_price)
         VALUES (:bid, :name, :ram, :rom, :price)"
    );
    $stmt->execute([
        ':bid'   => $brandId,
        ':name'  => $modelName,
        ':ram'   => $ramGb,
        ':rom'   => $romGb,
        ':price' => $basePrice,
    ]);

    writeAuditLog($pdo, 'CREATE', 'device_models',
        "Tạo dòng máy mới: {$modelName} (BrandID={$brandId}, RAM={$ramGb}GB, ROM={$romGb}GB)"
    );

    sendJson(true, "Đã thêm dòng máy \"{$modelName}\" thành công.");
}


/**
 * Cập nhật dòng máy
 * Required POST: model_id, model_name, brand_id, ram_gb, rom_gb, base_price
 */
function handleUpdateModel(): void
{
    global $pdo;

    $modelId    = (int) ($_POST['model_id']   ?? 0);
    $modelName  = trim($_POST['model_name'] ?? '');
    $brandId    = (int) ($_POST['brand_id']   ?? 0);
    $ramGb      = (int) ($_POST['ram_gb']     ?? 0);
    $romGb      = (int) ($_POST['rom_gb']     ?? 0);
    $basePrice  = (int) ($_POST['base_price'] ?? 0);

    if (!$modelId)           sendJson(false, 'Thiếu model_id.');
    if (empty($modelName))   sendJson(false, 'Tên dòng máy không được để trống.');
    if (mb_strlen($modelName) > 120) sendJson(false, 'Tên dòng máy không quá 120 ký tự.');
    if (!$brandId)           sendJson(false, 'Vui lòng chọn hãng sản xuất.');
    if ($ramGb <= 0)         sendJson(false, 'RAM không hợp lệ.');
    if ($romGb <= 0)         sendJson(false, 'ROM không hợp lệ.');
    if ($basePrice <= 0)     sendJson(false, 'Giá cơ sở phải lớn hơn 0.');

    // Kiểm tra tồn tại
    $check = $pdo->prepare("SELECT model_id FROM device_models WHERE model_id = :id LIMIT 1");
    $check->execute([':id' => $modelId]);
    if (!$check->fetch()) sendJson(false, 'Không tìm thấy dòng máy này.', 404);

    // Kiểm tra trùng tên (trong cùng hãng, trừ chính nó)
    $dup = $pdo->prepare(
        "SELECT model_id FROM device_models
         WHERE brand_id = :bid AND LOWER(model_name) = LOWER(:name) AND model_id != :mid LIMIT 1"
    );
    $dup->execute([':bid' => $brandId, ':name' => $modelName, ':mid' => $modelId]);
    if ($dup->fetch()) sendJson(false, "Tên \"{$modelName}\" đã tồn tại trong hãng này.");

    $stmt = $pdo->prepare(
        "UPDATE device_models
         SET brand_id = :bid, model_name = :name, ram_gb = :ram, rom_gb = :rom, base_price = :price
         WHERE model_id = :mid"
    );
    $stmt->execute([
        ':bid'   => $brandId,
        ':name'  => $modelName,
        ':ram'   => $ramGb,
        ':rom'   => $romGb,
        ':price' => $basePrice,
        ':mid'   => $modelId,
    ]);

    writeAuditLog($pdo, 'UPDATE', 'device_models',
        "Cập nhật dòng máy ID={$modelId}: {$modelName}"
    );

    sendJson(true, "Đã cập nhật dòng máy \"{$modelName}\".");
}


/**
 * Xóa dòng máy
 * Required POST: model_id
 * Điều kiện: không có phiên định giá nào dùng dòng máy này
 */
function handleDeleteModel(): void
{
    global $pdo;

    $modelId = (int) ($_POST['model_id'] ?? 0);
    if (!$modelId) sendJson(false, 'Thiếu model_id.');

    // Kiểm tra tồn tại
    $check = $pdo->prepare("SELECT model_name FROM device_models WHERE model_id = :id LIMIT 1");
    $check->execute([':id' => $modelId]);
    $model = $check->fetch();
    if (!$model) sendJson(false, 'Không tìm thấy dòng máy này.', 404);

    // Ràng buộc: không xóa nếu đã có phiên định giá dùng dòng máy này
    $countSessions = $pdo->prepare("SELECT COUNT(*) FROM valuation_sessions WHERE model_id = :id");
    $countSessions->execute([':id' => $modelId]);
    $count = (int) $countSessions->fetchColumn();

    if ($count > 0) {
        sendJson(false, "Không thể xóa: dòng máy này đang được dùng trong {$count} phiên định giá.");
    }

    $stmt = $pdo->prepare("DELETE FROM device_models WHERE model_id = :id");
    $stmt->execute([':id' => $modelId]);

    writeAuditLog($pdo, 'DELETE', 'device_models',
        "Xóa dòng máy: {$model['model_name']} (ID={$modelId})"
    );

    sendJson(true, "Đã xóa dòng máy \"{$model['model_name']}\".");
}


/* ============================================================
 * HELPER: Ghi Audit Log
 * ============================================================ */
function writeAuditLog(PDO $pdo, string $action, string $targetTable, string $detail): void
{
    try {
        $userId = $_SESSION['user_id'] ?? 0;
        $stmt = $pdo->prepare(
            "INSERT INTO audit_logs (user_id, action, target_table, created_at)
             VALUES (:uid, :action, :table, NOW())"
        );
        $stmt->execute([
            ':uid'    => $userId,
            ':action' => $detail,
            ':table'  => $targetTable,
        ]);
    } catch (Throwable $e) {
        // Không để lỗi audit log làm crash nghiệp vụ chính
    }
}