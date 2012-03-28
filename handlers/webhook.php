<?php
/**
 * Handler for webhook callbacks when a user puts a message
 * in the EmailToBox email folder.
 * 
 * @author Bruno Morency bruno@context.io
 * @copyright DokDok inc.
 * @license GNU Affero General Public License v3 http://www.gnu.org/licenses/agpl.html
 */

date_default_timezone_set('America/Montreal');
function logIt($message) {
	global $webHookData;
	if (preg_match('/\n$/',$message) == 0) $message .= "\n";
	file_put_contents(WEBHOOKS_TRACE_LOG, date('r')." [{$webHookData['webhook_id']}]: ".$message, FILE_APPEND);
}

function returnNotFound($deleteWebHook = false) {
	global $ctxIO, $webHookData;
	logIt("Can't find related account info, returning 404.");
	if ($deleteWebHook && ALLOW_UNKNOWN_WEBHOOKS_DELETE) {
		logIt("Deleting webhook {$webHookData['webhook_id']} on account {$webHookData['account_id']}");
		$ctxIO->deleteWebhook($webHookData['account_id'], $webHookData['webhook_id']);
	}
	header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found", true, 404);
	die;	
}

function getMessageBody($accountId, $messageId) {
	global $ctxIO;
	$r = $ctxIO->getMessageBody($accountId, array(
		'message_id' => $messageId,
		'type' => 'text/plain'
	));
	if ($r === false) {
		// unable to retrieve message body
		logIt("Unable to download message body with given message_id ($messageId): [".$ctxIO->getLastResponse()->getHttpCode()."] ".$ctxIO->getLastResponse()->getRawResponse());
		header($_SERVER["SERVER_PROTOCOL"]." 400 Bad Request", true, 400);
		die;
	}
	else {
		$comment = '';
		foreach ($r->getData() as $bodyPart) {
			$comment .= "\n".$bodyPart['content'];
		}
		return $comment;
	}
}

function getThreadMessageIds($accountId, $messageId) {
	global $ctxIO;
	$r = $ctxIO->getMessageThread($accountId, $messageId);
	if ($r === false) {
		// unable to retrieve thread
		logIt("Unable to retrieve message thread for message_id ($messageId): [".$ctxIO->getLastResponse()->getHttpCode()."] ".$ctxIO->getLastResponse()->getRawResponse());
		header($_SERVER["SERVER_PROTOCOL"]." 400 Bad Request", true, 400);
		die;
	}
	else {
		$msgs = $r->getDataProperty('messages'); 
		$msgIds = array();
		foreach ($msgs as $msg) {
			$msgIds[] = $msg['message_id'];
		}
		return $msgIds;
	}
}

function getVersionsFileId($accountId, $fileId) {
	global $ctxIO;
	$r = $ctxIO->listFileRevisions($accountId, $fileId);
	if ($r === false) {
		// unable to retrieve thread
		logIt("Unable to retrieve file revisions for file_id ($fileId): [".$ctxIO->getLastResponse()->getHttpCode()."] ".$ctxIO->getLastResponse()->getRawResponse());
		header($_SERVER["SERVER_PROTOCOL"]." 400 Bad Request", true, 400);
		die;
	}
	else {
		$revs = $r->getData();
		$revFileIds = array();
		foreach ($revs as $rev) {
			$revFileIds[] = $rev['file_id'];
		}
		return $revFileIds;
	}
}

if ($_SERVER['REQUEST_METHOD'] != 'POST') {
	header($_SERVER["SERVER_PROTOCOL"]." 405 Method Not Allowed", true, 405);
	die;	
}

//
// Decode WebHook request body and validate its signature
//

$webHookData = json_decode(file_get_contents('php://input'), true);

if (!is_array($webHookData) || !array_key_exists('signature', $webHookData)) {
	header($_SERVER["SERVER_PROTOCOL"]." 400 Bad Request", true, 400);
	die;	
}

logIt("Callback received for WebHook {$webHookData['account_id']} with code '{$rewriteQuery[0]}'");

if ($rewriteQuery[0] && substr($rewriteQuery[0], 0, 3) == 'fp-') {
	$folderPairId = array_pop(explode('-', $rewriteQuery[0]));
}
else {
	returnNotFound();
}

// check webhook data signature to authetifiy it's coming from Context.IO
if ($webHookData['signature'] != hash_hmac('sha256', $webHookData['timestamp'].$webHookData['token'], CONTEXTIO_CONSUMER_SECRET)) {
	logIt("Invalid signature (timestamp.token, signature): '{$webHookData['timestamp']}{$webHookData['token']}', '{$webHookData['signature']}'");
	header($_SERVER["SERVER_PROTOCOL"]." 401 Unauthorized", true, 401);
	die;	
}


$box = new Box_Rest_Client(BOX_API_KEY);
$ctxIO = new ContextIO(CONTEXTIO_CONSUMER_KEY, CONTEXTIO_CONSUMER_SECRET);
$ctxIO->saveHeaders(true);

// retrieve user and folder pair for this webhook notification
$user = new User(array('contextIoId' => $webHookData['account_id']));
if (is_null($user->getId())) returnNotFound(true);
$folderPair = $user->getFolderPair($folderPairId);
if (is_null($folderPair)) returnNotFound(true);
$box->set_auth_token($user->getBoxAuthToken());

//
// if there are attachments on this messsage, transfer them to the Box folder	
//

if (is_array($webHookData['message_data']['files']) && count($webHookData['message_data']['files']) >= 1) {

	logIt("Getting message body");
	$comment = getMessageBody($webHookData['account_id'], $webHookData['message_data']['message_id']);

	// if tracking versions is enabled on this folder pair, get entries for this file
	// from past uploads
	if ($folderPair->versionHistory()) {
		$threadMessageIds = getThreadMessageIds($webHookData['account_id'], $webHookData['message_data']['message_id']);
		$historyEntries = $folderPair->searchHistoryForMessages(getThreadMessageIds($webHookData['account_id'], $webHookData['message_data']['message_id']));
	}

	foreach ($webHookData['message_data']['files'] as $file) {

		// check if downloading that file will exceed user's quota
		if (!$user->isWithinQuota($file['size'])) {
			logIt("Dropping file_id {$file['file_id']}, it would exceed user's quota.");
			continue;
		}

		// if we're tracking versions, get version history for that file to see
		// if there's a previous already uploaded from the same thread
		$boxFileId = false;
		if ($folderPair->versionHistory() && !is_null($historyEntries)) {
			$revsFileId = getVersionsFileId($webHookData['account_id'], $file['file_id']);
			foreach ($historyEntries as $entry) {
				if ($entry['type'] == 'file' && in_array($entry['fileInfo']['contextio']['file_id'], $revsFileId)) {
					$boxFileId = $entry['fileInfo']['box']['file_id'];
					break;
				}
			}
		}
		
		// Download the attachment from the mailbox

		$tmpFileName = TMP_FILE_DIR."/".time().mt_rand(100,999);
		logIt("Downloading file_id {$file['file_id']} to $tmpFileName");
		$ctxIO->getFileContent($webHookData['account_id'], $file['file_id'], $tmpFileName);

		// the size property of the file given in Context.IO webhook data is the size of the 
		// body-part containing the base-64 encoded attachment, we want to log the size of 
		// the file in it's "normal" form.
		$actualFileSize = @filesize($tmpFileName);

		// upload it to Box
		$cFile = new Box_Client_File($tmpFileName, $file['file_name']);
		if ($boxFileId !== false) {
			$cFile->import(array('@attributes' => array(
				'id' => $boxFileId,
				'folder_id' => $folderPair->getBoxFolderId()
			)));	
			logIt("Uploading new version of Box file_id $boxFileId to folder_id ". $folderPair->getBoxFolderId());
			$status = $box->upload($cFile);
		}
		else {
			$cFile->import(array('@attributes' => array(
				'folder_id' => $folderPair->getBoxFolderId()
			)));	
			logIt("Uploading to Box folder_id ". $folderPair->getBoxFolderId());
			$status = $box->upload($cFile, array('new_copy'=>1));
		}

		// update data transfer quota
		$user->incrementCurrentUsage($actualFileSize);

		// log a new entry in the transfer history
		$fileInfo = array(
			'contextio' => array(
				'message_id' => $webHookData['message_data']['message_id'],
				'date' => $webHookData['message_data']['date'],
				'file_id' => $file['file_id'],
				'size' => $actualFileSize,
				'type' => $file['type'],
				'file_name' => $file['file_name'],
				'file_name_structure' => $file['file_name_structure'],
				'subject' => $webHookData['message_data']['subject'],
				'sender' => $webHookData['message_data']['addresses']['from']
			),
			'box' => array(
				'file_id' => $cFile->attr('id'),
				'folder_id' => $cFile->attr('folder_id'),
				'file_name' => $cFile->attr('file_name')
			)
		);
		if (array_key_exists('gmail_message_id', $webHookData['message_data'])) {
			$fileInfo['contextio']['gmail_message_id'] = $webHookData['message_data']['gmail_message_id'];
			$fileInfo['contextio']['gmail_thread_id'] = $webHookData['message_data']['gmail_thread_id'];
		}
		$folderPair->logFileTransfer($fileInfo, $status, ($boxFileId === false) ? 'file' : 'rev', ($status == 'upload_ok'));

		// if the upload work as expected, add the message body as a comment on that Box file
		if ($status == 'upload_ok') {
			logIt("Adding message body as comment on file we just created in Box (id: ".$cFile->attr('id').")");
			$box->add_comment('file', $cFile->attr('id'), $comment);
		}
		else {
			logIt("Upload to Box failed with status: $status");
		}

		// clean-up the attachment
		unlink($tmpFileName);
	}
}

else {

	//
	// The email we this the webhook for has no attachments.
	// Get thread to see if it's a reply to a file we already uploaded
	//

	logIt("Message has no file attached, checking to see if it's a comment on an existing file");

	$historyEntries = $folderPair->searchHistoryForMessages(getThreadMessageIds($webHookData['account_id'], $webHookData['message_data']['message_id']));
	if (is_null($historyEntries) || count($historyEntries) == 0) {
		logIt("Dropping this messages, it has no file and isn't part of a thread of an existing file.");
	} 
	else {
		$boxFiles = array();
		$comment = null;
		$overallStatusOk = true;
		foreach ($historyEntries as $entry) {
			if ($entry['type'] == 'file') {
				if (is_null($comment)) {
					logIt("Getting message body");
					$comment = getMessageBody($webHookData['account_id'], $webHookData['message_data']['message_id']);
				}
				logIt("Adding message body as comment on existing Box file (id: ".$entry['fileInfo']['box']['file_id'].")");
				$status = $box->add_comment('file', $entry['fileInfo']['box']['file_id'], $comment);
				$overallStatusOk = ($overallStatus != 'add_comment_ok' || $status != 'add_comment_ok');
				$boxFiles[] = array('file_id' => $entry['fileInfo']['box']['file_id'], 'file_name' => $entry['fileInfo']['box']['file_name']);
			}
		}
		$fileInfo = array(
			'contextio' => array(
				'message_id' => $webHookData['message_data']['message_id'],
				'date' => $webHookData['message_data']['date'],
				'subject' => $webHookData['message_data']['subject'],
				'sender' => $webHookData['message_data']['addresses']['from']
			),
			'boxFiles' => $boxFiles
		);

		if (array_key_exists('gmail_message_id', $webHookData['message_data'])) {
			$fileInfo['contextio']['gmail_message_id'] = $webHookData['message_data']['gmail_message_id'];
			$fileInfo['contextio']['gmail_thread_id'] = $webHookData['message_data']['gmail_thread_id'];
		}

		$folderPair->logFileTransfer($fileInfo, $status, 'comment', $overallStatusOk);
	}
}

logIt("Done processing");
header("Content-type: application/json");
echo json_encode(array('success'=>true));

?>