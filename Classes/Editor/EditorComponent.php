<?php

declare(strict_types=1);

namespace TYPO3\CMS\VisualEditor\Editor;

use TYPO3Fluid\Fluid\Core\ViewHelper\TagBuilder;

class EditorComponent
{
    protected TagBuilder $tagBuilder;
    protected string $content = "";
    protected array $javascriptModules = [];

    public function __construct(TagBuilder $tag)
    {
        $this->tagBuilder = $tag;
    }

    public function getJavascriptModules(): array
    {
        return $this->javascriptModules;
    }

    public function setJavascriptModules(array $javascriptModules): static
    {
        $this->javascriptModules = $javascriptModules;
        return $this;
    }

    public function getTagBuilder(): TagBuilder
    {
        return $this->tagBuilder;
    }

    public function setTagBuilder(TagBuilder $tag): static
    {
        $this->tagBuilder = $tag;
        return $this;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(string $content): static
    {
        $this->content = $content;
        return $this;
    }
}
