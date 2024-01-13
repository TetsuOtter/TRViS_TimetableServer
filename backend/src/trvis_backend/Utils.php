<?php

namespace dev_t0r\trvis_backend;

use dev_t0r\trvis_backend\model\JsonDateTime;

Utils::__init__();

final class Utils
{
	private static \DateTimeZone $UTC;
	public static function __init__()
	{
		self::$UTC = new \DateTimeZone('UTC');
	}

	public static function getUTC(): \DateTimeZone
	{
		return self::$UTC;
	}

	public static function getUtcNow(): \DateTime
	{
		return new \DateTime('now', self::$UTC);
	}

	public static function withJson(
		\Psr\Http\Message\ResponseInterface $oldResponse,
		mixed $data,
		int $statusCode = 200,
	): \Psr\Http\Message\ResponseInterface {
		$response = $oldResponse
			->withHeader('Content-Type', 'application/json')
			->withStatus($statusCode)
		;
		$response->getBody()->write(json_encode($data));
		return $response;
	}

	public static function withError(
		\Psr\Http\Message\ResponseInterface $oldResponse,
		int $statusCode,
		string $message,
		int $errorCode = null,
	): \Psr\Http\Message\ResponseInterface {
		if ($errorCode === null) {
			$errorCode = $statusCode;
		}

		return self::withJson(
			$oldResponse, [
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

	public static function utcDateStrOrNull(?\DateTimeInterface $date): ?string {
		if (is_null($date)) {
			return null;
		}

		if ($date->getOffset() !== 0) {
			if ($date instanceof \DateTimeImmutable) {}
			else if ($date instanceof \DateTime)
				$date = clone $date;
			else
				$date = \DateTime::createFromInterface($date);
			$date = $date->setTimezone(self::$UTC);
		}

		// ミリ秒部分は使用しない (そこまで精度は必要ないため)
		return $date->format('Y-m-d H:i:s');
	}

	public static function dbDateStrToDateTime(?string $dateStr): ?JsonDateTime {
		if (is_null($dateStr)) {
			return null;
		}

		// ミリ秒部分は使用しない (そこまで精度は必要ないため)
		$date = \DateTime::createFromFormat('Y-m-d H:i:s', $dateStr, self::$UTC);
		if ($date === false) {
			throw new \Exception("Invalid date string: $dateStr");
		}
		return new JsonDateTime($date);
	}

	public static function fromJsonDateStrToDateTime(mixed $dateStr): ?\DateTime {
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
			self::$UTC,
		);
		if ($date === false) {
			return null;
		}
		return $date;
	}
	public static function fromJsonDateOnlyStrToDateTime(mixed $dateStr): ?\DateTime {
		if (is_null($dateStr) || !is_string($dateStr) || empty($dateStr)) {
			return null;
		}

		if (str_contains($dateStr, ':')) {
			$date = self::fromJsonDateStrToDateTime($dateStr);
		} else {
			$date = \DateTime::createFromFormat('Y-m-d', $dateStr, self::$UTC);
			if ($date === false) {
				return null;
			}
		}
		return $date?->setTime(0, 0, 0, 0);
	}

	public static function getValue(mixed $d, string $key): mixed {
		if (is_object($d)) {
			if (property_exists($d, $key)) {
				return $d->{$key};
			}
		} else if (is_array($d)) {
			if (array_key_exists($key, $d)) {
				return $d[$key];
			}
		}
		return false;
	}
	public static function getValueOrNull(mixed $d, string $key): mixed {
		$ret = self::getValue($d, $key);
		return $ret === false ? null : $ret;
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
				} else if ($value instanceof \BackedEnum) {
					$value = $value->value;
				}
				$kvpArray[$key] = $value;
			}
		}
		return $kvpArray;
	}

	public static function floatvalOrNull(mixed $value): ?float {
		return is_null($value) ? null : floatval($value);
	}

	public static function errWorkGroupNotFound(): RetValueOrError {
		return RetValueOrError::withError(Constants::HTTP_NOT_FOUND, "WorkGroup not found");
	}
	public static function errWorkNotFound(): RetValueOrError {
		return RetValueOrError::withError(Constants::HTTP_NOT_FOUND, "Work not found");
	}
	public static function errStationNotFound(): RetValueOrError {
		return RetValueOrError::withError(Constants::HTTP_NOT_FOUND, "Station not found");
	}
	public static function errStationTrackNotFound(): RetValueOrError {
		return RetValueOrError::withError(Constants::HTTP_NOT_FOUND, "Station Track not found");
	}
	public static function errTrainNotFound(): RetValueOrError {
		return RetValueOrError::withError(Constants::HTTP_NOT_FOUND, "Train not found");
	}
	public static function errTimetableRowNotFound(): RetValueOrError {
		return RetValueOrError::withError(Constants::HTTP_NOT_FOUND, "TimetableRow not found");
	}
}
