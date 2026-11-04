<?php

use Illuminate\Support\Facades\Event;
use Native\Desktop\Events\Contracts\DispatchableFromNative;

// Stands in for a real native event: the controller only constructs classes the
// native side is allowed to dispatch, so a test event must opt in the same way.
class TestEvent implements DispatchableFromNative
{
    public function __construct(public string $test = '') {}
}

// A class the native side is NOT allowed to dispatch — no marker interface.
class UnmarkedEvent
{
    public function __construct(public string $test = '') {}
}

it('dispatches an event', function () {
    Event::fake();

    $this->withoutMiddleware()
        ->post('_native/api/events', [
            'event' => TestEvent::class,
        ])
        ->assertOk();

    Event::assertDispatched(TestEvent::class);
});

// Since 45b7ccfcb86ebf35be35c1eb7fbb9f05a224448f nonexistent classes are handled as string events
it('dispatches a string event', function () {
    Event::fake();

    $this->withoutMiddleware()
        ->post('_native/api/events', [
            'event' => 'some-event-that-is-no-class',
        ]);

    Event::assertDispatched('some-event-that-is-no-class');
});

it('passes the payload to the event', function () {
    Event::fake();

    $this->withoutMiddleware()
        ->post('_native/api/events', [
            'event' => TestEvent::class,
            'payload' => [
                'test' => 'Some payload string',
            ],
        ]);

    Event::assertDispatched(TestEvent::class, function ($event) {
        return $event->test === 'Some payload string';
    });
});

it('refuses to construct a class that is not marked dispatchable (object-injection gate)', function () {
    Event::fake();

    $this->withoutMiddleware()
        ->post('_native/api/events', [
            'event' => UnmarkedEvent::class,
        ])
        ->assertStatus(403)
        ->assertJson(['success' => false]);

    Event::assertNotDispatched(UnmarkedEvent::class);
});

it('refuses an object-injection gadget even with constructor arguments', function () {
    // SplFileObject($path, 'w') would create/truncate a file. It is not a
    // DispatchableFromNative, so the controller must never construct it.
    $target = tempnam(sys_get_temp_dir(), 'nativephp-gadget').'-sentinel';
    file_put_contents($target, 'keep me');

    $this->withoutMiddleware()
        ->post('_native/api/events', [
            'event' => SplFileObject::class,
            'payload' => [$target, 'w'],
        ])
        ->assertStatus(403);

    expect(file_get_contents($target))->toBe('keep me');

    @unlink($target);
});
