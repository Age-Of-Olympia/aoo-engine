<?php

namespace App\View\Action;

/**
 * Shared HTML-escape helper for the action workbench views, which all need the
 * same htmlspecialchars(ENT_QUOTES) one-liner. Accepts int|string so numeric
 * ids/values can be escaped without a cast at the call site.
 */
trait EscapesHtmlTrait
{
    private function esc(int|string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}
