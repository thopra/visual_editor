<?php

declare(strict_types=1);

namespace TYPO3\CMS\VisualEditor\Editor;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\VisualEditor\Service\EditModeService;

class Editor implements SingletonInterface
{
    public function __construct(
        private EditModeService $editModeService,
    )
    {}

    public function init(ServerRequestInterface $request): void
    {
        $this->editModeService->init($request);
    }
}
