<?php

namespace VoucherlyApi\PaymentGateway;

class PaymentGateway {

  public function __construct()
  {
  }
  
  public static function list() {
    return \VoucherlyApi\Request::get('payment_gateways');
  }
} 