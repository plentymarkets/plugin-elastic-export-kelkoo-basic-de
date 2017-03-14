<?php
namespace ElasticExportKelkooBasicDE;

use Plenty\Modules\DataExchange\Services\ExportPresetContainer;
use Plenty\Plugin\DataExchangeServiceProvider;

class ElasticExportKelkooBasicDEServiceProvider extends DataExchangeServiceProvider
{
    public function register()
    {

    }

    public function exports(ExportPresetContainer $container)
    {
        $container->add(
            'KelkooBasicDE-Plugin',
            'ElasticExportKelkooBasicDE\ResultField\KelkooBasicDE',
            'ElasticExportKelkooBasicDE\Generator\KelkooBasicDE',
            '',
            true
        );
    }
}