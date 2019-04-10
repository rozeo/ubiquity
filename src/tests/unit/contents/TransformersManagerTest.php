<?php
use models\User;
use Ubiquity\contents\transformation\TransformersManager;
use Ubiquity\orm\DAO;

/**
 * TransformersManager test case.
 */
class TransformersManagerTest extends BaseTest {

	/**
	 * Prepares the environment before running a test.
	 */
	protected function _before() {
		parent::_before ();
		$this->_loadConfig ();
		$this->_startCache ();
		$db = $this->config ["database"] ?? [ ];
		if ($db ["dbName"] !== "") {
			DAO::connect ( $db ["type"] ?? 'mysql', $db ["dbName"], $db ["serverName"] ?? '127.0.0.1', $db ["port"] ?? 3306, $db ["user"] ?? 'root', $db ["password"] ?? '', $db ["options"] ?? [ ], $db ["cache"] ?? false);
		}
		TransformersManager::startProd ();
	}

	/**
	 * Cleans up the environment after running a test.
	 */
	protected function _after() {
		$this->database = null;
	}

	protected function _display($callback) {
		ob_start ();
		$callback ();
		return ob_get_clean ();
	}

	/**
	 * Tests Perso transformer
	 */
	public function testPerso() {
		$user = DAO::getOne ( User::class, 1 );
		$password = $user->getPassword ();
		DAO::$transformerOp = 'toView';
		$user = DAO::getOne ( User::class, 1 );
		$this->assertEquals ( sha1 ( $password ), $user->getPassword () );
	}
}

