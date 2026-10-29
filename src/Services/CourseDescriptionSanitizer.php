<?php

namespace Modules\Glitter\Learning\Services;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * 강좌 상세 설명의 작은 서식 허용 목록. 저장과 미리보기는 이 결과만 사용합니다.
 */
class CourseDescriptionSanitizer
{
    private const ALLOWED = [
        'h2', 'h3', 'p', 'ul', 'ol', 'li', 'strong', 'em', 'u', 's', 'del', 'br', 'blockquote',
        'table', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td', 'caption', 'a', 'img',
    ];

    private const DISCARD = ['script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'button', 'svg', 'math'];

    public function sanitize(string $html): string
    {
        if ($html === '') {
            return '';
        }

        $previous = libxml_use_internal_errors(true);
        try {
            $document = new DOMDocument('1.0', 'UTF-8');
            if (! $document->loadHTML('<?xml encoding="UTF-8"><div id="glitter-course-content">'.$html.'</div>', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING)) {
                return htmlspecialchars($html, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            }

            $root = $document->getElementById('glitter-course-content');
            if (! $root) {
                return htmlspecialchars($html, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            }

            $this->cleanChildren($root);
            $clean = '';
            foreach ($root->childNodes as $child) {
                $clean .= $document->saveHTML($child);
            }

            return $clean;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private function cleanChildren(DOMNode $parent): void
    {
        // Snapshot the original children so moving nodes cannot make the loop skip
        // a sibling. Newly moved nodes are cleaned explicitly by cleanNode().
        $children = [];
        for ($child = $parent->firstChild; $child !== null; $child = $child->nextSibling) {
            $children[] = $child;
        }

        foreach ($children as $child) {
            if ($child->parentNode === $parent) {
                $this->cleanNode($parent, $child);
            }
        }
    }

    private function cleanNode(DOMNode $parent, DOMNode $child): void
    {
        if (! $child instanceof DOMElement) {
            if ($child->nodeType !== XML_TEXT_NODE) {
                $parent->removeChild($child);
            }

            return;
        }

        $tag = strtolower($child->tagName);
        if (in_array($tag, self::DISCARD, true)) {
            $parent->removeChild($child);

            return;
        }

        // CKEditor 5 wraps tables in <figure class="table">. The wrapper is
        // presentation-only, so unwrap it without bypassing sanitization of any
        // moved child, including nested wrappers.
        if ($tag === 'figure') {
            $movedChildren = [];
            for ($nested = $child->firstChild; $nested !== null; $nested = $nested->nextSibling) {
                $movedChildren[] = $nested;
            }

            foreach ($movedChildren as $nested) {
                $parent->insertBefore($nested, $child);
            }
            $parent->removeChild($child);

            foreach ($movedChildren as $nested) {
                if ($nested->parentNode === $parent) {
                    $this->cleanNode($parent, $nested);
                }
            }

            return;
        }

        $this->cleanChildren($child);
        if (! in_array($tag, self::ALLOWED, true)) {
            while ($child->firstChild) {
                $parent->insertBefore($child->firstChild, $child);
            }
            $parent->removeChild($child);

            return;
        }

        $this->cleanAttributes($child, $tag);
        if ($tag === 'img' && ! $child->hasAttribute('src')) {
            $parent->removeChild($child);
        }
    }

    private function cleanAttributes(DOMElement $element, string $tag): void
    {
        $allowed = match ($tag) {
            'a' => ['href', 'target'],
            'img' => ['src', 'alt'],
            'td', 'th' => ['colspan', 'rowspan'],
            default => [],
        };

        for ($index = $element->attributes->length - 1; $index >= 0; $index--) {
            $attribute = $element->attributes->item($index);
            if (! $attribute || ! in_array(strtolower($attribute->name), $allowed, true)) {
                if ($attribute) {
                    $element->removeAttributeNode($attribute);
                }

                continue;
            }

            $name = strtolower($attribute->name);
            $value = trim($attribute->value);
            if ($name === 'href' && ! $this->isSafeHref($value)) {
                $element->removeAttribute('href');
            } elseif ($name === 'src' && ($tag !== 'img' || ! $this->isSafeImageSrc($value))) {
                $element->removeAttribute('src');
            } elseif (in_array($name, ['colspan', 'rowspan'], true) && ! preg_match('/^[1-9][0-9]?$/', $value)) {
                $element->removeAttribute($name);
            } elseif ($name === 'target' && $value !== '_blank') {
                $element->removeAttribute('target');
            }
        }

        if ($tag === 'a' && $element->hasAttribute('target') && $element->getAttribute('target') === '_blank') {
            $element->setAttribute('rel', 'noopener noreferrer');
        }
    }

    private function isSafeHref(string $href): bool
    {
        if ($href === '' || preg_match('/[\x00-\x20\\\\]/', $href)) {
            return false;
        }

        $parts = parse_url($href);
        if ($parts === false) {
            return false;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        if ($scheme !== '') {
            if (! in_array($scheme, ['http', 'https', 'mailto'], true)) {
                return false;
            }

            if (in_array($scheme, ['http', 'https'], true) && empty($parts['host'])) {
                return false;
            }

            return true;
        }

        // Relative links are allowed; protocol-relative URLs are not.
        return ! str_starts_with($href, '//');
    }

    private function isSafeImageSrc(string $src): bool
    {
        return (bool) preg_match('#^/api/plugins/sirsoft-ckeditor5/images/[a-f0-9]{12}$#', $src);
    }
}
