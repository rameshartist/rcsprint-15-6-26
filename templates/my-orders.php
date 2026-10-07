<?php
$pageTitle = 'My Orders — RCS Graphic';
$currentPage = 'my-orders';
include INCLUDE_PATH . '/partials/head.php';
include INCLUDE_PATH . '/partials/header.php';
// cart-drawer is included by header.php — do not include again

$statusLabels = ['new_order'=>'New Order','received'=>'Received','design_approved'=>'Design Approved','processing'=>'Other Process','other_process'=>'Other Process','printing'=>'Printing','ready'=>'Dispatched','delivered'=>'Delivered','cancelled'=>'Cancelled','whatsapp_pending'=>'Pending'];
$statusColors = ['new_order'=>'b-blue','received'=>'b-blue','design_approved'=>'b-green','processing'=>'b-amber','other_process'=>'b-amber','printing'=>'b-orange','ready'=>'b-green','delivered'=>'b-ink','cancelled'=>'b-red','whatsapp_pending'=>'b-amber'];
$tlSteps = ['new_order','received','design_approved','printing','other_process','ready'];
$designApprovalLabels = ['pending_review'=>'Pending Review','issue_found'=>'Issue Found','proof_uploaded'=>'Waiting for Your Approval','revision_requested'=>'Revision Requested','approved'=>'Approved'];
?>
<div class="myord-hdr">
  <div class="container">
    <div class="myord-hdr-t">My Orders</div>
    <div class="myord-hdr-s">Track your print orders in real time</div>
    <div style="margin-top:10px">
      <a href="/profile" class="btn btn-outline btn-sm">👤 Manage Profile & Billing</a>
    </div>
  </div>
</div>
<div class="container" style="padding-top:24px;padding-bottom:60px">
  <?php if (empty($orders)): ?>
  <div style="text-align:center;padding:60px 20px">
    <div style="font-size:48px;margin-bottom:12px">📦</div>
    <div style="font-size:18px;font-weight:700;margin-bottom:8px">No Orders Yet</div>
    <div style="font-size:14px;color:var(--text2);margin-bottom:20px">Your orders will appear here</div>
    <a href="/categories" class="btn btn-blue">Browse Products →</a>
  </div>
  <?php else: ?>
  <?php foreach ($orders as $order):
    $orderTrackStatus = ($order['status'] ?? '') === 'processing' ? 'other_process' : ($order['status'] ?? '');
    $si = array_search($orderTrackStatus, $tlSteps);
    $si = $si === false ? -1 : $si;
    $items = $order['items'] ?? [];
    $itemDesc = implode(' + ', array_column($items, 'product_name'));
  ?>
  <div class="ord-card">
    <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:10px;margin-bottom:12px">
      <div>
        <div class="ord-id"><?= htmlspecialchars($order['order_id']) ?></div>
        <div class="ord-prod"><?= htmlspecialchars($itemDesc) ?></div>
        <div class="ord-meta">
          <?= date('d M Y', strtotime($order['created_at'])) ?> ·
          ₹<?= number_format($order['total_amount']) ?> ·
          <?= count($items) ?> item<?= count($items) > 1 ? 's' : '' ?>
        </div>
      </div>
      <span class="badge <?= $statusColors[$order['status']] ?? 'b-blue' ?>">
        <?= $statusLabels[$order['status']] ?? $order['status'] ?>
      </span>
    </div>

    <?php if ($order['status'] !== 'cancelled' && $order['status'] !== 'whatsapp_pending'): ?>
    <div class="ord-timeline">
      <?php foreach ($tlSteps as $i => $step): ?>
      <div class="tl-step <?= $i < $si ? 'done' : ($i === $si ? 'active' : '') ?>">
        <div class="tl-circle"><?= $i < $si ? '✓' : ($i + 1) ?></div>
        <div class="tl-label"><?= $statusLabels[$step] ?></div>
      </div>
      <?php if ($i < count($tlSteps) - 1): ?>
      <div class="tl-line <?= $i < $si ? 'done' : '' ?>"></div>
      <?php endif; ?>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div style="padding-top:8px;border-top:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px">
      <div style="font-size:12px;color:var(--text2)">
        <?php foreach ($items as $item): ?>
        <?= htmlspecialchars($item['product_name']) ?>: <?= number_format($item['quantity']) ?> × <?= htmlspecialchars($item['quality_name']) ?> |
        <?php endforeach; ?>
      </div>
      <div style="font-size:12px;color:var(--text2);flex-basis:100%">
        <?php foreach ($items as $item): ?>
          <?php $ds = (string)($item['design_approval_status'] ?? 'pending_review'); ?>
          <span style="<?= $ds === 'issue_found' ? 'display:inline-block;margin:3px 0;padding:6px 8px;border-radius:9px;background:#fff1f2;color:#be123c;font-weight:700' : '' ?>"><?= $ds === 'issue_found' ? '⚠ ' : '' ?><?= htmlspecialchars($item['product_name']) ?> design: <?= htmlspecialchars($designApprovalLabels[$ds] ?? ucfirst(str_replace('_', ' ', $ds))) ?><?= !empty($item['design_admin_note']) ? ' — ' . htmlspecialchars($item['design_admin_note']) : '' ?></span>
          <?php if (!empty($item['design_proof_file_id'])): ?> · <a href="/account/artwork/<?= (int)$item['design_proof_file_id'] ?>/download" target="_blank">Download proof</a><?php endif; ?> |
        <?php endforeach; ?>
      </div>
      <div style="display:flex;gap:7px">
        <?php if (in_array($order['payment_status'], ['paid']) && !empty($order['invoice_file_path'])): ?>
        <a href="/invoice/<?= htmlspecialchars($order['order_id']) ?>" class="btn btn-outline btn-xs" target="_blank">🧾 Invoice</a>
        <?php elseif (in_array($order['payment_status'], ['paid'])): ?>
        <span class="btn btn-outline btn-xs" style="opacity:.65;pointer-events:none">🧾 Invoice Soon</span>
        <?php endif; ?>
        <?php if ($order['payment_id']): ?>
        <button class="btn btn-outline btn-xs" onclick="alert('Payment ID:\n<?= htmlspecialchars($order['payment_id']) ?>')">🆔 PID</button>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
  <?php endif; ?>
</div>
<script>const CSRF = '<?= $csrf ?>';</script>
<script src="/assets/js/app.js"></script>
<?php include INCLUDE_PATH . '/partials/site-footer.php'; ?>
<?php include INCLUDE_PATH . '/partials/footer.php'; ?>
