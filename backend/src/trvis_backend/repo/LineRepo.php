<?php

namespace dev_t0r\trvis_backend\repo;

use dev_t0r\trvis_backend\model\Line;
use dev_t0r\trvis_backend\Utils;
use PDO;
use PDOStatement;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * DBテーブルは予約語回避のため `project_lines` / `project_lines_id`。
 * OpenAPIは `Line` / `lines_id` のままなので SELECT で別名を付ける。
 *
 * @extends MyProjectRootedRepoBase<Line>
 */
final class LineRepo extends MyProjectRootedRepoBase
{
	public function __construct(
		PDO $db,
		\Psr\Log\LoggerInterface $logger,
	) {
		parent::__construct(
			db: $db,
			logger: $logger,
			TABLE_NAME: 'project_lines',
			parentTableNameList: ['projects'],
			SQL_SELECT_COLUMNS: self::SQL_SELECT_COLUMNS,
			SQL_INSERT_COLUMNS: self::SQL_INSERT_COLUMNS,
		);
	}

	private const SQL_SELECT_COLUMNS = <<<SQL
		project_lines_id AS lines_id,
		projects_id,
		description,
		created_at,
		name

	SQL;

	protected function _fetchResultToObj(
		mixed $d,
	): mixed {
		$result = new Line();
		$result->setData([
			'lines_id' => Uuid::fromBytes($d['lines_id']),
			'projects_id' => Uuid::fromBytes($d['projects_id']),
			'description' => $d['description'],
			'created_at' => Utils::dbDateStrToDateTime($d['created_at']),
			'name' => $d['name'],
		]);
		return $result;
	}

	private const SQL_INSERT_COLUMNS = <<<SQL
	(
		project_lines_id,
		projects_id,
		description,
		owner,
		name
	)
	SQL;
	protected function _genInsertValuesQuerySegment(
		int $i
	): string {
		return <<<SQL
			(
				:lines_id_{$i},
				{$this->PLACEHOLDER_PARENT_ID},
				:description_{$i},
				{$this->PLACEHOLDER_OWNER},
				:name_{$i}
			)
		SQL;
	}
	protected function _setInsertValues(
		PDOStatement $query,
		int $i,
		UuidInterface $id,
		mixed $d,
	) {
		$query->bindValue(":lines_id_$i", $id->getBytes(), PDO::PARAM_STR);
		$query->bindValue(":description_$i", $d->description, PDO::PARAM_STR);
		$query->bindValue(":name_$i", $d->name, PDO::PARAM_STR);
	}
}
