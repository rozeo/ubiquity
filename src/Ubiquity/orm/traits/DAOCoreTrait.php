<?php

namespace Ubiquity\orm\traits;

use Ubiquity\db\SqlUtils;
use Ubiquity\events\DAOEvents;
use Ubiquity\events\EventsManager;
use Ubiquity\orm\OrmUtils;
use Ubiquity\orm\parser\ConditionParser;
use Ubiquity\orm\parser\Reflexion;
use Ubiquity\db\Database;

/**
 * Core Trait for DAO class.
 * Ubiquity\orm\traits$DAOCoreTrait
 * This class is part of Ubiquity
 *
 * @author jcheron <myaddressmail@gmail.com>
 * @version 1.1.1
 *
 * @property array $db
 * @property boolean $useTransformers
 * @property string $transformerOp
 *
 */
trait DAOCoreTrait {
	protected static $accessors = [ ];
	protected static $fields = [ ];

	abstract protected static function _affectsRelationObjects($className, $classPropKey, $manyToOneQueries, $oneToManyQueries, $manyToManyParsers, $objects, $included, $useCache): void;

	abstract protected static function prepareManyToMany(&$ret, $instance, $member, $annot = null);

	abstract protected static function prepareManyToOne(&$ret, $instance, $value, $fkField, $annotationArray);

	abstract protected static function prepareOneToMany(&$ret, $instance, $member, $annot = null);

	abstract protected static function _initRelationFields($included, $metaDatas, &$invertedJoinColumns, &$oneToManyFields, &$manyToManyFields): void;

	abstract protected static function getIncludedForStep($included);

	abstract protected static function getDb($model);

	protected static function getClass_($instance) {
		if (is_object ( $instance )) {
			return get_class ( $instance );
		}
		return $instance [0];
	}

	protected static function getInstance_($instance) {
		if (\is_object ( $instance )) {
			return $instance;
		}
		return $instance [0];
	}

	protected static function getValue_($instance, $member) {
		if (\is_object ( $instance )) {
			return Reflexion::getMemberValue ( $instance, $member );
		}
		return $instance [1];
	}

	protected static function getFirstKeyValue_($instance) {
		if (\is_object ( $instance )) {
			return OrmUtils::getFirstKeyValue ( $instance );
		}
		return $instance [1];
	}

	protected static function _getOne(Database $db, $className, ConditionParser $conditionParser, $included, $useCache) {
		$conditionParser->limitOne ();
		$included = self::getIncludedForStep ( $included );
		$object = null;
		$invertedJoinColumns = null;
		$oneToManyFields = null;
		$manyToManyFields = null;

		$metaDatas = OrmUtils::getModelMetadata ( $className );
		$tableName = $metaDatas ['#tableName'];
		$hasIncluded = $included || (\is_array ( $included ) && \sizeof ( $included ) > 0);
		if ($hasIncluded) {
			self::_initRelationFields ( $included, $metaDatas, $invertedJoinColumns, $oneToManyFields, $manyToManyFields );
		}
		$transformers = $metaDatas ['#transformers'] [self::$transformerOp] ?? [ ];
		$query = $db->prepareAndExecute ( $tableName, SqlUtils::checkWhere ( $conditionParser->getCondition () ), self::getFieldList ( $tableName, $metaDatas ), $conditionParser->getParams (), $useCache );
		if ($query && \sizeof ( $query ) > 0) {
			$oneToManyQueries = [ ];
			$manyToOneQueries = [ ];
			$manyToManyParsers = [ ];
			$accessors = $metaDatas ['#accessors'];
			$object = self::loadObjectFromRow ( \current ( $query ), $className, $invertedJoinColumns, $manyToOneQueries, $oneToManyFields, $manyToManyFields, $oneToManyQueries, $manyToManyParsers, $accessors, $transformers );
			if ($hasIncluded) {
				self::_affectsRelationObjects ( $className, OrmUtils::getFirstPropKey ( $className ), $manyToOneQueries, $oneToManyQueries, $manyToManyParsers, [ $object ], $included, $useCache );
			}
			EventsManager::trigger ( DAOEvents::GET_ONE, $object, $className );
		}
		return $object;
	}

	/**
	 *
	 * @param Database $db
	 * @param string $className
	 * @param ConditionParser $conditionParser
	 * @param boolean|array $included
	 * @param boolean|null $useCache
	 * @return array
	 */
	protected static function _getAll(Database $db, $className, ConditionParser $conditionParser, $included = true, $useCache = NULL) {
		$included = self::getIncludedForStep ( $included );
		$objects = array ();
		$invertedJoinColumns = null;
		$oneToManyFields = null;
		$manyToManyFields = null;

		$metaDatas = OrmUtils::getModelMetadata ( $className );
		$tableName = $metaDatas ['#tableName'];
		$hasIncluded = $included || (\is_array ( $included ) && \sizeof ( $included ) > 0);
		if ($hasIncluded) {
			self::_initRelationFields ( $included, $metaDatas, $invertedJoinColumns, $oneToManyFields, $manyToManyFields );
		}
		$transformers = $metaDatas ['#transformers'] [self::$transformerOp] ?? [ ];
		$query = $db->prepareAndExecute ( $tableName, SqlUtils::checkWhere ( $conditionParser->getCondition () ), self::getFieldList ( $tableName, $metaDatas ), $conditionParser->getParams (), $useCache );
		$oneToManyQueries = [ ];
		$manyToOneQueries = [ ];
		$manyToManyParsers = [ ];
		$propsKeys = OrmUtils::getPropKeys ( $className );
		$accessors = $metaDatas ['#accessors'];
		foreach ( $query as $row ) {
			$object = self::loadObjectFromRow ( $row, $className, $invertedJoinColumns, $manyToOneQueries, $oneToManyFields, $manyToManyFields, $oneToManyQueries, $manyToManyParsers, $accessors, $transformers );
			$key = OrmUtils::getPropKeyValues ( $object, $propsKeys );
			$objects [$key] = $object;
		}
		if ($hasIncluded) {
			self::_affectsRelationObjects ( $className, OrmUtils::getFirstPropKey ( $className ), $manyToOneQueries, $oneToManyQueries, $manyToManyParsers, $objects, $included, $useCache );
		}
		EventsManager::trigger ( DAOEvents::GET_ALL, $objects, $className );
		return $objects;
	}

	protected static function getFieldList($tableName, $metaDatas) {
		return self::$fields [$tableName] ?? (self::$fields [$tableName] = SqlUtils::getFieldList ( \array_diff ( $metaDatas ['#fieldNames'], $metaDatas ['#notSerializable'] ), $tableName ));
	}

	/**
	 *
	 * @param array $row
	 * @param string $className
	 * @param array $invertedJoinColumns
	 * @param array $manyToOneQueries
	 * @param array $accessors
	 * @return object
	 */
	private static function loadObjectFromRow($row, $className, &$invertedJoinColumns, &$manyToOneQueries, &$oneToManyFields, &$manyToManyFields, &$oneToManyQueries, &$manyToManyParsers, &$accessors, &$transformers) {
		$o = new $className ();
		if (self::$useTransformers) {
			foreach ( $transformers as $field => $transformer ) {
				$transform = self::$transformerOp;
				$row [$field] = $transformer::$transform ( $row [$field] );
			}
		}
		foreach ( $row as $k => $v ) {
			if (isset ( $accessors [$k] )) {
				$accesseur = $accessors [$k];
				$o->$accesseur ( $v );
			}
			$o->_rest [$k] = $v;
			if (isset ( $invertedJoinColumns ) && isset ( $invertedJoinColumns [$k] )) {
				$fk = '_' . $k;
				$o->$fk = $v;
				self::prepareManyToOne ( $manyToOneQueries, $o, $v, $fk, $invertedJoinColumns [$k] );
			}
		}
		if (isset ( $oneToManyFields )) {
			foreach ( $oneToManyFields as $k => $annot ) {
				self::prepareOneToMany ( $oneToManyQueries, $o, $k, $annot );
			}
		}
		if (isset ( $manyToManyFields )) {
			foreach ( $manyToManyFields as $k => $annot ) {
				self::prepareManyToMany ( $manyToManyParsers, $o, $k, $annot );
			}
		}
		return $o;
	}

	private static function parseKey(&$keyValues, $className, $quote) {
		if (! \is_array ( $keyValues )) {
			if (\strrpos ( $keyValues, '=' ) === false && \strrpos ( $keyValues, '>' ) === false && \strrpos ( $keyValues, '<' ) === false) {
				$keyValues = $quote . OrmUtils::getFirstKey ( $className ) . $quote . "='" . $keyValues . "'";
			}
		}
	}
}
