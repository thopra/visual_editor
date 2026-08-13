<?php

declare(strict_types=1);

namespace TYPO3\CMS\VisualEditor\Editor\Component;

use TYPO3\CMS\Core\Domain\RecordInterface;
use TYPO3\CMS\Core\Schema\Field\InputFieldType;
use TYPO3\CMS\Core\Schema\Field\TextFieldType;

trait EditorFieldComponentTrait
{
    protected string $value = '';
    protected string $title = '';
    protected string $name = '';
    protected RecordInterface $record;
    protected InputFieldType|TextFieldType $field;
    protected bool $allowedToModify = TRUE;

    public function getValue(): string
    {
        return $this->value;
    }

    public function setValue(string $value): static
    {
        $this->value = $value;
        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function getRecord(): RecordInterface
    {
        return $this->record;
    }

    public function setRecord(RecordInterface $record): static
    {
        $this->record = $record;
        return $this;
    }

    public function getField(): TextFieldType|InputFieldType
    {
        return $this->field;
    }

    public function setField(TextFieldType|InputFieldType $field): static
    {
        $this->field = $field;
        return $this;
    }

    public function isAllowedToModify(): bool
    {
        return $this->allowedToModify;
    }

    public function setAllowedToModify(bool $allowedToModify): static
    {
        $this->allowedToModify = $allowedToModify;
        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;
        return $this;
    }
}
