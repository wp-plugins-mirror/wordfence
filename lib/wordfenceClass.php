<?php
require_once('wordfenceConstants.php');
require_once('wfScanEngine.php');
require_once('wfCrawl.php');
require_once 'Diff.php';
require_once 'Diff/Renderer/Html/SideBySide.php';
require_once 'wfAPI.php';
require_once 'wfIssues.php';
require_once('wfDB.php');
require_once('wfUtils.php');
require_once('wfLog.php');
require_once('wfConfig.php');
require_once('wfSchema.php');
class wordfence {
	protected static $lastURLError = false;
	protected static $curlContent = "";
	protected static $curlDataWritten = 0;
	protected static $hasher = '';
	protected static $itoa64 = './0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
	protected static $ignoreList = false;
	public static $newVisit = false;
	private static $wfLog = false;
	private static $hitID = 0;
	private static $statusStartMsgs = array();
	public static function installPlugin(){
		$schema = new wfSchema();
		$schema->createAll(); //if not exists
		wfConfig::setDefaults(); //If not set
		
		$api = new wfAPI('', wfUtils::getWPVersion());
		$keyData = $api->call('get_anon_api_key');
		if($api->errorMsg){
			die("Error fetching free API key from Wordfence: " . $api->errorMsg);
		}
		if($keyData['ok'] && $keyData['apiKey']){
			wfConfig::set('apiKey', $keyData['apiKey']);
		} else {
			die("Could not understand the response we received from the Wordfence servers when applying for a free API key.");
		}


		if( !wp_next_scheduled( 'wordfence_daily_cron' )){
			wp_schedule_event(time(), 'daily', 'wordfence_daily_cron');
		}
		if( !wp_next_scheduled( 'wordfence_hourly_cron' )){
			wp_schedule_event(time(), 'hourly', 'wordfence_daily_cron');
		}
		update_option('wordfenceActivated', 1);
		$db = new wfDB();

		//Upgrading from 1.5.6 or earlier needs:
		$db->createKeyIfNotExists($prefix . 'wfStatus', 'level', 'k2');

		if(wfConfig::get('isPaid') == 'free'){
			wfConfig::set('isPaid', '');
		}
		wfConfig::set('alertEmailMsgCount', 0);
	}
	public static function uninstallPlugin(){
		update_option('wordfenceActivated', 0);
	}
	public static function hourlyCron(){
		global $wpdb; $p = $wpdb->base_prefix;
		$api = new wfAPI(wfConfig::get('apiKey'), wfUtils::getWPVersion());
		$patData = $api->call('get_known_vuln_pattern');
		if(is_array($patData) && $patData['pat']){
			if(@preg_match($patData['pat'], 'wordfence_test_vuln_match')){
				wfConfig::set('vulnRegex', $pat);
			}
		}
		
		if(wfConfig::get('other_WFNet')){
			$wfdb = new wfDB();
			$q1 = $wfdb->query("select URI from $p"."wfVulnScanners where ctime > unix_timestamp() - 3600 limit 1000");
			$URIs = array();
			while($rec = mysql_fetch_assoc($q1)){
				array_push($URIs, $rec['URI']);
			}
			$wfdb->query("truncate table $p"."wfVulnScanners");
			$this->api->call('send_net_404s', array(), array( 'URIs' => json_encode($URIs) ));

			$q2 = $wfdb->query("select INET_NTOA(IP) as IP from $p"."wfVulnScanners where ctime > unix_timestamp() - 3600");
			$wfdb->query("truncate table $p"."wfVulnScanners");
			$scanCont = "";
			while($rec = mysql_fetch_assoc($q2)){
				$scanCont .= pack('N', ip2long($rec['IP']));
			}

			$q3 = $wfdb->query("select INET_NTOA(IP) as IP from $p"."wfLockedOut where blockedTime > unix_timestamp() - 3600");
			$lockCont = "";
			while($rec = mysql_fetch_assoc($q3)){
				$lockCont .= pack('N', ip2long($rec['IP']));
			}
			$cont = pack('N', strlen($lockCont) / 4) . $lockCont . pack('N', strlen($scanCont) / 4) . $scanCont;

			$resp = $this->api->binCall('get_net_bad_ips', $cont);
			if($resp['code'] == 200){
				$len = strlen($resp['data']);
				$reason = "WFSN: Blocked by Wordfence Security Network";
				$wfdb->query("delete from $p"."wfBlocks where wfsn=1");
				if($len > 0 && $len % 4 == 0){
					for($i = 0; $i < $len; $i += 4){
						list($ipLong) = array_values(unpack('N', substr($resp['data'], $i, 4)));
						$IPStr = long2ip($ipLong);
						self::getLog()->blockIP($IPStr, $reason, true);
					}
				}
			}
		}
	}
	public static function dailyCron(){
		$wfdb = new wfDB();
		global $wpdb; $p = $wpdb->base_prefix;
		$wfdb->query("delete from $p"."wfLocs where ctime < unix_timestamp() - %d", WORDFENCE_MAX_IPLOC_AGE); 
		$wfdb->query("truncate table $p"."wfBadLeechers"); //only uses date that's less than 1 minute old
		$wfdb->query("delete from $p"."wfBlocks where blockedTime + %s < unix_timestamp()", wfConfig::get('blockedTime'));
		$wfdb->query("delete from $p"."wfCrawlers where lastUpdate < unix_timestamp() - (86400 * 7)");

		if(wfConfig::get('liveTraf_hitsMaxSize') && wfConfig::get('liveTraf_hitsMaxSize') > 0){
			$gotTableSize = false;
			$tableSizeQ = $wfdb->query("show table status like '$p"."wfHits'");
			if($tableSizeQ){
				$tableSizeRec = mysql_fetch_assoc($tableSizeQ);
				if($tableSizeRec && isset($tableSizeRec['Data_length']) && $tableSizeRec['Data_length'] > 0){
					$gotTableSize = true;
					if($tableSizeRec['Data_length'] > (wfConfig::get('liveTraf_hitsMaxSize') * 1024 * 1024) ){ //convert to bytes
						$count = $wfdb->querySingle("select count(*) as cnt from $p"."wfHits");
						$wfdb->query("delete from $p"."wfHits order by id asc limit %d", floor($count / 10)); //Delete 10% of rows. If we're still bigger than max, then next delete will reduce by further 10% and so on.
					}
				}
			} else {
				error_log("Wordfence could not get wfHits table data size for cleanup. Query returned false.");
			}
		}
				


		$maxRows = 1000; //affects stuff further down too
		foreach(array('wfLeechers', 'wfScanners') as $table){
			//This is time based per IP so shouldn't get too big
			$wfdb->query("delete from $p"."$table where eMin < ((unix_timestamp() - (86400 * 2)) / 60)");
		}
		$wfdb->query("delete from $p"."wfLockedOut where blockedTime + %s < unix_timestamp()", wfConfig::get('loginSec_lockoutMins') * 60);
		$count2 = $wfdb->querySingle("select count(*) as cnt from $p"."wfLogins");
		if($count2 > 100000){
			$wfdb->query("truncate table $p"."wfLogins"); //in case of Dos
		} else if($count2 > $maxRows){
			$wfdb->query("delete from $p"."wfLogins order by ctime asc limit %d", ($count2 - $maxRows));
		}
		$wfdb->query("delete from $p"."wfReverseCache where unix_timestamp() - lastUpdate > 86400");
		$count3 = $wfdb->querySingle("select count(*) as cnt from $p"."wfThrottleLog");
		if($count3 > 100000){
			$wfdb->query("truncate table $p"."wfThrottleLog"); //in case of DoS
		} else if($count3 > $maxRows){
			$wfdb->query("delete from $p"."wfThrottleLog order by endTime asc limit %d", ($count3 - $maxRows));
		}
		$count4 = $wfdb->querySingle("select count(*) as cnt from $p"."wfStatus");
		if($count4 > 100000){ //max status events we keep. This determines how much gets emailed to us when users sends us a debug report. 
			$wfdb->query("delete from $p"."wfStatus where level != 10 order by ctime asc limit %d", ($count4 - 100000));
			$count5 = $wfdb->querySingle("select count(*) as cnt from $p"."wfStatus where level=10");
			if($count5 > 100){
				$wfdb->query("delete from $p"."wfStatus where level = 10 order by ctime asc limit %d", ($count5 - 100) );
			}
		}

	}
	public static function install_actions(){
		if(defined('MULTISITE') && MULTISITE === true){
			global $blog_id;
			if($blog_id == 1 && get_option('wordfenceActivated') != 1){ return; } //Because the plugin is active once installed, even before it's network activated, for site 1 (WordPress team, why?!)
		}

		//Upgrading from 2.0.3 we changed isPaid from 'free' or 'paid' to true and false
		if(wfConfig::get('isPaid') == 'free'){
			wfConfig::set('isPaid', '');
		}
		//end

		add_action('wordfence_daily_cron', 'wordfence::dailyCron');
		add_action('wordfence_hourly_cron', 'wordfence::hourlyCron');
		add_action('plugins_loaded', 'wordfence::veryFirstAction');
		add_action('wordfence_scheduled_scan','wordfence::startScan');	
		add_action('init', 'wordfence::initAction');
		add_action('template_redirect', 'wordfence::templateRedir');
		add_action('shutdown', 'wordfence::shutdownAction');
		add_action('wp_authenticate','wordfence::authAction');
		add_action('login_init','wordfence::loginInitAction');
		add_action('wp_login','wordfence::loginAction');
		add_action('wp_logout','wordfence::logoutAction');
		add_action('profile_update', 'wordfence::profileUpdateAction', '99', 2);
		add_action('lostpassword_post', 'wordfence::lostPasswordPost', '1');
		add_filter('pre_comment_approved', 'wordfence::preCommentApprovedFilter', '99', 2);
		add_filter('authenticate', 'wordfence::authenticateFilter', 99, 3);
		//html|xhtml|atom|rss2|rdf|comment|export
		add_filter('get_the_generator_html', 'wordfence::genFilter', 99, 2);
		add_filter('get_the_generator_xhtml', 'wordfence::genFilter', 99, 2);
		add_filter('get_the_generator_atom', 'wordfence::genFilter', 99, 2);
		add_filter('get_the_generator_rss2', 'wordfence::genFilter', 99, 2);
		add_filter('get_the_generator_rdf', 'wordfence::genFilter', 99, 2);
		add_filter('get_the_generator_comment', 'wordfence::genFilter', 99, 2);
		add_filter('get_the_generator_export', 'wordfence::genFilter', 99, 2);
		if(is_admin()){
			add_action('admin_init', 'wordfence::admin_init');
			if(is_multisite()){
				if(wfUtils::isAdminPageMU()){
					add_action('network_admin_menu', 'wordfence::admin_menus');
				} //else don't show menu
			} else {
				add_action('admin_menu', 'wordfence::admin_menus');
			}
		}
	}
	public static function ajaxReceiver(){
		if(! self::isAdmin()){
			die(json_encode(array('errorMsg' => "You appear to have logged out or you are not an admin. Please sign-out and sign-in again.")));
		}
		$func = $_POST['action'];
		$nonce = $_POST['nonce'];
		if(! wp_verify_nonce($nonce, 'wp-ajax')){ 
			die(json_encode(array('errorMsg' => "Your browser sent an invalid security token to Wordfence. Please try reloading this page or signing out and in again.")));
		}
		//func is e.g. wordfence_ticker so need to munge it
		$func = str_replace('wordfence_', '', $func);
		$returnArr = call_user_func('wordfence::ajax_' . $func . '_callback');
		if($returnArr === false){
			$returnArr = array('errorMsg' => "Wordfence encountered an internal error executing that request.");
		}
			
		if(! is_array($returnArr)){
			error_log("Function $func did not return an array and did not generate an error.");
			$returnArr = array();
		}
		if(isset($returnARr['nonce'])){
			error_log("Wordfence ajax function return an array with 'nonce' already set. This could be a bug.");
		}
		$returnArr['nonce'] = wp_create_nonce('wp-ajax');
		die(json_encode($returnArr));
	}
	public static function lostPasswordPost(){
		if(self::isLockedOut(wfUtils::getIP())){
			require('wfLockedOut.php');
		}
		$email = $_POST['user_login'];
		if(empty($email)){ return; }
		$user = get_user_by('email', $_POST['user_login']);
		if($user){
			if(wfConfig::get('alertOn_lostPasswdForm')){
				wordfence::alert("Password recovery attempted", "Someone tried to recover the password for user with email address: $email\nTheir IP address was: " . wfUtils::getIP() . "\nTheir hostname was: " . self::getLog()->reverseLookup(wfUtils::getIP()));
			}
		}
		if(wfConfig::get('loginSecurityEnabled')){
			$tKey = 'wffgt_' . wfUtils::inet_aton(wfUtils::getIP());
			$forgotAttempts = get_transient($tKey);
			if($forgotAttempts){
				$forgotAttempts++;
			} else {
				$forgotAttempts = 1;
			}
			if($forgotAttempts >= wfConfig::get('loginSec_maxForgotPasswd')){
				self::lockOutIP(wfUtils::getIP(), "Exceeded the maximum number of tries to recover their password which is set at: " . wfConfig::get('loginSec_maxForgotPasswd'));
				require('wfLockedOut.php');
			}
			set_transient($tKey, $forgotAttempts, wfConfig::get('loginSec_countFailMins') * 60);
		}
	}
	public static function lockOutIP($IP, $reason){
		if(wfConfig::get('alertOn_loginLockout')){
			wordfence::alert("User locked out from signing in", "A user with IP address $IP has been locked out from the signing in or using the password recovery form for the following reason: $reason");
		}
		self::getLog()->lockOutIP(wfUtils::getIP(), $reason);
	}
	public static function isLockedOut($IP){
		return self::getLog()->isIPLockedOut($IP);
	}
	public static function veryFirstAction(){
		$wfFunc = $_GET['_wfsf'];
		if($wfFunc == 'unlockEmail'){
			$email = trim($_POST['email']);
			global $wpdb;
			$ws = $wpdb->get_results("SELECT ID, user_login FROM $wpdb->users");
			$users = array();
			foreach($ws as $user){
				$userDat = get_userdata($user->ID);
				if($userDat->user_level > 7){
					if($email == $userDat->user_email){
						$found = true;
						break;
					}
				}
			}
			if(! $found){
				foreach(wfConfig::getAlertEmails() as $alertEmail){
					if($alertEmail == $email){
						$found = true;
						break;
					}
				}
			}
			if($found){
				$key = wfUtils::bigRandomHex();
				$IP = wfUtils::getIP();
				set_transient('wfunlock_' . $key, $IP, 1800);
				$content = wfUtils::tmpl('email_unlockRequest.php', array(
					'siteName' => get_bloginfo('name', 'raw'),
					'siteURL' => wfUtils::getSiteBaseURL(),
					'unlockHref' => wfUtils::getSiteBaseURL() . '?_wfsf=unlockAccess&key=' . $key,
					'key' => $key,
					'IP' => $IP
					));
				wp_mail($email, "Unlock email requested", $content, "Content-Type: text/html");
			}
			echo "<html><body><h1>Your request was received</h1><p>We received a request to email \"$email\" instructions to unlock their access. If that is the email address of a site administrator or someone on the Wordfence alert list, then they have been emailed instructions on how to regain access to this sytem. The instructions we sent will expire 30 minutes from now.</body></html>";
			exit();
		} else if($wfFunc == 'unlockAccess'){
			if(! preg_match('/^\d+\.\d+\.\d+\.\d+$/', get_transient('wfunlock_' . $_GET['key']))){
				echo "Invalid key provided for authentication.";
				exit();
			}
			/* You can enable this for paranoid security leve.
			if(get_transient('wfunlock_' . $_GET['key']) != wfUtils::getIP()){
				echo "You can only use this link from the IP address you used to generate the unlock email.";
				exit();
			}
			*/
			$wfLog = new wfLog(wfConfig::get('apiKey'), wfUtils::getWPVersion());
			if($_GET['func'] == 'unlockMyIP'){
				$wfLog->unblockIP(wfUtils::getIP());
				$wfLog->unlockOutIP(wfUtils::getIP());
				header('Location: ' . wp_login_url());
				exit();
			} else if($_GET['func'] == 'unlockAllIPs'){
				$wfLog->unblockAllIPs();
				$wfLog->unlockAllIPs();
				header('Location: ' . wp_login_url());
				exit();
			} else if($_GET['func'] == 'disableRules'){
				wfConfig::set('firewallEnabled', 0);
				wfConfig::set('loginSecurityEnabled', 0);
				$wfLog->unblockAllIPs();
				$wfLog->unlockAllIPs();
				header('Location: ' . wp_login_url());
				exit();
			} else {
				echo "Invalid function specified. Please check the link we emailed you and make sure it was not cut-off by your email reader.";
				exit();
			}
		}

		$wfLog = self::getLog();
		$wfLog->firewallBadIPs();
	}
	public static function loginAction($username){
		if(sizeof($_POST) < 1){ return; } //only execute if login form is posted
		if(! $username){ return; }
		$user = get_user_by('login', $username);
		$userID = $user ? $user->ID : 0;
		self::getLog()->logLogin('loginOK', 0, $username);
		if(user_can($userID, 'update_core')){
			if(wfConfig::get('alertOn_adminLogin')){ 
				wordfence::alert("Admin Login", "A user with username \"$username\" who has administrator access signed in to your WordPress site.");
			}
		} else {
			if(wfConfig::get('alertOn_nonAdminLogin')){
				wordfence::alert("User login", "A non-admin user with username \"$username\" signed in to your WordPress site.");
			}
		}
	}
	public static function authenticateFilter($authResult){
		if(wfConfig::get('loginSecurityEnabled')){
			if(is_wp_error($authResult) && $authResult->get_error_code() == 'invalid_username' && wfConfig::get('loginSec_lockInvalidUsers')){
				self::lockOutIP(wfUtils::getIP(), "Used an invalid username to try to sign in.");
				require('wfLockedOut.php');
			}
			$tKey = 'wflginfl_' . wfUtils::inet_aton(wfUtils::getIP());
			if(is_wp_error($authResult) && ($authResult->get_error_code() == 'invalid_username' || $authResult->get_error_code() == 'incorrect_password') ){
				$tries = get_transient($tKey);
				if($tries){
					$tries++;
				} else {
					$tries = 1;
				}
				if($tries >= wfConfig::get('loginSec_maxFailures')){
					self::lockOutIP(wfUtils::getIP(), "Exceeded the maximum number of login failures which is: " . wfConfig::get('loginSec_maxFailures'));
					require('wfLockedOut.php');
				}
				set_transient($tKey, $tries, wfConfig::get('loginSec_countFailMins') * 60);
			} else if(get_class($authResult) == 'WP_User'){
				delete_transient($tKey); //reset counter on success
			}
		}
		if(is_wp_error($authResult) && ($authResult->get_error_code() == 'invalid_username' || $authResult->get_error_code() == 'incorrect_password') && wfConfig::get('loginSec_maskLoginErrors')){
			return new WP_Error( 'incorrect_password', sprintf( __( '<strong>ERROR</strong>: The username or password you entered is incorrect. <a href="%2$s" title="Password Lost and Found">Lost your password</a>?' ), $username, wp_lostpassword_url() ) );
		}
		return $authResult;
	}
	public static function logoutAction(){
		$userID = get_current_user_id();
		$userDat = get_user_by('id', $userID);
		self::getLog()->logLogin('logout', 0, $userDat->user_login); 
	}
	public static function loginInitAction(){
		if(self::isLockedOut(wfUtils::getIP())){
			require('wfLockedOut.php');
		}
	}
	public static function authAction($username){
		if(self::isLockedOut(wfUtils::getIP())){
			require('wfLockedOut.php');
		}
		if(! $username){ return; } 
		$userDat = get_user_by('login', $username);
		if($userDat){
			require_once( ABSPATH . 'wp-includes/class-phpass.php');
			$hasher = new PasswordHash(8, TRUE);
			if(! $hasher->CheckPassword($_POST['pwd'], $userDat->user_pass)){
				self::getLog()->logLogin('loginFailValidUsername', 1, $username); 
			}
		} else {
			self::getLog()->logLogin('loginFailInvalidUsername', 1, $username); 
		}
	}
	public static function getWPFileContent($file, $cType, $cName, $cVersion){
		if($cType == 'plugin'){
			$file = realpath(ABSPATH . $file);
			$file = substr($file, strlen(realpath(dirname(__FILE__) . '/../../')));
			$file = preg_replace('/^\/[^\/]+\//', '', $file);
		} else if($cType == 'theme'){
			$themeDir = substr(WP_CONTENT_DIR, strlen(ABSPATH)) . get_theme_roots();
			$file = preg_replace('#' . $themeDir . '/[^/]+/#', '', $file);
		} else if($cType == 'core'){

		} else {
			return array('errorMsg' => "An invalid type was specified to get file.");
		}
			
		$transKey = 'wf_wpFileContent_' . $file . '_' . $cType . '_' . $cName . '_' . $cVersion;
		$transKey = preg_replace('/[^a-zA-Z0-9\_]+/', '_', $transKey);
		$content = get_site_transient($transKey);
		if($content){ 
			return array('fileContent' => $content);
		}
		$api = new wfAPI(wfConfig::get('apiKey'), wfUtils::getWPVersion());
		$dat = $api->call('get_wp_file_content', array(
			'file' => $file,
			'cType' => $cType,
			'cName' => $cName,
			'cVersion' => $cVersion
			));
		if($api->errorMsg){
			return array('errorMsg' => $api->errorMsg);
		}
		if($dat['contents']){
			set_site_transient($transKey, $dat['contents'], WORDFENCE_TRANSIENTS_TIMEOUT);
			return array('fileContent' => $dat['contents']);
		} else {
			return array('errorMsg' => "We could not fetch a core WordPress file from the Wordfence API.");
		}
	}
	public static function ajax_sendActivityLog_callback(){
		$content = "SITE: " . site_url() . "\nPLUGIN VERSION: " . wfUtils::myVersion() . "\nWP VERSION: " . wfUtils::getWPVersion() . "\nAPI KEY: " . wfConfig::get('apiKey') . "\nADMIN EMAIL: " . get_option('admin_email') . "\nLOG:\n\n";
		$wfdb = new wfDB();
		global $wpdb;
		$p = $wpdb->base_prefix;
		$q = $wfdb->query("select ctime, level, type, msg from $p"."wfStatus order by ctime desc limit 10000");
		while($r = mysql_fetch_assoc($q)){
			if($r['type'] == 'error'){
				$content .= "\n";
			}
			$content .= date(DATE_RFC822, $r['ctime']) . '::' . sprintf('%.4f', $r['ctime']) . ':' . $r['level'] . ':' . $r['type'] . '::' . $r['msg'] . "\n";
		}
		$content .= "\n\n";
		
		ob_start();
		phpinfo();
		$phpinfo = ob_get_contents();
		ob_get_clean();

		$content .= $phpinfo;
		
		wp_mail($_POST['email'], "Wordfence Activity Log", $content);	
		return array('ok' => 1);
	}
	public static function ajax_saveConfig_callback(){
		$opts = wfConfig::parseOptions();
		$emails = array();
		foreach(explode(',', preg_replace('/[\r\n\s\t]+/', '', $opts['alertEmails'])) as $email){
			if(strlen($email) > 0){
				array_push($emails, $email);
			}
		}
		if(sizeof($emails) > 0){
			$badEmails = array();
			foreach($emails as $email){
				if(! preg_match('/^[^@]+@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,8})$/i', $email)){
					array_push($badEmails, $email);
				}
			}
			if(sizeof($badEmails) > 0){
				return array('errorMsg' => "The following emails are invalid: " . implode(', ', $badEmails));
			}
		}
		$opts['apiKey'] = trim($opts['apiKey']);
		if(! preg_match('/^[a-fA-F0-9]+$/', $opts['apiKey'])){
			return array('errorMsg' => "Please enter a valid API key for Wordfence before saving your options.");
		}
		$validUsers = array();
		$invalidUsers = array();
		foreach(explode(',', preg_replace('/[\r\n\s\t]+/', '', $opts['liveTraf_ignoreUsers'])) as $val){
			if(strlen($val) > 0){
				if(get_user_by('login', $val)){
					array_push($validUsers, $val);
				} else {
					array_push($invalidUsers, $val);
				}
			}
		}
		if(sizeof($invalidUsers) > 0){
			return array('errorMsg' => "The following users you selected to ignore in live traffic reports are not valid on this system: " . implode(', ', $invalidUsers));
		}
		if(sizeof($validUsers) > 0){
			$opts['liveTraf_ignoreUsers'] = implode(',', $validUsers);
		} else {
			$opts['liveTraf_ignoreUsers'] = '';
		}

		$validIPs = array();
		$invalidIPs = array();
		foreach(explode(',', preg_replace('/[\r\n\s\t]+/', '', $opts['liveTraf_ignoreIPs'])) as $val){
			if(strlen($val) > 0){
				if(preg_match('/^\d+\.\d+\.\d+\.\d+$/', $val)){
					array_push($validIPs, $val);
				} else {
					array_push($invalidIPs, $val);
				}
			}
		}
		if(sizeof($invalidIPs) > 0){
			return array('errorMsg' => "The following IPs you selected to ignore in live traffic reports are not valid: " . implode(', ', $invalidIPs));
		}
		if(sizeof($validIPs) > 0){
			$opts['liveTraf_ignoreIPs'] = implode(',', $validIPs);
		}
		$reload = '';
		$paidKeyMsg = false;
		if($opts['apiKey'] != wfConfig::get('apiKey')){
			$api = new wfAPI($opts['apiKey'], wfUtils::getWPVersion());
			$res = $api->call('check_api_key', array(), array());
			if($res['ok'] && isset($res['isPaid'])){
				wfConfig::set('apiKey', $opts['apiKey']);
				$reload = 'reload';
				wfConfig::set('isPaid', $res['isPaid']);
				if($res['isPaid']){
					$paidKeyMsg = true;
				}
			} else if($res['errorMsg']){
				return array('errorMsg' => $res['errorMsg']);
			} else {
				return array('errorMsg' => "We could not change your API key. Please try again in a few minutes.");
			}
		}
			
		if(preg_match('/[a-zA-Z0-9\d]+/', $opts['liveTraf_ignoreUA'])){
			$opts['liveTraf_ignoreUA'] = trim($opts['liveTraf_ignoreUA']);
		} else {
			$opts['liveTraf_ignoreUA'] = '';
		}
		if(! $opts['other_WFNet']){	
			$wfdb = new wfDB();
			global $wpdb;
			$p = $wpdb->base_prefix;
			$wfdb->query("delete from $p"."wfBlocks where wfsn=1");
		}
		foreach($opts as $key => $val){
			wfConfig::set($key, $val);
		}
		
		//Clears next scan if scans are disabled. Schedules next scan if enabled.
		$err = self::scheduleNextScan();
		if($err){
			return array('errorMsg' => $err);
		} else {
			return array('ok' => 1, 'reload' => $reload, 'paidKeyMsg' => $paidKeyMsg );
		}
	}
	public static function ajax_clearAllBlocked_callback(){
		$op = $_POST['op'];
		$wfLog = self::getLog();
		if($op == 'blocked'){
			$wfLog->unblockAllIPs();
		} else if($op == 'locked'){
			$wfLog->unlockAllIPs();
		}
		return array('ok' => 1);
	}
	public static function ajax_unlockOutIP_callback(){
		$IP = $_POST['IP'];
		self::getLog()->unlockOutIP($IP);
		return array('ok' => 1);
	}
	public static function ajax_unblockIP_callback(){
		$IP = $_POST['IP'];
		self::getLog()->unblockIP($IP);
		return array('ok' => 1);
	}
	public static function ajax_loadStaticPanel_callback(){
		$mode = $_POST['mode'];
		$wfLog = self::getLog();
		if($mode == 'topScanners' || $mode == 'topLeechers'){
			$results = $wfLog->getLeechers($mode);
		} else if($mode == 'blockedIPs'){
			$results = $wfLog->getBlockedIPs();
		} else if($mode == 'lockedOutIPs'){
			$results = $wfLog->getLockedOutIPs();
		} else if($mode == 'throttledIPs'){
			$results = $wfLog->getThrottledIPs();
		}
		return array('ok' => 1, 'results' => $results);
	}
	public static function ajax_blockIP_callback(){
		$IP = $_POST['IP'];
		if($IP == wfUtils::getIP()){
			return array('err' => 1, 'errorMsg' => "You can't block your own IP address.");
		}
		if(wfConfig::get('neverBlockBG') != 'treatAsOtherCrawlers'){ //Either neverBlockVerified or neverBlockUA is selected which means the user doesn't want to block google 
			if(wfCrawl::verifyCrawlerPTR('/googlebot\.com$/i', $IP)){
				return array('err' => 1, 'errorMsg' => "The IP address you're trying to block belongs to Google. Your options are currently set to not block these crawlers. Change this in Wordfence options if you want to manually block Google.");
			}
		}
		self::getLog()->blockIP($IP, $_POST['reason']);
		return array('ok' => 1);
	}
	public static function ajax_reverseLookup_callback(){
		$ips = explode(',', $_POST['ips']);
		$res = array();
		foreach($ips as $ip){
			$res[$ip] = self::getLog()->reverseLookup($ip);
		}
		return array('ok' => 1, 'ips' => $res);
	}
	public static function ajax_deleteIssue_callback(){
		$wfIssues = new wfIssues();
		$issueID = $_POST['id'];
		$wfIssues->deleteIssue($issueID);
		return array('ok' => 1);
	}
	public static function ajax_updateAllIssues_callback(){
		$op = $_POST['op'];
		$i = new wfIssues();
		if($op == 'deleteIgnored'){
			$i->deleteIgnored();	
		} else if($op == 'deleteNew'){
			$i->deleteNew();
		} else if($op == 'ignoreAllNew'){
			$i->ignoreAllNew();
		} else {
			return array('errorMsg' => "An invalid operation was called.");
		}
		return array('ok' => 1);
	}
	public static function ajax_updateIssueStatus_callback(){
		$wfIssues = new wfIssues();
		$status = $_POST['status'];
		$issueID = $_POST['id'];
		if(! preg_match('/^(?:new|delete|ignoreP|ignoreC)$/', $status)){
			return array('errorMsg' => "An invalid status was specified when trying to update that issue.");
		}
		$wfIssues->updateIssue($issueID, $status);
		return array('ok' => 1);
	}
	public static function ajax_loadIssues_callback(){
		$i = new wfIssues();
		$iss = $i->getIssues();
		return array(
			'issuesLists' => $iss,
			'summary' => $i->getSummaryItems(),
			'lastScanCompleted' => wfConfig::get('lastScanCompleted')
			);
	}
	public static function ajax_ticker_callback(){
		$wfdb = new wfDB();
		global $wpdb;
		$p = $wpdb->base_prefix;

		$serverTime = $wfdb->querySingle("select unix_timestamp()");
		$issues = new wfIssues();
		$jsonData = array(
			'serverTime' => $serverTime,
			'msg' => $wfdb->querySingle("select msg from $p"."wfStatus where level < 3 order by ctime desc limit 1")
			);
		$events = array();
		$alsoGet = $_POST['alsoGet'];
		if(preg_match('/^logList_(404|hit|human|crawler|gCrawler|loginLogout)$/', $alsoGet, $m)){
			$type = $m[1];
			$newestEventTime = $_POST['otherParams'];
			$listType = 'hits';
			if($type == 'loginLogout'){
				$listType = 'logins';
			}
			$events = self::getLog()->getHits($listType, $type, $newestEventTime);
		}
		$jsonData['events'] = $events;
		$jsonData['alsoGet'] = $alsoGet; //send it back so we don't load data if panel has changed
		return $jsonData;
	}
	public static function ajax_activityLogUpdate_callback(){
		$issues = new wfIssues();
		return array(
			'ok' => 1,
			'items' => self::getLog()->getStatusEvents($_POST['lastctime']),
			'currentScanID' => $issues->getScanTime()
			);
	}
	public static function ajax_deleteFile_callback(){
		$issueID = $_POST['issueID'];
		$wfIssues = new wfIssues();
		$issue = $wfIssues->getIssueByID($issueID);
		if(! $issue){
			return array('errorMsg' => "Could not delete file because we could not find that issue.");
		}
		if(! $issue['data']['file']){
			return array('errorMsg' => "Could not delete file because that issue does not appear to be a file related issue.");
		}
		$file = $issue['data']['file'];
		$localFile = ABSPATH . '/' . preg_replace('/^[\.\/]+/', '', $file);
		$localFile = realpath($localFile);
		if(strpos($localFile, ABSPATH) !== 0){
			return array('errorMsg' => "An invalid file was requested for deletion.");
		}
		$filesize = filesize($localFile);
		if(@unlink($localFile)){
			$wfIssues->updateIssue($issueID, 'delete');
			return array(
				'ok' => 1,
				'localFile' => $localFile,
				'file' => $file,
				'filesize' => $filesize
				);
		} else {
			$err = error_get_last();
			return array('errorMsg' => "Could not delete file $file. The error was: " . $err['message']);
		}
	}
	public static function ajax_restoreFile_callback(){
		$issueID = $_POST['issueID'];
		$wfIssues = new wfIssues();
		$issue = $wfIssues->getIssueByID($issueID);
		if(! $issue){
			return array('errorMsg' => "We could not find that issue in our database.");
		}
		$dat = $issue['data'];	
		$result = self::getWPFileContent($dat['file'], $dat['cType'], $dat['cName'], $dat['cVersion']);
		$file = $dat['file'];
		if($result['errorMsg']){
			return $result;
		} else if(! $result['fileContent']){
			return array('errorMsg' => "We could not get the original file to do a repair.");
		}
		
		if(preg_match('/\.\./', $file)){
			return array('errorMsg' => "An invalid file was specified for repair.");
		}
		$localFile = ABSPATH . '/' . preg_replace('/^[\.\/]+/', '', $file);
		$fh = fopen($localFile, 'w');
		if(! $fh){
			$err = error_get_last();
			return array('errorMsg' => "We could not write to that file. The error was: " . $err['message']);
		}
		flock($fh, LOCK_EX);
		$bytes = fwrite($fh, $result['fileContent']);
		flock($fh, LOCK_UN);
		fclose($fh);
		if($bytes < 1){
			return array('errorMsg' => "We could not write to that file. ($bytes bytes written) You may not have permission to modify files on your WordPress server.");
		}
		$wfIssues->updateIssue($issueID, 'delete');
		return array(
			'ok' => 1,
			'file' => $localFile
			);
	}
	public static function ajax_activate_callback(){
		$key = trim($_POST['key']);
		$email = trim($_POST['email']);
		$key = preg_replace('/[^a-fA-F0-9]+/', '', $key);
		if(strlen($key) < 10){
			return array("errorAlert" => "You entered an invalid API key." );
		}
		if(! preg_match('/.+\@.+/', $email)){
			return array("errorAlert" => "Please enter a valid email address where Wordfence can send alerts.");
		}

		wfConfig::set('apiKey', $key);
		wfConfig::set('alertEmails', $email);
		$api = new wfAPI(wfConfig::get('apiKey'), wfUtils::getWPVersion());
		$result = $api->call('activate', array(), array());
		if($api->errorMsg){
			wfConfig::set('apiKey', '');
			return array("errorMsg" => $api->errorMsg );
		}
		if($result['ok'] && isset($result['isPaid'])){
			wfConfig::set('isPaid', $result['isPaid']);
			$err = self::startScan();
			if($err){
				return array('errorMsg' => $err);
			} else {
				return array("ok" => 1);
			}
		} else {
			return array('errorAlert' => "An unknown error occured trying to activate Wordfence. Please try again in a few minutes." );
		}
	}
	public static function ajax_scan_callback(){
		self::status(4, 'info', "Ajax request received to start scan.");
		$err = self::startScan();
		if($err){
			return array('errorMsg' => $err);
		} else {
			return array("ok" => 1);
		}
	}
	public static function startScan(){
		self::status(4, 'info', "Entering start scan routine");
		$cron_url = plugins_url('wordfence/wfscan.php');
		self::status(4, 'info', "Cron URL is: " . $cron_url);
		$cronKey = wfUtils::bigRandomHex();
		self::status(4, 'info', "cronKey is: " . $cronKey);
		wfConfig::set('currentCronKey', time() . ',' . $cronKey);
		self::status(4, 'info', "cronKey is set");
		$result = wp_remote_post( $cron_url, array(
			'timeout' => 0.5, 
			'blocking' => true, 
			'sslverify' => false,
			'headers' => array(
				'x-wordfence-cronkey' => $cronKey
				)
			) );
		$procResp = self::processResponse($result);				
		if($procResp){ return $procResp; }
		//If the currentCronKey was eaten, then cron executed so return
		wfConfig::clearCache(); if(! wfConfig::get('currentCronKey')){ 
			self::status(4, 'info', "cronkey is empty so cron executed. Returning.");
			return false; 
		}

		//This second request is for hosts that don't know their own name. i.e. they don't have example.com in their hosts file or DNS pointing to their own IP address or loopback address. So we throw a hail mary to loopback.
		self::status(4, 'info', "cronkey is still set so sleeping for 0.2 seconds and checking again before trying another approach");
		usleep(200000);
		wfConfig::clearCache();
		if(wfConfig::get('currentCronKey')){ //cron key is still set, so cron hasn't executed yet. Maybe the request didn't go through
			self::status(4, 'info', "cronkey is still set so about to manually set host header and try again");
			$cron_url = preg_replace('/^(https?):\/\/[^\/]+/', '$1://127.0.0.1', $cron_url);
			self::status(4, 'info', "cron url is: $cron_url");
			$siteURL = site_url();
			self::status(4, 'info', "siteURL is: $siteURL");
			if(preg_match('/^https?:\/\/([^\/]+)/i', site_url(), $matches)){
				$host = $matches[1];
				self::status(4, 'info', "Extracted host $host from siteURL and trying remote post with manual host header set.");
				$result = wp_remote_post( $cron_url, array(
					'timeout' => 0.5, 
					'blocking' => true, 
					'sslverify' => false,
					'headers' => array(
						'x-wordfence-cronkey' => $cronKey,
						'Host' => $host
						)
					) );
				$procResp = self::processResponse($result);				
				if($procResp){ return $procResp; }
			}
		}
		return false;
	}
	public function processResponse($result){
		if((! is_wp_error($result)) && is_array($result) && empty($result['body']) === false){
			if(strpos($result['body'], 'WFSOURCEVISIBLE') !== false){
				self::status(4, 'info', "wfscan.php source is visible.");
				$msg = "Wordfence can't run because the source code of your WordPress plugin files is visible from the Internet. This is a serious security risk which you need to fix. Please look for .htaccess files in your WordPress root directory and your wp-content/ and wp-content/plugins/ directories that may contain malicious code designed to reveal your site source code to a hacker.";
				$htfiles = array();
				if(file_exists(ABSPATH . 'wp-content/.htaccess')){
					array_push($htfiles, '<a href="' . wfUtils::getSiteBaseURL() . '?_wfsf=view&nonce=' . wp_create_nonce('wp-ajax') . '&file=wp-content/.htaccess" target="_blank">wp-content/.htaccess</a>');
				}
				if(file_exists(ABSPATH . 'wp-content/plugins/.htaccess')){
					array_push($htfiles, '<a href="' . wfUtils::getSiteBaseURL() . '?_wfsf=view&nonce=' . wp_create_nonce('wp-ajax') . '&file=wp-content/plugins/.htaccess" target="_blank">wp-content/plugins/.htaccess</a>');
				}
				if(sizeof($htfiles) > 0){
					$msg .= "<br /><br />Click to view the .htaccess files below that may be the cause of this problem:<br />" . implode('<br />', $htfiles);
				}
				return $msg;	
					
			} else if(strpos($result['body'], '{') !== false && strpos($result['body'], 'errorMsg') !== false){
				self::status(4, 'info', "Got response from cron containing json");
				$resp = json_decode($result['body'], true);
				if(empty($resp['errorMsg']) === false){
					self::status(4, 'info', "Got an error message from cron: " . $resp['errorMsg']);
					return $resp['errorMsg'];
				}
			}
		}
		return false;
	}
	public static function templateRedir(){
		$wfFunc = get_query_var('_wfsf');		
		$wfLog = self::getLog();
		if($wfLog->logHitOK()){
			if(is_404() ){
				$wfLog->logLeechAndBlock('404');
			} else {
				$wfLog->logLeechAndBlock('hit');
			}
			if(wfConfig::get('liveTrafficEnabled')){ 
				self::$hitID = $wfLog->logHit();
				add_action('wp_head', 'wordfence::wp_head');
			}
		}

		if(! ($wfFunc == 'diff' || $wfFunc == 'view' || $wfFunc == 'sysinfo' || $wfFunc == 'IPTraf' || $wfFunc == 'viewActivityLog')){
			return;
		}
		if(! self::isAdmin()){
			return;
		}

		$nonce = $_GET['nonce'];
		if(! wp_verify_nonce($nonce, 'wp-ajax')){
			echo "Bad security token. Please sign out and sign-in again.";
			exit(0);
		}
		if($wfFunc == 'diff'){
			self::wfFunc_diff();
		} else if($wfFunc == 'view'){
			self::wfFunc_view();
		} else if($wfFunc == 'sysinfo'){
			require('sysinfo.php');
		} else if($wfFunc == 'IPTraf'){
			self::wfFunc_IPTraf();
		} else if($wfFunc == 'viewActivityLog'){
			self::wfFunc_viewActivityLog();
		}
		exit(0);
	}
	public static function wp_head(){
		echo '<script type="text/javascript">var wfHTImg = new Image(); wfHTImg.src="' . wfUtils::getBaseURL() . 'visitor.php?hid=' . wfUtils::encrypt(self::$hitID) . '";</script>';
	}
	public static function shutdownAction(){
	}
	public static function wfFunc_viewActivityLog(){
		require('viewFullActivityLog.php');
		exit(0);
	}
	public static function wfFunc_IPTraf(){
		$IP = $_GET['IP'];
		$reverseLookup = self::getLog()->reverseLookup($IP);
		if(! preg_match('/^\d+\.\d+\.\d+\.\d+$/', $IP)){
			echo "An invalid IP address was specified.";
			exit(0);
		}
		$wfLog = new wfLog(wfConfig::get('apiKey'), wfUtils::getWPVersion());
		$results = array_merge(
			$wfLog->getHits('hits', 'hit', 0, 10000, $IP), 
			$wfLog->getHits('hits', '404', 0, 10000, $IP)
			);
		usort($results, 'wordfence::iptrafsort');
		for($i = 0; $i < sizeof($results); $i++){
			if(array_key_exists($i + 1, $results)){
				$results[$i]['timeSinceLastHit'] = sprintf('%.4f', $results[$i]['ctime'] - $results[$i + 1]['ctime']);
			} else {
				$results[$i]['timeSinceLastHit'] = '';
			}
		}
		require('IPTraf.php');
		exit(0);
	}
	public static function iptrafsort($b, $a){
		if($a['ctime'] == $b['ctime']){ return 0; }
		return ($a['ctime'] < $b['ctime']) ? -1 : 1;
	}
	public static function wfFunc_view(){
		$localFile = ABSPATH . '/' . preg_replace('/^[\.\/]+/', '', $_GET['file']);
		if(strpos($localFile, '..') !== false){
			echo "Invalid file requested. (Relative paths not allowed)";
			exit();
		}
		$lang = false;
		$cont = @file_get_contents($localFile);
		$isEmpty = false;
		if(! $cont){
			if(file_exists($localFile) && filesize($localFile) === 0){
				$isEmpty = true;
			} else {
				$err = error_get_last();
				echo "We could not open the requested file for reading. The error was: " . $err['message'];
				exit(0);
			}
		}
		$fileMTime = @filemtime($localFile);
		$fileMTime = date('l jS \of F Y h:i:s A', $fileMTime);
		$fileSize = @filesize($localFile);
		$fileSize = number_format($fileSize, 0, '', ',') . ' bytes';

		require 'wfViewResult.php';
		exit(0);
	}
	public static function wfFunc_diff(){
		$result = self::getWPFileContent($_GET['file'], $_GET['cType'], $_GET['cName'], $_GET['cVersion']);
		if($result['errorMsg']){
			echo $result['errorMsg'];
			exit(0);
		} else if(! $result['fileContent']){
			echo "We could not get the contents of the original file to do a comparison.";
			exit(0);
		}

		$localFile = realpath(ABSPATH . '/' . preg_replace('/^[\.\/]+/', '', $_GET['file']));
		if(strpos($localFile, ABSPATH) !== 0){
			echo "An invalid file was requested for comparison.";
			exit(0);
		}
		$diffOptions = array();
		$localContents = file_get_contents($localFile);
		
		$diff = new Diff(
			//Treat DOS and Unix files the same
			preg_split("/(?:\r\n|\n)/", $result['fileContent']), 
			preg_split("/(?:\r\n|\n)/", $localContents), 
			array()
			);
		$renderer = new Diff_Renderer_Html_SideBySide;
		$diffResult = $diff->Render($renderer);
		require 'diffResult.php';
		exit(0);
	}
	public static function initAction(){
		global $wp;
		$wp->add_query_var('_wfsf');
		//add_rewrite_rule('wfStaticFunc/([a-zA-Z0-9]+)/?$', 'index.php?wfStaticFunc=' . $matches[1], 'top');
		$cookieName = 'wfvt_' . crc32(site_url());
		$c = isset($_COOKIES[$cookieName]) ? isset($_COOKIES[$cookieName]) : false;
		if($c){
			self::$newVisit = false;
		} else {
			self::$newVisit = true;
		}
		setcookie($cookieName, uniqid(), time() + 1800, '/');
	}
	public static function admin_init(){
		if(! self::isAdmin()){ return; }

		foreach(array('activate', 'scan', 'sendActivityLog', 'restoreFile', 'deleteFile', 'removeExclusion', 'activityLogUpdate', 'ticker', 'loadIssues', 'updateIssueStatus', 'deleteIssue', 'updateAllIssues', 'reverseLookup', 'unlockOutIP', 'unblockIP', 'blockIP', 'loadStaticPanel', 'saveConfig', 'clearAllBlocked') as $func){
			add_action('wp_ajax_wordfence_' . $func, 'wordfence::ajaxReceiver');
		}
		wp_enqueue_style('wordfence-main-style', WP_PLUGIN_URL . '/wordfence/css/main.css', '', wfUtils::myVersion());
		wp_enqueue_style('wordfence-colorbox-style', WP_PLUGIN_URL . '/wordfence/css/colorbox.css', '', wfUtils::myVersion());
		wp_enqueue_style('wordfence-dttable-style', WP_PLUGIN_URL . '/wordfence/css/dt_table.css', '', wfUtils::myVersion());

		wp_enqueue_script('json2');
		wp_enqueue_script('jquery.tmpl', wfUtils::getBaseURL() . 'js/jquery.tmpl.min.js', array('jquery'), wfUtils::myVersion());
		wp_enqueue_script('jquery.colorbox', wfUtils::getBaseURL() . 'js/jquery.colorbox-min.js', array('jquery'), wfUtils::myVersion());
		wp_enqueue_script('jquery.dataTables', wfUtils::getBaseURL() . 'js/jquery.dataTables.min.js', array('jquery'), wfUtils::myVersion());
		//wp_enqueue_script('jquery.tools', wfUtils::getBaseURL() . 'js/jquery.tools.min.js', array('jquery'));
		wp_enqueue_script('wordfenceAdminjs', wfUtils::getBaseURL() . 'js/admin.js', array('jquery'), wfUtils::myVersion());
		wp_localize_script('wordfenceAdminjs', 'WordfenceAdminVars', array(
			'ajaxURL' => admin_url('admin-ajax.php'),
			'firstNonce' => wp_create_nonce('wp-ajax'),
			'siteBaseURL' => wfUtils::getSiteBaseURL(),
			'debugOn' => wfConfig::get('debugOn', 0)
			));

	}
	public static function configure_warning(){
		if(! preg_match('/WordfenceSecOpt/', $_SERVER['REQUEST_URI'])){
			$numRun = wfConfig::get('alertEmailMsgCount', 0);
			if($numRun <= 3){
				echo '<div id="wordfenceConfigWarning" class="updated fade"><p><strong>Please set up an email address to receive Wordfence security alerts</strong> on the <a href="admin.php?page=WordfenceSecOpt">Wordfence Options Page</a>. This message will appear ' . (3 - $numRun) . ' more times.</p></div>';
				wfConfig::set('alertEmailMsgCount', ++$numRun);
			}

		}
	}
	public static function admin_menus(){
		if(! self::isAdmin()){ return; }
		if(! wfConfig::get('alertEmails')){
			if(wfUtils::isAdminPageMU()){
				add_action('network_admin_notices', 'wordfence::configure_warning');
			} else {
				add_action('admin_notices', 'wordfence::configure_warning');
			}
		}
		add_submenu_page("Wordfence", "Scan", "Scan", "activate_plugins", "Wordfence", 'wordfence::menu_scan');
		add_menu_page('Wordfence', 'Wordfence', 'activate_plugins', 'Wordfence', 'wordfence::menu_scan', WP_PLUGIN_URL . '/wordfence/images/wordfence-logo-16x16.png', 'div'); 
		if(wfConfig::get('liveTrafficEnabled')){
			add_submenu_page("Wordfence", "Live Traffic", "Live Traffic", "activate_plugins", "WordfenceActivity", 'wordfence::menu_activity');
		}
		add_submenu_page('Wordfence', 'Blocked IPs', 'Blocked IPs', 'activate_plugins', 'WordfenceBlockedIPs', 'wordfence::menu_blockedIPs');
		add_submenu_page("Wordfence", "Options", "Options", "activate_plugins", "WordfenceSecOpt", 'wordfence::menu_options');
	}
	public static function menu_options(){
		require 'menu_options.php';
	}
	public static function menu_blockedIPs(){
		require 'menu_blockedIPs.php';
	}
	public static function menu_config(){
		require 'menu_config.php';
	}
	public static function menu_activity(){
		require 'menu_activity.php';
	}
	public static function menu_scan(){
		require 'menu_scan.php';
	}
	public static function isAdmin(){
		if(is_multisite()){
			if(current_user_can('manage_network')){
				return true;
			}
		} else {
			if(current_user_can('update_core')){
				return true;
			}
		}
		return false;
	}
	public static function status($level /* 1 has highest visibility */, $type /* info|error */, $msg){
		if($type != 'info' && $type != 'error'){ error_log("Invalid status type: $type"); return; }
		self::getLog()->addStatus($level, $type, $msg);
	}
	public static function profileUpdateAction($userID, $newDat){
		if(wfConfig::get('other_pwStrengthOnUpdate')){
			$oldDat = get_userdata($userID);
			if($newDat->user_pass != $oldDat->user_pass){
				$wf = new wfScanEngine();	
				$wf->scanUserPassword($userID);
				$wf->emailNewIssues();
			}
		}
	}
	public static function genFilter($gen, $type){
		if(wfConfig::get('other_hidegetWPVersion')){
			return '';
		} else {
			return $gen;
		}
	}
	public static function preCommentApprovedFilter($approved, $cData){
		if( $approved == 1 && (! is_user_logged_in()) && wfConfig::get('other_noAnonMemberComments') ){
			$user = get_user_by('email', trim($cData['comment_author_email']));
			if($user){
				return 0; //hold for moderation if the user is not signed in but used a members email
			}
		}
		
		if(($approved == 1 || $approved == 0) && wfConfig::get('other_scanComments')){
			$wf = new wfScanEngine();
			if($wf->isBadComment($cData['comment_author'], $cData['comment_author_email'], $cData['comment_author_url'],  $cData['comment_author_IP'], $cData['comment_content'])){
				return 'spam';
			}
		}
		return $approved;
	}
	public static function getMyHomeURL(){
		return admin_url('admin.php?page=Wordfence', 'http');
	}
	public static function getMyOptionsURL(){
		return admin_url('admin.php?page=WordfenceSecOpt', 'http');
	}

	public static function alert($subject, $alertMsg){
		$content = wfUtils::tmpl('email_genericAlert.php', array(
			'subject' => $subject,
			'blogName' => get_bloginfo('name', 'raw'),
			'alertMsg' => $alertMsg,
			'date' => date('l jS \of F Y \a\t h:i:s A'),
			'myHomeURL' => self::getMyHomeURL(),
			'myOptionsURL' => self::getMyOptionsURL()
			));
		$emails = wfConfig::getAlertEmails();
		if(sizeof($emails) < 1){ return; }
		$subject = "[Wordfence Alert] " . $subject;
		wp_mail(implode(',', $emails), $subject, $content);
	}
	public static function scheduleNextScan($force = false){
		if(wfConfig::get('scheduledScansEnabled')){
			$nextScan = wp_next_scheduled('wordfence_scheduled_scan');
			if((! $force) && $nextScan && $nextScan - time() > 0){
				//scan is already scheduled for the future
				return;
			}
			$api = new wfAPI(wfConfig::get('apiKey'), wfUtils::getWPVersion());
			$result = $api->call('get_next_scan_time', array(), array());
			if(empty($result['errorMsg']) === false){
				return $result['errorMsg'];
			}
			$secsToGo = 3600 * 6; //In case we can't contact the API, schedule next scan 6 hours from now.
			if(is_array($result) && $result['secsToGo'] > 1){
				$secsToGo = $result['secsToGo'];
			}
			wp_clear_scheduled_hook('wordfence_scheduled_scan');
			wp_schedule_single_event(time() + $secsToGo, 'wordfence_scheduled_scan');
		} else {
			wp_clear_scheduled_hook('wordfence_scheduled_scan');
		}
	}
	private static function getLog(){
		if(! self::$wfLog){
			$wfLog = new wfLog(wfConfig::get('apiKey'), wfUtils::getWPVersion());
			self::$wfLog = $wfLog;
		}
		return self::$wfLog;
	}
	public static function statusStart($msg){
		self::$statusStartMsgs[] = $msg;
		self::status(10, 'info', 'SUM_START:' . $msg);
		return sizeof(self::$statusStartMsgs) - 1;
	}
	public static function statusEnd($idx, $haveIssues){
		if($haveIssues){
			self::status(10, 'info', 'SUM_ENDBAD:' . self::$statusStartMsgs[$idx]);
		} else {
			self::status(10, 'info', 'SUM_ENDOK:' . self::$statusStartMsgs[$idx]);
		}
		self::$statusStartMsgs[$idx] = '';
	}
	public static function statusEndErr(){
		for($i = 0; $i < sizeof(self::$statusStartMsgs); $i++){
			if(empty(self::$statusStartMsgs[$i]) === false){
				self::status(10, 'info', 'SUM_ENDERR:' . self::$statusStartMsgs[$i]);
				self::$statusStartMsgs[$i] = '';
			}
		}
	}
	public static function statusDisabled($msg){
		self::status(10, 'info', "SUM_DISABLED:" . $msg);
	}
	public static function statusPaidOnly($msg){
		self::status(10, 'info', "SUM_PAIDONLY:" . $msg);
	}
	public static function wfSchemaExists(){
		$db = new wfDB();
		global $wpdb; $prefix = $wpdb->base_prefix;
		$exists = $db->querySingle("show tables like '$prefix"."wfConfig'");
		return $exists ? true : false;
	}
}
?>
