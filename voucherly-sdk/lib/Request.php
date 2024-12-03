<?php
/**
 * Copyright (C) 2023  Voucherly
 * 
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 *
 * @author    Voucherly <info@voucherly.it>
 * @copyright 2024 Voucherly
 * @license   https://opensource.org/license/gpl-3-0/ GNU General Public License version 3 (GPL-3.0)
 */


namespace VoucherlyApi;

class Request
{

  const REQUEST_GET = 'GET';
  const REQUEST_POST = 'POST';

  public static function get( $route = '') {
    return self::call($route, "GET");
  }

  public static function get_on_demand( $apiKey, $route = '') {
    return self::call_on_demand($apiKey, $route, "GET");
  }

  public static function post( $route = '', $params = []) {
    return self::call($route, "POST", $params);
  }

  public static function post_on_demand( $apiKey, $route = '', $params = []) {
    return self::call_on_demand($apiKey, $route, "POST", $params);
  }

  private static function call( $route = '', $type = self::REQUEST_GET, $params = []) {

    return self::call_on_demand(Api::getApiKey() , $route, $type, $params);
  }

  private static function call_on_demand( $apiKey, $route = '', $type = self::REQUEST_GET, $params = []){
    $curlOptions = array();
    $curl = curl_init();

    $curlOptions[CURLOPT_URL] = 'https://api.voucherly.it/v1/'.$route;
    $curlOptions[CURLOPT_RETURNTRANSFER] = true;

    if(self::REQUEST_GET == $type) {
      $curlOptions[CURLOPT_HTTPGET] = true;
    }
    else{
      $curlOptions[CURLOPT_CUSTOMREQUEST] = $type;
      $curlOptions[CURLOPT_POSTFIELDS] = json_encode($params);
      $curlOptions[CURLOPT_POST] = true;
    }

    $curlOptions[CURLOPT_HTTPHEADER] = array(
      'Voucherly-API-Key: '.$apiKey,
      'Voucherly-Platform-Version: '.Api::getPlatformVersionHeader(),
      'Voucherly-Plugin-Version: '.Api::getPluginVersionHeader(),
      'Voucherly-Plugin-Name: '.Api::getPluginNameHeader(),
      'Voucherly-Plugin-Type: '.Api::getTypeHeader(),
      'Content-Type: application/json',
      'User-Agent: VoucherlyApiPhpSdk/'.Api::getVersion(),
    );

    curl_setopt_array($curl, $curlOptions);

    $responseJson = curl_exec($curl);
    $responseStatus = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $curlErrorCode = curl_errno($curl);
    $curlErrorMessage = curl_error($curl);
    curl_close($curl);
    
    if (!empty($curlErrorCode) && !empty($curlErrorMessage)) {
      throw new \Exception($curlErrorMessage, $curlErrorCode);
    }

    $isResponseOk = true;
    if ($responseStatus < 200 || $responseStatus > 299) {
      $isResponseOk = false;
    }

    $responseData = json_decode($responseJson);

    if (!$isResponseOk) {
      // I could check response data

      throw new NotSuccessException($responseStatus);
    }

    return $responseData;
  }
}
