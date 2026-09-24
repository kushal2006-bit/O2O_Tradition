<?php
session_start();
require_once '../shared/config.php';
require_once '../shared/security.php';
require_once '../shared/payments.php';
require_once '../shared/payment_lifecycle.php';
o2oCsrfToken();
requireLogin('customer','login.php');

if (!o2oRazorpayConfigured()) { http_response_code(503); exit('Online payment is not configured.'); }

$db=getDB();
o2oExpirePendingPayments($db);
$customerId=(int)$_SESSION['customer_id'];
$type=$_GET['type']??'';
$orderId=(int)($_GET['id']??0);
if(!in_array($type,['rental','purchase'],true)||$orderId<=0){http_response_code(400);exit('Invalid payment order.');}

if($type==='rental'){
    $st=$db->prepare("SELECT o.id,o.final_total AS amount,o.payment_status,o.payment_method,o.item_id,o.vendor_id,pt.id AS transaction_id,pt.provider_order_id
        FROM orders o JOIN payment_transactions pt ON pt.order_type='rental' AND pt.order_id=o.id
        WHERE o.id=? AND o.customer_id=? LIMIT 1");
}else{
    $st=$db->prepare("SELECT po.id,po.total_amount AS amount,po.payment_status,po.payment_method,po.vendor_id,pt.id AS transaction_id,pt.provider_order_id
        FROM purchase_orders po JOIN payment_transactions pt ON pt.order_type='purchase' AND pt.order_id=po.id
        WHERE po.id=? AND po.customer_id=? LIMIT 1");
}
$st->execute([$orderId,$customerId]);$payment=$st->fetch();
if(!$payment){http_response_code(404);exit('Payment order not found.');}
if($payment['payment_method']!=='Online Payment'){header('Location: '.($type==='rental'?'order_success.php?order_id=':'buy_success.php?id=').$orderId);exit;}
if($payment['payment_status']==='paid'){header('Location: '.($type==='rental'?'order_success.php?order_id=':'buy_success.php?id=').$orderId);exit;}
?>
<!doctype html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Secure Payment – O2O Tradition</title><style>body{font-family:Arial;background:#FAF6EE;color:#3D2B0F;margin:0}.nav{background:#1A1108;color:#C9A84C;padding:18px 28px}.main{max-width:620px;margin:55px auto;background:#fff;padding:35px;box-shadow:0 2px 12px #0001;text-align:center}.amount{font:36px Georgia,serif;color:#8B1A1A;margin:18px}.muted{color:#777;font-size:13px;line-height:1.6}.btn{background:#C9A84C;color:#1A1108;border:0;padding:14px 24px;font-weight:bold;cursor:pointer}.error{padding:12px;background:#FEF2F2;color:#991B1B;margin:15px 0}</style></head>
<body><div class="nav">O2O Tradition · Secure Checkout</div><main class="main"><h1>Complete Payment</h1><p class="muted">Order #<?=str_pad($orderId,6,'0',STR_PAD_LEFT)?> · Razorpay Secure Checkout</p><div class="amount">₹<?=number_format((float)$payment['amount'],2)?></div><p class="muted">Your payment is processed by Razorpay. O2O Tradition does not receive or store your card details.</p><button id="pay" class="btn">Pay Securely</button><a href="orders.php" class="btn" style="display:inline-block;margin-top:10px;background:#fff;color:#1A1108;border:1px solid #ddd;text-decoration:none">Cancel / Return to Orders</a><div id="msg" class="muted" style="margin-top:18px"></div></main>
<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<script>
const csrf=<?=json_encode($_SESSION['csrf_token'])?>;
const options={key:<?=json_encode(O2O_RAZORPAY_KEY_ID)?>,amount:<?=json_encode((int)round((float)$payment['amount']*100))?>,currency:'INR',name:'O2O Tradition',description:<?=json_encode('O2O Tradition '.ucfirst($type).' #'.str_pad($orderId,6,'0',STR_PAD_LEFT))?>,order_id:<?=json_encode($payment['provider_order_id'])?>,handler:function(response){
 const body=new URLSearchParams({csrf_token:csrf,status:'success',type:<?=json_encode($type)?>,id:<?=json_encode((string)$orderId)?>,razorpay_payment_id:response.razorpay_payment_id,razorpay_order_id:response.razorpay_order_id,razorpay_signature:response.razorpay_signature});
 document.getElementById('msg').textContent='Verifying payment…';
 fetch('payment_callback.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body}).then(r=>r.json()).then(d=>{if(d.ok){location.href=d.redirect;}else{document.getElementById('msg').textContent=d.error||'Payment verification failed.';}}).catch(()=>{document.getElementById('msg').textContent='Payment completed, but confirmation could not be verified. Please check My Orders.';});
}};
const rzp=new Razorpay(options);
rzp.on('payment.failed',function(){document.getElementById('msg').textContent='Payment was not completed. You can retry from this page.';});
document.getElementById('pay').onclick=()=>rzp.open();
</script></body></html>