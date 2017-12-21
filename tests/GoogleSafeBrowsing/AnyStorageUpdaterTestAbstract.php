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
 * GoogleSafeBrowsing AnyStorageUpdaterTestAbstract abstract class
 * 
 * You can use this base class for testing storage system implementing
 * GoogleSafeBrowsing_Updater_IStorage interface.
 * 
 * @author Merijn van den Kroonenberg
 * @copyright (c) Copyright 2017 Web2All BV
 * @since 2017-07-19
 */
abstract class GoogleSafeBrowsing_AnyStorageUpdaterTestAbstract extends TestCase
{
  /**
   * Test instantiation
   * 
   * @return GoogleSafeBrowsing_Updater_IStorage
   */
  public abstract function testStorageCreate();
  
  /**
   * List manipulation
   * 
   * @param GoogleSafeBrowsing_Updater_IStorage
   * @depends testStorageCreate
   */
  public function testStorageLists($storage)
  {
    $this->assertEquals(array(), $storage->getLists(), '$storage->getLists() does not return empty array');
    $set_lists=array('testlist' => 'test state');
    $storage->updateLists($set_lists);
    $this->assertEquals($set_lists, $storage->getLists(), '$storage->getLists() does not match the $storage->updateLists()');
    $set_lists['testlist2']='test state2';
    $storage->updateLists($set_lists);
    $this->assertEquals($set_lists, $storage->getLists(), '$storage->getLists() does not match the $storage->updateLists()');
  }

  /**
   * prefix manipulation
   * 
   * @param GoogleSafeBrowsing_Updater_IStorage
   * @depends testStorageCreate
   * @depends testStorageLists
   */
  public function testStoragePrefixes($storage)
  {
    $test_list='testlist';
    $empty_list_checksum='47DEQpj8HBSa+/TImW+5JCeuQeRkm5NMpJWZG3hSuFU=';
    // test initial state
    $this->assertArrayHasKey($test_list, $storage->getLists(), '$storage->getLists() does not return our test list "testlist"');
    $this->assertEquals($empty_list_checksum, $storage->getListChecksum($test_list,'sha256'), '$storage->getListChecksum() does not return expected checksum (for empty list)');
    // add 3 prefixes
    $prefixes=array(hex2bin('ffffffff'),hex2bin('00000000'),hex2bin('77777777'));
    $filled_list_checksum='GZbiaZF3issNKs3ff7FzYJtSAtOzrR5+PxVYFWxIk2Q=';
    $storage->addHashPrefixes($prefixes, $test_list);
    $this->assertEquals($filled_list_checksum, $storage->getListChecksum($test_list,'sha256'), '$storage->getListChecksum() does not return expected checksum after adding prefixes');
    // reset list
    $storage->removeHashPrefixesFromList($test_list);
    $this->assertArrayHasKey($test_list, $storage->getLists(), '$storage->getLists() does not return our test list "testlist"');
    $this->assertEquals($empty_list_checksum, $storage->getListChecksum($test_list,'sha256'), '$storage->getListChecksum() does not return expected checksum  after clearing list');
    // again add 3 prefixes, in different order
    $prefixes=array(hex2bin('00000000'),hex2bin('ffffffff'),hex2bin('77777777'));
    $filled_list_checksum='GZbiaZF3issNKs3ff7FzYJtSAtOzrR5+PxVYFWxIk2Q=';
    $storage->addHashPrefixes($prefixes, $test_list);
    $this->assertEquals($filled_list_checksum, $storage->getListChecksum($test_list,'sha256'), '$storage->getListChecksum() does not return expected checksum after adding prefixes');
    // add three more prefixes
    $prefixes=array(hex2bin('a0000000'),hex2bin('77777778'),hex2bin('0000000a'));
    $filled_list_checksum='fGDEb6OQTa7U3EmUBMOt6KehDHPrL1occz4uWw3MhWk=';
    $storage->addHashPrefixes($prefixes, $test_list);
    $this->assertEquals($filled_list_checksum, $storage->getListChecksum($test_list,'sha256'), '$storage->getListChecksum() does not return expected checksum after adding prefixes');
    // get back our prefixes by indices (0-5)
    $raw_prefixes=$storage->getHashPrefixesByIndices(array(0,1,2,3,4,5), $test_list);
    $this->assertCount(6, $raw_prefixes, '$storage->getHashPrefixesByIndices() did not return the number of expected results');
    $list_prefixes=array_map('bin2hex',$raw_prefixes);
    $expected_prefixes=array('00000000','0000000a','77777777','77777778','a0000000','ffffffff');
    $this->assertEquals($expected_prefixes, $list_prefixes, '$storage->getHashPrefixesByIndices() does not return expected result');
    // add a long prefix
    $prefixes=array(hex2bin('777777777877'));
    $filled_list_checksum='YzhluHttpM1YEXRuqM6DjXZS7lcUQv7rzQdHCkHgbZ8=';
    $storage->addHashPrefixes($prefixes, $test_list);
    $this->assertEquals($filled_list_checksum, $storage->getListChecksum($test_list,'sha256'), '$storage->getListChecksum() does not return expected checksum after adding long prefix');
    // get back our long prefix by index
    $raw_prefixes=$storage->getHashPrefixesByIndices(array(3), $test_list);
    $this->assertCount(1, $raw_prefixes, '$storage->getHashPrefixesByIndices() did not return the number of expected results');
    $this->assertEquals('777777777877', bin2hex($raw_prefixes[0]), '$storage->getHashPrefixesByIndices() does not return expected result');
  }

  /**
   * prefixes with number
   * 
   * @param GoogleSafeBrowsing_Updater_IStorage
   * @depends testStorageCreate
   * @depends testStoragePrefixes
   */
  public function testStoragePrefixNumberRegression($storage)
  {
    $test_list='testlist';
    // add 3 prefixes
    $prefixes=array(hex2bin('00e10000'),hex2bin('0000e100'),hex2bin('00e20000'));
    $filled_list_checksum='ZJugri2vpQtrNm0FHnE2CDaqb6yTVwbiL754Z9NL7oI=';
    $storage->addHashPrefixes($prefixes, $test_list);
    $this->assertEquals($filled_list_checksum, $storage->getListChecksum($test_list,'sha256'), '$storage->getListChecksum() does not return expected checksum after adding prefixes');
    // get back our prefixes by index
    $raw_prefixes=$storage->getHashPrefixesByIndices(array(2,3,4), $test_list);
    $this->assertCount(3, $raw_prefixes, '$storage->getHashPrefixesByIndices() did not return the number of expected results');
    $list_prefixes=array_map('bin2hex',$raw_prefixes);
    $this->assertEquals(array('0000e100','00e10000','00e20000'), $list_prefixes, '$storage->getHashPrefixesByIndices() does not return expected result');
    // remove prefix 00e20000
    $raw_prefixes=$storage->getHashPrefixesByIndices(array(4), $test_list);
    $this->assertCount(1, $raw_prefixes, '$storage->getHashPrefixesByIndices() did not return the number of expected results');
    $list_prefixes=array_map('bin2hex',$raw_prefixes);
    $this->assertEquals(array('00e20000'), $list_prefixes, '$storage->getHashPrefixesByIndices() does not return expected result');
    $storage->removeHashPrefixes($raw_prefixes, $test_list);
    $raw_prefixes=$storage->getHashPrefixesByIndices(array(2,3), $test_list);
    $this->assertCount(2, $raw_prefixes, '$storage->getHashPrefixesByIndices() did not return the number of expected results');
    $list_prefixes=array_map('bin2hex',$raw_prefixes);
    $this->assertEquals(array('0000e100','00e10000'), $list_prefixes, '$storage->getHashPrefixesByIndices() does not return expected result');
    $this->assertEquals('0JQ5VLgl6b10kFTyjM5rOM/gzD6mi+WDoaXH/JvOy4U=', $storage->getListChecksum($test_list,'sha256'), '$storage->getListChecksum() does not return expected checksum after removing prefix');
    // re-add prefix 00e20000
    $storage->addHashPrefixes(array(hex2bin('00e20000')), $test_list);
    $this->assertEquals($filled_list_checksum, $storage->getListChecksum($test_list,'sha256'), '$storage->getListChecksum() does not return expected checksum after re-adding removed prefix');
  }
  
  /**
   * prefixes longer than 4 bytes
   * 
   * @param GoogleSafeBrowsing_Updater_IStorage
   * @depends testStorageCreate
   * @depends testStoragePrefixNumberRegression
   */
  public function testStorageLongPrefix($storage)
  {
    $test_list='testlist';
    // add more long prefixes
    $prefixes=array_map('hex2bin',array('777777779777','777777779877','777777779977'));
    $filled_list_checksum='PBcNaNSyQy3ECJwlPnEiOupAJK5cPrfJy0IojpZiY5Y=';
    $storage->addHashPrefixes($prefixes, $test_list);
    $this->assertEquals($filled_list_checksum, $storage->getListChecksum($test_list,'sha256'), '$storage->getListChecksum() does not return expected checksum after adding two long prefixes');
    // get back our long prefixes by index
    $raw_prefixes=$storage->getHashPrefixesByIndices(array(5,6,7,8,9,10), $test_list);
    $this->assertCount(6, $raw_prefixes, '$storage->getHashPrefixesByIndices() did not return the number of expected results');
    $this->assertEquals(array('77777777','777777777877','777777779777','777777779877','777777779977','77777778'), array_map('bin2hex',$raw_prefixes), '$storage->getHashPrefixesByIndices() does not return expected result');
    // remove a long prefix
    $raw_prefixes=$storage->getHashPrefixesByIndices(array(9), $test_list);
    $this->assertCount(1, $raw_prefixes, '$storage->getHashPrefixesByIndices() did not return the number of expected results');
    $list_prefixes=array_map('bin2hex',$raw_prefixes);
    $this->assertEquals(array('777777779977'), $list_prefixes, '$storage->getHashPrefixesByIndices() does not return expected result');
    $storage->removeHashPrefixes($raw_prefixes, $test_list);
    $raw_prefixes=$storage->getHashPrefixesByIndices(array(8,9), $test_list);
    $this->assertCount(2, $raw_prefixes, '$storage->getHashPrefixesByIndices() did not return the number of expected results');
    $list_prefixes=array_map('bin2hex',$raw_prefixes);
    $this->assertEquals(array('777777779877','77777778'), $list_prefixes, '$storage->getHashPrefixesByIndices() does not return expected result');
    $this->assertEquals('SHn/V0QM9hKR8bAGKhUwNb9oWuSu2PVPCAVTJWoJxj8=', $storage->getListChecksum($test_list,'sha256'), '$storage->getListChecksum() does not return expected checksum after removing long prefix');
  }
  
  /**
   * List state tracking
   * 
   * @param GoogleSafeBrowsing_Updater_IStorage
   * @depends testStorageCreate
   */
  public function testStorageState($storage)
  {
    $storage->setUpdaterState(0, 0);
    $this->assertSame(0, $storage->getNextRunTimestamp(), '$storage->getNextRunTimestamp() does not return expected result');
    $this->assertSame(0, $storage->getErrorCount(), '$storage->getErrorCount() does not return expected result');
    $now=time();
    $storage->setUpdaterState($now, 6);
    $this->assertSame($now, $storage->getNextRunTimestamp(), '$storage->getNextRunTimestamp() does not return expected result');
    $this->assertSame(6, $storage->getErrorCount(), '$storage->getErrorCount() does not return expected result');
  }

}
?>