<?php

namespace App\Observers;

use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;
use Spatie\Crawler\CrawlObservers\CrawlObserver;

class CrawlerObserver extends CrawlObserver
{


    public function crawled(
        UriInterface $url,
        ResponseInterface $response,
        ?UriInterface $foundOnUrl = null,
        ?string $linkText = null,
    ): void {
        $content = $response->getBody()->getContents();

        // Log or save the content
        Log::info('Crawled URL: ' . $url);

        // Save content to a file
        $filename = 'crawled/' . md5((string)$url) . '.html';
        Log::info('Crawled filename: ' . $filename);
        Storage::disk('local')->put($filename, $content);

        // Alternatively, you can process the content directly here
        // For example, parse HTML, extract specific data, etc.
    }

    public function crawlFailed(
        UriInterface $url,
        RequestException $requestException,
        ?UriInterface $foundOnUrl = null,
        ?string $linkText = null,
    ): void {
        Log::error('Failed to crawl: ' . $url, [
            'exception' => $requestException->getMessage(),
        ]);
    }
}
