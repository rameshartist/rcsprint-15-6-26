<?php
declare(strict_types=1);

namespace Notifications;

final class AdminNotificationManager
{
    public static function ensureSchema(): void
    {
        \Database::query("CREATE TABLE IF NOT EXISTS admin_notifications (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            admin_id INT UNSIGNED NOT NULL,
            event_key VARCHAR(190) NOT NULL,
            type VARCHAR(40) NOT NULL,
            title VARCHAR(160) NOT NULL,
            message VARCHAR(500) NOT NULL,
            action_url VARCHAR(500) NOT NULL,
            source_created_at DATETIME NULL,
            read_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_admin_notification_event (admin_id,event_key),
            KEY idx_admin_notification_feed (admin_id,read_at,id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    public static function sync(int $adminId): void
    {
        self::ensureSchema();
        $events = [];
        $lastSync=(string)($_SESSION['admin_notification_sync_at']??'');
        $since=$lastSync!==''?date('Y-m-d H:i:s',max(0,strtotime($lastSync)-30)):null;
        try {
            $sql="SELECT id,order_id,customer_name,status,payment_status,customer_update_pending,customer_update_type,customer_update_at,refund_requested_at,created_at FROM orders WHERE (status='new_order' OR COALESCE(customer_update_pending,0)=1 OR refund_requested_at IS NOT NULL)".($since?" AND updated_at>=?":"")." ORDER BY id DESC LIMIT 80";
            foreach (\Database::rows($sql,$since?[$since]:[]) as $row) {
                $id=(int)$row['id']; $number=(string)($row['order_id'] ?: '#'.$id);
                if (!empty($row['refund_requested_at'])) $events[]=['order:refund:'.$id.':'.$row['refund_requested_at'],'refund','Refund Requested','Order '.$number.' requires a refund decision.','/admin/orders?attention=1#ord-'.$id,$row['refund_requested_at']];
                elseif (!empty($row['customer_update_pending'])) {
                    $type=(string)($row['customer_update_type']??'');$copy=match($type){'customer_design_approved'=>['Design Approved','Customer approved the design'],'customer_revision_requested'=>['Revision Requested','Customer requested a design revision'],'customer_artwork_uploaded'=>['Design File Uploaded','Customer uploaded a design file'],'customer_artwork_reuploaded'=>['Design File Replaced','Customer uploaded a replacement design file'],'customer_cancelled'=>['Order Cancelled','Customer cancelled the order'],'customer_refund_requested'=>['Refund Requested','Customer requested a refund'],'customer_paid'=>['Payment Completed','Customer completed payment'],default=>['Customer Action Required','Customer submitted an update']};
                    if(in_array($type,['customer_design_approved','customer_revision_requested','customer_artwork_uploaded','customer_artwork_reuploaded'],true))continue;
                    $events[]=['order:update:'.$id.':'.$type.':'.($row['customer_update_at']??''),'order_update',$copy[0],$copy[1].' for Order '.$number.'.','/admin/orders?attention=1#ord-'.$id,$row['customer_update_at']?:$row['created_at']];
                } else {$paid=($row['payment_status']??'')==='paid';$events[]=['order:new:'.$id,'order',$paid?'Payment Completed — New Order':'New Order Received','Order '.$number.' from '.($row['customer_name']?:'customer').($paid?' is paid and ready for processing.':' requires processing.'),'/admin/orders?status=new_order#ord-'.$id,$row['created_at']];}
            }
        } catch (\Throwable $e) { error_log('Order notification sync failed: '.$e->getMessage()); }
        try {
            $sql="SELECT id,request_code,customer_name,status,created_at FROM custom_quote_requests WHERE status IN ('new','customer_approved','payment_pending')".($since?" AND updated_at>=?":"")." ORDER BY id DESC LIMIT 60";
            foreach (\Database::rows($sql,$since?[$since]:[]) as $row) {
                $id=(int)$row['id']; $status=(string)$row['status'];
                $title=$status==='new'?'New Quote Request':($status==='customer_approved'?'Quote Approved':'Quote Payment Pending');
                $events[]=['quote:'.$status.':'.$id,'quote',$title,($row['request_code']?:'Quote #'.$id).' from '.($row['customer_name']?:'customer').' requires attention.','/admin/custom-orders#quote-'.$id,$row['created_at']];
            }
        } catch (\Throwable $e) { error_log('Quote notification sync failed: '.$e->getMessage()); }
        try {
            $sql="SELECT e.id,e.order_id,e.event_type,e.note,e.created_at,o.order_id public_order_id FROM order_design_events e JOIN orders o ON o.id=e.order_id WHERE e.actor_type='customer'".($since?" AND e.created_at>=?":"")." ORDER BY e.id DESC LIMIT 80";
            foreach(\Database::rows($sql,$since?[$since]:[]) as $row){
                $labels=[
                    'customer_artwork_uploaded'=>['Design File Uploaded','uploaded a design file'],
                    'customer_artwork_reuploaded'=>['Design File Replaced','uploaded a replacement design file'],
                    'customer_design_approved'=>['Design Approved','approved the design'],
                    'customer_revision_requested'=>['Revision Requested','requested a design revision'],
                ];
                if(!isset($labels[$row['event_type']]))continue;
                [$title,$action]=$labels[$row['event_type']];$number=(string)($row['public_order_id']?:'#'.$row['order_id']);
                $events[]=['design:'.$row['event_type'].':'.$row['id'],'design',$title,'Customer '.$action.' for Order '.$number.'.','/admin/orders?attention=1#ord-'.(int)$row['order_id'],$row['created_at']];
            }
        } catch (\Throwable $e) { error_log('Design notification sync failed: '.$e->getMessage()); }
        try {
            $sql="SELECT id,name,subject,priority,created_at FROM contact_leads WHERE COALESCE(is_read,0)=0".($since?" AND created_at>=?":"")." ORDER BY id DESC LIMIT 60";
            foreach(\Database::rows($sql,$since?[$since]:[]) as $row){$events[]=['lead:new:'.$row['id'],'lead','New Customer Inquiry',($row['name']?:'A customer').' submitted '.($row['subject']?:'a new inquiry').'.','/admin/leads#lead-'.(int)$row['id'],$row['created_at']];}
        } catch (\Throwable $e) { error_log('Lead notification sync failed: '.$e->getMessage()); }
        foreach ($events as [$key,$type,$title,$message,$url,$sourceAt]) {
            \Database::query("INSERT IGNORE INTO admin_notifications(admin_id,event_key,type,title,message,action_url,source_created_at,created_at) VALUES(?,?,?,?,?,?,?,NOW())",[$adminId,$key,$type,$title,$message,$url,$sourceAt]);
        }
        $_SESSION['admin_notification_sync_at']=date('Y-m-d H:i:s');
    }

    public static function feed(int $adminId, int $afterId = 0, int $limit = 30): array
    {
        self::sync($adminId); $limit=max(1,min(50,$limit));
        $rows=\Database::rows("SELECT id,type,title,message,action_url,source_created_at,read_at,created_at FROM admin_notifications WHERE admin_id=? AND id>? ORDER BY id DESC LIMIT {$limit}",[$adminId,$afterId]);
        $unread=(int)(\Database::row("SELECT COUNT(*) c FROM admin_notifications WHERE admin_id=? AND read_at IS NULL",[$adminId])['c']??0);
        $latest=(int)(\Database::row("SELECT COALESCE(MAX(id),0) id FROM admin_notifications WHERE admin_id=?",[$adminId])['id']??0);
        return ['notifications'=>$rows,'unread'=>$unread,'cursor'=>$latest];
    }

    public static function markRead(int $adminId, ?int $id = null): void
    {
        self::ensureSchema();
        if ($id) \Database::query("UPDATE admin_notifications SET read_at=COALESCE(read_at,NOW()) WHERE id=? AND admin_id=?",[$id,$adminId]);
        else \Database::query("UPDATE admin_notifications SET read_at=COALESCE(read_at,NOW()) WHERE admin_id=?",[$adminId]);
    }
}
