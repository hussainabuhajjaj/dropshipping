<template>
    <StorefrontLayout>
        <div class="bg-[#f7f3eb] pb-28 sm:pb-32">
            <div
                class="mx-auto flex w-full max-w-[1400px] flex-col gap-4 px-2.5 py-3 sm:gap-6 sm:px-5 sm:py-4 lg:px-8"
            >
                <HeroBanner
                    :hero="heroBanner"
                    :stats="heroStats"
                    :chips="heroChips"
                    :highlights="heroHighlights"
                    @jump="scrollToSection"
                />

                <BottomNav :items="sectionNavItems" />

                <section
                    id="categories"
                    class="rounded-[1.6rem] bg-[#fcfaf7] px-3.5 py-4 shadow-[0_12px_30px_rgba(15,23,42,0.04)] sm:rounded-[2rem] sm:px-5 sm:py-5"
                >
                    <CategoryScroll :categories="scrollCategories" />
                </section>

                <section
                    id="collections"
                    class="rounded-[1.6rem] bg-white px-3.5 py-4 shadow-[0_16px_38px_rgba(15,23,42,0.05)] sm:rounded-[2rem] sm:px-5 sm:py-5"
                >
                    <div class="flex items-end justify-between gap-3">
                        <div>
                            <p
                                class="text-[0.68rem] font-bold uppercase tracking-[0.24em] text-[#ff6b35]"
                            >
                                {{ t("Collections edit") }}
                            </p>
                            <h2
                                class="text-[1.2rem] font-black tracking-[-0.03em] text-slate-950 sm:text-[1.55rem]"
                            >
                                {{ t("Shop curated Simbazu collections") }}
                            </h2>
                        </div>
                        <Link
                            v-if="collectionsCtaHref"
                            :href="collectionsCtaHref"
                            class="shrink-0 text-[0.68rem] font-bold uppercase tracking-[0.16em] text-slate-500 transition hover:text-slate-950 sm:text-xs sm:tracking-[0.18em]"
                        >
                            {{ t("Browse all") }}
                        </Link>
                    </div>

                    <div
                        class="mt-3.5 grid gap-3 sm:mt-4 lg:grid-cols-[1.1fr_0.9fr]"
                    >
                        <Link
                            v-if="collectionLanes[0]"
                            :href="collectionLanes[0].href"
                            class="group overflow-hidden rounded-[1.6rem] border border-[#f0e7dc] bg-[#faf5ef] transition hover:-translate-y-1 hover:shadow-[0_18px_40px_rgba(15,23,42,0.08)]"
                        >
                            <div
                                class="grid gap-0 sm:grid-cols-[0.95fr_1.05fr]"
                            >
                                <div
                                    class="aspect-[1.04] overflow-hidden bg-[#f3e9db]"
                                >
                                    <img
                                        v-if="collectionLanes[0].image"
                                        :src="collectionLanes[0].image"
                                        :alt="collectionLanes[0].title"
                                        class="h-full w-full object-cover transition duration-300 group-hover:scale-105"
                                        loading="lazy"
                                    />
                                </div>
                                <div
                                    class="flex flex-col justify-between gap-3 px-4 py-4 sm:px-5 sm:py-5"
                                >
                                    <div>
                                        <p
                                            class="text-[0.62rem] font-bold uppercase tracking-[0.22em] text-[#c55b24]"
                                        >
                                            {{ collectionLanes[0].kicker }}
                                        </p>
                                        <p
                                            class="mt-2 text-[1.15rem] font-black tracking-[-0.03em] text-slate-950 sm:text-[1.45rem]"
                                        >
                                            {{ collectionLanes[0].title }}
                                        </p>
                                        <p
                                            class="mt-2 text-[0.9rem] leading-5 text-slate-600 sm:text-sm sm:leading-6"
                                        >
                                            {{ collectionLanes[0].subtitle }}
                                        </p>
                                    </div>
                                    <div class="flex flex-wrap gap-2">
                                        <span
                                            class="inline-flex rounded-full bg-white px-3 py-2 text-[0.62rem] font-bold uppercase tracking-[0.12em] text-slate-900 ring-1 ring-[#eadfce]"
                                        >
                                            {{ collectionLanes[0].cta }}
                                        </span>
                                        <span
                                            v-if="collectionLanes[0].tag"
                                            class="inline-flex rounded-full bg-[#111111] px-3 py-2 text-[0.62rem] font-bold uppercase tracking-[0.12em] text-white"
                                        >
                                            {{ collectionLanes[0].tag }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </Link>

                        <div class="grid gap-3">
                            <Link
                                v-for="lane in collectionLanes.slice(1, 3)"
                                :key="lane.title"
                                :href="lane.href"
                                class="group overflow-hidden rounded-[1.45rem] border border-[#f0e7dc] bg-[#fffaf4] transition hover:-translate-y-1 hover:shadow-[0_18px_40px_rgba(15,23,42,0.08)]"
                            >
                                <div
                                    class="grid grid-cols-[0.42fr_0.58fr] items-stretch"
                                >
                                    <div class="overflow-hidden bg-[#f3e9db]">
                                        <img
                                            v-if="lane.image"
                                            :src="lane.image"
                                            :alt="lane.title"
                                            class="h-full w-full object-cover transition duration-300 group-hover:scale-105"
                                            loading="lazy"
                                        />
                                    </div>
                                    <div class="space-y-2 px-3.5 py-3.5">
                                        <p
                                            class="text-[0.62rem] font-bold uppercase tracking-[0.22em] text-[#c55b24]"
                                        >
                                            {{ lane.kicker }}
                                        </p>
                                        <p
                                            class="text-[0.96rem] font-black tracking-[-0.02em] text-slate-950"
                                        >
                                            {{ lane.title }}
                                        </p>
                                        <p
                                            class="text-[0.82rem] leading-5 text-slate-600"
                                        >
                                            {{ lane.subtitle }}
                                        </p>
                                        <span
                                            class="inline-flex rounded-full bg-white px-3 py-1.5 text-[0.62rem] font-bold uppercase tracking-[0.12em] text-slate-900 ring-1 ring-[#eadfce]"
                                        >
                                            {{ lane.cta }}
                                        </span>
                                    </div>
                                </div>
                            </Link>
                        </div>
                    </div>
                </section>

                <section
                    id="intent"
                    class="rounded-[1.6rem] bg-white px-3.5 py-4 shadow-[0_16px_38px_rgba(15,23,42,0.05)] sm:rounded-[2rem] sm:px-5 sm:py-5"
                >
                    <div
                        class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between"
                    >
                        <div>
                            <p
                                class="text-[0.68rem] font-bold uppercase tracking-[0.24em] text-[#ff6b35]"
                            >
                                {{ t("Intent shortcuts") }}
                            </p>
                            <h2
                                class="text-lg font-black tracking-[-0.03em] text-slate-950 sm:text-xl"
                            >
                                {{ t("High-intent searches ready to tap") }}
                            </h2>
                        </div>
                        <p
                            class="max-w-md text-[0.92rem] leading-5 text-slate-500 sm:text-sm sm:leading-6"
                        >
                            {{
                                t(
                                    "Remove thinking by surfacing the exact shopping missions users usually type into search.",
                                )
                            }}
                        </p>
                    </div>

                    <div
                        class="mt-3.5 flex flex-nowrap gap-2 overflow-x-auto pb-1 [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden sm:mt-4 sm:flex-wrap sm:gap-2.5"
                    >
                        <Link
                            v-for="chip in searchIntentChips"
                            :key="chip.label"
                            :href="chip.href"
                            class="inline-flex min-h-10 shrink-0 items-center rounded-full border border-[#ece0d4] bg-[#fcf7f0] px-3.5 text-[0.78rem] font-bold text-slate-800 transition hover:-translate-y-0.5 hover:border-[#ffcfbc] hover:bg-white sm:min-h-11 sm:px-4 sm:text-sm"
                        >
                            {{ chip.label }}
                        </Link>
                    </div>
                </section>

                <section
                    id="mobile-app"
                    class="rounded-[1.6rem] bg-[#fff7ed] px-3.5 py-5 shadow-[0_16px_38px_rgba(15,23,42,0.05)] sm:rounded-[2rem] sm:px-5 sm:py-6"
                >
                    <div
                        class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div>
                            <p
                                class="text-[0.68rem] font-bold uppercase tracking-[0.24em] text-[#ff6b35]"
                            >
                                {{ t("Download our app") }}
                            </p>
                            <h2
                                class="mt-2 text-lg font-black tracking-[-0.03em] text-slate-950 sm:text-xl"
                            >
                                {{
                                    t(
                                        "Get the full Simbazu experience on mobile",
                                    )
                                }}
                            </h2>
                            <p
                                class="mt-2 max-w-xl text-[0.92rem] leading-6 text-slate-600 sm:text-sm sm:leading-6"
                            >
                                {{
                                    t(
                                        "Shop faster, track orders in real time, and unlock app-only offers.",
                                    )
                                }}
                            </p>
                        </div>
                        <div class="flex flex-wrap gap-3">
                            <a
                                target="_blank"
                                rel="noopener noreferrer"
                                href="https://apps.apple.com/"
                                class="inline-flex min-h-[44px] items-center justify-center rounded-full bg-slate-950 px-5 text-sm font-bold text-white transition hover:bg-slate-800"
                            >
                                {{ t("App Store") }}
                            </a>
                            <a
                                target="_blank"
                                rel="noopener noreferrer"
                                href="https://play.google.com/store"
                                class="inline-flex min-h-[44px] items-center justify-center rounded-full border border-slate-900 bg-white px-5 text-sm font-bold text-slate-950 transition hover:bg-slate-50"
                            >
                                {{ t("Google Play") }}
                            </a>
                        </div>
                    </div>
                </section>

                <section class="grid gap-4 lg:grid-cols-[0.9fr_1.1fr]">
                    <section
                        id="signals"
                        class="rounded-[1.6rem] bg-white px-3.5 py-4 shadow-[0_16px_38px_rgba(15,23,42,0.05)] sm:rounded-[2rem] sm:px-4 sm:py-5"
                    >
                        <div class="flex items-end justify-between gap-3">
                            <div>
                                <p
                                    class="text-[0.68rem] font-bold uppercase tracking-[0.24em] text-[#ff6b35]"
                                >
                                    {{ t("Trust compressed") }}
                                </p>
                                <h2
                                    class="text-lg font-black tracking-[-0.03em] text-slate-950 sm:text-xl"
                                >
                                    {{ t("Fewer doubts, faster taps") }}
                                </h2>
                            </div>
                        </div>
                        <div
                            class="mt-3.5 grid gap-2.5 sm:mt-4 sm:gap-3 sm:grid-cols-3 lg:grid-cols-1 xl:grid-cols-3"
                        >
                            <article
                                v-for="signal in proofSignals"
                                :key="signal.title"
                                class="rounded-[1.2rem] border border-[#f1e6da] bg-[#fffaf4] p-3.5 sm:rounded-[1.4rem] sm:p-4"
                            >
                                <p
                                    class="text-[0.62rem] font-bold uppercase tracking-[0.22em] text-[#c55b24]"
                                >
                                    {{ signal.eyebrow }}
                                </p>
                                <p
                                    class="mt-2 text-[0.95rem] font-black tracking-[-0.02em] text-slate-950 sm:text-base"
                                >
                                    {{ signal.title }}
                                </p>
                                <p
                                    class="mt-1 text-[0.88rem] leading-5 text-slate-600 sm:text-sm sm:leading-6"
                                >
                                    {{ signal.subtitle }}
                                </p>
                            </article>
                        </div>
                    </section>

                    <section id="flash">
                        <FlashSale
                            :deals="flashFeed"
                            :currency="currency"
                            :ends-at="flashEndsAt"
                        />
                    </section>
                </section>

                <section
                    id="for-you"
                    class="rounded-[1.6rem] bg-[#fcfaf7] px-3.5 py-4 shadow-[0_12px_30px_rgba(15,23,42,0.04)] sm:rounded-[2rem] sm:px-5 sm:py-5"
                >
                    <ProductGrid
                        :title="t('For you, not for everyone')"
                        :subtitle="t('High-density feed')"
                        :products="feedProducts"
                        :currency="currency"
                        :pills="feedPills"
                    />
                </section>

                <section
                    id="trending"
                    class="grid gap-4 lg:grid-cols-[0.62fr_0.38fr]"
                >
                    <section
                        class="rounded-[1.6rem] bg-white px-3.5 py-4 shadow-[0_16px_38px_rgba(15,23,42,0.05)] sm:rounded-[2rem] sm:px-4 sm:py-5"
                    >
                        <div class="flex items-end justify-between gap-3">
                            <div>
                                <p
                                    class="text-[0.68rem] font-bold uppercase tracking-[0.24em] text-[#ff6b35]"
                                >
                                    {{ t("Trending right now") }}
                                </p>
                                <h2
                                    class="text-[1.35rem] font-black tracking-[-0.03em] text-slate-950 sm:text-2xl"
                                >
                                    {{
                                        t(
                                            "Fast-entry lanes that keep users scrolling",
                                        )
                                    }}
                                </h2>
                            </div>
                            <Link
                                href="/products"
                                class="shrink-0 text-[0.68rem] font-bold uppercase tracking-[0.16em] text-slate-500 transition hover:text-slate-950 sm:text-xs sm:tracking-[0.18em]"
                            >
                                {{ t("Browse all") }}
                            </Link>
                        </div>

                        <div
                            class="mt-3.5 grid gap-2.5 sm:mt-4 sm:gap-3 sm:grid-cols-2 xl:grid-cols-3"
                        >
                            <Link
                                v-for="lane in trendLanes"
                                :key="lane.title"
                                :href="lane.href"
                                class="group overflow-hidden rounded-[1.6rem] border border-[#f0e7dc] bg-[#faf5ef] transition hover:-translate-y-1 hover:shadow-[0_18px_40px_rgba(15,23,42,0.08)]"
                            >
                                <div
                                    class="aspect-[1.12] overflow-hidden bg-[#f3e9db]"
                                >
                                    <img
                                        v-if="lane.image"
                                        :src="lane.image"
                                        :alt="lane.title"
                                        class="h-full w-full object-cover transition duration-300 group-hover:scale-105"
                                        loading="lazy"
                                    />
                                </div>
                                <div
                                    class="space-y-2 px-3.5 py-3.5 sm:px-4 sm:py-4"
                                >
                                    <p
                                        class="text-[0.62rem] font-bold uppercase tracking-[0.22em] text-[#c55b24]"
                                    >
                                        {{ lane.kicker }}
                                    </p>
                                    <p
                                        class="text-base font-black tracking-[-0.02em] text-slate-950 sm:text-lg"
                                    >
                                        {{ lane.title }}
                                    </p>
                                    <p
                                        class="text-[0.88rem] leading-5 text-slate-600 sm:text-sm sm:leading-6"
                                    >
                                        {{ lane.subtitle }}
                                    </p>
                                    <span
                                        class="inline-flex rounded-full bg-white px-3 py-2 text-[0.62rem] font-bold uppercase tracking-[0.12em] text-slate-900 ring-1 ring-[#eadfce] sm:text-[0.68rem] sm:tracking-[0.14em]"
                                    >
                                        {{ lane.cta }}
                                    </span>
                                </div>
                            </Link>
                        </div>
                    </section>

                    <section
                        id="social"
                        class="rounded-[1.6rem] bg-[#111111] px-3.5 py-4 text-white shadow-[0_20px_48px_rgba(15,23,42,0.16)] sm:rounded-[2rem] sm:px-4 sm:py-5"
                    >
                        <p
                            class="text-[0.68rem] font-bold uppercase tracking-[0.24em] text-[#facc15]"
                        >
                            {{ t("Social proof") }}
                        </p>
                        <h2
                            class="mt-2 text-[1.35rem] font-black tracking-[-0.03em] sm:text-2xl"
                        >
                            {{ t("Why the feed converts harder") }}
                        </h2>
                        <div class="mt-4 space-y-2.5 sm:mt-5 sm:space-y-3">
                            <article
                                v-for="block in socialBlocks"
                                :key="block.title"
                                class="rounded-[1.2rem] border border-white/10 bg-white/8 p-3.5 backdrop-blur sm:rounded-[1.45rem] sm:p-4"
                            >
                                <p
                                    class="text-[0.62rem] font-bold uppercase tracking-[0.22em] text-white/50"
                                >
                                    {{ block.eyebrow }}
                                </p>
                                <p
                                    class="mt-2 text-[0.95rem] font-black sm:text-base"
                                >
                                    {{ block.title }}
                                </p>
                                <p
                                    class="mt-1 text-[0.88rem] leading-5 text-white/74 sm:text-sm sm:leading-6"
                                >
                                    {{ block.subtitle }}
                                </p>
                            </article>
                        </div>
                    </section>
                </section>

                <section
                    v-if="bannerStrip"
                    class="overflow-hidden rounded-[1.6rem] bg-[#111111] px-3.5 py-4 text-white shadow-[0_20px_48px_rgba(15,23,42,0.16)] sm:rounded-[2rem] sm:px-5 sm:py-5"
                >
                    <div
                        class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between"
                    >
                        <div>
                            <p
                                class="text-[0.68rem] font-bold uppercase tracking-[0.24em] text-[#facc15]"
                            >
                                {{ bannerStrip.kicker }}
                            </p>
                            <h2
                                class="mt-2 text-[1.35rem] font-black tracking-[-0.03em] sm:text-2xl"
                            >
                                {{ bannerStrip.title }}
                            </h2>
                        </div>
                        <Link
                            :href="bannerStrip.href"
                            class="inline-flex min-h-11 items-center justify-center rounded-full bg-[#ff6b35] px-4 text-[0.82rem] font-bold text-white shadow-[0_12px_28px_rgba(255,107,53,0.38)] transition hover:-translate-y-0.5 hover:bg-[#ff5420] sm:min-h-12 sm:px-5 sm:text-sm"
                        >
                            {{ bannerStrip.cta }}
                        </Link>
                    </div>
                </section>
            </div>
        </div>

        <StickyCartBar />
    </StorefrontLayout>
</template>

<script setup>
import BottomNav from "@/Components/homepage/BottomNav.vue";
import CategoryScroll from "@/Components/homepage/CategoryScroll.vue";
import FlashSale from "@/Components/homepage/FlashSale.vue";
import HeroBanner from "@/Components/homepage/HeroBanner.vue";
import ProductGrid from "@/Components/homepage/ProductGrid.vue";
import StickyCartBar from "@/Components/homepage/StickyCartBar.vue";
import {
    formatCountdown,
    usePromoNow,
} from "@/composables/usePromoCountdown.js";
import { useTranslations } from "@/i18n";
import StorefrontLayout from "@/Layouts/StorefrontLayout.vue";
import { Link } from "@inertiajs/vue3";
import { computed } from "vue";

const props = defineProps({
    featured: { type: Array, required: true },
    bestSellers: { type: Array, required: true },
    recommended: { type: Array, required: true },
    bestValue: { type: Array, default: () => [] },
    flashDeals: { type: Array, default: () => [] },
    flashDealsViewAllHref: { type: String, default: "/promotions/flash-sales" },
    categoryHighlights: { type: Array, default: () => [] },
    featuredCategorySections: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] },
    featuredCategories: { type: Array, default: () => [] },
    currency: { type: String, default: "USD" },
    homeContent: { type: Object, default: null },
    banners: { type: Object, default: () => ({}) },
    seasonalDrops: { type: Array, default: () => [] },
    seasonalDropsViewAllHref: { type: String, default: "/products" },
    homeCollections: { type: Array, default: () => [] },
    homeCollectionsViewAllHref: { type: String, default: "/collections" },
    homepagePromotions: { type: Array, default: () => [] },
    popularSearches: { type: Array, default: () => [] },
});

const { t } = useTranslations();
const now = usePromoNow();

const buildShort = (name) => {
    const initials = String(name || "")
        .split(" ")
        .filter(Boolean)
        .slice(0, 2)
        .map((word) => word[0])
        .join("")
        .toUpperCase();

    return (
        initials ||
        String(name || "")
            .slice(0, 2)
            .toUpperCase()
    );
};

const dedupeProducts = (items) => {
    const seen = new Set();

    return items.filter((item) => {
        if (!item?.id || seen.has(item.id)) return false;
        seen.add(item.id);
        return true;
    });
};

const socialProofLabel = (product) => {
    const reviews = Number(product?.rating_count ?? 0);
    if (reviews >= 100) return t(":count reviews", { count: reviews });
    if (reviews >= 10) return t(":count shopper reviews", { count: reviews });
    if ((product?.rating ?? 0) >= 4.5) return t("Top-rated pick");
    return t("Popular right now");
};

const urgencyLabel = (product) => {
    const stock = Number(product?.variants?.[0]?.stock_on_hand ?? 0);
    const threshold = Number(product?.variants?.[0]?.low_stock_threshold ?? 0);

    if (stock > 0 && threshold > 0 && stock <= threshold) {
        return t("Only :count left", { count: stock });
    }

    if (stock > 0 && stock <= 3) {
        return t("Only :count left", { count: stock });
    }

    return "";
};

const promotionForProduct = (product) => {
    return (
        props.homepagePromotions.find((promotion) => {
            const targets = Array.isArray(promotion?.targets)
                ? promotion.targets
                : [];
            if (targets.length === 0 && promotion?.is_sitewide) return true;

            return targets.some((target) => {
                if (target?.target_type === "product")
                    return Number(target.target_id) === Number(product.id);
                if (target?.target_type === "category")
                    return (
                        Number(target.target_id) === Number(product.category_id)
                    );
                return false;
            });
        }) || null
    );
};

const feedSources = computed(() => [
    {
        key: "flash",
        label: t("Flash"),
        badge: t("Hot deal"),
        items: props.flashDeals,
    },
    {
        key: "for-you",
        label: t("For you"),
        badge: t("For you"),
        items: props.recommended,
    },
    {
        key: "bestsellers",
        label: t("Best sellers"),
        badge: t("Bestseller"),
        items: props.bestSellers,
    },
    {
        key: "featured",
        label: t("Trending"),
        badge: t("Trending"),
        items: props.featured,
    },
    {
        key: "fresh",
        label: t("New value"),
        badge: t("Fresh drop"),
        items: props.bestValue,
    },
]);

const feedProducts = computed(() => {
    const flattened = feedSources.value.flatMap((source) =>
        (Array.isArray(source.items) ? source.items : []).map(
            (product, index) => {
                const promotion = promotionForProduct(product);
                const countdown = promotion?.end_at
                    ? formatCountdown(promotion.end_at, now.value)
                    : "";

                return {
                    ...product,
                    badge: promotion?.badge_text || source.badge,
                    feedTag: source.key,
                    sectionLabel: index < 2 ? source.label : "",
                    proofLabel: socialProofLabel(product),
                    urgencyLabel: urgencyLabel(product),
                    anchorLabel:
                        promotion?.value_type === "percentage"
                            ? t("Anchor price unlocked")
                            : promotion?.value_type === "fixed"
                              ? t("Discount applied at quick add")
                              : "",
                    dealEndsAtLabel: countdown || "",
                };
            },
        ),
    );

    return dedupeProducts(flattened);
});

const flashFeed = computed(() =>
    feedProducts.value
        .filter((product) => product.feedTag === "flash")
        .slice(0, 8),
);

const flashEndsAt = computed(() => {
    const flashPromotions = props.homepagePromotions
        .filter(
            (promotion) =>
                promotion?.type === "flash_sale" && promotion?.end_at,
        )
        .map((promotion) => String(promotion.end_at))
        .sort();

    return flashPromotions[0] || null;
});

const heroImage = computed(() => {
    return (
        props.banners?.hero?.[0]?.imagePath ||
        props.banners?.carousel?.[0]?.imagePath ||
        props.homeCollections?.[0]?.image ||
        props.seasonalDrops?.[0]?.image ||
        feedProducts.value[0]?.media?.[0] ||
        feedProducts.value[0]?.image ||
        null
    );
});

const heroBanner = computed(() => {
    const firstSlide = Array.isArray(props.homeContent?.hero_slides)
        ? props.homeContent.hero_slides[0]
        : null;

    return {
        kicker: firstSlide?.kicker || t("Today only"),
        badge: firstSlide?.badge || t("Simbazu deals"),
        title: firstSlide?.title || t("Hot picks, fast deals, new drops."),
        subtitle:
            firstSlide?.subtitle ||
            t(
                "Shop Simbazu like a live fashion feed with limited-time offers, quick categories, and daily deal momentum.",
            ),
        image: firstSlide?.image || heroImage.value,
        primary: firstSlide?.primary || {
            label: t("Shop the feed"),
            href: "#for-you",
        },
        secondary: firstSlide?.secondary || {
            label: t("Open flash sale"),
            href: "#flash",
        },
        callout: t("Daily Simbazu finds"),
        calloutBadge: t("Hot"),
    };
});

const heroStats = computed(() => [
    { label: t("Products surfaced"), value: `${feedProducts.value.length}+` },
    { label: t("Fast lanes"), value: `${scrollCategories.value.length}` },
    {
        label: t("Urgency blocks"),
        value: flashFeed.value.length ? t("Live") : t("Ready"),
    },
]);

const heroChips = computed(() => [
    { label: t("Flash sale"), target: "flash" },
    { label: t("For you"), target: "for-you" },
    { label: t("Trending lanes"), target: "trending" },
    { label: t("Departments"), target: "categories" },
]);

const heroHighlights = computed(() => [
    ...(Array.isArray(props.homeContent?.top_strip)
        ? props.homeContent.top_strip
        : []
    )
        .slice(0, 2)
        .map((item) => ({
            eyebrow: item.icon || t("Signal"),
            title: item.title || t("Storefront signal"),
            subtitle: item.subtitle || "",
        })),
]);

const scrollCategories = computed(() => {
    const source = (
        props.featuredCategories?.length
            ? props.featuredCategories
            : props.categories
    ).slice(0, 12);

    return source.map((category) => ({
        ...category,
        name: category.name,
        image:
            category.image || category.heroImage || category.hero_image || null,
        short: buildShort(category.name),
        href: category.slug
            ? `/categories/${encodeURIComponent(category.slug)}`
            : "/products",
        meta: t(":count items", {
            count: new Intl.NumberFormat().format(
                Number(category.count ?? category.product_count ?? 0),
            ),
        }),
    }));
});

const proofSignals = computed(() => [
    {
        eyebrow: t("Impulse"),
        title: t("Quick add stays close to the product"),
        subtitle: t(
            "Users never have to hunt for the CTA after spotting something they want.",
        ),
    },
    ...(Array.isArray(props.homeContent?.top_strip)
        ? props.homeContent.top_strip
        : []
    )
        .slice(0, 2)
        .map((item) => ({
            eyebrow: item.icon || t("Signal"),
            title: item.title || t("Storefront signal"),
            subtitle: item.subtitle || "",
        })),
]);

const collectionLanes = computed(() => {
    const collectionDrops = (
        Array.isArray(props.homeCollections) ? props.homeCollections : []
    )
        .slice(0, 3)
        .map((item) => ({
            title: item.title,
            kicker: item.kicker || item.tag || t("Collection"),
            subtitle:
                item.subtitle ||
                t(
                    "Curated edits built for faster browsing and stronger discovery.",
                ),
            image: item.image,
            href: item.href || "/collections",
            cta: t("Shop collection"),
            tag: item.tag || t("Collection"),
        }));

    if (collectionDrops.length) {
        return collectionDrops;
    }

    return (
        Array.isArray(props.featuredCategorySections)
            ? props.featuredCategorySections
            : []
    )
        .slice(0, 3)
        .map((section) => ({
            title: section.title || section.name || t("Curated edit"),
            kicker: t("Category edit"),
            subtitle:
                section.subtitle ||
                t(
                    "A merchandised lane built from top-performing storefront categories.",
                ),
            image:
                section.image ||
                section.products?.[0]?.media?.[0] ||
                section.products?.[0]?.image ||
                null,
            href:
                section.href ||
                (section.slug
                    ? `/categories/${encodeURIComponent(section.slug)}`
                    : "/products"),
            cta: t("Shop edit"),
            tag: t("Edit"),
        }));
});

const collectionsCtaHref = computed(
    () =>
        props.homeCollectionsViewAllHref ||
        collectionLanes.value[0]?.href ||
        null,
);

const trendLanes = computed(() => {
    const seasonal = (
        Array.isArray(props.seasonalDrops) ? props.seasonalDrops : []
    ).slice(0, 3);

    if (seasonal.length) {
        return seasonal.map((item) => ({
            title: item.title,
            kicker: item.kicker || item.tag || t("Trend lane"),
            subtitle:
                item.subtitle ||
                t(
                    "Merchandised for high-intent shoppers chasing the current drop.",
                ),
            image: item.image,
            href: item.href || props.seasonalDropsViewAllHref,
            cta: t("Enter lane"),
        }));
    }

    return scrollCategories.value.slice(0, 3).map((category) => ({
        title: category.name,
        kicker: t("Top category"),
        subtitle: t(
            "Jump into a curated lane with strong product density and faster discovery.",
        ),
        image: category.image,
        href: category.href,
        cta: t("Shop now"),
    }));
});

const socialBlocks = computed(() => [
    ...(Array.isArray(props.homeContent?.rail_cards)
        ? props.homeContent.rail_cards
        : []
    )
        .slice(0, 3)
        .map((card) => ({
            eyebrow: card.kicker || t("Storefront lane"),
            title: card.title || t("Merchandising lane"),
            subtitle: card.subtitle || "",
        })),
    {
        eyebrow: t("Scroll depth"),
        title: t("Infinite feed behavior"),
        subtitle: t(
            "The product grid reveals progressively, which keeps the page feeling fresh instead of finite.",
        ),
    },
]);

const feedPills = computed(() => [
    { key: "all", label: t("All") },
    ...feedSources.value
        .filter((source) => Array.isArray(source.items) && source.items.length)
        .map((source) => ({ key: source.key, label: source.label })),
]);

const sectionNavItems = computed(() => [
    { id: "categories", label: t("Categories") },
    { id: "collections", label: t("Collections") },
    { id: "intent", label: t("Intent") },
    { id: "flash", label: t("Flash") },
    { id: "for-you", label: t("For You") },
    { id: "trending", label: t("Trending") },
    { id: "social", label: t("Proof") },
]);

const searchIntentChips = computed(() => {
    const searchTerms = (
        Array.isArray(props.popularSearches) ? props.popularSearches : []
    )
        .slice(0, 6)
        .map((item) => ({
            label: item.query,
            href: item.href || `/search?q=${encodeURIComponent(item.query)}`,
        }));

    const categoryTerms = scrollCategories.value
        .slice(0, 4)
        .map((category) => ({
            label: t("Shop :name", { name: category.name }),
            href: category.href,
        }));

    const productTerms = feedProducts.value.slice(0, 4).map((product) => ({
        label: product.name,
        href: product.href || `/products/${product.slug}`,
    }));

    return [...searchTerms, ...categoryTerms, ...productTerms].slice(0, 10);
});

const bannerStrip = computed(() => {
    const strip = props.homeContent?.banner_strip;
    if (!strip || typeof strip !== "object") return null;

    return {
        kicker: strip.kicker || t("Storefront picks"),
        title: strip.title || t("Explore the storefront"),
        cta: strip.cta || t("Browse now"),
        href: strip.href || "/products",
    };
});

const scrollToSection = (id) => {
    if (typeof window === "undefined") return;
    const element = document.getElementById(id);
    if (!element) return;
    element.scrollIntoView({ behavior: "smooth", block: "start" });
};
</script>
