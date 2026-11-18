<?php
$pageTitle = 'Design History — RCS Admin';
$currentAdmPage = 'design-history';
include __DIR__ . '/layout.php';

\Orders\OrderManager::ensureDesignEventSchema();
$h = static fn($v): string => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$q = trim((string)($_GET['q'] ?? ''));
$eventType = trim((string)($_GET['event_type'] ?? 'all'));
$dateFrom = trim((string)($_GET['date_from'] ?? ''));
$dateTo = trim((string)($_GET['date_to'] ?? ''));
$actionFilter = trim((string)($_GET['action'] ?? 'all'));
$filtersActive = $q !== '' || ($eventType !== '' && $eventType !== 'all') || $dateFrom !== '' || $dateTo !== '' || $actionFilter !== 'all';
$where = [];
$params = [];
$eventSelect = "SELECT e.*, o.order_id AS public_order_id, o.customer_name, o.customer_email, o.customer_phone,
        o.status AS order_status, o.created_at AS order_created_at, o.total_amount,
        oi.product_name, oi.quantity, oi.design_choice,
        oda.status AS current_design_status,
        caf.file_path AS current_artwork_path, caf.original_name AS current_artwork_name, caf.filename AS current_artwork_filename,
        pf.file_path AS current_proof_path, pf.original_name AS current_proof_name, pf.filename AS current_proof_filename
   FROM order_design_events e
   LEFT JOIN orders o ON o.id = e.order_id
   LEFT JOIN order_items oi ON oi.id = e.order_item_id
   LEFT JOIN order_design_approvals oda ON oda.id = e.design_approval_id
   LEFT JOIN artwork_files caf ON caf.id = oda.customer_artwork_file_id
   LEFT JOIN artwork_files pf ON pf.id = oda.proof_file_id";
if ($q !== '') {
    $where[] = '(o.order_id LIKE ? OR o.customer_name LIKE ? OR o.customer_email LIKE ? OR o.customer_phone LIKE ? OR oi.product_name LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like, $like, $like);
}
if ($eventType !== '' && $eventType !== 'all') {
    $where[] = 'e.event_type = ?';
    $params[] = $eventType;
}
if ($dateFrom !== '') { $where[] = 'DATE(e.created_at) >= ?'; $params[] = $dateFrom; }
if ($dateTo !== '') { $where[] = 'DATE(e.created_at) <= ?'; $params[] = $dateTo; }
if ($actionFilter === 'needs_action') {
    $where[] = "oda.status IN ('pending_review','issue_found','proof_uploaded','revision_requested')";
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
$matchedEvents = [];
$events = [];
$eventTypes = [];
try {
    $matchedEvents = Database::rows("$eventSelect $whereSql ORDER BY e.created_at DESC, e.id DESC LIMIT 250", $params);
    $events = $matchedEvents;
    if ($filtersActive && $matchedEvents) {
        $matchingOrderIds = array_values(array_unique(array_filter(array_map(static fn($event) => (int)($event['order_id'] ?? 0), $matchedEvents))));
        if ($matchingOrderIds) {
            $placeholders = implode(',', array_fill(0, count($matchingOrderIds), '?'));
            $events = Database::rows("$eventSelect WHERE e.order_id IN ($placeholders) ORDER BY e.created_at DESC, e.id DESC LIMIT 600", $matchingOrderIds);
        }
    }
    $eventTypes = Database::rows("SELECT event_type, COUNT(*) AS count FROM order_design_events GROUP BY event_type ORDER BY event_type ASC");
} catch (Throwable $e) {
    error_log('Design history page failed: ' . $e->getMessage());
}
$eventLabels = [
    'design_file_superseded' => 'Previous Revision (Archived)',
    'order_placed' => 'New Order Placed',
    'design_approval_created' => 'Workflow Started',
    'design_approval_synced' => 'Workflow Synced',
    'admin_issue_marked' => 'Issue Marked',
    'admin_proof_uploaded' => 'Proof Uploaded',
    'admin_design_approved' => 'Admin Approved',
    'customer_design_approved' => 'Customer Approved',
    'customer_revision_requested' => 'Revision Requested',
    'customer_artwork_uploaded' => 'Artwork Uploaded',
    'customer_artwork_reuploaded' => 'Artwork Re-uploaded',
    'media_archived' => 'Media Archived',
    'design_status_changed' => 'Status Changed',
];
$eventTags = [
    'order_placed' => ['Order', 'neutral', '🧾'],
    'design_approval_created' => ['Start', 'neutral', '🚦'],
    'design_approval_synced' => ['Sync', 'neutral', '🔄'],
    'admin_issue_marked' => ['Issue', 'danger', '⚠️'],
    'admin_proof_uploaded' => ['Proof', 'info', '📤'],
    'admin_design_approved' => ['Approved', 'success', '✅'],
    'customer_design_approved' => ['Approved', 'success', '✅'],
    'customer_revision_requested' => ['Revision', 'danger', '📝'],
    'customer_artwork_uploaded' => ['Artwork', 'info', '📎'],
    'customer_artwork_reuploaded' => ['Artwork', 'info', '🔁'],
    'media_archived' => ['Archived', 'neutral', '🗄️'],
    'design_status_changed' => ['Status', 'neutral', '🔖'],
];
$statusLabels = ['pending_review'=>'Pending Review','issue_found'=>'Issue Found','proof_uploaded'=>'Proof Sent','revision_requested'=>'Revision Requested','approved'=>'Approved','new_order'=>'New Order','received'=>'Received','design_approved'=>'Design Approved','printing'=>'Printing','other_process'=>'Other Process','processing'=>'Other Process','ready'=>'Dispatched','delivered'=>'Delivered','cancelled'=>'Cancelled','whatsapp_pending'=>'WA Pending'];
$actorIcon = static fn(string $type): string => match ($type) { 'customer' => '👤', 'admin' => '🛡️', 'system' => '⚙️', default => '⚙️' };
$formatStatus = static function (?string $status) use ($statusLabels): string {
    $status = trim((string)$status);
    if ($status === '') return '';
    return $statusLabels[$status] ?? ucwords(str_replace(['_', '-'], ' ', $status));
};
$eventTone = static function (string $type, string $status): string {
    if (str_contains($type, 'issue') || str_contains($type, 'revision') || in_array($status, ['issue_found','revision_requested'], true)) return 'danger';
    if (str_contains($type, 'approved') || $status === 'approved') return 'success';
    if (str_contains($type, 'proof') || str_contains($type, 'uploaded') || str_contains($type, 'artwork')) return 'info';
    return 'neutral';
};
$eventTime = static fn(array $event): int => strtotime((string)($event['created_at'] ?? '')) ?: 0;
$normalizePath = static function (?string $path): string {
    $path = trim((string)$path);
    if ($path === '') return '';
    return $path[0] === '/' || preg_match('#^https?://#i', $path) ? $path : '/' . $path;
};
$orderGroups = [];
foreach ($events as $event) {
    $orderId = (int)($event['order_id'] ?? 0);
    $key = $orderId > 0 ? (string)$orderId : 'unknown-' . count($orderGroups);
    if (!isset($orderGroups[$key])) {
        $orderGroups[$key] = [
            'order_id' => $orderId,
            'public_order_id' => (string)($event['public_order_id'] ?? ($orderId ? '#' . $orderId : 'Unknown Order')),
            'customer_name' => (string)($event['customer_name'] ?? 'Customer'),
            'customer_phone' => (string)($event['customer_phone'] ?? ''),
            'customer_email' => (string)($event['customer_email'] ?? ''),
            'order_status' => (string)($event['order_status'] ?? ''),
            'order_created_at' => (string)($event['order_created_at'] ?? ''),
            'total_amount' => $event['total_amount'] ?? null,
            'products' => [],
            'events' => [],
            'matched_count' => 0,
            'needs_action' => false,
            'latest_time' => 0,
            'latest_label' => '',
            'current_design_status' => '',
            'tone' => 'neutral',
        ];
    }
    $productName = trim((string)($event['product_name'] ?? ''));
    if ($productName !== '') {
        $productKey = $productName . '|' . (string)($event['quantity'] ?? '');
        $orderGroups[$key]['products'][$productKey] = $productName . (!empty($event['quantity']) ? ' × ' . (float)$event['quantity'] : '');
    }
    $type = (string)($event['event_type'] ?? '');
    $statusAfter = (string)($event['status_after'] ?? '');
    $tone = $eventTone($type, $statusAfter);
    $event['_label'] = $eventLabels[$type] ?? ucwords(str_replace(['_', '-'], ' ', $type));
    $event['_tone'] = $tone;
    $event['_tag'] = $eventTags[$type] ?? [ucwords(str_replace(['_', '-'], ' ', $type ?: 'Update')), $tone, '•'];
    $orderGroups[$key]['events'][] = $event;
    $orderGroups[$key]['needs_action'] = $orderGroups[$key]['needs_action'] || in_array((string)($event['current_design_status'] ?? ''), ['pending_review','issue_found','proof_uploaded','revision_requested'], true);
    $time = $eventTime($event);
    if ($time >= $orderGroups[$key]['latest_time']) {
        $orderGroups[$key]['latest_time'] = $time;
        $orderGroups[$key]['latest_label'] = $event['_label'];
        $orderGroups[$key]['current_design_status'] = (string)($event['current_design_status'] ?? $statusAfter ?? '');
        $orderGroups[$key]['tone'] = $tone;
    }
}
$matchedFingerprints = [];
foreach ($matchedEvents as $event) {
    $matchedFingerprints[(int)($event['id'] ?? 0)] = true;
}
foreach ($orderGroups as &$group) {
    $hasOrderPlaced = false;
    foreach ($group['events'] as $existingEvent) {
        if ((string)($existingEvent['event_type'] ?? '') === 'order_placed') {
            $hasOrderPlaced = true;
            break;
        }
    }
    if (!$hasOrderPlaced && $group['order_created_at'] !== '' && ($eventType === 'all' || $eventType === '')) {
        $virtual = [
            'id' => 0,
            'event_type' => 'order_placed',
            'created_at' => $group['order_created_at'],
            'actor_type' => 'system',
            'actor_name' => 'System',
            'status_before' => '',
            'status_after' => 'new_order',
            'note' => 'Order received and design workflow started.',
            'file_path' => '',
            'file_name' => '',
            'current_design_status' => $group['current_design_status'],
            '_label' => $eventLabels['order_placed'],
            '_tone' => 'neutral',
            '_tag' => $eventTags['order_placed'],
            '_virtual' => true,
        ];
        $group['events'][] = $virtual;
    }
    usort($group['events'], static fn($a, $b): int => (strtotime((string)($a['created_at'] ?? '')) ?: 0) <=> (strtotime((string)($b['created_at'] ?? '')) ?: 0));
    $group['matched_count'] = $filtersActive
        ? count(array_filter($group['events'], static fn($event): bool => !empty($matchedFingerprints[(int)($event['id'] ?? 0)])))
        : count(array_filter($group['events'], static fn($event): bool => empty($event['_virtual'])));
    if ($group['latest_time'] === 0 && $group['events']) {
        $latest = end($group['events']);
        $group['latest_time'] = $eventTime($latest);
        $group['latest_label'] = (string)($latest['_label'] ?? 'Order Update');
    }
}
unset($group);
usort($orderGroups, static fn($a, $b): int => ($b['latest_time'] ?? 0) <=> ($a['latest_time'] ?? 0));
$needsActionCount = count(array_filter($orderGroups, static fn($group): bool => !empty($group['needs_action'])));
$approvedCount = count(array_filter($events, static fn($event): bool => (string)($event['current_design_status'] ?? '') === 'approved'));
$issueCount = count(array_filter($events, static fn($event): bool => in_array((string)($event['current_design_status'] ?? ''), ['issue_found','revision_requested'], true)));
$buildQuery = static function (array $extra = []) use ($q, $eventType, $dateFrom, $dateTo, $actionFilter): string {
    $base = ['q' => $q, 'event_type' => $eventType !== 'all' ? $eventType : '', 'date_from' => $dateFrom, 'date_to' => $dateTo, 'action' => $actionFilter !== 'all' ? $actionFilter : ''];
    $params = array_filter(array_merge($base, $extra), static fn($v) => $v !== '' && $v !== null);
    return $params ? ('?' . http_build_query($params)) : '';
};
?>

<div class="design-history-page">
  <section class="design-history-hero">
    <div>
      <span>Audit trail</span>
      <h1>Order Design History</h1>
      <p>Every order is grouped into an expandable card with artwork uploads, proofs, issues, revisions and approvals in one compact timeline.</p>
    </div>
    <strong><?= number_format(count($orderGroups)) ?> orders</strong>
  </section>

  <section class="design-history-stats" aria-label="Design history summary">
    <a class="design-history-stat <?= $actionFilter === 'needs_action' ? 'act' : '' ?>" href="/admin/design-history<?= $h($buildQuery(['action' => 'needs_action'])) ?>"><span>Needs Action</span><strong><?= number_format($needsActionCount) ?></strong></a>
    <div class="design-history-stat"><span>Approved Events</span><strong><?= number_format($approvedCount) ?></strong></div>
    <div class="design-history-stat"><span>Issues / Revisions</span><strong><?= number_format($issueCount) ?></strong></div>
    <a class="design-history-stat" href="/admin/design-history"><span>Reset View</span><strong>All</strong></a>
  </section>

  <details class="design-history-filter-panel adm-filter-panel" open>
    <summary class="adm-filter-summary"><span><i class="fa-solid fa-filter" aria-hidden="true"></i> Filter History</span><em>Order, event, date & action</em><i class="fa-solid fa-chevron-down" aria-hidden="true"></i></summary>
  <form class="design-history-filters" method="GET">
    <input type="search" name="q" value="<?= $h($q) ?>" placeholder="Search order, customer, product…">
    <select name="event_type">
      <option value="all">All events</option>
      <?php foreach ($eventTypes as $type): $key = (string)($type['event_type'] ?? ''); ?>
        <option value="<?= $h($key) ?>" <?= $eventType === $key ? 'selected' : '' ?>><?= $h($eventLabels[$key] ?? ucwords(str_replace('_', ' ', $key))) ?> (<?= number_format((int)($type['count'] ?? 0)) ?>)</option>
      <?php endforeach; ?>
    </select>
    <input type="date" name="date_from" value="<?= $h($dateFrom) ?>">
    <input type="date" name="date_to" value="<?= $h($dateTo) ?>">
    <select name="action">
      <option value="all" <?= $actionFilter === 'all' ? 'selected' : '' ?>>All action states</option>
      <option value="needs_action" <?= $actionFilter === 'needs_action' ? 'selected' : '' ?>>Needs action only</option>
    </select>
    <button type="submit">Filter</button>
    <?php if ($filtersActive): ?><a href="/admin/design-history">Clear</a><?php endif; ?>
  </form>
  </details>

  <?php if (!$orderGroups): ?>
    <div class="design-history-empty">No design history events found yet. New uploads, proofs, issues and approvals will appear here automatically.</div>
  <?php else: ?>
    <section class="design-order-list" aria-label="Grouped order design history">
      <?php foreach ($orderGroups as $index => $group):
        $orderLink = !empty($group['order_id']) ? '/admin/orders?q=' . urlencode((string)$group['public_order_id']) : '/admin/orders';
        $latestDate = !empty($group['latest_time']) ? date('d M, h:i A', (int)$group['latest_time']) : 'No date';
        $products = array_values($group['products']);
        $productSummary = $products ? implode(', ', array_slice($products, 0, 2)) : 'No product linked';
        if (count($products) > 2) $productSummary .= ' +' . (count($products) - 2) . ' more';
        $currentStatus = $formatStatus((string)($group['current_design_status'] ?: $group['order_status']));
        $tone = (string)($group['tone'] ?? 'neutral');
      ?>
        <details class="design-order-card design-order-card--<?= $h($tone) ?>" <?= $index === 0 ? 'open' : '' ?>>
          <summary class="design-order-summary">
            <span class="design-order-caret" aria-hidden="true">⌄</span>
            <span class="design-order-id"><?= $h($group['public_order_id']) ?></span>
            <span class="design-order-customer"><b><?= $h($group['customer_name']) ?></b><?php if ($group['customer_phone'] !== ''): ?><small><?= $h($group['customer_phone']) ?></small><?php endif; ?></span>
            <span class="design-order-product" title="<?= $h(implode(', ', $products)) ?>"><?= $h($productSummary) ?></span>
            <span class="design-order-status design-order-status--<?= $h($tone) ?>"><?= $h($currentStatus !== '' ? $currentStatus : 'Updated') ?></span>
            <span class="design-order-latest"><b><?= $h($group['latest_label'] ?: 'Latest Update') ?></b><small><?= $h($latestDate) ?></small></span>
            <span class="design-order-count"><?= number_format((int)count($group['events'])) ?> history<?= $filtersActive ? ' · ' . number_format((int)$group['matched_count']) . ' matched' : '' ?></span>
          </summary>
          <div class="design-order-body">
            <div class="design-order-body-head">
              <div>
                <strong><?= $h($group['public_order_id']) ?> complete design timeline</strong>
                <span><?= $filtersActive ? 'Filter matched this order; full related history is shown below for context.' : 'Order placement to latest design update.' ?></span>
              </div>
              <a href="<?= $h($orderLink) ?>">View Order</a>
            </div>
            <div class="design-order-timeline">
              <?php foreach ($group['events'] as $event):
                $label = (string)($event['_label'] ?? ($eventLabels[(string)($event['event_type'] ?? '')] ?? 'Order Update'));
                [$tagText, $tagTone, $tagIcon] = $event['_tag'] ?? ['Update', (string)($event['_tone'] ?? 'neutral'), '•'];
                $eventToneClass = (string)($event['_tone'] ?? $tagTone);
                $statusBefore = $formatStatus((string)($event['status_before'] ?? ''));
                $statusAfter = $formatStatus((string)($event['status_after'] ?? ''));
                $filePath = $normalizePath((string)($event['file_path'] ?? ''));
                $fileName = trim((string)($event['file_name'] ?? ''));
                $fallbackProofPath = $normalizePath((string)($event['current_proof_path'] ?? ''));
                $fallbackArtworkPath = $normalizePath((string)($event['current_artwork_path'] ?? ''));
                if ($filePath === '' && in_array((string)($event['event_type'] ?? ''), ['admin_design_approved','customer_design_approved'], true)) {
                    $filePath = $fallbackProofPath !== '' ? $fallbackProofPath : $fallbackArtworkPath;
                    $fileName = $fallbackProofPath !== ''
                        ? trim((string)(($event['current_proof_name'] ?? '') ?: ($event['current_proof_filename'] ?? '')))
                        : trim((string)(($event['current_artwork_name'] ?? '') ?: ($event['current_artwork_filename'] ?? '')));
                }
                $eventCreated = strtotime((string)($event['created_at'] ?? '')) ?: 0;
                $isMatched = !$filtersActive || !empty($event['_virtual']) || !empty($matchedFingerprints[(int)($event['id'] ?? 0)]);
              ?>
                <article class="design-history-row design-history-row--<?= $h($eventToneClass) ?> <?= $isMatched ? 'is-matched' : 'is-context' ?>">
                  <time><strong><?= $h($eventCreated ? date('d M', $eventCreated) : '—') ?></strong><span><?= $h($eventCreated ? date('h:i A', $eventCreated) : '') ?></span></time>
                  <div class="design-history-row-main">
                    <div class="design-history-row-title">
                      <span class="design-history-tag design-history-tag--<?= $h($tagTone) ?>"><?= $h($tagIcon . ' ' . $tagText) ?></span>
                      <strong><?= $h($label) ?></strong>
                      <em><?= $h($actorIcon((string)($event['actor_type'] ?? 'system')) . ' ' . ucfirst((string)($event['actor_type'] ?? 'system'))) ?></em>
                      <?php if (!empty($event['_virtual'])): ?><em>Auto summary</em><?php endif; ?>
                    </div>
                    <div class="design-history-row-meta">
                      <?php if (!empty($event['actor_name'])): ?><span>By <?= $h($event['actor_name']) ?></span><?php endif; ?>
                      <?php if (!empty($event['product_name'])): ?><span><?= $h($event['product_name']) ?><?= !empty($event['quantity']) ? ' × ' . $h((float)$event['quantity']) : '' ?></span><?php endif; ?>
                    </div>
                    <?php if (!empty($event['note'])): ?><p><?= $h($event['note']) ?></p><?php endif; ?>
                  </div>
                  <div class="design-history-row-side">
                    <?php if ($statusBefore !== '' || $statusAfter !== ''): ?>
                      <span class="design-history-flow"><small><?= $h($statusBefore !== '' ? $statusBefore : 'New') ?></small><i>→</i><b><?= $h($statusAfter !== '' ? $statusAfter : 'Updated') ?></b></span>
                    <?php endif; ?>
                    <?php if ($filePath !== ''): ?>
                      <span class="design-history-file-actions">
                        <a href="<?= $h($filePath) ?>" target="_blank" rel="noopener">View</a>
                        <a href="<?= $h($filePath) ?>" download>Download</a>
                      </span>
                    <?php endif; ?>
                  </div>
                </article>
              <?php endforeach; ?>
            </div>
          </div>
        </details>
      <?php endforeach; ?>
    </section>
  <?php endif; ?>
</div>

    </div></div></div>

<script>
(() => {
  const panels = document.querySelectorAll('.adm-filter-panel');
  if (!panels.length) return;
  const mobileQuery = window.matchMedia('(max-width: 760px)');
  const syncPanels = (event) => {
    panels.forEach((panel) => {
      if (mobileQuery.matches) {
        if (!event) panel.open = false;
      } else {
        panel.open = true;
      }
    });
  };
  syncPanels();
  if (typeof mobileQuery.addEventListener === 'function') {
    mobileQuery.addEventListener('change', syncPanels);
  } else if (typeof mobileQuery.addListener === 'function') {
    mobileQuery.addListener(syncPanels);
  }
})();
</script>
</body></html>
