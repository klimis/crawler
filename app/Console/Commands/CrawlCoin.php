<?php

namespace App\Console\Commands;


use App\Observers\CrawlerObserver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
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
    protected $signature = 'crawl:coin';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Crawl coin site';
    protected static $counter = 1;
    protected static $numberOfCountries = 5;
    protected static $rndMinPages = 12;
    protected static $rndMaxPages = 20;
    protected static $rndMinSleep = 1;
    protected static $rndMaxSleep = 4;

    protected $env = 'production'; // or 'production' 'staging', 'development', etc.

    protected $vpnCountries = [
        'Italy',
        'Belgium',
        'Germany',
        'France',
        'Spain',
        'Netherlands',
        'Poland',
        'Sweden',
        'Finland',
        'Austria',
        'Greece',
        'Portugal',
        'Norway',
        'Denmark',
        'Ireland',

    ];

    public function ip()
    {
        //get random country from $vpnCountries
        $result = Process::run('nordvpn connect ' . $this->vpnCountries[array_rand($this->vpnCountries)]);
        return ($result->output());
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        for ($i = 0; $i < self::$numberOfCountries; $i++) { // loop three random countries/users
            $vpn = $this->ip();
            Log::info($vpn);

            $pages = static::pages();
            shuffle($pages);
            $pages = array_slice($pages, 0, rand(self::$rndMinPages, self::$rndMaxPages));

          //  static::appendPages($pages);
            static::env($pages, $this->env);
            static::get($pages);

        }
        Log::debug(static::disconnect());
    }

    public static function disconnect(){
        $result = Process::run('nordvpn disconnect');
        return ($result->output());
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
        }
        return $parse;

    }

    public static function get(array $pages = [])
    {
        foreach ($pages as $page) {
            Log::debug('Browsershot page: ' . $page);
            $html = static::shot($page);
            Log::debug('Sleep for: ' . $_s = rand(self::$rndMinSleep, self::$rndMaxSleep) . ' seconds. Total:'. static::$counter);
            sleep((int)$_s);
            static::$counter++;

        }
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

    public static function pages(string $env = 'production')
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

            if (strpos($loc, 'explorer/indices/') !== false
                || strpos($loc, 'explorer/scoreboards/') !== false
                || strpos($loc, 'explorer/countries-and-territories/') !== false
            ) {
                $pages[] = $loc;
            }
        }
        return $pages;

    }

    public static function env(&$pages = [], $env)
    {
        if ($env !== 'production') {
            //replace for each page the domain to demo.coin-dev.eu
            $pages = array_map(function ($page) {
                return str_replace('composite-indicators.jrc.ec.europa.eu', 'demo.coin-dev.eu', $page);
            }, $pages);
        }
    }

    public static function appendPages(&$pages = [])
    {
        return $pages = array_merge([
            'https://composite-indicators.jrc.ec.europa.eu/explorer',
            'https://composite-indicators.jrc.ec.europa.eu/explorer/indices-and-scoreboards',
            'https://composite-indicators.jrc.ec.europa.eu/explorer/countries-and-territories',
        ], $pages);
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
