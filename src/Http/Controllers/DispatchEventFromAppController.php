<?php

namespace Native\Desktop\Http\Controllers;

use Illuminate\Http\Request;
use Native\Desktop\Events\Contracts\DispatchableFromNative;
use Native\Desktop\Support\NativeEventSignature;

class DispatchEventFromAppController
{
    public function __invoke(Request $request)
    {
        $event = $request->input('event');
        $payload = $request->input('payload', []);

        // Script in the app's web view can POST here (the `api` routes carry no
        // middleware beyond the shared-secret cookie, which same-origin page
        // script sends automatically), so a page could forge a native event —
        // firing the app's listeners as if the shell sent it. Once the shell
        // signs its events, require a valid signature and drop anything a page
        // fabricates. The marker gate below still runs as defense-in-depth on
        // which classes may be constructed at all.
        if (NativeEventSignature::enforced() && ! NativeEventSignature::verify($request)) {
            return response()->json([
                'success' => false,
            ], 403);
        }

        if (is_string($event) && class_exists($event)) {
            // This endpoint constructs the named class and fires it. The class
            // must be one the native side is allowed to dispatch — every
            // desktop event implements DispatchableFromNative — or a page could
            // name any autoloadable class and hand it constructor arguments of
            // its own, an object-injection gadget (e.g. SplFileObject to
            // truncate a file).
            if (! is_subclass_of($event, DispatchableFromNative::class)) {
                return response()->json([
                    'success' => false,
                ], 403);
            }

            $event = new $event(...$payload);
            event($event);
        } else {
            event($event, $payload);
        }

        return response()->json([
            'success' => true,
        ]);
    }
}
