#!/usr/bin/env php
<?php
/**
 * Test ContentService - Verify book and chapter fetching
 */

define('CLI_SCRIPT', true);
define('WP_USE_THEMES', false);

// Load WordPress from Bedrock structure
require_once('/var/www/html/web/wp/wp-load.php');

// Load ContentService
require_once('/var/www/html/web/app/plugins/pressbooks-lti-platform/Services/ContentService.php');

use PB_LTI\Services\ContentService;

echo "🧪 Testing ContentService\n";
echo "========================\n\n";

// Test 1: Get all books
echo "📚 Test 1: Get all books\n";
$books = ContentService::get_all_books();
echo "Found " . count($books) . " books:\n";
foreach ($books as $book) {
    echo "  - {$book['title']} (ID: {$book['id']})\n";
    echo "    URL: {$book['url']}\n";
}
echo "\n";

// Test 2: Get book structure for first book (if exists)
if (!empty($books)) {
    $book = $books[0];
    echo "📖 Test 2: Get structure for book '{$book['title']}' (ID: {$book['id']})\n";
    $structure = ContentService::get_book_structure($book['id']);

    echo "Book info:\n";
    echo "  Title: {$structure['book_info']['title']}\n";
    echo "  URL: {$structure['book_info']['url']}\n\n";

    if (!empty($structure['front_matter'])) {
        echo "Front Matter (" . count($structure['front_matter']) . " items):\n";
        foreach ($structure['front_matter'] as $item) {
            echo "  - {$item['title']}\n";
        }
        echo "\n";
    }

    if (!empty($structure['chapters'])) {
        echo "Chapters (" . count($structure['chapters']) . " items):\n";
        foreach ($structure['chapters'] as $chapter) {
            echo "  - {$chapter['title']}\n";
        }
        echo "\n";
    }

    if (!empty($structure['parts'])) {
        echo "Parts (" . count($structure['parts']) . " items):\n";
        foreach ($structure['parts'] as $part) {
            echo "  - {$part['title']} (" . count($part['chapters']) . " chapters)\n";
        }
        echo "\n";
    }

    if (!empty($structure['back_matter'])) {
        echo "Back Matter (" . count($structure['back_matter']) . " items):\n";
        foreach ($structure['back_matter'] as $item) {
            echo "  - {$item['title']}\n";
        }
        echo "\n";
    }

    // Test 3: Get content item
    echo "📄 Test 3: Get content item for whole book\n";
    $content_item = ContentService::get_content_item($book['id']);
    if ($content_item) {
        echo "Content item:\n";
        echo "  Type: {$content_item['type']}\n";
        echo "  Title: {$content_item['title']}\n";
        echo "  URL: {$content_item['url']}\n";
        echo "  Text: {$content_item['text']}\n";
    }
    echo "\n";

    // Test 4: Get content item for specific chapter
    if (!empty($structure['chapters'])) {
        $chapter = $structure['chapters'][0];
        echo "📄 Test 4: Get content item for chapter '{$chapter['title']}'\n";
        $content_item = ContentService::get_content_item($book['id'], $chapter['id']);
        if ($content_item) {
            echo "Content item:\n";
            echo "  Type: {$content_item['type']}\n";
            echo "  Title: {$content_item['title']}\n";
            echo "  URL: {$content_item['url']}\n";
        }
        echo "\n";
    }
}

echo "✅ ContentService tests complete\n";
