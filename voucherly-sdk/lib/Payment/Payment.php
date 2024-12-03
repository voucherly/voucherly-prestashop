<?php

namespace VoucherlyApi\Payment;

class Payment {

  public function __construct()
  {
  }
  
  public static function create(CreatePaymentRequest $request) {
    return \VoucherlyApi\Request::post('payments', $request);
  }

  public static function get(string $payment_id) {
    return \VoucherlyApi\Request::get('payments/'.$payment_id);
  }

  public static function capture(string $payment_id) {
    return \VoucherlyApi\Request::post('payments/'.$payment_id.'/confirm');
  }

  public static function refund(string $payment_id) {
    return \VoucherlyApi\Request::post('payments/'.$payment_id.'/refund', array(
      "transactions" => null
    ));
  }
} 