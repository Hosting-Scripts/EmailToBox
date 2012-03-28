<?php
/**
 * Representation of EmailToBox users
 * 
 * @author Bruno Morency bruno@context.io
 * @copyright DokDok inc.
 * @license GNU Affero General Public License v3 http://www.gnu.org/licenses/agpl.html
 */

require_once(UTILS);

/**
 * Class to manage user accounts and sessions when they are connected to the server
 */
class User {

	/**
	 * @var Mongo
	 */
	protected $dbCx;

	/**
	 * @var MongoCollection
	 */
	protected $dbUsersColl;
	
	private $params;
	private $id;

	/**
	 * Instantiate a new User object
	 * 
	 * @param array $id Hash with exactly one item whose key can be 'id', 'boxUserId' or 'contextIoId' with the related value
	 */
	function __construct($id=null) {
		$this->dbCx = DBConnection::singleton()->getConnection();
		$this->dbUsersColl = $this->dbCx->selectCollection(DB_NAME, 'users');
		$this->params = array();
		
		if (is_array($id)) {
			$q = null;
			if (array_key_exists('id', $id)) {
				$q = array('_id' => new MongoId($id['id']));
			} 
			elseif (array_key_exists('boxUserId', $id)) {
				$q = array('box.user_id' => $id['boxUserId']);
			}
			elseif (array_key_exists('contextIoId', $id)) {
				$q = array('contextio.id' => $id['contextIoId']);
			}
			if (!is_null($q)) {
				$user = $this->dbUsersColl->findOne($q);
				if (is_null($user)) {
					$this->id = null;
					$this->params = array();
				} else {
					$this->id = $user['_id']->__toString();
					$this->params = $user;
					if (array_key_exists('usage', $this->params)) {
						usort($this->params['usage']['history'], function ($a, $b) {
							if ($a['until'] == $b['until']) return 0;
							else return ($a['until'] < $b['until']) ? -1 : 1;
						});
					}
				}
			}
		}
	}
	
	function __destruct() {
		$this->dbCx = null;
	}


	public function getId() {
		return $this->id;
	}


	public function getBoxAuthToken() {
		if (array_key_exists('box', $this->params) && array_key_exists('auth_token', $this->params['box'])) {
			return $this->params['box']['auth_token'];
		} 
		else {
			return null;
		}
	}


	public function getContextIoSource($accountId = null, $labelOrIndex = null) {
		if (!array_key_exists('contextio', $this->params)) return null;

		$accntInfo = $this->getContextIoInfo($accountId);

		if (is_null($accntInfo)) {
			return null;
		}

		if (is_null($labelOrIndex)) {
			return $accntInfo['src'][0];
		}
		else if (is_string($labelOrIndex)) {
			foreach ($accntInfo['src'] as $src) {
				if ($src['label'] == $labelOrIndex) {
					return $src;
				}
			}
		}
		else {
			return $accntInfo['src'][intval($labelOrIndex)];
		}

		return null;
	}


	public function getContextIoId($ctxIoEmail = null) {
		if (!array_key_exists('contextio', $this->params)) return null;
		if (is_null($ctxIoEmail)) {
			return $this->params['contextio'][0]['id'];
		}
		else {
			foreach ($this->params['contextio'] as $accnt) {
				if (in_array($ctxIoEmail, $accnt['addr'])) return $accnt['id'];
			}
			return null;
		}
	}


	public function getContextIoInfo($accountId = null) {
		if (!array_key_exists('contextio', $this->params)) return null;
		if (is_null($accountId)) {
			return $this->params['contextio'][0];
		}
		else {
			foreach ($this->params['contextio'] as $accnt) {
				if ($accountId == $accnt['id']) return $accnt;
			}
		}
		return null;
	}

	public static function boxUserIdExists($boxUserId) {
		return !is_null(DBConnection::singleton()->getConnection()->selectCollection(DB_NAME, 'users')->findOne(array('box.user_id' => $boxUserId)));
	}

	public static function userIdExists($userId) {
		return !is_null(DBConnection::singleton()->getConnection()->selectCollection(DB_NAME, 'users')->findOne(array('_id' => new MongoId($userId))));
	}


	public function updateBoxInfo($boxInfo) {
		$currentInfo = (array_key_exists('box', $this->params)) ? $this->params['box'] : array();
		foreach ($boxInfo as $field => $value) {
			$currentInfo[$field] = $value;
		}
		return $this->updateField('box', $currentInfo);
	}


	public function updateContextIoInfo($contextIoInfo) {
		$currentInfo = (array_key_exists('contextio', $this->params)) ? $this->params['contextio'] : array();
		$isNew = true;
		for ($i = 0; $i < count($currentInfo) ; ++$i) {
			if ($contextIoInfo['account_id'] == $currentInfo[$i]['account_id']) {
				foreach ($contextIoInfo as $field => $value) {
					$currentInfo[$i][$field] = $value;
				}
				$isNew = false;
				break;
			}
		}
		if ($isNew) {
			$currentInfo[] = $contextIoInfo;
		}
		return $this->updateField('contextio', $currentInfo);
	}


	private function updateField($field, $info) {
		$this->params[$field] = $info;
		return $this->dbUsersColl->update(
			array('_id' => new MongoId($this->id)), 
			array('$set' => array($field => $info))
		);
	}


	public static function addUser($boxInfo = null, $contextIoInfo = null) {
		
		$usrObj = array(
			'created' => time(),
			'lastLogin' => 0,
			'usage' => array(
				'quota' => USAGE_QUOTA_FREE,
				'history' => array(
					array(
						'until' => mktime(0, 0, 0, date('n') + 1), 
						'used' => 0
					)
				)
			)
		);

		if (!is_null($boxInfo)) {
			$usrObj['box'] = array(
				'user_id'=> $boxInfo['user_id'],
				'auth_token'=> $boxInfo['auth_token'],
				'max_upload_size'=> $boxInfo['max_upload_size'],
				'email'=> $boxInfo['email']
			);
		}

		if (!is_null($contextIoInfo)) {
			$usrObj['contextio'] = array(array(
				'id'=> $contextIoInfo['id'],
				'addr'=> $contextIoInfo['addr'],
				'src' => $contextIoInfo['src']
			));
		}
		DBConnection::singleton()->getConnection()->selectCollection(DB_NAME, 'users')->insert($usrObj, array('safe'=>true));
		
		return $usrObj['_id']->__toString();
	}

	public function getCurrentUsage() {
		if (array_key_exists('usage', $this->params)) {
			$latest = $this->params['usage']['history'][(count($this->params['usage']['history']) - 1)];
			while ($latest['until'] < time()) {
				// add quota history entry
				$newEntry = array(
					'until' => mktime(0, 0, 0, date('n', $latest['until']) + 1, date('j', $latest['until']), date('Y', $latest['until'])), 
					'used' => 0
				);

				$this->dbUsersColl->update(
					array('_id' => new MongoId($this->id)),
					array('$push' => array('usage.history' => $newEntry)),
					array('upsert' => false, 'multiple' => false)
				);

				$this->params['usage']['history'][] = $newEntry;
				$latest = $newEntry;
			}
			return $latest;
		}
		else {
			return null;
		}
	}

	public function getUsageQuota() {
		if (array_key_exists('usage', $this->params)) {
			return intval($this->params['usage']['quota']);
		}
		else {
			return -1;
		}
	}

	public function isWithinQuota($numBytes) {
		if (array_key_exists('usage', $this->params)) {
			$current = $this->getCurrentUsage();
			return (($current['used'] + $numBytes) <= $this->params['usage']['quota']); 
		}
		else {
			// assume infinite quota if nothing is specified for this user
			return true;
		}
	}

	public function incrementCurrentUsage($numBytes) {
		if (array_key_exists('usage', $this->params)) {
			$current = $this->getCurrentUsage();
			$newUsedValue = $current['used'] + $numBytes;

			$this->dbUsersColl->update(
				array(
					'_id' => new MongoId($this->id),
					'usage.history.until' => $current['until']
				),
				array('$set' => array('usage.history.$.used' => $newUsedValue)),
				array('upsert' => false, 'multiple' => false)
			);

			$this->params['usage']['history'][(count($this->params['usage']['history']) - 1)]['used'] = $newUsedValue;

			return true;
		}
		else {
			// assume infinite quota if nothing is specified for this user
			return true;
		}
	}

	public function getFolderPair($pairId = null) {

		if (is_array($this->params['folderPairs'])) {
			if (is_null($pairId)) {
				$pairId = array_shift(array_keys($this->params['folderPairs']));
				return new FolderPair($this->params['folderPairs'][$pairId], $this);
			}
			else if (array_key_exists($pairId, $this->params['folderPairs'])) {
				return new FolderPair($this->params['folderPairs'][$pairId], $this);
			}
		}

		return null;
	}

	public function createFolderPair($boxFolderName, $emailFolderName) {
		
		$boxAuthToken = $this->getBoxAuthToken();
		if (is_null($boxAuthToken)) {
			throw new Exception("No Box auth_token available for user.");
		}


		$ctxIoId = $this->getContextIoId();

		$source = $this->getContextIoSource($ctxIoId);
		if (is_null($source)) {
			throw new Exception("No Context.IO source information for user.");
		}

		// create folder on Box account
		$boxFolder = new Box_Client_Folder();
		$boxFolder->attr('name', $boxFolderName);
		$boxFolder->attr('parent_id', 0);
		$boxFolder->attr('share', 0);
		$box = new Box_Rest_Client(BOX_API_KEY);
		$box->set_auth_token($boxAuthToken);
		
		$createFolderStatus = $box->create($boxFolder);
		if ($createFolderStatus == 's_folder_exists') {
			$rootFolder = $box->folder(0, array('params'=>array('onelevel','nofiles','nozip')));
			foreach ($rootFolder->folder as $bf) {
				if ($bf->attr('name') == $boxFolderName) {
					$boxFolder = $bf;
					break;
				}
			}
		}
		else if ($createFolderStatus != 'create_ok') {
			// there's been an error cerating the folder
			throw new Exception("Error creating Box folder: ". $createFolderStatus);
		}

		// create folder in email account
		$ctxIO = new ContextIO(CONTEXTIO_CONSUMER_KEY, CONTEXTIO_CONSUMER_SECRET);
		$r = $ctxIO->addFolderToSource($ctxIoId, array(
			'label' => $source['label'],
			'folder' => $emailFolderName
		));
		if ($r === false) {
			// error creating folder in email, clean up the box folder we created
			$box->delete('folder', $boxFolder->attr('folder_id'));
			throw new Exception("Error creating email folder: [HTTP status: ". $ctxIO->getLastResponse()->getHttpCode() ."] ". $ctxIO->getLastResponse()->getRawResponse());
		}

		// folder pairs are identifed by a combination of the user's _id and a random 4 char string.
		// We use string based indexes to make sure deleting one pair won't screw up ids to follow it.
		if (!array_key_exists('folderPairs', $this->params) || !is_array($this->params['folderPairs'])) {
			$this->params['folderPairs'] = array();
		}
		do {
			$fpId = generateKey(8,false);
		} while (array_key_exists($fpId, $this->params['folderPairs']));
		$this->params['folderPairs'][$fpId] = array(
			'id' => $fpId,
			'created' => time(),
			'deleted' => 0,
			'paused' => 0,
			'versionHistory' => 1,
			'boxFolder' => array(
				'id' => (is_null($boxFolder->attr('folder_id'))) ? $boxFolder->attr('id') : $boxFolder->attr('folder_id'),
				'name' => (is_null($boxFolder->attr('folder_name'))) ? $boxFolder->attr('name') : $boxFolder->attr('folder_name'),
				'public_name' => $boxFolder->attr('public_name'),
				'path' => $boxFolder->attr('path'),
				'parent' => (is_null($boxFolder->attr('parent_folder_id'))) ? 0 : $boxFolder->attr('parent_folder_id')
			),
			'contextio' => array(
				'account_id' => $ctxIoId,
				'folder_name' => $emailFolderName
			)
		);

		// register webhook to get POSTed message info when an email is added in that new folder
		$r = $ctxIO->addWebhook($ctxIoId, array(
			'callback_url' => APP_URL.'/webhooks/fp-'.$fpId,
			'failure_notif_url' => APP_URL.'/webhooks/fp-'.$fpId.'/failure',
			'filter_folder_added' => $emailFolderName,
			'sync_period' => 0
		));
		if ($r === false) {
			// error creating the webhook
			$this->params['folderPairs'][$fpId]['contextio']['webhook_id'] = null;
		} 
		else {
			$this->params['folderPairs'][$fpId]['contextio']['webhook_id'] = $r->getDataProperty('webhook_id');
			$this->params['folderPairs'][$fpId]['contextio']['webhook_active'] = true;
		}

		// save settings for this folder pair and return it's id
		$this->updateField('folderPairs', $this->params['folderPairs']);

		return $fpId;

	}

}

