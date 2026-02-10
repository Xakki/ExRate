document.addEventListener('DOMContentLoaded', () => {
    // DOM Elements
    const providerSelect = document.getElementById('provider_select');
    const currencySelect = document.getElementById('currency');
    const baseCurrencySelect = document.getElementById('base_currency');
    const startDateInput = document.getElementById('start_date');
    const endDateInput = document.getElementById('end_date');
    const addPairBtn = document.getElementById('add-pair-btn');
    const updateRangeBtn = document.getElementById('update-range-btn');
    const activePairsList = document.getElementById('active-pairs-list');
    const presetButtons = document.querySelectorAll('.preset-btn');
    const chartCanvas = document.getElementById('rate-chart');

    // App State
    let providersData = [];
    let currentProviderKey = '';
    const chartColors = ['#4299E1', '#F56565', '#48BB78', '#ED8936', '#9F7AEA', '#ECC94B', '#ED64A6', '#38B2AC', '#F687B3', '#A0AEC0'];

    // Chart.js Instance
    const chart = new Chart(chartCanvas.getContext('2d'), {
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
                legend: { display: false },
                tooltip: { mode: 'index', intersect: false, position: 'nearest' }
            },
            interaction: { intersect: false, mode: 'index' }
        }
    });

    // --- State and UI Management ---

    /**
     * Saves the current chart state (currency pair labels) to localStorage for the active provider.
     */
    const saveState = () => {
        if (!currentProviderKey) return;
        const labels = chart.data.datasets.map(ds => ds.label);
        localStorage.setItem(`chart_state_${currentProviderKey}`, JSON.stringify(labels));
    };

    /**
     * Loads chart state from localStorage for the active provider and restores the pairs.
     */
    const loadState = async () => {
        const savedLabels = JSON.parse(localStorage.getItem(`chart_state_${currentProviderKey}`) || '[]');
        if (savedLabels.length > 0) {
            for (const label of savedLabels) {
                const [currency, base] = label.split('/');
                await addPair(currency, base);
            }
        } else {
            // Add a default pair if no state was saved for this provider
            await addPair('USD', baseCurrencySelect.value || 'RUB');
        }
    };

    /**
     * Updates the currency and base currency dropdowns based on the selected provider.
     */
    const updateCurrenciesForProvider = () => {
        const provider = providersData.find(p => p.key === currentProviderKey);
        if (!provider) return;

        const allCurrencies = new Set([...provider.currencies, provider.base_currency]);
        const sorted = Array.from(allCurrencies).sort();

        [currencySelect, baseCurrencySelect].forEach(select => {
            const currentValue = select.value;
            select.innerHTML = '';
            sorted.forEach(c => select.add(new Option(c, c)));
            select.value = sorted.includes(currentValue) ? currentValue : sorted[0];
        });

        // Set the base currency to the provider's default
        if (sorted.includes(provider.base_currency)) {
            baseCurrencySelect.value = provider.base_currency;
        }
    };

    /**
     * Renders the list of active currency pairs with remove buttons.
     */
    const renderActivePairs = () => {
        activePairsList.innerHTML = '';
        chart.data.datasets.forEach(dataset => {
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
            removeBtn.onclick = () => removePair(dataset.label);
            li.appendChild(removeBtn);

            activePairsList.appendChild(li);
        });
        saveState();
    };

    // --- Data Fetching ---

    /**
     * Fetches timeseries data from the API.
     * @returns {Promise<Object>}
     */
    const fetchTimeseries = async (startDate, endDate, currency, baseCurrency, provider) => {
        const url = `/api/v1/timeseries?start_date=${startDate}&end_date=${endDate}&currency=${currency}&base_currency=${baseCurrency}&provider=${provider}`;
        const response = await fetch(url);
        if (!response.ok) {
            const errorData = await response.json().catch(() => ({ detail: 'An unknown error occurred' }));
            throw new Error(errorData.detail || `Failed to fetch data for ${currency}/${baseCurrency}`);
        }
        return response.json();
    };

    // --- Chart Actions ---

    /**
     * Adds a new currency pair to the chart.
     */
    const addPair = async (currency, baseCurrency) => {
        if (chart.data.datasets.length >= 10) {
            console.warn('Maximum of 10 currency pairs reached.');
            return;
        }

        const label = `${currency}/${baseCurrency}`;
        if (currency === baseCurrency) {
            return; // Silently fail
        }
        if (chart.data.datasets.some(ds => ds.label === label)) {
            return; // Silently fail
        }

        try {
            const data = await fetchTimeseries(startDateInput.value, endDateInput.value, currency, baseCurrency, currentProviderKey);
            const newColor = chartColors[chart.data.datasets.length % chartColors.length];

            const newDataset = {
                label: label,
                data: Object.entries(data.rates).map(([date, rate]) => ({ x: new Date(date), y: parseFloat(rate) })),
                borderColor: newColor,
                backgroundColor: `${newColor}20`, // For area fill, if enabled
                fill: false,
                tension: 0.1,
                borderWidth: 2
            };

            chart.data.datasets.push(newDataset);
            chart.update();
            renderActivePairs();
        } catch (error) {
            console.error(`Error adding pair ${label}:`, error);
            alert(`Error: ${error.message}`);
        }
    };

    /**
     * Removes a currency pair from the chart by its label.
     */
    const removePair = (label) => {
        const index = chart.data.datasets.findIndex(ds => ds.label === label);
        if (index > -1) {
            chart.data.datasets.splice(index, 1);
            chart.update();
            renderActivePairs();
        }
    };

    /**
     * Updates all currently displayed pairs, optionally with a new base currency.
     */
    const updateAllPairs = async (newBaseCurrency = null) => {
        const startDate = startDateInput.value;
        const endDate = endDateInput.value;
        const base = newBaseCurrency || baseCurrencySelect.value;

        if (!chart.data.datasets.length && !newBaseCurrency) return;

        const targetCurrencies = chart.data.datasets.map(ds => ds.label.split('/')[0]);

        // Clear datasets for a full refresh
        chart.data.datasets = [];

        try {
            // Use Promise.all for concurrent fetching
            await Promise.all(targetCurrencies.map(currency => addPair(currency, base)));
        } catch (error) {
             console.error('Error updating charts:', error);
             alert(`Error updating one or more pairs: ${error.message}`);
        } finally {
            // addPair already calls render, but a final render ensures consistency
            renderActivePairs();
        }
    };

    // --- Event Handlers ---

    const handleProviderChange = async () => {
        saveState(); // Save state for the old provider
        chart.data.datasets = [];
        chart.update();
        currentProviderKey = providerSelect.value;
        updateCurrenciesForProvider();
        renderActivePairs(); // Clear the list UI
        await loadState(); // Load state for the new provider
    };

    const handleDatePreset = (event) => {
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
        startDateInput.value = start.toISOString().split('T')[0];
        endDateInput.value = end.toISOString().split('T')[0];

        presetButtons.forEach(btn => btn.classList.remove('active'));
        event.target.classList.add('active');
        updateAllPairs();
    };

    // --- Initialization ---
    const initialize = async () => {
        // Setup event listeners
        providerSelect.addEventListener('change', handleProviderChange);
        baseCurrencySelect.addEventListener('change', () => updateAllPairs(baseCurrencySelect.value));
        addPairBtn.addEventListener('click', () => addPair(currencySelect.value, baseCurrencySelect.value));
        updateRangeBtn.addEventListener('click', () => updateAllPairs());
        presetButtons.forEach(btn => btn.addEventListener('click', handleDatePreset));

        // Set default dates and select first preset
        document.querySelector('.preset-btn[data-period="month"]').click();

        // Fetch providers and setup initial state
        try {
            const response = await fetch('/api/v1/providers');
            providersData = await response.json();
            if (providersData.length === 0) throw new Error("No providers found.");

            providersData.forEach(p => providerSelect.add(new Option(p.description, p.key)));

            currentProviderKey = providerSelect.value || providersData[0]?.key;
            updateCurrenciesForProvider();
            await loadState();
        } catch (error) {
            console.error('Initialization failed:', error);
            alert(`Could not initialize the application: ${error.message}`);
        }
    };

    initialize();
});

function openTab(evt, tabName) {
    var i, tabcontent, tablinks;
    tabcontent = document.getElementsByClassName("tab-content");
    for (i = 0; i < tabcontent.length; i++) {
        tabcontent[i].style.display = "none";
    }
    tablinks = document.getElementsByClassName("tab-button");
    for (i = 0; i < tablinks.length; i++) {
        tablinks[i].className = tablinks[i].className.replace(" active", "");
    }
    document.getElementById(tabName).style.display = "block";
    evt.currentTarget.className += " active";
}
