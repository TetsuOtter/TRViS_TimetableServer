<?php

namespace dev_t0r\trvis_backend\repo;

use dev_t0r\trvis_backend\model\Station;
use dev_t0r\trvis_backend\model\StationLocationLonlat;
use dev_t0r\trvis_backend\Utils;
use PDO;
use PDOStatement;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * @extends MyRepoBase<Station>
 */
final class StationsRepo extends MyRepoBase
{
	public function __construct(
		PDO $db,
		LoggerInterface $logger,
	) {
		parent::__construct(
			db: $db,
			logger: $logger,
			TABLE_NAME: 'stations',
			parentTableNameList: ['work_groups'],
			SQL_SELECT_COLUMNS: self::SQL_SELECT_COLUMNS,
			SQL_INSERT_COLUMNS: self::SQL_INSERT_COLUMNS,
		);
	}

	private const SQL_SELECT_COLUMNS = <<<SQL
		stations_id,
		work_groups_id,
		description,
		created_at,
		name,
		location_km,
		ST_X(location_lonlat) AS location_lon,
		ST_Y(location_lonlat) AS location_lat,
		on_station_detect_radius_m,
		record_type

	SQL;

	protected function _fetchResultToObj(
		mixed $d,
	): mixed {
		$result = new Station();
		$lonlat = null;
		if (!is_null($d['location_lon']) && !is_null($d['location_lat'])) {
			$lonlat = new StationLocationLonlat();
			$lonlat->setData([
				'longitude' => $d['location_lon'],
				'latitude' => $d['location_lat'],
			]);
		}
		$result->setData([
			'stations_id' => Uuid::fromBytes($d['stations_id']),
			'work_groups_id' => Uuid::fromBytes($d['work_groups_id']),
			'description' => $d['description'],
			'created_at' => Utils::dbDateStrToDateTime($d['created_at']),
			'name' => $d['name'],
			'location_km' => $d['location_km'],
			'location_lonlat' => $lonlat,
			'on_station_detect_radius_m' => $d['on_station_detect_radius_m'],
			'record_type' => $d['record_type'],
		]);
		return $result;
	}

	private const SQL_INSERT_COLUMNS = <<<SQL
	(
		stations_id,
		work_groups_id,
		description,
		owner,
		name,
		location_km,
		location_lonlat,
		on_station_detect_radius_m,
		record_type
	)
	SQL;
	protected function _genInsertValuesQuerySegment(
		int $i
	): string {
		return <<<SQL
			(
				:stations_id_{$i},
				{$this->PLACEHOLDER_PARENT_ID},
				:description_{$i},
				{$this->PLACEHOLDER_OWNER},
				:name_{$i},
				:location_km_{$i},
				ST_PointFromText(:location_lonlat_{$i}),
				:on_station_detect_radius_m_{$i},
				:record_type_{$i}
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
			$lon = $d->location_lonlat->longitude;
			$lat = $d->location_lonlat->latitude;
			$latlon = "POINT($lon $lat)";
		}
		$query->bindValue(":stations_id_$i", $id->getBytes(), PDO::PARAM_STR);
		$query->bindValue(":description_$i", $d->description, PDO::PARAM_STR);
		$query->bindValue(":name_$i", $d->name, PDO::PARAM_STR);
		$query->bindValue(":location_km_$i", $d->location_km, PDO::PARAM_STR);
		$query->bindValue(":location_lonlat_$i", $latlon, PDO::PARAM_STR);
		$query->bindValue(":on_station_detect_radius_m_$i", $d->on_station_detect_radius_m, PDO::PARAM_STR);
		$query->bindValue(":record_type_$i", $d->record_type->value, PDO::PARAM_INT);
	}

	protected function _keyToUpdateQuerySetLine(
		string $key,
	): string {
		if ($key === 'location_lonlat') {
			return "{$key} = ST_PointFromText(:{$key})";
		} else {
			return parent::_keyToUpdateQuerySetLine($key);
		}
	}
	protected function _kvpToValueToBind(
		string $key,
		mixed $value,
	): mixed {
		if ($key === 'location_lonlat') {
			$lon = $value->longitude;
			$lat = $value->latitude;
			return "POINT($lon $lat)";
		} else {
			return parent::_kvpToValueToBind($key, $value);
		}
	}
}
