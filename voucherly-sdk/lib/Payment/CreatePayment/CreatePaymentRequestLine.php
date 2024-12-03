<?php

namespace VoucherlyApi\Payment;

class CreatePaymentRequestLine {
  
  public int $quantity = 0;
  
  public int $unitAmount = 0;
  
  public int $unitDiscountAmount = 0;
  
  public int $discountAmount = 0;
  
  public string $productName = '';
  
  public string $productImage = '';

  public bool $isFood = false;
  
}