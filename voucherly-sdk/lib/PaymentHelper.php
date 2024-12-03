<?php

namespace VoucherlyApi;

class PaymentHelper
{
  public static function isPaidOrCaptured($payment) {
    return $payment->status === 'Confirmed' || $payment->status === 'Captured' || $payment->status === 'Paid';
  }
}
