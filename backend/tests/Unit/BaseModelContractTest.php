<?php

namespace dev_t0r\trvis_backend\tests\Unit;

use dev_t0r\trvis_backend\model\ApiInfo;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use InvalidArgumentException;

/**
 * Pins the trimmed BaseModel contract that reused repos/services and
 * tests/Integration/IntegrationTestCase::makeModel depend on. Exercised
 * through a concrete model (ApiInfo). Both OAS props are set where
 * round-tripping is asserted, because getData() omits unset optionals.
 */
#[CoversClass(\dev_t0r\BaseModel::class)]
class BaseModelContractTest extends TestCase
{
	public function testGetOpenApiSchemaShape(): void
	{
		$schema = ApiInfo::getOpenApiSchema();
		$this->assertSame('object', $schema['type']);
		// properties keyed by snake_case prop name, ordered as declared.
		$this->assertSame(
			['server_name', 'version'],
			array_keys($schema['properties']),
			'makeModel() does array_fill_keys(array_keys($schema[properties]))',
		);
		// ApiInfo has no required props -> the "required" key is omitted
		// entirely (an empty required[] is an OpenAPI 3.0 smell).
		$this->assertArrayNotHasKey('required', $schema);
	}

	public function testSetDataGetDataRoundTrip(): void
	{
		$m = new ApiInfo();
		$m->setData(['server_name' => 's', 'version' => 'v']);
		$data = $m->getData();
		$this->assertIsObject($data);
		$this->assertSame('s', $data->server_name);
		$this->assertSame('v', $data->version);
	}

	public function testCreateFromDataAndJsonSerialize(): void
	{
		$m = ApiInfo::createFromData(['server_name' => 's', 'version' => 'v']);
		$this->assertEquals($m->getData(), $m->jsonSerialize());
		$this->assertSame(
			'{"server_name":"s","version":"v"}',
			json_encode($m),
		);
	}

	public function testGetDataOmitsUnsetOptionalProperty(): void
	{
		// Only one optional prop set; the other must be OMITTED (not null),
		// so the JSON body never carries keys the caller didn't provide.
		$m = ApiInfo::createFromData(['server_name' => 'only']);
		$data = $m->getData();
		$this->assertObjectHasProperty('server_name', $data);
		$this->assertObjectNotHasProperty('version', $data);
	}

	public function testSetUnknownPropertyThrows(): void
	{
		$m = new ApiInfo();
		$this->expectException(InvalidArgumentException::class);
		$m->setData(['not_a_real_prop' => 'x']);
	}
}
