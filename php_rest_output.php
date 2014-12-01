<?php
	// Facilitates output of error codes / data serialization
	class Output {
		private static $loggingEnabled = false;
		
		// Sends the response code
		public static function sendResponseCode($code) {
			if (self::$loggingEnabled) {
				self::dumpTrace("Sending Response Code: ", $code);
			}
			else {
				http_response_code($code);
			}
		}

		// Returns data which should be cached
		// Example: return static data object which never changes or changes infrequently
		public static function sendCacheableData($data, $expires) {
			self::populateCacheHeaders($expires);
			self::serializeData($data);
			exit();
		}


		// Returns data which should not be cached
		// Example: return traing object (because it is mutable)
		public static function sendNonCacheableData($data) {
			self::populateCacheHeaders(0);
			self::serializeData($data);
			exit();
		}


		// Return internal error
		public static function sendError($code, $msg) {			
			self::populateCacheHeaders(0);
			self::sendHeader('Access-Control-Expose-Headers: Detail');
			self::sendHeader('Content-type: text/plain');
			if ($msg != null) {
				self::sendErrorDetail($msg);
			}
			self::sendResponseCode($code);
		}
		
		
		// Send COORS option headers
		public static function sendOptionsHeaders() {
			self::populateCacheHeaders(0);
			if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'])) {
				self::sendHeader('Access-Control-Allow-Methods: GET, POST, OPTIONS');
			}
			if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'])) {
				header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");
			}
			exit();
		}


		// Send error details
		private static function sendErrorDetail($msg) {
			self::sendHeader("Detail: $msg");
		}


		// Populates HTTP cache headers indicating how the page should be cached
		private static function populateCacheHeaders($expires) {
			if ($expires <= 0) {
				if (isset($_SERVER['HTTP_ORIGIN'])) {
			    	self::sendHeader("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
			    	self::sendHeader('Access-Control-Allow-Credentials: true');
			    	self::sendHeader('Access-Control-Max-Age: 86400');   // 1-day
		    	}
			    			    
				self::sendHeader('Cache-Control: no-cache, no-store, must-revalidate');
				self::sendHeader('Pragma: no-cache');
				self::sendHeader('Expires: ' . gmdate('D, d M Y H:i:s', time()-60) . 'GMT');
			}
			else {
				self::sendHeader('Cache-Control: public');
				if ($expires != null) {
					self::sendHeader('Expires: ' . gmdate('D, d M Y H:i:s', time()+$expires) . 'GMT');
				}
			}
		}


		// Populates HTTP body
		private static function serializeData($data) {
			if ($data != null) {
				// Serialize data into JSON
				$output = json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
				if (json_last_error() != JSON_ERROR_NONE) {
					// Internal error
					self::sendError(500, "JSON serialization failed (error code " . json_last_error() . ")!" );
				}
				else {
					self::sendHeader('Content-type: application/json; charset=utf-8');
					self::sendOutput($output);
				}
			}
		}


		private static function sendHeader($header) {
			if (self::$loggingEnabled) {
				self::dumpTrace("Sending Header: ", $header);
			}
			else {
				header($header);
			}
		}


		private static function sendOutput($output) {
			if (self::$loggingEnabled) {
				self::dumpTrace("Sending Output: ", $output);
			}
			else {
				echo $output;
			}		
		}


		// Set logging enabled
		public static function setLoggingEnabled() {
			self::populateCacheHeaders(0);
			self::$loggingEnabled = true;
			header('Content-Type:text/plain');
		}


		// Dump the request
		public static function dumpRequest() {
			self::dumpTrace("PATH=", $_SERVER['PATH_INFO'], "\n");
			self::dumpTrace("\n");
			self::dumpTrace("== GET_PARAMETERS ==\n");
			self::dumpTrace($_GET);
			self::dumpTrace("\n");
			self::dumpTrace("== POST_PARAMETERS ==\n");
			self::dumpTrace($_POST);
			self::dumpTrace("\n");
			self::dumpTrace("== BODY ==\n");
			self::dumpTrace( file_get_contents('php://input') );
		}


		public static function dumpVar($var) {
			// If logging is disabled do nothing
			if (!self::$loggingEnabled) {
				return;
			}

			var_dump($var);			
		}


		public static function dumpTrace() {
			// If logging is disabled do nothing
			if (!self::$loggingEnabled) {
				return;
			}

			// Do actual logging
			for ($i = 0; $i < func_num_args(); $i++) {
				$arg = func_get_arg($i);
				self::dumpValue($arg);
			}
			echo "\n";
		}


		private static function dumpValue($val) {
			// Do actual logging
			if (is_array($val)) {
				echo "{";
				$isFirst = true;
				foreach ($val as $key => $value) {
					if ($isFirst) {
						$isFirst = false;	
					} 
					else 
					{
						echo ", ";
					}
					echo self::dumpValue($key) , "=>", self::dumpValue($value);
				}
				echo "}";
			} else if ($val instanceof DateTime) {
				echo 'DateTime{' , $val->format(DateTime::ISO8601) , '}';
			} else {
				echo $val;
			}
		}
	}
?>
