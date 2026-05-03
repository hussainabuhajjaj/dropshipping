import { router, usePage } from "@inertiajs/vue3";
import { computed, ref, watch } from "vue";

// State management
const preferences = ref({
    currency: "XOF",
    language: "fr",
    availableCurrencies: ["XOF"],
    availableLanguages: ["en", "fr"],
    currencyRates: {},
    currencyDecimals: {},
    displaySettings: {
        auto_convert_prices: true,
        show_currency_selector: false,
        default_customer_currency: "XOF",
    },
});

const loading = ref(false);
const error = ref(null);

export function useUserPreferences() {
    const page = usePage();

    const normalizePreferences = (payload = {}) => ({
        currency: payload.currency || "XOF",
        language: payload.language || "fr",
        availableCurrencies: payload.available_currencies || ["XOF"],
        availableLanguages: Object.keys(
            payload.available_languages || { en: "English" },
        ),
        currencyRates: payload.currency_rates || {
            USD: 1,
            USD_XOF: 600,
            XOF_USD: 0.00167,
        },
        currencyDecimals: payload.currency_decimals || { XOF: 0 },
        displaySettings: payload.display_settings || {
            auto_convert_prices: true,
            show_currency_selector: false,
            default_customer_currency: "XOF",
        },
    });

    // Initialize preferences from shared Inertia props.
    const initializePreferences = () => {
        preferences.value = normalizePreferences(
            page.props.user_preferences || {},
        );
    };

    // Fetch preferences from API
    const fetchPreferences = async () => {
        try {
            loading.value = true;
            error.value = null;

            const response = await fetch("/api/user-preferences");
            if (!response.ok) {
                throw new Error("Failed to fetch preferences");
            }

            const data = await response.json();
            preferences.value = normalizePreferences(data);
        } catch (err) {
            error.value = err.message;
            console.error("Failed to fetch preferences:", err);
        } finally {
            loading.value = false;
        }
    };

    // Update currency preference
    const setCurrency = async (currency) => {
        if (!preferences.value.availableCurrencies.includes(currency)) {
            throw new Error(`Currency ${currency} is not supported`);
        }

        const persistedCurrency = page.props.user_preferences?.currency || null;
        if (persistedCurrency && currency === persistedCurrency) {
            return; // No change needed
        }

        try {
            loading.value = true;
            error.value = null;

            await router.put(
                "/api/user-preferences/currency",
                { currency },
                {
                    preserveScroll: true,
                    onSuccess: (page) => {
                        preferences.value = normalizePreferences(
                            page.props.user_preferences || {},
                        );
                    },
                    onError: (errors) => {
                        error.value = Object.values(errors).join(", ");
                    },
                },
            );
        } catch (err) {
            error.value = err.message;
            console.error("Failed to update currency:", err);
            throw err;
        } finally {
            loading.value = false;
        }
    };

    // Update language preference
    const setLanguage = async (language) => {
        if (!preferences.value.availableLanguages.includes(language)) {
            throw new Error(`Language ${language} is not supported`);
        }

        const persistedLanguage = page.props.user_preferences?.language || null;
        if (persistedLanguage && language === persistedLanguage) {
            return; // No change needed
        }

        try {
            loading.value = true;
            error.value = null;

            await router.put(
                "/api/user-preferences/language",
                { language },
                {
                    preserveScroll: true,
                    onSuccess: (page) => {
                        preferences.value = normalizePreferences(
                            page.props.user_preferences || {},
                        );
                    },
                    onError: (errors) => {
                        error.value = Object.values(errors).join(", ");
                    },
                },
            );
        } catch (err) {
            error.value = err.message;
            console.error("Failed to update language:", err);
            throw err;
        } finally {
            loading.value = false;
        }
    };

    // Update multiple preferences at once
    const updatePreferences = async (updates) => {
        try {
            loading.value = true;
            error.value = null;

            const response = await fetch("/api/user-preferences", {
                method: "PUT",
                headers: {
                    "Content-Type": "application/json",
                    "X-CSRF-TOKEN": document
                        .querySelector('meta[name="csrf-token"]')
                        ?.getAttribute("content"),
                },
                body: JSON.stringify(updates),
            });

            if (!response.ok) {
                const errorData = await response.json();
                throw new Error(
                    errorData.message || "Failed to update preferences",
                );
            }

            const data = await response.json();
            preferences.value = normalizePreferences(data.preferences || {});

            // Reload page to update all props
            router.visit(window.location.pathname, {
                preserveScroll: true,
                preserveState: false,
                replace: true,
            });
        } catch (err) {
            error.value = err.message;
            console.error("Failed to update preferences:", err);
            throw err;
        } finally {
            loading.value = false;
        }
    };

    // Currency conversion utilities
    const convertCurrency = (amount, fromCurrency, toCurrency) => {
        if (fromCurrency === toCurrency) return amount;

        const rates = preferences.value.currencyRates;
        const directKey = `${fromCurrency}_${toCurrency}`;
        const inverseKey = `${toCurrency}_${fromCurrency}`;

        // Try direct conversion first (e.g., USD_XOF)
        if (rates[directKey] && typeof rates[directKey] === "number") {
            return amount * rates[directKey];
        }

        // Try inverse conversion (e.g., XOF_USD)
        if (rates[inverseKey] && typeof rates[inverseKey] === "number") {
            return amount / rates[inverseKey];
        }

        // Fallback: try using individual currency rates
        const fromRate = rates[fromCurrency];
        const toRate = rates[toCurrency];

        if (
            fromRate &&
            toRate &&
            typeof fromRate === "number" &&
            typeof toRate === "number"
        ) {
            // Convert through base currency
            return (amount / fromRate) * toRate;
        }

        return amount; // Return original if no rate found
    };

    // Currency formatting
    const formatCurrency = (amount, currency) => {
        const decimals = preferences.value.currencyDecimals[currency] || 2;
        const locale = preferences.value.language === "fr" ? "fr-FR" : "en-US";

        return Number(amount || 0).toLocaleString(locale, {
            style: "currency",
            currency: currency || "USD",
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals,
        });
    };

    // Language utilities
    const getLocaleOptions = computed(() => {
        const userLocales =
            page.props.user_preferences?.available_languages || {};
        return Object.entries(userLocales).map(([code, label]) => ({
            code,
            label,
        }));
    });

    // Computed properties
    const currentCurrency = computed({
        get: () => preferences.value.currency,
        set: (value) => {
            preferences.value.currency = value;
        },
    });

    const currentLanguage = computed({
        get: () => preferences.value.language,
        set: (value) => {
            preferences.value.language = value;
        },
    });

    const isLoading = computed(() => loading.value);
    const hasError = computed(() => error.value);

    // Initialize on first use.
    if (Object.keys(preferences.value.currencyRates || {}).length === 0) {
        initializePreferences();
    }

    watch(
        () => page.props.user_preferences,
        (nextPreferences) => {
            if (!nextPreferences) {
                return;
            }
            preferences.value = normalizePreferences(nextPreferences);
        },
        { deep: true },
    );

    return {
        // State
        preferences,
        currentCurrency,
        currentLanguage,
        isLoading,
        hasError,
        error,

        // Actions
        setCurrency,
        setLanguage,
        updatePreferences,
        fetchPreferences,

        // Utilities
        convertCurrency,
        formatCurrency,
        getLocaleOptions,

        // Available options
        availableCurrencies: computed(
            () => preferences.value.availableCurrencies,
        ),
        availableLanguages: computed(
            () => preferences.value.availableLanguages,
        ),
        currencyRates: computed(() => preferences.value.currencyRates),
        currencyDecimals: computed(() => preferences.value.currencyDecimals),
        displaySettings: computed(() => preferences.value.displaySettings),
    };
}
