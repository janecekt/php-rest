Simple PHP Library to expose REST services via PHP

# Example #
In the exaple bellow we'll create a simple REST endpoint with the following calls:
* GET /api/myservice/{id}/detail (re-written to /api/myservice.php/myservice/{id}/detail)
  * Returning the detail of the item with the specified id.
* GET /api/myservice/{id} (re-written to /api/myservice.php/myservice/{id}/detail)
  * Returning the item with the specified id
* GET /api/myservice?from={from}&to={to}"
  * Returing items between from and to
* POST /api/myservice/id   WITH BODY '{"action": "addToFavourites" }'
  * Adds the item with the ID to favourites
* POST /api/myservice/id   WITH BODY '{"action": "removeFromFavourites" }'
  * Removes the item with the ID from favourites
* GET /api/myservice/help
  * Returns an auto-generated spec of the REST API


## Step 1: SetUp .htaccess in Apache as follows ##
```
## Set apache to accept PATH_INFO - i.e. my.domain.com/api/myservice.php/myservice
## (server returns 404 if this is not enabled)
AcceptPathInfo On

## Apply rewrite rules
Options +FollowSymLinks
RewriteEngine On

## Rewrite for /api/myservice
RewriteRule ^myservice(/.*)?$ /api/myservice.php/myservice/$1 [QSA,L]
```

## Step 2: Write dispatching logic - myservice.php
```php
<?php 
	// Import Libraries
	include 'php-rest/php_rest_exceptions.php';
	include 'php-rest/php_rest_output.php';

	// Enable logging (remove this for production use)
	// Output::setLoggingEnabled();

	// Import Input calls
	include 'php-rest/php_rest_input.php';
	include 'php-rest/php_rest_meta.php';
	include 'php-rest/php_rest_dispatcher.php';

	// Import Business logic
	include 'myservice-logic.php';

	// Import DB & configure
	include 'php-rest/php_rest_db.php';
	DB::configure('mysql:host=MyHostname;dbname=MyDbName;charset=utf8', 'MyDbUsername', 'MyDbPassword' );

	// Create & configure dispatcher
	$app = new Dispatcher();

	// GET /myservice/{id}/detail
	$app->get("/myservice/{id:[0-9]+}/detail",
		array(
			Meta::pathArg('id', 'ID of the item'),
			Meta::getCall('Returns the item details for item with the given ID.')
		),
		function ($p_id) {
			$data = MyServiceLogic::getItemDetail($p_id);
			Output::sendNonCacheableData($data);
		});

	// GET /myservice/{id}
	$app->get("/myservice/{id:[0-9]+}",
		array(
			Meta::pathArg('id', 'ID of the item'),
			Meta::getCall('Returns the item with the given ID.')
		),
		function ($p_id) {
			$data = MyServiceLogic::getItem($p_id);
			Output::sendNonCacheableData($data);
		});


	// GET "/myservice?from={from}&to={to}"
	$app->get("/myservice",
		array(
			Meta::getCall('Returns all items with dates specified by from and to.')
				->mandatoryArg("from", "From date (YYYY-MM-DD)")
				->mandatoryArg("to", "To date (YYYY-MM-DD)")
		),
		function ($from, $to) {
			// Validate args
			Input::guardNotNull("Parameter [from]", $from);
			Input::guardNotNull("Parameter [to]", $to);

			// Parse args
			$from = Input::parseDateTime("Parameter[from]", $from, '!Y-m-d');
			$to = Input::parseDateTime("Parameter[to]", $to, '!Y-m-d');

			// Invoke logic
			$data = MyServiceLogic::getItems($from, $to);		
			Output::sendNonCacheableData($data);
		});


	// POST "/myservice/{id}"
	$app->post('/myservice/{id:[0-9]+}',
		array(
			Meta::pathArg('id', 'ID of the item'),
			Meta::postCall( 
				'{"action": "addToFavourites" }', 
				'Adds the item with the given ID to favourites and RETURNS the updated Item' )
			Meta::postCall( 
				'{"action": "removeFromFavourites" }', 
				'Removes the item with the given ID from favourites and RETURNS the updated Item' )
		), 
		function ($p_id, $body) {
			// Extract action from body
			$action = $body["action"];

			switch($action) {
				// Request body { "action": "addToFavourites" }
				case "addToFavourites":
					$data = MyServiceLogic::addToFavourites($p_id);
					Output::sendNonCacheableData($data);
					return;		

				// Request body { "action": "removeFromFavourites" }
				case "removeFromFavourites":
					$data = MyServiceLogic::removeFromFavourites($p_id);
					Output::sendNonCacheableData($data);
					return;
			}
			
			// Unsupported action
			throw new BadRequestException("Unsupported action requested action=" . $action);
		});	


	// GET "/myservice/help"
	$app->get('/myservice/help',
		array(
			Meta::getCall( 'Returns this help' )
		), 
		function () use($app) {
			$app->specHtml();
		});	



	// Run handler for this request
	$app->run();
```

## Step 3: Write business logic - myservice-logic.php
```php
<?php	
	// Contains actual business logic
	class MyServiceLogic {
		const ITEM_TYPE = 'Item';
		const ITEM_DETAIL_TYPE = 'ItemDetail';

		// Returns all items between from and to
		public static function getItems($user_id, $from, $to) {
			$query = "SELECT i.id , "
					. "i.from_date as `from_date::ISO-DATE-TIME-STRING`, "
					. "i.to_date as `to_date::ISO-DATE-TIME-STRING`, "
					. "i.desc as `desc` "
				. "FROM items i "
				. "WHERE i.from_date >= :from_date and i.to_date <= :to_date";

			return DB::queryForArray(self::ITEM_TYPE,
				$query, 
				array('from_date' => $from->format(DB::MYSQL_DATE_FORMAT), 
          'to_date' => $to->format(DB::MYSQL_DATE_FORMAT)) );
		}

		// Returns an item with the given id
		public static function getItem($item_id) {
			$query = "SELECT i.id , "
					. "i.from_date as `from_date::ISO-DATE-TIME-STRING`, "
					. "i.to_date as `to_date::ISO-DATE-TIME-STRING`, "
					. "i.desc as `desc` "
				. "FROM items i "
				. "WHERE i.id = :item_id";

			$result = DB::queryForArray(self::ITEM_TYPE,
				$query, 
				array('item_id' => $item_id) );
			
			if (count($result) != 1) {
			 	throw new NotFoundException("Item with id " . $item_id . " was not found !");
			}
			return $result[0];
		}
		

		// Returns training detail
		public static function getItemDetail($item_id) {
			$query = "SELECT i.* "
				. "FROM item_details i "
				. "WHERE i.id = :item_id";

			$result = DB::queryForArray(self::ITEM_DETAIL_TYPE,
				$query, 
				array('item_id' => $item_id) );
			
			if (count($result) != 1) {
			 	throw new NotFoundException("Item detail with id " . $item_id . " was not found !");
			}
			return $result[0];
	  }


		// Add item to favourites
    public static function addToFavourites($item_id) {
			$query = 'INSERT IGNORE INTO favourites (item_id) VALUES (:item_id)';
			DB::executeStatement($query, array('item_id' => $item_id));

			// Return updated training
			return self::getItem($item_id);
		}

		// Remove given training to favourites
    public static function removeFromFavourites($item_id) {
			$query = "DELETE FROM favourites fav WHERE item_id = :item_id";
			DB::executeStatement($query, array('item_id' => $item_id));

			// Return updated training
			return self::getItem($item_id);
		}
	}
?>
```

# Licence #
```
Copyright (c) 2008 - Tomas Janecek.

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
```
