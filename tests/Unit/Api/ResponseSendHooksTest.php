<?php

declare(strict_types=1);

namespace DotProject\Tests\Unit\Api;

use DotProject\Api\Response;
use PHPUnit\Framework\TestCase;

class ResponseSendHooksTest extends TestCase
{
    public function testOnSendCallbackIsExecutedWhenResponseIsSent(): void
    {
        $response = new class extends Response {
            public int $sendCount = 0;

            public function send(?bool $terminate = null): void
            {
                $this->sendCount++;
                $this->fireSendCallbacks();
            }
        };

        $receivedStatusCode = null;
        $callbackCalls = 0;

        $response
            ->json(['ok' => true], Response::HTTP_CREATED)
            ->onSend(function (Response $sentResponse) use (&$receivedStatusCode, &$callbackCalls): void {
                $callbackCalls++;
                $receivedStatusCode = $sentResponse->getStatusCode();
            });

        $response->send();

        $this->assertSame(1, $response->sendCount);
        $this->assertSame(1, $callbackCalls);
        $this->assertSame(Response::HTTP_CREATED, $receivedStatusCode);
    }
}
