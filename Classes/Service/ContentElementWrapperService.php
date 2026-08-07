<?php

declare(strict_types=1);

namespace TYPO3\CMS\VisualEditor\Service;

use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\VisualEditor\Editor\EditorTagFactory;

#[Autoconfigure(public: true)]
final readonly class ContentElementWrapperService
{
    public function __construct(
        private EditModeService $editModeService,
    ) {
    }

    /**
     * @param array<string, mixed> $data the raw database row
     */
    public function wrapContentElementHtml(string $table, array $data, string $content, ServerRequestInterface $request): string
    {
        if (!$this->editModeService->isEditMode($request)) {
            return $content;
        }

        $this->editModeService->init($request);

        $tag = GeneralUtility::makeInstance(EditorTagFactory::class)->getElement(
            request: $request,
            table: $table,
            databaseRow: $data,
            content: $content
        );

        return $tag->getTagBuilder()->render();
    }
}
