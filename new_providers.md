# Техническое задание на интеграцию новых провайдеров курсов валют

В этом файле описаны параметры API для новых провайдеров. Каждый провайдер должен быть реализован как класс в `app/src/Provider/`, наследующий `AbstractApiProvider` и имплементирующий `ProviderInterface`.

---

## ✅ 1. Frankfurter (ECB Data) - РЕАЛИЗОВАНО
**Описание:** Бесплатный API, предоставляющий данные Европейского центрального банка.
- **Base URL:** `https://api.frankfurter.app/`
- **Аутентификация:** Нет
- **Методы:**
    - **Latest:** `GET /latest?base={base}&symbols={symbols}`
    - **Historical:** `GET /{date}?base={base}&symbols={symbols}`
- **Формат данных:** JSON

## ✅ 2. Bank of Canada (Valet API) - РЕАЛИЗОВАНО
**Описание:** Официальные курсы Банка Канады.
- **Base URL:** `https://www.bankofcanada.ca/valet/`
- **Аутентификация:** Нет
- **Методы:**
    - **Historical/Latest:** `GET /observations/group/FX_RATES_DAILY/json?start_date={date}&end_date={date}`
- **Маппинг серий:** `FX{BASE}{TARGET}` (например, `FXUSDCAD` для USD к CAD).
- **Формат данных:** JSON

## ✅ 3. Binance Public API - РЕАЛИЗОВАНО
**Описание:** Криптовалютные курсы в реальном времени.
- **Base URL:** `https://api.binance.com`
- **Аутентификация:** Нет (для публичных данных)
- **Методы:**
    - **Latest:** `GET /api/v3/ticker/price?symbols=["BTCUSDT",...]`
    - **Historical:** `GET /api/v3/klines?symbol={SYMBOL}&interval=1d&startTime={timestamp}&limit=1`
- **Формат данных:** JSON

## ✅ 4. FRED (Federal Reserve Economic Data) - РЕАЛИЗОВАНО
**Описание:** Макроэкономические данные США.
- **Base URL:** `https://api.stlouisfed.org/fred/`
- **Аутентификация:** API Key (`api_key`)
- **Методы:**
    - **Observations:** `GET series/observations?series_id={id}&api_key={key}&file_type=json&observation_start={date}&observation_end={date}`
- **Маппинг серий:** `DEXUSEU` (USD/EUR), `DEXJPUS` (JPY/USD) и др.
- **Формат данных:** JSON

## ✅ 5. Московская Биржа (MOEX ISS) - РЕАЛИЗОВАНО
**Описание:** Курсы валютного рынка MOEX.
- **Base URL:** `https://iss.moex.com/iss/`
- **Аутентификация:** Нет
- **Методы:**
    - **Latest:** `GET statistics/engines/currency/markets/selt/rates.json`
    - **Historical:** `GET history/engines/currency/markets/selt/boards/CETS/securities/{secid}.json?from={date}&till={date}`
- **Безопасные ID (Security IDs):** `USD000UTSTOM` (USD/RUB), `EUR_RUB__TOM` (EUR/RUB), `CNYRUB_TOM` (CNY/RUB).
- **Формат данных:** JSON

## ✅ 6. Banco de México (Banxico) - РЕАЛИЗОВАНО
**Описание:** Официальные курсы Мексики.
- **Base URL:** `https://www.banxico.org.mx/SieAPIRest/service/v1/`
- **Аутентификация:** Header `Bmx-Token`
- **Методы:**
    - **TimeSeries:** `GET series/{idSeries}/datos/{startDate}/{endDate}`
- **Маппинг серий:** `SF43718` (USD FIX), `SF46410` (EUR), `SF46406` (JPY).
- **Формат данных:** JSON

## ✅ 7. Central Bank of Brazil (BCB) - РЕАЛИЗОВАНО
**Описание:** Данные ЦБ Бразилии через OData.
- **Base URL:** `https://olinda.bcb.gov.br/olinda/service/PTAX/version/v1/odata/`
- **Аутентификация:** Нет
- **Методы:**
    - **Historical:** `GET DollarRateDate(dataCotacao=@dataCotacao)?@dataCotacao='{MM-DD-YYYY}'&$format=json`
- **Формат данных:** JSON

## ❌ 8. IMF (International Monetary Fund) - ОТЛОЖЕНО (Сервер недоступен)
**Описание:** Глобальная финансовая статистика через SDMX.
- **Base URL:** `http://dataservices.imf.org/REST/`
- **Аутентификация:** Нет
- **Методы:**
    - **CompactData:** `GET CompactData/EXR/D.{BaseCurrency}.{TargetCurrency}?startPeriod={YYYY}&endPeriod={YYYY}`
- **Формат данных:** XML/JSON

