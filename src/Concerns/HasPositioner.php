<?php

namespace Native\Desktop\Concerns;

trait HasPositioner
{
    protected string $windowPosition = 'trayCenter';

    /**
     * @deprecated v3 removes windowPosition() and its presets, as the panel of a v3 menu bar item always sits under its icon. Nothing changes in v2.
     */
    public function windowPosition(string $position): self
    {
        $this->windowPosition = $position;

        return $this;
    }

    /**
     * @deprecated v3 removes windowPosition() and its presets, as the panel of a v3 menu bar item always sits under its icon. Nothing changes in v2.
     */
    public function trayLeft(): self
    {
        return $this->windowPosition('trayLeft');
    }

    /**
     * @deprecated v3 removes windowPosition() and its presets, as the panel of a v3 menu bar item always sits under its icon. Nothing changes in v2.
     */
    public function trayBottomLeft(): self
    {
        return $this->windowPosition('trayBottomLeft');
    }

    /**
     * @deprecated v3 removes windowPosition() and its presets, as the panel of a v3 menu bar item always sits under its icon. Nothing changes in v2.
     */
    public function trayRight(): self
    {
        return $this->windowPosition('trayRight');
    }

    /**
     * @deprecated v3 removes windowPosition() and its presets, as the panel of a v3 menu bar item always sits under its icon. Nothing changes in v2.
     */
    public function trayBottomRight(): self
    {
        return $this->windowPosition('trayBottomRight');
    }

    /**
     * @deprecated v3 removes windowPosition() and its presets, as the panel of a v3 menu bar item always sits under its icon. Nothing changes in v2.
     */
    public function trayCenter(): self
    {
        return $this->windowPosition('trayCenter');
    }

    /**
     * @deprecated v3 removes windowPosition() and its presets, as the panel of a v3 menu bar item always sits under its icon. Nothing changes in v2.
     */
    public function trayBottomCenter(): self
    {
        return $this->windowPosition('trayBottomCenter');
    }

    /**
     * @deprecated v3 removes windowPosition() and its presets, as the panel of a v3 menu bar item always sits under its icon. Nothing changes in v2.
     */
    public function topLeft(): self
    {
        return $this->windowPosition('topLeft');
    }

    /**
     * @deprecated v3 removes windowPosition() and its presets, as the panel of a v3 menu bar item always sits under its icon. Nothing changes in v2.
     */
    public function topRight(): self
    {
        return $this->windowPosition('topRight');
    }

    /**
     * @deprecated v3 removes windowPosition() and its presets, as the panel of a v3 menu bar item always sits under its icon. Nothing changes in v2.
     */
    public function bottomLeft(): self
    {
        return $this->windowPosition('bottomLeft');
    }

    /**
     * @deprecated v3 removes windowPosition() and its presets, as the panel of a v3 menu bar item always sits under its icon. Nothing changes in v2.
     */
    public function bottomRight(): self
    {
        return $this->windowPosition('bottomRight');
    }

    /**
     * @deprecated v3 removes windowPosition() and its presets, as the panel of a v3 menu bar item always sits under its icon. Nothing changes in v2.
     */
    public function topCenter(): self
    {
        return $this->windowPosition('topCenter');
    }

    /**
     * @deprecated v3 removes windowPosition() and its presets, as the panel of a v3 menu bar item always sits under its icon. Nothing changes in v2.
     */
    public function bottomCenter(): self
    {
        return $this->windowPosition('bottomCenter');
    }

    /**
     * @deprecated v3 removes windowPosition() and its presets, as the panel of a v3 menu bar item always sits under its icon. Nothing changes in v2.
     */
    public function leftCenter(): self
    {
        return $this->windowPosition('leftCenter');
    }

    /**
     * @deprecated v3 removes windowPosition() and its presets, as the panel of a v3 menu bar item always sits under its icon. Nothing changes in v2.
     */
    public function rightCenter(): self
    {
        return $this->windowPosition('rightCenter');
    }

    /**
     * @deprecated v3 removes windowPosition() and its presets, as the panel of a v3 menu bar item always sits under its icon. Nothing changes in v2.
     */
    public function center(): self
    {
        return $this->windowPosition('center');
    }

    /**
     * @deprecated v3 removes windowPosition() and its presets, as the panel of a v3 menu bar item always sits under its icon. Nothing changes in v2.
     */
    public function upperCenter(): self
    {
        return $this->windowPosition('upperCenter');
    }
}
