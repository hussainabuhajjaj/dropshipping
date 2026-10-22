<template>
  <StorefrontLayout>
    <Head :title="campaign.name">
      <meta
        v-if="lucky_draw && lucky_draw.seo && lucky_draw.seo.title"
        name="title"
        head-key="title"
        :content="lucky_draw.seo.title"
      />
      <meta
        v-if="lucky_draw && lucky_draw.seo && lucky_draw.seo.description"
        name="description"
        head-key="description"
        :content="lucky_draw.seo.description"
      />
    </Head>

    <div class="bg-[#090909]">
      <div class="relative overflow-hidden">
        <div class="absolute inset-0 bg-gradient-to-b from-amber-500/5 via-transparent to-transparent" />
        <div class="absolute -top-40 left-1/2 h-[500px] w-[500px] -translate-x-1/2 rounded-full bg-amber-500/10 blur-[120px]" />
        <div class="absolute -top-20 left-1/3 h-[300px] w-[300px] rounded-full bg-white/5 blur-[80px]" />

        <div v-for="n in 30" :key="n" class="confetti-piece"
          :style="{
            left: Math.random() * 100 + '%',
            animationDelay: Math.random() * 8 + 's',
            animationDuration: (3 + Math.random() * 4) + 's',
            backgroundColor: ['#f59e0b', '#fbbf24', '#fcd34d', '#fff', '#fef3c7'][n % 5],
            width: (4 + Math.random() * 6) + 'px',
            height: (4 + Math.random() * 6) + 'px',
            borderRadius: Math.random() > 0.5 ? '50%' : '2px',
          }"
        />

        <div class="relative mx-auto max-w-7xl px-4 pb-16 pt-12 sm:px-6 sm:pt-20 lg:pt-28">
          <div class="text-center">
            <div v-if="campaign.hero_kicker" class="inline-flex items-center gap-2 rounded-full border border-amber-500/30 bg-amber-500/10 px-4 py-1.5 text-xs font-semibold tracking-wider text-amber-400 uppercase">
              <span class="h-2 w-2 animate-pulse rounded-full bg-amber-400" />
              {{ campaign.hero_kicker }}
            </div>

            <h1 class="mt-6 bg-gradient-to-r from-amber-200 via-amber-400 to-amber-200 bg-clip-text text-4xl font-black leading-none tracking-tight text-transparent sm:text-5xl md:text-6xl lg:text-7xl">
              {{ campaign.name }}
            </h1>

            <p class="mx-auto mt-4 max-w-2xl text-base text-zinc-400 sm:text-lg">
              {{ campaign.hero_subtitle }}
            </p>

            <div class="mt-8 flex flex-col items-center justify-center gap-4 sm:flex-row">
              <a href="/products" class="inline-flex items-center gap-2 rounded-xl bg-amber-500 px-8 py-3.5 text-sm font-bold text-black transition-all hover:bg-amber-400 active:scale-[0.97]">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 00-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 00-16.536-1.84M7.5 14.25L5.106 5.272M6 20.25a.75.75 0 11-1.5 0 .75.75 0 011.5 0zm12.75 0a.75.75 0 11-1.5 0 .75.75 0 011.5 0z" /></svg>
                {{ t('Shop Now') }}
              </a>
              <a href="/collections" class="inline-flex items-center gap-2 rounded-xl border border-zinc-700 bg-zinc-800/50 px-8 py-3.5 text-sm font-bold text-white transition-all hover:bg-zinc-700 active:scale-[0.97]">
                {{ t('View Products') }}
              </a>
            </div>

            <div v-if="showCountdown" class="mt-12">
              <div class="mx-auto flex max-w-lg items-center justify-center gap-8 rounded-2xl border border-zinc-800 bg-zinc-900/60 px-6 py-4 backdrop-blur-sm">
                <div class="text-center">
                  <span class="block text-2xl font-bold text-white tabular-nums">{{ days }}</span>
                  <span class="text-[0.65rem] font-semibold tracking-wider text-zinc-500 uppercase">{{ t('Days') }}</span>
                </div>
                <div class="h-10 w-px bg-zinc-800" />
                <div class="text-center">
                  <span class="block text-2xl font-bold text-white tabular-nums">{{ hours }}</span>
                  <span class="text-[0.65rem] font-semibold tracking-wider text-zinc-500 uppercase">{{ t('Hours') }}</span>
                </div>
                <div class="h-10 w-px bg-zinc-800" />
                <div class="text-center">
                  <span class="block text-2xl font-bold text-white tabular-nums">{{ minutes }}</span>
                  <span class="text-[0.65rem] font-semibold tracking-wider text-zinc-500 uppercase">{{ t('Minutes') }}</span>
                </div>
                <div class="h-10 w-px bg-zinc-800" />
                <div class="text-center">
                  <span class="block text-2xl font-bold text-amber-400 tabular-nums">{{ seconds }}</span>
                  <span class="text-[0.65rem] font-semibold tracking-wider text-zinc-500 uppercase">{{ t('Seconds') }}</span>
                </div>
              </div>
            </div>

            <div v-if="lucky_draw.show_remaining_spots && lucky_draw.max_participants" class="mx-auto mt-8 max-w-lg">
              <div class="flex items-center justify-between text-xs font-semibold">
                <span class="uppercase tracking-wider text-zinc-400">{{ t('Spots Filled') }}</span>
                <span class="text-amber-400 tabular-nums">{{ lucky_draw.spots_filled }} / {{ lucky_draw.max_participants }}</span>
              </div>
              <div class="mt-2 h-3 w-full overflow-hidden rounded-full bg-zinc-800">
                <div class="h-full rounded-full bg-gradient-to-r from-amber-500 to-amber-400 transition-all duration-700"
                  :style="{ width: spotsPercentage + '%' }" />
              </div>
              <p class="mt-2 text-center text-[0.7rem] text-zinc-500">
                {{ remainingSpotsText }}
              </p>
            </div>
          </div>

          <div class="relative mx-auto mt-16 max-w-3xl">
            <div class="absolute inset-0 bg-gradient-to-t from-amber-500/20 via-amber-500/5 to-transparent rounded-[3rem] blur-3xl" />
            <div class="relative flex items-center justify-center rounded-[2rem] border border-zinc-800 bg-gradient-to-b from-zinc-900 to-[#090909] p-8 shadow-2xl shadow-amber-500/10">
              <div class="absolute -top-4 left-1/2 -translate-x-1/2">
                <div class="inline-flex items-center gap-1.5 rounded-full bg-gradient-to-r from-amber-500 to-amber-400 px-4 py-1 text-[0.6rem] font-bold tracking-widest text-black uppercase shadow-lg">
                  <svg class="h-3 w-3" viewBox="0 0 24 24" fill="currentColor"><path d="M11.645 20.91l-.007-.003-.022-.012a15.247 15.247 0 01-.383-.218 25.18 25.18 0 01-4.244-3.17C4.688 15.36 2.25 12.174 2.25 8.25 2.25 5.322 4.714 3 7.688 3A5.5 5.5 0 0112 5.052 5.5 5.5 0 0116.313 3c2.973 0 5.437 2.322 5.437 5.25 0 3.925-2.438 7.111-4.739 9.256a25.175 25.175 0 01-4.244 3.17 15.247 15.247 0 01-.383.219l-.022.012-.007.004-.003.001a.752.752 0 01-.704 0l-.003-.001z" /></svg>
                  {{ t('Grand Prize') }}
                </div>
              </div>
              <div class="relative h-48 w-48 sm:h-64 sm:w-64">
                <div class="absolute inset-0 rounded-full bg-gradient-to-br from-amber-400/30 via-amber-500/20 to-transparent blur-2xl" />
                <div class="relative flex h-full w-full items-center justify-center">
                  <div class="text-center">
                    <div class="mx-auto mb-2 flex h-20 w-20 items-center justify-center rounded-2xl bg-gradient-to-br from-amber-400 to-amber-600 shadow-lg shadow-amber-500/30 sm:h-24 sm:w-24">
                      <svg class="h-10 w-10 text-white sm:h-12 sm:w-12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 1.5H8.25A2.25 2.25 0 006 3.75v16.5a2.25 2.25 0 002.25 2.25h7.5A2.25 2.25 0 0018 20.25V3.75a2.25 2.25 0 00-2.25-2.25H13.5m-3 0V3h3V1.5m-3 0h3m-3 18.75h3" /></svg>
                    </div>
                    <p class="text-sm font-bold text-white">{{ lucky_draw.grand_prize }}</p>
                    <p class="text-[0.65rem] text-zinc-500">{{ t('1 Winner') }}</p>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div v-if="lucky_draw.runner_up_count > 0" class="mx-auto mt-6 flex max-w-3xl flex-wrap items-center justify-center gap-2">
            <span class="rounded-full border border-zinc-800 bg-zinc-900/60 px-4 py-1.5 text-xs text-zinc-400">
              {{ t('Runner-ups:') }} <span class="font-bold text-white">{{ lucky_draw.runner_up_count }}</span>
              {{ t('x') }} {{ formatGiftCard(lucky_draw.gift_card_amount, lucky_draw.gift_card_currency) }}
            </span>
            <span v-if="lucky_draw.guaranteed_reward_type" class="rounded-full border border-zinc-800 bg-zinc-900/60 px-4 py-1.5 text-xs text-zinc-400">
              {{ t('Everyone gets') }} <span class="font-bold text-amber-400">{{ guaranteedRewardText }}</span>
            </span>
          </div>
        </div>
      </div>
    </div>

    <div class="border-t border-zinc-800/50 bg-[#090909]">
      <div class="mx-auto max-w-7xl px-4 py-10 sm:px-6">
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
          <div v-for="stat in trustStats" :key="stat.label" class="flex items-center gap-3 rounded-xl border border-zinc-800 bg-zinc-900/50 p-4 backdrop-blur-sm">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-amber-500/10">
              <svg class="h-5 w-5 text-amber-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <path v-if="stat.icon === 'package'" stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5m8.25 3v6.75m0 0l-3-3m3 3l3-3M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" />
                <path v-else-if="stat.icon === 'shield'" stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" />
                <path v-else-if="stat.icon === 'truck'" stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 00-3.213-9.193 2.056 2.056 0 00-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 00-10.026 0 1.106 1.106 0 00-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12" />
                <path v-else stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a5.969 5.969 0 01-.474-.065 4.48 4.48 0 00.978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z" />
              </svg>
            </div>
            <div>
              <p class="text-sm font-bold text-white">{{ stat.label }}</p>
              <p class="text-[0.65rem] text-zinc-500">{{ stat.desc }}</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div v-if="campaign.content" class="prose mx-auto max-w-7xl px-4 py-10 prose-invert sm:px-6" v-html="campaign.content"></div>

    <div v-if="lucky_draw.landing_content" class="prose mx-auto max-w-7xl px-4 py-10 prose-invert sm:px-6" v-html="lucky_draw.landing_content"></div>

    <div class="border-t border-zinc-800/50 bg-[#090909]">
      <div class="mx-auto max-w-7xl px-4 py-16 sm:px-6">
        <div class="text-center">
          <p class="text-[0.65rem] font-semibold tracking-[0.3em] text-amber-400 uppercase">{{ t('How It Works') }}</p>
          <h2 class="mt-2 text-2xl font-black text-white sm:text-3xl">{{ t('Your Path to Winning') }}</h2>
        </div>
        <div class="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
          <div v-for="(step, i) in steps" :key="i" class="relative rounded-2xl border border-zinc-800 bg-zinc-900/50 p-6 text-center backdrop-blur-sm">
            <div class="absolute -top-3 left-1/2 -translate-x-1/2">
              <span class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-gradient-to-br from-amber-400 to-amber-600 text-xs font-bold text-black shadow-lg shadow-amber-500/30">0{{ i + 1 }}</span>
            </div>
            <div class="mx-auto mt-2 flex h-14 w-14 items-center justify-center rounded-xl bg-amber-500/10">
              <svg class="h-7 w-7 text-amber-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <path v-if="step.icon === 'shopping-cart'" stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 00-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 00-16.536-1.84M7.5 14.25L5.106 5.272M6 20.25a.75.75 0 11-1.5 0 .75.75 0 011.5 0zm12.75 0a.75.75 0 11-1.5 0 .75.75 0 011.5 0z" />
                <path v-else-if="step.icon === 'check-circle'" stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                <path v-else-if="step.icon === 'calendar'" stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
                <path v-else stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.563.563 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.563.563 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z" />
              </svg>
            </div>
            <h3 class="mt-4 text-base font-bold text-white">{{ step.title }}</h3>
            <p class="mt-1 text-xs text-zinc-500">{{ step.desc }}</p>
          </div>
        </div>
      </div>
    </div>

    <div v-if="products.length" class="border-t border-zinc-800/50 bg-[#090909]">
      <div class="mx-auto max-w-7xl px-4 py-16 sm:px-6">
        <div class="text-center">
          <p class="text-[0.65rem] font-semibold tracking-[0.3em] text-amber-400 uppercase">{{ t('Featured Products') }}</p>
          <h2 class="mt-2 text-2xl font-black text-white sm:text-3xl">{{ t('Shop These Top Picks') }}</h2>
          <p class="mt-2 text-sm text-zinc-500">{{ t('Every purchase qualifies you for the draw') }}</p>
        </div>
        <div class="mt-8 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
          <a v-for="product in products" :key="product.id" :href="'/products/' + product.slug" class="group rounded-xl border border-zinc-800 bg-zinc-900 overflow-hidden transition-all hover:border-amber-500/50 hover:shadow-lg hover:shadow-amber-500/5">
            <div class="aspect-square bg-zinc-800 overflow-hidden">
              <img v-if="product.image" :src="product.image" :alt="product.name" class="h-full w-full object-cover transition-transform group-hover:scale-105" />
              <div v-else class="flex h-full items-center justify-center">
                <svg class="h-10 w-10 text-zinc-700" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.41a2.25 2.25 0 013.182 0l2.909 2.91m-18 3.75h16.5a1.5 1.5 0 001.5-1.5V6a1.5 1.5 0 00-1.5-1.5H3.75A1.5 1.5 0 002.25 6v12a1.5 1.5 0 001.5 1.5zm10.5-11.25h.008v.008h-.008V8.25z" /></svg>
              </div>
            </div>
            <div class="p-3">
              <p class="text-xs text-zinc-400 truncate">{{ product.name }}</p>
              <p class="mt-1 text-sm font-bold text-white">{{ formatPrice(product.price, product.currency) }}</p>
              <p v-if="product.compare_at_price" class="text-[0.65rem] text-zinc-500"><span class="line-through">{{ formatPrice(product.compare_at_price, product.currency) }}</span></p>
            </div>
          </a>
        </div>
        <div class="mt-8 text-center">
          <a href="/products" class="inline-flex items-center gap-2 rounded-xl border border-zinc-700 bg-zinc-800/50 px-6 py-3 text-sm font-bold text-white transition-all hover:bg-zinc-700">
            {{ t('View All Products') }}
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" /></svg>
          </a>
        </div>
      </div>
    </div>

    <div v-if="lucky_draw.entry" class="border-t border-zinc-800/50 bg-[#090909]">
      <div class="mx-auto max-w-3xl px-4 py-10 sm:px-6">
        <div class="rounded-2xl border p-6 text-center backdrop-blur-sm"
          :class="entryIsWinner
            ? 'border-amber-500/50 bg-gradient-to-b from-amber-500/15 to-transparent'
            : 'border-zinc-800 bg-zinc-900/50'">
          <p class="text-[0.65rem] font-semibold tracking-[0.3em] text-amber-400 uppercase">{{ t('Your Entry') }}</p>
          <div class="mt-4 flex flex-wrap items-center justify-center gap-3">
            <span v-if="lucky_draw.entry.spot_number" class="rounded-full bg-amber-500/10 px-4 py-1.5 text-sm font-bold text-amber-400">
              {{ t('Spot') }} #{{ lucky_draw.entry.spot_number }}
            </span>
            <span class="rounded-full bg-zinc-800 px-4 py-1.5 text-sm text-zinc-300">{{ entryStateText }}</span>
            <span v-if="lucky_draw.entry.is_winner" class="rounded-full bg-gradient-to-r from-amber-500 to-amber-400 px-4 py-1.5 text-sm font-bold text-black">
              {{ t('Winner!') }}
            </span>
          </div>
          <p v-if="lucky_draw.entry.prize_label" class="mt-4 text-sm font-semibold text-white">{{ lucky_draw.entry.prize_label }}</p>
          <p v-if="lucky_draw.entry.reward_code && !lucky_draw.entry.is_winner" class="mt-2 text-sm text-zinc-400">
            {{ t('Reward code:') }} <span class="font-mono font-bold text-amber-400">{{ lucky_draw.entry.reward_code }}</span>
          </p>
        </div>
      </div>
    </div>

    <div class="border-t border-zinc-800/50 bg-[#090909]">
      <div class="mx-auto max-w-3xl px-4 py-16 sm:px-6">
        <div class="text-center">
          <p class="text-[0.65rem] font-semibold tracking-[0.3em] text-amber-400 uppercase">{{ t('FAQ') }}</p>
          <h2 class="mt-2 text-2xl font-black text-white sm:text-3xl">{{ t('Frequently Asked Questions') }}</h2>
        </div>
        <div class="mt-8 space-y-3">
          <div v-for="(item, i) in faq" :key="i" class="overflow-hidden rounded-xl border border-zinc-800">
            <button @click="toggleFaq(i)" class="flex w-full items-center justify-between bg-zinc-900/50 px-5 py-4 text-left text-sm font-semibold text-white transition-colors hover:bg-zinc-800/50">
              {{ item.q }}
              <svg class="h-4 w-4 shrink-0 text-zinc-500 transition-transform" :class="openFaq === i ? 'rotate-180' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" /></svg>
            </button>
            <div v-if="openFaq === i" class="border-t border-zinc-800 px-5 py-4 text-sm text-zinc-400">
              {{ item.a }}
            </div>
          </div>
          <div v-if="lucky_draw.terms" class="rounded-xl border border-zinc-800/60 bg-zinc-900/30 px-5 py-4">
            <p class="text-[0.6rem] font-semibold tracking-[0.3em] text-zinc-500 uppercase">{{ t('Terms & Conditions') }}</p>
            <div class="mt-2 text-xs leading-relaxed text-zinc-500" v-html="lucky_draw.terms"></div>
          </div>
        </div>
      </div>
    </div>

    <div class="sticky bottom-0 z-50 border-t border-zinc-800 bg-[#090909]/95 backdrop-blur-lg">
      <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-3 sm:px-6">
        <div class="hidden items-center gap-3 sm:flex">
          <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-gradient-to-br from-amber-400 to-amber-600">
            <svg class="h-5 w-5 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 1.5H8.25A2.25 2.25 0 006 3.75v16.5a2.25 2.25 0 002.25 2.25h7.5A2.25 2.25 0 0018 20.25V3.75a2.25 2.25 0 00-2.25-2.25H13.5m-3 0V3h3V1.5m-3 0h3m-3 18.75h3" /></svg>
          </div>
          <div>
            <p class="text-xs font-bold text-white">{{ t('Win a') }} {{ lucky_draw.grand_prize }}</p>
            <p class="text-[0.6rem] text-zinc-500">{{ t('Spend') }} {{ formatPrice(lucky_draw.min_order_amount, lucky_draw.currency) }} {{ t('to enter') }}</p>
          </div>
        </div>
        <a href="/products" class="flex w-full items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-amber-500 to-amber-400 px-6 py-2.5 text-sm font-bold text-black transition-all hover:from-amber-400 hover:to-amber-300 sm:w-auto">
          {{ t('Shop Now') }}
          <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" /></svg>
        </a>
      </div>
    </div>
  </StorefrontLayout>
</template>

<script setup>
import { computed, ref, onMounted, onUnmounted } from 'vue'
import { Head } from '@inertiajs/vue3'
import { useTranslations } from '@/i18n'
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue'

const props = defineProps({
  campaign: { type: Object, required: true },
  lucky_draw: { type: Object, default: () => ({}) },
  products: { type: Array, default: () => [] },
  promotions: { type: Array, default: () => [] },
  coupons: { type: Array, default: () => [] },
  banners: { type: Array, default: () => [] },
  collections: { type: Array, default: () => [] },
  trustStats: { type: Array, default: () => [] },
})

const { t } = useTranslations()

const openFaq = ref(null)
function toggleFaq(i) {
  openFaq.value = openFaq.value === i ? null : i
}

const countdownTarget = computed(() => {
  const value = props.lucky_draw.winner_announcement_at || props.campaign.ends_at
  return value ? new Date(value).getTime() : null
})

const showCountdown = computed(() =>
  props.lucky_draw.countdown_enabled && !!countdownTarget.value
)

const now = ref(Date.now())
let timer = null

const diff = computed(() => Math.max(0, (countdownTarget.value || 0) - now.value))
const days = computed(() => Math.floor(diff.value / 86400000))
const hours = computed(() => Math.floor((diff.value % 86400000) / 3600000))
const minutes = computed(() => Math.floor((diff.value % 3600000) / 60000))
const seconds = computed(() => Math.floor((diff.value % 60000) / 1000))

onMounted(() => {
  if (countdownTarget.value) {
    timer = setInterval(() => { now.value = Date.now() }, 1000)
  }
})

onUnmounted(() => {
  if (timer) clearInterval(timer)
})

const spotsPercentage = computed(() => {
  const max = Number(props.lucky_draw.max_participants || 0)
  const filled = Number(props.lucky_draw.spots_filled || 0)
  return max > 0 ? Math.min(100, Math.round((filled / max) * 100)) : 0
})

const remainingSpotsText = computed(() => {
  const remaining = Number(props.lucky_draw.remaining_spots || 0)
  return remaining > 0
    ? t(':count spots left — reserve yours by ordering now', { count: remaining })
    : t('All spots have been filled')
})

const entryIsWinner = computed(() => !!(props.lucky_draw.entry && props.lucky_draw.entry.is_winner))

const entryStateText = computed(() => {
  const state = props.lucky_draw.entry && props.lucky_draw.entry.state
  if (state === 'winner') return t('Winner')
  if (state === 'reward_issued') return t('Reward issued')
  if (state === 'spot_reserved') return t('Spot reserved')
  return t('Qualified')
})

const steps = computed(() => [
  {
    icon: 'shopping-cart',
    title: t('Shop'),
    desc: t('Spend :amount or more in one order', { amount: formatPrice(props.lucky_draw.min_order_amount, props.lucky_draw.currency) }),
  },
  { icon: 'check-circle', title: t('Auto-Enter'), desc: t('You\'re automatically entered — no codes needed') },
  { icon: 'calendar', title: t('Wait'), desc: drawDateText },
  { icon: 'award', title: t('Win'), desc: t('Lucky customers take home great prizes') },
])

const drawDateText = computed(() => {
  const value = props.lucky_draw.winner_announcement_at || props.campaign.ends_at
  return value
    ? t('Sit tight until the live draw on :date', { date: new Date(value).toLocaleDateString() })
    : t('Sit tight until the live draw')
})

const guaranteedRewardText = computed(() => {
  const type = props.lucky_draw.guaranteed_reward_type
  const value = Number(props.lucky_draw.guaranteed_reward_value || 0)
  if (type === 'free_shipping') return t('FREE Shipping')
  if (type === 'percentage_discount' || type === 'coupon_code') return `${value}% OFF`
  if (type === 'fixed_discount') return formatPrice(value, props.lucky_draw.currency)
  if (type === 'store_credit') return t('Store Credit')
  return ''
})

const faq = computed(() => {
  if (Array.isArray(props.lucky_draw.faq) && props.lucky_draw.faq.length) {
    return props.lucky_draw.faq
  }
  const amount = formatPrice(props.lucky_draw.min_order_amount, props.lucky_draw.currency)
  const date = props.lucky_draw.winner_announcement_at || props.campaign.ends_at
  return [
    { q: t('How do I enter the draw?'), a: t('Place an order of :amount or more on Simbazu to be automatically entered into the draw. No additional registration required.', { amount }) },
    { q: t('When will the draw take place?'), a: date ? t('The draw will take place on :date. Winners will be announced on our website and social media.', { date: new Date(date).toLocaleDateString() }) : t('Winners will be announced on our website and social media.') },
    { q: t('Can I enter multiple times?'), a: t('Yes, each qualifying order gives you an additional entry. The more you shop, the better your chances.') },
    { q: t('Will winners be notified?'), a: t('Yes, winners will be contacted by email and phone shortly after the draw.') },
    { q: t('Are there any hidden fees?'), a: t('No, participation is automatic and free with any qualifying order.') },
  ]
})

function formatGiftCard(amount, currency) {
  return formatPrice(amount, currency === 'XOF' ? 'XOF' : currency)
}

function formatPrice(amount, currency) {
  const n = Number(amount || 0).toLocaleString()
  if (currency === 'USD') return `$${n}`
  return `${n} FCFA`
}
</script>

<style scoped>
.confetti-piece {
  position: fixed;
  top: -10px;
  z-index: 10;
  pointer-events: none;
  animation: confettiFall linear infinite;
}

@keyframes confettiFall {
  0% { transform: translateY(-10px) rotate(0deg) scale(1); opacity: 1; }
  100% { transform: translateY(100vh) rotate(720deg) scale(0.5); opacity: 0; }
}
</style>
