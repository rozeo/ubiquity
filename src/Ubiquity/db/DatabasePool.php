<?php

namespace Ubiquity\db\providers\swoole;

use Ubiquity\db\Database;
use Ubiquity\log\Logger;
use Ubiquity\exceptions\DAOException;

class DatabasePool {
	private $pool;
	private $dbConfig;
	private $offset;
	private $dbs = [ ];

	public function __construct(&$config, $offset = null) {
		$this->pool = new \SplQueue ();
		$this->dbConfig = $offset ? ($config ['database'] [$offset] ?? ($config ['database'] ?? [ ])) : ($config ['database'] ['default'] ?? $config ['database']);
		$this->offset = $offset;
	}

	public function put($db) {
		$this->pool->enqueue ( $db );
	}

	public function get() {
		$offset = $this->offset;
		if (\class_exists ( '\\Swoole\\Coroutine' )) {
			$offset .= \Swoole\Coroutine::getuid () ?? '';
		}
		if (isset ( $this->dbs [$offset] )) {
			return $this->dbs [$offset];
		}
		if (! $this->pool->isEmpty ()) {
			return $this->pool->dequeue ();
		}
		$this->dbs [$offset] = $db = new Database ( $this->dbConfig ['wrapper'] ?? \Ubiquity\db\providers\pdo\PDOWrapper::class, $this->dbConfig ['type'], $this->dbConfig ['dbName'], $this->dbConfig ['serverName'] ?? '127.0.0.1', $this->dbConfig ['port'] ?? 3306, $this->dbConfig ['user'] ?? 'root', $this->dbConfig ['password'] ?? '', $this->dbConfig ['options'] ?? [ ], $this->dbConfig ['cache'] ?? false);
		try {
			$db->connect ();
			return $db;
		} catch ( \Exception $e ) {
			Logger::error ( "DAO", $e->getMessage () );
			throw new DAOException ( $e->getMessage (), $e->getCode (), $e->getPrevious () );
		}
	}
}

