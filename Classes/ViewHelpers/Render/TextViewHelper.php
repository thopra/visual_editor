<?php

declare(strict_types=1);

namespace TYPO3\CMS\VisualEditor\ViewHelpers\Render;

use InvalidArgumentException;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Domain\Exception\RecordPropertyNotFoundException;
use TYPO3\CMS\Core\Domain\RecordInterface;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Page\AssetCollector;
use TYPO3\CMS\Core\Schema\Field\TextFieldType;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\DomainObject\DomainObjectInterface;
use TYPO3\CMS\Fluid\ViewHelpers\Format\HtmlViewHelper;
use TYPO3\CMS\Frontend\Page\PageInformation;
use TYPO3\CMS\VisualEditor\EditableResult\Input;
use TYPO3\CMS\VisualEditor\EditableResult\RichText;
use TYPO3\CMS\VisualEditor\Editor\EditorFieldTag;
use TYPO3\CMS\VisualEditor\Editor\EditorTagFactory;
use TYPO3\CMS\VisualEditor\Service\EditModeService;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;
use TYPO3Fluid\Fluid\Core\ViewHelper\InvalidArgumentValueException;

/**
 * ViewHelper to render content based on records and fields from a TCA schema.
 * Handles the processing of both simple and rich text fields.
 *
 * ````html
 *   <f:render.text record="{page}" field="bodytext" />
 *   {record -> f:render.text(field: 'title')}
 *   <f:render.text field="subheader">{record}</f:render.text>
 * ````
 */
final class TextViewHelper extends AbstractViewHelper
{
    private const RECORD_TYPE = RecordInterface::class . '|' . PageInformation::class . '|' . DomainObjectInterface::class;

    /**
     * Extbase models have a __toString() method and Fluid calls that if we escape the Children (arguments)
     */
    protected $escapeChildren = false;

    protected $escapeOutput = false;

    public function __construct(
        private readonly EditModeService $editModeService,
        private readonly AssetCollector $assetCollector,
        private readonly Typo3Version $typo3Version,
    ) {
    }

    public function initializeArguments(): void
    {
        parent::initializeArguments();

        $type = 'object';
        // can not always use DI here because fluid initializes the class without DI. and calls initializeArguments.
        $typo3Version = $this->typo3Version ?? GeneralUtility::makeInstance(Typo3Version::class);
        if ($typo3Version->getMajorVersion() >= 14) {
            $type = self::RECORD_TYPE;
        }

        $this->registerArgument('record', $type, 'A Record API Object (field is also needed)');
        $this->registerArgument('field', 'string', 'the field that should be rendered', true);
        $this->registerArgument('optional', 'boolean', 'If the provided field does not exist in the record, null will be returned.', false, false);
    }

    public function getContentArgumentName(): string
    {
        return 'record';
    }

    public function render(): Input|RichText|null
    {
        $renderingContext = $this->renderingContext ?? throw new InvalidArgumentException('$this->renderingContext is not available', 1772464146);
        $request = $renderingContext->getAttribute(ServerRequestInterface::class);
        $this->editModeService->init($request);

        $record = $this->renderChildren();
        $field = $this->arguments['field'];
        try {
            $tag = GeneralUtility::makeInstance(EditorTagFactory::class)->getField(
                $request,
                $record,
                $field
            );
        } catch (RecordPropertyNotFoundException $recordPropertyNotFoundException) {
            if ($this->arguments['optional']) {
                return null;
            }

            throw new InvalidArgumentValueException(
                'The field "' . $field . '" does not exist in the given record `' . $record->getFullType() . '`.',
                1775554099,
                $recordPropertyNotFoundException,
            );
        }

        foreach ($tag->getJavascriptModules() as $module) {
            $this->assetCollector->addJavascriptModule($module);
        }

        if (!($tag->getField() instanceof TextFieldType) || !$tag->getField()->isRichText()) {
            return $this->renderInput($tag);
        }

        return $this->renderRichText($tag);
    }

    private function renderInput(EditorFieldTag $tag): Input {
        if (!$tag->isAllowedToModify()) {
            return new Input($tag->getLabel(), $tag->getTagBuilder()->getContent(), !$tag->getValue(), $tag->getValue()); // TODO maybe we should remove the Input and RichText classes?
        }

        return new Input($tag->getLabel(), $tag->getTagBuilder()->render(), !$tag->getValue(), $tag->getValue() ?: '');
    }

    private function renderRichText(EditorFieldTag $tag): RichText
    {
        if (!$tag->isAllowedToModify()) {
            $renderingContext = $this->renderingContext ?? throw new InvalidArgumentException('$this->renderingContext is not available', 1772464098);
            $escapedValue = $renderingContext->getViewHelperInvoker()->invoke(
                HtmlViewHelper::class,
                [],
                $renderingContext,
                fn(): string => $tag->getValue(),
            );
            return new RichText($tag->getLabel(), $escapedValue, $tag->getValue() === '', $tag->getValue());
        }

        return new RichText($tag->getLabel(), $tag->getTagBuilder()->render(), $tag->getValue() === '', $tag->getValue());
    }
}
