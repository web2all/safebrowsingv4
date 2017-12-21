<?php

/**
 * GoogleSafeBrowsing Lookup Storage interface (V4)
 * 
 * This interface describes the methods which any storage object must implement
 * 
 * @author Merijn van den Kroonenberg
 * @copyright (c) Copyright 2017 Web2All BV
 * @since 2017-07-10
 */
interface GoogleSafeBrowsing_Lookup_IStorage {
  
  /**
   * Get all lists and current state from the system
   * 
   * return a assoc array with key the listname and value the state
   * 
   * @return array
   */
  public function getLists();
  
  /**
   * Lookup prefix for url hashes
   * 
   * @param string[] $lookup_hashes
   * @return string[]  hashes with prefix present
   */
  public function hasPrefix($lookup_hashes);
  
  /**
   * Lookup listnames for the given url hash
   * 
   * Only do this for hashes for which a prefix was found!
   * 
   * @param string $lookup_hash
   * @return string[]  list names or null if not cached
   */
  public function isListedInCache($lookup_hash);
  
  /**
   * Add a full hash to the cache
   * 
   * @param string $full_hash
   * @param string[] $lists  list names
   * @param string $meta
   * @param int $cache_seconds
   * @return boolean
   */
  public function addHashInCache($full_hash,$lists,$meta,$cache_seconds);
  
}

?>