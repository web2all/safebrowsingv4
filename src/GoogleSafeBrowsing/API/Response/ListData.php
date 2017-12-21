<?php

/**
 * GoogleSafeBrowsing API Response ListData class
 * 
 * This class stores the result from a API "Data" request
 * 
 * @author Merijn van den Kroonenberg
 * @copyright (c) Copyright 2014-2017 Web2All BV
 * @since 2014-11-13
 */
class GoogleSafeBrowsing_API_Response_ListData {

  /**
   * list name
   *
   * @var string
   */
  public $list_name;
  
  /**
   * newClientState
   *
   * @var string
   */
  public $newClientState;
  
  /**
   * Assoc array with key the hashing algo and value the checksum
   *
   * @var array
   */
  public $checksum;
  
  /**
   * List of hash prefixes to remove
   *
   * @var GoogleSafeBrowsing_API_Response_ThreatEntrySet[]
   */
  public $additions=array();
  
  /**
   * List of hash prefixes to add
   *
   * @var GoogleSafeBrowsing_API_Response_ThreatEntrySet[]
   */
  public $removals=array();
  
  /**
   * Should we purge all data
   *
   * @var boolean
   */
  public $reset=false;
  
}

?>