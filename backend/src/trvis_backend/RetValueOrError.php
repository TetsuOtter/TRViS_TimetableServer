<?php

namespace dev_t0r\trvis_backend;

use Psr\Http\Message\ResponseInterface;

/**
 * @template T
 */
final class RetValueOrError
{
	public readonly bool $isError;
	/** @var T */
	public readonly mixed $value;
	public readonly int $statusCode;
	public readonly int $errorCode;
	public readonly string $errorMsg;

	/**
	 * @template T
	 * @param T $value
	 */
	private function __construct(
		bool $isError = false,
		mixed $value = null,
		?int $statusCode = null,
		?string $errorMsg = null,
		?int $errorCode = null,
		private readonly ?int $totalCount = null,
	) {
		$this->isError = $isError;
		$this->value = $value;
		$this->statusCode = $statusCode ?? 200;
		$this->errorCode = $errorCode ?? $this->statusCode;
		$this->errorMsg = $errorMsg ?? '';
	}

	/**
	 * @template T
	 * @param T $value
	 */
	public static function withValue(
		mixed $value,
		?int $statusCode = null,
	): self {
		return new self(
			value: $value,
			statusCode: $statusCode,
		);
	}
	/**
	 * @template T
	 * @param RetValueOrError<T> $value
	 * @param RetValueOrError<number> $totalCount
	 *
	 * M10 — accepted (count, page) consistency tradeoff
	 * -------------------------------------------------------
	 * Every paged-list service calls two independent SELECTs:
	 *   1. COUNT(*) → becomes the X-Total-Count response header ($totalCount)
	 *   2. Keyset/offset page → becomes the response body ($value)
	 *
	 * Under MySQL InnoDB's default READ COMMITTED isolation, each statement sees
	 * its own fresh MVCC snapshot.  A concurrent INSERT or DELETE that arrives
	 * between the two statements can therefore produce a tuple where the page and
	 * the total disagree (e.g. total=11 but the page still shows the just-deleted
	 * row, or total=12 but the new row is missing from the page).
	 *
	 * This is intentionally accepted (CODE_REVIEW.md M10 — 許容) because:
	 *   - The artefact is cosmetic: clients should treat (X-Total-Count, page) as
	 *     eventually consistent and never rely on them being jointly exact.
	 *   - Wrapping every paged read in a REPEATABLE READ transaction would add
	 *     lock/snapshot overhead to all read paths for negligible user-visible gain.
	 *
	 * If a future caller needs strict consistency (count == len(page) guaranteed),
	 * the fix is to wrap both repo calls in a single REPEATABLE READ transaction
	 * before invoking withTotalCount().
	 */
	public static function withTotalCount(
		RetValueOrError $value,
		RetValueOrError $totalCount,
		?int $statusCode = null,
	): self {
		// selectPageTotalCount は PDO 経由で COUNT(*) を文字列として返すため、
		// ?int $totalCount へ渡す前にここで一元的に int 化する
		// (constructor へ到達する全経路をこの一箇所でカバー)。負値はあり得ないが念のため 0 下限。
		$totalCountValue = $totalCount->isError ? null : max(0, (int)$totalCount->value);
		return new self(
			isError: $value->isError,
			value: $value->value,
			statusCode: $statusCode ?? $value->statusCode,
			errorMsg: $value->errorMsg,
			errorCode: $value->errorCode,
			totalCount: $totalCountValue,
		);
	}

	public static function withError(
		int $statusCode,
		string $errorMsg,
		?int $errorCode = null,
	): self {
		return new self(
			isError: true,
			statusCode: $statusCode,
			errorMsg: $errorMsg,
			errorCode: $errorCode,
		);
	}
	public static function withBadReq(
		string $errorMsg,
		?int $errorCode = null,
	): self {
		return self::withError(
			statusCode: Constants::HTTP_BAD_REQUEST,
			errorMsg: $errorMsg,
			errorCode: $errorCode,
		);
	}

	public function getResponseWithJson(ResponseInterface $response, ?int $statusCode = null): ResponseInterface
	{
		if ($this->isError) {
			return Utils::withError($response, $this->statusCode, $this->errorMsg, $this->errorCode);
		} elseif (!is_null($this->value)) {
			if (!is_null($this->totalCount)) {
				// PSR-7 ResponseInterface::withHeader() は string|string[] を要求するため明示的に文字列化
				$response = $response->withHeader(Constants::HEADER_TOTAL_COUNT, (string)$this->totalCount);
			}

			return Utils::withJson($response, $this->value, $statusCode ?? $this->statusCode);
		} else {
			return $response->withStatus($this->statusCode);
		}
	}
}
