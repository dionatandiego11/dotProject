<?php
/**
 * Event Dispatcher Test
 * 
 * Unit tests for the EventDispatcher class.
 * 
 * @package DotProject\Tests\Unit\Core
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace DotProject\Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use DotProject\Core\Event;
use DotProject\Core\EventDispatcher;

/**
 * @covers \DotProject\Core\EventDispatcher
 * @covers \DotProject\Core\Event
 */
class EventDispatcherTest extends TestCase
{
    private EventDispatcher $dispatcher;

    protected function setUp(): void
    {
        EventDispatcher::resetInstance();
        $this->dispatcher = EventDispatcher::getInstance();
    }

    protected function tearDown(): void
    {
        EventDispatcher::resetInstance();
    }

    /**
     * Test singleton pattern
     */
    public function testGetInstanceReturnsSameInstance(): void
    {
        $instance1 = EventDispatcher::getInstance();
        $instance2 = EventDispatcher::getInstance();

        $this->assertSame($instance1, $instance2);
    }

    /**
     * Test registering a listener
     */
    public function testOnRegistersListener(): void
    {
        $called = false;
        $this->dispatcher->on('test.event', function () use (&$called) {
            $called = true;
        });

        $this->assertTrue($this->dispatcher->hasListeners('test.event'));
    }

    /**
     * Test dispatching an event calls listener
     */
    public function testDispatchCallsListener(): void
    {
        $called = false;
        $this->dispatcher->on('test.event', function () use (&$called) {
            $called = true;
        });

        $this->dispatcher->dispatch('test.event');

        $this->assertTrue($called);
    }

    /**
     * Test listener receives event object
     */
    public function testListenerReceivesEvent(): void
    {
        $receivedEvent = null;
        $this->dispatcher->on('test.event', function (Event $event) use (&$receivedEvent) {
            $receivedEvent = $event;
        });

        $event = new Event('test.event', ['key' => 'value']);
        $this->dispatcher->dispatch('test.event', $event);

        $this->assertInstanceOf(Event::class, $receivedEvent);
        $this->assertSame('value', $receivedEvent->get('key'));
    }

    /**
     * Test multiple listeners are called
     */
    public function testMultipleListenersAreCalled(): void
    {
        $calls = [];

        $this->dispatcher->on('test.event', function () use (&$calls) {
            $calls[] = 'first';
        });

        $this->dispatcher->on('test.event', function () use (&$calls) {
            $calls[] = 'second';
        });

        $this->dispatcher->dispatch('test.event');

        $this->assertCount(2, $calls);
    }

    /**
     * Test listener priority
     */
    public function testListenerPriority(): void
    {
        $calls = [];

        $this->dispatcher->on('test.event', function () use (&$calls) {
            $calls[] = 'low';
        }, 0);

        $this->dispatcher->on('test.event', function () use (&$calls) {
            $calls[] = 'high';
        }, 100);

        $this->dispatcher->dispatch('test.event');

        $this->assertSame(['high', 'low'], $calls);
    }

    /**
     * Test stopping propagation
     */
    public function testStopPropagation(): void
    {
        $calls = [];

        $this->dispatcher->on('test.event', function (Event $event) use (&$calls) {
            $calls[] = 'first';
            $event->stopPropagation();
        }, 100);

        $this->dispatcher->on('test.event', function () use (&$calls) {
            $calls[] = 'second';
        }, 0);

        $this->dispatcher->dispatch('test.event');

        $this->assertSame(['first'], $calls);
    }

    /**
     * Test removing a listener
     */
    public function testOffRemovesListener(): void
    {
        $called = false;
        $listener = function () use (&$called) {
            $called = true;
        };

        $this->dispatcher->on('test.event', $listener);
        $this->dispatcher->off('test.event', $listener);
        $this->dispatcher->dispatch('test.event');

        $this->assertFalse($called);
    }

    /**
     * Test removeAllListeners
     */
    public function testRemoveAllListeners(): void
    {
        $this->dispatcher->on('test.event', fn() => null);
        $this->dispatcher->on('test.event', fn() => null);

        $this->dispatcher->removeAllListeners('test.event');

        $this->assertFalse($this->dispatcher->hasListeners('test.event'));
    }

    /**
     * Test hasListeners returns false for no listeners
     */
    public function testHasListenersReturnsFalseForNoListeners(): void
    {
        $this->assertFalse($this->dispatcher->hasListeners('nonexistent.event'));
    }

    /**
     * Test getListeners returns all listeners
     */
    public function testGetListenersReturnsListeners(): void
    {
        $listener1 = fn() => null;
        $listener2 = fn() => null;

        $this->dispatcher->on('test.event', $listener1);
        $this->dispatcher->on('test.event', $listener2);

        $listeners = $this->dispatcher->getListeners('test.event');

        $this->assertCount(2, $listeners);
    }

    /**
     * Test dispatch returns event
     */
    public function testDispatchReturnsEvent(): void
    {
        $event = new Event('test.event', ['key' => 'value']);
        $returned = $this->dispatcher->dispatch('test.event', $event);

        $this->assertSame($event, $returned);
    }

    /**
     * Test dispatch without event creates one
     */
    public function testDispatchWithoutEventCreatesOne(): void
    {
        $returned = $this->dispatcher->dispatch('test.event');

        $this->assertInstanceOf(Event::class, $returned);
        $this->assertSame('test.event', $returned->getName());
    }

    /**
     * Test event logging
     */
    public function testEventLogging(): void
    {
        $this->dispatcher->setLogging(true);
        $this->dispatcher->dispatch('test.event');
        $this->dispatcher->dispatch('another.event');

        $log = $this->dispatcher->getDispatchedEvents();

        $this->assertCount(2, $log);
        $this->assertSame('test.event', $log[0]['name']);
        $this->assertSame('another.event', $log[1]['name']);
    }

    /**
     * Test clear log
     */
    public function testClearLog(): void
    {
        $this->dispatcher->setLogging(true);
        $this->dispatcher->dispatch('test.event');
        $this->dispatcher->clearLog();

        $log = $this->dispatcher->getDispatchedEvents();

        $this->assertEmpty($log);
    }
}
