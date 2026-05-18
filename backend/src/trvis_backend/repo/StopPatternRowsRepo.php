<?php

namespace dev_t0r\trvis_backend\repo;

use dev_t0r\trvis_backend\model\StopPatternRow;
use dev_t0r\trvis_backend\Utils;
use PDO;
use PDOStatement;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * stop_pattern_rows。StopPattern直下 (parentId = stopPatternId = stop_patterns_id)。
 * projects_id は親StopPatternから導出 (INSERT時にサブクエリで埋める)。
 * description列はAPIに無いのでINSERTから除外 (DB default '')。
 *
 * @extends MyProjectRootedRepoBase<StopPatternRow>
 */
final class StopPatternRowsRepo extends MyProjectRootedRepoBase
{
	public function __construct(
		PDO $db,
		\Psr\Log\LoggerInterface $logger,
	) {
		parent::__construct(
			db: $db,
			logger: $logger,
			TABLE_NAME: 'stop_pattern_rows',
			parentTableNameList: ['stop_patterns'],
			SQL_SELECT_COLUMNS: self::SQL_SELECT_COLUMNS,
			SQL_INSERT_COLUMNS: self::SQL_INSERT_COLUMNS,
		);
	}

	private const SQL_SELECT_COLUMNS = <<<SQL
		stop_pattern_rows_id,
		stop_patterns_id,
		projects_id,
		project_stations_id,
		created_at,
		sort_key,
		track_name,
		track_hidden,
		is_operation_only_stop,
		is_pass,
		drive_time_mm,
		drive_time_ss,
		dwell_time_mm,
		dwell_time_ss,
		show_arrive,
		show_departure,
		arrive_str,
		departure_str,
		run_in_limit,
		run_out_limit,
		remarks,
		always_show_hh

	SQL;

	protected function _fetchResultToObj(
		mixed $d,
	): mixed {
		$result = new StopPatternRow();
		$result->setData([
			'stop_pattern_rows_id' => Uuid::fromBytes($d['stop_pattern_rows_id']),
			'stop_patterns_id' => Uuid::fromBytes($d['stop_patterns_id']),
			'projects_id' => Uuid::fromBytes($d['projects_id']),
			'project_stations_id' => Uuid::fromBytes($d['project_stations_id']),
			'created_at' => Utils::dbDateStrToDateTime($d['created_at']),
			'sort_key' => intval($d['sort_key']),
			'track_name' => $d['track_name'],
			'track_hidden' => boolval($d['track_hidden']),
			'is_operation_only_stop' => boolval($d['is_operation_only_stop']),
			'is_pass' => boolval($d['is_pass']),
			'drive_time_mm' => is_null($d['drive_time_mm']) ? null : intval($d['drive_time_mm']),
			'drive_time_ss' => is_null($d['drive_time_ss']) ? null : intval($d['drive_time_ss']),
			'dwell_time_mm' => is_null($d['dwell_time_mm']) ? null : intval($d['dwell_time_mm']),
			'dwell_time_ss' => is_null($d['dwell_time_ss']) ? null : intval($d['dwell_time_ss']),
			'show_arrive' => boolval($d['show_arrive']),
			'show_departure' => boolval($d['show_departure']),
			'arrive_str' => $d['arrive_str'],
			'departure_str' => $d['departure_str'],
			'run_in_limit' => is_null($d['run_in_limit']) ? null : intval($d['run_in_limit']),
			'run_out_limit' => is_null($d['run_out_limit']) ? null : intval($d['run_out_limit']),
			'remarks' => $d['remarks'],
			'always_show_hh' => boolval($d['always_show_hh']),
		]);
		return $result;
	}

	private const SQL_INSERT_COLUMNS = <<<SQL
	(
		stop_pattern_rows_id,
		stop_patterns_id,
		projects_id,
		project_stations_id,
		owner,
		sort_key,
		track_name,
		track_hidden,
		is_operation_only_stop,
		is_pass,
		drive_time_mm,
		drive_time_ss,
		dwell_time_mm,
		dwell_time_ss,
		show_arrive,
		show_departure,
		arrive_str,
		departure_str,
		run_in_limit,
		run_out_limit,
		remarks,
		always_show_hh
	)
	SQL;
	protected function _genInsertValuesQuerySegment(
		int $i
	): string {
		// projects_id は親StopPattern (stop_patterns) から導出
		return <<<SQL
			(
				:stop_pattern_rows_id_{$i},
				{$this->PLACEHOLDER_PARENT_ID},
				(SELECT sp.projects_id FROM stop_patterns sp WHERE sp.stop_patterns_id = {$this->PLACEHOLDER_PARENT_ID}),
				:project_stations_id_{$i},
				{$this->PLACEHOLDER_OWNER},
				:sort_key_{$i},
				:track_name_{$i},
				:track_hidden_{$i},
				:is_operation_only_stop_{$i},
				:is_pass_{$i},
				:drive_time_mm_{$i},
				:drive_time_ss_{$i},
				:dwell_time_mm_{$i},
				:dwell_time_ss_{$i},
				:show_arrive_{$i},
				:show_departure_{$i},
				:arrive_str_{$i},
				:departure_str_{$i},
				:run_in_limit_{$i},
				:run_out_limit_{$i},
				:remarks_{$i},
				:always_show_hh_{$i}
			)
		SQL;
	}
	protected function _setInsertValues(
		PDOStatement $query,
		int $i,
		UuidInterface $id,
		mixed $d,
	) {
		$query->bindValue(":stop_pattern_rows_id_$i", $id->getBytes(), PDO::PARAM_STR);
		$query->bindValue(":project_stations_id_$i", $d->project_stations_id->getBytes(), PDO::PARAM_STR);
		$query->bindValue(":sort_key_$i", $d->sort_key ?? 0, PDO::PARAM_INT);
		$query->bindValue(":track_name_$i", $d->track_name, PDO::PARAM_STR);
		$query->bindValue(":track_hidden_$i", $d->track_hidden ?? false, PDO::PARAM_BOOL);
		$query->bindValue(":is_operation_only_stop_$i", $d->is_operation_only_stop ?? false, PDO::PARAM_BOOL);
		$query->bindValue(":is_pass_$i", $d->is_pass ?? false, PDO::PARAM_BOOL);
		$query->bindValue(":drive_time_mm_$i", $d->drive_time_mm, $d->drive_time_mm === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
		$query->bindValue(":drive_time_ss_$i", $d->drive_time_ss, $d->drive_time_ss === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
		$query->bindValue(":dwell_time_mm_$i", $d->dwell_time_mm, $d->dwell_time_mm === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
		$query->bindValue(":dwell_time_ss_$i", $d->dwell_time_ss, $d->dwell_time_ss === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
		$query->bindValue(":show_arrive_$i", $d->show_arrive ?? true, PDO::PARAM_BOOL);
		$query->bindValue(":show_departure_$i", $d->show_departure ?? true, PDO::PARAM_BOOL);
		$query->bindValue(":arrive_str_$i", $d->arrive_str, PDO::PARAM_STR);
		$query->bindValue(":departure_str_$i", $d->departure_str, PDO::PARAM_STR);
		$query->bindValue(":run_in_limit_$i", $d->run_in_limit, $d->run_in_limit === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
		$query->bindValue(":run_out_limit_$i", $d->run_out_limit, $d->run_out_limit === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
		$query->bindValue(":remarks_$i", $d->remarks, PDO::PARAM_STR);
		$query->bindValue(":always_show_hh_$i", $d->always_show_hh ?? false, PDO::PARAM_BOOL);
	}
}
