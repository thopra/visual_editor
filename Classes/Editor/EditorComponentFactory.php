<?php

declare(strict_types=1);

namespace TYPO3\CMS\VisualEditor\Editor;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Configuration\Richtext as RichtextConfiguration;
use TYPO3\CMS\Core\Domain\Record;
use TYPO3\CMS\Core\Domain\RecordFactory;
use TYPO3\CMS\Core\Domain\RecordInterface;
use TYPO3\CMS\Core\Html\RteHtmlParser;
use TYPO3\CMS\Core\Schema\Capability\TcaSchemaCapability;
use TYPO3\CMS\Core\Schema\Field\InputFieldType;
use TYPO3\CMS\Core\Schema\Field\TextFieldType;
use TYPO3\CMS\Core\Schema\TcaSchemaFactory;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\DomainObject\DomainObjectInterface;
use TYPO3\CMS\VisualEditor\Core\RichtText\RichTextConfigurationService;
use TYPO3\CMS\VisualEditor\Core\RichtText\RichTextConfigurationServiceDto;
use TYPO3\CMS\VisualEditor\Editor\Component\ContentAreaComponent;
use TYPO3\CMS\VisualEditor\Editor\Component\ContentElementComponent;
use TYPO3\CMS\VisualEditor\Editor\Component\InputFieldComponent;
use TYPO3\CMS\VisualEditor\Editor\Component\RichTextFieldComponent;
use TYPO3\CMS\VisualEditor\Service\EditModeService;
use TYPO3\CMS\VisualEditor\Service\LocalizationService;
use TYPO3\CMS\VisualEditor\Service\ModelToRawRecordService;
use TYPO3Fluid\Fluid\Core\ViewHelper\TagBuilder;
use TYPO3\CMS\Frontend\Page\PageInformation;
use B13\Container\Domain\Model\Container;
use UnexpectedValueException;
use InvalidArgumentException;

final readonly class EditorComponentFactory implements SingletonInterface
{
    public function __construct(
        private EditModeService $editModeService,
        private LocalizationService $localizationService,
        private RecordFactory $recordFactory,
        private TcaSchemaFactory $tcaSchema,
        private ModelToRawRecordService $modelToRawRecordService,
        private RteHtmlParser $rteHtmlParser,
        private RichTextConfigurationService $richTextConfigurationService,
        private RichtextConfiguration $richtext,
    )
    {}

    /**
     * Create a tag object representing the custom element <ve-content-area /> which wraps a content column
     * in the frontend when editing mode is active.
     *
     * @param ServerRequestInterface $request The current PSR-7 Request Object
     * @param int $colPos The colPos number of the content column
     * @param string $columnName The column name or language label identifier
     * @param string|null $content The HTML content that should be wrapped by this tag
     * @param string[]|null $allowedContentTypes A list of allowed CTypes in this column
     * @param string[]|null $disallowedContentTypes A list of disallowed CTypes in this column
     * @param Container|int|null $containerParent If the extension container is installed, you can specify the current container object or uid of the parent elements
     * @return ContentAreaComponent
     */
    public function getContentArea(
        ServerRequestInterface $request,
        int $colPos,
        string $columnName,
        ?string $content = null,
        ?array $allowedContentTypes = [],
        ?array $disallowedContentTypes = [],
        Container|int|null $containerParent = null
    ): ContentAreaComponent
    {
        $pageInformation = $request->getAttribute('frontend.page.information');
        assert($pageInformation instanceof PageInformation);
        $pageUid = $pageInformation->getId();

        $component = (new ContentAreaComponent())
                        ->setContent($content ?: '')
                        ->setTarget($pageUid)
                        ->setColPos($colPos)
                        ->setColumnName($this->localizationService->tryTranslation($columnName))
                        ->setAllowedContentTypes($allowedContentTypes)
                        ->setDisallowedContentTypes($disallowedContentTypes);

        if ($containerParent instanceof Container) {
            $localizedUid = $containerParent->getContainerRecord()['_ORIG_uid'] ?? $containerParent->getContainerRecord()['_LOCALIZED_UID'] ?? $containerParent->getUidOfLiveWorkspace();
            $component->setContainerParent($localizedUid); // TODO (test with sys_language_uid > 1) (test with workspace)
        } elseif(is_int($containerParent)) {
            $component->setContainerParent($containerParent);
        }

        return $component;
    }

    /**
     * Create a tag object representing the custom element <ve-content-element /> which wraps a rendered content
     * element or record and enables editing functions when in editing mode if the user has permission to edit the record.
     *
     * @param ServerRequestInterface $request The current PSR-7 Request Object
     * @param string $table
     * @param array $databaseRow
     * @param string|null $content
     * @return TagBuilder
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     * @throws \TYPO3\CMS\Core\Schema\Exception\UndefinedFieldException
     * @throws \TYPO3\CMS\Core\Schema\Exception\UndefinedSchemaException
     */
    public function getElement(
        ServerRequestInterface $request, // even though currently not used, we should keep passing the request object mandatory for each method
        string $table,
        array $databaseRow,
        ?string $content = null
    ): ContentElementComponent
    {
        $canModifyRecord = true;
        /** @var BackendUserAuthentication $beUser */
        $beUser = $GLOBALS['BE_USER'];
        if (!$beUser->check('tables_modify', $table)) {
            $canModifyRecord = false; // no edit rights
        }

        if ($table === 'tt_content' && !$beUser->check('explicit_allowdeny', 'tt_content:CType:' . $databaseRow['CType'])) {
            $canModifyRecord = false;
            // no access to this content element type
        }

        $record = $this->recordFactory->createResolvedRecordFromDatabaseRow($table, $databaseRow);
        if (!$record instanceof Record) {
            throw new UnexpectedValueException('Record array must be a ' . Record::class, 1772465047);
        }

        $schema = $this->tcaSchema->get($record->getFullType());

        $hiddenFieldType = $schema->getCapability(TcaSchemaCapability::RestrictionDisabledField);
        $hiddenFieldName = $hiddenFieldType->getFieldName();
        if (
            $schema->getField($hiddenFieldName)->supportsAccessControl() && !$beUser->check(
                'non_exclude_fields',
                $record->getMainType() . ':' . $hiddenFieldName,
            )
        ) {
            $hiddenFieldName = ''; // user has no access to hidden field
        }

        return (new ContentElementComponent($record))
                    ->setContent($content ?: '')
                    ->setHidden($record->getSystemProperties()?->isDisabled() ?: false)
                    ->setHiddenFieldName($hiddenFieldName)
                    ->setElementName($this->getContentTypeLabel($record))
                    ->setCanModifyRecord($canModifyRecord)
                    ->setCanBeMoved($record->getLanguageInfo()?->getTranslationParent() ?: false);
    }

    /**
     * Create a tag object that represents the custom element <ve-editable-text /> or <ve-editable-rich-text /> depending
     * on the configuration of the given record and field.
     *
     * @param ServerRequestInterface $request
     * @param RecordInterface|PageInformation|DomainObjectInterface $record
     * @param string $field
     * @return InputFieldComponent|null
     * @throws \JsonException
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     * @throws \TYPO3\CMS\Core\Schema\Exception\UndefinedFieldException
     * @throws \TYPO3\CMS\Core\Schema\Exception\UndefinedSchemaException
     */
    public function getField(
        ServerRequestInterface $request, // even though currently not used, we should keep passing the request object mandatory for each method
        RecordInterface|PageInformation|DomainObjectInterface $record,
        string $field
    ): InputFieldComponent|RichTextFieldComponent|null
    {
        if ($record instanceof PageInformation) {
            $record = $this->recordFactory->createResolvedRecordFromDatabaseRow('pages', $record->getPageRecord());
        }

        if ($record instanceof DomainObjectInterface) {
            $record = $this->modelToRawRecordService->modelToRawRecord($record);
        }

        if (!$record instanceof RecordInterface) {
            throw new InvalidArgumentException(
                'Object could not be converted to RecordInterface. Given: ' . get_debug_type(
                    $record,
                ),
                1786108700,
            );
        }

        $value = $record->get($field) ?? '';

        if (!is_string($value)) {
            $table = $record->getMainType();
            throw new InvalidArgumentException(
                'The value of the field "' . $table . '.' . $field . '" must be a string. Given: ' . get_debug_type($value),
                1770321858,
            );
        }

        $schema = $this->tcaSchema->get($record->getFullType());
        $tableLabel = $schema->getTitle($this->localizationService->tryTranslation(...));

        $fieldSchema = $schema->getField($field);
        $label = $this->localizationService->tryTranslation($fieldSchema->getLabel());

        $label = $tableLabel . ': ' . $label;

        if ($fieldSchema instanceof InputFieldType) {
            return $this->getFieldInput($request, $record, $fieldSchema, $value, $label);
        }

        if ($fieldSchema instanceof TextFieldType) {
            if (!$fieldSchema->isRichText()) {
                return $this->getFieldInput($request, $record, $fieldSchema, $value, $label, true);
            }

            return $this->getFieldRichText($request, $record, $fieldSchema, $value, $label);
        }

        $table = $record->getMainType();
        throw new InvalidArgumentException('The field "' . $table . '.' . $field . '" is not supported. Given: ' . get_debug_type($fieldSchema), 1770618219);
    }

    /**
     * Create a tag that represents the custom element <ve-editable-text /> which can be used for inline editing of text fields.
     *
     * @param ServerRequestInterface $request
     * @param RecordInterface $record
     * @param InputFieldType|TextFieldType $field
     * @param string $value
     * @param string $label
     * @param bool $allowNewlines
     * @return InputFieldComponent|null
     * @throws \JsonException
     */
    public function getFieldInput(
        ServerRequestInterface $request, // even though currently not used, we should keep passing the request object mandatory for each method
        RecordInterface $record,
        InputFieldType|TextFieldType $field,
        string $value,
        string $label,
        bool $allowNewlines = false
    ): ?InputFieldComponent
    {
        $canEdit = $this->editModeService->canEditField($record, $field->getName(), $request);
        if (!$canEdit) {
            return null;
        }

        $localizedLabel = $this->localizationService->tryTranslation(
            'LLL:EXT:visual_editor/Resources/Private/Language/locallang.xlf:editable.title',
            [$label],
        );

        $component = (new InputFieldComponent($record))
                        ->setValue($value)
                        ->setName($label)
                        ->setTitle($localizedLabel)
                        ->setAllowNewLines($allowNewlines)
                        ->setValidation($this->getInputValidationConfiguration($field, $allowNewlines))
                        ->setField($field)
                        ->setAllowedToModify($this->editModeService->canEditField($record, $field->getName(), $request));

        return $component;
    }

    /**
     * Create a tag that represents the custom element <ve-editable-rich-text /> which can be used for inline editing of
     * rich text fields.
     *
     * @param ServerRequestInterface $request
     * @param RecordInterface $record
     * @param InputFieldType|TextFieldType $field
     * @param string $value
     * @param string $label
     * @return InputFieldComponent|null
     * @throws \JsonException
     */
    public function getFieldRichText(
        ServerRequestInterface $request, // even though currently not used, we should keep passing the request object mandatory for each method
        RecordInterface $record,
        InputFieldType|TextFieldType $field,
        string $value,
        string $label
    ): ?RichTextFieldComponent
    {
        $canEdit = $this->editModeService->canEditField($record, $field->getName(), $request);
        if (!$canEdit) {
            return null;
        }

        $rteOptions = $this->getRTEOptions($record, $field->getName());
        $processingConfiguration = $richtextConfiguration['proc.'] ?? [];
        $escapedValue = $this->rteHtmlParser->transformTextForRichTextEditor($value, $processingConfiguration);

        // Add required JavaScript modules to be loaded by the ViewHelper
        // This factory should be stateless and not have side effects, so we defer actually adding the modules with the
        // asset collector to the implementation level
        $jsModules = [];
        foreach ($rteOptions['importModules'] as $importModule) {
            $jsModules[] = $importModule['module'];
        }
        $jsModules[] = '@typo3/ckeditor5/translations/' . $rteOptions['language']['ui'] . '.js';

        $localizedLabel = $this->localizationService->tryTranslation(
            'LLL:EXT:visual_editor/Resources/Private/Language/locallang.xlf:editable.title',
            [$label],
        );

        $component = (new RichTextFieldComponent($record))
                        ->setValue($value)
                        ->setTitle($localizedLabel)
                        ->setName($label)
                        ->setField($field)
                        ->setContent($escapedValue)
                        ->setOptions($rteOptions)
                        ->setAllowedToModify($this->editModeService->canEditField($record, $field->getName(), $request))
                        ->setJavascriptModules($jsModules);

        return $component;
    }

    private function getContentTypeLabel(Record $record): string
    {
        $recordType = $record->getRecordType() ?? '';
        foreach ($GLOBALS['TCA']['tt_content']['columns']['CType']['config']['items'] as $item) {
            if ($item['value'] === $recordType && isset($item['label'])) {
                return $this->localizationService->tryTranslation($item['label']);
            }
        }

        return $recordType;
    }

    /**
     * @return array{0:string, 1:array<mixed>}
     */
    private function getRTEOptions(RecordInterface $record, string $field): array
    {
        $schema = $this->tcaSchema->get($record->getFullType());
        $richtextConfiguration = $this->richtext->getConfiguration(
            $record->getMainType(),
            $field,
            $record->getPid(),
            $record->getRecordType() ?? '',
            $schema->getField($field)->getConfiguration(),
        );

        $rawRecord = $record->getRawRecord() ?? $record;
        $richTextConfigurationServiceDto = new RichTextConfigurationServiceDto(
            tableName: $record->getMainType(),
            uid: $record->getComputedProperties()->getLocalizedUid() ?: $record->getComputedProperties()->getVersionedUid() ?: $record->getUid(),
            fieldName: $field,
            recordTypeValue: $record->getRecordType() ?? '',
            effectivePid: $record->getPid(),
            richtextConfigurationName: $richtextConfiguration['preset'],
            label: 'Text',
            placeholder: '',
            readOnly: false,
            data: $rawRecord->toArray(),
            additionalConfiguration: $richtextConfiguration['editor']['config'],
            externalPlugins: $richtextConfiguration['editor']['externalPlugins'],
        );

        $config = $this->richTextConfigurationService->resolveCkEditorConfiguration($richTextConfigurationServiceDto);

        unset($config['height']); // height is set by the content itself and css
        $config['debug'] = false; // for now we disable debug mode

        return $config;
    }

    private function getInputValidationConfiguration(InputFieldType|TextFieldType $field, bool $allowNewlines): array
    {
        $config = $field->getConfiguration();
        $validation = [
            'required' => $field->isRequired(),
            'allowNewlines' => $allowNewlines,
        ];

        $min = $config['min'] ?? null;
        if (is_int($min) || (is_string($min) && $min !== '')) {
            $min = (int)$min;
            if ($min > 0) {
                $validation['min'] = $min;
            }
        }

        $max = $config['max'] ?? null;
        if (is_int($max) || (is_string($max) && $max !== '')) {
            $max = (int)$max;
            if ($max > 0) {
                $validation['max'] = $max;
            }
        }

        $evalList = array_flip(GeneralUtility::trimExplode(',', (string)($config['eval'] ?? ''), true));

        $evals = [];
        $evalOrder = ['trim', 'upper', 'lower', 'alpha', 'num', 'alphanum', 'alphanum_x', 'nospace'];
        foreach ($evalOrder as $rule) {
            if (array_key_exists($rule, $evalList)) {
                $evals[] = $rule;
            }
        }

        if ($evals !== []) {
            $validation['eval'] = $evals;
        }

        return $validation;
    }
}
