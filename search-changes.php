<?php

class FunctionReportGenerator {
    private $url;
    private $folder;
    private $project;
    private $outputFile;
    private $changeKeyword = '';

    public function __construct($url, $folder, $project, $outputFile, $changeKeyword = null) {
        $this->url = $url;
        $this->folder = $folder;
        $this->project = $project;
        $this->outputFile = $outputFile;
        $this->changeKeyword = $changeKeyword;
    }

    public function generateReport() {
        $html = $this->fetchHtml($this->url);
        $functions = $this->extractFunctions($html);
        $reportContent = $this->formatHtmlOutput($functions);
        file_put_contents($this->outputFile, $reportContent);
    }

    private function fetchHtml($url) {
        $html = file_get_contents($url);
        if ($html === false) {
            throw new Exception("Failed to fetch URL: $url");
        }
        return $html;
    }

    private function extractFunctions($html) {
        $dom = new DOMDocument();
        @$dom->loadHTML($html);

        $h1Tags = $dom->getElementsByTagName('h1');
        $result = [];

        foreach ($h1Tags as $h1) {
            if (empty($this->changeKeyword)) {
                $h1Text = $h1->nodeValue;
                if (strpos($h1Text, '::') !== false) {
                    $parts = explode('::', $h1Text);
                    $classVal = trim(end($parts));
                    if ($classVal == '__construct') {
                        $result[] = 'new ' . $parts[0];
                    } else {
                        $result[] = $classVal;
                    }
                } else {
                    $result[] = $h1Text;
                }
            } else {
                $nextSibling = $h1->nextSibling;
                while ($nextSibling) {
                    if ($nextSibling->nodeName === 'table') {
                        if (strpos($nextSibling->textContent, $this->changeKeyword) !== false) {
                            $h1Text = $h1->nodeValue;
                            if (strpos($h1Text, '::') !== false) {
                                $parts = explode('::', $h1Text);
                                $result[] = trim(end($parts));
                            } else {
                                $result[] = $h1Text;
                            }
                        }
                        break;
                    }
                    $nextSibling = $nextSibling->nextSibling;
                }
            }
        }

        return $result;
    }

    private function searchFunctions($function) {
        $command = "rg --pcre2 '[^\w>:.(?!function)]\\s+" . $function . "\\(' -S -t php " . $this->folder . $this->project;
        $output = [];
        $return_var = 0;
        exec($command, $output, $return_var);
        return $output;
    }

    private function formatHtmlOutput($functions) {
        $htmlOutput = "<html><head><style>
            body { font-family: Arial, sans-serif; }
            .function { margin-bottom: 20px; }
            .function-name { font-weight: bold; }
            .usage { margin-left: 20px; }
            pre { background: #f4f4f4; padding: 10px; border: 1px solid #ddd; }
            </style></head><body>";
        $htmlOutput .= "<h1>Return Type Changed Function on " . $this->project . "</h1>";

        foreach ($functions as $function) {
            $usedFunctions = $this->searchFunctions($function);
            if (!empty($usedFunctions)) {
                $htmlOutput .= "<div class='function'><div class='function-name'>- $function</div>";
                $htmlOutput .= "<div class='usage'><pre>" . str_replace($this->folder, '', implode("\n", $usedFunctions)) . "</pre></div>";
            }
            $htmlOutput .= "</div>";
        }
        $htmlOutput .= "</body></html>";
        return $htmlOutput;
    }
}

// Kullanım örneği
$url = "php-change-log.html"; // Replace with the actual URL
$mainFolder = "/Users/denizvatan/";  // Replace with the actual folder path
$project = 'backend'; // your project folder
$outputFile = "all-changes-backend-output.html";
//$outputFile = "return-type-changes-backend-output.html";

$reportGenerator = new FunctionReportGenerator($url, $mainFolder, $project, $outputFile);
//$reportGenerator = new FunctionReportGenerator($url, $mainFolder, $project, $outputFile, 'return type');
$reportGenerator->generateReport();

