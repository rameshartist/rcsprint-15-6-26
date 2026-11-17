<?php
$pageTitle='Order Cleanup — RCS Admin';$currentAdmPage='order-cleanup';$admMainClass='adm-main--order-cleanup';include __DIR__.'/layout.php';
$count=static function(string $sql):int{try{return (int)(Database::row($sql)['c']??0);}catch(Throwable){return 0;}};
$counts=['orders'=>$count('SELECT COUNT(*) c FROM orders'),'cancelled_orders'=>$count("SELECT COUNT(*) c FROM orders WHERE status='cancelled'"),'quotes'=>$count('SELECT COUNT(*) c FROM custom_quote_requests'),'rejected_quotes'=>$count("SELECT COUNT(*) c FROM custom_quote_requests WHERE status='rejected'")];
$success=trim((string)($_GET['success']??''));$error=trim((string)($_GET['error']??''));$h=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');
?>
<div class="oc-page">
  <section class="oc-hero"><div><span class="oc-kicker">Super Admin Tool</span><h1>Order Cleanup</h1><p>Permanently remove all or cancelled orders, and all or rejected quotes.</p></div><a class="oc-backup-link" href="/admin/backup">Download Backup First</a></section>
  <?php if($success!==''):?><div class="oc-alert oc-alert--success"><?= $h($success) ?></div><?php endif;?>
  <?php if($error!==''):?><div class="oc-alert oc-alert--error"><?= $h($error) ?></div><?php endif;?>
  <section class="oc-cleanup-columns">
    <form class="oc-confirm-card" method="post" action="/admin/order-cleanup/delete-all" onsubmit="return confirm('Selected orders and their linked records will be permanently deleted. Continue?')">
      <input type="hidden" name="_token" value="<?= $h($csrf??'') ?>"><input type="hidden" name="entity" value="orders">
      <h2>Delete Orders</h2><p>Choose exactly which order records should be removed.</p>
      <label class="oc-field"><span>Orders</span><select class="fi" name="scope"><option value="all">All Orders (<?= number_format($counts['orders']) ?>)</option><option value="cancelled">Cancelled Orders (<?= number_format($counts['cancelled_orders']) ?>)</option></select></label>
      <button class="oc-delete-btn" type="submit" <?= $counts['orders']<=0?'disabled':'' ?>>Delete Orders</button>
    </form>
    <form class="oc-confirm-card" method="post" action="/admin/order-cleanup/delete-all" onsubmit="return confirm('Selected quotes will be permanently deleted. Continue?')">
      <input type="hidden" name="_token" value="<?= $h($csrf??'') ?>"><input type="hidden" name="entity" value="quotes">
      <h2>Delete Quotes</h2><p>Choose exactly which quote records should be removed.</p>
      <label class="oc-field"><span>Quotes</span><select class="fi" name="scope"><option value="all">All Quotes (<?= number_format($counts['quotes']) ?>)</option><option value="rejected">Rejected Quotes (<?= number_format($counts['rejected_quotes']) ?>)</option></select></label>
      <button class="oc-delete-btn" type="submit" <?= $counts['quotes']<=0?'disabled':'' ?>>Delete Quotes</button>
    </form>
  </section>
  <p class="oc-note">Deletion is permanent. Download a database backup before cleanup.</p>
</div>
