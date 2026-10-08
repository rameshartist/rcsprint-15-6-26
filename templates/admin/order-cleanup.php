<?php
$pageTitle = 'Order Cleanup — RCS Admin';
$currentAdmPage = 'order-cleanup';
$admMainClass = 'adm-main--order-cleanup';
include __DIR__ . '/layout.php';
$tableExists = static function (string $table): bool {
    try {
        return (bool)Database::row(
            "SELECT 1 AS ok FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1",
            [$table]
        );
    } catch (Throwable) { return false; }
};
$countRows = static function (string $sql): int {
    try { return (int)(Database::row($sql)['c'] ?? 0); }
    catch (Throwable) { return 0; }
};
$counts = [
    'orders' => $tableExists('orders') ? $countRows('SELECT COUNT(*) AS c FROM orders') : 0,
    'order_items' => $tableExists('order_items') ? $countRows('SELECT COUNT(*) AS c FROM order_items') : 0,
    'status_history' => $tableExists('order_status_history') ? $countRows('SELECT COUNT(*) AS c FROM order_status_history WHERE order_id IN (SELECT id FROM orders)') : 0,
    'payments' => $tableExists('payments') ? $countRows('SELECT COUNT(*) AS c FROM payments WHERE order_id IN (SELECT id FROM orders)') : 0,
    'coupon_uses' => $tableExists('coupon_uses') ? $countRows('SELECT COUNT(*) AS c FROM coupon_uses WHERE order_id IN (SELECT id FROM orders)') : 0,
    'product_reviews' => $tableExists('product_reviews') ? $countRows('SELECT COUNT(*) AS c FROM product_reviews WHERE order_id IN (SELECT id FROM orders) OR order_item_id IN (SELECT id FROM order_items)') : 0,
    'design_approvals' => $tableExists('order_design_approvals') ? $countRows('SELECT COUNT(*) AS c FROM order_design_approvals WHERE order_id IN (SELECT id FROM orders) OR order_item_id IN (SELECT id FROM order_items)') : 0,
    'design_events' => $tableExists('order_design_events') ? $countRows('SELECT COUNT(*) AS c FROM order_design_events WHERE order_id IN (SELECT id FROM orders) OR order_item_id IN (SELECT id FROM order_items)') : 0,
    'artwork_files' => $tableExists('artwork_files') ? $countRows('SELECT COUNT(*) AS c FROM artwork_files WHERE order_item_id IN (SELECT id FROM order_items)') : 0,
];
$success = trim((string)($_GET['success'] ?? ''));
$error = trim((string)($_GET['error'] ?? ''));
$h = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
?>
<div class="oc-page">
  <section class="oc-hero">
    <div>
      <span class="oc-kicker">Super Admin Tool</span>
      <h1>Order Cleanup</h1>
      <p>Delete all current testing orders and related order records so you can restart order testing from a clean slate.</p>
    </div>
    <a class="oc-backup-link" href="/admin/backup">Download Backup First</a>
  </section>

  <?php if ($success !== ''): ?><div class="oc-alert oc-alert--success"><?= $h($success) ?></div><?php endif; ?>
  <?php if ($error !== ''): ?><div class="oc-alert oc-alert--error"><?= $h($error) ?></div><?php endif; ?>

  <section class="oc-grid">
    <article class="oc-card oc-card--danger">
      <h2>Danger Zone</h2>
      <p>This action permanently removes order data from the database. Use this only while all existing orders are testing orders.</p>
      <ul>
        <li>Orders, order items and status history will be deleted.</li>
        <li>Payments, coupon uses, reviews, design approvals and design events will be deleted when present.</li>
        <li>Linked artwork/proof file records will be deleted. Uploaded files can be moved to trash if selected.</li>
      </ul>
    </article>

    <article class="oc-card">
      <h2>Records Found</h2>
      <div class="oc-stats">
        <?php foreach ($counts as $label => $value): ?>
          <div><span><?= $h(ucwords(str_replace('_', ' ', $label))) ?></span><strong><?= number_format((int)$value) ?></strong></div>
        <?php endforeach; ?>
      </div>
    </article>
  </section>

  <form class="oc-confirm-card" method="post" action="/admin/order-cleanup/delete-all" onsubmit="return confirm('This will delete all testing orders and related records. Continue?')">
    <input type="hidden" name="_token" value="<?= $h($csrf ?? '') ?>">
    <div class="oc-filter-grid">
      <label class="oc-field"><span>Delete Scope</span><select class="fi" name="scope"><option value="all">All Orders</option><option value="filtered">Only Matching Orders</option></select></label>
      <label class="oc-field"><span>Order Status</span><select class="fi" name="status"><option value="">Any status</option><?php foreach(['new_order','received','design_approved','printing','other_process','ready','delivered','cancelled'] as $status):?><option value="<?= $h($status) ?>"><?= $h(ucwords(str_replace('_',' ',$status))) ?></option><?php endforeach;?></select></label>
      <label class="oc-field"><span>Payment Status</span><select class="fi" name="payment_status"><option value="">Any payment status</option><option value="pending">Pending</option><option value="paid">Paid</option><option value="failed">Failed</option></select></label>
      <label class="oc-field"><span>Order Type</span><select class="fi" name="order_type"><option value="">Any order type</option><option value="normal">Regular</option><option value="combo_offer">Combo Offer</option><option value="custom">Custom Order</option></select></label>
      <label class="oc-field"><span>Order ID / Customer Detail</span><input class="fi" name="search" placeholder="Order ID, name, email or mobile"></label>
    </div>
    <label class="oc-check"><input type="checkbox" name="archive_files" value="1" checked> Move linked uploaded artwork/proof files to <code>/uploads/.trash/order-cleanup/</code></label>
    <label class="oc-field"><span>Type <b>DELETE ALL ORDERS</b> to confirm</span><input type="text" name="confirm_text" placeholder="DELETE ALL ORDERS" autocomplete="off" required></label>
    <button class="oc-delete-btn" type="submit" <?= ((int)($counts['orders'] ?? 0) <= 0) ? 'disabled' : '' ?>>Delete Selected Orders</button>
    <p class="oc-note">Tip: take a backup before cleanup. This tool is visible only to Super Admin users.</p>
  </form>
</div>
