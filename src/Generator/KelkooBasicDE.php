<?php

namespace ElasticExportKelkooBasicDE\Generator;

use ElasticExport\Helper\ElasticExportCoreHelper;
use ElasticExport\Helper\ElasticExportPriceHelper;
use ElasticExport\Helper\ElasticExportStockHelper;
use Plenty\Modules\DataExchange\Contracts\CSVPluginGenerator;
use Plenty\Modules\Helper\Services\ArrayHelper;
use Plenty\Modules\Item\DataLayer\Models\Record;
use Plenty\Modules\Item\DataLayer\Models\RecordList;
use Plenty\Modules\DataExchange\Models\FormatSetting;
use Plenty\Modules\Helper\Models\KeyValue;
use Plenty\Modules\Item\Search\Contracts\VariationElasticSearchScrollRepositoryContract;
use Plenty\Plugin\Log\Loggable;

/**
 * Class KelkooBasicDE
 *
 * @package ElasticExportKelkooBasicDE\Generator
 */
class KelkooBasicDE extends CSVPluginGenerator
{
	use Loggable;

    /**
     * @var ElasticExportCoreHelper $elasticExportHelper
     */
    private $elasticExportHelper;

	/**
	 * @var ElasticExportStockHelper $elasticExportStockHelper
	 */
    private $elasticExportStockHelper;

	/**
	 * @var ElasticExportPriceHelper $elasticExportPriceHelper
	 */
    private $elasticExportPriceHelper;

    /**
     * @var ArrayHelper $arrayHelper
     */
    private $arrayHelper;

    /**
     * KelkooBasicDE constructor.
	 *
     * @param ArrayHelper $arrayHelper
     */
    public function __construct(ArrayHelper $arrayHelper)
    {
        $this->arrayHelper = $arrayHelper;
    }

    /**
     * @param VariationElasticSearchScrollRepositoryContract $elasticSearch
     * @param array $formatSettings
     * @param array $filter
     */
    protected function generatePluginContent($elasticSearch, array $formatSettings = [], array $filter = [])
    {
        $this->elasticExportHelper = pluginApp(ElasticExportCoreHelper::class);
        $this->elasticExportStockHelper = pluginApp(ElasticExportStockHelper::class);
        $this->elasticExportPriceHelper = pluginApp(ElasticExportPriceHelper::class);

		$settings = $this->arrayHelper->buildMapFromObjectList($formatSettings, 'key', 'value');
		$this->setDelimiter(" ");

		$this->addCSVContent([
			'url',
			'title',
			'description',
			'price',
			'offerid',
			'image',
			'availability',
			'deliverycost',
			'deliveryTime',
			'unitaryPrice',
			'ean',
			'ecotax',
		]);

        $limitReached = false;
        $lines = 0;
        $startTime = microtime(true);

        if($elasticSearch instanceof VariationElasticSearchScrollRepositoryContract)
		{
			do
			{
				if($limitReached === true)
				{
					break;
				}

				$this->getLogger(__METHOD__)->debug('ElasticExportKelkooBasicDE::log.writtenlines', ['lines written' => $lines]);

				$esStartTime = microtime(true);

				$resultList = $elasticSearch->execute();

				$this->getLogger(__METHOD__)->debug('ElasticExportKelkooBasicDE::log.esDuration', [
					'Elastic Search duration' => microtime(true) - $esStartTime,
				]);

				if(count($resultList['error'] ?? []) > 0)
				{
					$this->getLogger(__METHOD__)->error('ElasticExportKelkooBasicDE::log.occurredElasticSearchErrors', [
						'error message' => $resultList['error'],
					]);
				}

				$buildRowStartTime = microtime(true);

				if(is_array($resultList['documents']) && count($resultList['documents'] ?? []) > 0)
				{
					foreach($resultList['documents'] as $item)
					{
						if($this->elasticExportStockHelper->isFilteredByStock($item, $filter))
						{
							continue;
						}

						try
						{
							$this->buildRow($item, $settings);
							$lines++;
						}
						catch(\Throwable $exception)
						{
							$this->getLogger(__METHOD__)->error('ElasticExportKelkooBasicDE::log.buildRowError', [
								'error' => $exception->getMessage(),
								'line' => $exception->getLine(),
								'variation ID' => $item['id']
							]);
						}

						$this->getLogger(__METHOD__)->debug('ElasticExportKelkooBasicDE::log.buildRowDuration', [
							'Build Row duration' => microtime(true) - $buildRowStartTime,
						]);

						if($lines == $filter['limit'])
						{
							$limitReached = true;
							break;
						}
					}
				}
			}
			while($elasticSearch->hasNext());
		}

		$this->getLogger(__METHOD__)->debug('ElasticExportKelkooBasicDE::log.fileGenerationDuration', [
			'Whole file generation duration' => microtime(true) - $startTime,
		]);
    }

	/**
	 * @var array $item
	 * @var KeyValue $settings
	 */
    private function buildRow($item, $settings)
	{
		$deliveryCost = $this->elasticExportHelper->getShippingCost($item['data']['item']['id'], $settings);

		if(!is_null($deliveryCost))
		{
			$deliveryCost = number_format((float)$deliveryCost, 2, ',', '');
		}
		else
		{
			$deliveryCost = '';
		}

		$priceList = $this->elasticExportPriceHelper->getPriceList($item, $settings, 2, '.');

		$data = [
			'url' 		    => $this->elasticExportHelper->getUrl($item, $settings, true, false),
			'title' 		=> $this->elasticExportHelper->getName($item, $settings, 80),
			'description'   => $this->elasticExportHelper->getDescription($item, $settings, 160),
			'price' 	    => $priceList['price'],
			'offerid'       => $item['id'],
			'image'		    => $this->elasticExportHelper->getMainImage($item, $settings),
			'availability'  => $this->elasticExportHelper->getAvailability($item, $settings, false),
			'deliverycost' 	=> $deliveryCost,
			'deliveryTime' 	=> $this->elasticExportHelper->getAvailability($item, $settings),
			'unitaryPrice'  => $this->elasticExportPriceHelper->getBasePrice($item, $priceList['price']),
			'ean'           => $this->elasticExportHelper->getBarcodeByType($item, $settings->get('barcode')),
			'ecotax'        => ''
		];

		$this->addCSVContent(array_values($data));
	}
}