<?php

require_once(dirname(__FILE__) . '/Lookup/URL.php');

/**
 * GoogleSafeBrowsing Lookup Client
 * 
 * Class for looking up urls in the google safebrowsing lists
 * 
 * @author Merijn van den Kroonenberg
 * @copyright (c) Copyright 2015-2017 Web2All BV
 * @since 2015-01-05
 */
class GoogleSafeBrowsing_Lookup {
  
  /**
   * API object
   *
   * @var GoogleSafeBrowsing_API
   */
  protected $api;
  
  /**
   * Storage engine object
   *
   * @var GoogleSafeBrowsing_Lookup_IStorage
   */
  protected $storage;
  
  /**
   * If verbose is true then debuglogging is on
   *
   * @var boolean
   */
  protected $verbose;
  
  /**
   * constructor
   * 
   * @param GoogleSafeBrowsing_API $api
   * @param GoogleSafeBrowsing_Lookup_IStorage $storage  storage engine
   */
  public function __construct($api, $storage)
  {
    $this->verbose=false;
    
    $this->api=$api;
    
    $this->storage=$storage;
  }
  
  /**
   * Lookup an url
   * 
   * @param string $url
   * @return string[]
   */
  public function lookup($url)
  {
    $result = array();
    // get url's to check
    $canon_url = GoogleSafeBrowsing_Lookup_URL::googleCanonicalize($url);
    if(is_null($canon_url)){
      // unable to canonicalize, nothing we can do
      $this->debugLog('lookup('.$url.'): cannot canonicalize url, skipping');
      return $result;
    }
    $lookup_urls = GoogleSafeBrowsing_Lookup_URL::googleGetLookupExpressions($canon_url);
    
    $lookup_hashes = array();
    foreach($lookup_urls as $lookup_url){
      $lookup_hashes[]=GoogleSafeBrowsing_Lookup_URL::hash($lookup_url);
      //$this->debugLog(bin2hex($lookup_hashes[count($lookup_hashes)-1])." $lookup_url");
    }
    
    $found_hashes=$this->storage->hasPrefix($lookup_hashes);
    
    $matched_lists=array();
    // full hash lookups
    foreach($found_hashes as $found_hash){
      $this->debugLog('lookup('.$url.'): found hash prefix for '.bin2hex($found_hash));
      $lookup_result=$this->storage->isListedInCache($found_hash);
      if(is_null($lookup_result)){
        $lookup_result=array();
        // not cached, query google
        $collection=$this->api->getHash(substr($found_hash,0,4),$this->storage->getLists());
        if(count($collection->list_hashes)==0){
          // no hashes found, can happen, so insert dummy record for this prefix
          // so we can use the cached result (empty)
          $this->storage->addHashInCache($found_hash,$lookup_result,null,(int)$collection->cache);
        }else{
          // full length hashes found
          foreach($collection->list_hashes as $list_hash){
            $this->debugLog('lookup('.$url.'): remote lookup found full length hashes in list '.$list_hash->list_name);
            foreach($list_hash->hashes as $hash){
              $this->debugLog('lookup('.$url.'):   hash '.bin2hex($hash->hash));
              // cache in db
              $cache_seconds=(int)$collection->cache;
              if(isset($hash->cache) && $hash->cache){
                $cache_seconds=(int)$hash->cache;
              }
              $this->storage->addHashInCache($hash->hash,array($list_hash->list_name),$hash->raw_meta,$cache_seconds);
              if($hash->hash==$found_hash){
                // full length hash match
                if(!in_array($list_hash->list_name, $lookup_result)){
                  $lookup_result[]=$list_hash->list_name;
                }
              }
            }
          }
        }
      }// end cached if/else
      // $lookup_result is array of listnames (can be empty if not listed)
      foreach($lookup_result as $matched_list){
        $matched_lists[$matched_list]=true;
      }
    }
    if(count($matched_lists)>0){
      $this->debugLog('lookup('.$url.'): listed in '.implode(', ',array_keys($matched_lists)));
    }
    return array_keys($matched_lists);
  }
  
  /**
   * Set the loglevel (0 is off, 1 is on)
   * 
   * @param int $level
   */
  public function setLogLevel($level)
  {
    if($level>0){
      $this->verbose=true;
    }else{
      $this->verbose=false;
    }
  }
  
  /**
   * Log message
   * 
   * @param string $message
   */
  protected function debugLog($message)
  {
    if($this->verbose){
      echo date("Y-m-d H:i:s ").'Lookup_Client '.$message."\n";
    }
  }
  
}

?>