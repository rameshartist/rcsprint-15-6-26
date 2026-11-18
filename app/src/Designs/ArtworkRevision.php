<?php
declare(strict_types=1);

namespace Designs;

/** One upload request is one revision; superseded files remain available to history. */
final class ArtworkRevision
{
    private static bool $ready = false;

    public static function ensureSchema(): void
    {
        if (self::$ready) return;
        $columns = array_column(\Database::rows("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='artwork_files'"), 'COLUMN_NAME');
        foreach (['is_current' => 'TINYINT(1) NOT NULL DEFAULT 1', 'design_batch_id' => 'VARCHAR(64) NULL', 'design_role' => 'VARCHAR(32) NULL'] as $name => $type) {
            if (!in_array($name, $columns, true)) \Database::query("ALTER TABLE artwork_files ADD COLUMN `{$name}` {$type}");
        }
        self::$ready = true;
    }

    public static function currentFiles(int $itemId, bool $proof = false): array
    {
        self::ensureSchema();
        $role = $proof ? "(af.design_role='admin_proof' OR (af.design_role IS NULL AND af.file_path LIKE '/uploads/artwork/proofs/%'))"
            : "(af.design_role='customer_artwork' OR (af.design_role IS NULL AND af.file_path NOT LIKE '/uploads/artwork/proofs/%'))";
        return \Database::rows("SELECT af.id,af.original_name,af.filename,af.file_path,af.mime_type,af.file_size,af.design_batch_id,af.design_role FROM artwork_files af WHERE af.order_item_id=? AND af.is_current=1 AND {$role} ORDER BY af.id", [$itemId]);
    }

    public static function normalizeFiles(array $input): array
    {
        if (!isset($input['name'])) throw new \InvalidArgumentException('Choose one or more design files.');
        if (!is_array($input['name'])) return [$input];
        if (count($input['name']) > 20) throw new \InvalidArgumentException('Choose at most 20 files per upload.');
        $files = [];
        foreach ($input['name'] as $key => $name) {
            if (!is_string($name)) throw new \InvalidArgumentException('Invalid upload format.');
            $file = [];
            foreach (['name','tmp_name','size','error','type'] as $field) $file[$field] = $input[$field][$key] ?? null;
            $files[] = $file;
        }
        if (!$files) throw new \InvalidArgumentException('Choose one or more design files.');
        return $files;
    }

    public static function upload(int $approvalId, array $input, bool $proof, array $actor, string $note = '', ?int $expectedCount = null): array
    {
        self::ensureSchema();
        if (!\Orders\OrderManager::ensureDesignApprovalSchema()
            || !\Orders\OrderManager::ensureDesignEventSchema()
            || !\Orders\OrderManager::ensureWorkflowSchema()
            || !\Orders\OrderManager::ensureCustomerUpdateSchema()) throw new \RuntimeException('Design workflow schema unavailable.');
        $files = self::normalizeFiles($input);
        if ($expectedCount !== null && $expectedCount !== count($files)) throw new \InvalidArgumentException('Some files did not reach the server. Please retry with a smaller set.');
        $maxMb = max(1, (int)\Database::setting('upload_max_mb', '50'));
        $allowed = array_map('trim', explode(',', (string)\Database::setting('upload_allowed_ext', 'pdf,ai,eps,png,jpg,jpeg,psd,cdr,svg,tif,tiff,zip')));
        // Validate the entire set before moving any file or replacing current artwork.
        foreach ($files as &$file) {
            if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string)$file['tmp_name'])) throw new \InvalidArgumentException('An upload did not complete. Please select all files and retry.');
            if ((int)$file['size'] > $maxMb * 1048576) throw new \InvalidArgumentException("Each file must be at most {$maxMb}MB.");
            $file['ext'] = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
            if (!in_array($file['ext'], $allowed, true)) throw new \InvalidArgumentException('File type .' . $file['ext'] . ' is not allowed.');
            $file['mime'] = (new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']) ?: 'application/octet-stream';
        }
        unset($file);
        $batch = bin2hex(random_bytes(16));
        $relative = '/uploads/artwork/' . ($proof ? 'proofs/' : 'revisions/') . date('Y/m/');
        $directory = UPLOAD_PATH . '/artwork/' . ($proof ? 'proofs/' : 'revisions/') . date('Y/m/');
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) throw new \RuntimeException('Upload directory unavailable.');
        $moved = [];
        $db = \Database::get();
        try {
            foreach ($files as &$file) {
                $file['filename'] = $batch . '-' . count($moved) . '.' . $file['ext'];
                $file['file_path'] = $relative . $file['filename'];
                $target = $directory . $file['filename'];
                if (!move_uploaded_file($file['tmp_name'], $target)) throw new \RuntimeException('Could not save all uploaded files.');
                $moved[] = $target;
            }
            unset($file);
            $db->beginTransaction();
            $approval = \Database::row('SELECT * FROM order_design_approvals WHERE id=? FOR UPDATE', [$approvalId]);
            if (!$approval) throw new \InvalidArgumentException('Design approval not found.');
            if (!$proof) {
                $order = \Database::row('SELECT user_id FROM orders WHERE id=?', [(int)$approval['order_id']]);
                if (!$order || (int)$order['user_id'] !== (int)$actor['id']) throw new \InvalidArgumentException('Design approval not found.');
                $initial = $approval['status'] === 'pending_review' && $approval['design_choice'] === 'upload' && empty($approval['customer_artwork_file_id']);
                if (!$initial && $approval['status'] !== 'issue_found') throw new \InvalidArgumentException('This design is not awaiting an upload.');
            } else $initial = false;
            $result = self::replaceFiles($approval, $files, $batch, $proof, $actor, $note, $initial);
            $db->commit();
            return $result;
        } catch (\Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            foreach ($moved as $path) if (is_file($path)) unlink($path);
            throw $e;
        }
    }

    /** Called inside the upload transaction; exposed for isolated database regression tests. */
    public static function replaceFiles(array $approval, array $files, string $batch, bool $proof, array $actor, string $note = '', bool $initial = false): array
    {
        if (!$files) throw new \InvalidArgumentException('No design files supplied.');
        $itemId = (int)$approval['order_item_id'];
        $role = $proof ? 'admin_proof' : 'customer_artwork';
        $status = $proof ? 'proof_uploaded' : 'pending_review';
        $event = ['order_id'=>(int)$approval['order_id'],'order_item_id'=>$itemId,'design_approval_id'=>(int)$approval['id'], 'actor_type'=>$proof?'admin':'customer','actor_id'=>(int)$actor['id'],'actor_name'=>$actor['name']??null,'status_before'=>$approval['status'],'status_after'=>$status];
        // Customer revisions also invalidate old proofs; a new proof replaces only prior proofs.
        $previous = $proof ? self::currentFiles($itemId, true) : array_merge(self::currentFiles($itemId), self::currentFiles($itemId, true));
        foreach ($previous as $old) {
            if (!\Orders\OrderManager::recordDesignEvent($event + ['event_type'=>'design_file_superseded','file_id'=>(int)$old['id'],'file_role'=>str_contains($old['file_path'], '/proofs/')?'admin_proof':'customer_artwork','note'=>'Superseded by a newer design batch; retained as history.', 'meta'=>['replaced_by_batch'=>$batch]])) throw new \RuntimeException('Could not preserve design history.');
            \Database::query('UPDATE artwork_files SET is_current=0 WHERE id=?', [(int)$old['id']]);
        }
        $saved = [];
        foreach ($files as $file) {
            $id = (int)\Database::insert('INSERT INTO artwork_files (uploaded_by,order_item_id,filename,original_name,file_path,mime_type,file_size,is_current,design_batch_id,design_role,created_at) VALUES (?,?,?,?,?,?,?,1,?,?,NOW())', [(int)$actor['id'],$itemId,$file['filename'],$file['name'],$file['file_path'],$file['mime'],(int)$file['size'],$batch,$role]);
            $saved[] = ['id'=>$id,'name'=>$file['name'],'path'=>$file['file_path'],'mime'=>$file['mime'],'view_url'=>'/account/artwork/'.$id.'/view','download_url'=>'/account/artwork/'.$id.'/download'];
            if (!\Orders\OrderManager::recordDesignEvent($event + ['event_type'=>$proof?'admin_proof_uploaded':($initial?'customer_artwork_uploaded':'customer_artwork_reuploaded'),'file_id'=>$id,'file_role'=>$proof?'admin_proof':($initial?'customer_artwork':'revision'),'note'=>$note ?: ($proof?'Corrected proof uploaded.':'Customer artwork uploaded for review.'),'meta'=>['batch_id'=>$batch,'file_count'=>count($files)]])) throw new \RuntimeException('Could not record design history.');
        }
        if ($proof) {
            if (!\Orders\OrderManager::updateDesignApproval((int)$approval['id'], 'proof_uploaded', $note, $saved[0]['id'], false)) throw new \RuntimeException('Could not update proof status.');
        } else {
            \Database::query("UPDATE order_design_approvals SET status='pending_review',customer_artwork_file_id=?,proof_file_id=NULL,customer_note=?,approved_at=NULL,updated_at=NOW() WHERE id=?", [$saved[0]['id'],'Customer uploaded '.count($saved).' design file(s).',(int)$approval['id']]);
            \Orders\OrderManager::markCustomerUpdate((int)$approval['order_id'], $initial?'customer_artwork_uploaded':'customer_artwork_reuploaded');
            \Orders\OrderManager::clearAdminUpdate((int)$approval['order_id']);
            \Orders\OrderManager::syncOrderDesignApproved((int)$approval['order_id']);
        }
        return ['ok'=>true,'status'=>$status,'msg'=>count($saved).' design file(s) uploaded for review.','artwork_id'=>$saved[0]['id'],'file_id'=>$saved[0]['id'],'file'=>$saved[0],'files'=>$saved,'batch_id'=>$batch];
    }
}
