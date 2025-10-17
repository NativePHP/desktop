<?php

namespace Native\Desktop\Events;

use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Support\Facades\Event;
use Native\Desktop\Drivers\Electron\ElectronServiceProvider;

class LivewireDispatcher
{
    public function register(): void
    {
        Event::listen(function (RequestHandled $event) {
            $this->handle($event);
        });
    }

    /** Injects assets inside every full-page response */
    public function handle(RequestHandled $handled)
    {
        $identifier = 'NativePHP Livewire Dispatcher';
        $html = $handled->response->getContent();
        $originalContent = $handled->response->original ?? null;

        if (! $originalContent) {
            return;
        }

        if (! $handled->response->isSuccessful()) {
            return;
        }

        // Skip if request doesn't return a full page
        if (! str_contains($html, '</html>')) {
            return;
        }

        // Skip when included before
        if (str_contains($html, "<!--[{$identifier}]-->")) {
            return;
        }

        // Get the JS dispatcher script
        $javascript = file_get_contents(
            ElectronServiceProvider::electronPath('electron-plugin/src/preload/livewire-dispatcher.js')
        );

        $handled->response->setContent(
            $this->inject($html, <<< HTML
            <!--[{$identifier}]-->
            <script type="module">
            {$javascript}
            </script>
            HTML)
        );

        $handled->response->original = $originalContent;
    }

    /** Injects assets into given html string (taken from Livewire's injection mechanism) */
    protected function inject(string $html, string $assets): string
    {
        $html = str($html);
        $assets = PHP_EOL.$assets.PHP_EOL;

        if ($html->test('/<\s*\/\s*head\s*>/i')) {
            return $html
                ->replaceMatches('/(<\s*\/\s*head\s*>)/i', $assets.'$1')
                ->toString();
        }

        return $html
            ->replaceMatches('/(<\s*\/\s*html\s*>)/i', $assets.'$1')
            ->toString();
    }
}
