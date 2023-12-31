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
}
