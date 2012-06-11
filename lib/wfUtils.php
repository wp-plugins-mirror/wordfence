<?php
require_once('wfConfig.php');
class wfUtils {
	private static $isWindows = false;
	public static $scanLockFH = false;
	public static function makeTimeAgo($secs, $noSeconds = false) {
		if($secs < 1){
			return "a moment";
		}
		$months = floor($secs / (86400 * 30));
		$days = floor($secs / 86400);
		$hours = floor($secs / 3600);
		$minutes = floor($secs / 60);
		if($months) {
			$days -= $months * 30;
			return self::pluralize($months, 'month', $days, 'day');
		} else if($days) {
			$hours -= $days * 24;
			return self::pluralize($days, 'day', $hours, 'hour');
		} else if($hours) {
			$minutes -= $hours * 60;
			return self::pluralize($hours, 'hour', $minutes, 'min');
		} else if($minutes) {
			$secs -= $minutes * 60;
			return self::pluralize($minutes, 'min');
		} else {
			if($noSeconds){
				return "less than a minute";
			} else {
				return floor($secs) . " secs";
			}
		}
	}
	public static function pluralize($m1, $t1, $m2 = false, $t2 = false) {
		if($m1 != 1) {
			$t1 = $t1 . 's';
		}
		if($m2 != 1) {
			$t2 = $t2 . 's';
		}
		if($m1 && $m2){
			return "$m1 $t1 $m2 $t2";
		} else {
			return "$m1 $t1";
		}
	}
	public static function formatBytes($bytes, $precision = 2) { 
		$units = array('B', 'KB', 'MB', 'GB', 'TB'); 

		$bytes = max($bytes, 0); 
		$pow = floor(($bytes ? log($bytes) : 0) / log(1024)); 
		$pow = min($pow, count($units) - 1); 

		// Uncomment one of the following alternatives
		$bytes /= pow(1024, $pow);
		// $bytes /= (1 << (10 * $pow)); 

		return round($bytes, $precision) . ' ' . $units[$pow]; 
	} 
	public static function inet_ntoa($ip){
		$long = 4294967295 - ($ip - 1);
		return long2ip(-$long);
	}
	public static function inet_aton($ip){
		return sprintf("%u", ip2long($ip));
	}
	public static function getBaseURL(){
		return plugins_url() . '/wordfence/';
	}
	public static function getPluginBaseDir(){
		return WP_CONTENT_DIR . '/plugins/';
		//return ABSPATH . 'wp-content/plugins/';
	}
	public static function getIP(){
		$ip = 0;
		if(isset($_SERVER['HTTP_X_FORWARDED_FOR'])){
			$ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
		}
		if((! $ip) && isset($_SERVER['REMOTE_ADDR'])){
			$ip = $_SERVER['REMOTE_ADDR'];
		}
		if(preg_match('/,/', $ip)){
			$parts = explode(',', $ip);
			$ip = trim($parts[0]);
		}
		if(preg_match('/:\d+$/', $ip)){
			$ip = preg_replace('/:\d+$/', '', $ip);
		}
		if(self::isValidIP($ip)){
			return $ip;
		} else {
			$msg = "Wordfence is not able to determine the IP addresses of visitors to your site and can't operate. We received IP: $ip from header1: " . $_SERVER['HTTP_X_FORWARDED_FOR'] . " and header2: " . $_SERVER['REMOTE_ADDR'];
			wordfence::status(1, 'error', $msg);
			error_log($msg);
			exit(0);
		}
	}
	public static function isValidIP($IP){
		if(preg_match('/^(\d+)\.(\d+)\.(\d+)\.(\d+)$/', $IP, $m)){
			if(
				$m[0] >= 0 && $m[0] <= 255 &&
				$m[1] >= 0 && $m[1] <= 255 &&
				$m[2] >= 0 && $m[2] <= 255 &&
				$m[3] >= 0 && $m[3] <= 255
			){
				return true;
			}
		}
		return false;
	}
	public static function getRequestedURL(){
		return ($_SERVER['HTTPS'] ? 'https' : 'http') . '://' . $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI'];
	}

	public static function editUserLink($userID){
		return get_admin_url() . 'user-edit.php?user_id=' . $userID;
	}
	public static function wdie($err){
		$trace=debug_backtrace(); $caller=array_shift($trace); 
		error_log("Wordfence error in " . $caller['file'] . " line " . $caller['line'] . ": $err");
		exit();
	}
	public static function tmpl($file, $data){
		extract($data);
		ob_start();
		include $file;
		return ob_get_contents() . (ob_end_clean() ? "" : "");
	}
	public static function bigRandomHex(){
		return dechex(rand(0, 2147483647)) . dechex(rand(0, 2147483647)) . dechex(rand(0, 2147483647));
	}
	public static function encrypt($str){
		$key = wfConfig::get('encKey');
		if(! $key){
			error_log("Wordfence error: No encryption key found!");
			exit();
		}
		$db = new wfDB();
		return $db->querySingle("select HEX(AES_ENCRYPT('%s', '%s')) as val", $str, $key);
	}
	public static function decrypt($str){
		$key = wfConfig::get('encKey');
		if(! $key){
			error_log("Wordfence error: No encryption key found!");
			exit();
		}
		$db = new wfDB();
		return $db->querySingle("select AES_DECRYPT(UNHEX('%s'), '%s') as val", $str, $key);
	}
	public static function logCaller(){
		$trace=debug_backtrace(); 
		$caller=array_shift($trace); 
		$c2 = array_shift($trace);
		error_log("Caller for " . $caller['file'] . " line " . $caller['line'] . " is " . $c2['file'] . ' line ' . $c2['line']);
	}
	public static function getWPVersion(){
		global $wp_version;
		global $wordfence_wp_version;
		if(isset($wordfence_wp_version)){
			return $wordfence_wp_version;
		} else {
			return $wp_version;
		}
	}
	public static function isAdminPageMU(){
		if(preg_match('/^[\/a-zA-Z0-9\-\_\s\+\~\!\^\.]*\/wp-admin\/network\//', $_SERVER['REQUEST_URI'])){ 
			return true; 
		}
		return false;
	}
	public static function getSiteBaseURL(){
		return rtrim(site_url(), '/') . '/';
	}
	public static function longestLine($data){
		$lines = preg_split('/[\r\n]+/', $data);
		$max = 0;
		foreach($lines as $line){
			$len = strlen($line);
			if($len > $max){
				$max = $len;
			}
		}
		return $max;
	}
	public static function longestNospace($data){
		$lines = preg_split('/[\r\n\s\t]+/', $data);
		$max = 0;
		foreach($lines as $line){
			$len = strlen($line);
			if($len > $max){
				$max = $len;
			}
		}
		return $max;
	}
	public static function requestMaxMemory(){
		if(wfConfig::get('maxMem', false) && (int) wfConfig::get('maxMem') > 0){
			$maxMem = (int) wfConfig::get('maxMem');
		} else {
			$maxMem = 256;
		}
		if( function_exists('memory_get_usage') && ( (int) @ini_get('memory_limit') < $maxMem ) ){
			@ini_set('memory_limit', $maxMem . 'M');
		}
	}
	public static function isAdmin(){
		if(is_multisite()){
			if(current_user_can('manage_network')){
				return true;
			}
		} else {
			if(current_user_can('manage_options')){
				return true;
			}
		}
		return false;
	}
	public static function isWindows(){
		if(! self::$isWindows){
			if(preg_match('/^win/', PHP_OS)){
				self::$isWindows = 'yes';
			} else {
				self::$isWindows = 'no';
			}
		}
		return self::$isWindows == 'yes' ? true : false;
	}
	public static function getScanLock(){
		if(self::isWindows()){
			//Windows does not support non-blocking flock, so we use time. 
			$scanRunning = wfConfig::get('wf_scanRunning');
			if($scanRunning && time() - $scanRunning < WORDFENCE_MAX_SCAN_TIME){
				return false;
			}
			wfConfig::set('wf_scanRunning', time());
			return true;
		} else {
			self::$scanLockFH = fopen(__FILE__, 'r');
			if(flock(self::$scanLockFH, LOCK_EX | LOCK_NB)){
				return true;
			} else {
				return false;
			}
		}
	}
	public static function clearScanLock(){
		if(self::isWindows()){
			wfConfig::set('wf_scanRunning', '');
		} else {
			if(self::$scanLockFH){
				@fclose(self::$scanLockFH);
				self::$scanLockFH = false;
			}
		}

	}
	public static function isScanRunning(){
		$scanRunning = true;
		if(self::getScanLock()){
			$scanRunning = false;
		}
		self::clearScanLock();
		return $scanRunning;
	}
	public static function getIPGeo($IP){ //Works with int or dotted
		
		$locs = self::getIPsGeo(array($IP));
		if(isset($locs[$IP])){
			return $locs[$IP];
		} else {
			return false;
		}
	}
	public static function getIPsGeo($IPs){ //works with int or dotted. Outputs same format it receives.
		$IPs = array_unique($IPs);
		$isInt = false;
		if(strpos($IPs[0], '.') === false){
			$isInt = true;
		}
		$toResolve = array();
		$db = new wfDB();
		global $wp_version;
		global $wpdb;
		$locsTable = $wpdb->base_prefix . 'wfLocs';
		$IPLocs = array();
		foreach($IPs as $IP){
			$r1 = $db->query("select IP, ctime, failed, city, region, countryName, countryCode, lat, lon, unix_timestamp() - ctime as age from " . $locsTable . " where IP=%s", ($isInt ? $IP : self::inet_aton($IP)) );
			if($r1){
				if($row = mysql_fetch_assoc($r1)){
					if($row['age'] > WORDFENCE_MAX_IPLOC_AGE){
						$db->query("delete from " . $locsTable . " where IP=%s", $row['IP']);
					} else {
						if($row['failed'] == 1){
							$IPLocs[$IP] = false;
						} else {
							if(! $isInt){
								$row['IP'] = self::inet_ntoa($row['IP']);
							}
							$IPLocs[$IP] = $row;
						}
					}
				}
			}
			if(! isset($IPLocs[$IP])){
				$toResolve[] = $IP;
			}
		}
		if(sizeof($toResolve) > 0){
			$api = new wfAPI(wfConfig::get('apiKey'), $wp_version); 
			$freshIPs = $api->call('resolve_ips', array(), array(
				'ips' => implode(',', $toResolve)
				));
			if(is_array($freshIPs)){
				foreach($freshIPs as $IP => $value){
					if($value == 'failed'){
						$db->query("insert IGNORE into " . $locsTable . " (IP, ctime, failed) values (%s, unix_timestamp(), 1)", ($isInt ? $IP : self::inet_aton($IP)) );
						$IPLocs[$IP] = false;
					} else {
						$db->query("insert IGNORE into " . $locsTable . " (IP, ctime, failed, city, region, countryName, countryCode, lat, lon) values (%s, unix_timestamp(), 0, '%s', '%s', '%s', '%s', %s, %s)", 
							($isInt ? $IP : self::inet_aton($IP)),
							$value[3], //city
							$value[2], //region
							$value[1], //countryName
							$value[0],//countryCode
							$value[4],//lat
							$value[5]//lon
							);
						$IPLocs[$IP] = array(
							'IP' => $IP,
							'city' => $value[3],
							'region' => $value[2],
							'countryName' => $value[1],
							'countryCode' => $value[0],
							'lat' => $value[4],
							'lon' => $value[5]
							);
					}
				}
			}
		}
		return $IPLocs;
	}
	public function reverseLookup($IP){
		$db = new wfDB();
		global $wpdb;
		$reverseTable = $wpdb->base_prefix . 'wfReverseCache';
		$IPn = wfUtils::inet_aton($IP);
		$host = $db->querySingle("select host from " . $reverseTable . " where IP=%s and unix_timestamp() - lastUpdate < %d", $IPn, WORDFENCE_REVERSE_LOOKUP_CACHE_TIME);
		if(! $host){
			$ptr = implode(".", array_reverse(explode(".",$IP))) . ".in-addr.arpa";
			$host = dns_get_record($ptr, DNS_PTR);
			if($host == null){
				$host = 'NONE';
			} else {
				$host = $host[0]['target'];
			}
			$db->query("insert into " . $reverseTable . " (IP, host, lastUpdate) values (%s, '%s', unix_timestamp()) ON DUPLICATE KEY UPDATE host='%s', lastUpdate=unix_timestamp()", $IPn, $host, $host);
		}
		if($host == 'NONE'){
			return '';
		} else {
			return $host;
		}
	}
}


?>
