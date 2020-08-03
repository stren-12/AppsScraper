<?php
use Sunra\PhpSimple\HtmlDomParser;

/**
 * AppsScraper
 *  
 * Class to get info about Apps in Apple App Store and Google play store
 * this class use HtmlDomParser Ruther then DOMDocument because it's better in html5 and UTF-8 (See User Contributed Notes below)
 * @link https://www.php.net/manual/en/domdocument.loadhtml.php
 * @version 1.0
 * @author  strn-12 <smagsf@gmail.com>
 */

class Apps_Scraper
{

    public $Dom;
    public $AcceptLanguage = '';
    public $AppStores = [
        'GooglePlay' =>[
            'url' =>'play.google.com' ,
            'elements' => [
            'image' => 'img', 
            'title' => 'h1[class=AHFaub]',
            'rate' => '.BHMmbe',
            'price' => 'meta[itemprop="price"]'
            ]
        ],
        'AppStore' => [
            'url' => 'apps.apple.com',
            'elements' => [
                'image' => 'img', 
                'title' => 'title',
                'rate' => '.we-customer-ratings__averages__display',
                'price' => 'li[class="inline-list__item inline-list__item--bulleted app-header__list__item--price"]'
            ]
        ]
    ];
    public function __construct($App_id,$AcceptLanguage ='')
    {
        
    }
    public function __destruct()
    {
        $this->Clean();
    }

    public function Clean(){
        $this->Dom = null ;
        $this->AcceptLanguage = null;
    }
    protected function LoadUrl($url)
    {
        $arr = [];
        if (! function_exists('curl_version')){
            trigger_error("Curl is missing");
            return false;
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL,$url);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_VERBOSE, 0);
        curl_setopt($ch, CURLOPT_FAILONERROR, true); // Required for HTTP error codes to be reported via our call to curl_error($ch)
        curl_setopt($ch, CURLOPT_HTTPHEADER, array("Accept-Language: ".$this->AcceptLanguage));
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 5.1; .NET CLR 1.0.3705; .NET CLR 1.1.4322; Media Center PC 4.0)');
        curl_setopt($ch, CURLOPT_REFERER, 'http://www.google.com');  //just a fake referer
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 0);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 20);
        $arr['respone'] = curl_exec($ch);
        $arr['errno']   = curl_errno( $ch );
        $arr['errmsg']  = curl_error( $ch );
        $arr['header']  = curl_getinfo( $ch );
        curl_close( $ch );
        if ( $arr['errno'] != 0 ){
            //error: bad url, timeout, redirect loop ...
            trigger_error("CURL Error: " . $arr['errmsg']);
            return false;
        }
        if ( $arr['http_code'] != 200 ){
            // HTTP Code 200 mean OK outherwise thir is something wrong with the request 
            // Check what's the error code, Report it and exit.
            // see: https://developer.mozilla.org/en-US/docs/Web/HTTP/Status
            switch($arr['http_code']){
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

        
    }
}
