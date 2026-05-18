<?php

namespace dev_t0r\trvis_backend\repo;

use dev_t0r\trvis_backend\Constants;
use dev_t0r\trvis_backend\model\DataWithId;
use dev_t0r\trvis_backend\model\TrvisContentType;
use dev_t0r\trvis_backend\model\TRViSJsonWork;
use dev_t0r\trvis_backend\model\Work;
use dev_t0r\trvis_backend\RetValueOrError;
use dev_t0r\trvis_backend\Utils;
use PDO;
use PDOStatement;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * @extends MyRepoBase<Work>
 */
final class WorksRepo extends MyRepoBase
{
	public function __construct(
		PDO $db,
		LoggerInterface $logger,
	) {
		parent::__construct(
			db: $db,
			logger: $logger,
			TABLE_NAME: 'works',
			parentTableNameList: ['work_groups'],
			SQL_SELECT_COLUMNS: self::SQL_SELECT_COLUMNS,
			SQL_INSERT_COLUMNS: self::SQL_INSERT_COLUMNS,
		);
	}

	private const SQL_SELECT_COLUMNS = <<<SQL
		works_id,
		work_groups_id,
		created_at,
		description,
		name,
		affect_date,
		affix_content_type,
		affix_file_name,
		remarks,
		has_e_train_timetable,
		e_train_timetable_content_type,
		e_train_timetable_file_name

	SQL;

	protected function _fetchResultToObj(
		mixed $d,
	): mixed {
		$result = new Work();
		$result->setData([
			'works_id' => Uuid::fromBytes($d['works_id']),
			'work_groups_id' => Uuid::fromBytes($d['work_groups_id']),
			'created_at' => Utils::dbDateStrToDateTime($d['created_at']),
			'description' => $d['description'],
			'name' => $d['name'],
			'affect_date' => Utils::dbDateStrToDateTime($d['affect_date']),
			'affix_content_type' => TrvisContentType::fromOrNull($d['affix_content_type']),
			// TODO: implement affix_content
			'affix_content' => null,
			'remarks' => $d['remarks'],
			'has_e_train_timetable' => boolval($d['has_e_train_timetable']),
			'e_train_timetable_content_type' => TrvisContentType::fromOrNull($d['e_train_timetable_content_type']),
			// TODO: implement e_train_timetable_content
			'e_train_timetable_content' => null,
		]);
		return $result;
	}

	private const SQL_INSERT_COLUMNS = <<<SQL
	(
		works_id,
		work_groups_id,
		description,
		owner,
		name,
		affect_date,
		affix_content_type,
		affix_file_name,
		remarks,
		has_e_train_timetable,
		e_train_timetable_content_type,
		e_train_timetable_file_name
	)
	SQL;
	protected function _genInsertValuesQuerySegment(
		int $i
	): string {
		return <<<SQL
			(
				:works_id_{$i},
				{$this->PLACEHOLDER_PARENT_ID},
				:description_{$i},
				{$this->PLACEHOLDER_OWNER},
				:name_{$i},
				:affect_date_{$i},
				:affix_content_type_{$i},
				:affix_file_name_{$i},
				:remarks_{$i},
				:has_e_train_timetable_{$i},
				:e_train_timetable_content_type_{$i},
				:e_train_timetable_file_name_{$i}
			)
		SQL;
	}
	protected function _setInsertValues(
		PDOStatement $query,
		int $i,
		UuidInterface $id,
		mixed $d,
	) {
		$query->bindValue(":works_id_$i", $id->getBytes(), PDO::PARAM_STR);
		$query->bindValue(":description_$i", $d->description, PDO::PARAM_STR);
		$query->bindValue(":name_$i", $d->name, PDO::PARAM_STR);
		$query->bindValue(":affect_date_$i", Utils::utcDateStrOrNull($d->affect_date), PDO::PARAM_STR);
		$query->bindValue(":affix_content_type_$i", $d->affix_content_type?->value, PDO::PARAM_INT);
		// TODO: implement affix_content
		$query->bindValue(":affix_file_name_$i", null, PDO::PARAM_STR);
		$query->bindValue(":remarks_$i", $d->remarks, PDO::PARAM_STR);
		$query->bindValue(":has_e_train_timetable_$i", $d->has_e_train_timetable ?? false, PDO::PARAM_BOOL);
		$query->bindValue(":e_train_timetable_content_type_$i", $d->e_train_timetable_content_type?->value, PDO::PARAM_INT);
		// TODO: implement e_train_timetable_content
		$query->bindValue(":e_train_timetable_file_name_$i", null, PDO::PARAM_STR);
	}

	/**
	 * @param array<UuidInterface> $parentIdList
	 * @param array<string, array<DataWithId<TRViSJsonWork>>> $dst
	 * @return RetValueOrError<array<UuidInterface>>
	 */
	public function dump(
		array $parentIdList,
		array &$dst,
	): RetValueOrError {
		$this->logger->debug(
			'WorksRepo::dump() called - {parentIdList}',
			[
				'parentIdList' => $parentIdList,
			],
		);

		$parentIdCount = count($parentIdList);
		$parentIdListPlaceholder = implode(', ', array_fill(0, $parentIdCount, '?'));
		try
		{
			// station_tracksとのJOINは、本当はstations_idも条件として加えるべきである。
			// しかし、実装上の都合で同じWorkGroupの他の駅に属するstation_tracksも登録できてしまう。
			// そのため、stations_idでの絞り込みは行わない。
			$query = $this->db->prepare(<<<SQL
				SELECT
					HEX(works.work_groups_id) AS parent_id,
					works.works_id AS works_id,
					works.name AS Name,
					works.affect_date AS AffectDate,
					works.affix_content_type AS AffixContentType,
					works.affix_file_name AS AffixFileName,
					works.remarks AS Remarks,
					works.has_e_train_timetable AS HasETrainTimetable,
					works.e_train_timetable_content_type AS ETrainTimetableContentType,
					works.e_train_timetable_file_name AS ETrainTimetableFileName
				FROM
					works
				WHERE
					{$this->parentTableName}_id IN ($parentIdListPlaceholder)
				SQL
			);
			for ($i = 0; $i < $parentIdCount; ++$i) {
				$query->bindValue($i + 1, $parentIdList[$i]->getBytes(), PDO::PARAM_STR);
			}
			$query->execute();
			$rowCount = $query->rowCount();
			$this->logger->debug(
				'selected work {rowCount} rows.',
				[
					'rowCount' => $rowCount,
				],
			);
			if ($rowCount === 0) {
				return RetValueOrError::withValue([]);
			}

			$rowCountPerParent = [];
			$worksIdList = [];
			$worksIdListIndex = 0;
			while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
				$parentId = $row['parent_id'];
				if (!array_key_exists($parentId, $rowCountPerParent)) {
					$rowCountPerParent[$parentId] = 0;
				}
				$d = self::fetchResultRowToTrvisJsonData($row);
				$dst[$parentId][$rowCountPerParent[$parentId]++] = $d;
				$worksIdList[$worksIdListIndex++] = $d->id;
			}

			return RetValueOrError::withValue($worksIdList);
		}
		catch (\PDOException $e)
		{
			$this->logger->error(
				'Failed to dump work rows. - {exception}',
				[
					'exception' => $e,
				],
			);
			return RetValueOrError::withError(
				Constants::HTTP_INTERNAL_SERVER_ERROR,
				'Failed to execute SQL - ' . $e->getCode(),
			);
		}
	}

	/**
	 * @param array<string, mixed> $kvpList
	 * @return array<string, DataWithId<TRViSJsonWork>>
	 */
	private static function fetchResultRowToTrvisJsonData(
		array $kvpList,
	): DataWithId {
		$d = new TRViSJsonWork();
		$d->setData([
			'Name' => $kvpList['Name'],
			'AffectDate' => Utils::dbDateStrToDateTime($kvpList['AffectDate']),
			'AffixContentType' => TrvisContentType::fromOrNull($kvpList['AffixContentType']),
			'AffixContent' => null,
			'Remarks' => $kvpList['Remarks'],
			'HasETrainTimetable' => boolval($kvpList['HasETrainTimetable']),
			'ETrainTimetableContentType' => TrvisContentType::fromOrNull($kvpList['ETrainTimetableContentType']),
			'ETrainTimetableContent' => null,

			'Trains' => [],
		]);
		return new DataWithId(
			id: Uuid::fromBytes($kvpList['works_id']),
			data: $d,
		);
	}

}
