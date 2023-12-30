<?php

/**
 * TRViS用 時刻表管理用API
 * PHP version 7.4
 *
 * @package dev_t0r
 * @author  OpenAPI Generator team
 * @link    https://github.com/openapitools/openapi-generator
 */

/**
 * No description provided (generated by Openapi Generator https://github.com/openapitools/openapi-generator)
 * The version of the OpenAPI document: 1.0.0
 * Generated by: https://github.com/openapitools/openapi-generator.git
 */

/**
 * NOTE: This class is auto generated by the openapi generator program.
 * https://github.com/openapitools/openapi-generator
 * Please update the test case below to test the model.
 */
namespace dev_t0r\trvis_backend\model;

use PHPUnit\Framework\TestCase;
use dev_t0r\trvis_backend\model\TokenRequest;

/**
 * TokenRequestTest Class Doc Comment
 *
 * @package dev_t0r\trvis_backend\model
 * @author  OpenAPI Generator team
 * @link    https://github.com/openapitools/openapi-generator
 *
 * @coversDefaultClass \dev_t0r\trvis_backend\model\TokenRequest
 */
class TokenRequestTest extends TestCase
{

    /**
     * Setup before running any test cases
     */
    public static function setUpBeforeClass(): void
    {
    }

    /**
     * Setup before running each test case
     */
    public function setUp(): void
    {
    }

    /**
     * Clean up after running each test case
     */
    public function tearDown(): void
    {
    }

    /**
     * Clean up after running all test cases
     */
    public static function tearDownAfterClass(): void
    {
    }

    /**
     * Test "TokenRequest"
     */
    public function testTokenRequest()
    {
        $testTokenRequest = new TokenRequest();
        $namespacedClassname = TokenRequest::getModelsNamespace() . '\\TokenRequest';
        $this->assertSame('\\' . TokenRequest::class, $namespacedClassname);
        $this->assertTrue(
            class_exists($namespacedClassname),
            sprintf('Assertion failed that "%s" class exists', $namespacedClassname)
        );
        $this->markTestIncomplete(
            'Test of "TokenRequest" model has not been implemented yet.'
        );
    }

    /**
     * Test attribute "apiKey"
     */
    public function testPropertyApiKey()
    {
        $this->markTestIncomplete(
            'Test of "apiKey" property in "TokenRequest" model has not been implemented yet.'
        );
    }

    /**
     * Test attribute "clientId"
     */
    public function testPropertyClientId()
    {
        $this->markTestIncomplete(
            'Test of "clientId" property in "TokenRequest" model has not been implemented yet.'
        );
    }

    /**
     * Test getOpenApiSchema static method
     * @covers ::getOpenApiSchema
     */
    public function testGetOpenApiSchema()
    {
        $schemaArr = TokenRequest::getOpenApiSchema();
        $this->assertIsArray($schemaArr);
    }
}