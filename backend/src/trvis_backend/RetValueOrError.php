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
		int $statusCode = null,
		string $errorMsg = null,
		int $errorCode = null,
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
		int $statusCode = null,
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
	 */
	public static function withTotalCount(
		RetValueOrError $value,
		RetValueOrError $totalCount,
		int $statusCode = null,
	): self {
		$totalCountValue = $totalCount->isError ? null : $totalCount->value;
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
		int $errorCode = null,
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
		int $errorCode = null,
	): self {
		return self::withError(
			statusCode: Constants::HTTP_BAD_REQUEST,
			errorMsg: $errorMsg,
			errorCode: $errorCode,
		);
	}

	public function getResponseWithJson(ResponseInterface $response, int $statusCode = null): ResponseInterface
	{
		if ($this->isError) {
			return Utils::withError($response, $this->statusCode, $this->errorMsg, $this->errorCode);
		} else if (!is_null($this->value)) {
			if (!is_null($this->totalCount)) {
				$response = $response->withHeader(Constants::HEADER_TOTAL_COUNT, $this->totalCount);
			}

			return Utils::withJson($response, $this->value, $statusCode ?? $this->statusCode);
		} else {
			return $response->withStatus($this->statusCode);
		}
	}
}
