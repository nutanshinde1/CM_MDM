<?php
declare(strict_types=1);

require_once __DIR__.'/env-loader.php';
load_env(__DIR__.'/.env');

function db(): PDO {
 static \ = null;
 if (\ instanceof PDO) return \;
 \System.Management.Automation.Internal.Host.InternalHost = getenv('DB_HOST') ?: '127.0.0.1';
 \ = getenv('DB_NAME') ?: 'cafeteria_management';
 \ = getenv('DB_USER') ?: 'root';
 \ = getenv('DB_PASSWORD') ?: '';
 \ = new PDO("mysql:host=\System.Management.Automation.Internal.Host.InternalHost;dbname=\;charset=utf8mb4", \, \, [
  PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  PDO::ATTR_EMULATE_PREPARES => false
 ]);
 return \;
}
function json_body(): array {
 \ = file_get_contents('php://input');
 \ = json_decode(\ === false ? '[]' : \, true);
 return is_array(\) ? \ : [];
}
function get_raw_body(): string {
 \ = file_get_contents('php://input');
 return \ === false ? '' : \;
}
function respond(array \,int \=200): never {
 http_response_code(\); header('Content-Type: application/json; charset=utf-8');
 echo json_encode(\,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); exit;
}
function need(string \): string {
 \=json_body(); \=trim((string)(\[\]??''));
 if(\==='') respond(['message'=>"\ is required"],400);
 return \;
}
function user(): array {
 if(empty(\['user'])) respond(['message'=>'Please log in'],401);
 return \['user'];
}
function admin(): array {
 \=user(); if((\['role']??'')!=='ADMIN') respond(['message'=>'Administrator access required'],403); return \;
}
function audit(PDO \,array \,string \,string \,int \,string \=''): void {
 \=\->prepare('INSERT INTO admin_audit_logs(admin_user_id,action,entity_type,entity_id,details) VALUES(?,?,?,?,?)');
 \->execute([\['id'],\,\,\,\]);
}

function get_razorpay_key_id(): string {
 \ = getenv('RAZORPAY_KEY_ID') ?: '';
 return is_string(\) ? trim(\) : '';
}
function get_razorpay_key_secret(): string {
 \ = getenv('RAZORPAY_KEY_SECRET') ?: '';
 return is_string(\) ? trim(\) : '';
}
function get_razorpay_webhook_secret(): string {
 \ = getenv('RAZORPAY_WEBHOOK_SECRET') ?: '';
 return is_string(\) ? trim(\) : '';
}
function razorpay_configured(): bool {
 return get_razorpay_key_id() !== '' && get_razorpay_key_secret() !== '';
}
function razorpay_api_call(string \, string \, array \ = []): array {
 \ = get_razorpay_key_id();
 \ = get_razorpay_key_secret();
 if (\ === '' || \ === '') {
  respond(['message'=>'Razorpay credentials not configured. Set RAZORPAY_KEY_ID and RAZORPAY_KEY_SECRET.'],500);
 }
 \ = 'https://api.razorpay.com/v1/' . ltrim(\, '/');
 \ = curl_init(\);
 \ = ['Content-Type: application/json'];
 curl_setopt(\, CURLOPT_RETURNTRANSFER, true);
 curl_setopt(\, CURLOPT_USERPWD, \ . ':' . \);
 curl_setopt(\, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
 curl_setopt(\, CURLOPT_TIMEOUT, 30);
 if (strtoupper(\) === 'POST') {
  curl_setopt(\, CURLOPT_POST, true);
  curl_setopt(\, CURLOPT_HTTPHEADER, \);
  curl_setopt(\, CURLOPT_POSTFIELDS, json_encode(\));
 } else {
  curl_setopt(\, CURLOPT_CUSTOMREQUEST, \);
  curl_setopt(\, CURLOPT_HTTPHEADER, \);
 }
 \ = curl_exec(\);
 \ = curl_error(\);
 \ = curl_getinfo(\, CURLINFO_HTTP_CODE);
 curl_close(\);
 if (\ === false) {
  respond(['message'=>'Failed to reach Razorpay: ' . \],502);
 }
 \ = json_decode(\, true);
 if (!is_array(\)) {
  respond(['message'=>'Invalid response from Razorpay'],502);
 }
 if (\ >= 400) {
  \ = \['error']['description'] ?? \['error']['message'] ?? 'Razorpay request failed';
  respond(['message' => \], \);
 }
 return \;
}
function verify_razorpay_signature(string \, string \, string \): bool {
 \ = get_razorpay_key_secret();
 if (\ === '') {
  return false;
 }
 \ = \ . '|' . \;
 \ = hash_hmac('sha256', \, \);
 return hash_equals(\, \);
}
function verify_razorpay_webhook_signature(string \, string \): bool {
 \ = get_razorpay_webhook_secret();
 if (\ === '') {
  return false;
 }
 \ = hash_hmac('sha256', \, \);
 return hash_equals(\, \);
}
