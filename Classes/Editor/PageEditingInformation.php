<?php

declare(strict_types=1);

namespace TYPO3\CMS\VisualEditor\Editor;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Domain\Record;
use TYPO3\CMS\Core\Domain\RecordInterface;
use TYPO3\CMS\Core\FormProtection\FormProtectionFactory;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Routing\PageArguments;
use TYPO3\CMS\Core\Schema\Capability\TcaSchemaCapability;
use TYPO3\CMS\Core\Schema\TcaSchemaFactory;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Frontend\Page\PageInformation;
use TYPO3\CMS\VisualEditor\Enum\EditMode;
use TYPO3\CMS\VisualEditor\Service\AllowedOriginService;
use TYPO3\CMS\VisualEditor\Service\LanguageModeService;
use TYPO3\CMS\VisualEditor\Service\LocalizationService;
use RuntimeException;

final class PageEditingInformation implements SingletonInterface
{
    protected ServerRequestInterface $request;
    protected ?string $token = null;
    protected ?UriInterface $backendEditUrl = null;

    public function __construct(
        private UriBuilder $uriBuilder,
        private LanguageServiceFactory $languageServiceFactory,
        private LanguageModeService $languageModeService,
        private LocalizationService $localizationService,
        private FormProtectionFactory $formProtectionFactory,
        private Typo3Version $typo3Version,
        private AllowedOriginService $allowedOriginService,
        private TcaSchemaFactory $tcaSchema,
    )
    {}

    public function withRequest(ServerRequestInterface $request): self
    {
        $this->request = $request;
        return $this;
    }

    public function isEnabled(): bool
    {
        $editMode = EditMode::fromRequest($this->request);
        return $editMode->isEditingEnabled();
    }

    public function getPageId(): int
    {
        $pageId = $this->getPageInformation()->getId();
        if (!$pageId) {
            throw new RuntimeException('Could not determine current page id', 1768983081);
        }
        return $this->getPageInformation()->getId();
    }

    public function IsShowIdWithTitle(): bool
    {
        return !empty($this->getBeUser()->getTSConfig()['options.']['pageTree.']['showPageIdWithTitle']);
    }

    public function getBackendEditUrl(): UriInterface
{
        $usedArguments = $this->getUsedArguments($this->request);
        if (!$this->backendEditUrl) {
            $this->backendEditUrl = $this->uriBuilder->buildUriFromRoute('web_edit', [
                'id' => $this->getPageId(),
                // the selected viewMode and languages are saved in be_user->uc
                'params' => $usedArguments,
            ]);
        }
        return $this->backendEditUrl;
    }

    public function getNewContentUrl(): string
    {
        $isExtContainerInstalled = ExtensionManagementUtility::isLoaded('container');
        return (string)$this->uriBuilder->buildUriFromRoute('new_content_element_wizard', [
            'id' => $this->getPageId(),
            'colPos' => '__COL_POS__',
            'uid_pid' => '__UID_PID__',
            ...($isExtContainerInstalled ? ['tx_container_parent' => '__TX_CONTAINER_PARENT__'] : []),
            'returnUrl' => $this->getBackendEditUrl(),
        ]);
    }

    public function getEditContentUrl(): string
    {
        return (string)$this->uriBuilder->buildUriFromRoute('record_edit', $this->getEditParams());
    }

    public function getEditContentContextualUrl(): ?string
    {
        if ($this->typo3Version->getMajorVersion() >= 14) {
            return (string)$this->uriBuilder->buildUriFromRoute('record_edit_contextual', $this->getEditParams());
        }
        return null;
    }

    public function isAllowNewContent(): bool
    {
        return $this->languageModeService->getAllowNewContent($this->getPageInformation(), $this->getSiteLanguage(), $this->request);
    }

    public function getToken(): string
    {
        if (!$this->token) {
            $this->token = $this->formProtectionFactory->createForType('backend')->generateToken('visual_editor', 'save');
        }
        return $this->token;
    }

    public function getRouteArguments(): object
    {
        return (object)$this->flattenBracketKeys(['params' => $this->getUsedArguments($this->request)]);
    }

    public function getAllowedOrigins(): array
    {
        return $this->allowedOriginService->getAllowedOrigins();
    }

    public function toArray(): array
    {
        return [
            'pageId' => $this->getPageId(),
            'languageId' => $this->getSiteLanguage()->getLanguageId(),
            'showIdWithTitle' => $this->isShowIdWithTitle(),
            'backendEditUrl' => $this->getBackendEditUrl(),
            'newContentUrl' => $this->getNewContentUrl(),
            'editContentUrl' => $this->getEditContentUrl(),
            'editContentContextualUrl' => $this->getEditContentContextualUrl(),
            'allowNewContent' => $this->isAllowNewContent(),
            'token' => $this->getToken(),
            'routeArguments' => $this->getRouteArguments(),
            'allowedOrigins' => $this->getAllowedOrigins(),
        ];
    }

    public function getInlineJavascript(): string
    {
        return 'window.TYPO3 = window.TYPO3 || {};
window.veInfo = ' . json_encode($this->toArray(), JSON_THROW_ON_ERROR) . ';
/* if you open this page without it being in an iframe we redirect to the backend */
if (window.parent === window && window.veInfo) {
  const backendEditUrl = window.veInfo.backendEditUrl || null;
  if (backendEditUrl) {
    window.location.replace(backendEditUrl);
    document.body.innerHTML = "";
  }
}';
    }

    public function getJavascriptModules(): array
    {
        $modules = [
            '@typo3/visual-editor/Frontend/index'
        ];

        if ($this->typo3Version->getMajorVersion() >= 14) {
            $modules[] = '@typo3/backend/element/contextual-record-edit-trigger.js';
        }

        return $modules;
    }

    public function getStyleSheets(): array
    {
        return [
            'editable' => 'EXT:visual_editor/Resources/Public/Css/editable.css'
        ];
    }

    public function getLanguageLabels(): array
    {
        $labels = [];
        $files = [
            'EXT:backend/Resources/Private/Language/locallang_alt_doc.xlf',
            'EXT:visual_editor/Resources/Private/Language/locallang.xlf',
        ];
        foreach ($files as $file) {
            $languageService = $this->languageServiceFactory->create($this->localizationService->getBackendUserLanguage() ?? 'en');
            foreach ($languageService->getLabelsFromResource($file) as $key => $value) {
                $labels[$key] = $value;
            }
        }

        return $labels;
    }


    public function canEditField(RecordInterface $record, string $field): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }

        $tcaSchema = $this->tcaSchema->get($record->getFullType());
        $fieldType = $tcaSchema->getField($field);

        if ($tcaSchema->hasCapability(TcaSchemaCapability::AccessReadOnly)) {
            return false; // table readonly
        }

        if ($fieldType->getConfiguration()['readOnly'] ?? false) {
            return false; // field readonly
        }

        // user access check
        $beUser = $this->getBeUser();
        if ($record instanceof Record || method_exists($record, 'getLanguageId')) {
            $languageId = $record->getLanguageId();
            // it is not that bad if we can not check the language access, on save there might be an error message. (better than always throwing an error.
            if (!$beUser->checkLanguageAccess($languageId)) {
                return false; // no access to this language
            }
        }

        if (!$beUser->check('tables_modify', $record->getMainType())) {
            return false; // no access to this table
        }

        if (!$beUser->isInWebMount($record->getPid())) {
            return false; // no access to this page // TODO move this to the middleware
        }

        if ($record->getMainType() === 'tt_content' && !$beUser->check('explicit_allowdeny', 'tt_content:CType:' . $record->get('CType'))) {
            return false;
            // content element type not allowed
        }

        if ($fieldType->supportsAccessControl() && !$beUser->check('non_exclude_fields', $record->getMainType() . ':' . $field)) {
            return false; // no access to this field
        }

        return true;
    }

    /**
     * @return array<string|array<string|array<mixed>>>
     */
    public function getUsedArguments(ServerRequestInterface $request): array
    {
        $routing = $request->getAttribute('routing');
        if (!$routing instanceof PageArguments) {
            throw new RuntimeException('Could not determine current routing context', 1773230232);
        }

        $usedArguments = array_replace_recursive(
            $routing->getArguments(),
            $routing->getRouteArguments(),
        );
        unset($usedArguments['cHash']);
        unset($usedArguments['editMode']);
        return $usedArguments;
    }

    private function getBeUser(): BackendUserAuthentication
    {
        $beUser = $GLOBALS['BE_USER'];
        if (!$beUser instanceof BackendUserAuthentication) {
            throw new RuntimeException('Could not determine backend user authentication', 3305745964);
        }

        return $beUser;
    }

    private function getPageInformation(): PageInformation
    {
        // backend and Frontend Context: determine current page id
        $pageInformation = $this->request->getAttribute('frontend.page.information');
        if (!$pageInformation instanceof PageInformation) {
            throw new RuntimeException('Could not determine current page information', 9965439961);
        }

        return $pageInformation;
    }

    private function getSiteLanguage(): SiteLanguage
    {
        $siteLanguage = $this->request->getAttribute('language');
        if (!$siteLanguage instanceof SiteLanguage) {
            throw new RuntimeException('Could not determine current site language', 3305745963);
        }

        return $siteLanguage;
    }

    private function getEditParams(): array
    {
        return [
            'edit' => ['__TABLE__' => ['__UID__' => 'edit']],
            'returnUrl' => $this->backendEditUrl,
            'module' => 'web_edit',
        ];
    }

    /**
     * @param array<array-key, string|float|int|bool|null|array<mixed>> $input
     * @return array<string, string>
     */
    private function flattenBracketKeys(array $input, string $prefix = ''): array
    {
        $result = [];

        foreach ($input as $key => $value) {
            $newKey = $prefix === '' ? (string)$key : $prefix . '[' . $key . ']';

            if (is_array($value)) {
                $result += $this->flattenBracketKeys($value, $newKey);
            } else {
                $result[$newKey] = (string)$value;
            }
        }

        return $result;
    }
}
