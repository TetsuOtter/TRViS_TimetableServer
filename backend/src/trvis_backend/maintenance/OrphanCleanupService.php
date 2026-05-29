<?php

declare(strict_types=1);

namespace dev_t0r\trvis_backend\maintenance;

use dev_t0r\trvis_backend\Constants;
use dev_t0r\trvis_backend\RetValueOrError;
use PDO;
use Psr\Log\LoggerInterface;

/**
 * M6 out-of-band orphan cleanup service.
 *
 * Soft-deletes child rows whose parents have already been soft-deleted,
 * fixing the leak where TrainsService::delete / WorksService::delete only
 * soft-delete the parent row without cascading to children.
 *
 * This is intentionally NOT invoked in-request; call it from a cron job
 * via bin/cleanup-orphans.php.
 */
final class OrphanCleanupService
{
	public function __construct(
		private readonly PDO $db,
		private readonly LoggerInterface $logger,
	) {
	}

	/**
	 * Run the orphan cleanup.
	 *
	 * On success returns RetValueOrError with value = ['trains' => int, 'timetable_rows' => int].
	 * For dry-run the value reports how many rows WOULD be soft-deleted; nothing is persisted.
	 *
	 * @return RetValueOrError<array{trains:int,timetable_rows:int}>
	 */
	public function run(bool $dryRun): RetValueOrError
	{
		try {
			// Use explicit transaction: autocommit state is non-deterministic across
			// environments because the Docker pdo.options does not set ATTR_AUTOCOMMIT=false.
			$this->db->beginTransaction();

			if ($dryRun) {
				// Dry-run: count what WOULD be soft-deleted without writing anything.

				// Count orphaned trains: trains whose parent work is soft-deleted.
				$stTrains = $this->db->prepare(
					'SELECT COUNT(*) FROM trains'
					. ' JOIN works ON trains.works_id = works.works_id'
					. ' WHERE trains.deleted_at IS NULL'
					. ' AND works.deleted_at IS NOT NULL'
				);
				$stTrains->execute();
				$trainsCount = (int)$stTrains->fetchColumn();

				// Count orphaned timetable_rows that WOULD be caught by both step 1
				// (train's work is deleted) and step 2 (train itself is deleted).
				// This mirrors exactly what the real run catches after step 1 completes:
				// rows whose train is currently deleted OR whose train's work is deleted
				// (the latter would cascade via the step-1 UPDATE).
				$stRows = $this->db->prepare(
					'SELECT COUNT(*) FROM timetable_rows tr'
					. ' JOIN trains t ON tr.trains_id = t.trains_id'
					. ' JOIN works w ON t.works_id = w.works_id'
					. ' WHERE tr.deleted_at IS NULL'
					. ' AND (t.deleted_at IS NOT NULL OR w.deleted_at IS NOT NULL)'
				);
				$stRows->execute();
				$rowsCount = (int)$stRows->fetchColumn();

				$this->db->rollBack();

				$this->logger->info(
					'OrphanCleanupService dry-run complete (trains:{trains}, timetable_rows:{timetable_rows})',
					['trains' => $trainsCount, 'timetable_rows' => $rowsCount, 'dryRun' => true],
				);

				return RetValueOrError::withValue(['trains' => $trainsCount, 'timetable_rows' => $rowsCount]);
			}

			// Step 1: soft-delete orphaned trains (parent work is soft-deleted).
			// UTC_TIMESTAMP() is used explicitly because the Docker pdo.options does
			// not issue SET time_zone='+00:00', so NOW()/CURRENT_TIMESTAMP() would
			// reflect the server session timezone rather than UTC.
			$stStep1 = $this->db->prepare(
				'UPDATE trains'
				. ' JOIN works ON trains.works_id = works.works_id'
				. ' SET trains.deleted_at = UTC_TIMESTAMP()'
				. ' WHERE trains.deleted_at IS NULL'
				. ' AND works.deleted_at IS NOT NULL'
			);
			$stStep1->execute();
			$trainsCount = $stStep1->rowCount();

			$this->logger->info(
				'OrphanCleanupService step 1 (trains) complete (affected:{trains}, dryRun:{dryRun})',
				['trains' => $trainsCount, 'dryRun' => false],
			);

			// Step 2: soft-delete orphaned timetable_rows AFTER step 1.
			// Running step 2 after step 1 in the same transaction guarantees
			// completeness: rows whose train was alive but whose grandparent work was
			// soft-deleted are first caught by step 1 (train is now soft-deleted),
			// then picked up here. Rows whose train was already directly soft-deleted
			// before this run are also caught. Together these two steps cover all
			// reachable orphans transitively.
			$stStep2 = $this->db->prepare(
				'UPDATE timetable_rows'
				. ' JOIN trains ON timetable_rows.trains_id = trains.trains_id'
				. ' SET timetable_rows.deleted_at = UTC_TIMESTAMP()'
				. ' WHERE timetable_rows.deleted_at IS NULL'
				. ' AND trains.deleted_at IS NOT NULL'
			);
			$stStep2->execute();
			$rowsCount = $stStep2->rowCount();

			$this->logger->info(
				'OrphanCleanupService step 2 (timetable_rows) complete (affected:{timetable_rows}, dryRun:{dryRun})',
				['timetable_rows' => $rowsCount, 'dryRun' => false],
			);

			$this->db->commit();

			return RetValueOrError::withValue(['trains' => $trainsCount, 'timetable_rows' => $rowsCount]);
		} catch (\Throwable $e) {
			$this->logger->error(
				'OrphanCleanupService failed: {message}',
				['message' => $e->getMessage(), 'exception' => $e],
			);
			if ($this->db->inTransaction()) {
				$this->db->rollBack();
			}
			return RetValueOrError::withError(
				Constants::HTTP_INTERNAL_SERVER_ERROR,
				'OrphanCleanupService failed: ' . $e->getMessage(),
			);
		}
	}
}
