<?php
require_once('wfAPI.php');
require_once('wfArray.php');
class wordfenceURLHoover {
	private $debug = false;
	public $errorMsg = false;
	private $hostsToAdd = false;
	private $table = '';
	private $apiKey = false;
	private $wordpressVersion = false;
	private $useDB = true;
	private $hostKeys = array();
	private $hostList = array();
	public $currentHooverID = false;
	private $_foundSome = false;
	private $dRegex = 'AAA|AARP|ABB|ABBOTT|ABBVIE|ABOGADO|ABUDHABI|AC|ACADEMY|ACCENTURE|ACCOUNTANT|ACCOUNTANTS|ACO|ACTIVE|ACTOR|AD|ADAC|ADS|ADULT|AE|AEG|AERO|AETNA|AF|AFL|AG|AGAKHAN|AGENCY|AI|AIG|AIRFORCE|AIRTEL|AKDN|AL|ALIBABA|ALIPAY|ALLFINANZ|ALLY|ALSACE|AM|AMICA|AMSTERDAM|ANALYTICS|ANDROID|ANQUAN|AO|APARTMENTS|APP|APPLE|AQ|AQUARELLE|AR|ARAMCO|ARCHI|ARMY|ARPA|ARTE|AS|ASIA|ASSOCIATES|AT|ATTORNEY|AU|AUCTION|AUDI|AUDIO|AUTHOR|AUTO|AUTOS|AVIANCA|AW|AWS|AX|AXA|AZ|AZURE|BA|BABY|BAIDU|BAND|BANK|BAR|BARCELONA|BARCLAYCARD|BARCLAYS|BAREFOOT|BARGAINS|BAUHAUS|BAYERN|BB|BBC|BBVA|BCG|BCN|BD|BE|BEATS|BEER|BENTLEY|BERLIN|BEST|BET|BF|BG|BH|BHARTI|BI|BIBLE|BID|BIKE|BING|BINGO|BIO|BIZ|BJ|BLACK|BLACKFRIDAY|BLOG|BLOOMBERG|BLUE|BM|BMS|BMW|BN|BNL|BNPPARIBAS|BO|BOATS|BOEHRINGER|BOM|BOND|BOO|BOOK|BOOTS|BOSCH|BOSTIK|BOT|BOUTIQUE|BR|BRADESCO|BRIDGESTONE|BROADWAY|BROKER|BROTHER|BRUSSELS|BS|BT|BUDAPEST|BUGATTI|BUILD|BUILDERS|BUSINESS|BUY|BUZZ|BV|BW|BY|BZ|BZH|CA|CAB|CAFE|CAL|CALL|CAMERA|CAMP|CANCERRESEARCH|CANON|CAPETOWN|CAPITAL|CAR|CARAVAN|CARDS|CARE|CAREER|CAREERS|CARS|CARTIER|CASA|CASH|CASINO|CAT|CATERING|CBA|CBN|CC|CD|CEB|CENTER|CEO|CERN|CF|CFA|CFD|CG|CH|CHANEL|CHANNEL|CHASE|CHAT|CHEAP|CHLOE|CHRISTMAS|CHROME|CHURCH|CI|CIPRIANI|CIRCLE|CISCO|CITIC|CITY|CITYEATS|CK|CL|CLAIMS|CLEANING|CLICK|CLINIC|CLINIQUE|CLOTHING|CLOUD|CLUB|CLUBMED|CM|CN|CO|COACH|CODES|COFFEE|COLLEGE|COLOGNE|COM|COMMBANK|COMMUNITY|COMPANY|COMPARE|COMPUTER|COMSEC|CONDOS|CONSTRUCTION|CONSULTING|CONTACT|CONTRACTORS|COOKING|COOL|COOP|CORSICA|COUNTRY|COUPON|COUPONS|COURSES|CR|CREDIT|CREDITCARD|CREDITUNION|CRICKET|CROWN|CRS|CRUISES|CSC|CU|CUISINELLA|CV|CW|CX|CY|CYMRU|CYOU|CZ|DABUR|DAD|DANCE|DATE|DATING|DATSUN|DAY|DCLK|DDS|DE|DEALER|DEALS|DEGREE|DELIVERY|DELL|DELOITTE|DELTA|DEMOCRAT|DENTAL|DENTIST|DESI|DESIGN|DEV|DHL|DIAMONDS|DIET|DIGITAL|DIRECT|DIRECTORY|DISCOUNT|DJ|DK|DM|DNP|DO|DOCS|DOG|DOHA|DOMAINS|DOT|DOWNLOAD|DRIVE|DTV|DUBAI|DURBAN|DVAG|DZ|EARTH|EAT|EC|EDEKA|EDU|EDUCATION|EE|EG|EMAIL|EMERCK|ENERGY|ENGINEER|ENGINEERING|ENTERPRISES|EPSON|EQUIPMENT|ER|ERNI|ES|ESQ|ESTATE|ET|EU|EUROVISION|EUS|EVENTS|EVERBANK|EXCHANGE|EXPERT|EXPOSED|EXPRESS|EXTRASPACE|FAGE|FAIL|FAIRWINDS|FAITH|FAMILY|FAN|FANS|FARM|FASHION|FAST|FEEDBACK|FERRERO|FI|FILM|FINAL|FINANCE|FINANCIAL|FIRESTONE|FIRMDALE|FISH|FISHING|FIT|FITNESS|FJ|FK|FLICKR|FLIGHTS|FLIR|FLORIST|FLOWERS|FLSMIDTH|FLY|FM|FO|FOO|FOOTBALL|FORD|FOREX|FORSALE|FORUM|FOUNDATION|FOX|FR|FRESENIUS|FRL|FROGANS|FRONTIER|FTR|FUND|FURNITURE|FUTBOL|FYI|GA|GAL|GALLERY|GALLO|GALLUP|GAME|GAMES|GARDEN|GB|GBIZ|GD|GDN|GE|GEA|GENT|GENTING|GF|GG|GGEE|GH|GI|GIFT|GIFTS|GIVES|GIVING|GL|GLASS|GLE|GLOBAL|GLOBO|GM|GMAIL|GMBH|GMO|GMX|GN|GOLD|GOLDPOINT|GOLF|GOO|GOOG|GOOGLE|GOP|GOT|GOV|GP|GQ|GR|GRAINGER|GRAPHICS|GRATIS|GREEN|GRIPE|GROUP|GS|GT|GU|GUARDIAN|GUCCI|GUGE|GUIDE|GUITARS|GURU|GW|GY|HAMBURG|HANGOUT|HAUS|HDFCBANK|HEALTH|HEALTHCARE|HELP|HELSINKI|HERE|HERMES|HIPHOP|HISAMITSU|HITACHI|HIV|HK|HKT|HM|HN|HOCKEY|HOLDINGS|HOLIDAY|HOMEDEPOT|HOMES|HONDA|HORSE|HOST|HOSTING|HOTELES|HOTMAIL|HOUSE|HOW|HR|HSBC|HT|HTC|HU|HYUNDAI|IBM|ICBC|ICE|ICU|ID|IE|IFM|IINET|IL|IM|IMAMAT|IMMO|IMMOBILIEN|IN|INDUSTRIES|INFINITI|INFO|ING|INK|INSTITUTE|INSURANCE|INSURE|INT|INTERNATIONAL|INVESTMENTS|IO|IPIRANGA|IQ|IR|IRISH|IS|ISELECT|ISMAILI|IST|ISTANBUL|IT|ITAU|IWC|JAGUAR|JAVA|JCB|JCP|JE|JETZT|JEWELRY|JLC|JLL|JM|JMP|JNJ|JO|JOBS|JOBURG|JOT|JOY|JP|JPMORGAN|JPRS|JUEGOS|KAUFEN|KDDI|KE|KERRYHOTELS|KERRYLOGISTICS|KERRYPROPERTIES|KFH|KG|KH|KI|KIA|KIM|KINDER|KITCHEN|KIWI|KM|KN|KOELN|KOMATSU|KP|KPMG|KPN|KR|KRD|KRED|KUOKGROUP|KW|KY|KYOTO|KZ|LA|LACAIXA|LAMBORGHINI|LAMER|LANCASTER|LAND|LANDROVER|LANXESS|LASALLE|LAT|LATROBE|LAW|LAWYER|LB|LC|LDS|LEASE|LECLERC|LEGAL|LEXUS|LGBT|LI|LIAISON|LIDL|LIFE|LIFEINSURANCE|LIFESTYLE|LIGHTING|LIKE|LIMITED|LIMO|LINCOLN|LINDE|LINK|LIPSY|LIVE|LIVING|LIXIL|LK|LOAN|LOANS|LOCKER|LOCUS|LOL|LONDON|LOTTE|LOTTO|LOVE|LR|LS|LT|LTD|LTDA|LU|LUPIN|LUXE|LUXURY|LV|LY|MA|MADRID|MAIF|MAISON|MAKEUP|MAN|MANAGEMENT|MANGO|MARKET|MARKETING|MARKETS|MARRIOTT|MATTEL|MBA|MC|MD|ME|MED|MEDIA|MEET|MELBOURNE|MEME|MEMORIAL|MEN|MENU|MEO|METLIFE|MG|MH|MIAMI|MICROSOFT|MIL|MINI|MK|ML|MLB|MLS|MM|MMA|MN|MO|MOBI|MOBILY|MODA|MOE|MOI|MOM|MONASH|MONEY|MONTBLANC|MORMON|MORTGAGE|MOSCOW|MOTORCYCLES|MOV|MOVIE|MOVISTAR|MP|MQ|MR|MS|MT|MTN|MTPC|MTR|MU|MUSEUM|MUTUAL|MUTUELLE|MV|MW|MX|MY|MZ|NA|NADEX|NAGOYA|NAME|NATURA|NAVY|NC|NE|NEC|NET|NETBANK|NETFLIX|NETWORK|NEUSTAR|NEW|NEWS|NEXT|NEXTDIRECT|NEXUS|NF|NG|NGO|NHK|NI|NICO|NIKON|NINJA|NISSAN|NISSAY|NL|NO|NOKIA|NORTHWESTERNMUTUAL|NORTON|NOWRUZ|NOWTV|NP|NR|NRA|NRW|NTT|NU|NYC|NZ|OBI|OFFICE|OKINAWA|OLAYAN|OLAYANGROUP|OLLO|OM|OMEGA|ONE|ONG|ONL|ONLINE|OOO|ORACLE|ORANGE|ORG|ORGANIC|ORIGINS|OSAKA|OTSUKA|OTT|OVH|PA|PAGE|PAMPEREDCHEF|PANERAI|PARIS|PARS|PARTNERS|PARTS|PARTY|PASSAGENS|PCCW|PE|PET|PF|PG|PH|PHARMACY|PHILIPS|PHOTO|PHOTOGRAPHY|PHOTOS|PHYSIO|PIAGET|PICS|PICTET|PICTURES|PID|PIN|PING|PINK|PIONEER|PIZZA|PK|PL|PLACE|PLAY|PLAYSTATION|PLUMBING|PLUS|PM|PN|POHL|POKER|PORN|POST|PR|PRAXI|PRESS|PRO|PROD|PRODUCTIONS|PROF|PROGRESSIVE|PROMO|PROPERTIES|PROPERTY|PROTECTION|PS|PT|PUB|PW|PWC|PY|QA|QPON|QUEBEC|QUEST|RACING|RE|READ|REALESTATE|REALTOR|REALTY|RECIPES|RED|REDSTONE|REDUMBRELLA|REHAB|REISE|REISEN|REIT|REN|RENT|RENTALS|REPAIR|REPORT|REPUBLICAN|REST|RESTAURANT|REVIEW|REVIEWS|REXROTH|RICH|RICHARDLI|RICOH|RIO|RIP|RO|ROCHER|ROCKS|RODEO|ROOM|RS|RSVP|RU|RUHR|RUN|RW|RWE|RYUKYU|SA|SAARLAND|SAFE|SAFETY|SAKURA|SALE|SALON|SAMSUNG|SANDVIK|SANDVIKCOROMANT|SANOFI|SAP|SAPO|SARL|SAS|SAXO|SB|SBI|SBS|SC|SCA|SCB|SCHAEFFLER|SCHMIDT|SCHOLARSHIPS|SCHOOL|SCHULE|SCHWARZ|SCIENCE|SCOR|SCOT|SD|SE|SEAT|SECURITY|SEEK|SELECT|SENER|SERVICES|SEVEN|SEW|SEX|SEXY|SFR|SG|SH|SHARP|SHAW|SHELL|SHIA|SHIKSHA|SHOES|SHOP|SHOUJI|SHOW|SHRIRAM|SI|SINA|SINGLES|SITE|SJ|SK|SKI|SKIN|SKY|SKYPE|SL|SM|SMILE|SN|SNCF|SO|SOCCER|SOCIAL|SOFTBANK|SOFTWARE|SOHU|SOLAR|SOLUTIONS|SONG|SONY|SOY|SPACE|SPIEGEL|SPOT|SPREADBETTING|SR|SRL|ST|STADA|STAR|STARHUB|STATEBANK|STATEFARM|STATOIL|STC|STCGROUP|STOCKHOLM|STORAGE|STORE|STREAM|STUDIO|STUDY|STYLE|SU|SUCKS|SUPPLIES|SUPPLY|SUPPORT|SURF|SURGERY|SUZUKI|SV|SWATCH|SWISS|SX|SY|SYDNEY|SYMANTEC|SYSTEMS|SZ|TAB|TAIPEI|TALK|TAOBAO|TATAMOTORS|TATAR|TATTOO|TAX|TAXI|TC|TCI|TD|TEAM|TECH|TECHNOLOGY|TEL|TELECITY|TELEFONICA|TEMASEK|TENNIS|TEST|TEVA|TF|TG|TH|THD|THEATER|THEATRE|TICKETS|TIENDA|TIFFANY|TIPS|TIRES|TIROL|TJ|TK|TL|TM|TMALL|TN|TO|TODAY|TOKYO|TOOLS|TOP|TORAY|TOSHIBA|TOTAL|TOURS|TOWN|TOYOTA|TOYS|TR|TRADE|TRADING|TRAINING|TRAVEL|TRAVELERS|TRAVELERSINSURANCE|TRUST|TRV|TT|TUBE|TUI|TUNES|TUSHU|TV|TVS|TW|TZ|UA|UBS|UG|UK|UNICOM|UNIVERSITY|UNO|UOL|UPS|US|UY|UZ|VA|VACATIONS|VANA|VC|VE|VEGAS|VENTURES|VERISIGN|VERSICHERUNG|VET|VG|VI|VIAJES|VIDEO|VIG|VIKING|VILLAS|VIN|VIP|VIRGIN|VISION|VISTA|VISTAPRINT|VIVA|VLAANDEREN|VN|VODKA|VOLKSWAGEN|VOTE|VOTING|VOTO|VOYAGE|VU|VUELOS|WALES|WALTER|WANG|WANGGOU|WARMAN|WATCH|WATCHES|WEATHER|WEATHERCHANNEL|WEBCAM|WEBER|WEBSITE|WED|WEDDING|WEIBO|WEIR|WF|WHOSWHO|WIEN|WIKI|WILLIAMHILL|WIN|WINDOWS|WINE|WME|WOLTERSKLUWER|WORK|WORKS|WORLD|WS|WTC|WTF|XBOX|XEROX|XIHUAN|XIN|XN--11B4C3D|XN--1CK2E1B|XN--1QQW23A|XN--30RR7Y|XN--3BST00M|XN--3DS443G|XN--3E0B707E|XN--3PXU8K|XN--42C2D9A|XN--45BRJ9C|XN--45Q11C|XN--4GBRIM|XN--55QW42G|XN--55QX5D|XN--5TZM5G|XN--6FRZ82G|XN--6QQ986B3XL|XN--80ADXHKS|XN--80AO21A|XN--80ASEHDB|XN--80ASWG|XN--8Y0A063A|XN--90A3AC|XN--90AIS|XN--9DBQ2A|XN--9ET52U|XN--9KRT00A|XN--B4W605FERD|XN--BCK1B9A5DRE4C|XN--C1AVG|XN--C2BR7G|XN--CCK2B3B|XN--CG4BKI|XN--CLCHC0EA0B2G2A9GCD|XN--CZR694B|XN--CZRS0T|XN--CZRU2D|XN--D1ACJ3B|XN--D1ALF|XN--E1A4C|XN--ECKVDTC9D|XN--EFVY88H|XN--ESTV75G|XN--FCT429K|XN--FHBEI|XN--FIQ228C5HS|XN--FIQ64B|XN--FIQS8S|XN--FIQZ9S|XN--FJQ720A|XN--FLW351E|XN--FPCRJ9C3D|XN--FZC2C9E2C|XN--FZYS8D69UVGM|XN--G2XX48C|XN--GCKR3F0F|XN--GECRJ9C|XN--H2BRJ9C|XN--HXT814E|XN--I1B6B1A6A2E|XN--IMR513N|XN--IO0A7I|XN--J1AEF|XN--J1AMH|XN--J6W193G|XN--JLQ61U9W7B|XN--JVR189M|XN--KCRX77D1X4A|XN--KPRW13D|XN--KPRY57D|XN--KPU716F|XN--KPUT3I|XN--L1ACC|XN--LGBBAT1AD8J|XN--MGB9AWBF|XN--MGBA3A3EJT|XN--MGBA3A4F16A|XN--MGBA7C0BBN0A|XN--MGBAAM7A8H|XN--MGBAB2BD|XN--MGBAYH7GPA|XN--MGBB9FBPOB|XN--MGBBH1A71E|XN--MGBC0A9AZCG|XN--MGBCA7DZDO|XN--MGBERP4A5D4AR|XN--MGBPL2FH|XN--MGBT3DHD|XN--MGBTX2B|XN--MGBX4CD0AB|XN--MIX891F|XN--MK1BU44C|XN--MXTQ1M|XN--NGBC5AZD|XN--NGBE9E0A|XN--NODE|XN--NQV7F|XN--NQV7FS00EMA|XN--NYQY26A|XN--O3CW4H|XN--OGBPF8FL|XN--P1ACF|XN--P1AI|XN--PBT977C|XN--PGBS0DH|XN--PSSY2U|XN--Q9JYB4C|XN--QCKA1PMC|XN--QXAM|XN--RHQV96G|XN--ROVU88B|XN--S9BRJ9C|XN--SES554G|XN--T60B56A|XN--TCKWE|XN--UNUP4Y|XN--VERMGENSBERATER-CTB|XN--VERMGENSBERATUNG-PWB|XN--VHQUV|XN--VUQ861B|XN--W4R85EL8FHU5DNRA|XN--W4RS40L|XN--WGBH1C|XN--WGBL6A|XN--XHQ521B|XN--XKC2AL3HYE2A|XN--XKC2DL3A5EE0H|XN--Y9A3AQ|XN--YFRO4I67O|XN--YGBI2AMMX|XN--ZFR164B|XPERIA|XXX|XYZ|YACHTS|YAHOO|YAMAXUN|YANDEX|YE|YODOBASHI|YOGA|YOKOHAMA|YOU|YOUTUBE|YT|YUN|ZA|ZAPPOS|ZARA|ZERO|ZIP|ZM|ZONE|ZUERICH|ZW';
	private $api = false;
	private $db = false;
	
	public function __sleep() {
		$this->writeHosts();	
		return array('debug', 'errorMsg', 'table', 'apiKey', 'wordpressVersion', 'dRegex');
	}
	
	public function __wakeup() {
		$this->hostsToAdd = new wfArray(array('owner', 'host', 'path', 'hostKey'));
		$this->api = new wfAPI($this->apiKey, $this->wordpressVersion);
		$this->db = new wfDB();
	}
	
	public function __construct($apiKey, $wordpressVersion, $db = false) {
		$this->hostsToAdd = new wfArray(array('owner', 'host', 'path', 'hostKey'));
		$this->apiKey = $apiKey;
		$this->wordpressVersion = $wordpressVersion;
		$this->api = new wfAPI($apiKey, $wordpressVersion);
		if($db){
			$this->db = $db;
		} else {
			$this->db = new wfDB();
		}
		global $wpdb;
		if(isset($wpdb)){
			$this->table = $wpdb->base_prefix . 'wfHoover';
		} else {
			$this->table = 'wp_wfHoover';
		}
		
		$this->cleanup();
	}
	
	public function cleanup() {
		$this->db->truncate($this->table);
	}
	
	public function hoover($id, $data) {
		$this->currentHooverID = $id;
		$this->_foundSome = false;
		@preg_replace_callback('/\b((?:[a-z][\w-]+:(?:\/{1,3}|[a-z0-9%])|www\d{0,3}[.]|[a-z0-9.\-]+[.][a-z]{2,4}\/)(?:[^\s()<>]+|\(([^\s()<>]+|(\([^\s()<>]+\)))*\))+(?:\(([^\s()<>]+|(\([^\s()<>]+\)))*\)|[^\s`!()\[\]{};:\'".,<>?«»“”‘’]))/i', array($this, 'captureURL'), $data);
		$this->writeHosts();
		return $this->_foundSome;
	}
	
	private function dbg($msg) { 
		if ($this->debug) { wordfence::status(4, 'info', $msg); } 
	}
	
	public function captureURL($matches) {
		$id = $this->currentHooverID;
		$url = $matches[0];
		$components = parse_url($url);
		if (!isset($components['scheme']) || !preg_match('/^https?$/i', $components['scheme'])) {
			return;
		}
		
		$host = (isset($components['host']) ? $components['host'] : '');
		$path = (isset($components['path']) && !empty($components['path']) ? $components['path'] : '/');
		$hashes = $this->_generateHashes($url);
		$prefixes = '';
		foreach ($hashes as $h) {
			$prefixes .= wfUtils::substr($h, 0, 4);
		}
		$this->hostsToAdd->push(array('owner' => $id, 'host' => $host, 'path' => $path, 'hostKey' => $prefixes));
		
		if($this->hostsToAdd->size() > 1000){ $this->writeHosts(); }
	}
	
	private function writeHosts() {
		if ($this->hostsToAdd->size() < 1) { return; }
		if ($this->useDB) {
			$sql = "INSERT INTO " . $this->table . " (owner, host, path, hostKey) VALUES ";
			while ($elem = $this->hostsToAdd->shift()) {
				//This may be an issue for hyperDB or other abstraction layers, but leaving it for now.
				$sql .= sprintf("('%s', '%s', '%s', '%s'),", 
						$this->db->realEscape($elem['owner']),
						$this->db->realEscape($elem['host']),
						$this->db->realEscape($elem['path']),
						$this->db->realEscape($elem['hostKey'])
								);
			}
			$sql = rtrim($sql, ',');
			$this->db->queryWrite($sql);
		}
		else {
			while ($elem = $this->hostsToAdd->shift()) {
				$this->hostKeys = array_merge($this->hostKeys, str_split($elem['hostKey'], 4));
				$this->hostList[] = array(
					'owner' => $elem['owner'],
					'host' => $elem['host'],
					'path' => $elem['path'],
					'hostKey' => $elem['hostKey']
					);
			}
		}
		
		$this->_foundSome = true;
	}
	public function getBaddies() {
		$allHostKeys = array();
		if ($this->useDB) {
			$q1 = $this->db->querySelect("SELECT hostKey FROM {$this->table}");
			foreach ($q1 as $hRec) {
				$allHostKeys = array_merge($allHostKeys, str_split($hRec['hostKey'], 4));
			}
		}
		else {
			$allHostKeys = $this->hostKeys;
		}
		
		$allHostKeys = array_values(array_unique($allHostKeys));
		
		/**
		 * Check hash prefixes first. Each one is a 4-byte binary prefix of a SHA-256 hash of the URL. The response will
		 * be a binary list of 4-byte indices; The full URL for each index should be sent in the secondary query to
		 * find the true good/bad status.
		 */
		if (count($allHostKeys) > 0) {
			if ($this->debug) {
				$this->dbg("Checking " . count($allHostKeys) . " hostkeys");
				foreach ($allHostKeys as $key) {
					$this->dbg("Checking hostkey: " . bin2hex($key));
				}
			}
			
			wordfence::status(2, 'info', "Checking " . count($allHostKeys) . " host keys against Wordfence scanning servers.");
			$resp = $this->api->binCall('check_host_keys', implode('', $allHostKeys));
			wordfence::status(2, 'info', "Done host key check.");
			$this->dbg("Done host key check");

			$badHostKeys = array();
			if ($resp['code'] >= 200 && $resp['code'] <= 299) {
				$this->dbg("Host key response: " . bin2hex($resp['data']));
				$dataLen = strlen($resp['data']);
				if ($dataLen > 0 && $dataLen % 2 == 0) {
					$this->dbg("Checking response indexes");
					for ($i = 0; $i < $dataLen; $i += 2) {
						$idxArr = unpack('n', substr($resp['data'], $i, 2));
						$idx = $idxArr[1];
						$this->dbg("Checking index {$idx}");
						if (isset($allHostKeys[$idx])) {
							$badHostKeys[] = $allHostKeys[$idx];
							$this->dbg("Got bad hostkey for record: " . bin2hex($allHostKeys[$idx]));
						}
						else {
							$this->dbg("Bad allHostKeys index: $idx");
							$this->errorMsg = "Bad allHostKeys index: $idx";
							return false;
						}
					}
				}
				else if ($dataLen > 0) {
					$this->errorMsg = "Invalid data length received from Wordfence server: " . $dataLen;
					$this->dbg($this->errorMsg);
					return false;
				}
			}
			else {
				$this->errorMsg = "Wordfence server responded with an error. HTTP code " . $resp['code'] . " and data: " . $resp['data'];
				return false;
			}
			
			if (count($badHostKeys) > 0) {
				$urlsToCheck = array();
				$totalURLs = 0;
				
				//Reconcile flagged prefixes with their corresponding URLs
				foreach ($badHostKeys as $badHostKey) {
					if ($this->useDB) {
						/**
						 * Putting a 10000 limit in here for sites that have a huge number of items with the same URL 
						 * that repeats. This is an edge case. But if the URLs are malicious then presumably the admin 
						 * will fix the malicious URLs and on subsequent scans the items (owners) that are above the 
						 * 10000 limit will appear.
						 */
						$q1 = $this->db->querySelect("SELECT owner, host, path, LOCATE('%s', hostKey) AS position FROM {$this->table} HAVING position != 0 AND MOD(position - 1, 4) = 0 LIMIT 10000", $badHostKey);
						foreach ($q1 as $rec) {
							$url = 'http://' . $rec['host'] . $rec['path'];
							if (!isset($urlsToCheck[$rec['owner']])) {
								$urlsToCheck[$rec['owner']] = array();
							}
							if (!in_array($url, $urlsToCheck[$rec['owner']])) {
								$urlsToCheck[$rec['owner']][] = $url;
								$totalURLs++;
							}
						}
					}
					else {
						foreach ($this->hostList as $rec) {
							$pos = strpos($rec['hostKey'], $badHostKey);
							if ($pos !== false && $pos % 4 == 0) {
								$url = 'http://' . $rec['host'] . $rec['path'];
								if (!isset($urlsToCheck[$rec['owner']])) {
									$urlsToCheck[$rec['owner']] = array();
								}
								if (!in_array($url, $urlsToCheck[$rec['owner']])) {
									$urlsToCheck[$rec['owner']][] = $url;
									$totalURLs++;
								}
							}
						}
					}
					if ($totalURLs > 10000) { break; }
				}

				if (count($urlsToCheck) > 0) {
					wordfence::status(2, 'info', "Checking " . $totalURLs . " URLs from " . sizeof($urlsToCheck) . " sources.");
					$badURLs = $this->api->call('check_bad_urls', array(), array('toCheck' => json_encode($urlsToCheck)));
					wordfence::status(2, 'info', "Done URL check.");
					$this->dbg("Done URL check");
					if (is_array($badURLs) && count($badURLs) > 0) {
						$finalResults = array();
						foreach ($badURLs as $file => $badSiteList) {
							if (!isset($finalResults[$file])) {
								$finalResults[$file] = array();
							}
							foreach ($badSiteList as $badSite) {
								$finalResults[$file][] = array(
									'URL' => $badSite[0],
									'badList' => $badSite[1]
									);
							}
						}
						$this->dbg("Confirmed " . count($badURLs) . " bad URLs");
						return $finalResults;
					}
				}
			}
		}
		
		return array();
	}
	
	protected function _generateHashes($url) {
		//The GSB specification requires generating and sending hash prefixes for a number of additional similar URLs. See: https://developers.google.com/safe-browsing/v4/urls-hashing#suffixprefix-expressions
		
		$canonicalURL = $this->_canonicalizeURL($url);
		
		//Extract the scheme
		$scheme = 'http';
		if (preg_match('~^([a-z]+[a-z0-9+\.\-]*)://(.*)$~i', $canonicalURL, $matches)) {
			$scheme = strtolower($matches[1]);
			$canonicalURL = $matches[2];
		}
		
		//Separate URL and query string
		$query = '';
		if (preg_match('/^([^?]+)(\??.*)/', $canonicalURL, $matches)) {
			$canonicalURL = $matches[1];
			$query = $matches[2];
		}
		
		//Separate host and path
		$path = '';
		preg_match('~^(.*?)(?:(/.*)|$)~', $canonicalURL, $matches);
		$host = $matches[1];
		if (isset($matches[2])) {
			$path = $matches[2];
		}
		
		//Clean host
		$host = $this->_normalizeHost($host);
		
		//Generate hosts list
		$hosts = array();
		if (filter_var(trim($host, '[]'), FILTER_VALIDATE_IP)) {
			$hosts[] = $host;
		}
		else {
			$hostComponents = explode('.', $host);
			
			$numComponents = count($hostComponents) - 7;
			if ($numComponents < 1) {
				$numComponents = 1;
			}
			
			$hosts[] = $host;
			for ($i = $numComponents; $i < count($hostComponents) - 1; $i++) {
				$hosts[] = implode('.', array_slice($hostComponents, $i));
			}
		}
		
		//Generate paths list
		$paths = array('/');
		$pathComponents = array_filter(explode('/', $path));
		
		$numComponents = min(count($pathComponents), 4);
		for ($i = 1; $i < $numComponents; $i++) {
			$paths[] = '/' . implode('/', array_slice($pathComponents, 0, $i)) . '/';
		}
		if ($path != '/') {
			$paths[] = $path;
		}
		if (strlen($query) > 0) {
			$paths[] = $path . '?' . $query;
		}
		$paths = array_reverse($paths); //So we start at the most specific and move to most generic
		
		//Generate hashes
		$hashes = array();
		foreach ($hosts as $h) {
			$hashes[$h] = hash('sha256', $h, true); //WFSB compatibility -- it uses hashes without the path
			foreach ($paths as $p) {
				$key = $h . $p;
				$hashes[$key] = hash('sha256', $key, true);
			}
		}
		
		return $hashes;
	}
	
	protected function _canonicalizeURL($url) { //Based on https://developers.google.com/safe-browsing/v4/urls-hashing#canonicalization and Google's reference implementation https://github.com/google/safebrowsing/blob/master/urls.go
		//Strip fragment
		$url = $this->_array_first(explode('#', $url));
		
		//Trim space
		$url = trim($url);
		
		//Remove tabs, CR, LF
		$url = preg_replace('/[\t\n\r]/', '', $url);
		
		//Normalize escapes
		$url = $this->_normalizeEscape($url);
		if ($url === false) { return false; }
		
		//Extract the scheme
		$scheme = 'http';
		if (preg_match('~^([a-z]+[a-z0-9+\.\-]*)://(.*)$~i', $url, $matches)) {
			$scheme = strtolower($matches[1]);
			$url = $matches[2];
		}
		
		//Separate URL and query string
		$query = '';
		if (preg_match('/^([^?]+)(\??.*)/', $url, $matches)) {
			$url = $matches[1];
			$query = $matches[2];
		}
		$endsWithSlash = substr($url, -1) == '/';
		
		//Separate host and path
		$path = '';
		preg_match('~^(.*?)(?:(/.*)|$)~', $url, $matches);
		$host = $matches[1];
		if (isset($matches[2])) {
			$path = $matches[2];
		}
		
		//Clean host
		$host = $this->_normalizeHost($host);
		if ($host === false) { return false; }
		
		//Clean path
		$path = preg_replace('~//+~', '/', $path); //Multiple slashes -> single slash
		$path = preg_replace('~(?:^|/)\.(?:$|/)~', '/', $path); //. path components removed
		while (preg_match('~/(?!\.\./)[^/]+/\.\.(?:$|/)~', $path)) { //Resolve ..
			$path = preg_replace('~/(?!\.\./)[^/]+/\.\.(?:$|/)~', '/', $path, 1);
		}
		$path = preg_replace('~(?:^|/)\.\.(?:$|/)~', '/', $path); //Eliminate .. at the beginning
		$path = trim($path, '.');
		$path = preg_replace('/\.\.+/', '.', $path);
		
		if ($path == '.' || $path == '') {
			$path = '/';
		}
		else if ($endsWithSlash && substr($path, -1) != '/') {
			$path .= '/';
		}
		
		return $scheme . '://' . $host . $path . $query;
	}
	
	protected function _normalizeEscape($url) {
		$maxDepth = 1024;
		$i = 0;
		while (preg_match('/%([0-9a-f]{2})/i', $url)) {
			$url = preg_replace_callback('/%([0-9a-f]{2})/i', array($this, '_hex2binCallback'), $url);
			$i++;
			
			if ($i > $maxDepth) {
				return false;
			}
		}
		
		return preg_replace_callback('/[\x00-\x20\x7f-\xff#%]/', array($this, '_bin2hexCallback'), $url);
	}
	
	protected function _hex2binCallback($matches) {
		return wfUtils::hex2bin($matches[1]);
	}

	protected function _bin2hexCallback($matches) {
		return '%' . bin2hex($matches[0]);	
	}
	
	protected function _normalizeHost($host) {
		//Strip username:password
		$host = $this->_array_last(explode('@', $host));
		
		//IPv6 literal
		if (substr($host, 0, 1) == '[') {
			if (strpos($host, ']') === false) { //No closing bracket
				return false;
			}
		}
		
		//Strip port
		$host = preg_replace('/:\d+$/', '', $host);
		
		//Unicode to IDNA
		$u = rawurldecode($host);
		if (preg_match('/[\x81-\xff]/', $u)) { //0x80 is technically Unicode, but the GSB canonicalization doesn't consider it one
			if (function_exists('idn_to_ascii')) { //Some PHP versions don't have this and we don't have a polyfill
				$host = idn_to_ascii($u);
			}
		}
		
		//Remove extra dots
		$host = trim($host, '.');
		$host = preg_replace('/\.\.+/', '.', $host);
		
		//Canonicalize IP addresses
		if ($iphost = $this->_parseIP($host)) {
			return $iphost;
		}
		
		return strtolower($host);
	}
	
	protected function _parseIP($host) {
		// The Windows resolver allows a 4-part dotted decimal IP address to have a
		// space followed by any old rubbish, so long as the total length of the
		// string doesn't get above 15 characters. So, "10.192.95.89 xy" is
		// resolved to 10.192.95.89. If the string length is greater than 15
		// characters, e.g. "10.192.95.89 xy.wildcard.example.com", it will be
		// resolved through DNS.
		if (strlen($host) <= 15) {
			$host = $this->_array_first(explode(' ', $host));
		}
		
		if (!preg_match('/^((?:0x[0-9a-f]+|[0-9\.])+)$/i', $host)) {
			return false;
		}
		
		$parts = explode('.', $host);
		if (count($parts) > 4) {
			return false;
		}
		
		$strings = array();
		foreach ($parts as $i => $p) {
			if ($i == count($parts) - 1) {
				$strings[] = $this->_canonicalNum($p, 5 - count($parts));
			}
			else {
				$strings[] = $this->_canonicalNum($p, 1);
			}
			
			if ($strings[$i] == '') {
				return '';
			}
		}
		
		return implode('.', $strings);
	}
	
	protected function _canonicalNum($part, $n) {
		if ($n <= 0 || $n > 4) {
			return '';
		}
		
		if (preg_match('/^0x(\d+)$/i', $part, $matches)) { //hex
			$part = hexdec($matches[1]);
		}
		else if (preg_match('/^0(\d+)$/i', $part, $matches)) { //octal
			$part = octdec($matches[1]);
		}
		else {
			$part = (int) $part;
		}
		
		$strings = array_fill(0, $n, '');
		for ($i = $n - 1; $i >= 0; $i--) {
			$strings[$i] = (string) ($part & 0xff);
			$part = $part >> 8;
		}
		return implode('.', $strings);
	}
	
	protected function _array_first($array) {
		if (empty($array)) {
			return null;
		}
		
		return $array[0];
	}
	
	protected function _array_last($array) {
		if (empty($array)) {
			return null;
		}
		
		return $array[count($array) - 1];
	}
}
?>
