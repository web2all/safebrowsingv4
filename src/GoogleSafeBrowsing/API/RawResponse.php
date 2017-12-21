<?php

/**
 * GoogleSafeBrowsing API RawResponse class
 * 
 * This class stores a http response from the API
 * 
 * @author Merijn van den Kroonenberg
 * @copyright (c) Copyright 2014 Web2All BV
 * @since 2014-11-13
 */
class GoogleSafeBrowsing_API_RawResponse {

  /**
   * HTTP Response code
   *
   * @var int
   */
  public $code;
  
  /**
   * Raw data
   *
   * @var string
   */
  public $data;
  
}

?>