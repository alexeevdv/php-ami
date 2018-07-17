<?php

namespace tests\unit;

use alexeevdv\ami\AMIRequest;

/**
 * Class AMIRequestTest
 * @package tests\unit
 */
class AMIRequestTest extends \Codeception\Test\Unit
{
    /**
     * @test
     */
    public function successfulInstantiation()
    {
        new AMIRequest('whatever', ['whatever' => 'else']);
    }

    /**
     * @test
     */
    public function testToString()
    {
        $expectedString = "Action: whatever\r\n"
            . "whatever: else\r\n"
            . "\r\n";
        $request = new AMIRequest('whatever', ['whatever' => 'else']);
        $this->assertEquals($expectedString, $request->__toString());
    }
}
