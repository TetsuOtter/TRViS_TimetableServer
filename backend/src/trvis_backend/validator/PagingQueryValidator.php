<?php

namespace dev_t0r\trvis_backend\validator;

use dev_t0r\trvis_backend\Constants;
use dev_t0r\trvis_backend\RetValueOrError;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

final class PagingQueryValidator
{
	public readonly bool $isError;
	private function __construct(
		public readonly int $pageFrom1,
		public readonly int $perPage,
		public readonly ?UuidInterface $topId,
		public readonly RetValueOrError $reqError,
	) {
		$this->isError = $reqError->isError;
	}

	private static function withErr(string $msg): self {
		return new self(
			pageFrom1: Constants::PAGE_DEFAULT_VALUE,
			perPage: Constants::PER_PAGE_DEFAULT_VALUE,
			topId: null,
			reqError: RetValueOrError::withBadReq($msg),
		);
	}
	public static function withRequest(
		ServerRequestInterface $request,
		LoggerInterface $logger,
	): self {
		$queryParams = $request->getQueryParams();
		$hasP = key_exists('p', $queryParams);
		$hasLimit = key_exists('limit', $queryParams);
		$hasTop = key_exists('top', $queryParams);
		$p = $hasP
			? $queryParams['p']
			: Constants::PAGE_DEFAULT_VALUE;
		$limit = $hasLimit
			? $queryParams['limit']
			: Constants::PER_PAGE_DEFAULT_VALUE;
		$top = $hasTop
			? $queryParams['top']
			: null;

		if ($hasP)
		{
			if (!is_numeric($p))
			{
				$logger->warning("Invalid number format (p:{p})", ['p' => $p]);
				return self::withErr("Invalid number format for parameter `p`");
			}

			$p = intval($p);
			if ($p < Constants::PAGE_MIN_VALUE)
			{
				$logger->warning("Value out of range (p:{p})", ['p' => $p]);
				return self::withErr("Value out of range (parameter `p`)");
			}
		}

		if ($hasLimit)
		{
			if (!is_numeric($limit))
			{
				$logger->warning("Invalid number format (limit:{limit})", ['limit' => $limit]);
				return self::withErr("Invalid number format for parameter `limit`");
			}

			$limit = intval($limit);
			if ($limit < Constants::PER_PAGE_MIN_VALUE || Constants::PER_PAGE_MAX_VALUE < $limit)
			{
				$logger->warning("Value out of range (limit:{limit})", ['limit' => $limit]);
				return self::withErr("Value out of range (parameter `limit`)");
			}
		}

		if ($hasTop)
		{
			if (!Uuid::isValid($top)) {
				$logger->warning("Invalid UUID format (top: {top})", ['top' => $top]);
				return self::withErr("Invalid UUID format (parameter `top`)");
			}

			$top = Uuid::fromString($top);
		}

		return new self(
			pageFrom1: $p,
			perPage: $limit,
			topId: $top,
			reqError: RetValueOrError::withValue(null),
		);
	}
}
