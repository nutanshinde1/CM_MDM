<?php
declare(strict_types=1);
function db(): PDO {
 static $pdo = null;
 if ($pdo instanceof PDO) return $pdo;
 $host = getenv('DB_HOST') ?: '127.0.0.1';
 $name = getenv('DB_NAME') ?: 'cafeteria_management';
 $user = getenv('DB_USER') ?: 'root';
 $pass = getenv('DB_PASSWORD') ?: '';
 $pdo = new PDO("mysql:host=$host;dbname=$name;charset=utf8mb4", $user, $pass, [
  PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  PDO::ATTR_EMULATE_PREPARES => false
 ]);
 return $pdo;
}
function json_body(): array {
 $data=json_decode(file_get_contents('php://input'),true);
 return is_array($data)?$data:[];
}
function respond(array $data,int $status=200): never {
 http_response_code($status); header('Content-Type: application/json; charset=utf-8');
 echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); exit;
}
function need(string $key): string {
 $b=json_body(); $v=trim((string)($b[$key]??''));
 if($v==='') respond(['message'=>"$key is required"],400);
 return $v;
}
function user(): array {
 if(empty($_SESSION['user'])) respond(['message'=>'Please log in'],401);
 return $_SESSION['user'];
}
function admin(): array {
 $u=user(); if(($u['role']??'')!=='ADMIN') respond(['message'=>'Administrator access required'],403); return $u;
}
function audit(PDO $pdo,array $u,string $action,string $type,int $id,string $details=''): void {
 $s=$pdo->prepare('INSERT INTO admin_audit_logs(admin_user_id,action,entity_type,entity_id,details) VALUES(?,?,?,?,?)');
 $s->execute([$u['id'],$action,$type,$id,$details]);
}
