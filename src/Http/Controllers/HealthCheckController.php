<?php

namespace Native\Desktop\Http\Controllers;

class HealthCheckController
{
    public function __invoke()
    {
        return response()->json([
            'success' => true,
        ]);
    }
}
