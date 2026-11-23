<?php

namespace Native\Desktop;

use Illuminate\Support\Traits\Conditionable;
use Native\Desktop\Client\Client;

class Notification
{
    use Conditionable;

    /**
     * @deprecated v3's show() returns nothing, and getId() reads the id. v2 does not have getId() yet.
     */
    public ?string $reference = null;

    protected string $title;

    protected string $body;

    protected string $event = '';

    protected string $sound = '';

    protected bool $silent = false;

    private bool $hasReply = false;

    private string $replyPlaceholder = '';

    private array $actions = [];

    final public function __construct(protected Client $client)
    {
        $this->title = config('app.name');
    }

    /**
     * @deprecated v3 removes this, as every call to the Notification facade makes a new notification there.
     */
    public static function new()
    {
        return new static(new Client);
    }

    /**
     * @deprecated v3 renames this to id(), which v2 does not have yet. A notification sent with the id of one still on screen replaces it.
     */
    public function reference(string $reference): self
    {
        $this->reference = $reference;

        return $this;
    }

    public function title(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    /**
     * @deprecated v3 renames this to clicked(), which v2 does not have yet. In v3 NotificationClicked still fires as well, and the class's constructor gets the values it names, such as id and data, where v2 passed reference and event. clicked() also takes a class with handle() or __invoke, which is called on a click.
     */
    public function event(string $event): self
    {
        $this->event = $event;

        return $this;
    }

    public function sound(string $sound): self
    {
        $this->sound = $sound;

        return $this;
    }

    public function silent(bool $silent = true): self
    {
        $this->silent = $silent;

        return $this;
    }

    /**
     * @deprecated v3 replaces this with reply(placeholder: $placeholder), which v2 does not have yet.
     */
    public function hasReply(string $placeholder = ''): self
    {
        $this->hasReply = true;
        $this->replyPlaceholder = $placeholder;

        return $this;
    }

    /**
     * @deprecated v3 replaces this with action($label), which v2 does not have yet. In v3 a button can carry a key, which comes back in NotificationActionClicked, and a class that a press fires or calls.
     */
    public function addAction(string $label): self
    {
        $this->actions[] = $label;

        return $this;
    }

    public function message(string $body): self
    {
        $this->body = $body;

        return $this;
    }

    public function show(): self
    {
        $response = $this->client->post('notification', [
            'reference' => $this->reference,
            'title' => $this->title,
            'body' => $this->body,
            'event' => $this->event,
            'sound' => $this->sound,
            'silent' => $this->silent,
            'hasReply' => $this->hasReply,
            'replyPlaceholder' => $this->replyPlaceholder,
            'actions' => array_map(fn (string $label) => [
                'type' => 'button',
                'text' => $label,
            ], $this->actions),
        ]);

        $this->reference = $response->json('reference');

        return $this;
    }
}
