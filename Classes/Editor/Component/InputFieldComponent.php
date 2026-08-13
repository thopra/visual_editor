<?php

declare(strict_types=1);

namespace TYPO3\CMS\VisualEditor\Editor\Component;

use TYPO3\CMS\Core\Domain\RecordInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3Fluid\Fluid\Core\ViewHelper\TagBuilder;

class InputFieldComponent implements EditorComponentInterface
{
    use EditorComponentTrait;
    use EditorFieldComponentTrait;

    protected bool $allowNewLines = false;
    protected array $validation = [];

    public function __construct(RecordInterface $record)
    {
        $this->setRecord($record);
    }

    public function getTagBuilder(): TagBuilder
    {
        $html = htmlspecialchars($this->getValue());
        if ($this->isAllowNewLines()) {
            $html = nl2br(htmlspecialchars(str_replace('<br>', "\n", $this->getValue())));
        }

        $tag = GeneralUtility::makeInstance(TagBuilder::class);
        $tag->setTagName('ve-editable-text');
        $tag->addAttribute('table', $this->getRecord()->getMainType());
        $tag->addAttribute('uid', (string)($this->getRecord()->getComputedProperties()->getLocalizedUid() ?: $this->getRecord()->getComputedProperties()->getVersionedUid() ?: $this->getRecord()->getUid()));
        $tag->addAttribute('field', $this->getField()->getName());
        $tag->addAttribute('fieldPositionId', $this->getRecord()->getMainType() . ':' . $this->getRecord()->getUid() . ':' . $this->getField()->getName());

        $tag->addAttribute('name', $this->getName());

        $tag->addAttribute('title', $this->getTitle());
        $tag->addAttribute('allowNewlines', $this->isAllowNewLines());
        $tag->addAttribute('value', str_replace('<br>', "\n", $this->getValue()));
        $tag->addAttribute('validation', json_encode($this->getValidation(), JSON_THROW_ON_ERROR));

        $tag->setContent($html);

        $tag->forceClosingTag(true);

        return $tag;
    }

    public function isAllowNewLines(): bool
    {
        return $this->allowNewLines;
    }

    public function setAllowNewLines(bool $allowNewLines): InputFieldComponent
    {
        $this->allowNewLines = $allowNewLines;
        return $this;
    }

    public function getValidation(): array
    {
        return $this->validation;
    }

    public function setValidation(array $validation): InputFieldComponent
    {
        $this->validation = $validation;
        return $this;
    }
}
