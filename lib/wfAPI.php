<?php
require_once('wordfenceConstants.php');
require_once('wordfenceClass.php');
class wfAPI {
	public $lastHTTPStatus = '';
	public $lastCurlErrorNo = '';
	private $curlDataWritten = 0;
	private $curlContent = 0;
	private $APIKey = '';
	private $wordpressVersion = '';
	private static $maintMsg = "The Wordfence scanning server may be down for maintenance.";
	public function __construct($apiKey, $wordpressVersion){
		$this->APIKey = $apiKey;
		$this->wordpressVersion = $wordpressVersion;
	}
	public function call($action, $getParams = array(), $postParams = array()){
		$json = $this->getURL($this->getAPIURL() . '/v' . WORDFENCE_API_VERSION . '/?' . $this->makeAPIQueryString() . '&' . http_build_query(
			array_merge(
				array('action' => $action),
				$getParams	
				)), $postParams);
		if(! $json){
			throw new Exception(self::$maintMsg);
		}

		$dat = json_decode($json, true);
		if(! is_array($dat)){
			throw new Exception("We could not understand the Wordfence API response when calling '$action'.");
		}
		if(is_array($dat) && isset($dat['errorMsg'])){
			throw new Exception($dat['errorMsg']);
		}
		return $dat;
	}
	public function curlWrite($h, $d){
		$this->curlContent .= $d;
		if($this->curlDataWritten > 10000000){ //10 megs
			return 0;
		} else {
			return strlen($d);
		}
	}
	protected function getURL($url, $postParams = array()){
		if(function_exists('curl_init')){
			$this->curlDataWritten = 0;
			$this->curlContent = "";
			$curl = curl_init($url);
			curl_setopt ($curl, CURLOPT_TIMEOUT, 300);
			curl_setopt ($curl, CURLOPT_USERAGENT, "Wordfence.com UA " . (defined('WORDFENCE_VERSION') ? WORDFENCE_VERSION : '[Unknown version]') );
			curl_setopt ($curl, CURLOPT_RETURNTRANSFER, TRUE);
			curl_setopt ($curl, CURLOPT_HEADER, 0);
			curl_setopt ($curl, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt ($curl, CURLOPT_SSL_VERIFYHOST, false);
			curl_setopt ($curl, CURLOPT_WRITEFUNCTION, array($this, 'curlWrite'));
			curl_setopt($curl, CURLOPT_POST, true);
			curl_setopt($curl, CURLOPT_POSTFIELDS, $postParams);
			
			$curlResult = curl_exec($curl);
			$httpStatus = curl_getinfo($curl, CURLINFO_HTTP_CODE);
			$this->lastCurlErrorNo = curl_errno($curl);
			if($httpStatus == 200){
				curl_close($curl);
				return $this->curlContent;
			} else {
				$cerror = curl_error($curl);
				curl_close($curl);
				throw new Exception(self::$maintMsg . " Got HTTP status code [$httpStatus] and curl error: $cerror");
			}
		} else {
			$data = $this->fileGet($url, $postParams);
			if($data === false){
				$err = error_get_last();
				throw new Exception(self::$maintMsg . " Got HTTP error: " . $err);
			}
			return $data;
		}

	}
	private function fileGet($url, $postParams){
		$body = "";
		if(is_array($postParams)){
			$bodyArr = array();
			foreach($postParams as $key => $val){
				$bodyArr[] = urlencode($key) . '=' . urlencode($val);
			}
			$body = implode('&', $bodyArr);
		} else {
			$body = $postParams;
		}
		$opts = array('http' =>
				array(
					'method'  => 'POST',
					'content' => $body,
					'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
					'timeout' => 60
				     )
			     );
		$context = stream_context_create($opts);
		return @file_get_contents($url, false, $context, -1);
	}
	public function binCall($func, $postData){
		$url = $this->getAPIURL() . '/v' . WORDFENCE_API_VERSION . '/?' . $this->makeAPIQueryString() . '&action=' . $func;
		if(function_exists('curl_init')){
			$curl = curl_init($url);
			curl_setopt ($curl, CURLOPT_TIMEOUT, 300);
			//curl_setopt($curl, CURLOPT_VERBOSE, true);
			curl_setopt ($curl, CURLOPT_USERAGENT, "Wordfence");
			curl_setopt ($curl, CURLOPT_RETURNTRANSFER, TRUE);
			curl_setopt ($curl, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt ($curl, CURLOPT_SSL_VERIFYHOST, false);
			curl_setopt($curl, CURLOPT_POST, true);
			if($postData){                  
				curl_setopt($curl, CURLOPT_POSTFIELDS, $postData);
			} else {                        
				curl_setopt($curl, CURLOPT_POSTFIELDS, array());
			}                               
			$data = curl_exec($curl);       
			$httpStatus = curl_getinfo($curl, CURLINFO_HTTP_CODE);
			if($httpStatus != 200){
				$cError = curl_error($curl);
				curl_close($curl);
				throw new Exception(self::$maintMsg . " Got HTTP status [$httpStatus] and curl error: $cError");
			}
		} else {
			$data = $this->fileGet($url, $postData);
			if($data === false){
				throw new Exception(self::$maintMsg . " Got HTTP error: " . error_get_last());
			}
			$httpStatus = '200';
		}
		if(preg_match('/\{.*errorMsg/', $data)){
			$jdat = @json_decode($data, true);
			if(is_array($jdat) && $jdat['errorMsg']){
				throw new Exception($jdat['errorMsg']);
			}
		}
		return array('code' => $httpStatus, 'data' => $data);
	}
	public function makeAPIQueryString(){
		$siteurl = '';
		if(function_exists('get_bloginfo')){
			$siteurl = get_bloginfo('siteurl');
		}
		return http_build_query(array(
			'v' => $this->wordpressVersion, 
			's' => $siteurl, 
			'k' => $this->APIKey
			));
	}
	private function getAPIURL(){
		$ssl_supported = false;
		if(defined('CURL_VERSION_SSL') && function_exists('curl_version')){
			$version = curl_version();
			$ssl_supported = ($version['features'] & CURL_VERSION_SSL);
		}
		return $ssl_supported ? WORDFENCE_API_URL_SEC : WORDFENCE_API_URL_NONSEC;
	}
}

?>
