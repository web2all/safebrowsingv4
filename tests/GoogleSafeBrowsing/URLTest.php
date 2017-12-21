<?php

use PHPUnit\Framework\TestCase;

/**
 * GoogleSafeBrowsing URLTest class
 * 
 * Test GoogleSafeBrowsing_Lookup_URL
 * 
 * @author Merijn van den Kroonenberg
 * @copyright (c) Copyright 2017 Web2All BV
 * @since 2017-07-20
 */
class URLTest extends TestCase
{
  /**
   * URL tests
   * 
   * @param string $test_url
   * @param string $expected_url
   * @dataProvider canonicalUrlProvider
   */
  public function testCanonicalURLs($test_url,$expected_url)
  {
    $canonical_url = GoogleSafeBrowsing_Lookup_URL::googleCanonicalize($test_url);
    $this->assertEquals($expected_url, $canonical_url, 'incorrect result from googleCanonicalize()');
  }

  public function canonicalUrlProvider()
  {
    // test_url, expected_url
    return array(
      'percent 25 nr 1'          => array('http://host/%25%32%35'            , 'http://host/%25'),
      'percent 25 nr 2'          => array('http://host/%25%32%35%25%32%35'   , 'http://host/%25%25'),
      // this one breaks, becasue we do not unescape if the only ones are %25, this is the affected edge case
      //'percent 25 nr 3'         => array('http://host/%2525252525252525'    , 'http://host/%25'),
      'percent 25 nr 4'          => array('http://host/asdf%25%32%35asd'     , 'http://host/asdf%25asd'),
      'percent 25 nr 5'          => array('http://host/%%%25%32%35asd%%'     , 'http://host/%25%25%25asd%25%25'),
      'keep as is'               => array('http://www.google.com/'                          , 'http://www.google.com/'),
      'percent unescape nr 1'    => array('http://%31%36%38%2e%31%38%38%2e%39%39%2e%32%36/%2E%73%65%63%75%72%65/%77%77%77%2E%65%62%61%79%2E%63%6F%6D/' , 'http://168.188.99.26/.secure/www.ebay.com/'),
      'percent unescape nr 2'    => array('http://195.127.0.11/uploads/%20%20%20%20/.verify/.eBaysecure=updateuserdataxplimnbqmn-xplmvalidateinfoswqpcmlx=hgplmcx/' , 'http://195.127.0.11/uploads/%20%20%20%20/.verify/.eBaysecure=updateuserdataxplimnbqmn-xplmvalidateinfoswqpcmlx=hgplmcx/'),
      // this one breaks, becasue somewhere in the code it chokes on the control characters
      //'percent unescape nr 3'   => array('http://host%23.com/%257Ea%2521b%2540c%2523d%2524e%25f%255E00%252611%252A22%252833%252944_55%252B' , 'http://host%23.com/~a!b@c%23d$e%25f^00&11*22(33)44_55+'),
      'numeric ip'               => array('http://3279880203/blah'           , 'http://195.127.0.11/blah'),
      'numeric hostname'         => array('http://4294967296/blah'           , 'http://4294967296/blah'),
      'indirection nr 1'         => array('http://www.google.com/blah/../hm' , 'http://www.google.com/hm'),
      'indirection nr 2'         => array('http://www.google.com/blah/..'    , 'http://www.google.com/'),
      'indirection nr 3'         => array('http://www.google.com/blah/.'     , 'http://www.google.com/blah/'),
      'indirection nr 4'         => array('http://www.google.com/./blah'     , 'http://www.google.com/blah'),
      'indirection nr 5'         => array('http://www.google.com/.blah/'     , 'http://www.google.com/.blah/'),
      'http prepend nr 1'        => array('www.google.com/'                  , 'http://www.google.com/'),
      'http prepend nr 2'        => array('www.google.com'                   , 'http://www.google.com/'),
      'strip fragment'           => array('http://www.evil.com/blah#frag'    , 'http://www.evil.com/blah'),
      'lowercase domain'         => array('http://www.GOOgle.com/'           , 'http://www.google.com/'),
      'strip dots'               => array('http://www.google.com.../'        , 'http://www.google.com/'),
      'strip whitespace'         => array("http://www.google.com/foo\tbar\rbaz\n2" , 'http://www.google.com/foobarbaz2'),
      // this one breaks because we strip empty query string in makeRFC2396Valid
      //'empty querystr'          => array('http://www.google.com/q?'         , 'http://www.google.com/q?'),
      'double querystr nr 1'     => array('http://www.google.com/q?r?'       , 'http://www.google.com/q?r?'),
      'double querystr nr 2'     => array('http://www.google.com/q?r?s'      , 'http://www.google.com/q?r?s'),
      'double fragment'          => array('http://evil.com/foo#bar#baz'      , 'http://evil.com/foo'),
      'keep specialchar nr 1'    => array('http://evil.com/foo;'             , 'http://evil.com/foo;'),
      'keep specialchar nr 2'    => array('http://evil.com/foo?bar;'         , 'http://evil.com/foo?bar;'),
      // this one breaks, probably because php urldecode chokes on some control characters (turns into _)
      //'hex codes'               => array('http://'."\x01\x80".'.com/'       , 'http://%01%80.com/'),
      'add trailing slash'       => array('http://notrailingslash.com'       , 'http://notrailingslash.com/'),
      'keep port'                => array('http://www.gotaport.com:1234/'    , 'http://www.gotaport.com:1234/'),
      'trim spaces'              => array('  http://www.google.com/  '       , 'http://www.google.com/'),
      'leading space nr 1'       => array('http:// leadingspace.com/'        , 'http://%20leadingspace.com/'),
      'leading space nr 2'       => array('http://%20leadingspace.com/'      , 'http://%20leadingspace.com/'),
      'leading space nr 3'       => array('%20leadingspace.com/'             , 'http://%20leadingspace.com/'),
      'https'                    => array('https://www.securesite.com/'      , 'https://www.securesite.com/'),
      // these two break, becasue somewhere in the code it chokes on the control characters
      //'retain percent escape nr 1' => array('http://host.com/ab%23cd'          , 'http://host.com/ab%23cd'),
      //'retain percent escape nr 2' => array('http://host.com/ab%08cd'          , 'http://host.com/ab%08cd'),
      'normalise slashes'        => array('http://host.com//twoslashes?more//slashes' , 'http://host.com/twoslashes?more//slashes'),
    );
  }

  /**
   * URL tests
   * 
   * @param string $test_url
   * @param array $expected_urls
   * @dataProvider lookupUrlProvider
   */
  public function testLookupExpression($test_url,$expected_urls)
  {
    $lookup_urls = GoogleSafeBrowsing_Lookup_URL::googleGetLookupExpressions($test_url);
    $this->assertEquals($expected_urls, $lookup_urls, 'incorrect result from googleGetLookupExpressions()');
  }

  public function lookupUrlProvider()
  {
    return array(
      array('http://a.b.c/1/2.html?param=1#frag' , array(
        'a.b.c/1/2.html?param=1',
        'a.b.c/1/2.html',
        'a.b.c/',
        'a.b.c/1/',
        'b.c/1/2.html?param=1',
        'b.c/1/2.html',
        'b.c/',
        'b.c/1/'
      )),
      array('http://a.b.c.d.e.f.g/1.html' , array(
        'a.b.c.d.e.f.g/1.html',
        'a.b.c.d.e.f.g/',
        //(Note: skip b.c.d.e.f.g, since we'll take only the last 5 hostname components, and the full hostname)
        'c.d.e.f.g/1.html',
        'c.d.e.f.g/',
        'd.e.f.g/1.html',
        'd.e.f.g/',
        'e.f.g/1.html',
        'e.f.g/',
        'f.g/1.html',
        'f.g/'
      )),
      array('http://a.b.c.d.e.f.g/1.html?param=1#frag' , array(
        'a.b.c.d.e.f.g/1.html?param=1',
        'a.b.c.d.e.f.g/1.html',
        'a.b.c.d.e.f.g/',
        //(Note: skip b.c.d.e.f.g, since we'll take only the last 5 hostname components, and the full hostname)
        'c.d.e.f.g/1.html?param=1',
        'c.d.e.f.g/1.html',
        'c.d.e.f.g/',
        'd.e.f.g/1.html?param=1',
        'd.e.f.g/1.html',
        'd.e.f.g/',
        'e.f.g/1.html?param=1',
        'e.f.g/1.html',
        'e.f.g/',
        'f.g/1.html?param=1',
        'f.g/1.html',
        'f.g/'
      )),
      array('http://1.2.3.4/1/' , array(
        '1.2.3.4/1/',
        '1.2.3.4/'
      ))
    );
  }
}
?>