<?php
declare(strict_types=1);
namespace Orders {
    // Isolated event/status collaborators; the real upload, filesystem and revision queries execute below.
    class OrderManager {
        public static function ensureDesignApprovalSchema():bool{return true;}
        public static function ensureDesignEventSchema():bool{return true;}
        public static function ensureWorkflowSchema():bool{return true;}
        public static function ensureCustomerUpdateSchema():bool{return true;}
        public static function recordDesignEvent(array $event):bool {
            \Database::insert('INSERT INTO events (file_id,event_type,meta) VALUES (?,?,?)',[$event['file_id'],$event['event_type'],json_encode($event['meta']??[])]);return true;
        }
        public static function updateDesignApproval(int $id,string $status,string $note,?int $file=null):bool {
            \Database::query('UPDATE order_design_approvals SET status=?,proof_file_id=? WHERE id=?',[$status,$file,$id]);return true;
        }
        public static function markCustomerUpdate(int $id,string $type):void{}
        public static function clearAdminUpdate(int $id):void{}
        public static function syncOrderDesignApproved(int $id):void{}
    }
}
namespace {
    class Database {
        public static PDO $db;
        public static function get():PDO{return self::$db;}
        public static function setting(string $key,mixed $default=null):mixed{return $default;}
        public static function query(string $sql,array $params=[]):\PDOStatement {
            $sql=str_replace([' FOR UPDATE','NOW()'],['','CURRENT_TIMESTAMP'],$sql);
            $statement=self::$db->prepare($sql);$statement->execute($params);return $statement;
        }
        public static function rows(string $sql,array $params=[]):array {
            if(str_contains($sql,'information_schema.COLUMNS'))return array_map(fn($row)=>['COLUMN_NAME'=>$row['name']],self::$db->query('PRAGMA table_info(artwork_files)')->fetchAll(PDO::FETCH_ASSOC));
            return self::query($sql,$params)->fetchAll(PDO::FETCH_ASSOC);
        }
        public static function row(string $sql,array $params=[]):?array{return self::query($sql,$params)->fetch(PDO::FETCH_ASSOC)?:null;}
        public static function insert(string $sql,array $params=[]):int{self::query($sql,$params);return (int)self::$db->lastInsertId();}
    }
    require dirname(__DIR__).'/app/src/Designs/ArtworkRevision.php';
    $directory=getenv('ARTWORK_REVISION_TEST_DIR');
    if(!$directory || !is_dir($directory))throw new RuntimeException('Run through tests/artwork_revision_uploads.py with an isolated temporary directory.');
    define('UPLOAD_PATH',$directory.'/uploads');
    Database::$db=new PDO('sqlite:'.$directory.'/test.sqlite',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    if(PHP_SAPI !== 'cli-server') {
        Database::$db->exec("CREATE TABLE orders (id INTEGER PRIMARY KEY,user_id INTEGER); INSERT INTO orders VALUES(1,7),(2,8);
        CREATE TABLE order_design_approvals(id INTEGER PRIMARY KEY,order_id INTEGER,order_item_id INTEGER,status TEXT,design_choice TEXT,customer_artwork_file_id INTEGER,proof_file_id INTEGER,customer_note TEXT,approved_at TEXT,updated_at TEXT);
        INSERT INTO order_design_approvals VALUES(1,1,11,'issue_found','upload',1,2,NULL,NULL,NULL),(2,2,22,'issue_found','upload',3,NULL,NULL,NULL,NULL);
        CREATE TABLE artwork_files(id INTEGER PRIMARY KEY AUTOINCREMENT,uploaded_by INTEGER,order_item_id INTEGER,filename TEXT,original_name TEXT,file_path TEXT,mime_type TEXT,file_size INTEGER,is_current INTEGER DEFAULT 1,design_batch_id TEXT,design_role TEXT,created_at TEXT);
        INSERT INTO artwork_files(id,order_item_id,filename,original_name,file_path,is_current) VALUES(1,11,'old.png','old.png','/uploads/artwork/old.png',1),(2,11,'old-proof.pdf','old-proof.pdf','/uploads/artwork/proofs/old-proof.pdf',1),(3,22,'other.pdf','other.pdf','/uploads/artwork/other.pdf',1);
        CREATE TABLE events(id INTEGER PRIMARY KEY,file_id INTEGER,event_type TEXT,meta TEXT);");
        echo "Fixture ready\n";exit;
    }
    header('Content-Type: application/json');
    if($_SERVER['REQUEST_METHOD']==='GET') {echo json_encode(['ok'=>true]);exit;}
    try {
        $result=\Designs\ArtworkRevision::upload(1,$_FILES['artwork']??[],isset($_GET['proof']),['id'=>7,'name'=>'Test actor'],'Test revision',isset($_POST['file_count'])?(int)$_POST['file_count']:null);
        echo json_encode($result);
    } catch(InvalidArgumentException $e){http_response_code(422);echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);}
    catch(Throwable $e){http_response_code(500);echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);}
}
