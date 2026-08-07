<?php

declare(strict_types=1);

namespace TYPO3\CMS\VisualEditor\Editor;

use TYPO3\CMS\Core\Domain\RecordInterface;
use TYPO3\CMS\Core\Schema\Field\InputFieldType;
use TYPO3\CMS\Core\Schema\Field\TextFieldType;

class EditorFieldTag extends EditorTag
{
    protected string $value = '';
    protected string $label = '';
    protected RecordInterface $record;
    protected InputFieldType|TextFieldType $field;
    protected bool $allowedToModify = TRUE;

    public function getValue(): string
    {
        return $this->value;
    }

    public function setValue(string $value): EditorFieldTag
    {
        $this->value = $value;
        return $this;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): EditorFieldTag
    {
        $this->label = $label;
        return $this;
    }

    public function getRecord(): RecordInterface
    {
        return $this->record;
    }

    public function setRecord(RecordInterface $record): EditorFieldTag
    {
        $this->record = $record;
        return $this;
    }

    public function getField(): TextFieldType|InputFieldType
    {
        return $this->field;
    }

    public function setField(TextFieldType|InputFieldType $field): EditorFieldTag
    {
        $this->field = $field;
        return $this;
    }

    public function isAllowedToModify(): bool
    {
        return $this->allowedToModify;
    }

    public function setAllowedToModify(bool $allowedToModify): EditorFieldTag
    {
        $this->allowedToModify = $allowedToModify;
        return $this;
    }
}
