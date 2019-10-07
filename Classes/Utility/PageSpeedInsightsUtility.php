<?php
declare(strict_types=1);

namespace Haassie\PageSpeedInsights\Utility;

use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class PageSpeedInsightsUtility
{
    /**
     * @param string $url
     * @param string $strategy
     * @param array $categories
     * @param string $reference
     * @param int $pageId
     * @param string $key
     * @return array
     */
    public static function checkUrl(string $url, string $strategy = 'mobile', array $categories = ['performance'], string $reference = '', int $pageId = 0, string $key = ''): array
    {
        $result = self::getResult($url, $strategy, $categories, $reference, $pageId, $key);
        self::saveResult($result);

        return $result;
    }

    /**
     * @param string $url
     * @param string $strategy
     * @param array $categories
     * @param string $reference
     * @param int $pageId
     * @param string $key
     * @return array
     */
    protected static function getResult(string $url, string $strategy = 'mobile', array $categories = ['performance'], string $reference = '', int $pageId = 0, string $key = ''): array
    {
        $categoryParameters = '&category=' . implode('&category=', $categories);
        $apiUrl = 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed?strategy=' . $strategy . $categoryParameters . '&url=' . urlencode($url);
        if (!empty($key)) {
            $apiUrl .= '&key=' . $key;
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiUrl);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        $rawData = curl_exec($ch);
        $data = json_decode($rawData);
        $categoriesArray = (array)$data->lighthouseResult->categories;

        curl_close($ch);

        $returnData = [
            'date' => time(),
            'url' => $url,
            'pageId' => $pageId,
            'strategy' => $strategy,
            'reference' => $reference
        ];
        if (property_exists($data, 'error')) {
            $returnData['error']['message'] = (string)$data->error->message;
            return $returnData;
        }

        if (property_exists($categoriesArray['performance'], 'score')) {
            $returnData['performance']['score'] = (float)$categoriesArray['performance']->score * 100;
        }

        if (is_array($data->lighthouseResult->audits->diagnostics->details->items) && !empty($data->lighthouseResult->audits->diagnostics->details->items[0]->totalByteWeight)) {
            $returnData['performance']['totalBytes'] = $data->lighthouseResult->audits->diagnostics->details->items[0]->totalByteWeight;
        }

        if (property_exists($data->lighthouseResult->audits->interactive, 'displayValue')) {
            $returnData['performance']['time_to_interactive'] = (float)$data->lighthouseResult->audits->interactive->displayValue;
        }

        if (property_exists($categoriesArray['seo'], 'score')) {
            $returnData['seo']['score'] = $categoriesArray['seo']->score * 100;
        }

        if (property_exists($categoriesArray['accessibility'], 'score')) {
            $returnData['accessibility']['score'] = (float)$categoriesArray['accessibility']->score * 100;
        }

        if (property_exists($categoriesArray['best-practices'], 'score')) {
            $returnData['best-practices']['score'] = (float)$categoriesArray['best-practices']->score * 100;
        }

        if (property_exists($data->lighthouseResult->categories->pwa, 'score')) {
            $returnData['pwa']['score'] = (float)$data->lighthouseResult->categories->pwa->score * 100;
        }

        return $returnData;
    }

    /**
     * @param array $result
     */
    protected static function saveResult(array $result): void
    {
        $queryBuilderResults = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable('tx_pagespeedinsights_results')->createQueryBuilder();
        $queryBuilderResults
            ->insert('tx_pagespeedinsights_results')
            ->values([
                'pid' => (int)$result['pageId'],
                'page_id' => (int)$result['pageId'],
                'tstamp' => time(),
                'date' => $result['date'],
                'url' => $result['url'],
                'strategy' => $result['strategy'],
                'reference' => $result['reference'],
                'seo_score' => $result['seo']['score'],
                'performance_score' => $result['performance']['score'],
                'pwa_score' => $result['pwa']['score'],
                'accessibility_score' => $result['accessibility']['score'],
                'bestpractices_score' => $result['best-practices']['score'],
            ])
            ->execute();
    }
}