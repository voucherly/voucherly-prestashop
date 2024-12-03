<?php

namespace VoucherlyApi;

/**
 * Exception is the base class for all user exceptions.
 */
class NotSuccessException extends \Exception
{
	function __construct(int $code) { 
        
       parent::__construct("HTTP status is not 2xx", $code);
    }

}