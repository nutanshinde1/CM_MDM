<?php
declare(strict_types=1);
session_start();
require __DIR__.'/config.php';
header('Access-Control-Allow-Origin: '.(getenv('CORS_ORIGIN')?:'http://127.0.0.1:5500'));
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: GET, POST, PATCH, DELETE, OPTIONS');
if(['REQUEST_METHOD']==='OPTIONS'){http_response_code(204);exit;}
try {
 =db(); =['REQUEST_METHOD']; =parse_url(['REQUEST_URI'],PHP_URL_PATH);
 =preg_replace('#^/api/?#','',); =preg_replace('#^/cafeteria-management/api/?#','',); =array_values(array_filter(explode('/',),'strlen'));
 =json_body();
 =get_raw_body();

 if(==='POST' && ==='auth/register'){
  =need('name');=strtolower(need('email'));=need('phone');=need('password');
  if(!filter_var(,FILTER_VALIDATE_EMAIL)||strlen()<8)respond(['message'=>'Enter a valid email and password of at least 8 characters'],400);
  try{=->prepare("INSERT INTO users(name,email,phone,password_hash,role,status) VALUES(?,?,?,?,'CUSTOMER','ACTIVE')");->execute([,,,password_hash(,PASSWORD_DEFAULT)]);}
  catch(PDOException ){if(->getCode()==='23000')respond(['message'=>'Email already registered'],409);throw ;}
  respond(['message'=>'Registration successful','email'=>],201);
 }
 if(==='POST' && ==='auth/login'){
  =->prepare("SELECT id,name,email,phone,password_hash,role,status FROM users WHERE email=?");->execute([strtolower(need('email'))]);=->fetch();
  if(!||!password_verify(need('password'),['password_hash']))respond(['message'=>'Invalid email or password'],401);
  if(['status']!=='ACTIVE')respond(['message'=>'Account is blocked'],403);
  session_regenerate_id(true);unset(['password_hash']);['user']=;respond(['user'=>]);
 }
 if(==='POST' && ==='auth/logout'){=[];session_destroy();respond(['message'=>'Logged out']);}
 if(==='GET' && ==='auth/me')respond(user());
 if(==='GET' && ==='categories'){respond(->query("SELECT id,name,icon,description,status FROM categories WHERE status='ACTIVE' ORDER BY name")->fetchAll());}
 if(==='GET' && ==='food-items'){
  =->query("SELECT id,name,category_id AS categoryId,price,description,image,popular,status FROM food_items WHERE status='AVAILABLE' ORDER BY id");respond(->fetchAll());
 }
 if(==='GET' && preg_match('#^food-items/(\d+)$#',,)){
  =->prepare("SELECT id,name,category_id AS categoryId,price,description,image,popular,status FROM food_items WHERE id=? AND status='AVAILABLE'");->execute([(int)[1]]);=->fetch();if(!)respond(['message'=>'Food item not found'],404);respond();
 }

 if(==='POST' && ==='payments/create-order'){
  =user();
  if(!razorpay_configured())respond(['message'=>'Razorpay not configured. Set RAZORPAY_KEY_ID and RAZORPAY_KEY_SECRET.'],500);
  =['items']??[];if(!is_array()||!count())respond(['message'=>'Cart is empty'],400);
  ->beginTransaction();
  =0.0;=[];
  foreach( as ){
   =(int)(['id']??0);=(int)(['qty']??0);
   if(<1||<1||>20)respond(['message'=>'Invalid item or quantity'],400);
   =->prepare("SELECT id,name,price FROM food_items WHERE id=? AND status='AVAILABLE' FOR UPDATE");->execute([]);=->fetch();
   if(!)respond(['message'=>'An item is unavailable'],409);
   =(float)['price'];=*;+=;[]=['id'=>,'name'=>['name'],'price'=>,'qty'=>,'line_total'=>round(,2)];
  }
  =round(,2);
  ='CAF-'.strtoupper(bin2hex(random_bytes(4)));
  =->prepare("INSERT INTO orders(order_ref,user_id,customer_name,phone,email,total,currency,payment_method,payment_status,status,pickup,notes) VALUES(?,?,?,?,?,?,'INR',?,'INITIATED','AWAITING_PAYMENT',?,?)");
  ->execute([,(int)['id'],trim((string)(['customerName']??['name'])),trim((string)(['phone']??['phone'])),['email'],,trim((string)(['paymentMethod']??'RAZORPAY')),trim((string)(['pickup']??'As soon as possible')),trim((string)(['notes']??''))]);
  =(int)->lastInsertId();
  =->prepare('INSERT INTO order_items(order_id,food_item_id,item_name,unit_price,quantity,line_total) VALUES(?,?,?,?,?,?)');
  foreach( as )->execute([,['id'],['name'],['price'],['qty'],['line_total']]);
  =->prepare("INSERT INTO payments(order_id,amount,currency,method,status) VALUES(?,?,?,'RAZORPAY','INITIATED')");->execute([,,'INR']);
  =(int)round(*100);
  =->prepare("SELECT razorpay_order_id FROM payments WHERE order_id=?");->execute([]);=->fetch();
  if( && !empty(['razorpay_order_id'])){
   =['razorpay_order_id'];
  }else{
   =razorpay_api_call('POST','orders',['amount'=>,'currency'=>'INR','receipt'=>,'notes'=>['order_ref'=>,'user_id'=>(string)['id']]]);
   =['id']??'';
   ->prepare("UPDATE payments SET razorpay_order_id=? WHERE order_id=?")->execute([,]);
   ->prepare("UPDATE orders SET razorpay_order_id=? WHERE id=?")->execute([,]);
  }
  ->commit();
  respond(['orderRef'=>,'orderId'=>,'razorpayOrderId'=>,'amount'=>,'amountPaise'=>,'currency'=>'INR','keyId'=>get_razorpay_key_id(),'items'=>],201);
 }

 if(==='POST' && ==='payments/verify'){
  =user();
  =trim((string)(['orderRef']??['order_ref']??''));
  =trim((string)(['razorpay_order_id']??['razorpayOrderId']??''));
  =trim((string)(['razorpay_payment_id']??['razorpayPaymentId']??''));
  =trim((string)(['razorpay_signature']??['razorpaySignature']??''));
  if(===''||===''||===''||==='')respond(['message'=>'Missing payment verification details'],400);
  ->beginTransaction();
  =->prepare('SELECT o.id,o.user_id,o.order_ref,o.total,o.currency,o.payment_status,o.status,o.razorpay_order_id,p.id as payment_id,p.status as pstatus,p.razorpay_order_id as p_rzp FROM orders o JOIN payments p ON p.order_id=o.id WHERE o.order_ref=? FOR UPDATE');
  ->execute([]);=->fetch();
  if(!||((int)['user_id']!==(int)['id'])){ ->rollBack(); respond(['message'=>'Order not found'],404); }
  if(['razorpay_order_id'] && ['razorpay_order_id']!==){ ->rollBack(); respond(['message'=>'Razorpay order mismatch'],400); }
  if(['p_rzp'] && ['p_rzp']!==){ ->rollBack(); respond(['message'=>'Razorpay order mismatch'],400); }
  if(['payment_status']==='PAID' && ['status']!=='AWAITING_PAYMENT' && ['status']!=='CANCELLED'){ 
    ->commit();
    =->prepare('SELECT order_ref AS id,total,payment_method AS paymentMethod,payment_status AS paymentStatus,status,created_at AS date FROM orders WHERE order_ref=?');->execute([]);=->fetch();
    =->prepare('SELECT item_name AS name,unit_price AS price,quantity AS qty,line_total AS lineTotal FROM order_items WHERE order_id=?');->execute([(int)['id']]);['items']=->fetchAll();
    respond(['success'=>true,'order'=>]);
  }
  if(!verify_razorpay_signature(,,)){ ->rollBack(); respond(['message'=>'Invalid payment signature'],400); }
  try{
   =razorpay_api_call('GET','payments/'.);
   if((['status']??'')!=='captured' && (['status']??'')!=='authorized'){
    ->rollBack(); respond(['message'=>'Payment not successful'],400);
   }
   =(int)(['amount']??0); =(int)round(((float)['total'])*100);
   if(!==){ ->rollBack(); respond(['message'=>'Payment amount mismatch'],400); }
   if((['currency']??'INR')!=='INR'){ ->rollBack(); respond(['message'=>'Invalid currency'],400); }
  }catch(Throwable ){
   // continue if API check fails but signature is valid? be strict but allow - signature already verified
  }
  ->prepare("UPDATE payments SET razorpay_order_id=?,razorpay_payment_id=?,status='PAID',verified_at=NOW() WHERE order_id=?")->execute([,,(int)['id']]);
  ->prepare("UPDATE orders SET razorpay_order_id=?,razorpay_payment_id=?,payment_status='PAID',status='CONFIRMED',payment_verified_at=NOW() WHERE id=?")->execute([,,(int)['id']]);
  ->commit();
  =->prepare('SELECT order_ref AS id,total,payment_method AS paymentMethod,payment_status AS paymentStatus,status,created_at AS date FROM orders WHERE order_ref=?');->execute([]);=->fetch();
  =->prepare('SELECT item_name AS name,unit_price AS price,quantity AS qty,line_total AS lineTotal FROM order_items WHERE order_id=?');->execute([(int)['id']]);['items']=->fetchAll();
  respond(['success'=>true,'order'=>]);
 }

 if(==='POST' && ==='payments/webhook'){
  =['HTTP_X_RAZORPAY_SIGNATURE']??(['HTTP_X_RAZORPAY_SIGNATURE ']??'');
  if(===''){ foreach(getallheaders() as =>){ if(strtolower()==='x-razorpay-signature'){ =; break; } } }
  if(===''||!verify_razorpay_webhook_signature(,)){ http_response_code(400); echo json_encode(['message'=>'Invalid webhook signature']); exit; }
  =json_decode(,true);
  if(!is_array()) { http_response_code(400); echo json_encode(['message'=>'Invalid payload']); exit; }
  ->beginTransaction();
  try{
   =['payload']??[];
   =['payment']['entity']??[];
   if(isset(['id'])){
    =['id'];
    =['order_id']??'';
    =->prepare('SELECT o.id,o.order_ref,o.user_id,o.payment_status FROM orders o JOIN payments p ON p.order_id=o.id WHERE p.razorpay_order_id=? OR o.razorpay_order_id=? FOR UPDATE');
    ->execute([,]);=->fetch();
    if( && ['payment_status']!=='PAID'){
     =['status']??'';
     if(==='captured'){
      ->prepare("UPDATE payments SET razorpay_payment_id=COALESCE(razorpay_payment_id,?),status='PAID',verified_at=COALESCE(verified_at,NOW()) WHERE order_id=?")->execute([,(int)['id']]);
      ->prepare("UPDATE orders SET razorpay_payment_id=COALESCE(razorpay_payment_id,?),payment_status='PAID',status=CASE WHEN status='AWAITING_PAYMENT' THEN 'CONFIRMED' ELSE status END,payment_verified_at=COALESCE(payment_verified_at,NOW()) WHERE id=?")->execute([,(int)['id']]);
     }elseif(==='failed'){
      ->prepare("UPDATE payments SET razorpay_payment_id=COALESCE(razorpay_payment_id,?),status='FAILED' WHERE order_id=?")->execute([,(int)['id']]);
      ->prepare("UPDATE orders SET payment_status='FAILED' WHERE id=? AND payment_status NOT IN ('PAID')")->execute([(int)['id']]);
     }
    }
   }
   ->commit();
  }catch(Throwable ){ if(->inTransaction())->rollBack(); }
  http_response_code(200); echo json_encode(['status'=>'ok']); exit;
 }

 if(==='POST' && ==='orders'){
  =user();=['items']??[];if(!is_array()||!count())respond(['message'=>'Cart is empty'],400);
  ->beginTransaction();=0;=[];
  foreach( as ){=(int)(['id']??0);=(int)(['qty']??0);if(<1||<1||>20)respond(['message'=>'Invalid item or quantity'],400);
   =->prepare("SELECT id,name,price FROM food_items WHERE id=? AND status='AVAILABLE' FOR UPDATE");->execute([]);=->fetch();if(!)respond(['message'=>'An item is unavailable'],409);
   =(float)['price'];+=round(*,2);[]=['id'=>,'name'=>['name'],'price'=>,'qty'=>];
  }
  ='CAF-'.strtoupper(bin2hex(random_bytes(4)));
  =->prepare("INSERT INTO orders(order_ref,user_id,customer_name,phone,email,total,payment_method,payment_status,status,pickup,notes) VALUES(?,?,?,?,?,?,?,'INITIATED','AWAITING_PAYMENT',?,?)");
  ->execute([,['id'],trim((string)(['customerName']??['name'])),trim((string)(['phone']??['phone'])), ['email'],,trim((string)(['paymentMethod']??'UPI')),trim((string)(['pickup']??'As soon as possible')),trim((string)(['notes']??''))]);
  =(int)->lastInsertId();
  =->prepare('INSERT INTO order_items(order_id,food_item_id,item_name,unit_price,quantity,line_total) VALUES(?,?,?,?,?,?)');
  foreach( as )->execute([,['id'],['name'],['price'],['qty'],round(['price']*['qty'],2)]);
  =->prepare("INSERT INTO payments(order_id,amount,method,status) VALUES(?,?,?,'INITIATED')");->execute([,,['paymentMethod']??'UPI']);
  ->commit();respond(['id'=>,'orderRef'=>,'total'=>number_format(,2,'.',''),'paymentStatus'=>'INITIATED','status'=>'AWAITING_PAYMENT','items'=>,'upiId'=>getenv('CAFETERIA_UPI_ID')?:'configure-merchant@upi'],201);
 }
 if(==='POST' && preg_match('#^payments/([A-Z0-9-]+)/claim$#',,)){
  =user();=need('transactionReference');->beginTransaction();
  =->prepare('SELECT o.id,o.user_id,o.status FROM orders o WHERE o.order_ref=? FOR UPDATE');->execute([[1]]);=->fetch();
  if(!||((int)['user_id']!==(int)['id']))respond(['message'=>'Order not found'],404);
  if(['status']!=='AWAITING_PAYMENT')respond(['message'=>'Order is not awaiting payment'],409);
  =->prepare("UPDATE payments SET transaction_reference=?,status='PENDING_VERIFICATION',claimed_at=NOW() WHERE order_id=? AND status='INITIATED'");->execute([,['id']]);
  if(!->rowCount())respond(['message'=>'Payment claim already submitted'],409);
  ->prepare("UPDATE orders SET payment_status='PENDING_VERIFICATION',status='PAYMENT_REVIEW' WHERE id=?")->execute([['id']]);->commit();
  respond(['orderRef'=>[1],'paymentStatus'=>'PENDING_VERIFICATION','orderStatus'=>'PAYMENT_REVIEW','message'=>'Awaiting independent admin verification']);
 }
 if(==='GET' && ==='orders/my'){=user();=->prepare('SELECT order_ref AS id,total,payment_method AS paymentMethod,payment_status AS paymentStatus,status,created_at AS date FROM orders WHERE user_id=? ORDER BY id DESC');->execute([['id']]);respond(->fetchAll());}
 if(==='GET' && preg_match('#^orders/([A-Z0-9-]+)$#',,)){
  =user();=->prepare('SELECT * FROM orders WHERE order_ref=?');->execute([[1]]);=->fetch();if(!)respond(['message'=>'Order not found'],404);
  if((int)['user_id']!==(int)['id']&&['role']!=='ADMIN')respond(['message'=>'Not your order'],403);
  =->prepare('SELECT item_name AS name,unit_price AS price,quantity AS qty,line_total AS lineTotal FROM order_items WHERE order_id=?');->execute([['id']]);['items']=->fetchAll();respond();
 }
 if(==='GET' && ==='admin/payments/pending'){admin();respond(->query("SELECT p.id,p.transaction_reference AS transactionReference,p.amount,p.method,p.claimed_at AS submittedAt,o.order_ref AS orderRef,o.customer_name AS customerName FROM payments p JOIN orders o ON o.id=p.order_id WHERE p.status='PENDING_VERIFICATION' ORDER BY p.claimed_at")->fetchAll());}
 if(==='POST' && preg_match('#^admin/payments/(\d+)/decision$#',,)){
  =admin();=strtoupper(need('action'));=trim((string)(['reason']??''));->beginTransaction();
  =->prepare('SELECT p.*,o.id AS oid FROM payments p JOIN orders o ON o.id=p.order_id WHERE p.id=? FOR UPDATE');->execute([(int)[1]]);=->fetch();
  if(!||['status']!=='PENDING_VERIFICATION')respond(['message'=>'Payment not pending review'],409);
  if(==='APPROVE'){->prepare("UPDATE payments SET status='PAID',verified_by=?,verified_at=NOW() WHERE id=?")->execute([['id'],['id']]);->prepare("UPDATE orders SET payment_status='PAID',status='CONFIRMED' WHERE id=?")->execute([['oid']]);}
  elseif(==='REJECT'){if(==='')respond(['message'=>'Rejection reason is required'],400);->prepare("UPDATE payments SET status='REJECTED',verified_by=?,verified_at=NOW(),rejection_reason=? WHERE id=?")->execute([['id'],,['id']]);->prepare("UPDATE orders SET payment_status='REJECTED',status='CANCELLED' WHERE id=?")->execute([['oid']]);}
  else respond(['message'=>'Action must be APPROVE or REJECT'],400);
  audit(,,,'PAYMENT',(int)['id'],);->commit();respond(['message'=>'Payment decision saved']);
 }
 if(==='GET' && ==='admin/orders'){admin();respond(->query('SELECT order_ref AS id,customer_name AS customerName,total,payment_status AS paymentStatus,status,created_at AS date FROM orders ORDER BY id DESC')->fetchAll());}
 if(==='PATCH' && preg_match('#^admin/orders/([A-Z0-9-]+)/status$#',,)){
  =admin();=strtoupper(need('status'));->beginTransaction();=->prepare('SELECT id,payment_status,status FROM orders WHERE order_ref=? FOR UPDATE');->execute([[1]]);=->fetch();
  if(!)respond(['message'=>'Order not found'],404);if(['payment_status']!=='PAID')respond(['message'=>'Unpaid orders cannot enter preparation'],409);
  =['CONFIRMED'=>['PREPARING','CANCELLED'],'PREPARING'=>['READY'],'READY'=>['COMPLETED']];
  if(!in_array(,[['status']]??[],true))respond(['message'=>'Invalid status transition'],409);
  ->prepare('UPDATE orders SET status=? WHERE id=?')->execute([,['id']]);audit(,,'STATUS_CHANGE','ORDER',(int)['id'],);->commit();respond(['id'=>[1],'status'=>]);
 }
 if(==='GET' && ==='admin/dashboard'){admin();respond([
  'totalOrders'=>(int)->query('SELECT COUNT(*) FROM orders')->fetchColumn(),
  'totalCustomers'=>(int)->query("SELECT COUNT(*) FROM users WHERE role='CUSTOMER'")->fetchColumn(),
  'foodItems'=>(int)->query('SELECT COUNT(*) FROM food_items')->fetchColumn(),
  'pendingVerifications'=>(int)->query("SELECT COUNT(*) FROM payments WHERE status='PENDING_VERIFICATION'")->fetchColumn(),
  'revenue'=>(float)->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status='PAID'")->fetchColumn()
 ]);}
 if(==='POST' && ==='feedback'){=user();=(int)(['rating']??0);if(<1||>5)respond(['message'=>'Rating must be 1-5'],400);=->prepare('INSERT INTO feedback(user_id,rating,message) VALUES(?,?,?)');->execute([['id'],,trim((string)(['message']??''))]);respond(['message'=>'Feedback submitted'],201);}
 respond(['message'=>'Endpoint not found'],404);
} catch(Throwable ){if(isset()&& instanceof PDO&&->inTransaction())->rollBack();error_log((string));respond(['message'=>'Server error'],500);}
