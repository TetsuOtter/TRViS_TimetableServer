<?php

namespace dev_t0r\trvis_backend;

use dev_t0r\trvis_backend\model\JsonDateTime;

final class Utils
{
	private static ?\DateTimeZone $UTC = null;

	public static function getUTC(): \DateTimeZone
	{
		return self::$UTC ??= new \DateTimeZone('UTC');
	}

	public static function getUtcNow(): \DateTime
	{
		return new \DateTime('now', self::getUTC());
	}

	public static function withJson(
		\Psr\Http\Message\ResponseInterface $oldResponse,
		mixed $data,
		int $statusCode = 200,
	): \Psr\Http\Message\ResponseInterface {
		$json = json_encode($data);
		if ($json === false) {
			// json_encode failed (e.g. malformed UTF-8 / NAN / recursion). Never
			// emit a success status with an empty body — that silently looks like
			// a valid empty response to the client. Fall back to a 500 envelope.
			// The fallback payload is a fixed ASCII array that cannot itself fail
			// to encode, so no recursion / second failure is possible.
			$statusCode = Constants::HTTP_INTERNAL_SERVER_ERROR;
			$json = json_encode([
				'code' => Constants::HTTP_INTERNAL_SERVER_ERROR,
				'message' => 'Internal Server Error',
			]);
		}

		$response = $oldResponse
			->withHeader('Content-Type', 'application/json')
			->withStatus($statusCode)
		;
		$response->getBody()->write($json);
		return $response;
	}

	public static function withError(
		\Psr\Http\Message\ResponseInterface $oldResponse,
		int $statusCode,
		string $message,
		?int $errorCode = null,
	): \Psr\Http\Message\ResponseInterface {
		if ($errorCode === null) {
			$errorCode = $statusCode;
		}

		return self::withJson(
			$oldResponse,
			[
				'code' => $errorCode,
				'message' => $message,
			],
			$statusCode,
		);
	}

	public static function withUuidError(
		\Psr\Http\Message\ResponseInterface $oldResponse,
	): \Psr\Http\Message\ResponseInterface {
		return self::withError($oldResponse, 400, 'Bad Request (Invalid UUID format)');
	}

	/**
	 * L1: precise mapping of MySQL integrity-constraint failures to HTTP.
	 *
	 * SQLSTATE 23000 is the integrity-constraint *class*; the prior blanket
	 * "23000 → 409 ${Entity} already exists" treats genuinely-different
	 * causes — FK pointing at a missing parent (1452), NOT NULL on a
	 * required column (1048/1364) — as duplicate-key conflicts, which
	 * misleads clients and crowds out real 4xx info. The MySQL driver
	 * errno lives at PDOStatement::errorInfo()[1] (or PDOException->errorInfo[1])
	 * and is what distinguishes them. Any unrecognised driver errno under 23000
	 * is treated as 500 (real integrity bug, not caller error) so we don't
	 * silently lie about the cause.
	 *
	 * @param ?string $sqlState     SQLSTATE class (e.g. '23000'); $query->errorCode() or $ex->getCode().
	 * @param ?int    $driverCode   MySQL driver errno from errorInfo()[1] / ex->errorInfo[1].
	 * @param string  $entityLabel  Singular entity name for the 409 message (e.g. "Project").
	 * @return RetValueOrError<null>
	 */
	public static function mapPdoIntegrityError(
		?string $sqlState,
		?int $driverCode,
		string $entityLabel,
	): RetValueOrError {
		if ($sqlState === '23000') {
			switch ($driverCode) {
				// 1062 = duplicate entry on UNIQUE/PRIMARY key.
				// 1586 = duplicate entry on partitioned UNIQUE key (same family).
				case 1062:
				case 1586:
					return RetValueOrError::withError(
						Constants::HTTP_CONFLICT,
						"$entityLabel already exists",
					);
				// 1452 = FK constraint fails (child row references a non-existent parent).
				// 1216 / 1217 = older spellings of FK add/drop failure (same cause class).
				case 1452:
				case 1216:
				case 1217:
					return RetValueOrError::withError(
						Constants::HTTP_BAD_REQUEST,
						"Bad Request (referenced parent does not exist)",
					);
				// 1048 = column cannot be null (NOT NULL with explicit NULL).
				// 1364 = field has no default value and was not supplied (strict mode).
				case 1048:
				case 1364:
					return RetValueOrError::withError(
						Constants::HTTP_BAD_REQUEST,
						"Bad Request (required field is missing or null)",
					);
				// Other 23000 causes (CHECK violation 3819, etc.) are not
				// caller-correctable in any common path here — surface 500.
			}
		}

		return RetValueOrError::withError(
			Constants::HTTP_INTERNAL_SERVER_ERROR,
			"Failed to execute SQL - " . ($sqlState ?? '?'),
		);
	}

	public static function utcDateStrOrNull(?\DateTimeInterface $date): ?string
	{
		if (is_null($date)) {
			return null;
		}

		if ($date->getOffset() !== 0) {
			if ($date instanceof \DateTimeImmutable) {
			} elseif ($date instanceof \DateTime) {
				$date = clone $date;
			} else {
				$date = \DateTime::createFromInterface($date);
			}
			$date = $date->setTimezone(self::getUTC());
		}

		// ミリ秒部分は使用しない (そこまで精度は必要ないため)
		return $date->format('Y-m-d H:i:s');
	}

	public static function dbDateStrToDateTime(?string $dateStr): ?JsonDateTime
	{
		if (is_null($dateStr)) {
			return null;
		}

		// ミリ秒部分は使用しない (そこまで精度は必要ないため)
		$date = \DateTime::createFromFormat('Y-m-d H:i:s', $dateStr, self::getUTC());
		if ($date === false) {
			throw new \Exception("Invalid date string: $dateStr");
		}
		return new JsonDateTime($date);
	}

	/**
	 * H6: true when the most recent DateTime::createFromFormat() emitted a
	 * warning (PHP normalizes out-of-range components instead of failing) or a
	 * non-fatal error. PHP >= 8.2 returns false from getLastErrors() on a clean
	 * parse; older PHP returns an array of zero counts — handle both.
	 */
	private static function hasDateParseWarning(): bool
	{
		$errors = \DateTime::getLastErrors();
		if ($errors === false) {
			return false;
		}
		return ($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0;
	}
	public static function fromJsonDateStrToDateTime(mixed $dateStr): ?\DateTime
	{
		if (is_null($dateStr) || !is_string($dateStr) || empty($dateStr)) {
			return null;
		}

		if (str_ends_with($dateStr, 'Z')) {
			$dateStr = substr($dateStr, 0, -1) . '+00:00';
		}
		$date = false;
		$date = \DateTime::createFromFormat(
			str_contains($dateStr, '.') ? 'Y-m-d\TH:i:s.uP' : \DateTime::ATOM,
			$dateStr,
			self::getUTC(),
		);
		if ($date === false || self::hasDateParseWarning()) {
			// H6: createFromFormat silently NORMALIZES out-of-range components
			// (e.g. "2024-13-45T99:99:99Z" -> 2025-02-18T...), which would let
			// garbage datetimes through as a different valid date — critically
			// for invite-key valid_from/expires_at (authorization window).
			// Reject when the parser emitted a warning.
			return null;
		}
		return $date;
	}
	public static function fromJsonDateOnlyStrToDateTime(mixed $dateStr): ?\DateTime
	{
		if (is_null($dateStr) || !is_string($dateStr) || empty($dateStr)) {
			return null;
		}

		if (str_contains($dateStr, ':')) {
			$date = self::fromJsonDateStrToDateTime($dateStr);
		} else {
			$date = \DateTime::createFromFormat('Y-m-d', $dateStr, self::getUTC());
			if ($date === false || self::hasDateParseWarning()) {
				// H6: reject normalized out-of-range date components.
				return null;
			}
		}
		return $date?->setTime(0, 0, 0, 0);
	}

	public static function getValue(mixed $d, string $key): mixed
	{
		if (is_object($d)) {
			if (property_exists($d, $key)) {
				return $d->{$key};
			}
		} elseif (is_array($d)) {
			if (array_key_exists($key, $d)) {
				return $d[$key];
			}
		}
		return false;
	}
	public static function getValueOrNull(mixed $d, string $key): mixed
	{
		$ret = self::getValue($d, $key);
		return $ret === false ? null : $ret;
	}

	/**
	 * Like getValueOrNull, but does NOT collapse a literal `false` to null.
	 * getValue() returns the bool `false` both for an absent key AND for a
	 * present `false` value, so getValueOrNull can't tell them apart — fine for
	 * most fields, but it silently nulls a present `is_pass: false` etc., which
	 * then violates the NOT NULL boolean columns on UPDATE. Use this for those
	 * fields: it preserves `false` and only yields null when the key is truly
	 * absent (or explicitly null).
	 */
	public static function getBoolValueOrNull(mixed $d, string $key): ?bool
	{
		if (is_object($d)) {
			if (!property_exists($d, $key)) {
				return null;
			}
			$v = $d->{$key};
		} elseif (is_array($d)) {
			if (!array_key_exists($key, $d)) {
				return null;
			}
			$v = $d[$key];
		} else {
			return null;
		}
		return is_null($v) ? null : (bool)$v;
	}

	/**
	 * @param array<string> $keys
	 * @return array<string, string|int>
	 */
	public static function getArrayForUpdateSource(
		array $keys,
		array|object $requestBody,
		object $getDataResult,
	): array {
		$checkPropExists = is_array($requestBody)
		? (fn(string $key): bool => array_key_exists($key, $requestBody))
		: (fn(string $key): bool => property_exists($requestBody, $key))
		;

		$kvpArray = [];
		foreach ($keys as $key) {
			if ($checkPropExists($key) && property_exists($getDataResult, $key)) {
				$value = $getDataResult->{$key};
				if ($value instanceof \DateTimeInterface) {
					$value = self::utcDateStrOrNull($value);
				} elseif ($value instanceof \BackedEnum) {
					$value = $value->value;
				}
				$kvpArray[$key] = $value;
			}
		}
		return $kvpArray;
	}

	public static function floatvalOrNull(mixed $value): ?float
	{
		return is_null($value) ? null : floatval($value);
	}

	/**
	 * L8: strict inbound UUID validator — accepts ONLY canonical lowercase
	 * RFC 4122 / RFC 9562 form (8-4-4-4-12, all hex lowercase).
	 *
	 * Ramsey\Uuid\Uuid::isValid() is intentionally permissive: it also
	 * accepts UPPERCASE, {brace}, urn:uuid: prefix, and 32-char no-dash forms.
	 * This helper rejects all of those, enforcing what the API actually
	 * promises clients and protecting against sentinel spoofing.
	 *
	 * Version nibble: [1-8] covers RFC 9562 versions v1–v8 (v7 is what this
	 * system generates; v4 is common in legacy data; v6/v8 are emerging).
	 * Digit 0 is the NIL UUID's version — rejecting it here also blocks NIL,
	 * which is Constants::getUuidNull(), a sentinel that must never arrive on
	 * the wire. The variant nibble [89ab] (RFC 4122 variant 1) independently
	 * rejects NIL (NIL has variant 0) for defense-in-depth.
	 *
	 * NO /i flag — uppercase rejection is the whole point; a future
	 * "harmless" /i addition would silently revert L8.
	 */
	private const UUID_STRICT_REGEX =
		'/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/';

	public static function isStrictUuid(mixed $value): bool
	{
		return is_string($value) && preg_match(self::UUID_STRICT_REGEX, $value) === 1;
	}

	// 権限チェック失敗時など、対象エンティティ種別に依存しない汎用 NotFound。
	// メッセージは意図的にエンティティ名を含めない (存在情報を漏らさないため・
	// MyServiceBase の汎用権限チェックは具象エンティティ種別を知らないため)。
	// 安易にエンティティ別ヘルパへ「改善」しないこと。
	public static function errContentNotFound(): RetValueOrError
	{
		return RetValueOrError::withError(Constants::HTTP_NOT_FOUND, "Content not found");
	}
	public static function errWorkGroupNotFound(): RetValueOrError
	{
		return RetValueOrError::withError(Constants::HTTP_NOT_FOUND, "WorkGroup not found");
	}
	public static function errWorkNotFound(): RetValueOrError
	{
		return RetValueOrError::withError(Constants::HTTP_NOT_FOUND, "Work not found");
	}
	public static function errStationNotFound(): RetValueOrError
	{
		return RetValueOrError::withError(Constants::HTTP_NOT_FOUND, "Station not found");
	}
	public static function errStationTrackNotFound(): RetValueOrError
	{
		return RetValueOrError::withError(Constants::HTTP_NOT_FOUND, "Station Track not found");
	}
	public static function errTrainNotFound(): RetValueOrError
	{
		return RetValueOrError::withError(Constants::HTTP_NOT_FOUND, "Train not found");
	}
	public static function errTimetableRowNotFound(): RetValueOrError
	{
		return RetValueOrError::withError(Constants::HTTP_NOT_FOUND, "TimetableRow not found");
	}
	public static function errProjectNotFound(): RetValueOrError
	{
		return RetValueOrError::withError(Constants::HTTP_NOT_FOUND, "Project not found");
	}
	public static function errLineNotFound(): RetValueOrError
	{
		return RetValueOrError::withError(Constants::HTTP_NOT_FOUND, "Line not found");
	}
	public static function errStationOnLineNotFound(): RetValueOrError
	{
		return RetValueOrError::withError(Constants::HTTP_NOT_FOUND, "StationOnLine not found");
	}
	public static function errStopPatternNotFound(): RetValueOrError
	{
		return RetValueOrError::withError(Constants::HTTP_NOT_FOUND, "StopPattern not found");
	}
	public static function errStopPatternRowNotFound(): RetValueOrError
	{
		return RetValueOrError::withError(Constants::HTTP_NOT_FOUND, "StopPatternRow not found");
	}
	public static function errColorNotFound(): RetValueOrError
	{
		return RetValueOrError::withError(Constants::HTTP_NOT_FOUND, "Color not found");
	}
	/**
	 * Privilege-tier rejection on a NON-GET (mutating) request: the caller can
	 * SEE the resource (so 404 would be a lie) but lacks the higher tier the
	 * operation needs. Kept entity-agnostic so it never leaks which field/level
	 * was missing — same non-leak philosophy as errContentNotFound().
	 *
	 * GET-side and non-member rejections stay 404 (errXxxNotFound) so existence
	 * is never disclosed; this 403 is reserved for read-capable members.
	 */
	public static function errForbidden(): RetValueOrError
	{
		return RetValueOrError::withError(Constants::HTTP_FORBIDDEN, "Forbidden");
	}
}
