<?php
	// Facilitates communication with the DB
	class DB {
		const MYSQL_DATE_FORMAT = 'Y-m-d H:i:s';

		private static $db_data_source = null;
		private static $db_user = null;
		private static $db_pass = null;

		private static $type_key = 'type';
		private static $pdo;


		// Configures a connection attributes
		public static function configure($data_source, $username, $password) {
			self::$db_data_source = $data_source;
			self::$db_user = $username;
			self::$db_pass = $password;
		}


		// Sets the type key used inside queryForArray as key to denote the result type
		public static function setTypeKey($type_key) {
			self::$type_key = $type_key;
		}


		// Runs a DB  query and converts the result into associative array (serializable to JSON)
		// $type       - name of the type (may be null)
		// $query      - query to execute (with ?) for positional parametrs e.g. 'SELECT * FROM table WHERE column > ?'
		// $args       - array of query arguments e.g. array('col value 1', 'col value 2')
		public static function queryForArray($type, $query, $args) {
			try {
				// Log
				if ($type != null) {
					Output::dumpTrace("==> Executing Query for ", $type, ": ", $query, " with ", $args );
				} else {
					Output::dumpTrace("==> Executing Query ", $query, " with ", $args );
				}

				// Get pdo
				$pdo = self::getPdo();

				// Prepare & Execute statement
				$stmt = $pdo->prepare($query);
				$stmt->execute( $args );

				// Fetch result
				$result = array();
				while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
					// Add type information
					if ($type != null) {
						$row = array(self::$type_key => $type) + $row;
					}
					
					// Add row data to result
					$result[] = $row;
				}

				// Dump and return result
				Output::dumpVar($result);
				return $result;
			} catch(Exception $e) {	
				// Convert DB exception
				throw new InternalErrorException('Chyba DB: ' . $e->getMessage());
			}
		}	


		// Executes a DB statenent into the DB (INSERT/UPDATE/DELETE).
		// $query      - query to execute (with ?) for positional parametrs e.g. 'DELETE FROM table WHERE column > ?'
		// $args       - array of query arguments e.g. array('col value 1', 'col value 2')
		public static function executeStatement($query, $args) {
			try {
				// Get pdo
				$pdo = self::getPdo();

				// Prepare & Execute statement
				Output::dumpTrace("==> Executing Statement ", $query, " with ", $args );
				$stmt = $pdo->prepare($query);
				$stmt->execute( $args );

				// Return number of affected rows
				Output::dumpTrace("==> Affected rows ", $stmt->rowCount());
				return $stmt->rowCount();
			} catch(Exception $e) {	
				// Finally - Close connection		
				throw new InternalErrorException('Chyba DB: ' . $e->getMessage());
			}
		}	


		// Return an instance of PDO
		private static function getPdo() {
			if (self::$pdo == null) {
				Output::dumpTrace("==> Creating PDO source=", self::$db_data_source, " , user=", self::$db_user, self::$db_pass == null ? ", password=NO" : ", password=YES" );
				self::$pdo = new PDO(self::$db_data_source, self::$db_user, self::$db_pass,
					array(
					    PDO::ATTR_PERSISTENT => true,
					    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
					));
			}
			return self::$pdo;
		}
	}
?>