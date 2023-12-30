<?php

namespace dev_t0r\trvis_backend;

final class Utils
{
	public static function withJson(
		\Psr\Http\Message\ResponseInterface $oldResponse,
		mixed $data
	): \Psr\Http\Message\ResponseInterface {
		$response = $oldResponse->withHeader('Content-Type', 'application/json');
		$response->getBody()->write(json_encode($data));
		return $response;
	}
}
