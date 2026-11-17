<?php
namespace Faq;

class FaqManager
{
    public const PAGE_LABELS = [
        'contact' => 'FAQs for Contact Page',
        'product_detail' => 'FAQs for All Product Detail Pages',
    ];

    public static function ensureSchema(): bool
    {
        try {
            \Database::query(
                "CREATE TABLE IF NOT EXISTS faqs (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    page_key VARCHAR(40) NOT NULL,
                    question VARCHAR(255) NOT NULL,
                    answer TEXT NOT NULL,
                    sort_order INT NOT NULL DEFAULT 0,
                    is_active TINYINT(1) NOT NULL DEFAULT 1,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_faqs_page_active (page_key, is_active, sort_order, id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
            try { \Database::query("ALTER TABLE faqs ADD COLUMN topic VARCHAR(160) NULL AFTER page_key"); } catch (\Throwable) {}
            try { \Database::query("ALTER TABLE faqs ADD COLUMN category_id INT UNSIGNED NULL AFTER topic"); } catch (\Throwable) {}
            return true;
        } catch (\Throwable $e) {
            error_log('FAQ schema unavailable: ' . $e->getMessage());
            return false;
        }
    }

    public static function normalizePageKey(string $pageKey): string
    {
        return array_key_exists($pageKey, self::PAGE_LABELS) ? $pageKey : 'contact';
    }

    public static function listByPage(string $pageKey, bool $activeOnly = true, ?int $categoryId = null): array
    {
        if (!self::ensureSchema()) return [];
        $pageKey = self::normalizePageKey($pageKey);
        $where = 'WHERE page_key = ?';
        $params = [$pageKey];
        if ($activeOnly) $where .= ' AND is_active = 1';
        if ($pageKey === 'product_detail' && $categoryId) { $where .= ' AND (category_id IS NULL OR category_id = ?)'; $params[] = $categoryId; }
        return \Database::rows(
            "SELECT id, page_key, topic, category_id, question, answer, sort_order, is_active, created_at, updated_at
             FROM faqs
             {$where}
             ORDER BY sort_order ASC, id DESC",
            $params
        );
    }

    public static function all(): array
    {
        if (!self::ensureSchema()) return [];
        return \Database::rows(
            "SELECT id, page_key, topic, category_id, question, answer, sort_order, is_active, created_at, updated_at
             FROM faqs
             ORDER BY FIELD(page_key, 'contact', 'product_detail'), sort_order ASC, id DESC"
        );
    }

    public static function save(array $data, int $id = 0): array
    {
        if (!self::ensureSchema()) return ['ok' => false, 'msg' => 'FAQ system is not ready.'];
        $pageKey = self::normalizePageKey((string)($data['page_key'] ?? 'contact'));
        $topic = trim((string)($data['topic'] ?? ''));
        $categoryId = (int)($data['category_id'] ?? 0) ?: null;
        $question = trim((string)($data['question'] ?? ''));
        $answer = trim((string)($data['answer'] ?? ''));
        $sortOrder = (int)($data['sort_order'] ?? 0);
        $isActive = !empty($data['is_active']) ? 1 : 0;
        if ($question === '') return ['ok' => false, 'msg' => 'Question is required.'];
        if ($answer === '') return ['ok' => false, 'msg' => 'Answer is required.'];

        if ($id > 0) {
            \Database::query(
                "UPDATE faqs
                    SET page_key = ?, topic = ?, category_id = ?, question = ?, answer = ?, sort_order = ?, is_active = ?, updated_at = NOW()
                  WHERE id = ?",
                [$pageKey, $topic, $categoryId, $question, $answer, $sortOrder, $isActive, $id]
            );
            return ['ok' => true, 'id' => $id, 'msg' => 'FAQ updated.'];
        }

        $newId = (int)\Database::insert(
            "INSERT INTO faqs (page_key, topic, category_id, question, answer, sort_order, is_active, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW())",
            [$pageKey, $topic, $categoryId, $question, $answer, $sortOrder, $isActive]
        );
        return ['ok' => true, 'id' => $newId, 'msg' => 'FAQ added.'];
    }

    public static function delete(int $id): array
    {
        if (!self::ensureSchema()) return ['ok' => false, 'msg' => 'FAQ system is not ready.'];
        if ($id <= 0) return ['ok' => false, 'msg' => 'Invalid FAQ.'];
        \Database::query("DELETE FROM faqs WHERE id = ?", [$id]);
        return ['ok' => true, 'msg' => 'FAQ deleted.'];
    }

    public static function toggle(int $id): array
    {
        if (!self::ensureSchema()) return ['ok' => false, 'msg' => 'FAQ system is not ready.'];
        $row = \Database::row("SELECT is_active FROM faqs WHERE id = ?", [$id]);
        if (!$row) return ['ok' => false, 'msg' => 'FAQ not found.'];
        $next = (int)($row['is_active'] ?? 0) ? 0 : 1;
        \Database::query("UPDATE faqs SET is_active = ?, updated_at = NOW() WHERE id = ?", [$next, $id]);
        return ['ok' => true, 'is_active' => $next, 'msg' => 'FAQ status updated.'];
    }
}
