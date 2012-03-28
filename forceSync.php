#!/usr/bin/php
<?php 
/**
 * CLI script to force a sync of all user accounts
 * 
 * @author Bruno Morency bruno@context.io
 * @copyright DokDok inc.
 * @license GNU Affero General Public License v3 http://www.gnu.org/licenses/agpl.html
 */

include_once("conf.php");

ini_set('display_errors','Off');
ini_set('error_log',PHP_ERROR_LOG);

require_once(UTILS);
include_once(CONTEXTIO);

echo "Connecting to database ... ";
$dbCx = DBConnection::singleton()->getConnection();
$coll = $dbCx->selectCollection(DB_NAME, 'users');
echo "done\n";

echo "Getting list of users and accounts to sync ... ";

$results = $coll->find(array(), array('contextio'=>1));
if (is_null($results)) {
	return null;
} else {
	echo "done\n";
	
	$ctxIO = new ContextIO(CONTEXTIO_CONSUMER_KEY, CONTEXTIO_CONSUMER_SECRET);
	$ctxIO->saveHeaders(true);

	foreach ($results as $user) {
		if (array_key_exists('contextio', $user)) {
			foreach ($user['contextio'] as $accnt) {
				echo "Sending sync to ContextIO account id ".$accnt['id']." ... ";
				$r = $ctxIO->syncSource($accnt['id']);
				if ($r === false) {
					echo "failed\n";
					appLogEntry("ERROR synching ContextIO account {$accnt['id']}: [". $ctxIO->getLastResponse()->getHttpCode() ."] ". $ctxIO->getLastResponse()->getRawResponse());
				} 
				else {
					echo "done\n";
				}

			}
		}
	}
}		


?>