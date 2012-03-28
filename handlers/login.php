<?php 
/**
 * Handle sign-in process using Box as auth provider
 * 
 * @author Bruno Morency bruno@context.io
 * @copyright DokDok inc.
 * @license GNU Affero General Public License v3 http://www.gnu.org/licenses/agpl.html
 */

function redirectToApp($user) {
	if (!is_a($user, 'User')) {
		$user = new User(array('id'=>$user));
	}
	$fp = $user->getFolderPair();
	header('location: /app');
}

if (array_key_exists('userId', $_SESSION) && User::userIdExists($_SESSION['userId'])) {
	redirectToApp($_SESSION['userId']);
}
elseif ($step == 'boxAuthCallback') {
	
	if ($boxAuthTicket != $_SESSION['boxAuthTicket']) {
		die("Error authenticating with box.net - invalid ticket");
	}	
			
	// check if we have a user matching this Box account
	$box = new Box_Rest_Client(BOX_API_KEY);
	$res = $box->get('get_account_info', array('auth_token' => $boxAuthToken));

	if($res['status'] === 'get_account_info_ok') {
		if (User::boxUserIdExists($res['user']['user_id'])) {
			$user = new User(array('boxUserId' => $res['user']['user_id']));
			$user->updateBoxInfo(array(
				'auth_token' => $boxAuthToken,
				'max_upload_size' => $res['user']['max_upload_size'],
				'email' => $res['user']['email']
			));
			$_SESSION['userId'] = $user->getId();
			unset($_SESSION['boxAuthTicket']);
			unset($_SESSION['boxAuthContext']);
			redirectToApp($user);
		}
		else {
			// this Box user doesn't exist, redirect to signup process 
			// as if user just signed in their Box account
			include_once(HANDLERS_SIGNUP);
		}
	}
	else {
		die("Error getting account info");
	}
}
else {
	// we either have no session opened or this user has no Box auth token
	try {
		$box = new Box_Rest_Client(BOX_API_KEY);
		$ticket = $box->get_auth_ticket();
		$_SESSION['boxAuthTicket'] = $ticket;
		$_SESSION['boxAuthContext'] = 'login';
		header('location: https://www.box.net/api/1.0/auth/'.$ticket);
	} catch (Exception $e) {
		die("Error authenticating with box.net");
	}
}

?>