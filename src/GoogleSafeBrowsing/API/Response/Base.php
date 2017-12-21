<?php

/**
 * GoogleSafeBrowsing API Response Base class
 * 
 * This is the base class for responses from the GoogleSafeBrowsing_API
 * It contains information about the success of the request.
 * 
 * @author Merijn van den Kroonenberg
 * @copyright (c) Copyright 2014 Web2All BV
 * @since 2014-11-18
 */
class GoogleSafeBrowsing_API_Response_Base {
  
  /**
   * If true we MUST follow the backoff behavior 
   *
   * @var boolean
   */
  public $backoff=false;
  
  /**
   * If true we had an unrecoverable error
   * Additional requests are useless, manual intervention is required
   *
   * @var boolean
   */
  public $abort=false;
  
}

?>