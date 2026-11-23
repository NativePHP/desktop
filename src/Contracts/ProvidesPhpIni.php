<?php

namespace Native\Desktop\Contracts;

/**
 * @deprecated v3 removes this interface but keeps phpIni(). Drop the implements clause and its import from your provider there, and keep the method. Nothing changes in v2.
 */
interface ProvidesPhpIni
{
    public function phpIni(): array;
}
