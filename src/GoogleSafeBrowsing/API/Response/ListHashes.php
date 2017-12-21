<?php

require_once(dirname(__FILE__) . '/Hash.php');

/**
 * GoogleSafeBrowsing API Response ListHashes class
 * 
 * This class stores the result from a API "Full length hash" request
 * 
 * @author Merijn van den Kroonenberg
 * @copyright (c) Copyright 2014 Web2All BV
 * @since 2014-11-13
 */
class GoogleSafeBrowsing_API_Response_ListHashes {

  /**
   * list name
   *
   * @var string
   */
  public $list_name;
  
  /**
   * List of full hashes
   *
   * @var GoogleSafeBrowsing_API_Response_Hash[]
   */
  public $hashes=array();
  
}

?>