<?php
function o2oRazorpayConfigured(): bool {
    return defined('O2O_RAZORPAY_KEY_ID') && O2O_RAZORPAY_KEY_ID !== ''
        && defined('O2O_RAZORPAY_KEY_SECRET') && O2O_RAZORPAY_KEY_SECRET !== '';
}

function o2oCreateRazorpayOrder(float $amount, string $receipt): array {
    if (!o2oRazorpayConfigured()) {
        throw new RuntimeException('Online payment is not configured.');
    }
    $payload = json_encode([
        'amount' => (int)round($amount * 100),
        'currency' => 'INR',
        'receipt' => $receipt,
        'payment_capture' => 1,
    ], JSON_THROW_ON_ERROR);

    $ch = curl_init('https://api.razorpay.com/v1/orders');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_USERPWD => O2O_RAZORPAY_KEY_ID . ':' . O2O_RAZORPAY_KEY_SECRET,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT => 20,
    ]);
    $raw = curl_exec($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($raw === false || $error !== '' || $http < 200 || $http >= 300) {
        error_log('Razorpay order creation failed: HTTP '.$http.' '.$error);
        throw new RuntimeException('Unable to initialize online payment.');
    }
    $data = json_decode($raw, true);
    if (!is_array($data) || empty($data['id']) || empty($data['amount'])) {
        throw new RuntimeException('Payment gateway returned an invalid order.');
    }
    return $data;
}

function o2oFetchRazorpayPayment(string $paymentId): array
{
    if (!o2oRazorpayConfigured() || $paymentId === '') throw new RuntimeException('Payment gateway is not configured.');
    $ch=curl_init('https://api.razorpay.com/v1/payments/'.rawurlencode($paymentId));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPGET => true,
        CURLOPT_USERPWD => O2O_RAZORPAY_KEY_ID . ':' . O2O_RAZORPAY_KEY_SECRET,
        CURLOPT_TIMEOUT => 20,
    ]);
    $raw=curl_exec($ch); $http=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); $error=curl_error($ch); curl_close($ch);
    if ($raw===false || $error!=='' || $http<200 || $http>=300) throw new RuntimeException('Unable to verify payment status.');
    $data=json_decode($raw,true);
    if(!is_array($data) || empty($data['id'])) throw new RuntimeException('Payment gateway returned an invalid payment.');
    return $data;
}

function o2oRefundRazorpayPayment(string $paymentId, float $amount): array
{
    if (!o2oRazorpayConfigured() || $paymentId === '' || $amount <= 0) throw new RuntimeException('Payment gateway is not configured.');
    $payload=json_encode(['amount'=>(int)round($amount*100)],JSON_THROW_ON_ERROR);
    $ch=curl_init('https://api.razorpay.com/v1/payments/'.rawurlencode($paymentId).'/refund');
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$payload,CURLOPT_USERPWD=>O2O_RAZORPAY_KEY_ID.':'.O2O_RAZORPAY_KEY_SECRET,CURLOPT_HTTPHEADER=>['Content-Type: application/json'],CURLOPT_TIMEOUT=>20]);
    $raw=curl_exec($ch);$http=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);$error=curl_error($ch);curl_close($ch);
    if($raw===false||$error!==''||$http<200||$http>=300){error_log('Razorpay refund failed: HTTP '.$http.' '.$error);throw new RuntimeException('Unable to create the gateway refund.');}
    $data=json_decode($raw,true);if(!is_array($data)||empty($data['id']))throw new RuntimeException('Payment gateway returned an invalid refund.');return $data;
}

function o2oVerifyRazorpaySignature(string $providerOrderId, string $paymentId, string $signature): bool {
    if (!o2oRazorpayConfigured() || $providerOrderId === '' || $paymentId === '' || $signature === '') return false;
    $expected = hash_hmac('sha256', $providerOrderId . '|' . $paymentId, O2O_RAZORPAY_KEY_SECRET);
    return hash_equals($expected, $signature);
}
?>