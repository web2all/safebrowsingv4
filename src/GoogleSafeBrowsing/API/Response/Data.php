<?php

require_once(dirname(__FILE__) . '/Base.php');
require_once(dirname(__FILE__) . '/ListData.php');

/**
 * GoogleSafeBrowsing API Response Data class
 * 
 * This class stores the result from a API "Data" request
 * 
 * @author Merijn van den Kroonenberg
 * @copyright (c) Copyright 2014 Web2All BV
 * @since 2014-11-13
 */
class GoogleSafeBrowsing_API_Response_Data extends GoogleSafeBrowsing_API_Response_Base {
  
  /**
   * Minimum delay in seconds before next data request
   *
   * @var int
   */
  public $next=0;
  
  /**
   * Should we purge all data
   *
   * @var boolean
   */
  public $reset=false;
  
  /**
   * The data for each list
   *
   * @var GoogleSafeBrowsing_API_Response_ListData[]
   */
  public $listdata=array();
  
}

?>