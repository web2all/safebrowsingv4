<?php
require_once(dirname(__FILE__) . '/AnyStorageLookupTestAbstract.php');

use org\bovigo\vfs\vfsStream,
    org\bovigo\vfs\vfsStreamDirectory,
    org\bovigo\vfs\visitor\vfsStreamPrintVisitor;

class GoogleSafeBrowsing_FileStorageLookupTest extends GoogleSafeBrowsing_AnyStorageLookupTestAbstract
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
   * @return GoogleSafeBrowsing_Updater_IStorage
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
   * @return GoogleSafeBrowsing_Lookup_IStorage
   * @depends testStorageCreate
   */
  public function testSetupTestState($storage)
  {
    return parent::testSetupTestState($storage);
  }

}
?>