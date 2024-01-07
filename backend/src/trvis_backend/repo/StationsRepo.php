<?php

namespace dev_t0r\trvis_backend\repo;

use PDO;
use Psr\Log\LoggerInterface;

final class StationsRepo
{
	public function __construct(
		private readonly PDO $db,
		private readonly LoggerInterface $logger,
	) {
	}
}
