<?php

declare(strict_types=1);

namespace TYPO3\CMS\VisualEditor\Editor\Component;

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3Fluid\Fluid\Core\ViewHelper\TagBuilder;

class ContentAreaComponent implements EditorComponentInterface
{
    use EditorComponentTrait;

    protected int $target;
    protected int $colPos;
    protected string $columnName;
    protected array $allowedContentTypes = [];
    protected array $disallowedContentTypes = [];
    protected ?int $containerParent = null;

    public function getTagBuilder(): TagBuilder
    {
        $tag = GeneralUtility::makeInstance(TagBuilder::class, 've-content-area', $this->getContent() ?: '');
        $tag->forceClosingTag(true);

        $tag->addAttribute('target', (string)$this->getTarget());
        $tag->addAttribute('colPos', (string)$this->getColPos());
        $tag->addAttribute('allowedContentTypes', implode(',', $this->getAllowedContentTypes()));
        $tag->addAttribute('disallowedContentTypes', implode(',', $this->getDisallowedContentTypes()));
        $tag->addAttribute('columnName', $this->getColumnName());

        if ($this->getContainerParent()) {
            $tag->addAttribute('tx_container_parent', (string)$this->getContainerParent());
        }

        return $tag;
    }

    public function getTarget(): int
    {
        return $this->target;
    }

    public function setTarget(int $target): ContentAreaComponent
    {
        $this->target = $target;
        return $this;
    }

    public function getColPos(): int
    {
        return $this->colPos;
    }

    public function setColPos(int $colPos): ContentAreaComponent
    {
        $this->colPos = $colPos;
        return $this;
    }

    public function getColumnName(): string
    {
        return $this->columnName;
    }

    public function setColumnName(string $columnName): ContentAreaComponent
    {
        $this->columnName = $columnName;
        return $this;
    }

    public function getAllowedContentTypes(): array
    {
        return $this->allowedContentTypes;
    }

    public function setAllowedContentTypes(array $allowedContentTypes): ContentAreaComponent
    {
        $this->allowedContentTypes = $allowedContentTypes;
        return $this;
    }

    public function getDisallowedContentTypes(): array
    {
        return $this->disallowedContentTypes;
    }

    public function setDisallowedContentTypes(array $disallowedContentTypes): ContentAreaComponent
    {
        $this->disallowedContentTypes = $disallowedContentTypes;
        return $this;
    }

    public function getContainerParent(): ?int
    {
        return $this->containerParent;
    }

    public function setContainerParent(?int $containerParent): ContentAreaComponent
    {
        $this->containerParent = $containerParent;
        return $this;
    }
}
