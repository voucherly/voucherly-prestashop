<?php

namespace VoucherlyApi\Payment;

class CreatePaymentRequest {
  
  public string $mode = 'Payment';
  public ?string $referenceId = null;
  public ?string $customerId = null;
  public string $customerEmail = '';
  public string $customerFirstName = '';
  public string $customerLastName = '';
  /**
   * @var string
   */
  public $redirectOkUrl = '';
  /**
   * @var string
   */
  public $redirectKoUrl = '';
  /**
   * @var string
   */
  public $callbackUrl = '';
  /**
   * @var string
   */
  public $language = '';
  /**
   * @var string
   */
  public $country = '';
  /**
   * @var string
   */
  public $shippingAddress = '';

  public $metadata = [];

  /**
   * @var CreatePaymentRequestLine[]
   */
  public $lines = [];
  /**
   * @var CreatePaymentRequestDiscount[]
   */
  public $discounts = [];
}