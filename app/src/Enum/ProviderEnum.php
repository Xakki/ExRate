<?php

declare(strict_types=1);

namespace App\Enum;

enum ProviderEnum: string
{
    case CBR = 'cbr';
    case ECB = 'ecb';
    case NBR = 'nbr';
    case CBRT = 'cbrt';
    case CNB = 'cnb';
    case RCB = 'rcb';
    case BNB = 'bnb'; // disabled
    case NBU = 'nbu';
    case NBG = 'nbg';
    case OPEN_EXCHANGE_RATES = 'open_exchange_rates';
    case CURRENCY_LAYER = 'currency_layer';
    case COIN_LAYER = 'coin_layer';
    case API_LAYER_FIXER = 'api_layer_fixer';
    case API_LAYER_CURRENCY_DATA = 'api_layer_currency_data';
    case API_LAYER_EXCHANGE_RATES_DATA = 'api_layer_exchange_rates_data';
    case EXCHANGE_RATES_API = 'exchange_rates_api';
    case FAST_FOREX = 'fast_forex';
    case FORGE = 'forge';
    case XCHANGE_API = 'xchange_api';
    case XIGNITE = 'xignite';
    case CURRENCY_DATA_FEED = 'currency_data_feed';
    case CURRENCY_CONVERTER = 'currency_converter';
    case ABSTRACT_API = 'abstract_api';
    case FRANKFURTER = 'frankfurter';
    case BANK_OF_CANADA = 'bank_of_canada';
    case BINANCE = 'binance';
    case FRED = 'fred';
    case MOEX = 'moex';
    case BANXICO = 'banxico';
    case BCB = 'bcb';
}
