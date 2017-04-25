<?php

class wfDashboard {
	const SCAN_SUCCESS = 1;
	const SCAN_FAILED = 0;
	const SCAN_NEVER_RAN = -1;
	const SCAN_WARNINGS = 2;
	
	const FEATURE_ENABLED = 1;
	const FEATURE_DISABLED = 0;
	const FEATURE_PREMIUM = -1;
	
	public $scanLastCompletion;
	public $scanLastStatusMessage;
	public $scanLastStatus;
	
	public $notifications = array();
	
	public $features = array();
	
	public $lastGenerated;
	
	public $tdfCommunity;
	public $tdfPremium;
	public $tdfBlacklist;
	
	public $ips24h;
	public $ips7d;
	public $ips30d;
	
	public $loginsSuccess;
	public $loginsFail;
	
	public $blacklist7d;
	
	public $localBlocks;
	
	public $networkBlock24h;
	public $networkBlock7d;
	public $networkBlock30d;
	
	public $countriesLocal;
	public $countriesNetwork;
	
	public static function updatePOSTParams() {
		if (wfConfig::p()) {
			global $wpdb;
			$topIPs = $wpdb->get_col("SELECT DISTINCT IP FROM (SELECT IP FROM {$wpdb->prefix}wfBlockedIPLog WHERE unixday >= FLOOR(UNIX_TIMESTAMP() / 86400) - 7 AND unixday <= FLOOR(UNIX_TIMESTAMP() / 86400) AND blockType = 'blacklist' ORDER BY blockCount DESC LIMIT 50) AS t ORDER BY IP ASC");
			$topIPs = base64_encode(implode('', $topIPs));
			return array('topBlacklist' => $topIPs);
		}
		return array();
	}
	
	/**
	 * @param array $data The data, parsed from JSON, of the response from noc1.
	 * @param bool|string $blacklistIPs The binary list of IPs that was sent for `topBlacklist` and that the `blacklistCounts` fields will correspond to.
	 */
	public static function processDashboardResponse($data, $blacklistIPs = false) {
		if (isset($data['notifications'])) {
			foreach ($data['notifications'] as $n) {
				if (!isset($n['id']) || !isset($n['priority']) || !isset($n['html'])) {
					continue;
				}
				
				new wfNotification($n['id'], $n['priority'], $n['html'], (isset($n['category']) ? $n['category'] : null));
			}
			
			unset($data['notifications']);
		}
		
		if (isset($data['blacklistCounts']) && is_string($blacklistIPs))  {
			$rawCounts = @base64_decode($data['blacklistCounts']);
			if ((wfUtils::strlen($rawCounts) / 4) == (wfUtils::strlen($blacklistIPs) / 16)) {
				$blacklistCounts = array('updated' => time(), 'counts' => array());
				$offsetIPs = 0;
				$offsetCounts = 0;
				while ($offsetIPs < wfUtils::strlen($blacklistIPs)) {
					$ip = wfUtils::inet_ntop(wfUtils::substr($blacklistIPs, $offsetIPs, 16));
					$countArr = @unpack('V', wfUtils::substr($rawCounts, $offsetCounts, 4));
					$count = (int) @array_shift($countArr);
					$blacklistCounts['counts'][] = array('ip' => $ip, 'network' => $count);
					$offsetIPs += 16;
					$offsetCounts += 4;
				}
				$data['blacklistCounts'] = $blacklistCounts;
			}
			else {
				unset($data['blacklistCounts']);
			}
		}
		
		wfConfig::set_ser('dashboardData', $data);
	}
	
	public function __construct() {
		// Scan values
		$lastScanCompleted = wfConfig::get('lastScanCompleted');
		if ($lastScanCompleted === false || empty($lastScanCompleted)) {
			$this->scanLastStatus = self::SCAN_NEVER_RAN;
		}
		else if ($lastScanCompleted == 'ok') {
			$this->scanLastStatus = self::SCAN_SUCCESS;
			
			$i = new wfIssues();
			$this->scanLastCompletion = (int) $i->getScanTime();
			$issueCount = $i->getIssueCount();
			if ($issueCount) {
				$this->scanLastStatus = self::SCAN_WARNINGS;
				$this->scanLastStatusMessage = "{$issueCount} issue" . ($issueCount == 1 ? ' found' : 's found');
			}
		} 
		else {
			$this->scanLastStatus = self::SCAN_FAILED;
			$n = wfNotification::getNotificationForCategory('wfplugin_scan', false);
			if ($n !== null) {
				$this->scanLastStatusMessage = $n->html;
			}
			else {
				$this->scanLastStatusMessage = esc_html(substr($lastScanCompleted, 0, 100) . (strlen($lastScanCompleted) > 100 ? '...' : ''));
			}
		}
		
		// Notifications
		$this->notifications = wfNotification::notifications();
		
		// Features
		$countryBlocking = self::FEATURE_PREMIUM;
		if (wfConfig::get('isPaid')) {
			$countryBlocking = self::FEATURE_DISABLED;
			$countryList = wfConfig::get('cbl_countries');
			if (!empty($countryList) && (wfConfig::get('cbl_loggedInBlocked', false) || wfConfig::get('cbl_loginFormBlocked', false) || wfConfig::get('cbl_restOfSiteBlocked', false))) {
				$countryBlocking = self::FEATURE_ENABLED;
			}
		}
		
		$this->features = array(
			array('name' => 'Firewall', 'link' => network_admin_url('admin.php?page=WordfenceWAF'), 'state' => !(!WFWAF_ENABLED || (class_exists('wfWAFConfig') && wfWAFConfig::isDisabled())) ? self::FEATURE_ENABLED : self::FEATURE_DISABLED),
			array('name' => 'Extended Protection', 'link' => network_admin_url('admin.php?page=WordfenceWAF'), 'state' => (!(!WFWAF_ENABLED || (class_exists('wfWAFConfig') && wfWAFConfig::isDisabled())) && WFWAF_AUTO_PREPEND) ? self::FEATURE_ENABLED : self::FEATURE_DISABLED),
			array('name' => 'Real-time IP Blacklist', 'link' => network_admin_url('admin.php?page=WordfenceWAF'), 'state' => !wfConfig::get('isPaid') ? self::FEATURE_PREMIUM : (WFWAF_ENABLED && class_exists('wfWAFConfig') && !wfWAFConfig::get('disableWAFBlacklistBlocking') ? self::FEATURE_ENABLED : self::FEATURE_DISABLED)),
			array('name' => 'Login Security', 'link' => network_admin_url('admin.php?page=WordfenceSecOpt#focus-loginSecurityEnabled'), 'state' => wfConfig::get('loginSecurityEnabled') ? self::FEATURE_ENABLED : self::FEATURE_DISABLED),
			array('name' => 'Scheduled Scans', 'link' => network_admin_url('admin.php?page=WordfenceScan#top#scheduling'), 'state' => wordfence::getNextScanStartTimestamp() !== false && wfConfig::get('scheduledScansEnabled') ? self::FEATURE_ENABLED : self::FEATURE_DISABLED),
			array('name' => 'Cellphone Sign-in', 'link' => network_admin_url('admin.php?page=WordfenceTools#top#twofactor'), 'state' => !wfConfig::get('isPaid') ? self::FEATURE_PREMIUM : (wfUtils::hasTwoFactorEnabled() ? self::FEATURE_ENABLED : self::FEATURE_DISABLED)),
			array('name' => 'Live Traffic', 'link' => network_admin_url('admin.php?page=WordfenceActivity'), 'state' => wfConfig::liveTrafficEnabled() ? self::FEATURE_ENABLED : self::FEATURE_DISABLED),
			array('name' => 'Country Blocking', 'link' => network_admin_url('admin.php?page=WordfenceBlocking#top#countryblocking'), 'state' => $countryBlocking),
			array('name' => 'Rate Limiting', 'link' => network_admin_url('admin.php?page=WordfenceSecOpt#focus-firewallEnabled'), 'state' => wfConfig::get('firewallEnabled') ? self::FEATURE_ENABLED : self::FEATURE_DISABLED),
			array('name' => 'Spamvertising Check', 'link' => network_admin_url('admin.php?page=WordfenceSecOpt#focus-spamvertizeCheck'), 'state' => !wfConfig::get('isPaid') ? self::FEATURE_PREMIUM : (wfConfig::get('spamvertizeCheck') ? self::FEATURE_ENABLED : self::FEATURE_DISABLED)),
			array('name' => 'Spam Blacklist Check', 'link' => network_admin_url('admin.php?page=WordfenceSecOpt#focus-checkSpamIP'), 'state' => !wfConfig::get('isPaid') ? self::FEATURE_PREMIUM : (wfConfig::get('checkSpamIP') ? self::FEATURE_ENABLED : self::FEATURE_DISABLED)),
		);
		
		$data = wfConfig::get_ser('dashboardData');
		$lastChecked = wfConfig::get('lastDashboardCheck', 0);
		if ((!is_array($data) || (isset($data['generated']) && $data['generated'] + 3600 < time())) && $lastChecked + 3600 < time()) {
			$wp_version = wfUtils::getWPVersion();
			$apiKey = wfConfig::get('apiKey');
			$api = new wfAPI($apiKey, $wp_version);
			wfConfig::set('lastDashboardCheck', time());
			try {
				if (isset($data['blacklistCounts']) && $data['blacklistCounts']['updated'] > (time() - 86400)) { //Preserve blacklist stats across hourly updates for 24 hours
					$blacklistCounts = $data['blacklistCounts'];
				}
				
				$json = $api->getStaticURL('/stats.json');
				$updated = @json_decode($json, true);
				if ($json && is_array($updated)) {
					self::processDashboardResponse($updated);
					$data = wfConfig::get_ser('dashboardData');
					if (isset($blacklistCounts)) { //Re-insert blacklist stats
						$data['blacklistCounts'] = $blacklistCounts;
						wfConfig::set_ser('dashboardData', $data);
					}
				}
			}
			catch (Exception $e) {
				//Do nothing
			}
		}
		
		// Last Generated
		if (is_array($data) && isset($data['generated'])) {
			$this->lastGenerated = $data['generated'];
		}
		
		// TDF
		if (is_array($data) && isset($data['tdf']) && isset($data['tdf']['community']) && isset($data['tdf']['premium']) && isset($data['tdf']['blacklist'])) {
			$this->tdfCommunity = (int) $data['tdf']['community'];
			$this->tdfPremium = (int) $data['tdf']['premium'];
			$this->tdfBlacklist = (int) $data['tdf']['blacklist'];
		}
		
		// Top IPs Blocked
		$activityReport = new wfActivityReport();
		$this->ips24h = (array) $activityReport->getTopIPsBlocked(100, 1);
		foreach ($this->ips24h as &$r24h) {
			$r24h = (array) $r24h;
			if (empty($r24h['countryName'])) { $r24h['countryName'] = 'Unknown'; }
		}
		$this->ips7d = (array) $activityReport->getTopIPsBlocked(100, 7);
		foreach ($this->ips7d as &$r7d) {
			$r7d = (array) $r7d;
			if (empty($r7d['countryName'])) { $r7d['countryName'] = 'Unknown'; }
		}
		$this->ips30d = (array) $activityReport->getTopIPsBlocked(100, 30);
		foreach ($this->ips30d as &$r30d) {
			$r30d = (array) $r30d;
			if (empty($r30d['countryName'])) { $r30d['countryName'] = 'Unknown'; }
		}
		
		// Recent Logins
		$logins = wordfence::getLog()->getHits('logins', 'loginLogout', 0, 200);
		$this->loginsSuccess = array();
		$this->loginsFail = array();
		foreach ($logins as $l) {
			if ($l['fail']) {
				$this->loginsFail[] = array('t' => $l['ctime'], 'name' => $l['username'], 'ip' => $l['IP']);
			}
			else if ($l['action'] != 'logout') {
				$this->loginsSuccess[] = array('t' => $l['ctime'], 'name' => $l['username'], 'ip' => $l['IP']);
			}
		}
		
		// Blacklist
		if (is_array($data) && isset($data['blacklistCounts']) && wfConfig::p()) {
			$this->blacklist7d = $data['blacklistCounts'];
			$ips = array();
			foreach ($this->blacklist7d['counts'] as $entry) {
				$ips[] = wfUtils::inet_pton($entry['ip']);
			}
			
			$localStats = $activityReport->getBlacklistBlockedStats(7, $ips);
			foreach ($this->blacklist7d['counts'] as &$blacklistEntry) {
				$local = 0;
				$countryName = 'Unknown';
				$countryCode = '';
				foreach ($localStats as $l) {
					if ($l['IP'] == wfUtils::inet_pton($blacklistEntry['ip'])) { 
						$local = $l['blockCount'];
						$countryName = $l['countryName'];
						$countryCode = $l['countryCode'];
						break;
					}
				}
				$blacklistEntry['ip'] = $this->_obfuscateIP($blacklistEntry['ip']);
				$blacklistEntry['local'] = $local;
				$blacklistEntry['countryName'] = $countryName;
				$blacklistEntry['countryCode'] = $countryCode;
			}
			
			usort($this->blacklist7d['counts'], array($this, '_sortBlacklist'));
		}
		
		// Local Attack Data
		$this->localBlocks = array();
		$this->localBlocks[] = array('title' => 'Complex', 
									 '24h' => (int) $activityReport->getBlockedCount(1, wfActivityReport::BLOCK_TYPE_COMPLEX),
									 '7d' => (int) $activityReport->getBlockedCount(7, wfActivityReport::BLOCK_TYPE_COMPLEX),
									 '30d' => (int) $activityReport->getBlockedCount(30, wfActivityReport::BLOCK_TYPE_COMPLEX),
									);
		$this->localBlocks[] = array('title' => 'Brute Force',
									 '24h' => (int) $activityReport->getBlockedCount(1, wfActivityReport::BLOCK_TYPE_BRUTE_FORCE),
									 '7d' => (int) $activityReport->getBlockedCount(7, wfActivityReport::BLOCK_TYPE_BRUTE_FORCE),
									 '30d' => (int) $activityReport->getBlockedCount(30, wfActivityReport::BLOCK_TYPE_BRUTE_FORCE),
									);
		$this->localBlocks[] = array('title' => 'Blacklist',
									 '24h' => (int) $activityReport->getBlockedCount(1, wfActivityReport::BLOCK_TYPE_BLACKLIST),
									 '7d' => (int) $activityReport->getBlockedCount(7, wfActivityReport::BLOCK_TYPE_BLACKLIST),
									 '30d' => (int) $activityReport->getBlockedCount(30, wfActivityReport::BLOCK_TYPE_BLACKLIST),
									);
		
		// Network Attack Data
		if (is_array($data) && isset($data['attackdata']) && isset($data['attackdata']['24h'])) {
			$this->networkBlock24h = $data['attackdata']['24h'];
			$this->networkBlock7d = $data['attackdata']['7d'];
			$this->networkBlock30d = $data['attackdata']['30d'];
		}
		
		// Blocked Countries
		$this->countriesLocal = (array) $activityReport->getTopCountriesBlocked(10, 7);
		foreach ($this->countriesLocal as &$rLocal) {
			$rLocal = (array) $rLocal;
			if (empty($rLocal['countryName'])) { $rLocal['countryName'] = 'Unknown'; }
		}
		
		if (is_array($data) && isset($data['countries']) && isset($data['countries']['7d'])) {
			$networkCountries = array();
			foreach ($data['countries']['7d'] as $rNetwork) {
				$countryCode = $rNetwork['cd'];
				$countryName = $activityReport->getCountryNameByCode($countryCode);
				if (empty($countryName)) { $countryName = 'Unknown'; }
				$totalBlockCount = $rNetwork['ct'];
				$networkCountries[] = array('countryCode' => $countryCode, 'countryName' => $countryName, 'totalBlockCount' => $totalBlockCount);
			}
			$this->countriesNetwork = $networkCountries;
		}
	}
	
	protected function _sortBlacklist($a, $b) {
		if ($a['local'] == $b['local']) { return 0; }
		if ($a['local'] < $b['local']) { return 1; }
		return -1;
	}
	
	protected function _obfuscateIP($ip) {
		if (wfUtils::isIPv6MappedIPv4($ip)) {
			$ip = substr($ip, strrpos($ip, ':') + 1);
		}
		
		if (preg_match('/^(\d+)\.\d+\.\d+\.(\d+)$/', $ip, $matches)) {
			return $matches[1] . '.x.x.' . $matches[2];
		}
		
		$binIP = wfUtils::inet_pton($ip);
		return bin2hex(wfUtils::substr($binIP, 0, 4)) . '::x::' . bin2hex(wfUtils::substr($binIP, -4));
	}
}
