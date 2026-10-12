<?php
// ─────────────────────────────────────────────────────────────
//  RCS Graphic — Product Reviews
// ─────────────────────────────────────────────────────────────

declare(strict_types=1);

namespace Reviews;

class ProductReview
{
    private const APPROVED = 'approved';
    private const PENDING = 'pending';
    private const REJECTED = 'rejected';

    private static ?bool $tableReady = null;

    public static function tableReady(): bool
    {
        if (self::$tableReady !== null) return self::$tableReady;
        try {
            try { \Database::query("ALTER TABLE users ADD COLUMN avatar_path VARCHAR(500) NULL AFTER company"); } catch (\Throwable) {}
            \Database::row('SELECT id FROM product_reviews LIMIT 1');
            self::$tableReady = true;
        } catch (\Throwable) {
            self::$tableReady = false;
        }
        return self::$tableReady;
    }

    public static function summaryForProduct(int $productId): array
    {
        $empty = [
            'average' => 0.0,
            'average_display' => '0.0',
            'count' => 0,
            'breakdown' => [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0],
        ];
        if ($productId <= 0 || !self::tableReady()) return $empty;

        try {
            $row = \Database::row(
                "SELECT COUNT(*) AS review_count, AVG(rating) AS avg_rating
                 FROM product_reviews
                 WHERE product_id = ? AND status = ?",
                [$productId, self::APPROVED]
            );
            $breakdownRows = \Database::rows(
                "SELECT rating, COUNT(*) AS c
                 FROM product_reviews
                 WHERE product_id = ? AND status = ?
                 GROUP BY rating",
                [$productId, self::APPROVED]
            );
        } catch (\Throwable) {
            return $empty;
        }

        $breakdown = $empty['breakdown'];
        foreach ($breakdownRows as $b) {
            $rating = (int)($b['rating'] ?? 0);
            if ($rating >= 1 && $rating <= 5) $breakdown[$rating] = (int)($b['c'] ?? 0);
        }
        $count = (int)($row['review_count'] ?? 0);
        $average = $count > 0 ? round((float)($row['avg_rating'] ?? 0), 1) : 0.0;

        return [
            'average' => $average,
            'average_display' => number_format($average, 1),
            'count' => $count,
            'breakdown' => $breakdown,
        ];
    }

    public static function approvedForProduct(int $productId, int $limit = 10): array
    {
        if ($productId <= 0 || !self::tableReady()) return [];
        $limit = max(1, min(50, $limit));
        $orderSql = self::reviewOrderSql();
        try {
            $rows = \Database::rows(
                "SELECT pr.*, u.name AS customer_name, u.avatar_path AS customer_avatar, p.name AS product_name, p.slug AS product_slug
                 FROM product_reviews pr
                 LEFT JOIN users u ON u.id = pr.user_id
                 LEFT JOIN products p ON p.id = pr.product_id
                 WHERE pr.product_id = ? AND pr.status = ?
                 {$orderSql}
                 LIMIT {$limit}",
                [$productId, self::APPROVED]
            );
        } catch (\Throwable $e) {
            error_log('Approved review fetch failed, retrying without joins: ' . $e->getMessage());
            try {
                $rows = \Database::rows(
                    "SELECT pr.*
                     FROM product_reviews pr
                     WHERE pr.product_id = ? AND pr.status = ?
                     {$orderSql}
                     LIMIT {$limit}",
                    [$productId, self::APPROVED]
                );
            } catch (\Throwable $fallbackError) {
                error_log('Approved review fallback fetch failed: ' . $fallbackError->getMessage());
                return [];
            }
        }
        return array_map([self::class, 'normalizeReview'], $rows);
    }

    public static function featured(int $limit = 6): array
    {
        if (!self::tableReady()) return [];
        $limit = max(1, min(12, $limit));
        $orderSql = self::reviewOrderSql();
        try {
            $rows = \Database::rows(
                "SELECT pr.*, u.name AS customer_name, u.avatar_path AS customer_avatar, p.name AS product_name, p.slug AS product_slug
                 FROM product_reviews pr
                 LEFT JOIN users u ON u.id = pr.user_id
                 LEFT JOIN products p ON p.id = pr.product_id
                 WHERE pr.status = ?
                 {$orderSql}
                 LIMIT {$limit}",
                [self::APPROVED]
            );
        } catch (\Throwable $e) {
            error_log('Featured review fetch failed, retrying without joins: ' . $e->getMessage());
            try {
                $rows = \Database::rows(
                    "SELECT pr.*
                     FROM product_reviews pr
                     WHERE pr.status = ?
                     {$orderSql}
                     LIMIT {$limit}",
                    [self::APPROVED]
                );
            } catch (\Throwable $fallbackError) {
                error_log('Featured review fallback fetch failed: ' . $fallbackError->getMessage());
                return [];
            }
        }
        return array_map([self::class, 'normalizeReview'], $rows);
    }

    public static function userReviews(int $userId): array
    {
        if ($userId <= 0 || !self::tableReady()) return [];
        try {
            $rows = \Database::rows(
                "SELECT pr.*, p.name AS product_name, p.slug AS product_slug,
                        COALESCE(p.image_path, (SELECT COALESCE(pi.image_path, pi.url) FROM product_images pi WHERE pi.product_id = p.id AND pi.is_primary = 1 LIMIT 1)) AS product_image
                 FROM product_reviews pr
                 LEFT JOIN products p ON p.id = pr.product_id
                 WHERE pr.user_id = ?
                 ORDER BY pr.created_at DESC, pr.id DESC",
                [$userId]
            );
        } catch (\Throwable) {
            try {
                $rows = \Database::rows(
                    "SELECT pr.*, p.name AS product_name, p.slug AS product_slug,
                            (SELECT pi.url FROM product_images pi WHERE pi.product_id = p.id AND pi.is_primary = 1 LIMIT 1) AS product_image
                     FROM product_reviews pr
                     LEFT JOIN products p ON p.id = pr.product_id
                     WHERE pr.user_id = ?
                     ORDER BY pr.created_at DESC, pr.id DESC",
                    [$userId]
                );
            } catch (\Throwable) {
                return [];
            }
        }
        return array_map([self::class, 'normalizeReview'], $rows);
    }

    public static function reviewableItemsForUser(int $userId): array
    {
        if ($userId <= 0 || !self::tableReady()) return [];
        try {
            $rows = \Database::rows(
                "SELECT oi.id AS order_item_id, oi.order_id AS db_order_id, oi.product_id, oi.product_name,
                        o.order_id AS public_order_id, o.created_at AS order_created_at, o.status AS order_status,
                        p.slug AS product_slug,
                        COALESCE(p.image_path, (SELECT COALESCE(pi.image_path, pi.url) FROM product_images pi WHERE pi.product_id = p.id AND pi.is_primary = 1 LIMIT 1)) AS product_image
                 FROM order_items oi
                 INNER JOIN orders o ON o.id = oi.order_id
                 LEFT JOIN products p ON p.id = oi.product_id
                 WHERE o.user_id = ?
                   AND o.status = 'delivered'
                   AND oi.product_id IS NOT NULL
                   AND NOT EXISTS (
                     SELECT 1 FROM product_reviews pr
                     WHERE pr.product_id = oi.product_id AND pr.user_id = ?
                   )
                 ORDER BY o.created_at DESC, oi.id DESC",
                [$userId, $userId]
            );
        } catch (\Throwable) {
            try {
                $rows = \Database::rows(
                    "SELECT oi.id AS order_item_id, oi.order_id AS db_order_id, oi.product_id, oi.product_name,
                            o.order_id AS public_order_id, o.created_at AS order_created_at, o.status AS order_status,
                            p.slug AS product_slug,
                            (SELECT pi.url FROM product_images pi WHERE pi.product_id = p.id AND pi.is_primary = 1 LIMIT 1) AS product_image
                     FROM order_items oi
                     INNER JOIN orders o ON o.id = oi.order_id
                     LEFT JOIN products p ON p.id = oi.product_id
                     WHERE o.user_id = ?
                       AND o.status = 'delivered'
                       AND oi.product_id IS NOT NULL
                       AND NOT EXISTS (
                         SELECT 1 FROM product_reviews pr
                         WHERE pr.product_id = oi.product_id AND pr.user_id = ?
                       )
                     ORDER BY o.created_at DESC, oi.id DESC",
                    [$userId, $userId]
                );
            } catch (\Throwable) {
                return [];
            }
        }

        $seen = [];
        $items = [];
        foreach ($rows as $row) {
            $productId = (int)($row['product_id'] ?? 0);
            if ($productId <= 0 || isset($seen[$productId])) continue;
            $seen[$productId] = true;
            $items[] = [
                'order_item_id' => (int)($row['order_item_id'] ?? 0),
                'order_id' => (int)($row['db_order_id'] ?? 0),
                'public_order_id' => (string)($row['public_order_id'] ?? ''),
                'product_id' => $productId,
                'product_name' => (string)($row['product_name'] ?? 'Product'),
                'product_slug' => (string)($row['product_slug'] ?? ''),
                'product_image' => (string)($row['product_image'] ?? ''),
                'order_created_at' => (string)($row['order_created_at'] ?? ''),
            ];
        }
        return $items;
    }

    public static function createOrUpdate(int $userId, array $payload): array
    {
        if ($userId <= 0) return ['ok' => false, 'msg' => 'Please login to submit a review.'];
        if (!self::tableReady()) return ['ok' => false, 'msg' => 'Reviews table is not installed yet. Please run database migration.'];

        $productId = (int)($payload['product_id'] ?? 0);
        $orderItemId = (int)($payload['order_item_id'] ?? 0);
        $rating = (int)($payload['rating'] ?? 0);
        $comment = trim((string)($payload['comment'] ?? ''));

        if ($productId <= 0) return ['ok' => false, 'msg' => 'Please select a product to review.'];
        if ($rating < 1 || $rating > 5) return ['ok' => false, 'msg' => 'Please select a rating between 1 and 5 stars.'];
        if (strlen($comment) < 10) return ['ok' => false, 'msg' => 'Please write at least 10 characters.'];
        if (strlen($comment) > 1000) return ['ok' => false, 'msg' => 'Review comment must be under 1000 characters.'];

        $purchase = self::verifiedPurchase($userId, $productId, $orderItemId);
        if (!$purchase) {
            return ['ok' => false, 'msg' => 'Review is available only after a delivered order for this product.'];
        }

        try {
            \Database::query(
                "INSERT INTO product_reviews (product_id, user_id, order_id, order_item_id, rating, comment, status, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                 ON DUPLICATE KEY UPDATE
                   order_id = VALUES(order_id),
                   order_item_id = VALUES(order_item_id),
                   rating = VALUES(rating),
                   comment = VALUES(comment),
                   status = VALUES(status),
                   admin_note = NULL,
                   updated_at = NOW()",
                [$productId, $userId, (int)$purchase['order_id'], (int)$purchase['order_item_id'], $rating, $comment, self::PENDING]
            );
        } catch (\Throwable $e) {
            error_log('Review save failed: ' . $e->getMessage());
            return ['ok' => false, 'msg' => 'Could not save review. Please try again.'];
        }

        return ['ok' => true, 'msg' => 'Thank you! Your review has been submitted for approval.'];
    }

    public static function adminList(string $status = 'all', string $search = '', int $limit = 100): array
    {
        if (!self::tableReady()) return [];
        $limit = max(1, min(200, $limit));
        $where = [];
        $params = [];
        if (in_array($status, [self::PENDING, self::APPROVED, self::REJECTED], true)) {
            $where[] = 'pr.status = ?';
            $params[] = $status;
        }
        if ($search !== '') {
            $where[] = '(p.name LIKE ? OR u.name LIKE ? OR u.email LIKE ? OR pr.comment LIKE ?)';
            $like = '%' . $search . '%';
            array_push($params, $like, $like, $like, $like);
        }
        $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
        try {
            $rows = \Database::rows(
                "SELECT pr.*, u.name AS customer_name, u.email AS customer_email,
                        p.name AS product_name, p.slug AS product_slug,
                        o.order_id AS public_order_id
                 FROM product_reviews pr
                 LEFT JOIN users u ON u.id = pr.user_id
                 LEFT JOIN products p ON p.id = pr.product_id
                 LEFT JOIN orders o ON o.id = pr.order_id
                 {$whereSql}
                 ORDER BY FIELD(pr.status, 'pending', 'approved', 'rejected'), pr.created_at DESC, pr.id DESC
                 LIMIT {$limit}",
                $params
            );
        } catch (\Throwable) {
            return [];
        }
        return array_map([self::class, 'normalizeReview'], $rows);
    }

    public static function moderate(int $reviewId, string $status, ?int $adminId = null, string $note = ''): array
    {
        if (!self::tableReady()) return ['ok' => false, 'msg' => 'Reviews table is not installed.'];
        if ($reviewId <= 0) return ['ok' => false, 'msg' => 'Invalid review.'];
        if (!in_array($status, [self::APPROVED, self::REJECTED, self::PENDING], true)) {
            return ['ok' => false, 'msg' => 'Invalid review status.'];
        }

        $sets = ['status = ?'];
        $params = [$status];

        // Some live databases may have an older product_reviews table. Keep moderation
        // working even if optional metadata columns have not been added yet.
        if (self::hasColumn('product_reviews', 'admin_note')) {
            $sets[] = 'admin_note = ?';
            $params[] = $note !== '' ? $note : null;
        }
        if (self::hasColumn('product_reviews', 'approved_by')) {
            $sets[] = 'approved_by = ?';
            $params[] = $status === self::APPROVED ? ($adminId ?: null) : null;
        }
        if (self::hasColumn('product_reviews', 'approved_at')) {
            $sets[] = 'approved_at = ?';
            $params[] = $status === self::APPROVED ? date('Y-m-d H:i:s') : null;
        }
        if (self::hasColumn('product_reviews', 'updated_at')) {
            $sets[] = 'updated_at = NOW()';
        }

        $params[] = $reviewId;
        try {
            $stmt = \Database::query(
                'UPDATE product_reviews SET ' . implode(', ', $sets) . ' WHERE id = ?',
                $params
            );
        } catch (\Throwable $e) {
            error_log('Review moderation failed: ' . $e->getMessage());
            return ['ok' => false, 'msg' => 'Could not update review. Please check product_reviews table columns/migration.'];
        }

        if ($stmt->rowCount() === 0 && !\Database::row('SELECT id FROM product_reviews WHERE id = ? LIMIT 1', [$reviewId])) {
            return ['ok' => false, 'msg' => 'Review not found.'];
        }

        return ['ok' => true, 'msg' => 'Review updated.'];
    }

    public static function setFeatured(int $reviewId, bool $featured): array
    {
        if (!self::tableReady()) return ['ok' => false, 'msg' => 'Reviews table is not installed.'];
        $sets = ['is_featured = ?'];
        $params = [$featured ? 1 : 0];
        if (self::hasColumn('product_reviews', 'updated_at')) {
            $sets[] = 'updated_at = NOW()';
        }
        $params[] = $reviewId;
        try {
            \Database::query(
                'UPDATE product_reviews SET ' . implode(', ', $sets) . ' WHERE id = ?',
                $params
            );
        } catch (\Throwable $e) {
            error_log('Review feature toggle failed: ' . $e->getMessage());
            return ['ok' => false, 'msg' => 'Could not update featured flag. Please check product_reviews table columns/migration.'];
        }
        return ['ok' => true, 'msg' => $featured ? 'Review marked as featured.' : 'Review removed from featured.'];
    }

    public static function delete(int $reviewId): array
    {
        if (!self::tableReady()) return ['ok' => false, 'msg' => 'Reviews table is not installed.'];
        try {
            \Database::query('DELETE FROM product_reviews WHERE id = ?', [$reviewId]);
        } catch (\Throwable $e) {
            error_log('Review delete failed: ' . $e->getMessage());
            return ['ok' => false, 'msg' => 'Could not delete review.'];
        }
        return ['ok' => true, 'msg' => 'Review deleted.'];
    }



    private static function reviewOrderSql(): string
    {
        $parts = [];
        if (self::hasColumn('product_reviews', 'is_featured')) {
            $parts[] = 'pr.is_featured DESC';
        }
        if (self::hasColumn('product_reviews', 'created_at')) {
            $parts[] = 'pr.created_at DESC';
        }
        $parts[] = 'pr.id DESC';
        return 'ORDER BY ' . implode(', ', $parts);
    }

    private static function hasColumn(string $table, string $column): bool
    {
        static $cache = [];
        $key = $table . '.' . $column;
        if (array_key_exists($key, $cache)) return $cache[$key];
        try {
            $row = \Database::row(
                "SELECT 1 AS ok
                 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = ?
                   AND COLUMN_NAME = ?
                 LIMIT 1",
                [$table, $column]
            );
            return $cache[$key] = (bool)$row;
        } catch (\Throwable) {
            return $cache[$key] = false;
        }
    }

    private static function verifiedPurchase(int $userId, int $productId, int $orderItemId = 0): ?array
    {
        $params = [$userId, $productId];
        $extra = '';
        if ($orderItemId > 0) {
            $extra = ' AND oi.id = ?';
            $params[] = $orderItemId;
        }
        try {
            return \Database::row(
                "SELECT o.id AS order_id, oi.id AS order_item_id
                 FROM order_items oi
                 INNER JOIN orders o ON o.id = oi.order_id
                 WHERE o.user_id = ?
                   AND oi.product_id = ?
                   AND o.status = 'delivered'
                   {$extra}
                 ORDER BY o.created_at DESC, oi.id DESC
                 LIMIT 1",
                $params
            );
        } catch (\Throwable) {
            return null;
        }
    }

    private static function normalizeReview(array $row): array
    {
        $name = trim((string)($row['customer_name'] ?? 'RCS Customer')) ?: 'RCS Customer';
        $initials = self::initials($name);
        $rating = max(1, min(5, (int)($row['rating'] ?? 5)));
        $createdAt = (string)($row['created_at'] ?? '');
        $createdDisplay = $createdAt !== '' && strtotime($createdAt) ? date('d M, Y', strtotime($createdAt)) : '';
        return [
            ...$row,
            'id' => (int)($row['id'] ?? 0),
            'product_id' => (int)($row['product_id'] ?? 0),
            'user_id' => (int)($row['user_id'] ?? 0),
            'rating' => $rating,
            'stars' => str_repeat('★', $rating) . str_repeat('☆', 5 - $rating),
            'customer_name' => $name,
            'customer_initials' => $initials,
            'customer_avatar' => (string)($row['customer_avatar'] ?? ''),
            'comment' => trim((string)($row['comment'] ?? '')),
            'status' => (string)($row['status'] ?? self::PENDING),
            'is_featured' => (int)($row['is_featured'] ?? 0) === 1,
            'created_display' => $createdDisplay,
        ];
    }

    private static function initials(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $letters = '';
        foreach (array_slice($parts, 0, 2) as $part) {
            $letters .= substr($part, 0, 1);
        }
        return strtoupper($letters !== '' ? $letters : 'RC');
    }
}
