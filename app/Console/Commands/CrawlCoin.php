<?php

namespace App\Console\Commands;


use App\Observers\CrawlerObserver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Spatie\Browsershot\Browsershot;
use Spatie\Crawler\Crawler;

class CrawlCoin extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:crawl-coin';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $pages = static::pages();
        //choose random 100 pages
        $pages = array_slice($pages, 0, 100);
        //random sort pages
        shuffle($pages);


//        $pages = [
//            'https://demo.coin-dev.eu/explorer/indices/ihdi/inequality–adjusted-human-development-index',
//            'https://demo.coin-dev.eu/explorer/indices/ceii/clean-energy-innovation-index'];
        static::get($pages);
    }

    public static function get(array $pages = [])
    {

        foreach ($pages as $page) {
            Log::debug('Browsershot page: ' . $page);
            $html = static::shot($page);

            if ($html) {
                Log::debug('Save page: ' . $page);
                $filename = 'crawled/' . static::pageName($page) . '.html';
                Storage::disk('local')->put($filename, $html);
            }
        }
    }

    public static function shot(string $url)
    {
        $parse = [];
        // Use Browsershot to properly render JavaScript-heavy pages
        try {
            $parse = Browsershot::url($url)
                ->waitUntilNetworkIdle() // Wait for network to be idle (important for SPAs)
                ->timeout(20) // Increase timeout to allow full rendering
                ->noSandbox()
                ->evaluate('document.documentElement.outerHTML'); // Get complete rendered HTML
        } catch (\Exception $e) {
            Log::error('Browsershot error for URL: ' . $url . ' - ' . $e->getMessage());
            //return '';
        }
        return $parse;

    }

    public static function pageName(string $url)
    {
        $parts = explode('/', $url);
        $lastPart = end($parts);
        // Remove query parameters if present
        $lastPart = strtok($lastPart, '?');
        // Remove file extension if present
        $lastPart = strtok($lastPart, '.');
        return $lastPart;
    }

    public static function pages()
    {
        //get pages from here https://composite-indicators.jrc.ec.europa.eu/api/v1/en/sitemap.xml
        $sitemapUrl = 'https://composite-indicators.jrc.ec.europa.eu/api/v1/en/sitemap.xml';
        $sitemapContent = file_get_contents($sitemapUrl);
        if ($sitemapContent === false) {
            Log::error('Failed to fetch sitemap content from ' . $sitemapUrl);
            return [];
        }
        $xml = simplexml_load_string($sitemapContent);
        //loop pages
        $pages = [];
        foreach ($xml->url as $url) {
            $loc = (string)$url->loc;
            if (strpos($loc, 'explorer/indices/') !== false || strpos($loc, 'explorer/scoreboards/') !== false) {
                $pages[] = $loc;
            }
        }

        return $pages;

    }

    public static function crawl()
    {
        Log::debug('Crawl Coin star');
        $url = 'https://demo.coin-dev.eu/';
        //$url = 'https://demo.api.coin-dev.eu/api/v1/en/cms/chart-v2/explorer-map?dt_tp_id=10&index_code=ggi';
        $url = 'https://demo.coin-dev.eu/explorer/indices/bamli/basel-anti-money-laundering-index';
        Crawler::create()
            ->setCrawlObserver(new CrawlerObserver())
            ->ignoreRobots()
            ->executeJavaScript()
            ->setTotalCrawlLimit(1)
            ->startCrawling($url);


        Log::debug('end');
    }
}
