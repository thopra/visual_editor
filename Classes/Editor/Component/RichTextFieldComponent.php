<?php

declare(strict_types=1);

namespace TYPO3\CMS\VisualEditor\Editor\Component;

use TYPO3\CMS\Core\Domain\RecordInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3Fluid\Fluid\Core\ViewHelper\TagBuilder;

class RichTextFieldComponent implements EditorComponentInterface
{
    use EditorComponentTrait;
    use EditorFieldComponentTrait;

    protected array $options = [];

    public function __construct(RecordInterface $record)
    {
        $this->setRecord($record);
    }

    public function getTagBuilder(): TagBuilder
    {
        $options = json_encode($this->getOptions(), JSON_THROW_ON_ERROR);

        $tag = GeneralUtility::makeInstance(TagBuilder::class);
        $tag->setTagName('ve-editable-rich-text');
        $tag->addAttribute('table', $this->getRecord()->getMainType());
        $tag->addAttribute('uid', (string)($this->getRecord()->getComputedProperties()->getLocalizedUid() ?: $this->getRecord()->getComputedProperties()->getVersionedUid() ?: $this->getRecord()->getUid()));
        $tag->addAttribute('field', $this->getField()->getName());
        $tag->addAttribute('fieldPositionId', $this->getRecord()->getMainType() . ':' . $this->getRecord()->getUid() . ':' . $this->getField()->getName());
        $tag->addAttribute('name', $this->getName());
        $tag->addAttribute('title', $this->getTitle());
        $tag->addAttribute('options', $options);

        $tag->setContent($this->getContent());

        $tag->forceClosingTag(true);

        return $tag;
    }

    public function getOptions(): array
    {
        return $this->options;
    }

    public function setOptions(array $options): RichTextFieldComponent
    {
        $this->options = $options;
        return $this;
    }
}
