<?php

namespace App\Markdown;

use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;
use Symfony\Component\HtmlSanitizer\Visitor\AttributeSanitizer\AttributeSanitizerInterface;

final class NoteUrlSanitizer implements AttributeSanitizerInterface
{
    public function getSupportedElements(): ?array
    {
        return null;
    }

    public function getSupportedAttributes(): ?array
    {
        return ['src', 'poster'];
    }

    public function sanitizeAttribute(string $element, string $attribute, string $value, HtmlSanitizerConfig $config): ?string
    {
        if (strncasecmp($value, 'data:', 5) !== 0) {
            return $value;
        }

        // Data images cannot become executable documents in an iframe.
        return $element === 'img' && $attribute === 'src'
            && preg_match('~^data:image/(?:png|gif|jpe?g|webp|avif|bmp|x-icon|vnd\.microsoft\.icon|svg\+xml)(?:;[^,]*)?,~i', $value)
                ? $value : null;
    }
}
