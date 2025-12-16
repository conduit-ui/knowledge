<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Entry;
use Illuminate\Support\Facades\View;

class StaticSitePublisher
{
    /**
     * Publish the static site to the given directory.
     */
    public function publish(string $outputDir): void
    {
        // Create output directory
        if (! is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        // Generate index page
        $this->generateIndexPage($outputDir);

        // Generate individual entry pages
        $this->generateEntryPages($outputDir);

        // Generate categories page
        $this->generateCategoriesPage($outputDir);

        // Generate tags page
        $this->generateTagsPage($outputDir);
    }

    /**
     * Generate the index page.
     */
    private function generateIndexPage(string $outputDir): void
    {
        $entries = Entry::orderBy('created_at', 'desc')->get();

        $html = View::make('site.index', ['entries' => $entries])->render();

        file_put_contents("{$outputDir}/index.html", $html);
    }

    /**
     * Generate individual entry pages.
     */
    private function generateEntryPages(string $outputDir): void
    {
        $entries = Entry::all();

        foreach ($entries as $entry) {
            $html = View::make('site.entry', ['entry' => $entry])->render();

            file_put_contents("{$outputDir}/entry-{$entry->id}.html", $html);
        }
    }

    /**
     * Generate the categories page.
     */
    private function generateCategoriesPage(string $outputDir): void
    {
        $categories = Entry::selectRaw('category, COUNT(*) as count')
            ->whereNotNull('category')
            ->groupBy('category')
            ->get()
            ->map(function ($category) {
                $entries = Entry::where('category', $category->category)->get();

                return (object) [
                    'name' => $category->category,
                    'count' => $category->count,
                    'entries' => $entries,
                ];
            });

        $html = View::make('site.categories', ['categories' => $categories])->render();

        file_put_contents("{$outputDir}/categories.html", $html);
    }

    /**
     * Generate the tags page.
     */
    private function generateTagsPage(string $outputDir): void
    {
        // Collect all tags with their entries
        $tagsData = [];

        $entries = Entry::whereNotNull('tags')->get();

        foreach ($entries as $entry) {
            if ($entry->tags) {
                foreach ($entry->tags as $tag) {
                    if (! isset($tagsData[$tag])) {
                        $tagsData[$tag] = [
                            'name' => $tag,
                            'slug' => $this->slugify($tag),
                            'entries' => [],
                        ];
                    }
                    $tagsData[$tag]['entries'][] = $entry;
                }
            }
        }

        // Sort tags alphabetically
        ksort($tagsData);

        // Convert to objects and add counts
        $tags = array_map(function ($tagData) {
            return (object) [
                'name' => $tagData['name'],
                'slug' => $tagData['slug'],
                'count' => count($tagData['entries']),
                'entries' => $tagData['entries'],
            ];
        }, $tagsData);

        $html = View::make('site.tags', ['tags' => $tags])->render();

        file_put_contents("{$outputDir}/tags.html", $html);
    }

    /**
     * Convert a string to a slug.
     */
    private function slugify(string $text): string
    {
        $text = preg_replace('~[^\pL\d]+~u', '-', $text) ?? $text;
        $converted = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
        $text = is_string($converted) ? $converted : $text;
        $text = preg_replace('~[^-\w]+~', '', $text) ?? $text;
        $text = trim($text, '-');
        $text = preg_replace('~-+~', '-', $text) ?? $text;

        return strtolower($text);
    }
}
