<?php

declare(strict_types=1);

namespace YoungDogs\AiSeo\Service;

use YoungDogs\AiSeo\Configuration\ManagedField;

/**
 * Builds the system and user prompt from the configured fields.
 */
final class PromptBuilder
{
    /**
     * @param array<string, ManagedField> $fields
     * @return list<array{role: string, content: string}>
     */
    public function build(array $fields, string $title, string $slug, string $content, string $language): array
    {
        $schema = [];
        $rules = [];
        foreach ($fields as $field) {
            $schema[$field->name] = $field->multiline ? 'string (may span multiple sentences)' : 'string (single line)';
            $rule = sprintf('- "%s" (%s)', $field->name, $field->label);
            if ($field->maxLength > 0) {
                $rule .= sprintf(': at most %d characters', $field->maxLength);
            }
            if ($field->instruction !== '') {
                $rule .= ($field->maxLength > 0 ? '. ' : ': ') . $field->instruction;
            }
            $rules[] = $rule;
        }

        $system = "You are an SEO specialist. Write search engine and social media metadata for a web page based on its content.\n"
            . 'Write all texts in ' . $language . ".\n"
            . "Only use facts from the provided content. Do not invent claims, do not use quotation marks around the whole text, no HTML.\n"
            . "Respond with a single JSON object and nothing else. Keys and rules:\n"
            . implode("\n", $rules) . "\n"
            . 'JSON shape: ' . json_encode($schema, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $user = "Page title: " . $title . "\n"
            . 'Page slug: ' . $slug . "\n\n"
            . "Page content:\n" . ($content !== '' ? $content : '(no content available)');

        return [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $user],
        ];
    }
}
