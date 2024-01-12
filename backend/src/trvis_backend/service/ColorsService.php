<?php

namespace dev_t0r\trvis_backend\service;

use dev_t0r\trvis_backend\model\Color;
use dev_t0r\trvis_backend\repo\ColorsRepo;
use dev_t0r\trvis_backend\repo\WorkGroupsPrivilegesRepo;
use dev_t0r\trvis_backend\RetValueOrError;
use PDO;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\UuidInterface;

/**
 * @template-implements IMyServiceBase<Color, ColorsRepo>
 */
final class ColorsService extends MyServiceBase
{
	public function __construct(
		PDO $db,
		LoggerInterface $logger,
	) {
		parent::__construct(
			db: $db,
			targetRepo: new ColorsRepo($db, $logger),
			parentRepo: new WorkGroupsPrivilegesRepo($db, $logger),
			logger: $logger,
			dataTypeName: 'Color',
			keys: [
				'name',
				'description',
				'color_8bit',
				'color_real',
			],
		);
	}

	protected function beforeUpdate(
		string $senderUserId,
		UuidInterface $id,
		/** @param T $data */
		object $data,
		/** @param array<string, mixed> $kvpArray */
		array &$kvpArray,
	): ?RetValueOrError {
		$hasColor8bit = array_key_exists('color_8bit', $kvpArray);
		$hasColorReal = array_key_exists('color_real', $kvpArray);

		if ($hasColor8bit) {
			$color8bit = $kvpArray['color_8bit'];
			$kvpArray['red_8bit'] = $color8bit->red;
			$kvpArray['green_8bit'] = $color8bit->green;
			$kvpArray['blue_8bit'] = $color8bit->blue;
			unset($kvpArray['color_8bit']);
		}

		if ($hasColorReal) {
			$colorReal = $kvpArray['color_real'];
			$kvpArray['red_real'] = $colorReal->red;
			$kvpArray['green_real'] = $colorReal->green;
			$kvpArray['blue_real'] = $colorReal->blue;
			unset($kvpArray['color_real']);
		}

		return null;
	}
}
