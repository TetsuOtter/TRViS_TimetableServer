<?php

namespace dev_t0r\trvis_backend;

final class Utils
{
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

	private const UTC = new \DateTimeZone('UTC');
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
			$date = $date->setTimezone(self::UTC);
		}

		return $date->format('Y-m-d H:i:s.v');
	}

	public static function utcDateStrToDateTime(?string $dateStr): ?\DateTime {
		if (is_null($dateStr)) {
			return null;
		}

		$date = \DateTime::createFromFormat('Y-m-d H:i:s.u', $dateStr, self::UTC);
		if ($date === false) {
			throw new \Exception("Invalid date string: $dateStr");
		}
		return $date;
	}
}
