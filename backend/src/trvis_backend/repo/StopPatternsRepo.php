<?php

namespace dev_t0r\trvis_backend\repo;

use dev_t0r\trvis_backend\model\StopPattern;
use dev_t0r\trvis_backend\Utils;
use PDO;
use PDOStatement;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * stop_patterns。Project直下。
 * API `lines_id` ↔ DB `project_lines_id` (予約語回避) を別名で対応。
 * description列はAPIに無いのでINSERTから除外 (DB default '')。
 *
 * @extends MyProjectRootedRepoBase<StopPattern>
 */
final class StopPatternsRepo extends MyProjectRootedRepoBase
{
	public function __construct(
		PDO $db,
		\Psr\Log\LoggerInterface $logger,
	) {
		parent::__construct(
			db: $db,
			logger: $logger,
			TABLE_NAME: 'stop_patterns',
			parentTableNameList: ['projects'],
			SQL_SELECT_COLUMNS: self::SQL_SELECT_COLUMNS,
			SQL_INSERT_COLUMNS: self::SQL_INSERT_COLUMNS,
		);
	}

	private const SQL_SELECT_COLUMNS = <<<SQL
		stop_patterns_id,
		projects_id,
		project_lines_id AS lines_id,
		created_at,
		name,
		from_project_stations_id,
		to_project_stations_id,
		direction

	SQL;

	protected function _fetchResultToObj(
		mixed $d,
	): mixed {
		$result = new StopPattern();
		$result->setData([
			'stop_patterns_id' => Uuid::fromBytes($d['stop_patterns_id']),
			'projects_id' => Uuid::fromBytes($d['projects_id']),
			'lines_id' => Uuid::fromBytes($d['lines_id']),
			'created_at' => Utils::dbDateStrToDateTime($d['created_at']),
			'name' => $d['name'],
			'from_project_stations_id' => is_null($d['from_project_stations_id']) ? null : Uuid::fromBytes($d['from_project_stations_id']),
			'to_project_stations_id' => is_null($d['to_project_stations_id']) ? null : Uuid::fromBytes($d['to_project_stations_id']),
			'direction' => intval($d['direction']),
		]);
		return $result;
	}

	private const SQL_INSERT_COLUMNS = <<<SQL
	(
		stop_patterns_id,
		projects_id,
		project_lines_id,
		owner,
		name,
		from_project_stations_id,
		to_project_stations_id,
		direction
	)
	SQL;
	protected function _genInsertValuesQuerySegment(
		int $i
	): string {
		return <<<SQL
			(
				:stop_patterns_id_{$i},
				{$this->PLACEHOLDER_PARENT_ID},
				:lines_id_{$i},
				{$this->PLACEHOLDER_OWNER},
				:name_{$i},
				:from_project_stations_id_{$i},
				:to_project_stations_id_{$i},
				:direction_{$i}
			)
		SQL;
	}
	protected function _setInsertValues(
		PDOStatement $query,
		int $i,
		UuidInterface $id,
		mixed $d,
	) {
		$query->bindValue(":stop_patterns_id_$i", $id->getBytes(), PDO::PARAM_STR);
		$query->bindValue(":lines_id_$i", $d->lines_id->getBytes(), PDO::PARAM_STR);
		$query->bindValue(":name_$i", $d->name, PDO::PARAM_STR);
		$query->bindValue(":from_project_stations_id_$i", $d->from_project_stations_id?->getBytes(), PDO::PARAM_STR);
		$query->bindValue(":to_project_stations_id_$i", $d->to_project_stations_id?->getBytes(), PDO::PARAM_STR);
		$query->bindValue(":direction_$i", $d->direction ?? 1, PDO::PARAM_INT);
	}

	protected function _keyToUpdateQuerySetLine(
		string $key,
	): string {
		if ($key === 'lines_id') {
			// API `lines_id` -> DB `project_lines_id`
			return "project_lines_id = :{$key}";
		}
		return parent::_keyToUpdateQuerySetLine($key);
	}
}
