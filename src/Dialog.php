<?php

namespace Native\Desktop;

use Illuminate\Support\Traits\Conditionable;
use Illuminate\Support\Traits\Macroable;
use Native\Desktop\Client\Client;
use Native\Desktop\Facades\Window;

class Dialog
{
    use Conditionable;
    use Macroable;

    protected $title;

    protected $defaultPath;

    protected $buttonLabel = 'Select';

    protected $properties = [
        'openFile',
    ];

    protected $filters = [];

    protected $windowReference;

    final public function __construct(protected Client $client) {}

    /**
     * @deprecated v3 removes this. A panel there comes from Dialog::pickFiles(), pickFolders() or saveFile() on the Dialog facade, and the option methods keep their names. Nothing changes in v2.
     */
    public static function new()
    {
        return new static(new Client);
    }

    public function title(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function defaultPath(string $defaultPath): self
    {
        $this->defaultPath = $defaultPath;

        return $this;
    }

    public function button(string $buttonLabel): self
    {
        $this->buttonLabel = $buttonLabel;

        return $this;
    }

    public function multiple()
    {
        $this->properties[] = 'multiSelections';

        return $this;
    }

    public function withHiddenFiles()
    {
        $this->properties[] = 'showHiddenFiles';

        return $this;
    }

    public function files()
    {
        $this->properties = array_diff($this->properties, ['openDirectory']);
        $this->properties = ['openFile'];

        return $this;
    }

    public function folders()
    {
        $this->properties = array_diff($this->properties, ['openFile']);
        $this->properties[] = 'openDirectory';

        return $this;
    }

    public function dontResolveSymlinks(): self
    {
        $this->properties[] = 'noResolveAliases';

        return $this;
    }

    public function filter(string $name, array $extensions): self
    {
        $this->filters[] = [
            'name' => $name,
            'extensions' => $extensions,
        ];

        return $this;
    }

    public function properties(array $properties): self
    {
        $this->properties = $properties;

        return $this;
    }

    public function asSheet(?string $windowId = null): self
    {
        $this->windowReference = $windowId ?? Window::current()->getId();

        return $this;
    }

    /**
     * @deprecated v3 removes this, as a panel there does not wait for the user. The answer runs a callback, pathsSelected() or cancelled(), and the paths always come as a list. Nothing changes in v2.
     */
    public function open()
    {
        $result = $this->client->post('dialog/open', $this->dialogData())->json('result');

        if (! in_array('multiSelections', $this->properties)) {
            return $result[0] ?? null;
        }

        return $result;
    }

    /**
     * @deprecated v3 removes this, as a panel there does not wait for the user. Dialog::saveFile() answers in pathsSelected() or cancelled(). Nothing changes in v2.
     */
    public function save()
    {
        return $this->client->post('dialog/save', $this->dialogData())->json('result');
    }

    public function dialogData(): array
    {
        return [
            'title' => $this->title,
            'windowReference' => $this->windowReference,
            'defaultPath' => $this->defaultPath,
            'filters' => $this->filters,
            'buttonLabel' => $this->buttonLabel,
            'properties' => array_unique($this->properties),
        ];
    }
}
