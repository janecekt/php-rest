<?php
	// Facilitates input of data
	class Input {
		// Populates HTTP body
		public static function decodeBody() {
			$requestBody = file_get_contents('php://input');
			$data = json_decode($requestBody, true);
			if (json_last_error() != JSON_ERROR_NONE) {
				throw new BadRequestException("Body is not a valid JSON !");
			}
			else {
				return $data;
			}	
		}

		// Parse date to timestamp
		public static function parseDateTime($arg_name, $str_date, $format) {
			if ($str_date == null) {
				return null;
			}

			$result = DateTime::createFromFormat($format, $str_date, new DateTimeZone('UTC'));
			if ($result == false) {
				throw new BadRequestException($arg_name . " must be in the format " . $format);				
			}
			return $result;
		}


		public static function guardNotNull($arg_name, $arg) {
			if ($arg == null) {
				throw new BadRequestException($arg_name . " must be specified !");
			}
		}
	}
?>