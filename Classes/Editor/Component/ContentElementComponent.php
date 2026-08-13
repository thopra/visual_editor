<?php

declare(strict_types=1);

namespace TYPO3\CMS\VisualEditor\Editor\Component;

use TYPO3\CMS\Core\Domain\Record;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3Fluid\Fluid\Core\ViewHelper\TagBuilder;

class ContentElementComponent implements EditorComponentInterface
{
    use EditorComponentTrait;

    protected Record $record;
    protected bool $canModifyRecord = false;
    protected bool $canBeMoved = false;
    protected bool $hidden = false;
    protected string $hiddenFieldName = '';
    protected string $elementName = '';

    public function __construct(Record $record)
    {
        $this->setRecord($record);
    }

    public function getTagBuilder(): TagBuilder
    {
        $table = $this->getRecord()->getMainType();
        $tag = GeneralUtility::makeInstance(TagBuilder::class, 've-content-element', $this->getContent() ?: '');
        $tag->forceClosingTag(true);
        $tag->addAttribute('elementName', $this->getElementName());
        $tag->addAttribute('CType', $this->getRecord()->get('CType'));
        $tag->addAttribute('table', $table);

        $uid = $this->getRecord()->getComputedProperties()->getLocalizedUid() ?: $this->getRecord()->getComputedProperties()->getVersionedUid() ?: $this->getRecord()->getUid();
        $tag->addAttribute('id', $table . ':' . $uid);
        $tag->addAttribute('uid', (string)$uid);
        $tag->addAttribute('scrollPositionId', $table . ':' . $this->getRecord()->getUid());
        $tag->addAttribute('pid', (string)$this->getRecord()->getPid());
        $tag->addAttribute('colPos', $this->getRecord()->get('colPos'));
        $tag->addAttribute('hiddenFieldName', $this->getHiddenFieldName());

        if ($this->isCanModifyRecord()) {
            $tag->addAttribute('canModifyRecord', 'true');
        }

        if ($this->isCanBeMoved()) {
            $tag->addAttribute('canBeMoved', 'true');
        }

        if ($this->isHidden()) {
            $tag->addAttribute('isHidden', 'true');
        }

        if ($this->getRecord()->has('tx_container_parent')) {
            // EXT:container compatibility
            $tag->addAttribute('tx_container_parent', $this->getRecord()->getRawRecord()->get('tx_container_parent'));
            // TODO (test with sys_language_uid > 1) (test with workspace) possibly we need to find the correct overlay uid
        }

        return $tag;
    }

    public function getRecord(): Record
    {
        return $this->record;
    }

    public function setRecord(Record $record): ContentElementComponent
    {
        $this->record = $record;
        return $this;
    }

    public function isCanModifyRecord(): bool
    {
        return $this->canModifyRecord;
    }

    public function setCanModifyRecord(bool $canModifyRecord): ContentElementComponent
    {
        $this->canModifyRecord = $canModifyRecord;
        return $this;
    }

    public function isCanBeMoved(): bool
    {
        return $this->canBeMoved;
    }

    public function setCanBeMoved(bool $canBeMoved): ContentElementComponent
    {
        $this->canBeMoved = $canBeMoved;
        return $this;
    }

    public function isHidden(): bool
    {
        return $this->hidden;
    }

    public function setHidden(bool $hidden): ContentElementComponent
    {
        $this->hidden = $hidden;
        return $this;
    }

    public function getHiddenFieldName(): string
    {
        return $this->hiddenFieldName;
    }

    public function setHiddenFieldName(string $hiddenFieldName): ContentElementComponent
    {
        $this->hiddenFieldName = $hiddenFieldName;
        return $this;
    }

    public function getElementName(): string
    {
        return $this->elementName;
    }

    public function setElementName(string $elementName): ContentElementComponent
    {
        $this->elementName = $elementName;
        return $this;
    }
}
