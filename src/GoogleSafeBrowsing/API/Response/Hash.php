<?php

/**
 * GoogleSafeBrowsing API Response Hash class
 * 
 * This class stores the result from a API "Full length hash" request
 * 
 * @author Merijn van den Kroonenberg
 * @copyright (c) Copyright 2014 Web2All BV
 * @since 2014-11-13
 */
class GoogleSafeBrowsing_API_Response_Hash {

  /**
   * Hash meta type
   * 0 = unknown
   * 1 = landing
   * 2 = distribution
   *
   * @var int
   */
  public $type;
  
  /**
   * How long to cache this data
   *
   * @var int
   */
  public $cache;
  
  /**
   * raw meta data binary
   *
   * @var string
   */
  public $raw_meta;
  
  /**
   * The hash
   *
   * @var string
   */
  public $hash;
  
}

?>