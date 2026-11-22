<?php

namespace Native\Desktop;

use Illuminate\Support\Traits\Conditionable;
use Native\Desktop\Client\Client;

class Clipboard
{
    use Conditionable;

    public function __construct(protected Client $client) {}

    public function clear()
    {
        $this->client->delete('clipboard');
    }

    public function text($text = null): string
    {
        if (is_null($text)) {
            return $this->client->get('clipboard/text')->json('text');
        }

        $this->client->post('clipboard/text', [
            'text' => $text,
        ]);

        return $text;
    }

    public function html($html = null): string
    {
        if (is_null($html)) {
            return $this->client->get('clipboard/html')->json('html');
        }

        $this->client->post('clipboard/html', [
            'html' => $html,
        ]);

        return $html;
    }

    /**
     * @deprecated v3 keeps image() but refuses a relative path, which v2 reads from PHP's working folder. Give a full path, such as storage_path('app/chart.png'), or a data URL. Nothing changes in v2.
     */
    public function image($image = null): ?string
    {
        if (is_null($image)) {
            return $this->client->get('clipboard/image')->json('image');
        }

        $dataUri = $image;

        if (is_string($image) && file_exists($image)) {
            $type = pathinfo($image, PATHINFO_EXTENSION);
            $data = file_get_contents($image);
            $dataUri = "data:image/{$type};base64,".base64_encode($data);
        }

        $this->client->post('clipboard/image', [
            'image' => $dataUri,
        ]);

        return $image;
    }
}
