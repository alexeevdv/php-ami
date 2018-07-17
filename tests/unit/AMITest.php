<?php

namespace tests\unit;

use alexeevdv\ami\AMI;
use Codeception\Stub;
use Codeception\Stub\Expected;
use Exception;

/**
 * Class AMITest
 * @package tests\unit
 */
class AMITest extends \Codeception\Test\Unit
{
    /**
     * @throws Exception
     * @test
     */
    public function SetVar()
    {
        $ami = Stub::makeEmptyExcept(AMI::class, 'SetVar', [
            'sendRequest' => Expected::once(function ($action, $params) {
                $this->assertEquals('SetVar', $action);
                $expectedParams = [
                    'Channel' => 'channel',
                    'Variable' => 'variable',
                    'Value' => 'value',
                ];
                $this->assertEquals($expectedParams, $params);
            })
        ], $this);
        $ami->SetVar('channel', 'variable', 'value');
    }

    /**
     * @throws Exception
     * @test
     */
    public function StopMonitor()
    {
        $ami = Stub::makeEmptyExcept(AMI::class, 'StopMonitor', [
            'sendRequest' => Expected::once(function ($action, $params) {
                $this->assertEquals('StopMonitor', $action);
                $expectedParams = ['Channel' => 'channel'];
                $this->assertEquals($expectedParams, $params);
            })
        ], $this);
        $ami->StopMonitor('channel');
    }

    /**
     * @throws Exception
     * @test
     */
    public function ZapDialOffhook()
    {
        $ami = Stub::makeEmptyExcept(AMI::class, 'ZapDialOffhook', [
            'sendRequest' => Expected::once(function ($action, $params) {
                $this->assertEquals('ZapDialOffhook', $action);
                $expectedParams = ['ZapChannel' => 'channel', 'Number' => 123];
                $this->assertEquals($expectedParams, $params);
            })
        ], $this);
        $ami->ZapDialOffhook('channel', 123);
    }

    /**
     * @throws Exception
     * @test
     */
    public function ZapDNDoff()
    {
        $ami = Stub::makeEmptyExcept(AMI::class, 'ZapDNDoff', [
            'sendRequest' => Expected::once(function ($action, $params) {
                $this->assertEquals('ZapDNDoff', $action);
                $expectedParams = ['ZapChannel' => 'channel'];
                $this->assertEquals($expectedParams, $params);
            })
        ], $this);
        $ami->ZapDNDoff('channel');
    }

    /**
     * @throws Exception
     * @test
     */
    public function Queues()
    {
        $ami = Stub::makeEmptyExcept(AMI::class, 'Queues', [
            'sendRequest' => Expected::once(function ($action, $params) {
                $this->assertEquals('Queues', $action);
                $expectedParams = [];
                $this->assertEquals($expectedParams, $params);
            })
        ], $this);
        $ami->Queues();
    }

    /**
     * @throws Exception
     * @test
     */
    public function Redirect()
    {
        $ami = Stub::makeEmptyExcept(AMI::class, 'Redirect', [
            'sendRequest' => Expected::once(function ($action, $params) {
                $this->assertEquals('Redirect', $action);
                $expectedParams = [
                    'Channel' => 'a',
                    'ExtraChannel' => 'b',
                    'Exten' => 'c',
                    'Context' => 'd',
                    'Priority' => 'e',
                ];
                $this->assertEquals($expectedParams, $params);
            })
        ], $this);
        $ami->Redirect('a', 'b', 'c', 'd', 'e');
    }

    /**
     * @throws Exception
     * @test
     */
    public function AbsoluteTimeout()
    {
        $ami = Stub::makeEmptyExcept(AMI::class, 'AbsoluteTimeout', [
            'sendRequest' => Expected::once(function ($action, $params) {
                $this->assertEquals('AbsoluteTimeout', $action);
                $expectedParams = [
                    'Channel' => 'a',
                    'Timeout' => 'b',
                ];
                $this->assertEquals($expectedParams, $params);
            })
        ], $this);
        $ami->AbsoluteTimeout('a', 'b');
    }

    /**
     * @throws Exception
     * @test
     */
    public function ChangeMonitor()
    {
        $ami = Stub::makeEmptyExcept(AMI::class, 'ChangeMonitor', [
            'sendRequest' => Expected::once(function ($action, $params) {
                $this->assertEquals('ChangeMonitor', $action);
                $expectedParams = [
                    'Channel' => 'a',
                    'File' => 'b',
                ];
                $this->assertEquals($expectedParams, $params);
            })
        ], $this);
        $ami->ChangeMonitor('a', 'b');
    }

    /**
     * @throws Exception
     * @test
     */
    public function Events()
    {
        $ami = Stub::makeEmptyExcept(AMI::class, 'Events', [
            'sendRequest' => Expected::once(function ($action, $params) {
                $this->assertEquals('Events', $action);
                $expectedParams = [
                    'EventMask' => 'a',
                ];
                $this->assertEquals($expectedParams, $params);
            })
        ], $this);
        $ami->Events('a');
    }
}
