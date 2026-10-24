<?php
declare(strict_types=1);
session_start();
require __DIR__.'/config.php';
header('Access-Control-Allow-Origin: '.(getenv('CORS_ORIGIN')?:'http://127.0.0.1:5500'));
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: GET, POST, PATCH, DELETE, OPTIONS');
if($_SERVER['REQUEST_METHOD']==='OPTIONS'){http_response_code(204);exit;}
try {
 $pdo=db(); $method=$_SERVER['REQUEST_METHOD']; $path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
 $path=preg_replace('#^/api/?#','',$path); $parts=array_values(array_filter(explode('/',$path),'strlen'));
 $body=json_body();
 if($method==='POST' && $path==='auth/register'){
  $name=need('name');$email=strtolower(need('email'));$phone=need('phone');$pw=need('password');
  if(!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($pw)<8)respond(['message'=>'Enter a valid email and password of at least 8 characters'],400);
  try{$s=$pdo->prepare("INSERT INTO users(name,email,phone,password_hash,role,status) VALUES(?,?,?,?,'CUSTOMER','ACTIVE')");$s->execute([$name,$email,$phone,password_hash($pw,PASSWORD_DEFAULT)]);}
  catch(PDOException $e){if($e->getCode()==='23000')respond(['message'=>'Email already registered'],409);throw $e;}
  respond(['message'=>'Registration successful','email'=>$email],201);
 }
 if($method==='POST' && $path==='auth/login'){
  $s=$pdo->prepare("SELECT id,name,email,phone,password_hash,role,status FROM users WHERE email=?");$s->execute([strtolower(need('email'))]);$u=$s->fetch();
  if(!$u||!password_verify(need('password'),$u['password_hash']))respond(['message'=>'Invalid email or password'],401);
  if($u['status']!=='ACTIVE')respond(['message'=>'Account is blocked'],403);
  session_regenerate_id(true);unset($u['password_hash']);$_SESSION['user']=$u;respond(['user'=>$u]);
 }
 if($method==='POST' && $path==='auth/logout'){$_SESSION=[];session_destroy();respond(['message'=>'Logged out']);}
 if($method==='GET' && $path==='auth/me')respond(user());
 if($method==='GET' && $path==='categories'){respond($pdo->query("SELECT id,name,icon,description,status FROM categories WHERE status='ACTIVE' ORDER BY name")->fetchAll());}
 if($method==='GET' && $path==='food-items'){
  $s=$pdo->query("SELECT id,name,category_id AS categoryId,price,description,image,popular,status FROM food_items WHERE status='AVAILABLE' ORDER BY id");respond($s->fetchAll());
 }
 if($method==='GET' && preg_match('#^food-items/(\d+)$#',$path,$m)){
  $s=$pdo->prepare("SELECT id,name,category_id AS categoryId,price,description,image,popular,status FROM food_items WHERE id=? AND status='AVAILABLE'");$s->execute([(int)$m[1]]);$r=$s->fetch();if(!$r)respond(['message'=>'Food item not found'],404);respond($r);
 }
 if($method==='POST' && $path==='orders'){
  $u=user();$items=$body['items']??[];if(!is_array($items)||!count($items))respond(['message'=>'Cart is empty'],400);
  $pdo->beginTransaction();$total=0;$validated=[];
  foreach($items as $it){$id=(int)($it['id']??0);$qty=(int)($it['qty']??0);if($id<1||$qty<1||$qty>20)respond(['message'=>'Invalid item or quantity'],400);
   $s=$pdo->prepare("SELECT id,name,price FROM food_items WHERE id=? AND status='AVAILABLE' FOR UPDATE");$s->execute([$id]);$f=$s->fetch();if(!$f)respond(['message'=>'An item is unavailable'],409);
   $price=(float)$f['price'];$total+=round($price*$qty,2);$validated[]=['id'=>$id,'name'=>$f['name'],'price'=>$price,'qty'=>$qty];
  }
  $ref='CAF-'.strtoupper(bin2hex(random_bytes(4)));
  $s=$pdo->prepare("INSERT INTO orders(order_ref,user_id,customer_name,phone,email,total,payment_method,payment_status,status,pickup,notes) VALUES(?,?,?,?,?,?,?,'INITIATED','AWAITING_PAYMENT',?,?)");
  $s->execute([$ref,$u['id'],trim((string)($body['customerName']??$u['name'])),trim((string)($body['phone']??$u['phone'])), $u['email'],$total,trim((string)($body['paymentMethod']??'UPI')),trim((string)($body['pickup']??'As soon as possible')),trim((string)($body['notes']??''))]);
  $oid=(int)$pdo->lastInsertId();
  $line=$pdo->prepare('INSERT INTO order_items(order_id,food_item_id,item_name,unit_price,quantity,line_total) VALUES(?,?,?,?,?,?)');
  foreach($validated as $i)$line->execute([$oid,$i['id'],$i['name'],$i['price'],$i['qty'],round($i['price']*$i['qty'],2)]);
  $pay=$pdo->prepare("INSERT INTO payments(order_id,amount,method,status) VALUES(?,?,?,'INITIATED')");$pay->execute([$oid,$total,$body['paymentMethod']??'UPI']);
  $pdo->commit();respond(['id'=>$ref,'orderRef'=>$ref,'total'=>number_format($total,2,'.',''),'paymentStatus'=>'INITIATED','status'=>'AWAITING_PAYMENT','items'=>$validated,'upiId'=>getenv('CAFETERIA_UPI_ID')?:'configure-merchant@upi'],201);
 }
 if($method==='POST' && preg_match('#^payments/([A-Z0-9-]+)/claim$#',$path,$m)){
  $u=user();$txn=need('transactionReference');$pdo->beginTransaction();
  $s=$pdo->prepare('SELECT o.id,o.user_id,o.status FROM orders o WHERE o.order_ref=? FOR UPDATE');$s->execute([$m[1]]);$o=$s->fetch();
  if(!$o||((int)$o['user_id']!==(int)$u['id']))respond(['message'=>'Order not found'],404);
  if($o['status']!=='AWAITING_PAYMENT')respond(['message'=>'Order is not awaiting payment'],409);
  $s=$pdo->prepare("UPDATE payments SET transaction_reference=?,status='PENDING_VERIFICATION',claimed_at=NOW() WHERE order_id=? AND status='INITIATED'");$s->execute([$txn,$o['id']]);
  if(!$s->rowCount())respond(['message'=>'Payment claim already submitted'],409);
  $pdo->prepare("UPDATE orders SET payment_status='PENDING_VERIFICATION',status='PAYMENT_REVIEW' WHERE id=?")->execute([$o['id']]);$pdo->commit();
  respond(['orderRef'=>$m[1],'paymentStatus'=>'PENDING_VERIFICATION','orderStatus'=>'PAYMENT_REVIEW','message'=>'Awaiting independent admin verification']);
 }
 if($method==='GET' && $path==='orders/my'){$u=user();$s=$pdo->prepare('SELECT order_ref AS id,total,payment_method AS paymentMethod,payment_status AS paymentStatus,status,created_at AS date FROM orders WHERE user_id=? ORDER BY id DESC');$s->execute([$u['id']]);respond($s->fetchAll());}
 if($method==='GET' && preg_match('#^orders/([A-Z0-9-]+)$#',$path,$m)){
  $u=user();$s=$pdo->prepare('SELECT * FROM orders WHERE order_ref=?');$s->execute([$m[1]]);$o=$s->fetch();if(!$o)respond(['message'=>'Order not found'],404);
  if((int)$o['user_id']!==(int)$u['id']&&$u['role']!=='ADMIN')respond(['message'=>'Not your order'],403);
  $s=$pdo->prepare('SELECT item_name AS name,unit_price AS price,quantity AS qty,line_total AS lineTotal FROM order_items WHERE order_id=?');$s->execute([$o['id']]);$o['items']=$s->fetchAll();respond($o);
 }
 if($method==='GET' && $path==='admin/payments/pending'){admin();respond($pdo->query("SELECT p.id,p.transaction_reference AS transactionReference,p.amount,p.method,p.claimed_at AS submittedAt,o.order_ref AS orderRef,o.customer_name AS customerName FROM payments p JOIN orders o ON o.id=p.order_id WHERE p.status='PENDING_VERIFICATION' ORDER BY p.claimed_at")->fetchAll());}
 if($method==='POST' && preg_match('#^admin/payments/(\d+)/decision$#',$path,$m)){
  $a=admin();$action=strtoupper(need('action'));$reason=trim((string)($body['reason']??''));$pdo->beginTransaction();
  $s=$pdo->prepare('SELECT p.*,o.id AS oid FROM payments p JOIN orders o ON o.id=p.order_id WHERE p.id=? FOR UPDATE');$s->execute([(int)$m[1]]);$p=$s->fetch();
  if(!$p||$p['status']!=='PENDING_VERIFICATION')respond(['message'=>'Payment not pending review'],409);
  if($action==='APPROVE'){$pdo->prepare("UPDATE payments SET status='PAID',verified_by=?,verified_at=NOW() WHERE id=?")->execute([$a['id'],$p['id']]);$pdo->prepare("UPDATE orders SET payment_status='PAID',status='CONFIRMED' WHERE id=?")->execute([$p['oid']]);}
  elseif($action==='REJECT'){if($reason==='')respond(['message'=>'Rejection reason is required'],400);$pdo->prepare("UPDATE payments SET status='REJECTED',verified_by=?,verified_at=NOW(),rejection_reason=? WHERE id=?")->execute([$a['id'],$reason,$p['id']]);$pdo->prepare("UPDATE orders SET payment_status='REJECTED',status='CANCELLED' WHERE id=?")->execute([$p['oid']]);}
  else respond(['message'=>'Action must be APPROVE or REJECT'],400);
  audit($pdo,$a,$action,'PAYMENT',(int)$p['id'],$reason);$pdo->commit();respond(['message'=>'Payment decision saved']);
 }
 if($method==='GET' && $path==='admin/orders'){admin();respond($pdo->query('SELECT order_ref AS id,customer_name AS customerName,total,payment_status AS paymentStatus,status,created_at AS date FROM orders ORDER BY id DESC')->fetchAll());}
 if($method==='PATCH' && preg_match('#^admin/orders/([A-Z0-9-]+)/status$#',$path,$m)){
  $a=admin();$status=strtoupper(need('status'));$pdo->beginTransaction();$s=$pdo->prepare('SELECT id,payment_status,status FROM orders WHERE order_ref=? FOR UPDATE');$s->execute([$m[1]]);$o=$s->fetch();
  if(!$o)respond(['message'=>'Order not found'],404);if($o['payment_status']!=='PAID')respond(['message'=>'Unpaid orders cannot enter preparation'],409);
  $trans=['CONFIRMED'=>['PREPARING','CANCELLED'],'PREPARING'=>['READY'],'READY'=>['COMPLETED']];
  if(!in_array($status,$trans[$o['status']]??[],true))respond(['message'=>'Invalid status transition'],409);
  $pdo->prepare('UPDATE orders SET status=? WHERE id=?')->execute([$status,$o['id']]);audit($pdo,$a,'STATUS_CHANGE','ORDER',(int)$o['id'],$status);$pdo->commit();respond(['id'=>$m[1],'status'=>$status]);
 }
 if($method==='GET' && $path==='admin/dashboard'){admin();respond([
  'totalOrders'=>(int)$pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn(),
  'totalCustomers'=>(int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='CUSTOMER'")->fetchColumn(),
  'foodItems'=>(int)$pdo->query('SELECT COUNT(*) FROM food_items')->fetchColumn(),
  'pendingVerifications'=>(int)$pdo->query("SELECT COUNT(*) FROM payments WHERE status='PENDING_VERIFICATION'")->fetchColumn(),
  'revenue'=>(float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status='PAID'")->fetchColumn()
 ]);}
 if($method==='POST' && $path==='feedback'){$u=user();$rating=(int)($body['rating']??0);if($rating<1||$rating>5)respond(['message'=>'Rating must be 1–5'],400);$s=$pdo->prepare('INSERT INTO feedback(user_id,rating,message) VALUES(?,?,?)');$s->execute([$u['id'],$rating,trim((string)($body['message']??''))]);respond(['message'=>'Feedback submitted'],201);}
 respond(['message'=>'Endpoint not found'],404);
} catch(Throwable $e){if(isset($pdo)&&$pdo instanceof PDO&&$pdo->inTransaction())$pdo->rollBack();error_log((string)$e);respond(['message'=>'Server error. Check PHP error log and database configuration.'],500);}
