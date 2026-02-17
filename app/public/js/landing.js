/**
 * ExchangeRateApp - A modular application for managing exchange rate charts.
 */
class ExchangeRateApp {
    constructor() {
        this.config = {
            chartColors: ['#4299E1', '#F56565', '#48BB78', '#ED8936', '#9F7AEA', '#ECC94B', '#ED64A6', '#38B2AC', '#F687B3', '#A0AEC0'],
            maxDatasets: 10,
            apiEndpoints: {
                providers: '/api/v1/providers',
                timeseries: '/api/v1/timeseries'
            }
        };

        this.state = {
            providers: [],
            currentProviderKey: '',
            groupingMode: 'provider', // 'provider' or 'pair'
            isLoading: false
        };

        this.elements = this.getDOMElements();
        this.chart = this.initChart();
        this.initEventListeners();
    }

    getDOMElements() {
        return {
            providerSelect: document.getElementById('provider_select'),
            currencySelect: document.getElementById('currency'),
            baseCurrencySelect: document.getElementById('base_currency'),
            startDateInput: document.getElementById('start_date'),
            endDateInput: document.getElementById('end_date'),
            addPairBtn: document.getElementById('add-pair-btn'),
            updateRangeBtn: document.getElementById('update-range-btn'),
            activePairsList: document.getElementById('active-pairs-list'),
            presetButtons: document.querySelectorAll('.preset-btn'),
            chartCanvas: document.getElementById('rate-chart'),
            groupingModeInputs: document.querySelectorAll('input[name="grouping_mode"]'),
            providerSelectLabel: document.getElementById('provider_select_label'),
            addItemLabel: document.getElementById('add_item_label'),
            notificationContainer: document.getElementById('notification-container'),
        };
    }

    initChart() {
        const ctx = this.elements.chartCanvas.getContext('2d');
        return new Chart(ctx, {
            type: 'line',
            data: { datasets: [] },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: { type: 'time', time: { unit: 'day' }, title: { display: true, text: 'Date' }, grid: { display: false } },
                    y: { title: { display: true, text: 'Rate' } }
                },
                plugins: {
                    legend: { display: true, position: 'top' },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        callbacks: {
                            label: (context) => {
                                let label = context.dataset.label || '';
                                if (label) label += ': ';
                                label += context.raw.originalY !== undefined ? context.raw.originalY : context.parsed.y;
                                return label;
                            }
                        }
                    }
                },
                interaction: { intersect: false, mode: 'index' }
            }
        });
    }

    initEventListeners() {
        this.elements.groupingModeInputs.forEach(input =>
            input.addEventListener('change', (e) => this.handleGroupingModeChange(e.target.value))
        );
        this.elements.providerSelect.addEventListener('change', () => this.handleProviderChange());
        this.elements.baseCurrencySelect.addEventListener('change', () => this.handleCurrencyChange());
        this.elements.currencySelect.addEventListener('change', () => {
            if (this.state.groupingMode === 'pair') this.handleCurrencyChange();
        });
        this.elements.addPairBtn.addEventListener('click', () => this.handleAddItemClick());
        this.elements.updateRangeBtn.addEventListener('click', () => this.updateAllItems());
        this.elements.presetButtons.forEach(btn =>
            btn.addEventListener('click', (e) => this.handleDatePreset(e))
        );
    }

    // --- State Management ---

    getModeKey() {
        const { groupingMode, currentProviderKey } = this.state;
        return groupingMode === 'provider'
            ? currentProviderKey
            : `${this.elements.baseCurrencySelect.value}_${this.elements.currencySelect.value}`;
    }

    saveState() {
        const key = this.getModeKey();
        if (!key) return;

        const stateToSave = {
            mode: this.state.groupingMode,
            datasets: this.chart.data.datasets.map(ds => ({
                label: ds.label,
                provider: ds.provider,
                currency: ds.currency,
                baseCurrency: ds.baseCurrency
            }))
        };
        localStorage.setItem(`chart_state_${this.state.groupingMode}_${key}`, JSON.stringify(stateToSave));
    }

    async loadState() {
        const key = this.getModeKey();
        const saved = JSON.parse(localStorage.getItem(`chart_state_${this.state.groupingMode}_${key}`) || 'null');

        this.chart.data.datasets = [];

        if (saved && saved.mode === this.state.groupingMode) {
            // Sequential to maintain color order and avoid race conditions on chart.update()
            for (const ds of saved.datasets) {
                await this.addItem(ds.currency, ds.baseCurrency, ds.provider, ds.label, false);
            }
        } else if (this.state.groupingMode === 'provider') {
            const provider = this.state.providers.find(p => p.key === this.state.currentProviderKey);
            if (provider) {
                await this.addItem('USD', provider.base_currency, this.state.currentProviderKey, null, false);
            }
        }

        this.chart.update();
        this.renderActiveItems();
    }

    // --- UI Rendering ---

    populateSelect(select, options, currentValue) {
        select.innerHTML = '';
        options.forEach(opt => {
            const val = typeof opt === 'string' ? opt : opt.value;
            const label = typeof opt === 'string' ? opt : opt.label;
            select.add(new Option(label, val));
        });
        if (currentValue && options.some(o => (typeof o === 'string' ? o : o.value) === currentValue)) {
            select.value = currentValue;
        }
    }

    updateUIControls() {
        const { groupingMode, providers, currentProviderKey } = this.state;
        const { providerSelectLabel, addItemLabel, currencySelect, baseCurrencySelect } = this.elements;

        if (groupingMode === 'provider') {
            providerSelectLabel.textContent = 'Provider';
            addItemLabel.textContent = 'Add Currency';

            const provider = providers.find(p => p.key === currentProviderKey);
            if (!provider) return;

            const currencies = Array.from(new Set([...provider.currencies, provider.base_currency])).sort();
            this.populateSelect(currencySelect, currencies, currencySelect.value);
            this.populateSelect(baseCurrencySelect, currencies, baseCurrencySelect.value || provider.base_currency);
        } else {
            providerSelectLabel.textContent = 'Add Provider';
            addItemLabel.textContent = 'Currency Pair';

            const allCurrencies = new Set();
            providers.forEach(p => {
                allCurrencies.add(p.base_currency);
                p.currencies.forEach(c => allCurrencies.add(c));
            });
            const sorted = Array.from(allCurrencies).sort();

            this.populateSelect(currencySelect, sorted, currencySelect.value);
            this.populateSelect(baseCurrencySelect, sorted, baseCurrencySelect.value);

            this.updateAvailableProviders();
        }
    }

    updateAvailableProviders() {
        if (this.state.groupingMode !== 'pair') return;

        const base = this.elements.baseCurrencySelect.value;
        const curr = this.elements.currencySelect.value;

        const available = this.state.providers.filter(p =>
            (p.base_currency === base && p.currencies.includes(curr)) ||
            (p.base_currency === curr && p.currencies.includes(base)) ||
            (p.currencies.includes(base) && p.currencies.includes(curr))
        ).map(p => ({ label: p.description, value: p.key }));

        this.populateSelect(this.elements.providerSelect, available, this.elements.providerSelect.value);
    }

    renderActiveItems() {
        const fragment = document.createDocumentFragment();
        this.chart.data.datasets.forEach(dataset => {
            const li = document.createElement('li');
            li.style.borderColor = dataset.borderColor;

            const span = document.createElement('span');
            span.textContent = dataset.label;
            span.style.color = dataset.borderColor;
            span.style.fontWeight = 'bold';
            li.appendChild(span);

            const removeBtn = document.createElement('button');
            removeBtn.textContent = '×';
            removeBtn.className = 'remove-btn';
            removeBtn.title = `Remove ${dataset.label}`;
            removeBtn.onclick = () => this.removeItem(dataset.label);
            li.appendChild(removeBtn);

            fragment.appendChild(li);
        });

        this.elements.activePairsList.innerHTML = '';
        this.elements.activePairsList.appendChild(fragment);
        this.saveState();
    }

    // --- API Interactions ---

    async fetchTimeseries(currency, baseCurrency, provider) {
        const params = new URLSearchParams({
            start_date: this.elements.startDateInput.value,
            end_date: this.elements.endDateInput.value,
            currency,
            base_currency: baseCurrency,
            provider
        });

        const response = await fetch(`${this.config.apiEndpoints.timeseries}?${params}`, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        });

        if (!response.ok) {
            const errorData = await response.json().catch(() => ({}));
            let errorMessage = 'Failed to fetch rates';

            if (errorData.detail) {
                errorMessage = errorData.detail;
            } else if (errorData.error) {
                errorMessage = errorData.error;
            } else if (errorData.violations && Array.isArray(errorData.violations)) {
                errorMessage = errorData.violations.map(v => `${v.propertyPath}: ${v.title}`).join('; ');
            } else if (errorData.title) {
                errorMessage = errorData.title;
            }

            throw new Error(errorMessage);
        }
        return response.json();
    }

    async fetchProviders() {
        const response = await fetch(this.config.apiEndpoints.providers, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        });
        if (!response.ok) {
            const errorData = await response.json().catch(() => ({}));
            throw new Error(errorData.error || errorData.detail || 'Failed to fetch providers');
        }
        return response.json();
    }

    // --- Actions ---

    async addItem(currency, baseCurrency, providerKey, customLabel = null, shouldUpdateChart = true) {
        if (this.chart.data.datasets.length >= this.config.maxDatasets) {
            this.notify(`Maximum of ${this.config.maxDatasets} items reached`, 'error');
            return;
        }

        const provider = this.state.providers.find(p => p.key === providerKey);
        if (!provider) return;

        const label = customLabel || (this.state.groupingMode === 'provider' ? `${currency}/${baseCurrency}` : provider.description);

        if (this.state.groupingMode === 'provider' && currency === baseCurrency) return;
        if (this.chart.data.datasets.some(ds => ds.label === label)) return;

        try {
            const data = await this.fetchTimeseries(currency, baseCurrency, providerKey);
            const colorIndex = this.chart.data.datasets.length % this.config.chartColors.length;
            const color = this.config.chartColors[colorIndex];

            this.chart.data.datasets.push({
                label,
                data: Object.entries(data.rates).map(([date, rate]) => ({
                    x: new Date(date),
                    y: parseFloat(rate),
                    originalY: rate
                })),
                borderColor: color,
                backgroundColor: `${color}20`,
                fill: false,
                tension: 0.1,
                borderWidth: 2,
                provider: providerKey,
                currency,
                baseCurrency
            });

            if (shouldUpdateChart) {
                this.chart.update();
                this.renderActiveItems();
            }
        } catch (error) {
            console.error(`Error adding item:`, error);
            this.notify(error.message, 'error');
        }
    }

    removeItem(label) {
        const index = this.chart.data.datasets.findIndex(ds => ds.label === label);
        if (index > -1) {
            this.chart.data.datasets.splice(index, 1);
            this.chart.update();
            this.renderActiveItems();
        }
    }

    async updateAllItems() {
        const currentDatasets = [...this.chart.data.datasets];
        this.chart.data.datasets = [];

        // In 'pair' mode, all datasets share same currency/base
        const sharedBase = this.elements.baseCurrencySelect.value;
        const sharedCurr = this.elements.currencySelect.value;

        await Promise.all(currentDatasets.map(ds =>
            this.addItem(
                this.state.groupingMode === 'provider' ? ds.currency : sharedCurr,
                this.state.groupingMode === 'provider' ? ds.baseCurrency : sharedBase,
                ds.provider,
                ds.label,
                false
            )
        ));

        this.chart.update();
        this.renderActiveItems();
    }

    // --- Handlers ---

    async handleGroupingModeChange(mode) {
        this.state.groupingMode = mode;
        this.chart.data.datasets = [];
        this.chart.update();
        this.updateUIControls();
        await this.loadState();
    }

    async handleProviderChange() {
        if (this.state.groupingMode === 'provider') {
            const previous = this.chart.data.datasets.map(ds => ({
                currency: ds.currency,
                baseCurrency: ds.baseCurrency
            }));

            this.state.currentProviderKey = this.elements.providerSelect.value;
            const provider = this.state.providers.find(p => p.key === this.state.currentProviderKey);

            this.chart.data.datasets = [];
            this.updateUIControls();

            if (provider && previous.length > 0) {
                const supported = new Set([...provider.currencies, provider.base_currency]);
                for (const ds of previous) {
                    if (supported.has(ds.currency) && supported.has(ds.baseCurrency)) {
                        await this.addItem(ds.currency, ds.baseCurrency, this.state.currentProviderKey, null, false);
                    }
                }
            } else {
                this.saveState();
            }

            this.chart.update();
            this.renderActiveItems();
        }
    }

    async handleCurrencyChange() {
        if (this.state.groupingMode === 'pair') {
            this.updateAvailableProviders();
            this.chart.data.datasets = [];
            this.chart.update();
            await this.loadState();
        } else {
            await this.updateAllItems();
        }
    }

    handleAddItemClick() {
        this.addItem(this.elements.currencySelect.value, this.elements.baseCurrencySelect.value, this.elements.providerSelect.value);
    }

    handleDatePreset(event) {
        const period = event.target.dataset.period;
        const end = new Date();
        let start = new Date();

        switch (period) {
            case 'week': start.setDate(end.getDate() - 7); break;
            case 'month': start.setMonth(end.getMonth() - 1); break;
            case '6-months': start.setMonth(end.getMonth() - 6); break;
            case 'year': start.setFullYear(end.getFullYear() - 1); break;
            case '5-years': start.setFullYear(end.getFullYear() - 5); break;
        }

        this.elements.startDateInput.value = start.toISOString().split('T')[0];
        this.elements.endDateInput.value = end.toISOString().split('T')[0];

        this.elements.presetButtons.forEach(btn => btn.classList.remove('active'));
        event.target.classList.add('active');
        this.updateAllItems();
    }

    notify(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `notification ${type}`;
        notification.innerHTML = `
            <span>${message}</span>
            <button style="background:none; border:none; color:inherit; cursor:pointer; font-size:1.2rem; margin-left:10px;">&times;</button>
        `;

        const closeBtn = notification.querySelector('button');
        const removeNotification = () => {
            notification.classList.add('fade-out');
            setTimeout(() => notification.remove(), 500);
        };

        closeBtn.onclick = removeNotification;
        this.elements.notificationContainer.appendChild(notification);

        setTimeout(removeNotification, 5000);
    }

    async start() {
        try {
            this.state.providers = await this.fetchProviders();
            if (this.state.providers.length === 0) throw new Error("No providers available.");

            const options = this.state.providers.map(p => ({ label: p.description, value: p.key }));
            this.populateSelect(this.elements.providerSelect, options);

            this.state.currentProviderKey = this.elements.providerSelect.value || this.state.providers[0].key;

            this.updateUIControls();

            // Default to month view if not set
            if (!this.elements.startDateInput.value) {
                document.querySelector('.preset-btn[data-period="month"]')?.click();
            } else {
                await this.loadState();
            }
        } catch (error) {
            console.error('App initialization failed:', error);
        }
    }
}

// Initialize Application
document.addEventListener('DOMContentLoaded', () => {
    const app = new ExchangeRateApp();
    app.start();

    // Global utility for tabs (kept for HTML compatibility)
    window.openTab = (evt, tabName) => {
        const contents = document.getElementsByClassName("tab-content");
        for (let content of contents) content.style.display = "none";

        const buttons = document.getElementsByClassName("tab-button");
        for (let btn of buttons) btn.classList.remove("active");

        document.getElementById(tabName).style.display = "block";
        evt.currentTarget.classList.add("active");
    };
});
