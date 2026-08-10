<?php

declare(strict_types=1);

namespace TYPO3\CMS\VisualEditor\Service;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Domain\RecordInterface;
use TYPO3\CMS\Core\Page\AssetCollector;
use TYPO3\CMS\Core\Page\PageRenderer;
use RuntimeException;

use TYPO3\CMS\VisualEditor\Editor\PageEditingInformation;
use TYPO3\CMS\VisualEditor\Enum\EditMode;

final readonly class EditModeService
{
    public function __construct(
        private AssetCollector $assetCollector,
        private PageRenderer $pageRenderer,
    ) {
    }

    public function isEditMode(ServerRequestInterface $request): bool
    {
        $editMode = EditMode::fromRequest($request);
        return $editMode->isEditingEnabled();
    }

    public function init(ServerRequestInterface $request): void
    {
        if (!$this->isEditMode($request)) {
            return;
        }

        /** @var PageEditingInformation $pageEditor */
        $pageEditor = $this->getPageEditingInformation($request);

        foreach ($pageEditor->getStyleSheets() as $identifier => $styleSheet) {
            $this->assetCollector->addStyleSheet($identifier, $styleSheet);
        }

        foreach ($pageEditor->getJavascriptModules() as $module) {
            $this->assetCollector->addJavaScriptModule($module);
        }

        foreach ($pageEditor->getLanguageLabels() as $key => $value) {
            $this->pageRenderer->addInlineLanguageLabel($key, $value);
        }

        if (!$this->assetCollector->hasInlineJavaScript('veLangInfo')) {
            $this->assetCollector->addInlineJavaScript(
                'veLangInfo',
                $pageEditor->getInlineJavascript(),
                [
                    'type' => 'text/javascript',
                ],
                [
                    'useNonce' => true,
                ],
            );
        }
    }

    public function canEditField(RecordInterface $record, string $field, ServerRequestInterface $request): bool
    {
        if (!$this->isEditMode($request)) {
            return false; // not in edit mode
        }

        return $this->getPageEditingInformation($request)->canEditField($record, $field);
    }

    private function getPageEditingInformation(ServerRequestInterface $request): PageEditingInformation
    {
        /** @var PageEditingInformation $pageEditor */
        $pageEditor = $request->getAttribute('frontend.visualEditor');
        if (!($pageEditor instanceof PageEditingInformation)) {
            throw new RuntimeException('Could not get page editing information attribute from request', 1786366241);
        }
        return $pageEditor;
    }
}
