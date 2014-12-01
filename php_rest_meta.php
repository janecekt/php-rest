<?php 
	// Metadata for the argument
	class MetaArg {
		public $name;
		public $isMandatory;
		public $desc;

		public function __construct() { 
			$args = func_get_args();
			$this->name = $args[0];
			$this->isMandatory = $args[1];
			$this->desc = $args[2];
		}
	}


	// Metadata for a general call
	class MetaCall {
		public $path;
		public $pathArgs = array();
		public $desc;
	}


	// Metadata for GET call
	class MetaGetCall extends MetaCall {
		public $args = array();

		public function mandatoryArg($name, $desc) {
			$this->args[] = new MetaArg($name, true, $desc);
			return $this;
		}

		public function optionalArg($name, $desc) {
			$this->args[] = new MetaArg($name, false, $desc);
			return $this;
		}

		public function toHtml() {
			echo '<table>', "\n";

			// Path
			echo '  <tr><td class="specTitle" colspan="2">GET ' , $this->path , '</td></tr>', "\n";
			echo '  <tr><td class="specDesc" colspan="2">', $this->desc , '</td></tr>', "\n";

			// Path args
			if (count($this->pathArgs) > 0) {
				echo '  <tr><td class="specPathArgTitle" colspan="2">Path Arguments</td></tr>', "\n";
				foreach ($this->pathArgs as $arg) {
					echo '  <tr><td class="specPathArgName">' , $arg->name , '</td>',
							'<td class="specPathArgDesc">' , $arg->desc , '</td></tr>', "\n";
				}
			}

			// Args
			if (count($this->args) > 0) {
				echo '  <tr><td class="specUrlArgTitle" colspan="2">URL Arguments</td></tr>', "\n";
				foreach ($this->args as $arg) {
					echo '  <tr><td class="specUrlArgName">' , $arg->name, '</td>',
							'<td class="specUrlArgDesc">',  $arg->isMandatory ? '[mandatory]' : '[optional]',
								': ', $arg->desc , '</td></tr>', "\n";
				}
			}

			echo '</table>', "\n";
		}

	}


	// Metadata for POST call
	class MetaPostCall extends MetaCall {
		public $body;

		public function toHtml() {
			echo '<table>', "\n";

			// Path
			echo '  <tr><td class="specTitle" colspan="2">POST ' , $this->path , '</td></tr>', "\n";
			echo '  <tr><td class="specDesc" colspan="2">', $this->desc , '</td></tr>', "\n";

			// Path args
			if (count($this->pathArgs) > 0) {
				echo '  <tr><td class="specPathArgTitle" colspan="2">Path Arguments</td></tr>', "\n";
				foreach ($this->pathArgs as $arg) {
					echo '  <tr><td class="specPathArgName">' , $arg->name , '</td>',
							'<td class="specPathArgDesc">' , $arg->desc , '</td></tr>', "\n";
				}
			}

			echo '  <tr><td class="specBodyTitle" colspan="2">Body</td></tr>', "\n";
			echo '  <tr><td colspan="2"><pre class="specBodyText">' , $this->body , '</pre></td></tr>', "\n";

			echo '</table>', "\n";
		}


	}


	// Helper method holding metata
	class Meta {
		public static function postProcessPost($path, $args) {
			// Filer and validate
			$result = array();
			$pathArgs = array();
			foreach($args as $arg) {
				if ($arg instanceof MetaArg) {
					$pathArgs[] = $arg;
				} else if ($arg instanceof MetaPostCall) {
					$result[] = $arg;
				} else {
					throw new InternalErrorException("Unsupported type " . $arg);
				}
			}

			// Backfill
			foreach($result as $arg) {
				$arg->path = $path;
				$arg->pathArgs = $pathArgs;	
			}

			return $result;
		}

		public static function postProcessGet($path, $args) {
			// Filer and validate
			$result = array();
			$pathArgs = array();
			foreach($args as $arg) {
				if ($arg instanceof MetaArg) {
					$pathArgs[] = $arg;
				} else if ($arg instanceof MetaGetCall) {
					$result[] = $arg;
				} else {
					throw new InternalErrorException("Unsupported type " . $arg);
				}
			}

			// Backfill
			foreach($result as $arg) {
				$arg->path = $path;
				$arg->pathArgs = $pathArgs;	
			}

			return $result;
		}


		public static function pathArg($name, $desc) {
			return new MetaArg($name, true, $desc);
		}

		public static function getCall($desc) {
			$d = new MetaGetCall();
			$d->desc = $desc;
			return $d;
		}

		public static function postCall($body, $desc) {
			$d = new MetaPostCall();
			$d->body = $body;
			$d->desc = $desc;
			return $d;
		}
	}
?>