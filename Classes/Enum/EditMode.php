<?php

declare(strict_types=1);

namespace TYPO3\CMS\VisualEditor\Enum;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;

enum EditMode: string
{
    /* The current request is in context of the backend module and should enable editing */
    case BackendModule = 'backend_module';
    /* The current request is not coming from the backend module, editing is disabled */
    case Disabled = 'disabled';
    /* The current request has no authenticated backend user and editing should not be enabled */
    case NotAuthorized = 'not_authorized';

    public static function fromRequest(ServerRequestInterface $request): static
    {
        if (!($GLOBALS['BE_USER'] ?? null) instanceof BackendUserAuthentication) {
            return self::NotAuthorized;
        }

        $queryParams = $request->getQueryParams();
        if (isset($queryParams['editMode'])) {
            return self::BackendModule;
        }

        return self::Disabled;
    }

    public function isEditingEnabled(): bool
    {
        return match($this) {
            self::BackendModule => true,
            default => false,
        };
    }
}
