<?php 
/**
 * Handler for UI once users are logged in
 * 
 * @author Bruno Morency bruno@context.io
 * @copyright DokDok inc.
 * @license GNU Affero General Public License v3 http://www.gnu.org/licenses/agpl.html
 */

if (!array_key_exists('userId', $_SESSION) || !User::userIdExists($_SESSION['userId'])) {
	header('location: /login');
	die();
}
$user = new User(array('id'=>$_SESSION['userId']));

$task = (count($rewriteQuery) >= 1) ? array_shift($rewriteQuery) : '';
$fpId = (count($rewriteQuery) >= 1) ? array_shift($rewriteQuery) : '';
$res = (count($rewriteQuery) >= 1) ? array_shift($rewriteQuery) : '';

$fp = (empty($fpId)) ? $user->getFolderPair() : $user->getFolderPair($fpId);
if (empty($fpId)) $fpId = $fp->getId();

if ($task == 'fp') {
	if ($res == 'pause') {
		if ($fp->pause()) {
			echo json_encode($fp->getInfo(false));
		} 
		else {
			echo json_encode(array('error' => true));
		}
	}
	else if ($res == 'resume') {
		if ($fp->resume()) {
			echo json_encode($fp->getInfo(false));
		} 
		else {
			echo json_encode(array('error' => true));
		}
	}
	else if ($res == 'info') {
		echo json_encode($fp->getInfo(false));
	}
	else if ($res == 'history') {
		$offset = (count($rewriteQuery) >= 1) ? intval(array_shift($rewriteQuery)) : 0;
		echo json_encode($fp->getHistory($offset));
	}
}
else {
	$headerVars['showLogout'] = true;
	$bodyVars['dataTransfer'] = array(
		'usage' => $user->getCurrentUsage(),
		'quota' => $user->getUsageQuota()
	);
	include_once(INTERFACE_APP);
}
?>