<?php
/**
 * Representation of a FolderPair belonging to an EmailToBox user
 * 
 * @author Bruno Morency bruno@context.io
 * @copyright DokDok inc.
 * @license GNU Affero General Public License v3 http://www.gnu.org/licenses/agpl.html
 */

require_once(UTILS);

/**
 * Class to manage user accounts and sessions when they are connected to the server
 */
class FolderPair {

	/**
	 * @var Mongo
	 */
	protected $dbCx;

	/**
	 * @var MongoCollection
	 */
	protected $dbHistoryColl;
	
	private $params;
	private $id;
	private $owner;

	/**
	 * Instantiate a new User object
	 * 
	 * @param array $id Hash with exactly one item whose key can be 'id', 'boxUserId' or 'contextIoId' with the related value
	 */
	function __construct($info, User $owner) {
		$this->dbCx = DBConnection::singleton()->getConnection();
		$this->dbHistoryColl = $this->dbCx->selectCollection(DB_NAME, 'history');
		$this->params = array();
		
		if (is_array($info)) {
			$this->id = $info['id'];
			$this->params = $info;
			$this->owner = $owner;
		}
		else {
			throw new Exception("Invalid $info given to instantiate folder pair.");
		}
	}
	
	function __destruct() {
		$this->dbCx = null;
	}

	public function getId() {
		return $this->id;
	}

	public function getBoxFolderId() {
		return $this->params['boxFolder']['id'];
	}

	public function getInfo($raw = true) {
		$info = $this->params;
		if (!$raw) {
			unset($info['contextio']['account_id']);
			unset($info['contextio']['webhook_id']);
			unset($info['contextio']['webhook_active']);
			unset($info['boxFolder']['id']);
			unset($info['boxFolder']['parent']);
			$ctxIoInfo = $this->owner->getContextIoInfo();
			$info['contextio']['email'] = $ctxIoInfo['addr'][0];
		}
		return $info;
	}

	public function versionHistory($setToState = null) {
		if (!is_null($setToState)) {
			$this->params['versionHistory'] = ($setToState) ? 1 : 0;
		} 
		else {
			return ($this->params['versionHistory'] == 1) ? true : false;
		}
	}

	public function pause() {
		return $this->setPausedProperty(time());
	}

	public function resume() {
		return $this->setPausedProperty(0);
	}

	private function setPausedProperty($state) {
		$webhookActive = ($state > 0) ? false : true;

		$ctxIO = new ContextIO(CONTEXTIO_CONSUMER_KEY, CONTEXTIO_CONSUMER_SECRET);
		$r = $ctxIO->modifyWebhook($this->params['contextio']['account_id'], array(
			'webhook_id' => $this->params['contextio']['webhook_id'],
			'active' => intval($webhookActive)
		));
		if ($r === false) {
			appLogEntry("Error changing active state of webhook {$this->params['contextio']['webhook_id']} for folderPair {$this->id}: [". $ctxIO->getLastResponse()->getHttpCode() ."] ". $ctxIO->getLastResponse()->getRawResponse());
			return false;
		} 
		else {
			$coll = $this->dbCx->selectCollection(DB_NAME, 'users');
			$q = array('_id' => new MongoId($this->owner->getId()));
			$u = array('$set' => array(
				"folderPairs.".$this->id.".paused" => $state,
				"folderPairs.".$this->id.".contextio.webhook_active" => $webhookActive
			));
			$coll->update($q, $u, array('upsert'=>false, 'multiple'=>false));
			
			$this->params['paused'] = $state;
			$this->params['contextio']['webhook_active'] = $webhookActive;
			return true;
		}
	}

	public function logFileTransfer($fileInfo, $boxStatus, $type = 'file', $success = false) {
		$this->dbHistoryColl->insert(array(
			'userId' => $this->owner->getId(),
			'fpId' => $this->id,
			'time' => time(),
			'fileInfo' => $fileInfo,
			'boxStatus' => $boxStatus,
			'success' => $success,
			'type' => $type
		));
	}

	public function searchHistoryForMessages($messageIdList) {
		$q = array('fpId' => $this->id);
		if (is_array($messageIdList)) {
			$q['fileInfo.contextio.message_id'] = array('$in' => $messageIdList);
		}
		else {
			$q['fileInfo.contextio.message_id'] = $messageIdList;
		}

		$results = $this->dbHistoryColl->find($q);
		if (is_null($results)) {
			return null;
		} else {
			$entries = array();
			foreach ($results as $doc) {
				$entries[] = $doc;
			}
			return $entries;
		}		
	}

	public function getHistory($offset = 0, $limit = FOLDERPAIR_HISTORY_LIST_SIZE) {
		$cursor = $this->dbHistoryColl->find(array('fpId' => $this->id))->sort(array('time'=>-1))->limit($limit);
		if ($offset > 0) $cursor->skip($offset);

		if (is_null($cursor)) {
			return null;
		} else {
			$entries = array();
			foreach ($cursor as $doc) {
				$entries[] = array(
					'id' => $doc['_id']->__toString(),
					'fileInfo' => $doc['fileInfo'],
					'success' => $doc['success'],
					'time' => $doc['time'],
					'type' => $doc['type']
				);
			}
			return $entries;
		}		

	}

}

?>