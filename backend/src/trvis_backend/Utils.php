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

		return $date->format('Y-m-d H:i:s.v');
	}

	public static function dbDateStrToDateTime(?string $dateStr): ?JsonDateTime {
		if (is_null($dateStr)) {
			return null;
		}

		$date = \DateTime::createFromFormat('Y-m-d H:i:s.u', $dateStr, self::$UTC);
		if ($date === false) {
			throw new \Exception("Invalid date string: $dateStr");
		}
		return new JsonDateTime($date);
	}

	public static function errWorkGroupNotFound(): RetValueOrError {
		return RetValueOrError::withError(Constants::HTTP_NOT_FOUND, "WorkGroup not found");
	}
}
