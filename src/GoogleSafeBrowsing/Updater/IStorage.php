<?php

/**
 * GoogleSafeBrowsing Updater Storage interface (V4)
 * 
 * This interface describes the methods which any storage object must implement
 * 
 * @author Merijn van den Kroonenberg
 * @copyright (c) Copyright 2014-2017 Web2All BV
 * @since 2014-11-19
 */
interface GoogleSafeBrowsing_Updater_IStorage {
  
  /**
   * Adds one or more hashprefixes
   * 
   * @param string[] $prefixes
   * @param string $list
   */
  public function addHashPrefixes($prefixes, $list);
  
  /**
   * Remove one or more hashprefixes
   * 
   * @param string[] $prefixes
   * @param string $list
   */
  public function removeHashPrefixes($prefixes, $list);
  
  /**
   * Remove all prefixes of this list
   * 
   * @param string $list
   */
  public function removeHashPrefixesFromList($list);
  
  /**
   * Remove one or more hashprefixes
   * 
   * @param int[] $indices
   * @param string $list
   * @return string[]  binary prefixes
   */
  public function getHashPrefixesByIndices($indices, $list);
  
  /**
   * Get all lists and current state from the system
   * 
   * return a assoc array with key the listname and value the state
   * 
   * @return array
   */
  public function getLists();
  
  /**
   * Store the updated state for each list
   * 
   * param is a assoc array with key the listname and value the state
   * 
   * @param array $lists
   */
  public function updateLists($lists);
  
  /**
   * Store the nextrun timestamp and errorcount
   * 
   * @param int $timestamp  nextrun timestamp
   * @param int $errorcount  how many consecutive errors did we have (if any)
   */
  public function setUpdaterState($timestamp, $errorcount);
  
  /**
   * Retrieve the nextrun timestamp
   * 
   * @return int
   */
  public function getNextRunTimestamp();
  
  /**
   * Retrieve the amount of consecutive errors (0 if no errors)
   * 
   * @return int
   */
  public function getErrorCount();
  
  /**
   * Calculate checksum
   * 
   * @param string $list
   * @param string $algo (sha256)
   * @return string  base64 encoded hash
   */
  public function getListChecksum($list,$algo);
}

?>