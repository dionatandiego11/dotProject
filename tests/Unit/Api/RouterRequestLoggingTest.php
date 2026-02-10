<?php

declare(strict_types=1);

namespace DotProject\Core {
    final class LoggerTestBuffer
    {
        /** @var array<int, string> */
        public static array $messages = [];
    }

    function error_log(string $message): bool
    {
        LoggerTestBuffer::$messages[] = $message;
        return true;
    }
}

namespace DotProject\Tests\Unit\Api {
    use DotProject\Api\Request;
    use DotProject\Api\Response;
    use DotProject\Api\Router;
    use DotProject\Core\LoggerTestBuffer;
    use PHPUnit\Framework\TestCase;

    class RouterRequestLoggingTest extends TestCase
    {
        protected function setUp(): void
        {
            LoggerTestBuffer::$messages = [];
        }

        public function testRouterLogsRequestOnceWhenResponseSendReturnsControl(): void
        {
            $request = new class('GET', '/v1/ping') extends Request {
                private string $methodValue;
                private string $uriValue;
                /** @var array<string, mixed> */
                private array $paramsValue = [];

                public function __construct(string $method, string $uri)
                {
                    $this->methodValue = $method;
                    $this->uriValue = $uri;
                }

                public function getMethod(): string
                {
                    return $this->methodValue;
                }

                public function getUri(): string
                {
                    return $this->uriValue;
                }

                /** @return array<string, mixed> */
                public function getParams(): array
                {
                    return $this->paramsValue;
                }

                /** @param array<string, mixed> $params */
                public function setParams(array $params): void
                {
                    $this->paramsValue = $params;
                }
            };

            $response = new class extends Response {
                public int $sendCount = 0;

                public function send(?bool $terminate = null): void
                {
                    $this->sendCount++;
                    $this->fireSendCallbacks();
                }
            };

            $router = new Router($request, $response);
            $router->get('/v1/ping', static function (): array {
                return ['ok' => true];
            });

            $router->run();

            $entries = [];
            foreach (LoggerTestBuffer::$messages as $message) {
                $decoded = json_decode($message, true);
                if (is_array($decoded)) {
                    $entries[] = $decoded;
                }
            }

            $apiRequestEntries = array_values(array_filter(
                $entries,
                static fn(array $entry): bool => ($entry['message'] ?? null) === 'API Request'
            ));

            $this->assertSame(1, $response->sendCount);
            $this->assertCount(1, $apiRequestEntries);

            $context = $apiRequestEntries[0]['context'] ?? [];
            $this->assertSame('GET', $context['method'] ?? null);
            $this->assertSame('/v1/ping', $context['uri'] ?? null);
            $this->assertSame(Response::HTTP_OK, (int) ($context['status_code'] ?? 0));
            $this->assertGreaterThanOrEqual(0, (int) ($context['duration_ms'] ?? -1));
        }
    }
}
