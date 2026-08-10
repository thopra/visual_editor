<?php

declare(strict_types=1);

namespace TYPO3\CMS\VisualEditor\EventListener;

use B13\Container\Domain\Model\Container;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Fluid\Event\ModifyRenderedContentAreaEvent;
use TYPO3\CMS\VisualEditor\BackwardsCompatibility\Event\RenderContentAreaEvent as V13RenderContentAreaEvent;
use TYPO3\CMS\VisualEditor\Editor\EditorComponentFactory;
use TYPO3\CMS\VisualEditor\Service\EditModeService;
use TYPO3\CMS\VisualEditor\Service\LocalizationService;

final readonly class RenderContentAreaEventListener
{
    public function __construct(
        private EditModeService $editModeService,
        private LocalizationService $localizationService,
    ) {
    }

    #[AsEventListener]
    public function __invoke(ModifyRenderedContentAreaEvent|V13RenderContentAreaEvent $event): void
    {
        if (!$this->editModeService->isEditMode($event->getRequest())) {
            return;
        }

        $this->editModeService->init($event->getRequest());

        $tagFactory = GeneralUtility::makeInstance(EditorComponentFactory::class);
        $tag = $tagFactory->getContentArea(
            $event->getRequest(),
            $event->getContentArea()->getColPos(),
            $event->getContentArea()->getName(),
            content: $event->getRenderedContentArea(),
            allowedContentTypes: $event->getContentArea()->getAllowedContentTypes(),
            disallowedContentTypes: $event->getContentArea()->getDisallowedContentTypes(),
            containerParent: $event->getContentArea()->getConfiguration()['container']
                                // Backwards compatibility for TYPO3 13: (TODO remove this in TYPO3 15)
                                ?? $event->getContentArea()->getConfiguration()['tx_container_parent']
                                ?? null,
        );

        $event->setRenderedContentArea($tag->getTagBuilder()->render());
    }
}
