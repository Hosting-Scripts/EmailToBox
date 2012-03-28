<?php
/**
 * Class to instantiate a database connection
 * 
 * @author Dominik Gehl dominik@context.io
 * @copyright DokDok inc.
 * @license GNU Affero General Public License v3 http://www.gnu.org/licenses/agpl.html
 */

class DBConnection {

	// Hold an instance of the class
	private static $instance;
	/**
	 * @var Mongo
	 */
	private $db;

	// A private constructor; prevents direct creation of object
	private function __construct() {
		$this->db = null;
	}
	

	/**
	 * Get a DBConnection instance
	 *
	 * Example:
	 * <code>
	 * $dbCx = DBConnection::singleton()->getConnection();
	 * </code>
	 *
	 * @return DBConnection
	 */
	public static function singleton() {
		if (!isset(self::$instance)) {
			$c = __CLASS__;
			self::$instance = new $c;
		}

		return self::$instance;
	}

	/**
	 * Get an authenticated database connection
	 *
	 * Example:
	 * <code>
	 * $dbCx = DBConnection::singleton()->getConnection();
	 * </code>
	 *
	 * @return Mongo
	 */
	public function getConnection() {
		if (is_null($this->db)) {
			$this->db = new Mongo("mongodb://localhost:27017/" . DB_NAME);
		}
		return $this->db;
	}

	/**
	 * Prevent users to clone the instance
	 */
	public function __clone() {
		trigger_error('Clone is not allowed.', E_USER_ERROR);
	}
}
?>