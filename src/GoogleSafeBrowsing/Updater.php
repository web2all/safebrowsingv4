<?php

require_once(dirname(__FILE__) . '/API.php');
require_once(dirname(__FILE__) . '/Updater/IStorage.php');

/**
 * GoogleSafeBrowsing Updater class
 * 
 * This class will keep uptodate a local copy of the google safe browsing url hash database.
 * How the data is actually stored is handled by a storage class, which must implement 
 * the GoogleSafeBrowsing_Updater_IStorage interface.
 * 
 * This class is intended to be run as a daemon and will update the hash prefix database 
 * according to google's requirements.
 * 
 * See: https://developers.google.com/safe-browsing/v4/update-api
 * 
 * @author Merijn van den Kroonenberg
 * @copyright (c) Copyright 2014-2017 Web2All BV
 * @since 2014-11-14
 */
class GoogleSafeBrowsing_Updater {
  
  /**
   * API object
   *
   * @var GoogleSafeBrowsing_API
   */
  protected $api;
  
  /**
   * Storage engine object
   *
   * @var GoogleSafeBrowsing_Updater_IStorage
   */
  protected $storage;
  
  /**
   * Lists state
   * assoc array with key the listname and value the state
   * 
   * @var string[]
   */
  protected $lists;
  
  /**
   * When can we do the next request
   * 
   * @var int
   */
  protected $next_request_timestamp;
  
  /**
   * When did we start?
   *
   * @var int
   */
  protected $start_timestamp;
  
  /**
   * When must we end?
   *
   * @var int
   */
  protected $end_timestamp;
  
  /**
   * How many seconds may we run?
   * 0 is unlimited
   * Set before calling run()
   *
   * @var int
   */
  public $max_run_time=0;
  
  /**
   * How many consecutive errors did we get (if > 0 then we are in backoff mode)
   *
   * @var int
   */
  protected $error_count=0;
  
  /**
   * If pcntl is enabled we handle SIGTERM and SIGHUP to exit the daemon
   *
   * @var boolean
   */
  protected $exit_signal_received=false;
  
  /**
   * True if pcntl module is enabled
   *
   * @var boolean
   */
  protected $pcntl_enabled=false;
  
  /**
   * PHP < 5.3 does not have pcntl_signal_dispatch() function, emulate it
   *
   * @var boolean
   */
  protected $pcntl_emulate_dispatch=false;
  
  /**
   * constructor
   * 
   * @param string $api_key
   * @param GoogleSafeBrowsing_Updater_IStorage $storage  storage engine
   */
  public function __construct($api_key, $storage)
  {
    $this->api=new GoogleSafeBrowsing_API($api_key);
    if(!($storage instanceof GoogleSafeBrowsing_Updater_IStorage)){
      throw new Exception('Invalid storage engine');
    }
    $this->storage=$storage;
    
    $this->initializeFromStorage();
  }
  
  /**
   * Initializes object state from storage backend
   * 
   */
  protected function initializeFromStorage()
  {
    $this->debugLog('initializeFromStorage()');
    $this->lists=$this->storage->getLists();
    $this->next_request_timestamp=$this->storage->getNextRunTimestamp();
    $this->error_count=$this->storage->getErrorCount();
    
    $this->debugLog('current memory usage: '.memory_get_usage());
    $this->debugLog('peak memory usage   : '.memory_get_peak_usage());
  }
  
  /**
   * stores object state to storage backend
   * 
   */
  protected function serializeToStorage()
  {
    $this->debugLog('serializeToStorage()');
    $this->storage->updateLists($this->lists);
    $this->storage->setUpdaterState($this->next_request_timestamp, $this->error_count);
    
    $this->debugLog('current memory usage: '.memory_get_usage());
    $this->debugLog('peak memory usage   : '.memory_get_peak_usage());
  }
  
  /**
   * Set the current lists
   * 
   * @param array $lists
   */
  public function setLists($lists)
  {
    $this->lists=$lists;
    // update the storage, else we are out of sync
    $this->storage->updateLists($this->lists);
  }
  
  /**
   * Get the current lists
   * 
   * @return array
   */
  public function getLists()
  {
    return $this->lists;
  }
  
  /**
   * Backoff behaviour
   * 
   */
  protected function handleBackoffError()
  {
    $this->error_count++;
    if($this->error_count==1){
      // first error, retry after one minute
      $this->next_request_timestamp=time()+60;
    }elseif($this->error_count<6){
      // every error (after the first) we must wait double the time (starting with 30-60 minutes)
      $this->next_request_timestamp=time()+(mt_rand(1800,3600) * ($this->error_count-1));
    }else{
      // more than 5 consecutive errors: always wait 480 minutes
      $this->next_request_timestamp=time()+28800;
    }
  }
  
  /**
   * Process data from Data API request
   * 
   * @param GoogleSafeBrowsing_API_Response_Data $data
   */
  protected function processData($data)
  {
    // set the next_request_timestamp $data->next seconds in the future
    $this->next_request_timestamp=time()+$data->next;
    // check if we have to purge all data
    if($data->reset){
      // purge all data
      $this->debugLog('processData: purging all data');
      // delete all stored prefixes
      // all lists must have no state
      foreach($this->lists as $list => $state){
        $this->lists[$list]='';
        $this->storage->removeHashPrefixesFromList($list);
      }
      $this->storage->updateLists($this->lists);
      // todo: purge cached full hashes
      return;
    }
    foreach($data->listdata as $listdata){
      // for each list
      
      // check if we have to purge all list data
      if($listdata->reset){
        // purge all data
        $this->debugLog('processData: purging data of list '.$listdata->list_name);
        // delete all stored prefixes
        // all list must have no state
        $this->lists[$listdata->list_name]='';
        $this->storage->removeHashPrefixesFromList($listdata->list_name);
      }
      
      $this->lists[$listdata->list_name]=$listdata->newClientState;
      
      // update the lists in the local database (applying the removals before the additions) 
      // https://developers.google.com/safe-browsing/v4/local-databases#database-updates
      
      // fetch removal ThreatEntrySet's
      foreach($listdata->removals as $entry){
        if(isset($entry->rawHashes) && count($entry->rawHashes)>0){ 
          // remove by hash
          $this->storage->removeHashPrefixes($entry->rawHashes,$listdata->list_name);
        }elseif(isset($entry->rawIndices) && count($entry->rawIndices)>0){
          // remove by indice
          $hashes=$this->storage->getHashPrefixesByIndices($entry->rawIndices,$listdata->list_name);
          $this->storage->removeHashPrefixes($hashes,$listdata->list_name);
        }else{
          // nothing??
          $this->debugLog('processData: removal ThreatEntrySet without data!');
        }
      }
      
      // fetch addition ThreatEntrySet's
      foreach($listdata->additions as $entry){
        if(isset($entry->rawHashes) && count($entry->rawHashes)>0){ 
          // remove by hash
          $this->storage->addHashPrefixes($entry->rawHashes,$listdata->list_name);
        }else{
          // nothing??
          $this->debugLog('processData: removal ThreatEntrySet without data!');
        }
      }
      
      if(isset($listdata->checksum)){
        if(isset($listdata->checksum['sha256'])){
          $this->debugLog('processData: checksum for list '.$listdata->list_name.' should be '.$listdata->checksum['sha256']);
          $data_checksum=$this->storage->getListChecksum($listdata->list_name,'sha256');
          if($listdata->checksum['sha256'] != $data_checksum){
            $this->debugLog('processData: checksum for list '.$listdata->list_name.' does not match expected checksum '.$listdata->checksum['sha256'].' != '.$data_checksum);
            // checksum mismatch, so reset the list (next time get the full one)
            $this->lists[$listdata->list_name]='';
            $this->debugLog('processData: Reset list state for '.$listdata->list_name);
          }
        }
      }
      
      // check if we need to bail out
      if($this->pcntl_enabled){
        // process signals with handlers if we received any
        $this->pcntlSignalDispatch();
        if($this->exit_signal_received){
          // signal received, stop processing
          break;
        }
      }
    }// end list loop
    $this->storage->updateLists($this->lists);
  }
  
  /**
   * Run the updater
   * 
   */
  public function run()
  {
    $this->debugLog('run() called');
    // find out how long the daemon may run before it must exit
    $this->start_timestamp=time();
    if($this->max_run_time){
      $this->end_timestamp=$this->start_timestamp+$this->max_run_time;
    }else{
      $this->end_timestamp=0;
    }
    
    $this->pcntlSignalSetup();
    
    foreach($this->lists as $list => $state){
      $this->debugLog('List checksum of list '.$list.' is '.$this->storage->getListChecksum($list,'sha256'));
    }
    
    // if the next_request_timestamp is not in the future, then we add
    // 0-1 minutes to comply with google policy:
    // https://developers.google.com/safe-browsing/v4/request-frequency
    if($this->next_request_timestamp<=$this->start_timestamp){
      $this->next_request_timestamp=time()+mt_rand(1,60);
    }
    
    // start daemon loop
    while(!$this->exit_signal_received && ($this->end_timestamp==0 || time()<$this->end_timestamp)){
      // do our stuff
      // check if we may do the next request
      if(time()>=$this->next_request_timestamp){
        // yes, we may do the next request
        /* GoogleSafeBrowsing_API_Response_Data $data */
        $data=$this->api->getData($this->lists);
        
        if($data->abort){
          // something is wrong, we cannot recover this automatically
          $this->debugLog('API error (abort) code '.$this->api->getLastErrorCode().': '.$this->api->getLastErrorString());
          // store current state (to be sure)
          $this->serializeToStorage();
          // and die
          throw new Exception('Unrecoverable error on google safe browsing API');
        }
        if($data->backoff){
          // temporal error, enter backoff mode
          $this->debugLog('API error (backoff) code '.$this->api->getLastErrorCode().': '.$this->api->getLastErrorString());
          if($this->api->getLastErrorCode()<1000){
            // error < 1000 means its a curl error and not backoff mode dictated by google. So lets send notification.
            trigger_error('API error (backoff) code '.$this->api->getLastErrorCode().': '.$this->api->getLastErrorString(),E_USER_NOTICE);
          }
          $this->debugLog('entering backoff mode with errorcount '.$this->error_count);
          $this->handleBackoffError();
          // store current state (to be sure)
          $this->serializeToStorage();
          continue;
        }
        $this->error_count=0;
        // for each data request we have to fetch the redirs
        $this->processData($data);
        // as we have to wait (most likely), lets store our state in the meanwhile
        $this->serializeToStorage();
      }else{
        // no not yet, lets wait (sleep)
        $sleep_till=$this->next_request_timestamp;
        if($this->end_timestamp!=0 && $sleep_till>$this->end_timestamp){
          $this->debugLog('next request timestamp is after our end time, so only sleep till our end time');
          $sleep_till=$this->end_timestamp;
        }
        $this->beforeSleep($sleep_till-time());
        $this->debugLog('sleeping '.($sleep_till-time()).' seconds');
        sleep($sleep_till-time());
        if($this->pcntl_enabled){
          // process signals with handlers if we received any
          $this->pcntlSignalDispatch();
          if($this->exit_signal_received){
            // signal received, stop processing
            break;
          }
        }
      }
    }// end daemon loop
    
    // store current state
    $this->serializeToStorage();
    
    // exit daemon
  }
  
  /**
   * Called before the daemon sleeps
   * 
   * @param int $duration  how long are we gonna sleep
   */
  protected function beforeSleep($duration)
  {
    // you can override in extending class
  }
  
  /**
   * Log message
   * 
   * @param string $message
   */
  protected function debugLog($message)
  {
    echo date("Y-m-d H:i:s ").'Updater '.$message."\n";
  }
  
  /**
   * Signal handle which will exit our daemon loop
   * 
   * @param int $signo
   */
  public function exitSignalHandler($signo)
  {
    $this->debugLog('exitSignalHandler: signal '.$signo.' received, exit daemon loop');
    $this->exit_signal_received=true;
  }
  
  /**
   * Set up signal handling if supported
   * 
   * Do NOT call from constructor!
   */
  protected function pcntlSignalSetup()
  { 
    if (function_exists('pcntl_signal')) {
      pcntl_signal(SIGHUP, array($this,'exitSignalHandler') );// kill -HUP
      pcntl_signal(SIGTERM, array($this,'exitSignalHandler') );// kill
      pcntl_signal(SIGINT, array($this,'exitSignalHandler') );// CTRL-C
      $this->pcntl_enabled=true;
      // pre 5.3 fallback
      if (!function_exists('pcntl_signal_dispatch')) {
        $this->pcntl_emulate_dispatch=true;
      }
      $this->debugLog('pcntlSignalSetup: signal handling enabled');
    }
  }
  
  /**
   * Handle signals
   * 
   */
  protected function pcntlSignalDispatch()
  {
    if($this->pcntl_emulate_dispatch){
      $dummy=0;
      declare(ticks=1) {
        // dummy to trigger tick
        $dummy++;
      }
    }else{
      pcntl_signal_dispatch();
    }
  }
  
}

?>