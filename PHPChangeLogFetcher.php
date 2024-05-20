<?php

require 'simple_html_dom.php';

class PHPChangelogFetcher
{
    private $baseUrl = "https://www.php.net/manual/en/";
    private $functionsIndexUrl = "indexes.functions.php";
    private $outputFile;

    public function __construct($outputFile)
    {
        $this->outputFile = $outputFile;
    }

    public function fetch()
    {
        $this->initializeOutputFile();
        $html = $this->getHtmlFromUrl($this->baseUrl . $this->functionsIndexUrl);

        foreach ($html->find('a') as $element) {
            $functionUrl = $this->baseUrl . $element->href;
            echo "Fetching changelog for " . $element->innertext . "...\n";
            $this->fetchChangelog($functionUrl);
            echo "\n\n";
        }

        $this->finalizeOutputFile();
    }

    public function continueFromTitle($title)
    {
        $html = $this->getHtmlFromUrl($this->baseUrl . $this->functionsIndexUrl);

        $target = null;
        foreach ($html->find('a') as $element) {
            if ($element->innertext == $title) {
                $target = $element;
                break;
            }
        }

        if ($target === null) {
            echo "Title not found: $title\n";
            return;
        }

        $startCollecting = false;
        $urlsAfterTarget = [];

        foreach ($html->find('a') as $element) {
            if ($startCollecting) {
                $urlsAfterTarget[] = $element->href;
            }
            if ($element === $target) {
                $startCollecting = true;
            }
        }

        foreach ($urlsAfterTarget as $url) {
            $functionUrl = $this->baseUrl . $url;
            echo "Fetching changelog for " . $url . "...\n";
            $this->fetchChangelog($functionUrl);
            echo "\n\n";
        }
    }

    private function initializeOutputFile()
    {
        file_put_contents($this->outputFile, $this->getHtmlHeader());
    }

    private function finalizeOutputFile()
    {
        file_put_contents($this->outputFile, $this->getHtmlFooter(), FILE_APPEND);
    }

    private function getHtmlHeader()
    {
        return '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PHP Change Log</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f9f9f9;
        }
        h1 {
            text-align: center;
            color: #333;
        }
        h3 {
            text-align: center;
            color: #666;
            margin-top: 0;
        }
        table {
            width: 80%;
            margin: 20px auto;
            border-collapse: collapse;
            background-color: #fff;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        th, td {
            padding: 12px;
            text-align: left;
        }
        thead {
            background-color: #007BFF;
            color: #fff;
        }
        tbody tr:nth-child(even) {
            background-color: #f2f2f2;
        }
        a {
            color: #007BFF;
            text-decoration: none;
        }
        a:hover {
            text-decoration: underline;
        }
        code {
            background-color: #f2f2f2;
            padding: 2px 4px;
            border-radius: 4px;
        }
        .function {
            font-weight: bold;
        }
        .classname {
            color: #d9534f;
        }
    </style>
</head>
<body>';
    }

    private function getHtmlFooter()
    {
        return '</body></html>';
    }

    private function getHtmlFromUrl($url)
    {
        $response = $this->getUrlContent($url);
        return str_get_html($response);
    }

    private function fetchChangelog($url)
    {
        $html = $this->getHtmlFromUrl($url);
        $found = false;

        foreach ($html->find('h3') as $h3) {
            if ($h3->innertext == 'Changelog') {
                $found = true;
                $this->appendToOutputFile("<h1>" . $html->find('h1', 0)->innertext . "</h1>");
                $this->appendToOutputFile("<h3>Change Log for $url</h3>");

                $table = $h3->next_sibling();
                if ($table) {
                    $this->appendToOutputFile($table->outertext);
                } else {
                    echo "No changelog table found for $url.<br>";
                }
            }
        }

        if (!$found) {
            echo "No changelog found for $url.\n";
        }
    }

    private function appendToOutputFile($content)
    {
        file_put_contents($this->outputFile, $content, FILE_APPEND);
    }

    private function getUrlContent($url)
    {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

        $response = curl_exec($ch);

        if ($response === false) {
            echo 'Error fetching the page: ' . curl_error($ch);
        }

        curl_close($ch);
        return $response;
    }
}

$outputFile = 'php-change-log.html';

$changelogFetcher = new PHPChangelogFetcher($outputFile);
$changelogFetcher->fetch();

// If script fails continue from title
// $changelogFetcher->continueFromTitle('IntlDateFormatter::create');
