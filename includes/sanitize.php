<?php
/**
 * HTML sanitisation for rich-text content submitted by admins/editors
 * (news articles, page content, event descriptions, committee bios).
 *
 * Implemented with PHP's built-in DOMDocument so no external dependency
 * or Composer install is required on shared hosting. Uses a strict
 * allow-list of tags and attributes — everything else is stripped.
 */

function sanitize_html(string $html): string
{
    if (trim($html) === '') {
        return '';
    }

    $allowedTags = [
        'p', 'br', 'strong', 'b', 'em', 'i', 'u',
        'h2', 'h3', 'h4',
        'ul', 'ol', 'li',
        'a', 'img',
        'blockquote',
    ];

    $allowedAttributes = [
        'a' => ['href', 'title', 'target', 'rel'],
        'img' => ['src', 'alt', 'title', 'width', 'height', 'loading'],
    ];

    $dom = new DOMDocument('1.0', 'UTF-8');
    libxml_use_internal_errors(true);
    // Wrap in a template so fragments (no <html><body>) parse predictably.
    $dom->loadHTML('<?xml encoding="UTF-8"><div id="sanitize-root">' . $html . '</div>', LIBXML_NOERROR | LIBXML_NOWARNING);
    libxml_clear_errors();

    $root = $dom->getElementById('sanitize-root');
    if (!$root) {
        return '';
    }

    sanitize_walk($dom, $root, $allowedTags, $allowedAttributes);

    $out = '';
    foreach (iterator_to_array($root->childNodes) as $child) {
        $out .= $dom->saveHTML($child);
    }
    return trim($out);
}

function sanitize_walk(DOMDocument $dom, DOMNode $node, array $allowedTags, array $allowedAttributes): void
{
    $children = iterator_to_array($node->childNodes);

    foreach ($children as $child) {
        if ($child->nodeType === XML_TEXT_NODE) {
            continue;
        }

        if ($child->nodeType !== XML_ELEMENT_NODE) {
            $node->removeChild($child);
            continue;
        }

        /** @var DOMElement $child */
        $tag = strtolower($child->tagName);

        if (!in_array($tag, $allowedTags, true)) {
            // Unwrap: keep children (e.g. text inside a <script> is dropped entirely for safety,
            // but text inside a disallowed but harmless wrapper like <span> is preserved).
            if ($tag === 'script' || $tag === 'style' || $tag === 'iframe' || $tag === 'object' || $tag === 'embed') {
                $node->removeChild($child);
                continue;
            }
            sanitize_walk($dom, $child, $allowedTags, $allowedAttributes);
            while ($child->firstChild) {
                $node->insertBefore($child->firstChild, $child);
            }
            $node->removeChild($child);
            continue;
        }

        // Strip disallowed attributes, and neutralise dangerous attribute values.
        $allowedAttrsForTag = $allowedAttributes[$tag] ?? [];
        foreach (iterator_to_array($child->attributes ?? []) as $attr) {
            $name = strtolower($attr->name);
            if (!in_array($name, $allowedAttrsForTag, true) || str_starts_with($name, 'on')) {
                $child->removeAttribute($attr->name);
                continue;
            }
            if ($name === 'href' || $name === 'src') {
                $value = trim($attr->value);
                $lower = strtolower($value);
                if (str_starts_with($lower, 'javascript:') || str_starts_with($lower, 'data:text/html') || str_starts_with($lower, 'vbscript:')) {
                    $child->removeAttribute($attr->name);
                }
            }
        }

        if ($tag === 'a') {
            $child->setAttribute('rel', 'noopener noreferrer');
        }

        sanitize_walk($dom, $child, $allowedTags, $allowedAttributes);
    }
}

/** Strict plain-text sanitiser for form fields (names, subjects, etc). */
function sanitize_text(?string $value, int $maxLength = 255): string
{
    $value = trim($value ?? '');
    $value = strip_tags($value);
    if (mb_strlen($value) > $maxLength) {
        $value = mb_substr($value, 0, $maxLength);
    }
    return $value;
}
