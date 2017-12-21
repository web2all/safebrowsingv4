<?php
require_once(dirname(__FILE__) . '/AnyStorageUpdaterTestAbstract.php');

use org\bovigo\vfs\vfsStream,
    org\bovigo\vfs\vfsStreamDirectory,
    org\bovigo\vfs\visitor\vfsStreamPrintVisitor;

class GoogleSafeBrowsing_FileStorageUpdaterTest extends GoogleSafeBrowsing_AnyStorageUpdaterTestAbstract
{
  /**
   * @var  vfsStreamDirectory
   */
  private static $root;

  /**
   * set up test environmemt
   */
  public static function setUpBeforeClass()
  {
    self::$root = vfsStream::setup('FileStorageTest');
  }

  /**
   * Test instantiation
   * 
   * @return GoogleSafeBrowsing_Example_FileStorage
   */
  public function testStorageCreate()
  {
    $storage = new GoogleSafeBrowsing_Example_FileStorage(vfsStream::url('FileStorageTest/storage'));
    $storage->setQuiet();
    $this->assertTrue(self::$root->hasChild('storage'),'FileStorage root dir has not been created');
    
    $this->assertEquals($storage->getLists(), array(), '$storage->getLists() does not erturn empty array');
    
    //vfsStream::inspect(new  vfsStreamPrintVisitor());
    $this->assertTrue(self::$root->hasChild('storage'),'FileStorage root dir does no longer exist');
    
    return $storage;
  }

  /**
   * List manipulation
   * 
   * @param GoogleSafeBrowsing_Updater_IStorage
   * @depends testStorageCreate
   */
  public function testStorageLists($storage)
  {
    $this->assertTrue(self::$root->hasChild('storage'),'FileStorage root dir does no longer exist');
    parent::testStorageLists($storage);
    $this->assertTrue(self::$root->hasChild('storage/lists'),'FileStorage lists storage file does not exist');
    //vfsStream::inspect(new  vfsStreamPrintVisitor());
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
    $this->assertTrue(self::$root->hasChild('storage'),'FileStorage root dir does no longer exist');
    parent::testStoragePrefixes($storage);
    //vfsStream::inspect(new  vfsStreamPrintVisitor());
    $this->assertTrue(self::$root->hasChild('storage/testlist'),'FileStorage testlist directory was not created');
    $this->assertTrue(self::$root->hasChild('storage/testlist/prefixes'),'FileStorage prefixes database file for list testlist does not exist');
  }
}
?>