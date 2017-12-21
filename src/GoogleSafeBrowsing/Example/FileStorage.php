<?php

require_once(dirname(__FILE__) . '/../Updater/IStorage.php');
require_once(dirname(__FILE__) . '/../Lookup/IStorage.php');

// hex2bin only exists from PHP 5.4 onwards
if ( !function_exists( 'hex2bin' ) ) {
  function hex2bin( $str ) {
    $sbin = "";
    $len = strlen( $str );
    for ( $i = 0; $i < $len; $i += 2 ) {
       $sbin .= pack( "H*", substr( $str, $i, 2 ) );
    }
    return $sbin;
  }
}

/**
 * GoogleSafeBrowsing Updater Storage class
 * 
 * This is a example file based implementation of storage class, this is NOT
 * suitable for production use!
 * 
 * @author Merijn van den Kroonenberg
 * @copyright (c) Copyright 2014-2017 Web2All BV
 * @since 2014-11-20
 */
class GoogleSafeBrowsing_Example_FileStorage implements GoogleSafeBrowsing_Updater_IStorage, GoogleSafeBrowsing_Lookup_IStorage {
  
  /**
   * Will debugLog entries be output
   *
   * @var boolean
   */
  protected $verbose;
  
  /**
   * The base directory where all data is stored (prefixes, state)
   *
   * @var string
   */
  protected $storage_dir;
  
  /**
   * The filename for storing prefixes (in each list dir)
   *
   * @var string
   */
  protected $prefixes_filename;
  
  /**
   * The filename for storing full hashes (in each list dir)
   *
   * @var string
   */
  protected $fullhash_cache_filename;
  
  /**
   * The filename for storing the connection errorcount
   *
   * @var string
   */
  protected $errorcount_filename;
  
  /**
   * The filename for storing the next runtime timestamp
   *
   * @var string
   */
  protected $nextruntime_filename;
  
  /**
   * constructor
   * 
   * @param string $storage_dir
   */
  public function __construct($storage_dir)
  {
    if(substr($storage_dir, -1)!='/'){
      $storage_dir.='/';
    }
    if(!is_dir($storage_dir)){
      mkdir($storage_dir, 0777, true);
    }
    if(!is_writable($storage_dir)){
      throw new Exception('Storage dir "'.$storage_dir.'" not writable!');
    }
    $this->storage_dir=$storage_dir;
    // set filenames
    $this->prefixes_filename='prefixes';
    $this->fullhash_cache_filename='fullcache';
    $this->errorcount_filename='errorcount';
    $this->nextruntime_filename='nextruntime';
    $this->verbose=true;
  }
  
  /**
   * Call this method to disable the default vebose mode
   * 
   * @param boolean $quiet
   */
  public function setQuiet($quiet=true)
  {
    $this->verbose=!$quiet;
  }
  
  /**
   * Gets all hashprefixes for a given (add) chunk number
   * 
   * @param string $list
   * @return string
   */
  protected function getListStorageDir($list)
  {
    return $this->storage_dir.preg_replace('#/#','_',$list).'/';
  }
  
  /**
   * Adds one or more hashprefixes
   * 
   * @param string[] $prefixes
   * @param string $list
   */
  public function addHashPrefixes($prefixes, $list)
  {
    $listdir=$this->getListStorageDir($list);
    if(!is_dir($listdir)){
      mkdir($listdir, 0777, true);
    }
    $new_prefixes=array_map('bin2hex',$prefixes);
    if(is_file($listdir.$this->prefixes_filename)){
      $existing_prefixes=explode("\n",file_get_contents($listdir.$this->prefixes_filename));
      foreach($new_prefixes as $new_prefix){
        $existing_prefixes[]=$new_prefix;
      }
      sort($existing_prefixes,SORT_STRING);
      file_put_contents($listdir.'prefixes', implode("\n",$existing_prefixes));
    }else{
      sort($new_prefixes,SORT_STRING);
      file_put_contents($listdir.'prefixes', implode("\n",$new_prefixes));
    }
    $this->debugLog('addHashPrefixes() added '.count($new_prefixes).' prefixes to list '.$list);
  }
  
  /**
   * Remove one or more hashprefixes
   * 
   * @param string[] $prefixes
   * @param string $list
   */
  public function removeHashPrefixes($prefixes, $list)
  {
    $listdir=$this->getListStorageDir($list);
    if(!is_dir($listdir)){
      return;
    }
    $remove_prefixes=array_map('bin2hex',$prefixes);
    $existing_prefixes=explode("\n",file_get_contents($listdir.$this->prefixes_filename));
    // bail out if no prefixes in file
    if(count($existing_prefixes)==0){
        $this->warningLog('removeHashPrefixes() removing prefixes '.implode(',',$prefixes).' from list '.$list.' but list is empty');
      return;
    }
    $changed=false;
    foreach($remove_prefixes as $prefix){
      $result=array_search($prefix, $existing_prefixes,true);
      if($result===false){
        // not found
        $this->warningLog('removeHashPrefixes() removing prefix '.$prefix.' in list '.$list.' but could not find the prefix');
        continue;
      }
      $this->debugLog('removeHashPrefixes() removed prefix '.$prefix.' from list '.$list);
      unset($existing_prefixes[$result]);
      $changed=true;
    }
    // only update when needed (or we will create files for non existing chunks)
    if($changed){
      file_put_contents($listdir.$this->prefixes_filename, implode("\n",$existing_prefixes));
    }
  }
  
  /**
   * Remove all prefixes of this list
   * 
   * @param string $list
   */
  public function removeHashPrefixesFromList($list)
  {
    $this->debugLog('removeHashPrefixesFromList() list '.$list);
    $listdir=$this->getListStorageDir($list);
    if(!$listdir || strlen($listdir)<10){
      $this->warningLog('removeHashPrefixesFromList() invalid list directory for list '.$list);
      return;
    }
    if(!is_dir($listdir)){
      $this->debugLog('removeHashPrefixesFromList() no list directory for list '.$list);
      return;
    }
    if(!is_file($listdir.$this->prefixes_filename)){
      $this->debugLog('removeHashPrefixesFromList() no prefix file for list '.$list);
      return;
    }
    unlink($listdir.$this->prefixes_filename);
  }
  
  /**
   * Remove one or more hashprefixes
   * 
   * @param int[] $indices
   * @param string $list
   * @return string[]  binary prefixes
   */
  public function getHashPrefixesByIndices($indices, $list)
  {
    $listdir=$this->getListStorageDir($list);
    if(!is_dir($listdir)){
      return array();
    }
    $existing_prefixes=explode("\n",file_get_contents($listdir.$this->prefixes_filename));
    // bail out if no prefixes in file
    if(count($existing_prefixes)==0){
      $this->debugLog('getHashPrefixesByIndices() there are no prefixes in list '.$list);
      return array();
    }
    $result=array();
    foreach($indices as $index){
      if(isset($existing_prefixes[$index])){
        $result[]=hex2bin($existing_prefixes[$index]);
      }else{
        $this->warningLog('getHashPrefixesByIndices() could not find prefix with index '.$index.' in list '.$list);
      }
    }
    return $result;
  }
  
  /**
   * Get all lists and current state from the system
   * 
   * return a assoc array with key the listname and value the state
   * 
   * @return array
   */
  public function getLists()
  {
    if(is_file($this->storage_dir.'lists')){
      return unserialize(file_get_contents($this->storage_dir.'lists'));
    }else{
      return array();
    }
  }
  
  /**
   * Store the updated state for each list
   * 
   * param is a assoc array with key the listname and value the state
   * 
   * @param array $lists
   */
  public function updateLists($lists)
  {
    $this->debugLog('updateLists() called');
    file_put_contents($this->storage_dir.'lists', serialize($lists));
  }
  
  /**
   * Store the nextrun timestamp and errorcount
   * 
   * @param int $timestamp  nextrun timestamp
   * @param int $errorcount  how many consecutive errors did we have (if any)
   */
  public function setUpdaterState($timestamp, $errorcount)
  {
    $this->debugLog('setUpdaterState() called ('.$timestamp.', '.$errorcount.')');
    file_put_contents($this->storage_dir.$this->nextruntime_filename, $timestamp);
    file_put_contents($this->storage_dir.$this->errorcount_filename, $errorcount);
  }
  
  /**
   * Retrieve the nextrun timestamp
   * 
   * @return int
   */
  public function getNextRunTimestamp()
  {
    if(is_file($this->storage_dir.$this->nextruntime_filename)){
      return (int)file_get_contents($this->storage_dir.$this->nextruntime_filename);
    }else{
      return time();
    }
  }
  
  /**
   * Retrieve the amount of consecutive errors (0 if no errors)
   * 
   * @return int
   */
  public function getErrorCount()
  {
    if(is_file($this->storage_dir.$this->errorcount_filename)){
      return (int)file_get_contents($this->storage_dir.$this->errorcount_filename);
    }else{
      return 0;
    }
  }
  
  /**
   * Calculate checksum
   * 
   * @param string $list
   * @param string $algo (sha256)
   * @return string  base64 encoded hash
   */
  public function getListChecksum($list,$algo)
  {
    $listdir=$this->getListStorageDir($list);
    if(!is_dir($listdir)){
      $this->debugLog('getListChecksum() no list directory found for list '.$list);
      return base64_encode(hash($algo,'',true));
    }
    if(!is_file($listdir.$this->prefixes_filename)){
      $this->debugLog('getListChecksum() no prefix file found for list '.$list);
      return base64_encode(hash($algo,'',true));
    }
    $existing_prefixes=explode("\n",file_get_contents($listdir.$this->prefixes_filename));
    // bail out if no prefixes 
    if(count($existing_prefixes)==0){
      $this->debugLog('getListChecksum() no prefixes in list '.$list);
      return base64_encode(hash($algo,'',true));
    }
    if($existing_prefixes[count($existing_prefixes)-1]==''){
      unset($existing_prefixes[count($existing_prefixes)-1]);
    }
    $data='';
    foreach($existing_prefixes as $prefix){
      $data.=hex2bin($prefix);
    }
    return base64_encode(hash($algo,$data,true));
  }
  
  /**
   * Lookup prefix for url hashes
   * 
   * @param string[] $lookup_hashes
   * @return string[]  hashes with prefix present
   */
  public function hasPrefix($lookup_hashes)
  {
    $found_hashes=array();
    // note: this is an example implementation, do not use in production.
    //$this->debugLog('hasPrefix() start lookup');
    // okay, lets optimize something, remove the million bin2hex calls
    $lookup_hashes_hex=array();
    foreach($lookup_hashes as $lookup_hash){
      $lookup_hashes_hex[]=bin2hex($lookup_hash);
    }
    foreach($this->getLists() as $listname => $liststate){
      if(count($lookup_hashes_hex)==0){
        // all found, break away
        break 1;
      }
      // create a copy, so we can remove entries when done for this list,
      // but not for all lists.
      $list_lookup_hashes_hex=$lookup_hashes_hex;
      
      $listdir=$this->getListStorageDir($listname);
      if(!is_dir($listdir)){
        $this->debugLog('hasPrefix() no list directory found for list '.$listname);
        continue;
      }
      if(!is_file($listdir.$this->prefixes_filename)){
        $this->debugLog('hasPrefix() no prefix file found for list '.$listname);
        continue;
      }
      $prefix_fh = fopen($listdir.$this->prefixes_filename, "r");
      if (!$prefix_fh) {
        $this->warningLog('hasPrefix() could not read '.$listdir.$this->prefixes_filename);
        continue;
      }
      
      $still_seeking=true;
      $seek_size=32768;
      
      // seek to the end
      if(fseek($prefix_fh,-$seek_size,SEEK_END)<0){
        // seek failed (most likely past the beginning of the file)
        //$this->debugLog('hasPrefix() seek '.$listname.' seek fail');
        fseek($prefix_fh,0);
        $still_seeking=false;
        $seek_lookup_hashes_hex=$list_lookup_hashes_hex;
      }else{
        //$this->debugLog('hasPrefix() seek '.$listname.' at '.ftell($prefix_fh));
        // find the next newline
        fgets($prefix_fh, 1024);
      }
      
      while (($file_prefix = fgets($prefix_fh, 1024)) !== false) {
        // we have two parts:
        // 1) seek backwards in the file till at least one full hash is bigger than the 
        //    prefix (string compare the size of the prefix). If so, go to (2).
        // 2) try to find only the hashes we found in (1). Either we find them or
        //    we don't, but once all have been found or excluded we go back to (1) and
        //    start seeking backwards again.
        
        // 1) seek 32k blocks
        while($still_seeking){
          $seek_lookup_hashes_hex=array();
          foreach($list_lookup_hashes_hex as $hex_lookup_hash){
            // compare with length-1 so we ignore the newline at end of $file_prefix
            // but the last entry in the file won't have a newline so we have to test :(
            $sub=0;
            if(substr($file_prefix,-1,1)=="\n"){
              $sub=-1;
            }
            // unfortunately strncmp is a pretty heavy operation
            $cmp=strncmp($file_prefix,$hex_lookup_hash,strlen($file_prefix)+$sub);
            if($cmp<=0){
              // ok, we should find this hash in this seek block, or we won't find it at all
              $seek_lookup_hashes_hex[]=$hex_lookup_hash;
              //$this->debugLog('hasPrefix() HIT '.$hex_lookup_hash.'');
            }
          }
          if(count($seek_lookup_hashes_hex)==0){
            // no hashes in this block, so keep seeking backwards
            if(fseek($prefix_fh,-$seek_size,SEEK_CUR)<0){
              // seek failed (most likely past the beginning of the file)
              //$this->debugLog('hasPrefix() seek '.$listname.' seek fail');
              fseek($prefix_fh,0);
              $still_seeking=false;
              $seek_lookup_hashes_hex=$list_lookup_hashes_hex;
            }else{
              // find the next newline
              //$this->debugLog('hasPrefix() seek '.$listname.' at '.ftell($prefix_fh));
              fgets($prefix_fh, 1024);
              if (($file_prefix = fgets($prefix_fh, 1024)) === false) {
                // end of file, thats weird
                $this->warningLog('hasPrefix() unexpected end of file for list '.$listname);
                fseek($prefix_fh,0);
                $still_seeking=false;
                $seek_lookup_hashes_hex=$list_lookup_hashes_hex;
              }
            }
          }else{
            $still_seeking=false;
          }
        }
        
        // 2) find inside the 32k block
        foreach($seek_lookup_hashes_hex as $hex_lookup_hash){
          // compare with length-1 so we ignore the newline at end of $file_prefix
          // but the last entry in the file won't have a newline so we have to test :(
          $sub=0;
          if(substr($file_prefix,-1,1)=="\n"){
            $sub=-1;
          }
          // unfortunately strncmp is a pretty heavy operation
          $cmp=strncmp($file_prefix,$hex_lookup_hash,strlen($file_prefix)+$sub);
          if($cmp==0){
            // match
            $found_hashes[$hex_lookup_hash]=true;
            $found_index=array_search($hex_lookup_hash , $lookup_hashes_hex, true);
            if($found_index!==false){
              // once we have a match for a hash, we do not need to look for any more hits
              // on that hash.
              unset($lookup_hashes_hex[$found_index]);
              if(count($lookup_hashes_hex)==0){
                // all found, break away
                break 2;
              }
            }
          }
          if($cmp>=0){
            // ok we won't find a prefix for this hash in this list (or we just found it)
            $found_index=array_search($hex_lookup_hash , $list_lookup_hashes_hex, true);
            if($found_index!==false){
              //$this->debugLog('hasPrefix() bail out for '.$hex_lookup_hash.' out of '.$listname);
              unset($list_lookup_hashes_hex[$found_index]);
              if(count($list_lookup_hashes_hex)==0){
                // no more to lookup for this list, break away
                break 2;
              }
            }
            $found_index=array_search($hex_lookup_hash , $seek_lookup_hashes_hex, true);
            if($found_index!==false){
              //$this->debugLog('hasPrefix() bail out for seek block '.$hex_lookup_hash.' out of '.$listname);
              unset($seek_lookup_hashes_hex[$found_index]);
              if(count($seek_lookup_hashes_hex)==0){
                // no more to find in this seek block, break away
                $still_seeking=true;
                break 1;
              }
            }
          }
          
        }
      }
      fclose($prefix_fh);
    }
    return array_map('hex2bin',array_keys($found_hashes));
  }
  
  /**
   * Lookup listnames for the given url hash
   * 
   * Only do this for hashes for which a prefix was found!
   * 
   * @param string $lookup_hash
   * @return string[]  list names or null if not cached
   */
  public function isListedInCache($lookup_hash)
  {
    // by default no match in cache
    $matched_listnames=null;
    
    $hex_lookup_hash=bin2hex($lookup_hash);
    
    foreach($this->getLists() as $listname => $liststate){
      $listdir=$this->getListStorageDir($listname);
      if(!is_dir($listdir)){
        $this->debugLog('isListedInCache() no list directory found for list '.$listname);
        continue;
      }
      if(!is_file($listdir.$this->fullhash_cache_filename)){
        //$this->debugLog('isListedInCache() no fullcache file found for list '.$listname);
        // no cache file, nothing to look up
        continue;
      }
      $cached_hashes=explode("\n",file_get_contents($listdir.$this->fullhash_cache_filename));
      foreach($cached_hashes as $cache_entry){
        $cache_parts=explode(';',$cache_entry);
        // 0:prefix;1:fullhash;2:expire_timestamp
        if($cache_parts[0]===substr($hex_lookup_hash,0,strlen($cache_parts[0]))){
          // ok prefix hash match 
          if(is_null($matched_listnames)){
            $matched_listnames=array();
          }
          // test against full hash
          if($cache_parts[1]===$hex_lookup_hash){
            // full hash match
            if($cache_parts[2]<time()){
              // expired full match must result in cache-miss so it gets re-fetched
              return null;
            }
            $matched_listnames[]=$listname;
            // we can skip to the next list
            continue(2);
          }
        }
      }
    }
    
    if(is_null($matched_listnames)){
      // we still have a complete cache miss, as a last resort
      // check if the prefix is in the negative cache file
      if(is_file($this->storage_dir.$this->fullhash_cache_filename)){
        // negative cache file
        $negative_prefixes=explode("\n",file_get_contents($this->storage_dir.$this->fullhash_cache_filename));
        foreach($negative_prefixes as $cache_entry){
          $cache_parts=explode(';',$cache_entry);
          // 0:prefix;1:expire_timestamp
          if($cache_parts[0]===substr($hex_lookup_hash,0,strlen($cache_parts[0]))){
            // ok prefix hash match 
            if(is_null($matched_listnames)){
              $matched_listnames=array();
            }
            if($cache_parts[1]<time()){
              // expired prefix match must result in cache-miss so it gets re-fetched
              return null;
            }
          }
        }
      }
      
    }
    
    return $matched_listnames;
  }
  
  /**
   * Add a full hash to the cache
   * 
   * @param string $full_hash
   * @param string[] $lists  list names
   * @param string $meta
   * @param int $cache_seconds
   * @return boolean
   */
  public function addHashInCache($full_hash,$lists,$meta,$cache_seconds)
  {
    // note: this is an example implementation, no locking is done,
    //       not suitable for concurrent requests
    
    $full_hash_hex=bin2hex($full_hash);
    $prefix_hex=substr($full_hash_hex,0,8);
    
    // first see if its a negative match (no lists are provided)
    if(!$lists){
      if(!is_file($this->storage_dir.$this->fullhash_cache_filename)){
        // negative cache file
        $this->debugLog('addHashInCache() no (negative) fullcache file found, creating it');
        touch($this->storage_dir.$this->fullhash_cache_filename);
      }
      // negative caching is for the entire prefix
      $negative_cached_prefixes=explode("\n",file_get_contents($this->storage_dir.$this->fullhash_cache_filename));
      $negative_cached_prefixes_new=array();
      // find existing and expire old entries
      $existing=false;
      foreach($negative_cached_prefixes as $cache_entry){
        if($cache_entry==''){
          // empty, probably empty file
          continue;
        }
        // 0:prefix;1:expire_timestamp
        $cache_parts=explode(';',$cache_entry);
        
        // is it already in cache?
        if($cache_parts[0]===$prefix_hex){
          // yup, update expiration
          $existing=true;
          $cache_parts[1]=time()+$cache_seconds;
          $negative_cached_prefixes_new[]=implode(';',$cache_parts);
          continue;
        }
        
        // check if entry must be expired
        if($cache_parts[1]<time()){
          continue;
        }
        $negative_cached_prefixes_new[]=$cache_entry;
      }
      // and add the new one if we didn't update existing already
      if(!$existing){
        $negative_cached_prefixes_new[]=implode(';',array($prefix_hex,time()+$cache_seconds));
      }
      file_put_contents($this->storage_dir.$this->fullhash_cache_filename,implode("\n",$negative_cached_prefixes_new));
    }
    
    foreach($lists as $listname){
      $listdir=$this->getListStorageDir($listname);
      if(!is_dir($listdir)){
        $this->debugLog('addHashInCache() no list directory found for list '.$listname);
        continue;
      }
      if(!is_file($listdir.$this->fullhash_cache_filename)){
        $this->debugLog('addHashInCache() no fullcache file found for list '.$listname.' creating it');
        touch($listdir.$this->fullhash_cache_filename);
      }
      
      $cached_hashes=explode("\n",file_get_contents($listdir.$this->fullhash_cache_filename));
      $cached_hashes_new=array();
      // find existing and expire old entries
      $existing=false;
      foreach($cached_hashes as $cache_entry){
        if($cache_entry==''){
          // empty, probably empty file
          continue;
        }
        // 0:prefix;1:fullhash;2:expire_timestamp
        $cache_parts=explode(';',$cache_entry);
        
        // is it already in cache?
        if($cache_parts[1]===$full_hash_hex){
          // yup, update expiration
          $existing=true;
          $cache_parts[2]=time()+$cache_seconds;
          $cached_hashes_new[]=implode(';',$cache_parts);
          continue;
        }
        
        // check if entry must be expired (but wait an hour before removing expired entries)
        if($cache_parts[2]<(time()+3600)){
          continue;
        }
        $cached_hashes_new[]=$cache_entry;
      }
      // and add the new one if we didn't update existing already
      if(!$existing){
        $cached_hashes_new[]=implode(';',array($prefix_hex,$full_hash_hex,time()+$cache_seconds));
      }
      file_put_contents($listdir.$this->fullhash_cache_filename,implode("\n",$cached_hashes_new));
    }
    return true;
  }
  
  /**
   * Log message (debug/informational)
   * 
   * @param string $message
   */
  protected function debugLog($message)
  {
    if($this->verbose){
      echo date("Y-m-d H:i:s ").'FileStorage '.$message."\n";
    }
  }
  
  /**
   * Log message (warning/error)
   * 
   * @param string $message
   */
  protected function warningLog($message)
  {
    echo date("Y-m-d H:i:s ").'FileStorage '.$message."\n";
  }
  
}

?>