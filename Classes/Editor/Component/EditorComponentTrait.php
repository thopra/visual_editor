<?php

declare(strict_types=1);

namespace TYPO3\CMS\VisualEditor\Editor\Component;

trait EditorComponentTrait
{
    protected string $content = "";
    protected array $javascriptModules = [];

    public function getJavascriptModules(): array
    {
        return $this->javascriptModules;
    }

    public function setJavascriptModules(array $javascriptModules): static
    {
        $this->javascriptModules = $javascriptModules;
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
