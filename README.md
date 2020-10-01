# AppsScraper
Class to Scrap AppData from its own store
This Class Used to Scrap data from app web page in the supported stores
with no need to worry about different html page layout
 Also its have the ability to auto-detect the store from the app id without need to define it
## Features
* Auto detect for the store from the AppId by Regex
* Universal way to get app data with no need to worry about different html page layout

## Installation
First you need to install HtmlDomParser by composer

```bash
composer require stren-12/apps-scraper   
```

then in php code 
```php
use AppsScraper\AppsScraper;
$AppsScraper = new AppsScraper('com.google.android.youtube');
echo var_dump($AppsScraper->GetAppData())
/* output: array(4) { ["image"]=> string(106) "https://lh3.googleusercontent.com/lMoItBgdPPVDJsNOVtP26EKHePkwBg-PkuY9NOrc-fumRtTFP4XhpUNk_22syN4Datc=s180" 
["title"]=> string(8) "YouTube " ["rate"]=> string(3) "4.1" ["price"]=> NULL } */
```

## Note
There is a lot of Methods in the library, it's well documented take your time looking for it