<?php
	// Common Parent
	abstract class WebServiceException extends Exception {
		public function __construct($message, $code, Exception $previous = null) {
		        parent::__construct($message, $code, $previous);
    		}
	}


	// If request is invalid, missing required args, or unsupported
	class BadRequestException extends WebServiceException {
		public function __construct($message, Exception $previous = null) {
		        parent::__construct($message, 400, $previous);
    		}
	}


	// If the request requires authorization but authorization headers are missing
	class UnauthorizedException extends WebServiceException {
		public function __construct($message, Exception $previous = null) {
		        parent::__construct($message, 401, $previous);
    		}
	}


	// If authorization headers were provided but but user is not permissioned to do a given operation 
	class ForbidenException extends WebServiceException {
		public function __construct($message, Exception $previous = null) {
		        parent::__construct($message, 403, $previous);
    		}
	}


	// If the resource (data item) does not exist
	// Example: requested training with id=547 does not exist
	class NotFoundException extends WebServiceException {
		public function __construct($message, Exception $previous = null) {
		        parent::__construct($message, 404, $previous);
    		}
	}


	// If the operation cannot be applied to a given resource 
	// Example: unbook training which is not booked
	class ConflictException extends WebServiceException {
		public function __construct($message, Exception $previous = null) {
		        parent::__construct($message, 409, $previous);
    		}
	}


	// Processing of the request failed
	// Example: data serialization failed, db-connection failed etc.
	class InternalErrorException extends WebServiceException {
		public function __construct($message, Exception $previous = null) {
		        parent::__construct($message, 500, $previous);
    		}
	}
?>