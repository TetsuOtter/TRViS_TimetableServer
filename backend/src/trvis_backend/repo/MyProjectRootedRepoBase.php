<?php

namespace dev_t0r\trvis_backend\repo;

use dev_t0r\trvis_backend\Constants;
use dev_t0r\trvis_backend\RetValueOrError;
use PDO;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * Project=権限ルート の配下にぶら下がる汎用エンティティ用のRepoベース。
 *
 * 各テーブルは `projects_id BINARY(16) NOT NULL` を直接持つため、
 * 権限解決は「自テーブルの行から projects_id を引く → ProjectsPrivilegesRepo
 * へ委譲」で一律に行える ([[backend-model-additions]] の chokepoint と同型)。
 *
 * MyRepoBase::selectPrivilegeType は work_groups_privileges を
 * `USING (work_groups_id)` でJOINするため project-rooted では使えない。
 * ここで override する。CRUD系 (selectOne/selectPage/insertList/update/delete)
 * は MyRepoBase の実装が `{TABLE_NAME}` 直クエリで work_groups に依存しない
 * ため、そのまま継承する。
 *
 * @template T of \dev_t0r\BaseModel
 * @extends MyRepoBase<T>
 */
abstract class MyProjectRootedRepoBase extends MyRepoBase
{
	public function selectPrivilegeType(
		UuidInterface $id,
		string $userId = Constants::UID_ANONYMOUS,
		bool $includeAnonymous = false,
		bool $selectForUpdate = false,
	): RetValueOrError {
		$this->logger->debug(
			'selectPrivilegeType (project-rooted) {TABLE_NAME} id: {id}, userId: {userId}',
			[
				'TABLE_NAME' => $this->TABLE_NAME,
				'id' => $id,
				'userId' => $userId,
			],
		);

		try
		{
			$query = $this->db->prepare(
				<<<SQL
				SELECT
					projects_id
				FROM
					{$this->TABLE_NAME}
				WHERE
					{$this->TABLE_NAME}_id = :id
				AND
					deleted_at IS NULL
				SQL
				.
				($selectForUpdate ? ' FOR UPDATE' : '')
			);
			$query->bindValue(':id', $id->getBytes(), PDO::PARAM_STR);
			$query->execute();
			$row = $query->fetch(PDO::FETCH_ASSOC);
			if ($row === false) {
				$this->logger->warning(
					'selectPrivilegeType (project-rooted) {TABLE_NAME} - row not found (id:{id})',
					[ 'TABLE_NAME' => $this->TABLE_NAME, 'id' => $id ],
				);
				return $this->errNotFound();
			}
		}
		catch (\PDOException $ex)
		{
			$errCode = $ex->getCode();
			$this->logger->error(
				"Failed to execute SQL ({errorCode} -> {errorInfo})",
				[ "errorCode" => $errCode, "errorInfo" => $ex->getMessage() ],
			);
			return RetValueOrError::withError(
				Constants::HTTP_INTERNAL_SERVER_ERROR,
				"Failed to execute SQL - " . $errCode,
			);
		}

		$projectsId = Uuid::fromBytes($row['projects_id']);
		// Project=権限ルート: 所属Projectの権限を導出して返す (同namespaceなのでimport不要)
		return (new ProjectsPrivilegesRepo($this->db, $this->logger))->selectPrivilegeType(
			id: $projectsId,
			userId: $userId,
			includeAnonymous: $includeAnonymous,
			selectForUpdate: $selectForUpdate,
		);
	}
}
