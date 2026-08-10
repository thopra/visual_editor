<?php

declare(strict_types=1);


namespace TYPO3\CMS\VisualEditor\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\VisualEditor\Editor\PageEditingInformation;

class PageEditingInformationMiddleware implements MiddlewareInterface
{
    /**
     * @param  \Psr\Http\Message\ServerRequestInterface $request
     * @param  \Psr\Http\Server\RequestHandlerInterface $handler
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface
    {
        $pageEditingInformation = (GeneralUtility::makeInstance(PageEditingInformation::class))
            ->withRequest($request);

        $request = $request->withAttribute('frontend.visualEditor', $pageEditingInformation);

        return $handler->handle($request);
    }
}
