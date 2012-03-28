<?php
/**
 * Apache rewrites all HTTP requests to the app to this script
 * 
 * @author Bruno Morency bruno@context.io
 * @copyright DokDok inc.
 * @license GNU Affero General Public License v3 http://www.gnu.org/licenses/agpl.html
 */

include_once("../conf.php");

ini_set('display_errors','Off');
ini_set('error_log',PHP_ERROR_LOG);

require_once(UTILS);
include_once(BOX_PHP_SDK);
include_once(CONTEXTIO);

/*
Apache is configured to rewrite URLs for this app as follows:

<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteCond %{REQUEST_URI} !^/(.*\.(png|gif|jpg|jpeg|css|less|js|ico))$
    RewriteCond %{REQUEST_URI} !^/$
    RewriteRule ^/?(.*)$ /index.php?_q=$1 [QSA,L]
</IfModule>
*/
if (array_key_exists('_q', $_GET)) {
	$rewriteQuery = explode('/', preg_replace('/^\//','',$_GET['_q']));
}
$action = array_shift($rewriteQuery);


session_save_path(SESSION_PATH);
session_start();

$includeJS = array();
$includeJS[] = "libs/modernizr.custom.84908.js";
$includeJS[] = "libs/jquery.ba-hashchange.min.js";
$includeJS[] = "libs/dateToLocaleFormat.js";
$includeJS[] = "libs/jsrender.js";

if ($action == 'boxauth' && array_key_exists('auth_token',$_GET)) {
	// this is the callback box.net redirects to after a user has logged in	
		
	$boxAuthTicket = strval($_GET['ticket']);
	$boxAuthToken = strval($_GET['auth_token']);
	$step = 'boxAuthCallback';

	if ($_SESSION['boxAuthContext'] == 'signup') {
		include_once(HANDLERS_SIGNUP);
	}
	else if ($_SESSION['boxAuthContext'] == 'login') {
		include_once(HANDLERS_LOGIN);
	}
}

else if ($action == 'signup') {
	include_once(HANDLERS_SIGNUP);
}

else if ($action == 'webhooks') {
	include_once(HANDLERS_WEBHOOK);
}

else if ($action == 'app') {
	$includeJS[] = "app.js";
	include_once(HANDLERS_APP);
}

else if ($action == 'login') {
	include_once(HANDLERS_LOGIN);
}

else if ($action == 'logout') {
	$_SESSION = array();
	if (ini_get("session.use_cookies")) {
		$params = session_get_cookie_params();
		setcookie(session_name(), '', time() - 42000,
			$params["path"], $params["domain"],
			$params["secure"], $params["httponly"]
		);
	}
	session_destroy();
	header('location: /');
}

else {
	// show public intro page with "get started" button
	include(INTERFACE_HOME);
}

?>