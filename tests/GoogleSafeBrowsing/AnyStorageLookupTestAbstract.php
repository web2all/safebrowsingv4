<?php

use PHPUnit\Framework\TestCase;

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
 * GoogleSafeBrowsing AnyStorageLookupTestAbstract abstract class
 * 
 * You can use this base class for testing storage system implementing
 * GoogleSafeBrowsing_Lookup_IStorage interface.
 * 
 * @author Merijn van den Kroonenberg
 * @copyright (c) Copyright 2017 Web2All BV
 * @since 2017-07-25
 */
abstract class GoogleSafeBrowsing_AnyStorageLookupTestAbstract extends TestCase
{
  /**
   * Test instantiation
   * 
   * @return GoogleSafeBrowsing_Lookup_IStorage
   */
  public abstract function testStorageCreate();

  /**
   * Set up some data
   * 
   * @param GoogleSafeBrowsing_Updater_IStorage
   * @return GoogleSafeBrowsing_Lookup_IStorage
   * @depends testStorageCreate
   */
  public function testSetupTestState($storage)
  {
    // add lists
    $this->assertEquals(array(), $storage->getLists(), '$storage->getLists() does not return empty array');
    $set_lists=array('testlist1' => 'test state1', 'testlist2' => 'test state2');
    $storage->updateLists($set_lists);
    $this->assertEquals($set_lists, $storage->getLists(), '$storage->getLists() does not match the $storage->updateLists()');
    
    // add prefixes
    $test_list1='testlist1';
    $prefixes=array(hex2bin('ffffffff'),hex2bin('00000000'),hex2bin('77777777'));
    $storage->addHashPrefixes($prefixes, $test_list1);
    
    $test_list2='testlist2';
    $prefixes=array(hex2bin('66666666'));
    $storage->addHashPrefixes($prefixes, $test_list2);
    return $storage;
  }

  /**
   * Test lookups in prefixes
   * 
   * @param GoogleSafeBrowsing_Lookup_IStorage
   * @depends testSetupTestState
   */
  public function testLookups($storage)
  {
    $lookup_full_hashes=array('ffffffffffffffff');
    $matched_full_hashes=$storage->hasPrefix(array_map('hex2bin',$lookup_full_hashes));
    $this->assertEquals($lookup_full_hashes, array_map('bin2hex',$matched_full_hashes), '$storage->hasPrefix() does not return expected full hash');
    
    $lookup_full_hashes=array('8888888888888888');
    $matched_full_hashes=$storage->hasPrefix(array_map('hex2bin',$lookup_full_hashes));
    $this->assertEquals(array(), array_map('bin2hex',$matched_full_hashes), '$storage->hasPrefix() does not return expected full hash');
    
    $lookup_full_hashes=array('1234567890123456','00000000ffffffff','8888888888888888');
    $matched_full_hashes=$storage->hasPrefix(array_map('hex2bin',$lookup_full_hashes));
    $this->assertEquals(array('00000000ffffffff'), array_map('bin2hex',$matched_full_hashes), '$storage->hasPrefix() does not return expected full hash');
    
    $lookup_full_hashes=array('ffffffffffffffff','00000000ffffffff','8888888888888888');
    $matched_full_hashes=$storage->hasPrefix(array_map('hex2bin',$lookup_full_hashes));
    $matched_full_hashes_sorted=array_map('bin2hex',$matched_full_hashes);
    sort($matched_full_hashes_sorted);
    $this->assertEquals(array('00000000ffffffff','ffffffffffffffff'), $matched_full_hashes_sorted, '$storage->hasPrefix() does not return expected full hash');
    
    $lookup_full_hashes=array('6666666600000000');
    $matched_full_hashes=$storage->hasPrefix(array_map('hex2bin',$lookup_full_hashes));
    $this->assertEquals($lookup_full_hashes, array_map('bin2hex',$matched_full_hashes), '$storage->hasPrefix() does not return expected full hash');
    
    $lookup_full_hashes=array('6666666600000000','7777777777777777');
    $matched_full_hashes=$storage->hasPrefix(array_map('hex2bin',$lookup_full_hashes));
    sort($lookup_full_hashes);
    $matched_full_hashes_sorted=array_map('bin2hex',$matched_full_hashes);
    sort($matched_full_hashes_sorted);
    $this->assertEquals($lookup_full_hashes, $matched_full_hashes_sorted, '$storage->hasPrefix() does not return expected full hash');
    
    $lookup_full_hashes=array('000e060000000000');
    $matched_full_hashes=$storage->hasPrefix(array_map('hex2bin',$lookup_full_hashes));
    $this->assertEquals(array(), array_map('bin2hex',$matched_full_hashes), '$storage->hasPrefix() does not return expected full hash');
    
  }

  /**
   * Test lookups in prefixes
   * 
   * @param GoogleSafeBrowsing_Lookup_IStorage
   * @depends testSetupTestState
   * @depends testLookups
   */
  public function testLookupsLongPrefixes($storage)
  {
    $test_list2='testlist2';
    $prefixes=array(hex2bin('66666677ac'),hex2bin('66666677ab'));
    $storage->addHashPrefixes($prefixes, $test_list2);
    
    
    $lookup_full_hashes=array('66666677abc00099','66666666ffffffff','66666677ae000000');
    $matched_full_hashes=$storage->hasPrefix(array_map('hex2bin',$lookup_full_hashes));
    $matched_full_hashes_sorted=array_map('bin2hex',$matched_full_hashes);
    sort($matched_full_hashes_sorted);
    $this->assertEquals(array('66666666ffffffff','66666677abc00099'), $matched_full_hashes_sorted, '$storage->hasPrefix() does not return expected full hash');
    
  }

  /**
   * Test lookups in full hash cache
   * 
   * @param GoogleSafeBrowsing_Lookup_IStorage
   * @depends testSetupTestState
   */
  public function testCache($storage)
  {
    $test_list1='testlist1';
    $test_list2='testlist2';
    
    $this->assertTrue($storage->addHashInCache('ffffffffffffffff',array($test_list1),'',60), '$storage->addHashInCache() failed');
    $this->assertEquals(array($test_list1), $storage->isListedInCache('ffffffffffffffff'), '$storage->isListedInCache() does not return expected lists');
    
    $this->assertTrue($storage->addHashInCache('ffffffffffffffff',array($test_list2),'',60), '$storage->addHashInCache() failed');
    $this->assertEquals(array($test_list1,$test_list2), $storage->isListedInCache('ffffffffffffffff'), '$storage->isListedInCache() does not return expected lists');
    
    $this->assertEquals(null, $storage->isListedInCache('00000000ffffffff'), '$storage->isListedInCache() should return null as the entry is not in the cache');
    $this->assertTrue($storage->addHashInCache('00000000ffffffff',array(),'',60), '$storage->addHashInCache() failed');
  }

  /**
   * Test lookups in full hash cache with negative hits
   * 
   * @param GoogleSafeBrowsing_Lookup_IStorage
   * @depends testSetupTestState
   */
  public function testCacheNegative($storage)
  {
    $test_list1='testlist1';
    $test_list2='testlist2';
    
    $this->assertTrue($storage->addHashInCache('eeeeffff',array(),'',60), '$storage->addHashInCache() negative cache add failed');
    $this->assertEquals(array(), $storage->isListedInCache('eeeeffff00005555'), '$storage->isListedInCache() does not return negative hit');
    
    $this->assertEquals(null, $storage->isListedInCache('ddddffff00001111'), '$storage->isListedInCache() does not return cache miss');
    
    $this->assertTrue($storage->addHashInCache('ddddffffffffffff',array($test_list1),'',60), '$storage->addHashInCache() failed');
    $this->assertEquals(array($test_list1), $storage->isListedInCache('ddddffffffffffff'), '$storage->isListedInCache() does not return expected lists');
    
    $this->assertEquals(array(), $storage->isListedInCache('ddddffff00001111'), '$storage->isListedInCache() does not return negative hit');
  }

}
?>