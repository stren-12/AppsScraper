<?php
namespace AppsScraper;

use \Wa72\HtmlPageDom\HtmlPageCrawler;
/**
 * AppsScraper
 *  
 * Class to Scrap AppData from its own store
 * This Class Used to Scrap data from app web page in the supported stores
 * with no need to worry about different html page layout
 * Also its have the ability to auto-detect the store from the app id without need to define it
 * 
 * @package AppsScraper
 * @version 1.1
 * @author  Sultan Aljohani <stren-12.com , sultanfahad.sa>
 * @license https://    opensource.org/licenses/MIT MIT License
 * @link    https://github.com/stren-12/AppsScraper
 */



class AppsScraper
{

    /*
     * HTTP Accept Languge Header 
     * if you want to retreve non-english version of the app use this header 
     * 
     * @link https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Accept-Language
     * @var string
     */
    protected $AcceptLanguage = '*';

    /**
     * Application Id
     * 
     * @var string
     */
    protected $AppId = '';

    /**
     * Application Url
     * 
     * @var string
     */
    protected $AppUrl = '';

    /**
     * TargtedStore (App Store, Google Play, etc..)
     * 
     * @var string
     */
    protected $TargtedStore = '';

    /**
     * Document Object Model (DOM) 
     * 
     * @link https://developer.mozilla.org/en-US/docs/Web/API/Document_Object_Model/Introduction
     * @var object
     */
    protected $Dom;

    /**
     * Whaterver print errors or not 
     * if false no error will printed and php built-in $this->SetError() will not be called
     * @var bool
     */
    protected $Debug;

    /**
     * An Array of occurred errors 
     * 
     * @var array
     */
    protected $Errors =[];

    /**
     * Did the library initialized correctly or not
     * true if everything fine false otherwise
     * 
     * @var bool
     */
    protected $Initialized = false;

    /**
     * Application Data
     * 
     * @var array
     */ 
    protected $AppData = [
        'image' => null, 
        'name' => null,
        'rating'  => null,
        'price' => null,
        'description' => null,
        'operatingSystem' => null,
        'author' => null
    ];

    /**
     * Stores Data
     * base url, Regex for auto-detect and elements selector (Jquery like)
     * @var array
     */ 
    protected $AppStores = [
        'GooglePlay' =>[
            'url' => 'https://play.google.com/store/apps/details?id=%s' ,
            'regex' => '/^[[^.A-z]+$/',
            'schema' => 'script[type="application/ld+json"]',
          
        ],
        'AppStore' => [
            'url' => 'https://apps.apple.com/app/id%u',
            'regex' =>'/^[0-9]+$/',
            'schema' => 'script[name="schema:software-application"]',
        ]
    ];
    

    /**
     * Constructor 
     * First Check the parameters validity, select the store (Or Auto-Detect by regex)
     * then Check if Curl Extension loaded or not 
     * before Final step we have check the $AppId 
     * Load Url, initialize the Dom, lastly Scrap App Data 
     * @param   string  $AppId
     * @param   string  $TargtedStore = ""
     * @param   string  $AcceptLanguage = ""
     * @param 	bool $Debug = false
     * @return  void
     */
    public function __construct($AppId,$TargtedStore = "",$AcceptLanguage = "",$Debug = false)
    {
        $this->$Debug = $Debug;
        
        if($TargtedStore != '' && !isset($this->AppStores[$TargtedStore])){
            $this->SetError("$TargtedStore Is Not Supported");
            return;
        }elseif(!$this->DetectAppStore($AppId)){
            $this->SetError("$AppId Is Not valid AppId");
            return;
        }
        if (! extension_loaded('curl')){
            $this->SetError("php curl extension is missing");
            return;
        }

        if (! extension_loaded('xml')){
            $this->SetError("php xml extension is missing");
            return;
        }

        if (! extension_loaded('dom')){
            $this->SetError("php dom extension is missing");
            return;
        }
        elseif($TargtedStore != ''){
            $this->TargtedStore = $TargtedStore;
        }
        $this->AppId = $AppId;
        $this->AcceptLanguage = $AcceptLanguage;

        if(!$this->CheckAppId($this->AppId)){
            $this->SetError("$this->AppId Is Wrong AppId For $TargtedStore");
            return ;
        }
        $this->AppUrl = sprintf($this->AppStores[$this->TargtedStore]['url'],$AppId);
        if(!$this->LoadUrl($this->AppUrl)){
            return;
        }
        $this->LoadAppData();

        $this->Initialized = true;
    }

    // --------------------------------------------------------------------

    /**
     * Destructor
     * Call Clear() Method to clear memory due to php5 circular references memory leak...
     * @see Clear()
     * 
     * @return  void
     */
    public function __destruct()
    {
        $this->Clear();
    }

    // --------------------------------------------------------------------

     /**
     * Because constructor cannot return false we will use this Method to check if everything is correct
     * 
     * @return  bool
     */
    public function Initialized(): bool{
        return $this->Initialized;
    }
    // --------------------------------------------------------------------

     /**
     * Return array of Current supported App Stores
     * 
     * @return  array
     */
    public static function GetAppStores(): array{
        return array_keys(self::$AppStores);
    }

    // --------------------------------------------------------------------

    /**
     * Return the AppData (After Scraping)
     * $AppData is encapsulated by this Method (Readonly)
     * 
     * @see $AppData
     * @return  array
     */
    public function GetAppData(): array{
        return $this->AppData;
    }

    // --------------------------------------------------------------------

    /**
     * Error Setter
     * Add An Error to $Errors array and call php built-in trigger_error() if $Debug is true
     * 
     * @see $Debug
     * @see $Errors
     * @param   string  $Error
     * @return  void
     */
    protected function SetError(string $Error): void{
        $this->Errors[] = $Error;
        if($this->Debug === true){
            trigger_error($Error);
        }
    }

    // --------------------------------------------------------------------

    /**
     * Error Getter
     * 
     * @return  array
     */
    public function GetErrors(): array{
        return $this->Errors; 
    }

    // --------------------------------------------------------------------

    /**
     * Return the AppData (After Scraping)
     * After fetching the HTTP response by $this->LoadUrl()
     * Now it's time for parsing the response to extract the data we need 
     * Note: all App stores usess https://schema.org/SoftwareApplication schema 
     * 
     * @see $AppData
     */
    protected function LoadAppData(): void{
 
        $schema  = $this->AppStores[$this->TargtedStore]['schema'];
        $json_string = $this->Dom->filter($schema)->getInnerHtml();
        $json    = json_decode($json_string,true); 
        foreach($this->AppData as $k => $v)
        {
            // If data is in the Schema JSON just store it in $this->AppData
            if(isset($json[$k])){
                $this->AppData[$k] = $json[$k];
                
            }else{
                // If not check if we looking to price or not  

                if($k == 'price'){
                    /** 
                     * Some appstores (i.e GooglePlay) store price in diffrent way
                     * Inside the Schema json and we have handle it 
                     * 
                    */ 
                   switch($this->TargtedStore){
                    case "GooglePlay":
                        $this->AppData[$k] = $json['offers'][0]['price'];

                        break;
                    case "AppStore":
                        $this->AppData[$k] = $json['offers']['price'];
                        break;
                   }
                }
            }
        }
    }

    // --------------------------------------------------------------------

    /**
     * Detect App Store
     * By using Regex in $App Stores see if match, otherwise return false
     * 
     * @see __construct()
     * @see $AppStores
     * @return  mixed
     */
    protected function DetectAppStore(string $AppId){
        foreach($this->AppStores as $k => $v){
            if(preg_match($this->AppStores[$k]['regex'],$AppId)){
                $this->TargtedStore = $k;
                return $k;
            }
        }
        return false;
    }

    // --------------------------------------------------------------------

    /**
     * Get Targted Store
     * return the TargtedStore 
     * 
     * @see $TargtedStore
     * @return  string
     */
    public function GetTargtedStore(): string{
        return $this->TargtedStore;
    }

    // --------------------------------------------------------------------

    /**
     * Compare Scraped AppData By user-defined data
     * Useful for check if app data is old (image, title, etc..)
     * By providing current AppData with the new fetched 
     * @param $AppData
     * @return  bool
     */
    public function CompareData(array $AppData): bool{
        // NOTE: in php to compare two arrays you should use array_diff for key-value compartion
        // @see https://stackoverflow.com/questions/901815/php-compare-array/12058251#12058251
        return !(array_diff($AppData, $this->AppData) || array_diff($this->AppData, $AppData));
    }

    // --------------------------------------------------------------------

    /**
     * Check if AppId match's user-defined store
     * Unlike DetectAppStore() this Method will used when user defined $TargtedStore
     * @see __construct()
     * @see DetectAppStore()
     * @see $TargtedStore
     * @see $AppStores
     * @return  bool
     */
    protected function CheckAppId(string $AppId): bool{
        if(preg_match($this->AppStores[$this->TargtedStore]['regex'],$AppId)){
            return true;
        }
        return false;
    }

    // --------------------------------------------------------------------

    /**
     * Clear memory due to php5 circular references memory leak...
     * 
     * @see __destruct()
     * @return  void
     */
    protected function Clear(): void{
        $this->AcceptLanguage = null;
        $this->AppId = null;
        $this->AppUrl = null;
        $this->TargtedStore = null;
        $this->Dom = null;
        $this->AppData = null; 
        $this->AppStores = null;
    }

    // --------------------------------------------------------------------

    /**
     * Load App HTML page to Dom using Curl for fetching And HtmlDomParser for Dom Object
     * Check for Curl error and non 200 HTTP Code (200 == OK)
     * 
     * @link https://developer.mozilla.org/en-US/docs/Web/HTTP/Status
     * @return  bool
     */
    protected function LoadUrl(string $url): bool
    {
        $arr = [];
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL,$url);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_VERBOSE, 0);
        curl_setopt($ch, CURLOPT_FAILONERROR, true); // Required for HTTP error codes to be reported via our call to curl_error($ch)
        curl_setopt($ch, CURLOPT_HTTPHEADER, array("Accept-Language: ".$this->AcceptLanguage));
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 5.1; .NET CLR 1.0.3705; .NET CLR 1.1.4322; Media Center PC 4.0)');
        curl_setopt($ch, CURLOPT_REFERER, 'http://www.google.com');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, 0);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 20);
        $arr['response'] = curl_exec( $ch );
        $arr['errno']   = curl_errno( $ch );
        $arr['errmsg']  = curl_error( $ch );
        $arr['header']  = curl_getinfo( $ch );
        curl_close( $ch );
        if ( $arr['errno'] != 0 ){
            if ( $arr['header']['http_code'] != 200 ){
                // HTTP Code 200 mean OK outherwise thir is something wrong with the request 
                // Check what's the error code, Report it and exit.
                switch($arr['header']['http_code']){
                    case 400:
                        $this->SetError("HTTP Error: 400 Bad Request");
                    break;
                    case 404:
                        $this->SetError("HTTP Error: 404 Not Found");
                    break;
                    case 500:
                        $this->SetError("HTTP Error: 500 Internal Server Error");
                    break;
                    case 503:
                        $this->SetError("HTTP Error: 503 Service Unavailable");
                    break;
                    default:
                        $this->SetError("HTTP Error Code : ".$arr['http_code']);
                    break;
                        
                }
                return false;
            }
            //error: bad url, timeout, redirect loop ...
            $this->SetError("CURL Error: " . $arr['errmsg']);
            return false;
        }
        
        $this->Dom = new HtmlPageCrawler($arr['response']);

        return true;
        
    }
}
