<?php

require_once(dirname(__FILE__) . '/API/RawResponse.php');
require_once(dirname(__FILE__) . '/API/Response/Data.php');
require_once(dirname(__FILE__) . '/API/Response/ThreatEntrySet.php');
require_once(dirname(__FILE__) . '/API/Response/HashCollection.php');

/**
 * GoogleSafeBrowsing API class
 * 
 * This class is used to query the google's safe brwosing API.
 * current version API 4.0
 * 
 * See: https://developers.google.com/safe-browsing/v4/update-api
 * 
 * @author Merijn van den Kroonenberg
 * @copyright (c) Copyright 2014-2017 Web2All BV
 * @since 2014-11-13
 */
class GoogleSafeBrowsing_API {

  /**
   * Your api key
   *
   * @var string
   */
  protected $api_key;
  
  /**
   * Base url
   *
   * @var string
   */
  protected $base_api_url;
  
  /**
   * Which google safe browsing api version is supported
   *
   * @var string
   */
  protected $protocol_version;
  
  /**
   * the version of our client
   *
   * @var string
   */
  protected $our_version;
  
  /**
   * the version of our client
   *
   * @var string
   */
  protected $our_id;
  
  /**
   * The errorcode of the last request, if any
   * 1 - 1000 curl errors
   * 1000+ our custom errorcodes
   *
   * @var int
   */
  protected $last_errorcode;
  
  /**
   * The error of the last request, if any
   *
   * @var string
   */
  protected $last_errorstring;
  
  /**
   * constructor
   * 
   * @param string $api_key
   */
  public function __construct($api_key)
  {
    $this->api_key=$api_key;
    $this->base_api_url='https://safebrowsing.googleapis.com/v4/';
    //$this->base_api_url='https://test-safebrowsing.sandbox.googleapis.com/v4/'; // TEST
    //$this->base_api_url='https://staging-safebrowsing.sandbox.googleapis.com/v4/'; // STAGING
    $this->protocol_version="4.0";
    $this->our_version='2.0';
    $this->our_id='web2all-php';
  }
  
  /**
   * Build the url to google api
   * 
   * @param string $command
   * @return string
   */
  protected function buildAPIURL($command)
  {
    return $this->base_api_url.$command.'?key='.urlencode($this->api_key);
  }
  
  /**
   * make a string (list name) of list identifiers
   * 
   * @param array $listdata
   * @return string
   */
  protected function buildListName($listdata)
  {
    return $listdata['threatType'].'/'.$listdata['platformType'].'/'.$listdata['threatEntryType'];
  }
  
  /**
   * get array of list identifiers based on list name
   * 
   * @param string $listname
   * @return array
   */
  protected function deconstructListName($listname)
  {
    $nameparts=explode('/',$listname);
    $listdata=array();
    $listdata['threatType']=$nameparts[0];
    $listdata['platformType']=$nameparts[1];
    $listdata['threatEntryType']=$nameparts[2];
    return $listdata;
  }
  
  /**
   * Get a time in (whole) seconds
   * 
   * @param string $timespan_secs
   * @return int
   */
  protected function stringToSeconds($timespan_secs)
  {
    $seconds=(int)preg_replace('/^(\d+)\.\d*s$/','$1',$timespan_secs);
    // because we ignore decimals, add one sec to be on the safe side
    $seconds++;
    if($seconds==1){
      $seconds+=60;
      error_log('unparsable timespan: '.$timespan_secs);
    }
    return $seconds;
  }
  
  /**
   * return the data for the 'client' element in the request
   * 
   * @return array
   */
  protected function getClientRequestData()
  {
    return array(
      'clientId' => $this->our_id,
      'clientVersion' => $this->our_version
    );
  }
  
  /**
   * Get the error code of the last api request, or 0 if no error
   * 
   * 1 - 1000 curl errors
   * 1000+ our custom errorcodes (subtract 1000 to get the http statuscode)
   * 
   * @return int
   */
  public function getLastErrorCode()
  {
    return $this->last_errorcode;
  }
  
  /**
   * Get the error of the last api request, or empty if no error
   * 
   * @return string
   */
  public function getLastErrorString()
  {
    return $this->last_errorstring;
  }
  
  /**
   * post to google api
   * 
   * @param string $url
   * @param string $data  post data
   * @return GoogleSafeBrowsing_API_RawResponse
   */
  protected function postAPI($url, $data='',$post=true)
  {
    $curl = curl_init();
    
    curl_setopt( $curl, CURLOPT_URL, $url );
    curl_setopt( $curl, CURLOPT_POST, $post );
    curl_setopt( $curl, CURLOPT_RETURNTRANSFER, true );
    curl_setopt( $curl, CURLOPT_BINARYTRANSFER, true );// not needed from PHP 5.2 onwards
    if($data){
      curl_setopt( $curl, CURLOPT_POSTFIELDS, $data );
    }
    curl_setopt( $curl, CURLOPT_HTTPHEADER, array('Content-Type: application/json') );
    
    $content = curl_exec( $curl );
    if($this->last_errorcode = curl_errno($curl)) {
      // curl errors are 1-89, but some might be added in future
      $this->last_errorstring = curl_error($curl);
    }else{
      $this->last_errorstring = '';
      $this->last_errorcode = 0;
    }
    $response = curl_getinfo( $curl );
    curl_close( $curl );
    $rawresponse= new GoogleSafeBrowsing_API_RawResponse();
    $rawresponse->code=$response['http_code'];
    $rawresponse->data=$content;
    return $rawresponse;
  }
  
  /**
   * Get the available lists
   * 
   * @return string[]
   */
  public function getLists()
  {
    $response = $this->postAPI($this->buildAPIURL('threatLists'), '',false);
    // handle response_code
    //error_log($response->code.' '.$response->data);
    $result = json_decode($response->data,true);
    $lists=$result['threatLists'];
    $lists_stringified=array();
    foreach($lists as $listdata){
      $lists_stringified[]=$this->buildListName($listdata);
    }
    return $lists_stringified;
  }
  
  /**
   * Get updates for lists
   * 
   * @param array $lists  assoc array with key listname and value the list state
   * @return GoogleSafeBrowsing_API_Response_Data
   */
  public function getData($lists)
  {
    if(count($lists)==0){
      $data = new GoogleSafeBrowsing_API_Response_Data();
      $data->abort=true;
      return $data;
    }
    $list_updates=array();
    foreach($lists as $listname => $liststate){
      $list_upd=$this->deconstructListName($listname);
      $list_upd['state']=$liststate;
      $list_updates[]=$list_upd;
    }
    $body=json_encode(array(
      'client'             => $this->getClientRequestData(),
      'listUpdateRequests' => $list_updates
    ));
    $response = $this->postAPI($this->buildAPIURL('threatListUpdates:fetch'), $body);
    
    // set up data response
    $data = new GoogleSafeBrowsing_API_Response_Data();
    
    // handle response_code
    switch($response->code){
      case 200:
        // ok!
        break;
      case 400:
        // Bad Request—The HTTP request was not correctly formed. The client did not provide all required CGI parameters or the body did not contain any meaningful entries.
        print_r($body);
        print_r($response);
        $this->last_errorcode = 1400;
        $this->last_errorstring = 'Bad Request—The HTTP request was not correctly formed. The client did not provide all required CGI parameters or the body did not contain any meaningful entries.';
        $data->abort = true;
        return $data;
      case 403:
        // Forbidden—The client id or API key is invalid or unauthorized.
        // at some point google would issue a 403 when requesting a deleted/removed list
        //print_r(array($this->buildAPIURL('threatListUpdates:fetch'), $body));
        print_r($response);
        $this->last_errorcode = 1403;
        $this->last_errorstring = 'Forbidden—The client id or API key is invalid or unauthorized.';
        $data->abort = true;
        return $data;
      case 404:
        // not found, something is seriously buggered
        print_r($response);
        $this->last_errorcode = 1404;
        $this->last_errorstring = 'File not found: API address gone.';
        $data->abort = true;
        return $data;
      case 503:
        // Service Unavailable—The server cannot handle the request. Clients MUST follow the backoff behavior specified in the Request Frequency section
        //print_r($response);
        $this->last_errorcode = 1503;
        $this->last_errorstring = 'Service Unavailable—The server cannot handle the request. Clients MUST follow the backoff behavior specified in the Request Frequency section';
        $data->backoff = true;
        return $data;
      case 505:
        // HTTP Version Not Supported—The server CANNOT handle the requested protocol major version.
        //print_r($response);
        $this->last_errorcode = 1505;
        $this->last_errorstring = 'HTTP Version Not Supported—The server CANNOT handle the requested protocol major version.';
        $data->abort = true;
        return $data;
      default:
        // unknown response code
        // could be a network error
        // lets handle as if backoff mode
        print_r($response);
        $data->backoff = true;
        return $data;
    }
    
    // parse data, see https://developers.google.com/safe-browsing/v4/reference/rest/v4/threatListUpdates/fetch#response-body
    $response_decoded=json_decode($response->data,true);
    
    foreach($response_decoded['listUpdateResponses'] as $list_response){
      $list_data=new GoogleSafeBrowsing_API_Response_ListData();
      $list_data->list_name=$this->buildListName($list_response);
      if($list_response['responseType']=='FULL_UPDATE'){
        $list_data->reset=true;
      }
      $list_data->newClientState=$list_response['newClientState'];
      
      if(isset($list_response['checksum'])){
        $list_data->checksum=$list_response['checksum'];
      }
      
      // test if anything changes
      //if(!isset($list_response['additions']) && !isset($list_response['removals'])){
      //  // no changes in list
      //}
      
      // additions
      if(isset($list_response['additions'])){
        foreach($list_response['additions'] as $addition){
          $entry=new GoogleSafeBrowsing_API_Response_ThreatEntrySet();
          if($addition['compressionType']!='RAW'){
            // not yet supported
            error_log('unsupported compressionType: '.$addition['compressionType']);
            continue;
          }
          if(isset($addition['rawHashes'])){
            $binary_concat_prefixes=base64_decode($addition['rawHashes']['rawHashes']);
            $entry->rawHashes=str_split($binary_concat_prefixes, $addition['rawHashes']['prefixSize']);
            $entry->hashSize=$addition['rawHashes']['prefixSize'];
          }
          $list_data->additions[]=$entry;
        }
      }
      // deletions
      if(isset($list_response['removals'])){
        foreach($list_response['removals'] as $removal){
          $entry=new GoogleSafeBrowsing_API_Response_ThreatEntrySet();
          if($removal['compressionType']!='RAW'){
            // not yet supported
            error_log('unsupported compressionType: '.$removal['compressionType']);
            continue;
          }
          if(isset($removal['rawIndices'])){
            $entry->rawIndices=$removal['rawIndices']['indices'];
          }elseif(isset($removal['rawHashes'])){
            $binary_concat_prefixes=base64_decode($removal['rawHashes']['rawHashes']);
            $entry->rawHashes=str_split($binary_concat_prefixes, $removal['rawHashes']['prefixSize']);
            $entry->hashSize=$removal['rawHashes']['prefixSize'];
          }
          $list_data->removals[]=$entry;
        }
      }
      $data->listdata[]=$list_data;
    }
    $data->next=$this->stringToSeconds($response_decoded['minimumWaitDuration']);
    
    // okay, done
    return $data;
  }
  
  /**
   * Get the full hashes for the given prefix (only supports one prefix for now)
   * 
   * @param string $prefix
   * @param string[] $list_states  assoc array with listname => list_sate
   * @return GoogleSafeBrowsing_API_Response_HashCollection
   */
  public function getHash($prefix,$list_states)
  {
    // build the threatTypes/platformTypes/threatEntries
    $threatTypes=array();
    $platformTypes=array();
    $threatEntryTypes=array();
    $listname_hash=array();
    foreach($list_states as $listname => $list_state){
      $listname_hash[$listname]=true;
      $list_parts=$this->deconstructListName($listname);
      $threatTypes[$list_parts['threatType']]=true;
      $platformTypes[$list_parts['platformType']]=true;
      $threatEntryTypes[$list_parts['threatEntryType']]=true;
    }
    $body=json_encode(array(
      'client'       => $this->getClientRequestData(),
      'clientStates' => array_values($list_states),
      'threatInfo'   => array(
        'threatTypes'      => array_keys($threatTypes),
        'platformTypes'    => array_keys($platformTypes),
        'threatEntryTypes' => array_keys($threatEntryTypes),
        'threatEntries'    => array(array('hash' => base64_encode($prefix)))
      )
    ));// for debugging add JSON_PRETTY_PRINT
    $response = $this->postAPI($this->buildAPIURL('fullHashes:find'), $body);
    
    $collection = new GoogleSafeBrowsing_API_Response_HashCollection();
    
    // handle response_code
    switch($response->code){
      case 200:
        // ok!
        break;
      case 400:
        // Bad Request—The HTTP request was not correctly formed. The client did not provide all required CGI parameters.
        print_r($response);
        $collection->abort = true;
        return $collection;
      case 403:
        // Forbidden—The client id or API key is invalid or unauthorized.
        print_r($response);
        $collection->abort = true;
        return $collection;
      case 503:
        // Service Unavailable—The server cannot handle the request. Clients MUST follow the backoff behavior specified in the Request Frequency section
        print_r($response);
        $collection->backoff = true;
        return $collection;
      case 505:
        // HTTP Version Not Supported—The server CANNOT handle the requested protocol major version.
        print_r($response);
        $collection->abort = true;
        return $collection;
      default:
        // unknown response code
        print_r($response);
        $collection->abort = true;
        return $collection;
    }
    
    // parse data
    $response_decoded=json_decode($response->data,true);
    //print_r($body);echo "\n";
    //print_r($response->data);echo "\n";
    
    // cache lifetime https://developers.google.com/safe-browsing/v4/caching
    if(isset($response_decoded['negativeCacheDuration'])){
      $collection->cache=$this->stringToSeconds($response_decoded['negativeCacheDuration']);
    }
    
    // minimumWaitDuration indicates for how long this client may not issue a getHash request at all
    if(isset($response_decoded['minimumWaitDuration'])){
      // todo: not yet implemented minimumWaitDuration
    }
    
    // fixme: currently returns multiple GoogleSafeBrowsing_API_Response_ListHashes even if some full length hashes
    // are for the same list.
    // process lists
    if(isset($response_decoded['matches'])){
      foreach($response_decoded['matches'] as $match){
        $list_data = new GoogleSafeBrowsing_API_Response_ListHashes();
        $list_data->list_name = $this->buildListName($match);
        if(!isset($listname_hash[$list_data->list_name])){
          // we don't run this list, skip
          continue;
        }
        
        $hash = new GoogleSafeBrowsing_API_Response_Hash();
        
        $hash->hash = base64_decode($match['threat']['hash']);
        
        if(isset($match['cacheDuration'])){
          $hash->cache=$this->stringToSeconds($match['cacheDuration']);
        }
        
        if(isset($match['threatEntryMetadata'])){
          // there is meta data
          $hash->raw_meta=json_encode($match['threatEntryMetadata']);
          if(isset($match['threatEntryMetadata']['entries'])){
            foreach($match['threatEntryMetadata']['entries'] as $meta_keyvalue){
              switch(base64_decode($meta_keyvalue['key'])){
                case 'malware_threat_type':
                  switch(base64_decode($meta_keyvalue['value'])){
                    case 'LANDING':
                      $hash->type=1;
                      break;
                    case 'DISTRIBUTION':
                      $hash->type=2;
                      break;
                  }
                  break;
                  
                case 'malware_pattern_type':
                  break;
              }
            }
          }
        }
        
        $list_data->hashes[] = $hash;
        
        $collection->list_hashes[]=$list_data;
      }
    }
    
    // okay thats everything
    return $collection;
  }
  
}

?>