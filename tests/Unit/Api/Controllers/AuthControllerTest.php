<?php
/**
 * Testes unitários para AuthController
 */

namespace DotProject\Tests\Unit\Api\Controllers;

use PHPUnit\Framework\TestCase;
use DotProject\Api\Controller\AuthController;
use DotProject\Api\Request;
use DotProject\Api\Response;

class AuthControllerTest extends TestCase
{
    public function testControllerCanBeInstantiated(): void
    {
        $request = $this->createMock(Request::class);
        $response = new Response();
        
        $controller = new AuthController($request, $response);
        
        $this->assertInstanceOf(AuthController::class, $controller);
    }
    
    public function testLoginValidationErrors(): void
    {
        $request = $this->createMock(Request::class);
        $request->method('getBodyParam')
            ->willReturnCallback(function ($key) {
                return ['username' => '', 'password' => ''][ $key ] ?? null;
            });
        
        $response = new Response();
        $controller = new AuthController($request, $response);
        
        $result = $controller->login();
        
        $this->assertInstanceOf(Response::class, $result);
    }
}
