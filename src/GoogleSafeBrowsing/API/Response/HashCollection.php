<?php

require_once(dirname(__FILE__) . '/Base.php');
require_once(dirname(__FILE__) . '/ListHashes.php');

/**
 * GoogleSafeBrowsing API Response HashCollection class
 * 
 * This class stores the result from a API "Full length hash" request
 * 
 * @author Merijn van den Kroonenberg
 * @copyright (c) Copyright 2014 Web2All BV
 * @since 2014-11-13
 */
class GoogleSafeBrowsing_API_Response_HashCollection extends GoogleSafeBrowsing_API_Response_Base {
  
  /**
   * How long to cache this data
   *
   * @var int
   */
  public $cache=0;
  
  /**
   * The data for each list
   *
   * @var GoogleSafeBrowsing_API_Response_ListHashes[]
   */
  public $list_hashes=array();
  
}

?>