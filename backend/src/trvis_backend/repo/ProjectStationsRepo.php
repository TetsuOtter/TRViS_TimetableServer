<?php

namespace dev_t0r\trvis_backend\repo;

use dev_t0r\trvis_backend\model\ProjectStation;
use dev_t0r\trvis_backend\model\ProjectStationLocationLonlat;
use dev_t0r\trvis_backend\Utils;
use PDO;
use PDOStatement;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * Project共通の駅 (project_stations)。Project直下。
 * description列はDBにあるがAPIに無いのでINSERTから除外 (DB default '')。
 *
 * @extends MyProjectRootedRepoBase<ProjectStation>
 */
final class ProjectStationsRepo extends MyProjectRootedRepoBase
{
	public function __construct(
		PDO $db,
		\Psr\Log\LoggerInterface $logger,
	) {
		parent::__construct(
			db: $db,
			logger: $logger,
			TABLE_NAME: 'project_stations',
			parentTableNameList: ['projects'],
			SQL_SELECT_COLUMNS: self::SQL_SELECT_COLUMNS,
			SQL_INSERT_COLUMNS: self::SQL_INSERT_COLUMNS,
		);
	}

	private const SQL_SELECT_COLUMNS = <<<SQL
		project_stations_id,
		projects_id,
		created_at,
		name,
		full_name,
		ST_X(location_lonlat) AS location_lon,
		ST_Y(location_lonlat) AS location_lat,
		on_station_detect_radius_m,
		always_show_hh

	SQL;

	protected function _fetchResultToObj(
		mixed $d,
	): mixed {
		$result = new ProjectStation();
		$lonlat = null;
		if (!is_null($d['location_lon']) && !is_null($d['location_lat'])) {
			$lonlat = new ProjectStationLocationLonlat();
			$lonlat->setData([
				'longitude' => floatval($d['location_lon']),
				'latitude' => floatval($d['location_lat']),
			]);
		}
		$result->setData([
			'project_stations_id' => Uuid::fromBytes($d['project_stations_id']),
			'projects_id' => Uuid::fromBytes($d['projects_id']),
			'created_at' => Utils::dbDateStrToDateTime($d['created_at']),
			'name' => $d['name'],
			'full_name' => $d['full_name'],
			'location_lonlat' => $lonlat,
			'on_station_detect_radius_m' => is_null($d['on_station_detect_radius_m']) ? null : floatval($d['on_station_detect_radius_m']),
			'always_show_hh' => boolval($d['always_show_hh']),
		]);
		return $result;
	}

	private const SQL_INSERT_COLUMNS = <<<SQL
	(
		project_stations_id,
		projects_id,
		owner,
		name,
		full_name,
		location_lonlat,
		on_station_detect_radius_m,
		always_show_hh
	)
	SQL;
	protected function _genInsertValuesQuerySegment(
		int $i
	): string {
		return <<<SQL
			(
				:project_stations_id_{$i},
				{$this->PLACEHOLDER_PARENT_ID},
				{$this->PLACEHOLDER_OWNER},
				:name_{$i},
				:full_name_{$i},
				ST_PointFromText(:location_lonlat_{$i}),
				:on_station_detect_radius_m_{$i},
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
		$latlon = null;
		if (!is_null($d->location_lonlat)) {
			$latlon = "POINT({$d->location_lonlat->longitude} {$d->location_lonlat->latitude})";
		}
		$query->bindValue(":project_stations_id_$i", $id->getBytes(), PDO::PARAM_STR);
		$query->bindValue(":name_$i", $d->name, PDO::PARAM_STR);
		$query->bindValue(":full_name_$i", $d->full_name, PDO::PARAM_STR);
		$query->bindValue(":location_lonlat_$i", $latlon, PDO::PARAM_STR);
		$query->bindValue(":on_station_detect_radius_m_$i", $d->on_station_detect_radius_m, PDO::PARAM_STR);
		$query->bindValue(":always_show_hh_$i", $d->always_show_hh ?? false, PDO::PARAM_BOOL);
	}

	protected function _keyToUpdateQuerySetLine(
		string $key,
	): string {
		if ($key === 'location_lonlat') {
			return "{$key} = ST_PointFromText(:{$key})";
		}
		return parent::_keyToUpdateQuerySetLine($key);
	}
	protected function _kvpToValueToBind(
		string $key,
		mixed $value,
	): mixed {
		if ($key === 'location_lonlat') {
			return "POINT({$value->longitude} {$value->latitude})";
		}
		return parent::_kvpToValueToBind($key, $value);
	}
}
