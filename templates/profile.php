<?php
$pageTitle = 'My Account — RCS Graphic';
$currentPage = 'profile';
include INCLUDE_PATH . '/partials/head.php';
include INCLUDE_PATH . '/partials/header.php';

$pageHero = [
    'key' => 'profile',
    'title' => 'My Account',
    'subtitle' => 'Manage your profile, track orders, review design approvals and download invoices in one place.',
    'eyebrow' => 'Customer Portal',
    'fallback_image' => '/assets/images/sample-products/stationery/stationery-1.svg',
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => '/'],
        ['label' => 'My Account', 'url' => null],
    ],
];
$profile = $profile ?? [];
$billing = $profile['billing'] ?? [];
$shipping = $profile['shipping'] ?? [];
$orders = is_array($orders ?? null) ? $orders : [];
$customOrders = is_array($customOrders ?? null) ? $customOrders : [];
$customFulfillmentOrders = is_array($customFulfillmentOrders ?? null) ? $customFulfillmentOrders : [];
$reviewableItems = is_array($reviewableItems ?? null) ? $reviewableItems : [];
$myReviews = is_array($myReviews ?? null) ? $myReviews : [];
$wishlistItems = is_array($wishlistItems ?? null) ? $wishlistItems : [];
$myDesigns = is_array($myDesigns ?? null) ? $myDesigns : [];

$h = static fn($value): string => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
$name = trim((string)($profile['name'] ?? $user['name'] ?? 'RCS Customer'));
$email = trim((string)($profile['email'] ?? $user['email'] ?? ''));
$phone = trim((string)($profile['phone'] ?? $user['phone'] ?? ''));
$company = trim((string)($profile['company'] ?? $user['company'] ?? ''));
$city = trim((string)($shipping['city'] ?? $billing['city'] ?? 'Rajkot'));
$state = trim((string)($shipping['state'] ?? $billing['state'] ?? 'Gujarat'));
$pincode = trim((string)($shipping['pincode'] ?? $billing['pincode'] ?? ''));
$locationParts = array_filter([$city, $state, $pincode]);
$location = $locationParts ? implode(', ', $locationParts) : 'Add your default address';
$initials = strtoupper(substr(trim($name), 0, 1) ?: 'R');
$hasSavedAddress = trim((string)($shipping['address_line1'] ?? '')) !== '' || trim((string)($billing['address_line1'] ?? '')) !== '';
$savedAddressCount = $hasSavedAddress ? 1 : 0;

$statusLabels = [
    'new_order' => 'New Order',
    'received' => 'Received',
    'design_approved' => 'Design Approved',
    'printing' => 'Printing',
    'other_process' => 'Other Process',
    'processing' => 'Other Process',
    'ready' => 'Dispatched',
    'delivered' => 'Delivered',
    'cancelled' => 'Cancelled',
    'whatsapp_pending' => 'Pending',
    'new' => 'Request Received',
    'reviewing' => 'Under Review',
    'quoted' => 'Quote Ready',
    'sent_to_customer' => 'Quote Sent',
    'customer_approved' => 'Approved',
    'payment_pending' => 'Payment Pending',
    'converted_to_order' => 'Paid',
    'rejected' => 'Rejected',
    'closed' => 'Closed',
];
$designApprovalLabels = ['pending_review'=>'Pending Review','issue_found'=>'Issue Found','proof_uploaded'=>'Waiting for Your Approval','revision_requested'=>'Revision Requested','approved'=>'Approved'];
$progressStatuses = ['new_order', 'received', 'design_approved', 'printing', 'other_process', 'processing', 'ready', 'whatsapp_pending'];
$totalOrders = count($orders);
$progressOrders = count(array_filter($orders, static fn($order) => in_array((string)($order['status'] ?? ''), $progressStatuses, true)));
$completedOrders = count(array_filter($orders, static fn($order) => (string)($order['status'] ?? '') === 'delivered'));
$recentOrders = array_slice($orders, 0, 4);

// Combine custom quote and fulfillment records into one customer-facing list.
// Paid custom orders stay in the production orders table, but are presented only in My Custom Orders.
$customOrdersByQuote = [];
foreach ($customFulfillmentOrders as $customOrder) {
    $quoteId = (int)($customOrder['custom_quote_id'] ?? 0);
    if ($quoteId > 0) $customOrdersByQuote[$quoteId] = $customOrder;
}
$customOrderDisplay = [];
foreach ($customOrders as $quote) {
    $quoteId = (int)($quote['id'] ?? 0);
    if (isset($customOrdersByQuote[$quoteId])) {
        $customOrder = $customOrdersByQuote[$quoteId];
        $customOrder['custom_request'] = $quote;
        $customOrderDisplay[] = $customOrder;
        unset($customOrdersByQuote[$quoteId]);
        continue;
    }
    $customOrderDisplay[] = [
        'order_id' => (string)($quote['request_code'] ?? ('CQ-' . $quoteId)),
        'created_at' => $quote['created_at'] ?? null,
        'total_amount' => (float)($quote['quoted_amount'] ?? 0),
        'payment_status' => (string)($quote['payment_status'] ?? 'not_required'),
        'payment_method' => '',
        'status' => (string)($quote['status'] ?? 'new'),
        'quote_token' => (string)($quote['quote_token'] ?? ''),
        'is_quote_only' => true,
        'custom_request' => $quote,
        'items' => [[
            'product_name' => (string)($quote['product_name'] ?? 'Custom Product'),
            'quantity' => (int)($quote['quantity'] ?? 1),
            'quality_name' => (string)($quote['material_type'] ?? 'Custom specification'),
            'custom_size_dimension' => (string)($quote['size_dimension'] ?? ''),
        ]],
    ];
}
foreach ($customOrdersByQuote as $customOrder) $customOrderDisplay[] = $customOrder;
usort($customOrderDisplay, static fn(array $a, array $b): int => strcmp((string)($b['created_at'] ?? ''), (string)($a['created_at'] ?? '')));
$accountSettings = is_array($settingsMap ?? null) ? $settingsMap : [];
if ($accountSettings === []) {
    try {
        $settingsRows = Database::rows("SELECT `key`, value FROM settings");
        $accountSettings = array_column($settingsRows, 'value', 'key');
    } catch (\Throwable) {
        $accountSettings = [];
    }
}
$accountBizPhoneRaw = trim((string)($accountSettings['biz_phone'] ?? '+91 8980000023')) ?: '+91 8980000023';
$accountBizPhone = $h($accountBizPhoneRaw);
$accountBizPhoneHref = preg_replace('/\D+/', '', $accountBizPhoneRaw);
$accountBizWaRaw = trim((string)($accountSettings['biz_whatsapp'] ?? $accountBizPhoneRaw));
$accountBizWa = preg_replace('/\D+/', '', $accountBizWaRaw);
if ($accountBizWa === '') {
    $accountBizWa = $accountBizPhoneHref;
}
$accountShortFileName = static function (?string $name, string $fallback = 'File'): string {
    $name = trim((string)$name);
    if ($name === '') return $fallback;
    if (strlen($name) <= 24) return $name;
    $ext = pathinfo($name, PATHINFO_EXTENSION);
    $base = pathinfo($name, PATHINFO_FILENAME);
    return substr($base !== '' ? $base : $name, 0, 16) . '…' . ($ext !== '' ? '.' . $ext : '');
};
$accountNormalizeAssetPath = static function (?string $path): string {
    $path = trim((string)$path);
    if ($path === '') return '';
    return $path[0] === '/' ? $path : '/' . $path;
};
$accountIsImageFile = static function (?string $mime, ?string $name, ?string $path = null): bool {
    $mime = strtolower(trim((string)$mime));
    $source = trim((string)($name ?: $path));
    $ext = strtolower(pathinfo($source, PATHINFO_EXTENSION));
    return str_starts_with($mime, 'image/') || in_array($ext, ['jpg','jpeg','png','gif','webp','svg'], true);
};

$renderOrders = static function (array $list, bool $compact = false, bool $custom = false) use ($h, $statusLabels, $designApprovalLabels, $accountShortFileName, $accountNormalizeAssetPath, $accountIsImageFile): void {
    if (empty($list)) {
        ?>
        <div class="account-empty-state">
          <i class="fa-solid fa-box-open"></i>
          <strong>No orders yet</strong>
          <span><?= $custom ? 'Your custom quotes and orders will appear here.' : 'Your print orders will appear here after checkout.' ?></span>
          <a href="/categories" class="btn btn-blue btn-sm">Browse Products</a>
        </div>
        <?php
        return;
    }
    ?>
    <div class="account-order-table" role="table" aria-label="<?= $compact ? 'Recent orders' : 'All orders' ?>">
      <div class="account-order-row account-order-head" role="row">
        <span>Order ID</span><span>Date</span><span>Products</span><span>Amount</span><span>Status</span><span>Action</span>
      </div>
      <?php foreach ($list as $order):
        $items = is_array($order['items'] ?? null) ? $order['items'] : [];
        $status = (string)($order['status'] ?? 'new_order');
        $statusClass = preg_replace('/[^a-z0-9_-]/i', '', $status);
        $productTitle = implode(', ', array_filter(array_map(static fn($item) => (string)($item['product_name'] ?? ''), $items)));
        $orderPublicId = (string)($order['order_id'] ?? $order['id'] ?? '');
        $isPaid = in_array((string)($order['payment_status'] ?? ''), ['paid'], true);
        $hasAdminUpdate = !empty($order['admin_update_pending']);
        $adminUpdateLabel = match ((string)($order['admin_update_type'] ?? '')) {
            'admin_proof_uploaded' => 'Proof Ready — Your Approval Required',
            'admin_issue_marked' => 'Artwork Action Required',
            'admin_design_approved' => 'Design Approved',
            'order_status_updated' => 'Order Status Updated',
            'invoice_uploaded' => 'Invoice Available',
            'shipping_updated' => 'Shipping Details Updated',
            default => 'New Order Update',
        };
        $isQuoteOnly = !empty($order['is_quote_only']);
        $customRequest = is_array($order['custom_request'] ?? null) ? $order['custom_request'] : [];
        $canPayCustom = $custom && $isQuoteOnly && !$isPaid
            && !empty($order['quote_token'])
            && in_array($status, ['customer_approved', 'payment_pending'], true);
        $trackSteps = ['received', 'design_approved', 'printing', 'other_process', 'ready'];
        $trackStatus = $status === 'processing' ? 'other_process' : $status;
        $trackIndex = array_search($trackStatus, $trackSteps, true);
        $trackIndex = $trackIndex === false ? -1 : (int)$trackIndex;
        $isCancelled = $status === 'cancelled';
        $isWhatsappPending = $status === 'whatsapp_pending';
      ?>
        <details id="account-order-<?= $h(preg_replace('/[^A-Za-z0-9_-]+/', '-', $orderPublicId)) ?>" class="account-order-detail <?= $hasAdminUpdate ? 'has-admin-update' : '' ?>" data-order-detail="<?= $h($orderPublicId) ?>" data-order-db-id="<?= (int)($order['id'] ?? 0) ?>" data-admin-update-type="<?= $h($order['admin_update_type'] ?? '') ?>">
          <summary class="account-order-row" role="row">
            <strong>#<?= $h($order['order_id'] ?? $order['id'] ?? '') ?></strong>
            <span><?= !empty($order['created_at']) ? date('d M, Y', strtotime((string)$order['created_at'])) : '—' ?></span>
            <span class="account-product-count" title="<?= $h($productTitle) ?>"><?= count($items) ?> item<?= count($items) === 1 ? '' : 's' ?></span>
            <b>₹<?= number_format((float)($order['total_amount'] ?? 0)) ?></b>
            <span class="account-status status-<?= $h($statusClass) ?>"><?= $h($hasAdminUpdate ? $adminUpdateLabel : ($statusLabels[$status] ?? ucfirst($status))) ?></span>
            <span class="account-mini-btn">Actions <i class="fa-solid fa-chevron-down" aria-hidden="true"></i></span>
          </summary>
          <div class="account-order-expanded">
            <div class="account-order-items-panel">
              <div class="account-order-block-title"><strong><?= $custom ? 'Custom Order Details' : 'Order Items & Files' ?></strong><span><?= $isQuoteOnly ? 'Approved quote and specifications' : 'Artwork and proofs are separated item-wise' ?></span></div>
              <?php if (empty($items)): ?>
                <p>No product items found for this order.</p>
              <?php else: ?>
                <div class="account-order-item-cards">
                  <?php foreach ($items as $item):
                    $designStatus = (string)($item['design_approval_status'] ?? 'pending_review');
                    $productImg = $accountNormalizeAssetPath($item['product_image'] ?? '');
                    $artworkName = (string)($item['artwork_original_name'] ?? $item['artwork_filename'] ?? '');
                    $artworkPath = $accountNormalizeAssetPath($item['artwork_file_path'] ?? '');
                    $artworkIsImage = $accountIsImageFile($item['artwork_mime_type'] ?? '', $artworkName, $artworkPath);
                    $proofName = (string)($item['design_proof_original_name'] ?? $item['design_proof_filename'] ?? '');
                    $proofPath = trim((string)($item['design_proof_file_path'] ?? ''));
                    $proofPath = $accountNormalizeAssetPath($proofPath);
                    $proofMime = strtolower((string)($item['design_proof_mime_type'] ?? ''));
                    $proofExt = strtolower(pathinfo($proofName !== '' ? $proofName : (string)($item['design_proof_filename'] ?? ''), PATHINFO_EXTENSION));
                    $proofIsImage = str_starts_with($proofMime, 'image/') || in_array($proofExt, ['jpg','jpeg','png','gif','webp','svg'], true);
                    $approvalId = (int)($item['design_approval_id'] ?? 0);
                    $canReviewProof = $approvalId > 0 && !empty($item['design_proof_file_id']) && in_array($designStatus, ['proof_uploaded'], true);
                    $canInitialArtworkUpload = $approvalId > 0 && (string)($item['design_choice'] ?? '') === 'upload' && empty($item['artwork_file_id']) && empty($item['artwork_filename']) && $designStatus === 'pending_review';
                    $canUploadArtwork = $approvalId > 0 && ($designStatus === 'issue_found' || $canInitialArtworkUpload);
                  ?>
                    <article class="account-order-item-card <?= $isQuoteOnly ? 'is-quote-only' : '' ?> <?= in_array($designStatus, ['issue_found','revision_requested'], true) ? 'account-design-issue' : '' ?>" data-design-approval-item="<?= $approvalId ?>" data-design-status="<?= $h($designStatus) ?>">
                      <div class="account-order-item-thumb">
                        <?php if ($productImg !== ''): ?><img src="<?= $h($productImg) ?>" alt="<?= $h($item['product_name'] ?? 'Product') ?>" loading="lazy"><?php else: ?><i class="fa-solid fa-box-open"></i><?php endif; ?>
                      </div>
                      <div class="account-order-item-main">
                        <div class="account-order-item-title">
                          <strong><?= $h($item['product_name'] ?? 'Product') ?></strong>
                          <span data-design-status-label><?= $isQuoteOnly ? 'Quote Details' : $h($designApprovalLabels[$designStatus] ?? ucfirst(str_replace('_', ' ', $designStatus))) ?></span>
                        </div>
                        <p><?= number_format((float)($item['quantity'] ?? 0)) ?> qty × <?= $h($item['quality_name'] ?? 'Standard') ?></p>
                        <?php if ($isQuoteOnly && !empty($item['custom_size_dimension'])): ?><small>Size / Dimension: <?= $h($item['custom_size_dimension']) ?></small><?php endif; ?>
                        <?php if ($isQuoteOnly && !empty($customRequest['quote_note'])): ?><small><?= nl2br($h($customRequest['quote_note'])) ?></small><?php endif; ?>
                        <?php if (!empty($item['design_admin_note'])): ?><small class="<?= $designStatus === 'issue_found' ? 'account-design-alert-note' : '' ?>"><?= $designStatus === 'issue_found' ? '⚠ Action required: ' : '' ?><?= $h($item['design_admin_note']) ?></small><?php endif; ?>
                        <?php if (!empty($item['design_customer_note'])): ?><small class="account-design-customer-note">Your message: <?= $h($item['design_customer_note']) ?></small><?php endif; ?>
                      </div>
                      <?php if (!$isQuoteOnly): ?><div class="account-order-file-grid">
                        <div class="account-order-file" data-artwork-box>
                          <b>Your artwork</b>
                          <div data-artwork-preview>
                          <?php if (!empty($item['artwork_file_id'])): ?>
                            <a href="/account/artwork/<?= (int)$item['artwork_file_id'] ?>/view" target="_blank" rel="noopener" title="<?= $h($artworkName ?: 'Artwork File') ?>">
                              <span><?php if ($artworkIsImage && $artworkPath !== ''): ?><img src="<?= $h($artworkPath) ?>" alt="" loading="lazy"><?php else: ?><i class="fa-regular fa-file-lines"></i><?php endif; ?></span>
                              <em><?= $h($accountShortFileName($artworkName, 'Artwork File')) ?></em>
                            </a>
                            <span class="account-artwork-file-actions">
                              <a href="/account/artwork/<?= (int)$item['artwork_file_id'] ?>/view" target="_blank" rel="noopener">View</a>
                              <a href="/account/artwork/<?= (int)$item['artwork_file_id'] ?>/download" target="_blank" rel="noopener">Download</a>
                            </span>
                          <?php else: ?>
                            <small>No artwork uploaded</small>
                          <?php endif; ?>
                          </div>
                          <?php if ($approvalId > 0): ?>
                            <div class="account-artwork-reupload-form <?= $canUploadArtwork ? 'is-enabled' : '' ?>">
                              <input type="file" name="artwork" accept=".pdf,.ai,.eps,.png,.jpg,.jpeg,.psd,.cdr,.svg,.tif,.tiff,.zip" onchange="uploadAccountArtworkRevision(this, <?= $approvalId ?>)" <?= $canUploadArtwork ? '' : 'disabled' ?>>
                              <button type="button" onclick="chooseAccountArtworkRevision(this)" <?= $canUploadArtwork ? '' : 'disabled' ?>><?= $canInitialArtworkUpload ? 'Upload Design' : 'Reupload Design' ?></button>
                              <small><?= $canInitialArtworkUpload ? 'You selected upload later. Choose your design file here when ready.' : ($designStatus === 'issue_found' ? 'One click: choose file and upload starts automatically.' : 'Upload is available when a design file is required.') ?></small>
                            </div>
                          <?php endif; ?>
                        </div>
                        <div class="account-order-file">
                          <b>Corrected file</b>
                          <?php if (!empty($item['design_proof_file_id'])): ?>
                            <a href="/account/artwork/<?= (int)$item['design_proof_file_id'] ?>/view" target="_blank" rel="noopener" title="<?= $h($proofName ?: 'Proof File') ?>">
                              <span><?php if ($proofIsImage && $proofPath !== ''): ?><img src="<?= $h($proofPath) ?>" alt="" loading="lazy"><?php else: ?><i class="fa-regular fa-file-lines"></i><?php endif; ?></span>
                              <em><?= $h($accountShortFileName($proofName, 'Proof File')) ?></em>
                            </a>
                            <span class="account-artwork-file-actions">
                              <a href="/account/artwork/<?= (int)$item['design_proof_file_id'] ?>/view" target="_blank" rel="noopener">View</a>
                              <a href="/account/artwork/<?= (int)$item['design_proof_file_id'] ?>/download" target="_blank" rel="noopener">Download</a>
                            </span>
                          <?php else: ?>
                            <small>No proof uploaded yet</small>
                          <?php endif; ?>
                        </div>
                      </div>
                      <div class="account-order-item-actions">
                        <?php if ($canReviewProof): ?>
                          <form class="account-design-revision-form account-design-revision-form--inline" data-design-revision-form="<?= $approvalId ?>" onsubmit="sendDesignRevision(event, <?= $approvalId ?>)">
                            <textarea name="message" rows="2" minlength="5" required placeholder="Request revision message..."></textarea>
                            <button type="submit">Submit Revision</button>
                          </form>
                          <div class="account-design-review-actions">
                            <button type="button" class="account-design-approve-btn" onclick="approveAccountDesign(<?= $approvalId ?>, this)">Approve Design</button>
                          </div>
                        <?php else: ?>
                          <span data-design-action-status><?= $h($designApprovalLabels[$designStatus] ?? ucfirst(str_replace('_', ' ', $designStatus))) ?></span>
                        <?php endif; ?>
                      </div><?php endif; ?>
                      <div class="account-order-live-msg" data-design-live-msg hidden></div>
                    </article>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            <div class="account-order-bottom-bar">
            <div class="account-order-info-card">
              <strong>Payment</strong>
              <p><?= $h(ucwords(str_replace('_', ' ', (string)($order['payment_status'] ?? 'pending')))) ?><?php if (!empty($order['payment_method'])): ?> · <?= $h(ucfirst((string)$order['payment_method'])) ?><?php endif; ?></p>
              <?php if (!empty($order['payment_id'])): ?><small>Payment ID: <?= $h($order['payment_id']) ?></small><?php endif; ?>
            </div>
            <div class="account-order-actions-list" aria-label="Order actions">
              <strong>Actions</strong>
              <?php if ($canPayCustom): ?><a href="/custom-checkout/<?= rawurlencode((string)$order['quote_token']) ?>"><i class="fa-solid fa-lock" aria-hidden="true"></i> Pay Custom Order</a><?php endif; ?>
              <?php if (!$isQuoteOnly): ?><button type="button" onclick="openAccountOrder(this)"><i class="fa-solid fa-truck-fast" aria-hidden="true"></i> Track Order</button><?php endif; ?>
              <?php if ($isPaid && $orderPublicId !== '' && !empty($order['invoice_file_path'])): ?>
                <a href="/invoice/<?= rawurlencode($orderPublicId) ?>" target="_blank" rel="noopener"><i class="fa-regular fa-file-lines" aria-hidden="true"></i> Download Invoice</a>
              <?php elseif ($isPaid): ?>
                <span class="account-order-action-disabled"><i class="fa-regular fa-file-lines" aria-hidden="true"></i> Invoice will be available soon</span>
              <?php elseif (!$isQuoteOnly): ?>
                <span class="account-order-action-disabled"><i class="fa-regular fa-file-lines" aria-hidden="true"></i> Invoice after payment</span>
              <?php endif; ?>
            </div>
            </div>
            <?php if (!$isQuoteOnly): ?><div class="account-order-tracking" aria-label="Tracking detail">
              <div class="account-track-head">
                <strong>Tracking Detail</strong>
                <span><?= $h($statusLabels[$status] ?? ucfirst($status)) ?></span>
              </div>
              <?php if ($isCancelled): ?>
                <div class="account-track-alert is-cancelled"><i class="fa-solid fa-circle-xmark" aria-hidden="true"></i> This order has been cancelled.</div>
              <?php elseif ($isWhatsappPending): ?>
                <div class="account-track-alert is-pending"><i class="fa-brands fa-whatsapp" aria-hidden="true"></i> WhatsApp confirmation is pending. Our team will update this order after confirmation.</div>
              <?php else: ?>
                <div class="account-track-steps">
                  <?php foreach ($trackSteps as $stepIndex => $step):
                    $stepClass = $stepIndex < $trackIndex ? 'is-done' : ($stepIndex === $trackIndex ? 'is-active' : 'is-pending');
                  ?>
                    <div class="account-track-step <?= $stepClass ?>">
                      <span><?= $stepIndex < $trackIndex ? '✓' : ($stepIndex + 1) ?></span>
                      <strong><?= $h($statusLabels[$step] ?? ucfirst($step)) ?></strong>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </div><?php endif; ?>
          </div>
        </details>
      <?php endforeach; ?>
    </div>
    <?php
};
?>
<main class="account-page" data-design-target="account.page">
  <?php include INCLUDE_PATH . '/partials/page-hero.php'; ?>

  <section class="account-dashboard container" aria-label="Account dashboard">
    <aside class="account-sidebar" aria-label="My account menu">
      <button class="account-nav-item is-active" type="button" data-account-tab="dashboard"><i class="fa-solid fa-shapes"></i><span>Dashboard</span></button>
      <button class="account-nav-item" type="button" data-account-tab="orders"><i class="fa-regular fa-clipboard"></i><span>My Orders</span></button>
      <button class="account-nav-item" type="button" data-account-tab="custom-orders"><i class="fa-solid fa-wand-magic-sparkles"></i><span>My Custom Orders</span></button>
      <button class="account-nav-item" type="button" data-account-tab="wishlist"><i class="fa-regular fa-heart"></i><span>My Wishlist</span></button>
      <button class="account-nav-item" type="button" data-account-tab="designs"><i class="fa-regular fa-pen-to-square"></i><span>My Designs</span></button>
      <button class="account-nav-item" type="button" data-account-tab="reviews"><i class="fa-regular fa-star"></i><span>My Reviews</span></button>
      <button class="account-nav-item" type="button" data-account-tab="addresses"><i class="fa-solid fa-location-dot"></i><span>Saved Addresses</span></button>
      <button class="account-nav-item" type="button" data-account-tab="details"><i class="fa-regular fa-user"></i><span>Account Details</span></button>
      <button class="account-nav-item" type="button" data-account-tab="security"><i class="fa-solid fa-lock"></i><span>Change Password</span></button>
      <a class="account-nav-item" href="/logout"><i class="fa-solid fa-arrow-right-from-bracket"></i><span>Logout</span></a>

    </aside>

    <div class="account-main">
      <section class="account-tab-panel is-active" data-account-panel="dashboard" aria-label="Account dashboard overview">
        <div class="account-stats-grid">
          <article class="account-stat-card stat-purple">
            <span class="account-stat-icon"><i class="fa-solid fa-bag-shopping"></i></span>
            <div><small>Total Orders</small><strong><?= number_format($totalOrders) ?></strong><button type="button" data-account-tab="orders">View Orders <i class="fa-solid fa-arrow-right"></i></button></div>
          </article>
          <article class="account-stat-card stat-orange">
            <span class="account-stat-icon"><i class="fa-regular fa-rectangle-list"></i></span>
            <div><small>Orders in Progress</small><strong><?= str_pad((string)$progressOrders, 2, '0', STR_PAD_LEFT) ?></strong><button type="button" data-account-tab="orders">Track Now <i class="fa-solid fa-arrow-right"></i></button></div>
          </article>
          <article class="account-stat-card stat-green">
            <span class="account-stat-icon"><i class="fa-solid fa-bag-shopping"></i></span>
            <div><small>Completed Orders</small><strong><?= str_pad((string)$completedOrders, 2, '0', STR_PAD_LEFT) ?></strong><button type="button" data-account-tab="orders">View History <i class="fa-solid fa-arrow-right"></i></button></div>
          </article>
          <article class="account-stat-card stat-wallet">
            <span class="account-stat-icon"><i class="fa-solid fa-location-dot"></i></span>
            <div><small>Saved Addresses</small><strong><?= str_pad((string)$savedAddressCount, 2, '0', STR_PAD_LEFT) ?></strong><button type="button" data-account-tab="addresses">Manage Address <i class="fa-solid fa-arrow-right"></i></button></div>
          </article>
        </div>

        <section class="account-card account-profile-card" aria-label="Profile summary">
          <div class="account-avatar-wrap">
            <div class="account-avatar" aria-hidden="true"><?= $h($initials) ?></div>
            <button type="button" data-account-tab="details" aria-label="Edit profile photo"><i class="fa-solid fa-camera"></i></button>
          </div>
          <div class="account-profile-copy">
            <h2><?= $h($name) ?></h2>
            <?php if ($company !== ''): ?><p><i class="fa-regular fa-building"></i><?= $h($company) ?></p><?php endif; ?>
            <p><i class="fa-regular fa-envelope"></i><?= $email !== '' ? $h($email) : 'Add email address' ?></p>
            <p><i class="fa-solid fa-phone"></i><?= $phone !== '' ? $h($phone) : 'Add phone number' ?></p>
            <p><i class="fa-solid fa-location-dot"></i><?= $h($location) ?></p>
          </div>
          <button class="account-edit-btn" type="button" data-account-tab="details">Edit Profile</button>
        </section>

        <section class="account-card account-orders-card" aria-labelledby="recentOrdersTitle">
          <div class="account-section-head">
            <h2 id="recentOrdersTitle">Recent Orders</h2>
            <button type="button" data-account-tab="orders">View All Orders <i class="fa-solid fa-arrow-right"></i></button>
          </div>
          <?php $renderOrders($recentOrders, true); ?>
        </section>

      </section>

      <section class="account-tab-panel" data-account-panel="orders" aria-labelledby="ordersPanelTitle">
        <section class="account-card account-orders-card">
          <div class="account-section-head">
            <div><h2 id="ordersPanelTitle">My Orders</h2><p>All your print orders and payment/status information in one place.</p></div>
            <a href="/categories">Place New Order <i class="fa-solid fa-arrow-right"></i></a>
          </div>
          <?php $renderOrders($orders, false); ?>
        </section>
      </section>

      <section class="account-tab-panel" data-account-panel="custom-orders"><section class="account-card account-orders-card"><div class="account-section-head"><div><h2>My Custom Orders</h2><p>Custom quotes, payments, production updates and actions in one place.</p></div></div><?php $renderOrders($customOrderDisplay, false, true); ?></section></section>
      <section class="account-tab-panel" data-account-panel="wishlist" aria-labelledby="wishlistPanelTitle">
        <section class="account-card account-wishlist-card">
          <div class="account-section-head">
            <div><h2 id="wishlistPanelTitle">My Wishlist</h2><p>Products you saved for quick access later.</p></div>
            <a href="/categories">Browse More <i class="fa-solid fa-arrow-right"></i></a>
          </div>
          <?php if (empty($wishlistItems)): ?>
            <div class="account-empty-state compact"><i class="fa-regular fa-heart"></i><strong>Your wishlist is empty</strong><span>Tap the heart on any product to save it here.</span><a href="/categories" class="btn btn-blue btn-sm">Browse Products</a></div>
          <?php else: ?>
            <div class="account-wishlist-grid" id="accountWishlistGrid">
              <?php foreach ($wishlistItems as $item):
                $wishProductId = (int)($item['id'] ?? 0);
                $wishName = trim((string)($item['name'] ?? 'Product'));
                $wishSlug = trim((string)($item['slug'] ?? ''));
                $wishImg = trim((string)($item['primary_image'] ?? ($item['image_path'] ?? '')));
                $wishCategory = trim((string)($item['category_name'] ?? 'Print Product'));
                $wishPrice = (float)($item['min_price'] ?? 0);
              ?>
                <article class="account-wishlist-item" data-wishlist-product="<?= $wishProductId ?>">
                  <a class="account-wishlist-img" href="/product/<?= $h($wishSlug) ?>">
                    <?php if ($wishImg !== ''): ?>
                      <img src="<?= $h($wishImg) ?>" alt="<?= $h($wishName) ?>" loading="lazy">
                    <?php else: ?>
                      <span aria-hidden="true">📦</span>
                    <?php endif; ?>
                  </a>
                  <div class="account-wishlist-copy">
                    <small><?= $h($wishCategory) ?></small>
                    <strong><?= $h($wishName) ?></strong>
                    <em><?= $wishPrice > 0 ? ('Starting from ₹' . number_format($wishPrice)) : 'Price on request' ?></em>
                    <div class="account-wishlist-actions">
                      <a href="/product/<?= $h($wishSlug) ?>" class="btn btn-blue btn-sm">View Product</a>
                      <button type="button" class="btn btn-outline btn-sm" onclick="removeWishlistItem(<?= $wishProductId ?>, this)"><i class="fa-regular fa-trash-can"></i> Remove</button>
                    </div>
                  </div>
                </article>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </section>
      </section>

      <section class="account-tab-panel" data-account-panel="designs" aria-labelledby="designsPanelTitle">
        <section class="account-card account-designs-card">
          <div class="account-section-head">
            <div><h2 id="designsPanelTitle">My Designs</h2><p>Designs are based on your recent orders and uploaded artwork where available.</p></div>
            <a href="/categories">Upload New Design <i class="fa-solid fa-arrow-right"></i></a>
          </div>
          <?php if (empty($myDesigns)): ?>
            <div class="account-empty-state">
              <i class="fa-regular fa-folder-open"></i>
              <strong>No uploaded designs yet</strong>
              <span>Artwork uploaded during checkout will appear here after the order is placed.</span>
              <a href="/categories" class="btn btn-blue btn-sm">Upload With an Order</a>
            </div>
          <?php else: ?>
            <div class="account-design-grid account-uploaded-design-grid">
              <?php foreach ($myDesigns as $design):
                  $designId = (int)($design['id'] ?? 0);
                  $productName = trim((string)($design['product_name'] ?? 'Print Artwork')) ?: 'Print Artwork';
                  $fileName = trim((string)($design['original_name'] ?? $design['filename'] ?? 'Artwork file')) ?: 'Artwork file';
                  $orderId = trim((string)($design['order_id'] ?? ''));
                  $productSlug = trim((string)($design['product_slug'] ?? ''));
                  $createdDate = $design['created_at'] ?? $design['order_created_at'] ?? date('Y-m-d');
                  $filePath = \Designs\UserDesigns::publicFilePath($design);
                  $isImage = \Designs\UserDesigns::isImage($design);
                  $fileSize = \Designs\UserDesigns::formattedSize($design);
                  $designChoice = trim((string)($design['design_choice'] ?? 'upload'));
              ?>
                <article class="account-design-card account-design-file-card">
                  <div class="account-design-preview">
                    <?php if ($isImage && $filePath !== ''): ?>
                      <img src="<?= $h($filePath) ?>" alt="<?= $h($fileName) ?>" loading="lazy">
                    <?php else: ?>
                      <div class="account-design-file-icon"><i class="fa-regular fa-file-lines"></i></div>
                    <?php endif; ?>
                  </div>
                  <div class="account-design-copy">
                    <strong><?= $h($productName) ?></strong>
                    <span class="account-design-filename"><?= $h($fileName) ?></span>
                    <div class="account-design-meta">
                      <?php if ($orderId !== ''): ?><span>Order <?= $h($orderId) ?></span><?php endif; ?>
                      <span><?= $h(ucfirst($designChoice)) ?> artwork</span>
                      <span><?= $h($fileSize) ?></span>
                      <span>Uploaded <?= date('d M, Y', strtotime((string)$createdDate)) ?></span>
                    </div>
                    <div class="account-design-actions">
                      <?php if ($designId > 0): ?>
                        <a href="/account/artwork/<?= $designId ?>/view" class="btn btn-outline btn-sm" target="_blank" rel="noopener"><i class="fa-regular fa-eye"></i> View</a><a href="/account/artwork/<?= $designId ?>/download" class="btn btn-blue btn-sm"><i class="fa-solid fa-download"></i> Download</a>
                      <?php endif; ?>
                      <?php if ($productSlug !== ''): ?>
                        <a href="/product/<?= $h($productSlug) ?>" class="btn btn-outline btn-sm">Reorder</a>
                      <?php endif; ?>
                    </div>
                  </div>
                </article>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </section>
      </section>


      <section class="account-tab-panel" data-account-panel="reviews" aria-labelledby="reviewsPanelTitle">
        <section class="account-card account-reviews-card">
          <div class="account-section-head">
            <div><h2 id="reviewsPanelTitle">My Reviews</h2><p>Review delivered products and track approval status for your submitted feedback.</p></div>
            <a href="/categories">Explore More Products <i class="fa-solid fa-arrow-right"></i></a>
          </div>

          <div class="account-review-block">
            <h3><i class="fa-regular fa-star"></i> Products ready for review</h3>
            <?php if (empty($reviewableItems)): ?>
              <div class="account-empty-state compact"><i class="fa-regular fa-face-smile"></i><strong>No pending reviews</strong><span>Delivered products that are ready for review will appear here.</span></div>
            <?php else: ?>
              <div class="account-reviewable-list">
                <?php foreach ($reviewableItems as $item):
                  $img = trim((string)($item['product_image'] ?? ''));
                  $productUrl = !empty($item['product_slug']) ? '/product/' . rawurlencode((string)$item['product_slug']) : '#';
                ?>
                <article class="account-reviewable-card" data-review-product="<?= (int)($item['product_id'] ?? 0) ?>">
                  <div class="account-review-product">
                    <div class="account-review-thumb">
                      <?php if ($img !== ''): ?><img src="<?= $h($img) ?>" alt="<?= $h($item['product_name'] ?? 'Product') ?>" loading="lazy"><?php else: ?><i class="fa-solid fa-box-open"></i><?php endif; ?>
                    </div>
                    <div>
                      <strong><?= $h($item['product_name'] ?? 'Product') ?></strong>
                      <span>Order #<?= $h($item['public_order_id'] ?? '') ?><?= !empty($item['order_created_at']) ? ' · ' . date('d M, Y', strtotime((string)$item['order_created_at'])) : '' ?></span>
                      <?php if ($productUrl !== '#'): ?><a href="<?= $h($productUrl) ?>">View product</a><?php endif; ?>
                    </div>
                  </div>
                  <form class="account-review-form" onsubmit="submitAccountReview(event, this)">
                    <input type="hidden" name="product_id" value="<?= (int)($item['product_id'] ?? 0) ?>">
                    <input type="hidden" name="order_item_id" value="<?= (int)($item['order_item_id'] ?? 0) ?>">
                    <label>Rating</label>
                    <select name="rating" class="fi" required>
                      <option value="5">★★★★★ Excellent</option>
                      <option value="4">★★★★☆ Good</option>
                      <option value="3">★★★☆☆ Average</option>
                      <option value="2">★★☆☆☆ Needs improvement</option>
                      <option value="1">★☆☆☆☆ Poor</option>
                    </select>
                    <label>Comment</label>
                    <textarea name="comment" class="fi" rows="3" minlength="10" maxlength="1000" placeholder="Share print quality, delivery and support experience…" required></textarea>
                    <div class="account-review-msg" aria-live="polite"></div>
                    <button class="btn btn-blue btn-sm" type="submit"><i class="fa-regular fa-paper-plane"></i> Submit Review</button>
                  </form>
                </article>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>

          <div class="account-review-block">
            <h3><i class="fa-solid fa-list-check"></i> Submitted reviews</h3>
            <?php if (empty($myReviews)): ?>
              <div class="account-empty-state compact"><i class="fa-regular fa-comment-dots"></i><strong>No reviews submitted yet</strong><span>Your submitted reviews and approval status will show here.</span></div>
            <?php else: ?>
              <div class="account-submitted-reviews">
                <?php foreach ($myReviews as $review):
                  $status = (string)($review['status'] ?? 'pending');
                  $productUrl = !empty($review['product_slug']) ? '/product/' . rawurlencode((string)$review['product_slug']) : '#';
                ?>
                <article class="account-submitted-review">
                  <div>
                    <strong><?= $h($review['product_name'] ?? 'Product') ?></strong>
                    <span class="account-review-stars" aria-label="<?= (int)($review['rating'] ?? 0) ?> out of 5 stars"><?= $h($review['stars'] ?? '') ?></span>
                    <p><?= $h($review['comment'] ?? '') ?></p>
                    <?php if ($productUrl !== '#'): ?><a href="<?= $h($productUrl) ?>">View product</a><?php endif; ?>
                  </div>
                  <span class="account-review-status is-<?= $h($status) ?>"><?= $h(ucfirst($status)) ?></span>
                </article>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        </section>
      </section>

      <section class="account-tab-panel" data-account-panel="addresses" aria-labelledby="addressesPanelTitle">
        <section class="account-card account-form-card">
          <div class="account-section-head"><div><h2 id="addressesPanelTitle">Saved Addresses</h2><p>Manage default delivery and billing addresses used during checkout.</p></div></div>
          <div class="account-form-block">
            <h3><i class="fa-solid fa-location-dot"></i> Default Delivery Address</h3>
            <div class="fg"><label>Address Line 1</label><input id="ps-add1" class="fi" value="<?= $h($shipping['address_line1'] ?? '') ?>"></div>
            <div class="fg"><label>Address Line 2</label><input id="ps-add2" class="fi" value="<?= $h($shipping['address_line2'] ?? '') ?>"></div>
            <div class="f2">
              <div class="fg"><label>City</label><input id="ps-city" class="fi" value="<?= $h($shipping['city'] ?? '') ?>"></div>
              <div class="fg"><label>State</label><input id="ps-state" class="fi" value="<?= $h($shipping['state'] ?? '') ?>"></div>
            </div>
            <div class="fg" style="margin-bottom:0"><label>Pincode</label><input id="ps-pin" class="fi" value="<?= $h($shipping['pincode'] ?? '') ?>"></div>
          </div>
          <div class="account-form-block">
            <h3><i class="fa-regular fa-file-lines"></i> Default Billing Details (GST Invoice)</h3>
            <?php if (!empty($profile['migration_required'])): ?>
            <div class="account-warning">Billing fields are not available yet. Please run the SQL migration shared in the implementation notes.</div>
            <?php endif; ?>
            <label class="account-same-address"><input type="checkbox" id="pb-same-shipping" onchange="copyShippingToBilling(this.checked)"> <span>Billing address same as delivery address</span></label>
            <div class="fg"><label>Legal Business Name</label><input id="pb-legal" class="fi" value="<?= $h($billing['legal_name'] ?? '') ?>" placeholder="ABC Pvt Ltd"></div>
            <div class="fg"><label>GSTIN</label><input id="pb-gst" class="fi" value="<?= $h($billing['gst_no'] ?? '') ?>" placeholder="24ABCDE1234F1Z5" style="text-transform:uppercase" oninput="this.value=this.value.toUpperCase()"></div>
            <div class="fg"><label>Billing Address Line 1</label><input id="pb-add1" class="fi" value="<?= $h($billing['address_line1'] ?? '') ?>"></div>
            <div class="fg"><label>Billing Address Line 2</label><input id="pb-add2" class="fi" value="<?= $h($billing['address_line2'] ?? '') ?>"></div>
            <div class="f2">
              <div class="fg"><label>City</label><input id="pb-city" class="fi" value="<?= $h($billing['city'] ?? '') ?>"></div>
              <div class="fg"><label>State</label><input id="pb-state" class="fi" value="<?= $h($billing['state'] ?? '') ?>"></div>
            </div>
            <div class="fg" style="margin-bottom:0"><label>Pincode</label><input id="pb-pin" class="fi" value="<?= $h($billing['pincode'] ?? '') ?>"></div>
          </div>
          <div class="account-form-actions"><button class="btn btn-blue" onclick="saveProfile()"><i class="fa-solid fa-floppy-disk"></i> Save Addresses</button></div>
        </section>
      </section>



      <section class="account-tab-panel" data-account-panel="details" aria-labelledby="detailsPanelTitle">
        <section class="account-card account-form-card">
          <div class="account-section-head"><div><h2 id="detailsPanelTitle">Account Details</h2><p>Update your name, email, phone and company details.</p></div></div>
          <div class="account-form-block">
            <h3><i class="fa-regular fa-user"></i> Basic Details</h3>
            <div class="fg"><label>Full Name *</label><input id="p-name" class="fi" value="<?= $h($name) ?>"></div>
            <div class="f2">
              <div class="fg"><label>Email *</label><input id="p-email" type="email" class="fi" value="<?= $h($email) ?>"></div>
              <div class="fg"><label>Phone *</label><input id="p-phone" type="tel" class="fi" value="<?= $h($phone) ?>"></div>
            </div>
            <div class="fg" style="margin-bottom:0"><label>Company (optional)</label><input id="p-company" class="fi" value="<?= $h($company) ?>"></div>
          </div>
          <div id="profErr" class="account-alert is-error" style="display:none"></div>
          <div id="profOk" class="account-alert is-ok" style="display:none"></div>
          <div class="account-form-actions">
            <button class="btn btn-blue" onclick="saveProfile()"><i class="fa-solid fa-floppy-disk"></i> Save Profile</button>
            <button type="button" class="btn btn-outline" data-account-tab="security"><i class="fa-solid fa-lock"></i> Password Settings</button>
          </div>
        </section>
      </section>

      <section class="account-tab-panel" data-account-panel="security" aria-labelledby="securityPanelTitle">
        <section class="account-card account-form-card">
          <div class="account-section-head"><div><h2 id="securityPanelTitle">Change Password</h2><p>Set a new password without leaving My Account.</p></div></div>
          <div class="account-form-block">
            <h3><i class="fa-solid fa-lock"></i> Security</h3>
            <p class="account-muted">Existing passwords are stored securely in hashed form and cannot be shown in plain text.</p>
            <div class="fg"><label>Current Password *</label><div class="account-pass-wrap"><input id="pw-current" type="password" class="fi" placeholder="Enter current password"><button type="button" onclick="togglePassField('pw-current', this)">👁️</button></div></div>
            <div class="fg"><label>New Password *</label><div class="account-pass-wrap"><input id="pw-new" type="password" class="fi" placeholder="Minimum 6 characters"><button type="button" onclick="togglePassField('pw-new', this)">👁️</button></div></div>
            <div class="fg"><label>Confirm New Password *</label><div class="account-pass-wrap"><input id="pw-confirm" type="password" class="fi" placeholder="Retype new password"><button type="button" onclick="togglePassField('pw-confirm', this)">👁️</button></div></div>
            <div id="pwErr" class="account-alert is-error" style="display:none"></div>
            <div id="pwOk" class="account-alert is-ok" style="display:none"></div>
            <div class="account-form-actions"><button class="btn btn-blue" type="button" onclick="changePassword()">Update Password</button></div>
          </div>
        </section>
      </section>


    </div>
  </section>

  <section class="why-print-section account-why-section" aria-labelledby="accountWhyTitle" data-reveal>
    <div class="why-print-container">
      <h2 class="why-print-heading" id="accountWhyTitle">Why Choose <span>RCS PRINT?</span></h2>
      <div class="why-print-panel" aria-label="Why choose RCS Print">
        <article class="why-print-item">
          <div class="why-print-icon why-print-green"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i></div>
          <div class="why-print-copy"><h3>Premium Quality</h3><p>Best quality materials and printing.</p></div>
        </article>
        <article class="why-print-item">
          <div class="why-print-icon why-print-orange"><i class="fa-regular fa-thumbs-up" aria-hidden="true"></i></div>
          <div class="why-print-copy"><h3>100% Satisfaction</h3><p>Your happiness matters.</p></div>
        </article>
        <article class="why-print-item">
          <div class="why-print-icon why-print-purple"><i class="fa-solid fa-pen-ruler" aria-hidden="true"></i></div>
          <div class="why-print-copy"><h3>Free Design Support</h3><p>Professional design support at no extra cost.</p></div>
        </article>
        <article class="why-print-item">
          <div class="why-print-icon why-print-purple"><i class="fa-solid fa-tags" aria-hidden="true"></i></div>
          <div class="why-print-copy"><h3>Affordable Pricing</h3><p>Low price with the best value.</p></div>
        </article>
        <article class="why-print-item">
          <div class="why-print-icon why-print-orange"><i class="fa-solid fa-cube" aria-hidden="true"></i></div>
          <div class="why-print-copy"><h3>Bulk Order Specialist</h3><p>Special prices for bulk requirements.</p></div>
        </article>
      </div>
    </div>
  </section>

  <section class="quick-help-section account-quick-help-section" aria-label="Quick help and bulk order actions" data-reveal>
    <div class="quick-help-container">
      <div class="quick-help-bar">
        <a class="quick-help-item quick-help-call" href="tel:<?= $h($accountBizPhoneHref) ?>">
          <span class="quick-help-icon"><i class="fa-solid fa-phone-volume" aria-hidden="true"></i></span>
          <span class="quick-help-copy"><span>Need Help? Call Us</span><strong><?= $accountBizPhone ?></strong></span>
        </a>
        <button class="quick-help-item quick-help-whatsapp" type="button" onclick="window.open('https://wa.me/<?= $h($accountBizWa) ?>','_blank')">
          <span class="quick-help-icon"><i class="fa-brands fa-whatsapp" aria-hidden="true"></i></span>
          <span class="quick-help-copy"><strong>Chat with us on WhatsApp</strong><span>We are here to help!</span></span>
        </button>
        <a class="quick-help-item quick-help-download" href="/categories" aria-label="Download our brochure for all products">
          <span class="quick-help-icon"><i class="fa-solid fa-download" aria-hidden="true"></i></span>
          <span class="quick-help-copy"><strong>Download Our Brochure</strong><span>For All Products</span></span>
        </a>
      </div>
    </div>
  </section>
</main>

<script>
const ACCOUNT_TABS = ['dashboard','orders','custom-orders','wishlist','designs','reviews','addresses','details','security'];

function setAccountTab(tab, pushHash = true) {
  const safeTab = ACCOUNT_TABS.includes(tab) ? tab : 'dashboard';
  document.querySelectorAll('[data-account-tab]').forEach(el => {
    const active = el.dataset.accountTab === safeTab;
    el.classList.toggle('is-active', active && el.classList.contains('account-nav-item'));
    if (el.classList.contains('account-nav-item')) el.setAttribute('aria-current', active ? 'page' : 'false');
  });
  document.querySelectorAll('[data-account-panel]').forEach(panel => {
    const active = panel.dataset.accountPanel === safeTab;
    panel.classList.toggle('is-active', active);
    panel.toggleAttribute('hidden', !active);
  });
  if (pushHash) history.replaceState(null, '', safeTab === 'dashboard' ? '/profile' : `/profile#${safeTab}`);
}

document.querySelectorAll('[data-account-tab]').forEach(el => {
  el.addEventListener('click', event => {
    const tab = el.dataset.accountTab;
    if (!tab) return;
    event.preventDefault();
    setAccountTab(tab);
  });
});

document.querySelectorAll('.account-order-detail.has-admin-update').forEach(card => {
  card.addEventListener('toggle', async () => {
    if (!card.open || card.dataset.updateAcknowledged === '1') return;
    if (!['order_status_updated','admin_design_approved','invoice_uploaded','shipping_updated'].includes(card.dataset.adminUpdateType || '')) return;
    const id = Number(card.dataset.orderDbId || 0);
    if (!id) return;
    card.dataset.updateAcknowledged = '1';
    const resp = await fetch(`/api/orders/${id}/admin-update/ack`, {method:'POST', headers:{'X-CSRF-TOKEN':'<?= $h($csrf ?? '') ?>'}, credentials:'same-origin'});
    if (resp.ok) card.classList.remove('has-admin-update');
  });
});

async function removeWishlistItem(productId, btn) {
  productId = parseInt(productId || '0', 10);
  if (!productId) return;
  btn.disabled = true;
  try {
    const resp = await fetch(`/api/wishlist/${productId}`, {
      method: 'DELETE',
      headers: {'X-CSRF-TOKEN':'<?= $h($csrf ?? '') ?>'},
      credentials: 'same-origin'
    });
    const data = await resp.json();
    if (!data.ok) {
      alert(data.msg || 'Could not remove wishlist item');
      btn.disabled = false;
      return;
    }
    const card = btn.closest('[data-wishlist-product]');
    card?.remove();
    const grid = document.getElementById('accountWishlistGrid');
    if (grid && !grid.querySelector('[data-wishlist-product]')) {
      grid.outerHTML = '<div class="account-empty-state compact"><i class="fa-regular fa-heart"></i><strong>Your wishlist is empty</strong><span>Tap the heart on any product to save it here.</span><a href="/categories" class="btn btn-blue btn-sm">Browse Products</a></div>';
    }
  } catch (err) {
    console.error(err);
    alert('Could not remove wishlist item');
    btn.disabled = false;
  }
}

const ACCOUNT_OPEN_ORDER_KEY = 'accountOpenOrder';
const ACCOUNT_OPEN_ORDERS_KEY = 'accountOpenOrders';
const ACCOUNT_PENDING_OPEN_ORDER_KEY = 'accountPendingOpenOrder';
const ACCOUNT_OPEN_TAB_KEY = 'accountOpenTab';
let restoringAccountOrder = false;

function getAccountHashValue() {
  return decodeURIComponent((location.hash || '').replace(/^#/, ''));
}

function getOrderKeyFromHash() {
  const hashValue = getAccountHashValue();
  return hashValue.startsWith('orders-') ? hashValue.slice('orders-'.length) : '';
}

function getAccountTabFromHash() {
  const hashValue = getAccountHashValue();
  if (hashValue === 'orders' || hashValue.startsWith('orders-')) return 'orders';
  return hashValue;
}

window.addEventListener('hashchange', () => {
  const hashOrder = getOrderKeyFromHash();
  if (hashOrder) {
    storeAccountOrderOpen(hashOrder, true);
  }
  const tab = getAccountTabFromHash() || (hashOrder ? 'orders' : 'dashboard');
  setAccountTab(tab, false);
  restoreOpenAccountOrders(Boolean(hashOrder));
});

const rememberedOrder = sessionStorage.getItem(ACCOUNT_OPEN_ORDER_KEY) || '';
const linkedOrder = getOrderKeyFromHash();
if (linkedOrder) {
  storeAccountOrderOpen(linkedOrder, true);
}
const initialAccountTab = getAccountTabFromHash() || ((linkedOrder || rememberedOrder) ? (sessionStorage.getItem(ACCOUNT_OPEN_TAB_KEY) || 'orders') : 'dashboard');
setAccountTab(initialAccountTab, false);
restoreOpenAccountOrders(Boolean(linkedOrder));
window.addEventListener('load', () => restoreOpenAccountOrders(Boolean(getOrderKeyFromHash())));
requestAnimationFrame(() => restoreOpenAccountOrders(Boolean(getOrderKeyFromHash())));
window.setTimeout(() => restoreOpenAccountOrders(Boolean(getOrderKeyFromHash())), 250);
document.querySelectorAll('.account-order-detail').forEach(detail => {
  detail.querySelector('summary')?.addEventListener('click', () => {
    detail.dataset.manualToggle = '1';
  });
  detail.addEventListener('toggle', () => {
    if (restoringAccountOrder) return;
    const manualClose = !detail.open && detail.dataset.manualToggle === '1';
    delete detail.dataset.manualToggle;
    saveAccountOrderState(detail, detail.open, manualClose);
  });
});



async function approveAccountDesign(id, btn) {
  id = parseInt(id || '0', 10);
  if (!id || !confirm('Approve this corrected design for printing?')) return;
  rememberOpenAccountOrder(btn);
  if (btn) btn.disabled = true;
  try {
    const resp = await fetch(`/api/design-approvals/${id}/approve`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': APP.csrfToken },
      credentials: 'same-origin',
      body: JSON.stringify({ note: 'Approved by customer.' }),
    });
    const data = await resp.json();
    if (!data.ok) { alert(data.msg || 'Could not approve design.'); if (btn) btn.disabled = false; return; }
    updateDesignItemState(btn, 'approved', data.msg || 'Design approved successfully.');
  } catch (e) {
    alert('Could not approve design right now.');
    if (btn) btn.disabled = false;
  }
}

function toggleDesignRevisionForm(id) {
  const form = document.querySelector(`[data-design-revision-form="${id}"]`);
  if (!form) return;
  form.hidden = !form.hidden;
  if (!form.hidden) form.querySelector('textarea')?.focus();
}

async function sendDesignRevision(event, id) {
  event.preventDefault();
  const form = event.currentTarget;
  const message = form.querySelector('textarea')?.value.trim() || '';
  if (message.length < 5) { alert('Please write a clear revision message.'); return; }
  const btn = form.querySelector('button[type="submit"]');
  rememberOpenAccountOrder(form);
  if (btn) btn.disabled = true;
  try {
    const resp = await fetch(`/api/design-approvals/${id}/revision`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': APP.csrfToken },
      credentials: 'same-origin',
      body: JSON.stringify({ message }),
    });
    const data = await resp.json();
    if (!data.ok) { alert(data.msg || 'Could not send revision request.'); if (btn) btn.disabled = false; return; }
    updateDesignItemState(form, 'revision_requested', data.msg || 'Revision request sent successfully.');
  } catch (e) {
    alert('Could not send revision request right now.');
    if (btn) btn.disabled = false;
  }
}

function chooseAccountArtworkRevision(trigger) {
  const form = trigger?.closest?.('.account-artwork-reupload-form');
  const input = form?.querySelector?.('input[type="file"]');
  if (!input || input.disabled) { alert('Upload is available when a design file is required.'); return; }
  input.click();
}

async function uploadAccountArtworkRevision(input, id) {
  const form = input?.closest('.account-artwork-reupload-form');
  const btn = form?.querySelector('button[type="button"]');
  if (!input || input.disabled) { alert('Upload is available when a design file is required.'); return; }
  if (!input.files.length) { alert('Please choose a design file to reupload.'); return; }
  rememberOpenAccountOrder(input);
  const selectedFile = input.files[0];
  const card = getAccountOrderDetailFromElement(input)?.querySelector(`[data-design-approval-item="${id}"]`) || input.closest('[data-design-approval-item]');
  const tempPreviewUrl = selectedFile && String(selectedFile.type || '').toLowerCase().startsWith('image/') ? URL.createObjectURL(selectedFile) : '';
  setDesignArtworkUploadProgress(input, selectedFile, tempPreviewUrl);
  const fd = new FormData();
  fd.append('artwork', selectedFile);
  const oldText = btn ? btn.textContent : '';
  if (btn) { btn.disabled = true; btn.textContent = 'Uploading…'; }
  try {
    const resp = await fetch(`/api/design-approvals/${id}/artwork`, {
      method: 'POST',
      headers: { 'X-CSRF-TOKEN': APP.csrfToken },
      credentials: 'same-origin',
      body: fd,
    });
    const data = await resp.json();
    if (!data.ok) {
      const errorMsg = data.msg || 'Could not reupload design.';
      showDesignUploadError(input, errorMsg);
      alert(errorMsg);
      if (btn) { btn.disabled = false; btn.textContent = oldText || 'Reupload Design'; }
      if (tempPreviewUrl) URL.revokeObjectURL(tempPreviewUrl);
      input.value = '';
      return;
    }
    if (data.file) {
      updateDesignItemState(input, data.status || 'pending_review', data.msg || 'Design uploaded successfully.', data.file || null);
    } else {
      updateDesignArtworkPreview(card, { name: selectedFile.name, mime: selectedFile.type, path: tempPreviewUrl }, 'uploaded');
      updateDesignItemState(input, data.status || 'pending_review', data.msg || 'Design uploaded successfully.', null);
    }
    if (tempPreviewUrl && data.file) URL.revokeObjectURL(tempPreviewUrl);
    input.value = '';
  } catch (e) {
    const errorMsg = 'Could not reupload design right now.';
    showDesignUploadError(input, errorMsg);
    alert(errorMsg);
    if (btn) { btn.disabled = false; btn.textContent = oldText || 'Reupload Design'; }
    if (tempPreviewUrl) URL.revokeObjectURL(tempPreviewUrl);
    input.value = '';
  }
}

const ACCOUNT_DESIGN_LABELS = {
  pending_review: 'Pending Review',
  issue_found: 'Issue Found',
  proof_uploaded: 'Waiting for Your Approval',
  revision_requested: 'Revision Requested',
  approved: 'Approved',
};

function escapeAccountHtml(value) {
  return String(value || '').replace(/[&<>"']/g, (ch) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
  }[ch]));
}

function shortAccountFileName(name) {
  const value = String(name || 'Artwork File').trim() || 'Artwork File';
  if (value.length <= 24) return value;
  const dot = value.lastIndexOf('.');
  const ext = dot > 0 ? value.slice(dot) : '';
  const base = dot > 0 ? value.slice(0, dot) : value;
  return `${base.slice(0, 16)}…${ext}`;
}

function showDesignLiveMessage(card, message, type = 'success') {
  const box = card?.querySelector?.('[data-design-live-msg]');
  if (!box) return;
  box.hidden = false;
  box.className = `account-order-live-msg is-${type}`;
  box.textContent = message || (type === 'success' ? 'Updated successfully.' : 'Could not update.');
}

function updateDesignArtworkPreview(card, file, state = 'ready') {
  if (!card || !file) return;
  const preview = card.querySelector('[data-artwork-preview]');
  if (!preview) return;
  const name = escapeAccountHtml(shortAccountFileName(file.name || 'Artwork File'));
  const title = escapeAccountHtml(file.name || 'Artwork File');
  const viewRaw = file.view_url || (file.id ? `/account/artwork/${file.id}/view` : '');
  const downloadRaw = file.download_url || (file.id ? `/account/artwork/${file.id}/download` : '');
  const viewUrl = escapeAccountHtml(viewRaw);
  const downloadUrl = escapeAccountHtml(downloadRaw);
  const isImage = String(file.mime || '').toLowerCase().startsWith('image/');
  const imagePath = file.path || file.preview_url || '';
  const thumb = isImage && imagePath
    ? `<img src="${escapeAccountHtml(imagePath)}" alt="" loading="lazy">`
    : '<i class="fa-regular fa-file-lines"></i>';
  const fileMarkup = viewRaw
    ? `<a href="${viewUrl}" target="_blank" rel="noopener" title="${title}"><span>${thumb}</span><em>${name}</em></a>`
    : `<span class="account-artwork-file-preview" title="${title}"><span>${thumb}</span><em>${name}</em></span>`;
  const statusMarkup = state === 'uploading' ? '<small class="account-artwork-upload-state"><i class="fa-solid fa-circle-notch fa-spin" aria-hidden="true"></i> Uploading design…</small>' : '';
  const actionsMarkup = viewRaw && downloadRaw ? `<span class="account-artwork-file-actions"><a href="${viewUrl}" target="_blank" rel="noopener">View</a><a href="${downloadUrl}" target="_blank" rel="noopener">Download</a></span>` : '';
  preview.innerHTML = `${fileMarkup}${statusMarkup}${actionsMarkup}`;
}

function setDesignArtworkUploadProgress(source, file, previewUrl = '') {
  const card = source?.closest?.('[data-design-approval-item]');
  if (!card || !file) return;
  updateDesignArtworkPreview(card, { name: file.name, mime: file.type, path: previewUrl }, 'uploading');
  showDesignLiveMessage(card, 'Uploading design… please wait. This can take a moment for large files.', 'info');
}

function showDesignUploadError(source, message) {
  const card = source?.closest?.('[data-design-approval-item]');
  if (!card) return;
  showDesignLiveMessage(card, message || 'Upload failed. Please try again.', 'error');
}

function updateDesignItemState(source, status, message, file = null) {
  const card = source?.closest?.('[data-design-approval-item]');
  if (!card) return;
  const normalized = status || 'pending_review';
  card.dataset.designStatus = normalized;
  card.classList.toggle('account-design-issue', ['issue_found', 'revision_requested'].includes(normalized));
  const label = ACCOUNT_DESIGN_LABELS[normalized] || normalized.replace(/_/g, ' ');
  card.querySelectorAll('[data-design-status-label], [data-design-action-status]').forEach((el) => { el.textContent = label; });
  if (file) updateDesignArtworkPreview(card, file);

  const uploadForm = card.querySelector('.account-artwork-reupload-form');
  if (uploadForm) {
    uploadForm.classList.remove('is-enabled');
    uploadForm.querySelectorAll('input, button').forEach((el) => { el.disabled = true; });
    const hint = uploadForm.querySelector('small');
    if (hint) hint.textContent = 'Design uploaded successfully. Our team will review it.';
  }
  if (normalized === 'approved' || normalized === 'revision_requested') {
    card.querySelectorAll('.account-design-revision-form, .account-design-review-actions').forEach((el) => { el.hidden = true; });
    if (!card.querySelector('[data-design-action-status]')) {
      const actions = card.querySelector('.account-order-item-actions');
      if (actions) actions.innerHTML = `<span data-design-action-status>${escapeAccountHtml(label)}</span>`;
    }
  }
  showDesignLiveMessage(card, message || 'Updated successfully.', 'success');
  rememberOpenAccountOrder(card);
}

function saveAccountOrderState(detail, open, manualClose = false) {
  const key = detail?.dataset?.orderDetail || '';
  if (!key) return;
  if (open) {
    storeAccountOrderOpen(key);
    return;
  }
  const pendingKey = sessionStorage.getItem(ACCOUNT_PENDING_OPEN_ORDER_KEY) || '';
  if (pendingKey === key && !manualClose) {
    storeAccountOrderOpen(key, true);
    setAccountOrderOpen(detail, true, false);
    return;
  }
  removeStoredAccountOrder(key, true);
  if (sessionStorage.getItem(ACCOUNT_OPEN_ORDER_KEY) === key) {
    sessionStorage.removeItem(ACCOUNT_OPEN_ORDER_KEY);
  }
  const detailHash = `#orders-${encodeURIComponent(key)}`;
  if (location.hash === detailHash) history.replaceState(null, '', '/profile#orders');
}
function setAccountOrderOpen(detail, open, persist = true) {
  if (!detail) return;
  restoringAccountOrder = true;
  detail.open = open;
  if (persist) saveAccountOrderState(detail, open);
  window.setTimeout(() => { restoringAccountOrder = false; }, 350);
}
function getAccountOrderDetailFromElement(el) {
  const detail = el?.closest?.('.account-order-detail') || el;
  return detail?.classList?.contains('account-order-detail') ? detail : null;
}
function getStoredAccountOrders() {
  const legacy = sessionStorage.getItem(ACCOUNT_OPEN_ORDER_KEY) || '';
  let list = [];
  try { list = JSON.parse(sessionStorage.getItem(ACCOUNT_OPEN_ORDERS_KEY) || '[]'); }
  catch (e) { list = []; }
  if (!Array.isArray(list)) list = [];
  if (legacy && !list.includes(legacy)) list.push(legacy);
  return list.filter(Boolean);
}
function storeAccountOrderOpen(key, keepPending = false) {
  if (!key) return;
  const ids = new Set(getStoredAccountOrders());
  ids.add(key);
  sessionStorage.setItem(ACCOUNT_OPEN_ORDERS_KEY, JSON.stringify([...ids]));
  sessionStorage.setItem(ACCOUNT_OPEN_ORDER_KEY, key);
  if (keepPending) sessionStorage.setItem(ACCOUNT_PENDING_OPEN_ORDER_KEY, key);
  sessionStorage.setItem(ACCOUNT_OPEN_TAB_KEY, 'orders');
}
function removeStoredAccountOrder(key, clearPending = false) {
  if (!key) return;
  const ids = getStoredAccountOrders().filter(item => item !== key);
  sessionStorage.setItem(ACCOUNT_OPEN_ORDERS_KEY, JSON.stringify(ids));
  if ((sessionStorage.getItem(ACCOUNT_PENDING_OPEN_ORDER_KEY) || '') === key) {
    if (clearPending) sessionStorage.removeItem(ACCOUNT_PENDING_OPEN_ORDER_KEY);
    else return;
  }
  if ((sessionStorage.getItem(ACCOUNT_OPEN_ORDER_KEY) || '') === key) sessionStorage.removeItem(ACCOUNT_OPEN_ORDER_KEY);
}
function rememberOpenAccountOrder(el) {
  const detail = getAccountOrderDetailFromElement(el);
  const key = detail?.dataset?.orderDetail || '';
  if (!key) return '';
  storeAccountOrderOpen(key, true);
  setAccountOrderOpen(detail, true);
  const targetHash = `#orders-${encodeURIComponent(key)}`;
  if (location.hash !== targetHash) history.replaceState(null, '', `/profile${targetHash}`);
  return key;
}
function reloadKeepingAccountOrderOpen(el) {
  const key = rememberOpenAccountOrder(el);
  if (key) {
    storeAccountOrderOpen(key, true);
    const targetUrl = `/profile#orders-${encodeURIComponent(key)}`;
    if (window.location.pathname === '/profile' && window.location.hash === `#orders-${encodeURIComponent(key)}`) {
      window.location.reload();
    } else {
      window.location.href = targetUrl;
    }
    return;
  }
  window.location.reload();
}
function restoreOpenAccountOrders(shouldScroll = false) {
  const hashKey = getOrderKeyFromHash();
  const pendingKey = sessionStorage.getItem(ACCOUNT_PENDING_OPEN_ORDER_KEY) || '';
  const keys = [...new Set([hashKey, pendingKey, ...getStoredAccountOrders()].filter(Boolean))];
  if (!keys.length) return;
  setAccountTab(sessionStorage.getItem(ACCOUNT_OPEN_TAB_KEY) || 'orders', false);
  let scrollTarget = null;
  keys.forEach((key) => {
    const detail = Array.from(document.querySelectorAll('.account-order-detail')).find(item => item.dataset.orderDetail === key);
    if (detail) {
      setAccountOrderOpen(detail, true, false);
      if (!scrollTarget && (key === hashKey || key === pendingKey)) scrollTarget = detail;
    }
  });
  if (scrollTarget) {
    storeAccountOrderOpen(scrollTarget.dataset.orderDetail || '');
    window.setTimeout(() => {
      if ((sessionStorage.getItem(ACCOUNT_PENDING_OPEN_ORDER_KEY) || '') === (scrollTarget.dataset.orderDetail || '')) {
        sessionStorage.removeItem(ACCOUNT_PENDING_OPEN_ORDER_KEY);
      }
    }, 1200);
    if (shouldScroll) window.setTimeout(() => scrollTarget.scrollIntoView({behavior:'smooth', block:'start'}), 160);
  }
}
function copyShippingToBilling(checked) {
  if (!checked) return;
  const map = [['ps-add1','pb-add1'],['ps-add2','pb-add2'],['ps-city','pb-city'],['ps-state','pb-state'],['ps-pin','pb-pin']];
  map.forEach(([from, to]) => { const src = document.getElementById(from); const dst = document.getElementById(to); if (src && dst) dst.value = src.value; });
}
function openAccountOrder(trigger) {
  const detail = trigger?.closest('.account-order-detail');
  if (!detail) return;
  setAccountOrderOpen(detail, true);
  const tracking = detail.querySelector('.account-order-tracking');
  const target = tracking || detail;
  tracking?.classList.remove('is-highlighted');
  target.scrollIntoView({behavior:'smooth', block:'center'});
  if (tracking) {
    window.setTimeout(() => tracking.classList.add('is-highlighted'), 220);
    window.setTimeout(() => tracking.classList.remove('is-highlighted'), 1800);
  }
}


async function submitAccountReview(event, form) {
  event.preventDefault();
  const msg = form.querySelector('.account-review-msg');
  const btn = form.querySelector('button[type="submit"]');
  if (msg) { msg.textContent = ''; msg.className = 'account-review-msg'; }
  const payload = {
    product_id: Number(form.product_id?.value || 0),
    order_item_id: Number(form.order_item_id?.value || 0),
    rating: Number(form.rating?.value || 0),
    comment: form.comment?.value.trim() || '',
  };
  if (!payload.comment || payload.comment.length < 10) {
    if (msg) { msg.textContent = 'Please write at least 10 characters.'; msg.classList.add('is-error'); }
    return;
  }
  try {
    if (btn) btn.disabled = true;
    const resp = await fetch('/api/reviews', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': APP.csrfToken },
      credentials: 'same-origin',
      body: JSON.stringify(payload),
    });
    const data = await resp.json();
    if (!data.ok) {
      if (msg) { msg.textContent = data.msg || 'Could not submit review.'; msg.classList.add('is-error'); }
      return;
    }
    if (msg) { msg.textContent = data.msg || 'Review submitted for approval.'; msg.classList.add('is-ok'); }
    window.setTimeout(() => window.location.reload(), 900);
  } catch (e) {
    if (msg) { msg.textContent = 'Could not submit review right now.'; msg.classList.add('is-error'); }
  } finally {
    if (btn) btn.disabled = false;
  }
}

async function saveProfile() {
  const err = document.getElementById('profErr');
  const ok = document.getElementById('profOk');
  if (err) err.style.display = 'none';
  if (ok) ok.style.display = 'none';

  const payload = {
    name: document.getElementById('p-name')?.value.trim() || '',
    email: document.getElementById('p-email')?.value.trim() || '',
    phone: document.getElementById('p-phone')?.value.trim() || '',
    company: document.getElementById('p-company')?.value.trim() || '',
    shipping: {
      address_line1: document.getElementById('ps-add1')?.value.trim() || '',
      address_line2: document.getElementById('ps-add2')?.value.trim() || '',
      city: document.getElementById('ps-city')?.value.trim() || '',
      state: document.getElementById('ps-state')?.value.trim() || '',
      pincode: document.getElementById('ps-pin')?.value.trim() || '',
    },
    billing: {
      legal_name: document.getElementById('pb-legal')?.value.trim() || '',
      gst_no: (document.getElementById('pb-gst')?.value || '').trim().toUpperCase(),
      address_line1: document.getElementById('pb-add1')?.value.trim() || '',
      address_line2: document.getElementById('pb-add2')?.value.trim() || '',
      city: document.getElementById('pb-city')?.value.trim() || '',
      state: document.getElementById('pb-state')?.value.trim() || '',
      pincode: document.getElementById('pb-pin')?.value.trim() || '',
    }
  };

  if (!payload.name || !payload.email || !payload.phone) {
    setAccountTab('details');
    const target = document.getElementById('profErr');
    target.textContent = 'Name, email and phone are required.';
    target.style.display = 'block';
    return;
  }

  try {
    const resp = await fetch('/api/profile', {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': APP.csrfToken },
      credentials: 'same-origin',
      body: JSON.stringify(payload),
    });
    const data = await resp.json();
    if (!data.ok) {
      setAccountTab('details');
      const target = document.getElementById('profErr');
      target.textContent = data.msg || 'Could not update profile.';
      target.style.display = 'block';
      return;
    }
    const target = document.getElementById('profOk');
    target.textContent = data.migration_required
      ? 'Basic profile updated. Billing fields will work after DB migration is applied.'
      : 'Profile updated successfully.';
    setAccountTab('details');
    target.style.display = 'block';
  } catch (e) {
    setAccountTab('details');
    const target = document.getElementById('profErr');
    target.textContent = 'Could not update profile right now.';
    target.style.display = 'block';
  }
}

async function changePassword() {
  const err = document.getElementById('pwErr');
  const ok = document.getElementById('pwOk');
  err.style.display = 'none';
  ok.style.display = 'none';

  const current_password = document.getElementById('pw-current')?.value || '';
  const new_password = document.getElementById('pw-new')?.value || '';
  const confirm_password = document.getElementById('pw-confirm')?.value || '';

  if (!current_password || !new_password || !confirm_password) {
    err.textContent = 'Please fill all password fields.';
    err.style.display = 'block';
    return;
  }
  if (new_password !== confirm_password) {
    err.textContent = 'New password and confirm password must match.';
    err.style.display = 'block';
    return;
  }

  try {
    const resp = await fetch('/api/profile/password', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': APP.csrfToken },
      credentials: 'same-origin',
      body: JSON.stringify({ current_password, new_password, confirm_password }),
    });
    const data = await resp.json();
    if (!data.ok) {
      err.textContent = data.msg || 'Could not update password.';
      err.style.display = 'block';
      return;
    }
    ['pw-current','pw-new','pw-confirm'].forEach(id => { const el = document.getElementById(id); if (el) el.value = ''; });
    ok.textContent = 'Password updated successfully.';
    ok.style.display = 'block';
  } catch (e) {
    err.textContent = 'Could not update password right now.';
    err.style.display = 'block';
  }
}

function togglePassField(id, btn) {
  const el = document.getElementById(id);
  if (!el) return;
  const show = el.type === 'password';
  el.type = show ? 'text' : 'password';
  btn.textContent = show ? '🙈' : '👁️';
}
</script>
<?php include INCLUDE_PATH . '/partials/site-footer.php'; ?>
<?php include INCLUDE_PATH . '/partials/footer.php'; ?>
