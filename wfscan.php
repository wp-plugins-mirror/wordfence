<?php
ignore_user_abort(true);
$wordfence_wp_version = false;
if ( !defined('ABSPATH') ) {
	/** Set up WordPress environment */
	if($_SERVER['SCRIPT_FILENAME']){
		$wfBaseDir = preg_replace('/[^\/]+\/[^\/]+\/[^\/]+\/wfscan\.php$/', '', $_SERVER['SCRIPT_FILENAME']);
		require_once($wfBaseDir . 'wp-load.php');
		global $wp_version;
		global $wordfence_wp_version;
		require($wfBaseDir . 'wp-includes/version.php');
		$wordfence_wp_version = $wp_version;
	} else {
		require_once('../../../wp-load.php');
		require_once('../../../wp-includes/version.php');
	}
}
require_once('lib/wordfenceConstants.php');
require_once('lib/wfScanEngine.php');

class wfScan {
	public static function wfScanMain(){
		if(! $_SERVER['HTTP_X_WORDFENCE_CRONKEY']){ 
			self::errorExit("The Wordfence scanner did not receive the x_wordfence_cronkey secure header.");
		}
		$currentCronKey = wfConfig::get('currentCronKey', false);
		if(! $currentCronKey){
			self::errorExit("Wordfence could not find a saved cron key to start the scan.");
		}

		$savedKey = explode(',',$currentCronKey);
		if(time() - $savedKey[0] > 60){ 
			self::errorExit("The key used to start a scan has expired.");
		} //keys only last 60 seconds and are used within milliseconds of creation
		if($savedKey[1] != $_SERVER['HTTP_X_WORDFENCE_CRONKEY']){ 
			self::errorExit("Wordfence could not start a scan because the cron key does not match the saved key.");
		}
		wfConfig::set('currentCronKey', '');
		ini_set('max_execution_time', 1800); //30 mins
		self::becomeAdmin();

		$scanRunning = wfConfig::get('wf_scanRunning');
		if($scanRunning && time() - $scanRunning < WORDFENCE_MAX_SCAN_TIME){
			self::errorExit("There is already a scan running.");
		}
		wfConfig::set('wf_scanRunning', time());
		register_shutdown_function('wfScan::clearScan');

		$scan = new wfScanEngine();
		$scan->go();
		wfConfig::set('wf_scanRunning', '');
	}
	private static function errorExit($msg){
		echo json_encode(array('errorMsg' => $msg)); 
		exit();	
	}
	public static function becomeAdmin(){
		global $wpdb;
		$ws = $wpdb->get_results("SELECT ID, user_login FROM $wpdb->users");
		$users = array();
		foreach($ws as $user){
			$userDat = get_userdata($user->ID);
			array_push($users, array(
				'id' => $user->ID,
				'user_login' => $user->user_login,
				'level' => $userDat->user_level
				));
		}
		usort($users, 'wfScan::usort');
		wp_set_current_user($users[0]['id'], $users[0]['user_login']);
	}
	public static function usort($b, $a){
		if($a['level'] == $b['level']){ return 0; }
		return ($a['level'] < $b['level']) ? -1 : 1;
	}
	public static function clearScan(){
		wfConfig::set('wf_scanRunning', '');
	}
}
wfScan::wfScanMain();
?>
