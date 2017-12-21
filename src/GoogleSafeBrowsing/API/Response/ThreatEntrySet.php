<?php

/**
 * GoogleSafeBrowsing API Response ThreatEntrySet class
 * 
 * This class stores the result from a API "Data" request
 * We only support RAW compressionType at the moment!
 * 
 * @author Merijn van den Kroonenberg
 * @copyright (c) Copyright 2017 Web2All BV
 * @since 2017-07-06
 */
class GoogleSafeBrowsing_API_Response_ThreatEntrySet {

  /**
   * Size in bytes of prefix (only used with rawHashes)
   *
   * @var int
   */
  public $hashSize;
  
  /**
   * List of hash prefixes to remove
   *
   * @var string[]
   */
  public $rawHashes=array();
  
  /**
   * List of hash prefixes to add
   *
   * @var int[]
   */
  public $rawIndices=array();
  
}

?>