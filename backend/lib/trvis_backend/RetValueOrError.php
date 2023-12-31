<?php

namespace dev_t0r\trvis_backend;
use Psr\Http\Message\ResponseInterface;

final class RetValueOrError
{
	public readonly bool $isError;
	public readonly mixed $value;
	public readonly int $statusCode;
	public readonly int $errorCode;
	public readonly string $errorMsg;

	private function __construct(
		bool $isError = false,
		mixed $value = null,
		int $statusCode = null,
		string $errorMsg = null,
		int $errorCode = null,
	) {
		$this->isError = $isError;
		$this->value = $value;
		$this->statusCode = $statusCode ?? 200;
		$this->errorCode = $errorCode ?? $this->statusCode;
		$this->errorMsg = $errorMsg;
	}

	public static function withValue(
		mixed $value,
		int $statusCode = null,
	): RetValueOrError {
		return new RetValueOrError(
			value: $value,
			statusCode: $statusCode,
		);
	}
	public static function withError(
		int $statusCode,
		string $errorMsg,
		int $errorCode = null,
	): RetValueOrError {
		return new RetValueOrError(
			isError: true,
			statusCode: $statusCode,
			errorMsg: $errorMsg,
			errorCode: $errorCode,
		);
	}

	public function getResponseWithJson(ResponseInterface $response, int $statusCode = null): ResponseInterface
	{
		if ($this->isError) {
			return Utils::withError($response, $this->statusCode, $this->errorMsg, $this->errorCode);
		} else {
			return Utils::withJson($response, $this->value, $statusCode ?? $this->statusCode);
		}
	}
}
