<?php

declare(strict_types=1);

/**
 * services/MasterDataService.php
 *
 * Business logic cho quản lý Dữ liệu nền: Hãng (brands) & Dòng máy (device_models).
 * Nguyên tắc: Chỉ nhận input → query DB → trả Array/bool hoặc ném Exception.
 * KHÔNG echo, KHÔNG header(), KHÔNG $_SESSION (trừ đọc user_id để audit).
 */
class MasterDataService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }


    // ═══════════════════════════════════════════════════════════
    // getBrands – Lấy danh sách hãng (kèm số dòng máy)
    // ═══════════════════════════════════════════════════════════

    /**
     * @return array<int, array{brand_id:int, brand_name:string, model_count:int}>
     */
    public function getBrands(): array
    {
        return $this->pdo
            ->query(
                "SELECT b.brand_id,
                        b.brand_name,
                        (SELECT COUNT(*) FROM device_models dm WHERE dm.brand_id = b.brand_id) AS model_count
                 FROM   brands b
                 ORDER  BY b.brand_name ASC"
            )
            ->fetchAll(PDO::FETCH_ASSOC);
    }


    // ═══════════════════════════════════════════════════════════
    // getModels – Lấy danh sách dòng máy (kèm tên hãng), lọc theo brand_id (optional)
    // ═══════════════════════════════════════════════════════════

    /**
     * @param  int|null $brandId  Nếu truyền > 0 → lọc theo hãng
     * @return array<int, array{model_id:int, model_name:string, brand_id:int, brand_name:string, ram_gb:int, rom_gb:int, base_price:int}>
     */
    public function getModels(?int $brandId = null): array
    {
        $sql = "
            SELECT dm.model_id,
                   dm.model_name,
                   dm.brand_id,
                   b.brand_name,
                   dm.ram_gb,
                   dm.rom_gb,
                   dm.base_price
            FROM   device_models dm
            JOIN   brands b ON b.brand_id = dm.brand_id
        ";

        if ($brandId !== null && $brandId > 0) {
            $sql .= " WHERE dm.brand_id = :brand_id";
            $stmt = $this->pdo->prepare($sql . " ORDER BY dm.model_name ASC");
            $stmt->execute([':brand_id' => $brandId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        return $this->pdo
            ->query($sql . " ORDER BY b.brand_name ASC, dm.model_name ASC")
            ->fetchAll(PDO::FETCH_ASSOC);
    }


    // ═══════════════════════════════════════════════════════════
    // addBrand – Thêm hãng mới
    // ═══════════════════════════════════════════════════════════

    /**
     * @return int  brand_id vừa tạo
     * @throws InvalidArgumentException  Tên rỗng / quá dài
     * @throws RuntimeException          Tên hãng đã tồn tại
     */
    public function addBrand(string $name): int
    {
        $name = trim($name);

        if ($name === '') {
            throw new InvalidArgumentException('Tên hãng không được để trống.');
        }
        if (mb_strlen($name) > 80) {
            throw new InvalidArgumentException('Tên hãng tối đa 80 ký tự.');
        }

        $dup = $this->pdo->prepare("SELECT COUNT(*) FROM brands WHERE brand_name = :n");
        $dup->execute([':n' => $name]);
        if ((int) $dup->fetchColumn() > 0) {
            throw new RuntimeException('Hãng này đã tồn tại.');
        }

        $stmt = $this->pdo->prepare("INSERT INTO brands (brand_name) VALUES (:n)");
        $stmt->execute([':n' => $name]);

        $newId = (int) $this->pdo->lastInsertId();
        $this->_audit("Thêm hãng mới: {$name}", 'brands');

        return $newId;
    }


    // ═══════════════════════════════════════════════════════════
    // addModel – Thêm dòng máy mới (kèm giá sàn)
    // ═══════════════════════════════════════════════════════════

    /**
     * @return int  model_id vừa tạo
     * @throws InvalidArgumentException  Dữ liệu không hợp lệ
     * @throws RuntimeException          brand_id không tồn tại / tên dòng máy đã tồn tại trong hãng
     */
    public function addModel(int $brandId, string $name, int $basePrice, int $ramGb = 0, int $romGb = 0): int
    {
        $name = trim($name);

        if ($brandId <= 0) {
            throw new InvalidArgumentException('brand_id không hợp lệ.');
        }
        if ($name === '') {
            throw new InvalidArgumentException('Tên dòng máy không được để trống.');
        }
        if (mb_strlen($name) > 120) {
            throw new InvalidArgumentException('Tên dòng máy tối đa 120 ký tự.');
        }
        if ($basePrice < 0) {
            throw new InvalidArgumentException('Giá sàn không được âm.');
        }
        if ($ramGb < 0 || $romGb < 0) {
            throw new InvalidArgumentException('RAM/ROM không hợp lệ.');
        }

        $brandCheck = $this->pdo->prepare("SELECT brand_name FROM brands WHERE brand_id = :id");
        $brandCheck->execute([':id' => $brandId]);
        $brand = $brandCheck->fetch(PDO::FETCH_ASSOC);

        if (!$brand) {
            throw new RuntimeException('Hãng không tồn tại.');
        }

        $dup = $this->pdo->prepare(
            "SELECT COUNT(*) FROM device_models WHERE brand_id = :bid AND model_name = :n"
        );
        $dup->execute([':bid' => $brandId, ':n' => $name]);
        if ((int) $dup->fetchColumn() > 0) {
            throw new RuntimeException('Dòng máy này đã tồn tại trong hãng đã chọn.');
        }

        $stmt = $this->pdo->prepare(
            "INSERT INTO device_models (brand_id, model_name, ram_gb, rom_gb, base_price)
             VALUES (:bid, :n, :ram, :rom, :price)"
        );
        $stmt->execute([
            ':bid'   => $brandId,
            ':n'     => $name,
            ':ram'   => $ramGb,
            ':rom'   => $romGb,
            ':price' => $basePrice,
        ]);

        $newId = (int) $this->pdo->lastInsertId();
        $this->_audit("Thêm dòng máy mới: {$brand['brand_name']} {$name} (Giá sàn: {$basePrice})", 'device_models');

        return $newId;
    }


    // ═══════════════════════════════════════════════════════════
    // updateBasePrice – Cập nhật giá sàn cho dòng máy hiện có
    // ═══════════════════════════════════════════════════════════

    /**
     * @return bool
     * @throws InvalidArgumentException  Dữ liệu không hợp lệ
     * @throws RuntimeException          model_id không tồn tại
     */
    public function updateBasePrice(int $modelId, int $newBasePrice): bool
    {
        if ($modelId <= 0) {
            throw new InvalidArgumentException('model_id không hợp lệ.');
        }
        if ($newBasePrice < 0) {
            throw new InvalidArgumentException('Giá sàn không được âm.');
        }

        $stmt = $this->pdo->prepare(
            "SELECT dm.model_name, b.brand_name
             FROM   device_models dm
             JOIN   brands b ON b.brand_id = dm.brand_id
             WHERE  dm.model_id = :id"
        );
        $stmt->execute([':id' => $modelId]);
        $model = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$model) {
            throw new RuntimeException('Dòng máy không tồn tại.');
        }

        $ok = $this->pdo->prepare(
            "UPDATE device_models SET base_price = :price WHERE model_id = :id"
        )->execute([':price' => $newBasePrice, ':id' => $modelId]);

        if ($ok) {
            $this->_audit(
                "Cập nhật giá sàn {$model['brand_name']} {$model['model_name']} -> {$newBasePrice}",
                'device_models'
            );
        }

        return (bool) $ok;
    }


    // ═══════════════════════════════════════════════════════════
    // PRIVATE HELPER
    // ═══════════════════════════════════════════════════════════

    private function _audit(string $action, string $table): void
    {
        try {
            $this->pdo->prepare(
                "INSERT INTO audit_logs (user_id, action, target_table)
                 VALUES (:u, :a, :t)"
            )->execute([
                ':u' => $_SESSION['user']['user_id'] ?? 0,
                ':a' => $action,
                ':t' => $table,
            ]);
        } catch (Throwable $e) {}
    }
}