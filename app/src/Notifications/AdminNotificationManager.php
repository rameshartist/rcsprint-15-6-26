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
            $sql="SELECT id,order_id,customer_name,status,customer_update_pending,customer_update_type,customer_update_at,refund_requested_at,created_at FROM orders WHERE (status='new_order' OR COALESCE(customer_update_pending,0)=1 OR refund_requested_at IS NOT NULL)".($since?" AND updated_at>=?":"")." ORDER BY id DESC LIMIT 80";
            foreach (\Database::rows($sql,$since?[$since]:[]) as $row) {
                $id=(int)$row['id']; $number=(string)($row['order_id'] ?: '#'.$id);
                if (!empty($row['refund_requested_at'])) $events[]=['order:refund:'.$id.':'.$row['refund_requested_at'],'refund','Refund Requested','Order '.$number.' requires a refund decision.','/admin/orders?attention=1#ord-'.$id,$row['refund_requested_at']];
                elseif (!empty($row['customer_update_pending'])) $events[]=['order:update:'.$id.':'.($row['customer_update_type']??'customer').':'.($row['customer_update_at']??''),'order_update','Customer Update','Order '.$number.' has a customer update requiring attention.','/admin/orders?attention=1#ord-'.$id,$row['customer_update_at']?:$row['created_at']];
                else $events[]=['order:new:'.$id,'order','New Order Received','Order '.$number.' from '.($row['customer_name']?:'customer').' requires processing.','/admin/orders?status=new_order#ord-'.$id,$row['created_at']];
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
