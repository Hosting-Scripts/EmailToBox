<?php 
/**
 * Handler for signup steps
 * 
 * @author Bruno Morency bruno@context.io
 * @copyright DokDok inc.
 * @license GNU Affero General Public License v3 http://www.gnu.org/licenses/agpl.html
 */

if (!isset($step)) {
	$step = (count($rewriteQuery) >= 1) ? array_shift($rewriteQuery) : '';
}

function errorPage($message, $linkHref = false, $linkLabel = false) {
	$bodyVars = array(
		"errorMessage" => $message
	);
	if ($linkHref) {
		$bodyVars['linkTo'] = array(
			"href" => $linkHref,
			"label" => $linkLabel
		);
	}
	include(INTERFACE_SIGNUP_ERROR);
	die();	
}


if ($step == 'email' && !empty($_SESSION['userId'])) {
	
	//
	// we have an authenticated box user, now we ask user which email accounts he 
	// wants to connect to. Default will be the email address form his Box account.
	//

	$templateVars['step'] = $step;
	$templateVars['defaultEmail'] = $_SESSION['signupDefaultEmail'];
	include(INTERFACE_SIGNUP_STEP_EMAIL);
}

else if ($step == 'contextio') {
	
	//
	// Connect the given email account to our Context.IO API key.
	//
	// Get a connect token from Context.IO and redirect browser to that URL
	// to associate the mailbox of the new user to our Context.IO API key
	//

	$ctxIO = new ContextIO(CONTEXTIO_CONSUMER_KEY, CONTEXTIO_CONSUMER_SECRET);
	$ctxIO->saveHeaders(true);
	if (empty($_POST['mailbox_email'])) {
		$templateVars['step'] = $step;
		$templateVars['defaultEmail'] = $_SESSION['signupDefaultEmail'];
		$templateVars['feedback'] = array('class'=>'error', 'message_code'=>'email_missing');
		include(INTERFACE_SIGNUP_STEP_EMAIL);
	}
	else {
		unset($_SESSION['signupDefaultEmail']);
		$r = $ctxIO->addConnectToken(null, array(
			"callback_url" => APP_URL."/signup/contextio-callback",
			"email" => strval($_POST['mailbox_email']),
			"service_level" => "pro"
		));
		if ($r === false) {
			appLogEntry("ERROR getting connect token: [". $ctxIO->getLastResponse()->getHttpCode() ."] ". $ctxIO->getLastResponse()->getRawResponse());
			errorPage("Unable to get initiate connection for your email account.");
		} 
		else {
			$token = $r->getData();
			$_SESSION['ContextIO-connectToken'] = $token['token'];
			header("Location: ". $token['browser_redirect_url']);
			die();
		}
	}

}

else if ($step == 'contextio-callback') {

	//
	// This is what Context.IO redirects the browser to once the user configured
	// his mailbox with our API key.
	//
	
	// get account information about the token that has been used
	$ctxIO = new ContextIO(CONTEXTIO_CONSUMER_KEY, CONTEXTIO_CONSUMER_SECRET);
	$r = $ctxIO->getConnectToken(null, trim($_GET['contextio_token']));
	if ($r === false || $r->getDataProperty('used') == 0 || $r->getDataProperty('token') != $_SESSION['ContextIO-connectToken']) {
		appLogEntry("ERROR getting connect token information on callback: [". $ctxIO->getLastResponse()->getHttpCode() ."] ". $ctxIO->getLastResponse()->getRawResponse());
		errorPage("Unable to get email account settings.");
	}
	unset($_SESSION['ContextIO-connectToken']);

	$tokenInfo = $r->getData();
	$user = new User(array('id' => $_SESSION['userId']));
	$sourcesInfo = array();
	foreach ($tokenInfo['account']['sources'] as $source) {
		$sourcesInfo[] = array(
			'email' => $tokenInfo['email'], 
			'label' => $source['label'], 
			'username' => $source['username'], 
			'server' => $source['server']
		);
	}
	$user->updateContextIoInfo(array(
		'id' => $tokenInfo['account']['id'],
		'addr'=> $tokenInfo['account']['email_addresses'],
		'src' => $sourcesInfo
	));

	// create folder pair
	try {
		$user->createFolderPair(DEFAULT_FOLDERPAIR_NAME_BOX, DEFAULT_FOLDERPAIR_NAME_EMAIL);
	} catch (Exception $e) {
		appLogEntry("Exception creating new folder pair: [".$e->getFile().":".$e->getLine()."] ". $e->getMessage());
		errorPage("Unable to create the EmailToBox folders: ".$e->getMessage());
	}

	header('location: /signup/done');
	die();
}

else if ($step == 'box') {
	
	//
	// First step is to have the user authenticate in his box account
	// and get an auth_token for that account.
	//
	
	try {
		$box = new Box_Rest_Client(BOX_API_KEY);
		$ticket = $box->get_auth_ticket();
		$_SESSION['boxAuthTicket'] = $ticket;
		$_SESSION['boxAuthContext'] = 'signup';
		header('location: https://www.box.net/api/1.0/auth/'.$ticket);
	} catch (Exception $e) {
		appLogEntry("Error getting auth ticket from Box");
		errorPage("Error authenticating with Box");
	}
}

elseif ($step == 'boxAuthCallback') {
	
	if ($boxAuthTicket != $_SESSION['boxAuthTicket']) {
		appLogEntry("Error authenticating with Box - invalid ticket");
		errorPage("Error authenticating with Box");
	}	
	unset($_SESSION['boxAuthTicket']);
	unset($_SESSION['boxAuthContext']);
			
	// create a user record with the box.net user id and email
	$box = new Box_Rest_Client(BOX_API_KEY);
	$res = $box->get('get_account_info',array('auth_token' => $boxAuthToken));

	if($res['status'] === 'get_account_info_ok') {
	
		// make sure this Box account doesn't exist in our records
		if (User::boxUserIdExists($res['user']['user_id'])) {
			appLogEntry("Trying to signup with a Box user id {$res['user']['user_id']} that is already known.");
			errorPage("Seems that we already have a user for this Box account.", "/login", "Login with this account");
		}
		else {
			$userId = User::addUser(array(
				'user_id' => $res['user']['user_id'],
				'auth_token' => $boxAuthToken,
				'max_upload_size' => $res['user']['max_upload_size'],
				'email' => $res['user']['email']
			));
		}
		$_SESSION['userId'] = $userId;
		$_SESSION['signupDefaultEmail'] = $res['user']['email'];

		// move on to next step in signup
		header('location: /signup/email');
	}
	else {
		appLogEntry("Error getting Box user info for id {$res['user']['user_id']}.");
		errorPage("Error getting account info from Box.");
	}
}

elseif ($step == 'done') {
	include(INTERFACE_SIGNUP_STEP_FINAL);
}

else {
	// this will show the signup starting point with
	// a big button getting users to the 'box' step
	include(INTERFACE_SIGNUP_STEP_BOX);
}

?>