<template>
  <StorefrontLayout>
    <Head :title="t('Win the New iPhone 17')" />

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
            <div class="inline-flex items-center gap-2 rounded-full border border-amber-500/30 bg-amber-500/10 px-4 py-1.5 text-xs font-semibold tracking-wider text-amber-400 uppercase">
              <span class="h-2 w-2 animate-pulse rounded-full bg-amber-400" />
              {{ t('Limited Time Campaign') }}
            </div>

            <h1 class="mt-6 bg-gradient-to-r from-amber-200 via-amber-400 to-amber-200 bg-clip-text text-4xl font-black leading-none tracking-tight text-transparent sm:text-5xl md:text-6xl lg:text-7xl">
              {{ t('WIN THE NEW') }}
              <br />
              <span class="text-white">{{ t('iPHONE 17') }}</span>
            </h1>

            <p class="mx-auto mt-4 max-w-2xl text-base text-zinc-400 sm:text-lg">
              {{ t('Spend 30,000 FCFA or more in one order and automatically enter the draw for your chance to win.') }}
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

            <div class="mt-12">
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
                    <p class="text-sm font-bold text-white">iPhone 17</p>
                    <p class="text-[0.65rem] text-zinc-500">{{ t('1 Winner') }}</p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="bg-[#090909] border-t border-zinc-800/50">
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

    <div class="bg-[#090909]">
      <div class="mx-auto max-w-7xl px-4 py-16 sm:px-6">
        <div class="text-center">
          <p class="text-[0.65rem] font-semibold tracking-[0.3em] text-amber-400 uppercase">{{ t('Categories') }}</p>
          <h2 class="mt-2 text-2xl font-black text-white sm:text-3xl">{{ t('Shop by Category') }}</h2>
          <p class="mt-2 text-sm text-zinc-500">{{ t('Find your winning product') }}</p>
        </div>
        <div class="mt-8 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
          <a v-for="cat in categories" :key="cat.name"
            :href="'/category/' + cat.slug"
            class="group relative overflow-hidden rounded-2xl border border-zinc-800 bg-zinc-900 p-6 transition-all hover:border-amber-500/50 hover:shadow-lg hover:shadow-amber-500/10"
          >
            <div class="absolute inset-0 bg-gradient-to-br opacity-0 transition-opacity group-hover:opacity-10" :class="cat.color" />
            <div class="relative">
              <div class="flex h-14 w-14 items-center justify-center rounded-xl bg-zinc-800 group-hover:bg-amber-500/20 transition-colors">
                <svg v-if="cat.name === t('Fashion')" class="h-7 w-7 text-amber-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3-6l3-3m0 0l-3-3m3 3H9" /></svg>
                <svg v-else-if="cat.name === t('Electronics')" class="h-7 w-7 text-amber-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 1.5H8.25A2.25 2.25 0 006 3.75v16.5a2.25 2.25 0 002.25 2.25h7.5A2.25 2.25 0 0018 20.25V3.75a2.25 2.25 0 00-2.25-2.25H13.5m-3 0V3h3V1.5m-3 0h3m-3 18.75h3" /></svg>
                <svg v-else-if="cat.name === t('Beauty')" class="h-7 w-7 text-amber-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9.53 16.122a3 3 0 00-5.78 1.128 2.25 2.25 0 01-2.4 2.245 4.5 4.5 0 008.4-2.245c0-.399-.078-.78-.22-1.128zm0 0a15.998 15.998 0 003.388-1.62m-5.043-.025a15.994 15.994 0 011.622-3.395m3.42 3.42a15.995 15.995 0 004.764-4.648l3.876-5.814a1.151 1.151 0 00-1.597-1.597L14.146 6.32a15.996 15.996 0 00-4.649 4.763m3.42 3.42a6.776 6.776 0 00-3.42-3.42" /></svg>
                <svg v-else-if="cat.name === t('Home')" class="h-7 w-7 text-amber-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" /></svg>
                <svg v-else class="h-7 w-7 text-amber-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 003 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 005.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 009.568 3z" /><path stroke-linecap="round" stroke-linejoin="round" d="M6 6h.008v.008H6V6z" /></svg>
              </div>
              <h3 class="mt-4 text-base font-bold text-white group-hover:text-amber-400 transition-colors">{{ cat.name }}</h3>
            </div>
          </a>
        </div>
      </div>
    </div>

    <div class="bg-[#090909] border-t border-zinc-800/50">
      <div class="mx-auto max-w-7xl px-4 py-16 sm:px-6">
        <div class="text-center">
          <p class="text-[0.65rem] font-semibold tracking-[0.3em] text-amber-400 uppercase">{{ t('How It Works') }}</p>
          <h2 class="mt-2 text-2xl font-black text-white sm:text-3xl">{{ t('Your Path to Winning') }}</h2>
        </div>
        <div class="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
          <div v-for="(step, i) in steps" :key="i" class="relative rounded-2xl border border-zinc-800 bg-zinc-900/50 p-6 text-center backdrop-blur-sm">
            <div class="absolute -top-3 left-1/2 -translate-x-1/2">
              <span class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-gradient-to-br from-amber-400 to-amber-600 text-xs font-bold text-black shadow-lg shadow-amber-500/30">0{{ step.step }}</span>
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

    <div class="bg-[#090909] border-t border-zinc-800/50">
      <div class="mx-auto max-w-7xl px-4 py-16 sm:px-6">
        <div class="text-center">
          <p class="text-[0.65rem] font-semibold tracking-[0.3em] text-amber-400 uppercase">{{ t('Featured Products') }}</p>
          <h2 class="mt-2 text-2xl font-black text-white sm:text-3xl">{{ t('Shop These Top Picks') }}</h2>
          <p class="mt-2 text-sm text-zinc-500">{{ t('Every purchase qualifies you for the giveaway') }}</p>
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

    <div class="bg-[#090909] border-t border-zinc-800/50">
      <div class="mx-auto max-w-7xl px-4 py-16 sm:px-6">
        <div class="text-center">
          <p class="text-[0.65rem] font-semibold tracking-[0.3em] text-amber-400 uppercase">{{ t('Why Choose Simbazu') }}</p>
          <h2 class="mt-2 text-2xl font-black text-white sm:text-3xl">{{ t('The Best Shopping Experience') }}</h2>
        </div>
        <div class="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
          <div v-for="feature in features" :key="feature.title" class="rounded-xl border border-zinc-800 bg-zinc-900/50 p-5 backdrop-blur-sm transition-all hover:border-amber-500/30">
            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-amber-500/10">
              <svg class="h-5 w-5 text-amber-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <path v-if="feature.icon === 'shopping-bag'" stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5V6a3.75 3.75 0 10-7.5 0v4.5m11.356-1.993l1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 01-1.12-1.243l1.264-12A1.125 1.125 0 015.513 7.5h12.974c.576 0 1.059.435 1.119 1.007zM8.625 10.5a.375.375 0 11-.75 0 .375.375 0 01.75 0zm7.5 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" />
                <path v-else-if="feature.icon === 'shield-check'" stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" />
                <path v-else-if="feature.icon === 'truck'" stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 00-3.213-9.193 2.056 2.056 0 00-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 00-10.026 0 1.106 1.106 0 00-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12" />
                <path v-else-if="feature.icon === 'headphones'" stroke-linecap="round" stroke-linejoin="round" d="M20.25 8.511c.884.284 1.5 1.128 1.5 2.097v4.286c0 1.136-.847 2.1-1.98 2.193-.34.027-.68.052-1.02.072v3.091l-3-3c-1.354 0-2.694-.055-4.02-.163a2.115 2.115 0 01-.825-.242m9.345-8.334a2.126 2.126 0 00-.476-.095 48.64 48.64 0 00-8.048 0c-1.131.094-1.976 1.057-1.976 2.192v4.286c0 .837.46 1.58 1.155 1.951m9.345-8.334V6.637c0-1.621-1.152-3.026-2.76-3.235A48.455 48.455 0 0011.25 3c-2.115 0-4.198.137-6.24.402-1.608.209-2.76 1.614-2.76 3.235v6.226c0 1.621 1.152 3.026 2.76 3.235.577.075 1.157.14 1.74.194V21l4.155-4.155" />
                <path v-else-if="feature.icon === 'rotate-ccw'" stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182" />
                <path v-else stroke-linecap="round" stroke-linejoin="round" d="M21 11.25v-.225a18.188 18.188 0 00-5.25-3.9m0 0a17.415 17.415 0 00-5.25-1.5c-1.903 0-3.718.33-5.25.9m10.5 4.5v7.5a2.25 2.25 0 01-2.25 2.25H9.75A2.25 2.25 0 017.5 18.75v-7.5m10.5 0V9.75a2.25 2.25 0 00-2.25-2.25H7.5A2.25 2.25 0 005.25 9.75v1.5M21 11.25H5.25" />
              </svg>
            </div>
            <h3 class="mt-3 text-sm font-bold text-white">{{ feature.title }}</h3>
            <p class="mt-1 text-xs text-zinc-500">{{ feature.desc }}</p>
          </div>
        </div>
      </div>
    </div>

    <div class="bg-[#090909] border-t border-zinc-800/50">
      <div class="mx-auto max-w-7xl px-4 py-16 sm:px-6">
        <div class="text-center">
          <p class="text-[0.65rem] font-semibold tracking-[0.3em] text-amber-400 uppercase">{{ t('Reviews') }}</p>
          <h2 class="mt-2 text-2xl font-black text-white sm:text-3xl">{{ t('What Our Customers Say') }}</h2>
        </div>
        <div class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
          <div v-for="(review, i) in reviews" :key="i" class="rounded-xl border border-zinc-800 bg-zinc-900/50 p-5 backdrop-blur-sm">
            <div class="flex items-center gap-1">
              <svg v-for="s in 5" :key="s" class="h-4 w-4" :class="s <= review.rating ? 'text-amber-400' : 'text-zinc-700'" viewBox="0 0 20 20" fill="currentColor"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" /></svg>
            </div>
            <p class="mt-3 text-sm text-zinc-300">"{{ review.text }}"</p>
            <div class="mt-3 flex items-center gap-2">
              <div class="flex h-8 w-8 items-center justify-center rounded-full bg-amber-500/20 text-xs font-bold text-amber-400">{{ review.name.charAt(0) }}</div>
              <div>
                <p class="text-xs font-semibold text-white">{{ review.name }}</p>
                <p class="text-[0.6rem] text-zinc-600">{{ review.location }}</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="bg-[#090909] border-t border-zinc-800/50">
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
            <p class="text-xs font-bold text-white">{{ t('Win an iPhone 17') }}</p>
            <p class="text-[0.6rem] text-zinc-500">{{ t('Spend 30,000 FCFA to enter') }}</p>
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
  products: { type: Array, default: () => [] },
  faq: { type: Array, default: () => [] },
  campaignEndsAt: { type: String, required: true },
  minOrderAmount: { type: Number, default: 30000 },
  winnerCount: { type: Number, default: 1 },
  trustStats: { type: Array, default: () => [] },
  categories: { type: Array, default: () => [] },
  features: { type: Array, default: () => [] },
  steps: { type: Array, default: () => [] },
})

const { t } = useTranslations()

const openFaq = ref(null)

function toggleFaq(i) {
  openFaq.value = openFaq.value === i ? null : i
}

const now = ref(Date.now())
const target = ref(new Date(props.campaignEndsAt).getTime())
let timer = null

const diff = computed(() => Math.max(0, target.value - now.value))

const days = computed(() => Math.floor(diff.value / 86400000))
const hours = computed(() => Math.floor((diff.value % 86400000) / 3600000))
const minutes = computed(() => Math.floor((diff.value % 3600000) / 60000))
const seconds = computed(() => Math.floor((diff.value % 60000) / 1000))

onMounted(() => {
  timer = setInterval(() => { now.value = Date.now() }, 1000)
})

onUnmounted(() => {
  if (timer) clearInterval(timer)
})

function formatPrice(amount, currency) {
  const sym = currency === 'USD' ? '$' : 'CFA'
  const n = Number(amount).toLocaleString()
  return sym === '$' ? `$${n}` : `${n} FCFA`
}

const reviews = [
  { name: 'Aminata K.', location: 'Abidjan', rating: 5, text: t('I\'ve been shopping on Simbazu for months. Fast delivery and great quality. I hope I win the iPhone!') },
  { name: 'Kouamé B.', location: 'Bouaké', rating: 5, text: t('The giveaway got me shopping more. Already placed two orders this month. Fingers crossed!') },
  { name: 'Fatou D.', location: 'Abidjan', rating: 4, text: t('Great selection of products and the delivery was faster than expected. Definitely participating in the giveaway.') },
  { name: 'Mamadou S.', location: 'Yamoussoukro', rating: 5, text: t('I never win anything, but with Simbazu at least I get great products while trying my luck!') },
  { name: 'Aicha T.', location: 'San Pedro', rating: 5, text: t('Been using Simbazu for all my shopping needs. The iPhone giveaway is the cherry on top.') },
  { name: 'Jean-Pierre L.', location: 'Abidjan', rating: 4, text: t('The WhatsApp ordering is super convenient. Love the selection of electronics.') },
]
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
