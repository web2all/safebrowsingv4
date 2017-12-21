# Web2All safebrowsingv4

This is a google safebrowsing client (update API V4) written in PHP.
For information see the google documentation [https://developers.google.com/safe-browsing/v4/update-api](https://developers.google.com/safe-browsing/v4/update-api).

In short the update API keeps track of a local database of hashcodes which is updated using the update API v4 protocol. Any urls will be checked against the local database. When possible matches are found, more hashes will be retrieved from the google service. 

URLs which are checked are **NEVER** sent to google, only a hash will be submitted to google. This is the big advantage compared to the Lookup API v4. An additional advantage is less network traffic as initial checks are done against a local database of hash prefixes and the bulk of the checks does not require a roundtrip to the google service.

Disadvantage of the Update service compared to the Lookup service is that a local cache/data storage is required and the client must update it periodically. So the client must run as a daemon (or scheduled) to keep the local database up-to-date.

The web2all/safebrowsingv4 is no longer actively maintained.

## What is in this package ##

This package contains the google safebrowsing Update API V4 protocol implementation. But it only includes a reference implementation for the local storage (for storing the hash prefix database).

This reference implementation is file-based and just an example, it is not suited for production use.

For an example of a mysql storage backend implementation of this safebrowsing client, see the `web2all/safebrowsingv4-sqlstorage` package.

## Usage ##

Install using composer (eg. `composer create-project web2all/safebrowsingv4`).

Go to google and request a new API key, see [https://developers.google.com/safe-browsing/v4/get-started](https://developers.google.com/safe-browsing/v4/get-started).

For tests run:
`vendor/bin/phpunit tests`

To test the safebrowsing client using the example file storage backend create a sample php script:

    ini_set ( "memory_limit", "712M");
    require_once('vendor/autoload.php');
    
    $storage = new GoogleSafeBrowsing_Example_FileStorage('/writeable/dir/storage/');
    $updater = new GoogleSafeBrowsing_Updater('YOUR-GOOGLE-KEY', $storage);

    $current_lists=$updater->getLists();
    $changed=false;

    $ensure_lists='MALWARE/ANY_PLATFORM/URL,SOCIAL_ENGINEERING/ANY_PLATFORM/URL,UNWANTED_SOFTWARE/ANY_PLATFORM/URL,POTENTIALLY_HARMFUL_APPLICATION/ANDROID/URL,POTENTIALLY_HARMFUL_APPLICATION/IOS/URL';

    foreach(explode(',',$ensure_lists) as $list){
      if(!isset($current_lists[$list])){
        $current_lists[$list]='';
        echo "add list $list\n";
        $changed=true;
      }
    }
    if($changed){
      $updater->setLists($current_lists);
    }

    $updater->run();

the above script will create a local hash prefix database in `'/writeable/dir/storage/'` and will keep it uptodate. You would want to run such a script in the background. The initial run will need to download all prefixes. It is also pretty memory hungry, an initial run, or when all lists are reset could require 600Mb.

Once above script runs, you can do lookups like this:

    require_once('vendor/autoload.php');
    $storage = new GoogleSafeBrowsing_Example_FileStorage('/writeable/dir/storage/');
    $api=new GoogleSafeBrowsing_API('YOUR-GOOGLE-KEY');
    
    $lookup=new GoogleSafeBrowsing_Lookup($api, $storage);

    if(isset($argv[1])){
      $url=$argv[1];
    }else{
      die('no url given');
    }

    echo "Looking up: $url\n";
    $lists=$lookup->lookup($url);
    if(!$lists){
      echo "NOT LISTED\n";
    }else{
      echo implode(',',$lists)."\n";
    }

## Warning ##

Do not use the included FileStorage example storage backend in production! It is not efficeient, its just an implementation example. If you must, use `web2all/safebrowsingv4-sqlstorage` which can be used with mysql. Or implement you own storage backend by implementing the `GoogleSafeBrowsing_Lookup_IStorage` and `GoogleSafeBrowsing_Updater_IStorage` interfaces.

## See also ##

- `web2all/safebrowsingv4-sqlstorage` [https://github.com/web2all/safebrowsingv4-sqlstorage](https://github.com/web2all/safebrowsingv4-sqlstorage) for a SQL based storage implementation.

## License ##

Web2All safebrowsingv4 is open-sourced software licensed under the MIT license ([https://opensource.org/licenses/MIT](https://opensource.org/licenses/MIT "license")).
