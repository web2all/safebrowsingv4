<?php

/**
 * GoogleSafeBrowsing Lookup URL class
 * 
 * This class provides static methods for parsing and manipulating urls
 * 
 * See: https://developers.google.com/safe-browsing/developers_guide_v3#PerformingLookups
 * 
 * @author Merijn van den Kroonenberg
 * @copyright (c) Copyright 2014-2015 Web2All BV
 * @since 2014-12-24
 */
class GoogleSafeBrowsing_Lookup_URL {
  
  /**
   * Make valid url
   * 
   * Make it valid according to RFC 2396. If the URL uses an internationalized domain name (IDN), 
   * it should be converted to the ASCII Punycode representation. The URL must include a path 
   * component; that is, it must have a trailing slash ('http://google.com/').
   * 
   * @param string $url
   * @return string  or null if not a valid url
   */
  public static function makeRFC2396Valid($url)
  {
    // parse the url into parts
    $parsed_url=@parse_url($url);
    // check parsed url
    if($parsed_url===false){
      // unable to parse url
      return null;
    }
    // when no scheme present, parse_url may go haywire
    if(!isset($parsed_url['scheme'])){
      // if its any kind of relative url. we are not interested and we should return null
      
      // check if its only a fragment (starting with #)
      if(preg_match('/^#/',$url)){
        // yep, fragment
        return null;
      }
      
      // check if it starts with a path (/some/path)
      if(preg_match('#^/[^/]#',$url)){
        // starts with / (but not //)
        return null;
      }
      
      // check if starts with //
      if(preg_match('#^//[^/]#',$url)){
        // okay it starts with // (but not ///), so turn it into http://
        $parsed_url=@parse_url('http:'.$url);
      }else{
        // okay, it doesnt have a scheme, but lets add one as it possibly could be an url
        $parsed_url=@parse_url('http://'.$url);
      }
      if($parsed_url===false){
        // unable to parse url
        return null;
      }
    }
    if(!isset($parsed_url['host'])){
      // without host, we cannot do anything
      return null;
    }
    // now create our url parts:
    $scheme='http://';
    if(isset($parsed_url['scheme'])){
      $scheme=$parsed_url['scheme'].'://';
    }
    $hostname=$parsed_url['host'];
    // todo: If the URL uses an internationalized domain name (IDN) it should be converted to the ASCII Punycode representation.
    $port='';
    if(isset($parsed_url['port'])){
      $port=':'.$parsed_url['port'];
    }
    $path='/';
    if(isset($parsed_url['path'])){
      // actually path should always exist
      $path=$parsed_url['path'];
    }
    $query='';
    if(isset($parsed_url['query'])){
      $query='?'.$parsed_url['query'];
    }
    $fragment='';
    if(isset($parsed_url['fragment'])){
      $fragment='#'.$parsed_url['fragment'];
    }
    return $scheme.$hostname.$port.$path.$query.$fragment;
  }
  
  /**
   * Canonicalize the url according to google's wishes
   * 
   * First, remove tab (0x09), CR (0x0d), and LF (0x0a) characters from the URL. Do not remove escape sequences for these characters (e.g. '%0a').
   * 
   * If the URL ends in a fragment, remove the fragment. For example, shorten 'http://google.com/#frag' to 'http://google.com/'.
   * 
   * Next, repeatedly percent-unescape the URL until it has no more percent-escapes.
   * 
   * To canonicalize the hostname:
   * 
   * Extract the hostname from the URL and then:
   * 1.Remove all leading and trailing dots.
   * 2.Replace consecutive dots with a single dot.
   * 3.If the hostname can be parsed as an IP address, normalize it to 4 dot-separated decimal values. The client should handle any 
   *   legal IP- address encoding, including octal, hex, and fewer than 4 components.
   * 4.Lowercase the whole string.
   * 
   * To canonicalize the path:
   * - The sequences "/../" and "/./" in the path should be resolved by replacing "/./" with "/", and removing "/../" along with the preceding path component.
   * - Replace runs of consecutive slashes with a single slash character.
   * 
   * Do not apply these path canonicalizations to the query parameters.
   * 
   * In the URL, percent-escape all characters that are <= ASCII 32, >= 127, "#", or "%". The escapes should use uppercase hex characters.
   * 
   * @param string $url
   * @return string  or null if no valid url
   */
  public static function googleCanonicalize($url)
  {
  
    // First, remove tab (0x09), CR (0x0d), and LF (0x0a) characters from the URL. Do not remove escape sequences for these characters (e.g. '%0a').
    $url=preg_replace('#[\x09\x0d\x0a]#', '', $url);
    
    $url=self::makeRFC2396Valid(trim($url));
    if(is_null($url)){
      // unable to parse url
      return null;
    }
    
    // Next, repeatedly percent-unescape the URL until it has no more percent-escapes.
    $url=self::percentUnEscape($url);
    // fixme: unescaped %23 are not handled correctly
    
    // parse the url into parts
    $parsed_url=parse_url($url);
    // check parsed url
    if($parsed_url===false){
      // unable to parse url
      return null;
    }
    if(!isset($parsed_url['host'])){
      // without host, we cannot do anything
      return null;
    }
    // now create our url parts:
    $scheme=$parsed_url['scheme'].'://';
    $hostname=$parsed_url['host'];
    $hostname=strtolower($hostname);
    $hostname=preg_replace('#(\.+)?(.*?)(\.+)?$#', '$2', $hostname);
    // todo: handle ip address conversions
    // is it IPv4 in decimal?
    if(preg_match('/^[0-9]+$/',$hostname)){
      // ok its a number
      // lets see if its in the 32bit range
      $ip4_hex=base_convert($hostname, 10, 16);
      if(strlen($ip4_hex)<=8){
        // ok, hex representation is less than 9 chars, so thats max FFFFFFFF
        // convert to decimal dotted
        // first make sure its 8 chars hex
        $ip4_hex=str_pad($ip4_hex , 8, "0", STR_PAD_LEFT);
        $ip4_dec_arr=array();
        foreach(str_split($ip4_hex,2) as $octet){
          $ip4_dec_arr[]=base_convert($octet, 16, 10);
        }
        $hostname=implode('.', $ip4_dec_arr);
      }
    }
    $port='';
    if(isset($parsed_url['port'])){
      $port=':'.$parsed_url['port'];
    }
    // The sequences "/../" and "/./" in the path should be resolved by replacing "/./" with "/", and removing "/../" along with the preceding path component.
    $path=preg_replace('#/\.(/|$)#', '/', $parsed_url['path']);
    $path=preg_replace('#/[^/]+/\.\.(/|$)#', '/', $path);
    // Replace runs of consecutive slashes with a single slash character
    $path=preg_replace('#/+#', '/', $path);
    $query='';
    if(isset($parsed_url['query'])){
      $query='?'.$parsed_url['query'];
    }
    // fixme: empty queries (only the questionmark) are not handled correctly, the questionmark is lost
    return self::googlePercentEscape($scheme.$hostname.$port.$path.$query);
  }
  
  /**
   * Get a list of lookup expressions for the given canonicalized url
   * 
   * Only use the host and path components of the URL. The scheme, username, password, and port are disregarded. If the 
   * URL includes query parameters, the client will include a lookup with the full path and query parameters.
   * 
   * For the hostname, the client will try at most 5 different strings. They are:
   * - the exact hostname in the URL
   * - up to 4 hostnames formed by starting with the last 5 components and successively removing the leading component. 
   *   The top-level domain can be skipped. These additional hostnames should not be checked if the host is an IP address.
   * 
   * For the path, the client will also try at most 6 different strings. They are:
   * - the exact path of the URL, including query parameters
   * - the exact path of the URL, without query parameters
   * - the 4 paths formed by starting at the root (/) and successively appending path components, including a trailing slash.
   * 
   * @param string $url
   * @return string[]
   */
  public static function googleGetLookupExpressions($url)
  {
    $expressions=array();
    // todo: implement
    // parse the url into: schem, hostname (or ip) and path
    $parsed_url=parse_url($url);
    // check parsed url
    if($parsed_url===false){
      // unable to parse url
      return $expressions;
    }
    if(!isset($parsed_url['host'])){
      // without host, we cannot do anything
      return $expressions;
    }
    // now create our url parts:
    $hostname=$parsed_url['host'];
    $path='';
    if(isset($parsed_url['path'])){
      // actually path should always exist
      $path=$parsed_url['path'];
    }
    $query='';
    if(isset($parsed_url['query'])){
      $query='?'.$parsed_url['query'];
    }
    if(self::isHostnameIP($hostname)){
      // its IP address, so no need to try different hostnames
      return self::googleGetPathPermutations($hostname, $path, $query);
    }else{
      // try different hostname combinations
      // start with original
      $try_hostnames=array($hostname);
      // and the 5 topmost parts
      $hostname_parts=explode('.',$hostname);
      // if the hostname has more than 2 parts, we will try different hostnames with leading parts removed
      if(count($hostname_parts)>2){
        // lets see the longest sub hostname we have to try
        $start_depth=count($hostname_parts);
        if($start_depth>5){
          // not more than 5 deep
          $start_depth=5;
        }else{
          $start_depth--;
        }
        for($i=$start_depth;$i>=2;$i--){
          // build a hostname from the last $i parts of the hostname
          $try_hostnames[]=implode('.',array_slice($hostname_parts,-$i));
        }
      }
      foreach($try_hostnames as $try_hostname){
        $sub_expressions = self::googleGetPathPermutations($try_hostname, $path, $query);
        $expressions=array_merge($expressions,$sub_expressions);
      }
    }
    return $expressions;
  }
  
  /**
   * Get a list of lookup expressions for the given canonicalized url
   * 
   * For the path, the client will also try at most 6 different strings. They are:
   * - the exact path of the URL, including query parameters
   * - the exact path of the URL, without query parameters
   * - the 4 paths formed by starting at the root (/) and successively appending path components, including a trailing slash.
   * 
   * @param string $hostname
   * @param string $path
   * @param string $query
   * @return string[]
   */
  public static function googleGetPathPermutations($hostname, $path, $query)
  {
    $expressions=array();
    if($query){
      $expressions[]=$hostname.$path.$query;
    }
    $expressions[]=$hostname.$path;
    if($path!='/'){
      // try different path components
      // at most 4 path components
      $path_parts=explode('/',$path);
      if($path_parts[count($path_parts)-1]==''){
        // last part is empty (so it ends on a slash), remove the part
        array_pop($path_parts);
      }
      
      $num_perm=count($path_parts);
      if($num_perm>4){
        // not more than 4 deep
        $num_perm=4;
      }else{
        $num_perm--;
      }
      for($i=1;$i<=$num_perm;$i++){
        // build a hostname from the firts $i parts of the path (first part is always empty because path starts with a slash)
        // and we add an empty element to the end, so we ebnd on a slash
        $try_path=implode('/',array_merge(array_slice($path_parts,0,$i),array('')));
        $expressions[]=$hostname.$try_path;
      }
    }
    return $expressions;
  }
  
  /**
   * Check if the given hostname is an ip address
   * 
   * @param string $hostname
   * @return boolean
   */
  public static function isHostnameIP($hostname)
  {
    // IP-literal: encased in square brackets
    // we match starting [ character followed by non ] characters followed by ] character as last character in hostname
    if(preg_match('/^\[[^\]]\]$/',$hostname)){
      // ok its an IP-literal, we do not parse the literal to see if its valid
      return true;
    }
    // IPv4 in decimal
    if(preg_match('/^[0-9]+$/',$hostname)){
      // ok its a number
      // lets see if its in the 32bit range
      $ip4_hex=base_convert($hostname, 10, 16);
      if(strlen($ip4_hex)<=8){
        // ok, hex representation is less than 9 chars, so thats max FFFFFFFF
        return true;
      }
    }
    $ip_parts=Array();
    if(!preg_match('/^([0-9]{1,3})\.([0-9]{1,3})\.([0-9]{1,3})\.([0-9]{1,3})$/',$hostname,$ip_parts)){
      return false;
    }
    // check if each block is in byte range (hard to test with regex)
    for($j=1;$j<=4;$j++){
      if(($ip_parts[$j]>255) || ($ip_parts[$j]<0)){
        return false;
      }
    }
    return true;
  }
  
  /**
   * percent-escape all characters that are <= ASCII 32, >= 127, "#", or "%". The escapes should use uppercase hex characters.
   * 
   * @param string $string
   * @return string
   */
  public static function googlePercentEscape($string)
  {
    $result='';
    foreach(str_split($string) as $char){
      $intval=ord($char);
      if($intval <= 32 || $intval >= 127 || $char == '#' || $char == '%'){
        $result.='%'.sprintf('%02X',$intval);
      }else{
        $result.=$char;
      }
    }
    return $result;
  }
  
  /**
   * unescape the string
   * 
   * @param string $string
   * @return string
   */
  public static function percentUnEscape($string)
  {
    // Next, repeatedly percent-unescape the URL until it has no more percent-escapes.
    // this is not trivial, because the percent character could be in the url, when are we done?
    // lets unescape till there are no more percentages, or the only percentages are %25 (the % char)
    $keep_unescaping=true;
    while($keep_unescaping){
      if(strpos($string, '%')===false){
        // no % found, done
        break;
      }
      // replace al %25 sequences
      $test_string=preg_replace('#%25#s', '', $string);
      // now see if any % left
      // no? then we must decode exactly one more time
      if(strpos($test_string, '%')===false){
        // no % found
        $keep_unescaping=false;
      }
      $dec_string=urldecode($string);
      if($dec_string==$string){
        // decoding has no effect, bail out
        break;
      }
      $string=$dec_string;
    }
    return $string;
  }
  
  /**
   * create hash of url
   * 
   * @param string $url
   * @return string
   */
  public static function hash($url)
  {
    return hash("sha256",$url,true);
  }
  
}

?>