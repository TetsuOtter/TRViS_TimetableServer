<?php

namespace dev_t0r\trvis_backend\repo;

use dev_t0r\trvis_backend\model\Color;
use dev_t0r\trvis_backend\model\Color8bit;
use dev_t0r\trvis_backend\model\ColorReal;
use dev_t0r\trvis_backend\Utils;
use PDO;
use PDOStatement;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * @extends MyRepoBase<Color>
 */
final class ColorsRepo extends MyRepoBase
{
	public function __construct(
		PDO $db,
		LoggerInterface $logger,
	) {
		parent::__construct(
			db: $db,
			logger: $logger,
			TABLE_NAME: 'colors',
			parentTableNameList: ['work_groups'],
			SQL_SELECT_COLUMNS: self::SQL_SELECT_COLUMNS,
			SQL_INSERT_COLUMNS: self::SQL_INSERT_COLUMNS,
		);
	}

	private const SQL_SELECT_COLUMNS = <<<SQL
		colors_id,
		work_groups_id,
		description,
		created_at,
		name,

		red_8bit,
		green_8bit,
		blue_8bit,

		red_real,
		green_real,
		blue_real

	SQL;

	protected function _fetchResultToObj(
		mixed $d,
	): mixed {
		$result = new Color();
		$color8bit = new Color8bit();
		$color8bit->setData([
			'red' => $d['red_8bit'],
			'green' => $d['green_8bit'],
			'blue' => $d['blue_8bit'],
		]);

		$colorReal = null;
		$redReal = $d['red_real'];
		$greenReal = $d['green_real'];
		$blueReal = $d['blue_real'];
		if (!is_null($redReal) && !is_null($greenReal) && !is_null($blueReal)) {
			$colorReal = new ColorReal();
			$colorReal->setData([
				'red' => floatval($redReal),
				'green' => floatval($greenReal),
				'blue' => floatval($blueReal),
			]);
		}

		$result->setData([
			'colors_id' => Uuid::fromBytes($d['colors_id']),
			'work_groups_id' => Uuid::fromBytes($d['work_groups_id']),
			'description' => $d['description'],
			'created_at' => Utils::dbDateStrToDateTime($d['created_at']),
			'name' => $d['name'],
			'color_8bit' => $color8bit,
			'color_real' => $colorReal,
		]);
		return $result;
	}

	private const SQL_INSERT_COLUMNS = <<<SQL
	(
		colors_id,
		work_groups_id,
		description,
		owner,
		name,

		red_8bit,
		green_8bit,
		blue_8bit,

		red_real,
		green_real,
		blue_real
	)
	SQL;
	protected function _genInsertValuesQuerySegment(
		int $i
	): string {
		return <<<SQL
			(
				:colors_id_{$i},
				{$this->PLACEHOLDER_PARENT_ID},
				:description_{$i},
				{$this->PLACEHOLDER_OWNER},
				:name_{$i},

				:red_8bit_{$i},
				:green_8bit_{$i},
				:blue_8bit_{$i},

				:red_real_{$i},
				:green_real_{$i},
				:blue_real_{$i}
			)
		SQL;
	}
	protected function _setInsertValues(
		PDOStatement $query,
		int $i,
		UuidInterface $id,
		mixed $d,
	) {
		$query->bindValue(":colors_id_$i", $id->getBytes(), PDO::PARAM_STR);
		$query->bindValue(":description_$i", $d->description, PDO::PARAM_STR);
		$query->bindValue(":name_$i", $d->name, PDO::PARAM_STR);

		$query->bindValue(":red_8bit_$i", $d->color_8bit->red, PDO::PARAM_INT);
		$query->bindValue(":green_8bit_$i", $d->color_8bit->green, PDO::PARAM_INT);
		$query->bindValue(":blue_8bit_$i", $d->color_8bit->blue, PDO::PARAM_INT);

		$query->bindValue(":red_real_$i", $d->color_real?->red, PDO::PARAM_STR);
		$query->bindValue(":green_real_$i", $d->color_real?->green, PDO::PARAM_STR);
		$query->bindValue(":blue_real_$i", $d->color_real?->blue, PDO::PARAM_STR);
	}
}
