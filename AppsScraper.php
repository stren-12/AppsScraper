<?php
use Sunra\PhpSimple\HtmlDomParser;

/**
 * AppsScraper
 *  
 * Class to Scrap AppData from its own store
 * This Class Used to Scrap data from app web page in the supported stores
 * with no need to worry about different html page layout
 * Also its have the ability to auto-detect the store from the app id without need to define it
 * 
 * @package	AppsScraper
 * @version 1.0
 * @author  strn-12 <smagsf@gmail.com>
 * @license	https://opensource.org/licenses/MIT	MIT License
 * @link	https://github.com/stren-12/AppsScraper
 */



class AppsScraper
{

    /**
	 * HTTP Accept Languge Header 
	 * if you want to retreve non-english version of the app use this header 
     * 
     * @link https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Accept-Language
	 * @var	string
	 */
    protected $AcceptLanguage = '*';

    /**
	 * Application Id
	 * 
	 * @var	string
	 */
    protected $AppId = '';

    /**
	 * Application Url
	 * 
	 * @var	string
	 */
    protected $AppUrl = '';

    /**
	 * TargtedStore (App Store, Google Play, etc..)
	 * 
	 * @var	string
	 */
    protected $TargtedStore = '';

    /**
	 * Document Object Model (DOM) 
	 * this will be 
     * 
     * @link https://developer.mozilla.org/en-US/docs/Web/API/Document_Object_Model/Introduction
	 * @var	object
	 */
    protected $Dom;

    /**
	 * Application Data
	 * 
	 * @var	array
	 */ 
    protected $AppData = [
        'image' => null, 
        'title' => null,
        'rate' =>  null,
        'price' => null
    ];

    /**
	 * App Stores Data
	 * base url, Regex for auto-detect and elements selector (Jquery like)
	 * @var	array
	 */ 
    protected $AppStores = [
        'GooglePlay' =>[
            'url' => 'https://play.google.com/store/apps/details?id=%s' ,
            'regex' => '/^[[^.a-z]+$/',
            'elements' => [
            'image' => 'img', 
            'title' => 'h1[class=AHFaub]',
            'rate' => '.BHMmbe',
            'price' => 'meta[itemprop="price"]'
            ]
        ],
        'AppStore' => [
            'url' => 'https://apps.apple.com/app/id%u',
            'regex' =>'/^[0-9]+$/',
            'elements' => [
                'image' => 'img', 
                'title' => 'title',
                'rate' => '.we-customer-ratings__averages__display',
                'price' => 'li[class="inline-list__item inline-list__item--bulleted app-header__list__item--price"]'
            ]
        ]
    ];
    

   	/**
	 * Constructor 
	 * First Check the parameters validity, select the store (Or Auto-Detect by regex)
     * then Check if Curl Extension loaded or not 
	 * before Final step we have check the $AppId 
	 * Load Url, initialize the Dom, lastly Scrap App Data 
	 * @param   string	$AppId
     * @param   string  $TargtedStore = ""
     * @param   string  $AcceptLanguage = ""
	 * @return	bool
	 */
    public function __construct($AppId,$TargtedStore = "",$AcceptLanguage = "")
    {
        if($TargtedStore != '' && !isset($this->AppStores[$TargtedStore])){
            trigger_error("$TargtedStore Is Not Supported");
            return false;
        }elseif(!$this->DetectAppStore($AppId)){
            trigger_error("$AppId Is Not valid AppId");
            return false;
        }
        if (! function_exists('curl_version')){
            trigger_error("Curl is missing");
            return false;
        }
        elseif($TargtedStore != ''){
            $this->TargtedStore = $TargtedStore;
        }
        $this->AppId = $AppId;
        $this->AcceptLanguage = $AcceptLanguage;

        if(!$this->CheckAppId($this->AppId)){
            trigger_error("$this->AppId Is Wrong AppId For $TargtedStore");
            return false;
        }
        $this->AppUrl = sprintf($this->AppStores[$this->TargtedStore]['url'],$AppId);
        if(!$this->LoadUrl($this->AppUrl)){
            return false;
        }
        $this->LoadAppData();
        return true;
    }

    // --------------------------------------------------------------------

    /**
	 * Destructor
     * Call Clear() Method to clear memory due to php5 circular references memory leak...
     * @see Clear()
     * 
	 * @return	void
	 */
    public function __destruct()
    {
        $this->Clear();
    }

    // --------------------------------------------------------------------

     /**
	 * Return array of Current supported App Stores
     * 
	 * @return	array
	 */
    public static function GetAppStores(){
        return array_keys(self::$AppStores);
    }

    // --------------------------------------------------------------------

    /**
	 * Return the AppData (After Scraping)
     * $AppData is encapsulated by this Method (Pseudo Readonly)
     * 
     * @see $AppData
	 * @return	array
	 */
    public function GetAppData(){
        return $this->AppData;
    }

    // --------------------------------------------------------------------

    /**
	 * Return the AppData (After Scraping)
     * $AppData is encapsulated by this Method (Pseudo Readonly)
     * 
     * @see $AppData
	 * @return	array
	 */
    protected function LoadAppData(){
        $DomNode = '';
        foreach($this->AppStores[$this->TargtedStore]['elements'] as $k => $v){
            // Note: we can use php7.2+ '??' Null Coalescing Operator But we need this class to be compatible with older versions
            $tempVar = '';
            $DomNode = $this->Dom->find($v);
            if(strpos($v,'img') !== FALSE){
                $tempVar = $DomNode[0]->src;
                $this->AppData[$k] = (!empty($tempVar)) ? $tempVar: null;
            }elseif(strpos($v,'meta') !== FALSE){
                $tempVar = $DomNode[0]->content;
                $this->AppData[$k] = (!empty($tempVar)) ? $tempVar: null;
            }else{
                $tempVar = $DomNode[0]->plaintext;
                $this->AppData[$k] = (!empty($tempVar)) ? $tempVar: null;
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
	 * @return	mixed
	 */
    protected function DetectAppStore($AppId){
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
	 * Compare Scraped AppData By user-defined data
     * Useful for check if app data is old (image, title, etc..)
     * 
	 * @return	bool
	 */
    public function CompareData($AppData){
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
	 * @return	bool
	 */
    protected function CheckAppId($AppId){
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
	 * @return	void
	 */
    protected function Clear(){
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
	 * @return	bool
	 */
    protected function LoadUrl($url)
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
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 0);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 20);
        $arr['respone'] = curl_exec( $ch );
        $arr['errno']   = curl_errno( $ch );
        $arr['errmsg']  = curl_error( $ch );
        $arr['header']  = curl_getinfo( $ch );
        curl_close( $ch );
        if ( $arr['errno'] != 0 ){
            //error: bad url, timeout, redirect loop ...
            trigger_error("CURL Error: " . $arr['errmsg']);
            return false;
        }
        if ( $arr['header']['http_code'] != 200 ){
            // HTTP Code 200 mean OK outherwise thir is something wrong with the request 
            // Check what's the error code, Report it and exit.
            switch($arr['header']['http_code']){
                case 400:
                    trigger_error("HTTP Error: 400 Bad Request");
                break;
                case 404:
                    trigger_error("HTTP Error: 404 Not Found");
                break;
                case 500:
                    trigger_error("HTTP Error: 500 Internal Server Error");
                break;
                case 503:
                    trigger_error("HTTP Error: 503 Service Unavailable");
                break;
                default:
                    trigger_error("HTTP Error Code : ".$arr['http_code']);
                break;
                    
            }
            return false;
        }
        $this->Dom = HtmlDomParser::str_get_html($arr['respone']);
        return true;
        
    }
}
