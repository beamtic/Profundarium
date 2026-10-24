<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NoteMetadataTest extends TestCase
{
    public function test_metadata_overrides_page_title_and_sets_description_language_and_direction(): void
    {
        $document = $this->renderNote(<<<'MD'
---
title: Metadata title
description: A useful description
lang: ar-EG
dir: rtl
opengraph:
  title: Separate social title
---
# Content heading
MD);
        $this->assertSame('Metadata title', $document->getElementsByTagName('title')[0]->textContent);
        $this->assertSame('Content heading', $document->getElementsByTagName('h1')[0]->textContent);
        $this->assertSame('A useful description', $document->querySelector('meta[name="description"]')->getAttribute('content'));
        $this->assertSame('ar-EG', $document->documentElement->getAttribute('lang'));
        $this->assertSame('rtl', $document->documentElement->getAttribute('dir'));
        $this->assertSame('Separate social title', $document->querySelector('meta[property="og:title"]')->getAttribute('content'));
    }

    public function test_values_are_escaped_and_invalid_attributes_fall_back(): void
    {
        $document = $this->renderNote(<<<'MD'
---
title: '<script>alert(1)</script> & "title"'
description: '"><script>alert(2)</script>'
lang: 'en" onload="alert(3)'
dir: 'rtl" onload="alert(4)'
---
# Heading
MD);
        $this->assertSame('<script>alert(1)</script> & "title"', $document->getElementsByTagName('title')[0]->textContent);
        $this->assertSame('"><script>alert(2)</script>', $document->querySelector('meta[name="description"]')->getAttribute('content'));
        $this->assertCount(0, $document->getElementsByTagName('script'));
        $this->assertSame('en', $document->documentElement->getAttribute('lang'));
        $this->assertSame('ltr', $document->documentElement->getAttribute('dir'));
        $this->assertFalse($document->documentElement->hasAttribute('onload'));
    }

    public function test_absent_invalid_and_blank_metadata_preserve_defaults(): void
    {
        foreach (["# Heading", "---\ntitle: []\ndescription: {}\nlang: [en]\ndir: false\n---\n# Heading", "---\ntitle: '  '\nlang: invalid_locale\ndir: auto\n---\n# Heading", "---\ntitle: [broken\n---\n# Heading"] as $markdown) {
            $document = $this->renderNote($markdown);
            $this->assertSame('Heading', $document->getElementsByTagName('title')[0]->textContent);
            $this->assertNull($document->querySelector('meta[name="description"]'));
            $this->assertSame('en', $document->documentElement->getAttribute('lang'));
            $this->assertSame('ltr', $document->documentElement->getAttribute('dir'));
        }
        $document = $this->renderNote('No heading');
        $this->assertSame('Note', $document->getElementsByTagName('title')[0]->textContent);
    }

    public function test_language_subtags_are_supported(): void
    {
        $document = $this->renderNote("---\nlang: zh-Hant-TW\ndir: ltr\n---\nText");
        $this->assertSame('zh-Hant-TW', $document->documentElement->getAttribute('lang'));
    }

    private int $noteNumber = 0;

    private function renderNote(string $markdown): \Dom\HTMLDocument
    {
        config(['services.hedgedoc.url' => 'https://notes.example.test']);
        $note = 'metadata-' . ++$this->noteNumber;
        Http::preventStrayRequests();
        Http::fake([
            'https://notes.example.test/' . $note . '/download' => Http::response($markdown),
            '*/publish' => Http::response('', 404),
            '*/info' => Http::response('', 404),
        ]);
        $response = $this->get('/profundarium/' . $note)->assertOk();
        return \Dom\HTMLDocument::createFromString($response->getContent(), LIBXML_NOERROR, 'UTF-8');
    }
}
