<?php

use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;
use Native\Desktop\Events\Contracts\DispatchableFromNative;

/**
 * /_native/api/events has no middleware beyond the shared-secret cookie, which
 * same-origin page script sends automatically, so a page could forge a native
 * event. Once the Electron shell signs its events (NATIVEPHP_EVENTS_SIGNED), the
 * controller must verify an app-key HMAC over a nonce, a timestamp and the exact
 * body, and refuse anything else. These drive the controller over HTTP exactly
 * as the shell (and a forging page) would.
 */

// A marker event with a flexible constructor so a signed, accepted POST builds.
class SignedTestEvent implements DispatchableFromNative
{
    public function __construct(public string $value = '') {}
}

beforeEach(function () {
    // Every lane boots with the app key the shell signs with.
    config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
    app()->forgetInstance('encrypter');
});

afterEach(function () {
    putenv('NATIVEPHP_EVENTS_SIGNED');
});

/** The exact body bytes, the signed headers, and the raw POST — as the shell sends them. */
function postSignedEvent(string $event, array $payload, array $override = []): TestResponse
{
    $body = json_encode(['event' => $event, 'payload' => $payload]);
    $nonce = $override['nonce'] ?? bin2hex(random_bytes(16));
    $timestamp = (string) ($override['timestamp'] ?? time());
    $signedBody = $override['signedBody'] ?? $body;

    $signature = $override['signature'] ?? hash_hmac(
        'sha256',
        "nativephp.native-event\n".$nonce."\n".$timestamp."\n".$signedBody,
        app('encrypter')->getKey(),
    );

    return test()->call('POST', '/_native/api/events', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
        'HTTP_X_NATIVE_EVENT_NONCE' => $nonce,
        'HTTP_X_NATIVE_EVENT_TIMESTAMP' => $timestamp,
        'HTTP_X_NATIVE_EVENT_SIGNATURE' => $signature,
    ], $body);
}

$payload = fn () => ['value' => 'genuine'];

it('lets an unsigned event through while the shell does not sign (back-compat)', function () use ($payload) {
    Event::fake([SignedTestEvent::class]);
    // NATIVEPHP_EVENTS_SIGNED unset → enforcement off.

    test()->postJson('/_native/api/events', ['event' => SignedTestEvent::class, 'payload' => $payload()])
        ->assertOk()->assertJson(['success' => true]);

    Event::assertDispatched(SignedTestEvent::class);
});

it('rejects an unsigned event once the shell signs', function () use ($payload) {
    Event::fake([SignedTestEvent::class]);
    putenv('NATIVEPHP_EVENTS_SIGNED=1');

    test()->postJson('/_native/api/events', ['event' => SignedTestEvent::class, 'payload' => $payload()])
        ->assertStatus(403)->assertJson(['success' => false]);

    Event::assertNotDispatched(SignedTestEvent::class);
});

it('accepts a correctly signed event', function () use ($payload) {
    Event::fake([SignedTestEvent::class]);
    putenv('NATIVEPHP_EVENTS_SIGNED=1');

    postSignedEvent(SignedTestEvent::class, $payload())
        ->assertOk()->assertJson(['success' => true]);

    Event::assertDispatched(SignedTestEvent::class);
});

it('rejects a signature made over a different body (tampered payload)', function () use ($payload) {
    Event::fake([SignedTestEvent::class]);
    putenv('NATIVEPHP_EVENTS_SIGNED=1');

    // Signature computed over the genuine body, but a different body is posted.
    $honest = json_encode(['event' => SignedTestEvent::class, 'payload' => $payload()]);
    postSignedEvent(SignedTestEvent::class, ['value' => 'tampered'], [
        'signedBody' => $honest,
    ])->assertStatus(403);

    Event::assertNotDispatched(SignedTestEvent::class);
});

it('rejects a stale timestamp', function () use ($payload) {
    Event::fake([SignedTestEvent::class]);
    putenv('NATIVEPHP_EVENTS_SIGNED=1');

    postSignedEvent(SignedTestEvent::class, $payload(), ['timestamp' => time() - 120])
        ->assertStatus(403);

    Event::assertNotDispatched(SignedTestEvent::class);
});

it('rejects a replayed nonce', function () use ($payload) {
    Event::fake([SignedTestEvent::class]);
    putenv('NATIVEPHP_EVENTS_SIGNED=1');

    $nonce = bin2hex(random_bytes(16));

    postSignedEvent(SignedTestEvent::class, $payload(), ['nonce' => $nonce])->assertOk();
    // Same nonce again: one-time use, refused.
    postSignedEvent(SignedTestEvent::class, $payload(), ['nonce' => $nonce])->assertStatus(403);

    Event::assertDispatchedTimes(SignedTestEvent::class, 1);
});

it('rejects a signature the app key did not make', function () use ($payload) {
    Event::fake([SignedTestEvent::class]);
    putenv('NATIVEPHP_EVENTS_SIGNED=1');

    postSignedEvent(SignedTestEvent::class, $payload(), ['signature' => str_repeat('0', 64)])
        ->assertStatus(403);

    Event::assertNotDispatched(SignedTestEvent::class);
});

it('refuses an object-injection gadget even with a valid signature (marker gate behind the signature gate)', function () {
    putenv('NATIVEPHP_EVENTS_SIGNED=1');

    $target = tempnam(sys_get_temp_dir(), 'nativephp-gadget').'-sentinel';
    file_put_contents($target, 'keep me');

    // A correctly signed request naming SplFileObject still must not be built:
    // it is not a DispatchableFromNative.
    postSignedEvent(SplFileObject::class, [$target, 'w'])
        ->assertStatus(403);

    expect(file_get_contents($target))->toBe('keep me');

    @unlink($target);
});
