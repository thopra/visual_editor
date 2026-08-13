<?php

declare(strict_types=1);

namespace TYPO3\CMS\VisualEditor\Editor\Component;

use TYPO3Fluid\Fluid\Core\ViewHelper\TagBuilder;

interface EditorComponentInterface
{
    public function getTagBuilder(): TagBuilder;
    public function getJavascriptModules(): array;
    public function getContent(): string;
}
