<?php

namespace VoucherlyApi;

class Api
{
  private static $env = "production";
  private static $apiKeyLive;
  private static $apiKeySandbox;
  private static $version = "1.0.0";
  private static $platformVersionHeader;
  private static $pluginVersionHeader;
  private static $pluginNameHeader;
  private static $typeHeader;

  public static function testAuthentication($apiKey = null): bool {
    try {

      $test = $apiKey == null
        ? Request::get("payment_gateways")
        : Request::get_on_demand($apiKey, "payment_gateways");

      return true;

    } catch(NotSuccessException $ex) {

      if ($ex->getCode() == 401) {  
        return false;
      }

      return true;
    }
  }

  public static function getApiKey() {
    return self::$env == "live" ? self::$apiKeyLive : self::$apiKeySandbox;
  }
  
  public static function setApiKey($value, $environment) {
    if ($environment == "live") {
      self::$apiKeyLive = $value;
    }
    else {
      self::$apiKeySandbox = $value;
    }
  }
  
  public static function getEnvironment() {
    return self::$env;
  }
  
  public static function setSandbox($sandbox) {
    self::$env = $sandbox == true ? "sand" : "live";
  }

  /**
   * Get version 
   * @return string
  */
  public static function getVersion() {
    return self::$version;
  }  
  
  /**
   * Get platform version header
   * @return string
  */
  public static function getPlatformVersionHeader() {
    return self::$platformVersionHeader;
  }
  /**
   * Set platform version header
   * @param string $value
  */
  public static function setPlatformVersionHeader($value) {
    self::$platformVersionHeader = $value;
  }

  /**
   * Get plugin version header
   * @return string
  */
  public static function getPluginVersionHeader() {
    return self::$pluginVersionHeader;
  }
  /**
   * Set plugin version header
   * @param string $value
  */
  public static function setPluginVersionHeader($value) {
    self::$pluginVersionHeader = $value;
  }

  /**
   * Get plugin name header
   * @return string
  */
  public static function getPluginNameHeader() {
    return self::$pluginNameHeader;
  }
  /**
   * Set plugin name header
   * @param string $value
  */
  public static function setPluginNameHeader($value) {
    self::$pluginNameHeader = $value;
  }

  /**
   * Get type header
   * @return string
  */
  public static function getTypeHeader() {
    return self::$typeHeader;
  }
  /**
   * Set type header
   * @param string $value
  */
  public static function setTypeHeader($value) {
    self::$typeHeader = $value;
  }

}
