<?php 
	// Path pattern matching
	// Example:
       //    $pattern = "/api/service/item/{year:[0-9]+}-{id:[a-z]+}"
       // (1) Function checks if $path matches regexp /api/service/item/([0-9]+)-([a-z]+)
	//       Returns true if it matches - false otherwise
       // (2) If matched updates $path_args array as follows:
	//	$path_args["year"] = actual value matching the regexp {0-9}+
	//	$path_args["id"] = actual value matching the regexp [a-z]+
	function match_path_pattern($pattern, &$path_args, $path) {
		// Build $arg_exps
		// /some/{A}-{B}/{C} => /some/{0}-{1}/{2}, $arg_exps("A", "B", "C")
		$arg_exps = array();
		$pattern=preg_replace_callback('(\{([^}]+)\})', 
			function($match) use (&$arg_exps) {
				array_push($arg_exps, $match[1]);
				$idx = count($arg_exps) -1;
				return "{" . $idx . "}";
			},
			$pattern);

		// Escape all regexp characters in the pattern
		$pattern=preg_quote($pattern, '/');

		// Enrich pattern with /^ and $/ to ensure full match
		$pattern= "/^" . $pattern . "$/";

		// Replace #i# and build $arg_keys
		// Before:
		//    $pattern = /some/\{0\}-\{1\}/\{2\}
		//    $arg_exps = ("name0:regex0", "name1:regex1", "name2:regex2")
		// After:
		//   $pattern = /some/(regex0)-(regex1)/(regex2)
		//   $arg_keys = (name0,name1,name2)
		$arg_keys = array();
		$pattern=preg_replace_callback('/\\\{([0-9]+)\\\}/',
			function($match) use (&$arg_exps, &$arg_keys) {
				$idx = intval($match[1]);
				$exp = $arg_exps[$idx];
				$exp_parts = array();
				if (preg_match("/^([^:]+):([^\/()]+)$/", $exp, $exp_parts) != 1) {
					throw new InvalidArgumentException("$exp is not a valid expression !");
				}
				array_push($arg_keys, $exp_parts[1]);
				$regEx = $exp_parts[2];
				return "(" . $regEx . ")";
			},
			$pattern);
				
		// Match $path using $pattern to get $arg_values
		$arg_values = array();		
		$match_count = preg_match($pattern, $path, $arg_values);

		// If we have a match
		if ($match_count == 1) {
			for ($i = 0; $i < count($arg_keys); $i++) {		    
			    $path_args[ $arg_keys[$i] ] = $arg_values[$i+1];
			}
			return true;
		}

		// If no match
		return false;	
	}


	// Dispatcher of the REST calls
	class Dispatcher {
		private $getHandlers = array();
		private $postHandlers = array();
		private $meta = array();
		
		// Register new handler for GET method
		public function get($path, $meta, callable $handler) {
			if ($meta != null) {
				$this->meta = array_merge($this->meta, Meta::postProcessGet($path, $meta));
			}
			$this->getHandlers[$path] = $handler;
		}

		// Register new handler for POST method
		public function post($path, $meta, callable $handler) {
			if ($meta != null) {
				$this->meta = array_merge( $this->meta, Meta::postProcessPost($path, $meta));
			}
			$this->postHandlers[$path] = $handler;
		}
		
		public function run() {
			try {
				if ($_SERVER['REQUEST_METHOD'] == 'GET') {
					Output::dumpTrace("==> Searching for GET handler");
					$this->dispatchToHandler($_GET, $this->getHandlers);
				}
				else if ($_SERVER['REQUEST_METHOD'] == 'POST') {
					Output::dumpTrace("==> Searching for POST handler");
					$this->dispatchToHandler($_POST, $this->postHandlers);
				}
				else if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
					Output::dumpTrace("==> OPTIONS Request");
					Output::sendOptionsHeaders();
				}
				throw new BadRequestException("No handler was found");
			} catch(WebServiceException $ex) {
				Output::dumpTrace($ex);
				Output::sendError($ex->getCode(), $ex->getMessage());
			} catch(Exception $ex) {
				Output::dumpTrace($ex);
				// 500 : Internal error
				Output::sendError(500, "Unknown exception");
			}
			exit();
		}

		private function dispatchToHandler($args, $handlers) {
			// Remove trailing "/" from PATH_INFO
			$pathInfo = preg_replace('/\/$/', '', $_SERVER['PATH_INFO']);

			// Find matching handler
			foreach ($handlers as $path => $handler) {
				$path_args = array();
				if (match_path_pattern($path, $path_args, $pathInfo)) {
					Output::dumpTrace("PATH - MATCH :: ", $path, " ? = ? ", $pathInfo);
					Output::dumpTrace();
					Output::dumpTrace("==> Handler found - Mapping arguments");

					$this->invokeHandler($path_args, $args, $handler);

					Output::dumpTrace();
					Output::dumpTrace("==> Handler invocation completed ");

					exit();
				}
				Output::dumpTrace("PATH - NO MATCH :: ", $path, " ? = ? ", $pathInfo);
			}
			Output::dumpTrace();
			Output::dumpTrace("==> No Handler found");
		}

		private function invokeHandler($path_args, $args, $handler) {
			Output::dumpTrace("Request PathArgs=" , $path_args);
			Output::dumpTrace("Request Args=", $args);
			
			// Look at closure method using reflection
			$method = new ReflectionMethod($handler, "__invoke");
			$paramValues = $this->buildParamValues($method, $path_args, $args);

			// Invoke handler
			Output::dumpTrace();
			Output::dumpTrace("==> Invoking handler ");
			$method->invokeArgs($handler, $paramValues);
			exit();
		}

		
		private function buildParamValues($method, $path_args, $args) {
			$paramValues = array();
			foreach($method->getParameters() as $parameter) {
				$paramParts = array();				
				preg_match('/^(p_)?(.*)$/', $parameter->getName(), $paramParts);
				if ($paramParts[1] == 'p_') {
					$paramValue = $path_args[ $paramParts[2] ];
					array_push($paramValues, $paramValue);
					Output::dumpTrace("==> Mapped PathParameter :: ", $parameter->getName(), "=", $paramValue );
				}
				else if ($parameter->getName() == 'body') {
					$paramValue = Input::decodeBody();
					array_push($paramValues, $paramValue);
					Output::dumpTrace("==> Mapped BodyParameter :: ", $parameter->getName(), "=", $paramValue );					
				}
				else { 
					$paramValue = $args[ $paramParts[2] ];
					array_push($paramValues, $paramValue);
					Output::dumpTrace("==> Mapped Parameter :: ", $parameter->getName(), "=", $paramValue );
				}
			}
			return $paramValues;			
		}


		public function specHtml() {
			echo '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">', "\n";
			echo '<html xmlns="http://www.w3.org/1999/xhtml">', "\n";
			echo '  <head>', "\n";
			echo '    <title>API Specification</title>', "\n";
			echo '    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />', "\n";
			echo '    <style type="text/css">', "\n";
			echo '      body { font-size: 12pt; font-family: sans-serif;  }', "\n";			
			echo '      table { width: 650px; border: 1px solid grey; border-collapse:collapse; }', "\n";
			echo '      td { border: 1px solid grey; padding: 5px 15px 5px 15px;  }', "\n";
			echo '      .specSeparator { margin: 20px; }', "\n";		
			echo '      .specTitle { background-color:darkgrey; color: black; font-weight: bold; font-family: monospace; font-size: 14pt; }', "\n";
			echo '      .specDesc { color: darkgreen; font-size: 12pt; }', "\n";
			echo '      .specPathArgTitle { background-color: azure; font-weight: bold; font-size: 11pt; }', "\n";
			echo '      .specPathArgName { width: 100px; background-color: azure; font-weight: bold; font-size: 11pt; }', "\n";
			echo '      .specPathArgDesc {  font-size: 11pt; }', "\n";
			echo '      .specUrlArgTitle { background-color: beige; font-weight: bold; font-size: 11pt; }', "\n";
			echo '      .specUrlArgName { width: 100px; background-color: beige; font-weight: bold; font-size: 11pt; }', "\n";
			echo '      .specUrlArgDesc {  font-size: 11pt; }', "\n";
			echo '      .specBodyTitle { background-color: mistyrose; font-size: 11pt; font-weight: bold; }', "\n";
			echo '      .specBodyText { margin: 7px; font-size: 12pt; font-family: monospace;  }', "\n";		
			echo '      ', "\n";
			echo '    </style>', "\n";
			echo '  </head>', "\n";
			echo '<body>', "\n";

			foreach ($this->meta as $arg) {
				$arg->toHtml();
				echo '<div class="specSeparator"></div>', "\n";
			}

			echo '</body>', "\n";
			echo '</html>';
		}
	}
?>
