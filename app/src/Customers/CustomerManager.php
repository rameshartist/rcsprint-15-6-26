<?php
declare(strict_types=1);

namespace Customers;

final class CustomerManager
{
    /** Permanently remove the account and its dependent database records atomically. */
    public static function delete(int $customerId): bool
    {
        if ($customerId <= 0) return false;
        $db = \Database::get();
        // Optional feature tables differ between installations. Metadata errors must fail,
        // rather than being mistaken for absent tables and leaving a partial deletion.
        $columns = [];
        foreach (\Database::rows('SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()') as $column) {
            $columns[$column['TABLE_NAME']][$column['COLUMN_NAME']] = true;
        }
        $db->beginTransaction();
        try {
            $customer = \Database::row('SELECT id FROM users WHERE id = ? FOR UPDATE', [$customerId]);
            if (!$customer) { $db->rollBack(); return false; }
            $ids = static function (string $table, string $column, array $values) use ($columns): array {
                if (!$values || !isset($columns[$table][$column])) return [];
                $found = [];
                foreach (array_chunk($values, 500) as $batch) {
                    $marks = implode(',', array_fill(0, count($batch), '?'));
                    $found = array_merge($found, array_map('intval', array_column(\Database::rows("SELECT id FROM `{$table}` WHERE `{$column}` IN ({$marks})", $batch), 'id')));
                }
                return $found;
            };
            $remove = static function (string $table, string $column, array $values) use ($columns): void {
                if (!$values || !isset($columns[$table][$column])) return;
                // Bound batches avoid large prepared statements on long-lived accounts.
                foreach (array_chunk(array_values(array_unique($values)), 500) as $batch) {
                    $marks = implode(',', array_fill(0, count($batch), '?'));
                    \Database::query("DELETE FROM `{$table}` WHERE `{$column}` IN ({$marks})", $batch);
                }
            };
            $orders = $ids('orders', 'user_id', [$customerId]);
            $items = $ids('order_items', 'order_id', $orders);
            $carts = $ids('carts', 'user_id', [$customerId]);
            $cartItems = $ids('cart_items', 'cart_id', $carts);
            $quotes = array_values(array_unique(array_merge(
                $ids('custom_quote_requests', 'user_id', [$customerId]),
                $ids('custom_quote_requests', 'order_id', $orders)
            )));
            // Remove children before parents; never disable foreign-key checks.
            foreach (['product_reviews', 'order_design_events', 'order_design_approvals'] as $table) {
                $remove($table, 'user_id', [$customerId]);
                $remove($table, 'order_id', $orders);
                $remove($table, 'order_item_id', $items);
            }
            $remove('artwork_files', 'uploaded_by', [$customerId]);
            $remove('artwork_files', 'order_item_id', $items);
            $remove('artwork_files', 'cart_item_id', $cartItems);
            foreach (['payments', 'coupon_uses', 'order_status_history'] as $table) {
                $remove($table, 'order_id', $orders);
                $remove($table, 'user_id', [$customerId]);
            }
            $remove('cart_items', 'custom_quote_id', $quotes);
            $remove('cart_items', 'cart_id', $carts);
            $remove('order_items', 'order_id', $orders);
            $remove('orders', 'user_id', [$customerId]);
            $remove('custom_quote_requests', 'id', $quotes);
            $remove('carts', 'user_id', [$customerId]);
            $remove('user_addresses', 'user_id', [$customerId]);
            $remove('wishlists', 'user_id', [$customerId]);
            $remove('users', 'id', [$customerId]);
            $db->commit();
            return true;
        } catch (\Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }
}
