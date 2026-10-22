import fs from 'node:fs/promises';
import path from 'node:path';
import { chromium } from 'playwright';

const outputDir = path.resolve('marketing/week-1-assets');

const brand = {
  name: 'Simbazu',
  handle: '@simbazu.online',
  site: 'simbazu.net',
  amber: '#f59e0b',
  amberDark: '#d97706',
  ink: '#020617',
  cream: '#f7f4ef',
  warm: '#fffaf3',
  border: '#e7ded1',
  muted: '#667085',
};

const products = {
  schoolAnimalBag: {
    name: 'Animal backpack',
    code: 'PVYN7NET7',
    price: '$9.12',
    image: 'https://cf.cjdropshipping.com/15656256/1608144987144.jpg',
  },
  waterproofSchoolbag: {
    name: 'Waterproof schoolbag',
    code: 'PVKIEPTYF',
    price: '$20.82',
    image: 'https://cf.cjdropshipping.com/2042/546530161223.jpg',
  },
  pencilCase: {
    name: 'Canvas pencil case',
    code: 'PA7W602VB',
    price: '$6.30',
    image: 'https://cf.cjdropshipping.com/aef8aa7c-8aa5-43ee-8fb9-9e3dd535288f.jpg',
  },
  cartoonBackpack: {
    name: 'Cartoon backpack',
    code: 'PWMU0GNSP',
    price: '$12.28',
    image: 'https://cf.cjdropshipping.com/92a96835-0b33-4441-b720-fecaee57ee22.jpg',
  },
  tumbler: {
    name: '40 oz tumbler',
    code: 'PEIF2DQL1',
    price: '$32.06',
    image: 'https://oss-cf.cjdropshipping.com/product/2024/02/05/08/cee3f7df-3fff-49cf-ad9e-ef4e16d5f586.jpg',
  },
  keyBox: {
    name: 'Waterproof key box',
    code: 'PZKKZCQG1',
    price: '$25.98',
    image: 'https://cf.cjdropshipping.com/9f7c6401-529f-4416-8d9a-9062b54842bc.jpg',
  },
  bento: {
    name: 'Airtight lunch box',
    code: 'PNM9QPX0I',
    price: '$29.54',
    image: 'https://oss-cf.cjdropshipping.com/product/2026/01/28/05/2658e891-9422-4670-94ca-24f98d7f0787_trans.jpeg',
  },
  counterStorage: {
    name: 'Kitchen storage box',
    code: 'PXYEE6BSH',
    price: '$28.07',
    image: 'https://cf.cjdropshipping.com/quick/product/390767cf-d6ef-4b72-8f12-d1040eb9bb0f.jpg',
  },
  hairAccessory: {
    name: 'Braided hair accessory',
    code: 'PZPUGENQQ',
    price: '$1.96',
    image: 'https://cf.cjdropshipping.com/quick/product/4800f7d6-9eef-4ccb-a9ad-46b1805598b7.jpg',
  },
  hairClip: {
    name: 'Star hair clip',
    code: 'PJ03U9LIO',
    price: '$0.06',
    image: 'https://cf.cjdropshipping.com/quick/product/1d6ed894-ff98-4021-bc7c-c8eb8cd71782.jpg',
  },
  nailBee: {
    name: 'Nail art ornament',
    code: 'P3VBIKLUQ',
    price: '$0.07',
    image: 'https://cf.cjdropshipping.com/quick/product/82b6eab1-ff6e-4d90-83da-1f6abafe2eb7.jpg',
  },
  nailButterfly: {
    name: 'Butterfly nail decor',
    code: 'P8LFUBPAT',
    price: '$0.09',
    image: 'https://oss-cf.cjdropshipping.com/product/2025/10/27/03/b3ec9c5c-de53-48c9-878c-461b8fdc6009_trans.jpeg',
  },
  retroGlasses: {
    name: 'Retro round glasses',
    code: 'PON2U1DDT',
    price: '$10.44',
    image: 'https://cf.cjdropshipping.com/60a66f88-db9b-420c-b275-f6d9e75d35b2.jpg',
  },
  fashionGlasses: {
    name: 'Fashion glasses',
    code: 'PLCTSHFT0',
    price: '$4.75',
    image: 'https://cf.cjdropshipping.com/1617243413587.jpg',
  },
  roundSunglasses: {
    name: 'Round sunglasses',
    code: 'PPWVQBOGJ',
    price: '$6.52',
    image: 'https://cf.cjdropshipping.com/4122899b-391c-4f75-8be9-2f397f1935aa.jpg',
  },
  colorfulSunglasses: {
    name: 'Color sunglasses',
    code: 'PS3G7ILUV',
    price: '$3.65',
    image: 'https://cf.cjdropshipping.com/197419cb-bfe4-4e03-a100-7d5e6f045f0b.jpg',
  },
};

const posts = [
  {
    file: 'day-01-brand-introduction.png',
    day: 'Day 1',
    label: 'Brand',
    headline: 'Smart finds for daily life',
    subhead: 'Fashion, beauty, home, school, travel, and useful everyday products.',
    translations: ['Des trouvailles utiles pour le quotidien', 'اختيارات ذكية للحياة اليومية'],
    cta: 'Follow for daily finds',
    chips: ['Fashion', 'Beauty', 'Home', 'School'],
    items: [products.waterproofSchoolbag, products.tumbler, products.hairAccessory, products.retroGlasses],
  },
  {
    file: 'day-02-product-code-search.png',
    day: 'Day 2',
    label: 'Shopping Tip',
    headline: 'Search faster with product code',
    subhead: 'Save the code, search it on Simbazu, and find the product again quickly.',
    translations: ['Cherchez plus vite avec le code produit', 'ابحث بسرعة باستخدام كود المنتج'],
    cta: 'Save the code',
    chips: ['Product code', products.waterproofSchoolbag.code],
    items: [products.waterproofSchoolbag],
  },
  {
    file: 'day-03-back-to-school-picks.png',
    day: 'Day 3',
    label: 'Back To School',
    headline: 'Back-to-school picks',
    subhead: 'Bags, pencil cases, and useful school essentials customers can search by code.',
    translations: ['Selections rentree', 'اختيارات العودة إلى المدرسة'],
    cta: 'Shop school picks',
    chips: ['School bags', 'Pencil cases', 'Lunch'],
    items: [products.schoolAnimalBag, products.waterproofSchoolbag, products.pencilCase, products.cartoonBackpack],
  },
  {
    file: 'day-04-home-organization.png',
    day: 'Day 4',
    label: 'Home',
    headline: 'Small upgrades, easier space',
    subhead: 'Simple products for desk, kitchen, keys, lunch, and daily organization.',
    translations: ['Petites ameliorations maison', 'تغييرات منزلية صغيرة'],
    cta: 'Explore home finds',
    chips: ['Desk', 'Kitchen', 'Storage'],
    items: [products.tumbler, products.keyBox, products.bento, products.counterStorage],
  },
  {
    file: 'day-05-beauty-finds.png',
    day: 'Day 5',
    label: 'Beauty',
    headline: 'Beauty finds for your routine',
    subhead: 'Hair accessories and nail details for quick everyday content.',
    translations: ['Essentiels beaute pour votre routine', 'منتجات جمال لروتينك اليومي'],
    cta: 'Comment your favorite',
    chips: ['Hair', 'Nails', 'Routine'],
    items: [products.hairAccessory, products.hairClip, products.nailBee, products.nailButterfly],
  },
  {
    file: 'day-06-fashion-accessories.png',
    day: 'Day 6',
    label: 'Fashion',
    headline: 'Details change the outfit',
    subhead: 'Glasses and sunglasses that make a simple look feel more styled.',
    translations: ['Les details changent la tenue', 'التفاصيل تغير الإطلالة'],
    cta: 'Explore fashion',
    chips: ['Glasses', 'Sunglasses', 'Style'],
    items: [products.retroGlasses, products.fashionGlasses, products.roundSunglasses, products.colorfulSunglasses],
  },
  {
    file: 'day-07-audience-poll.png',
    day: 'Day 7',
    label: 'Poll',
    headline: 'What should we show more?',
    subhead: 'Fashion, beauty, home, or gadgets? Your vote decides the next picks.',
    translations: ['Que voulez-vous voir plus ?', 'ماذا تريدون أن نعرض أكثر؟'],
    cta: 'Vote or comment',
    chips: ['Fashion', 'Beauty', 'Home', 'Gadgets'],
    items: [products.retroGlasses, products.hairAccessory, products.tumbler, products.schoolAnimalBag],
  },
];

function escapeHtml(value) {
  return String(value)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}

function productCard(item, index, compact = false) {
  return `
    <article class="product ${compact ? 'compact' : ''}">
      <div class="image-wrap">
        <img src="${escapeHtml(item.image)}" alt="">
      </div>
      <div class="meta">
        <span class="code">${escapeHtml(item.code)}</span>
        <strong>${escapeHtml(item.name)}</strong>
        <span class="price">${escapeHtml(item.price)}</span>
      </div>
      <span class="number">${index + 1}</span>
    </article>
  `;
}

function renderPost(post) {
  const single = post.items.length === 1;
  const productHtml = post.items.map((item, index) => productCard(item, index, single)).join('');
  const chipHtml = post.chips.map((chip) => `<span>${escapeHtml(chip)}</span>`).join('');
  const translationHtml = post.translations.map((line) => `<p>${escapeHtml(line)}</p>`).join('');

  return `
    <!doctype html>
    <html lang="en">
      <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <style>
          * { box-sizing: border-box; }
          body {
            margin: 0;
            width: 1080px;
            height: 1350px;
            background: ${brand.cream};
            color: ${brand.ink};
            font-family: Inter, Arial, "Noto Sans Arabic", sans-serif;
          }
          .canvas {
            width: 1080px;
            height: 1350px;
            padding: 58px;
            background:
              linear-gradient(180deg, rgba(255,255,255,0.78), rgba(255,250,243,0.96)),
              radial-gradient(circle at 12% 8%, rgba(245,158,11,0.22), transparent 30%),
              radial-gradient(circle at 92% 100%, rgba(2,6,23,0.16), transparent 34%);
            display: grid;
            grid-template-rows: auto auto 1fr auto;
            gap: 34px;
            overflow: hidden;
          }
          .top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 24px;
          }
          .brand {
            display: flex;
            align-items: center;
            gap: 16px;
            font-weight: 950;
            font-size: 38px;
            letter-spacing: 0;
          }
          .mark {
            width: 62px;
            height: 62px;
            border-radius: 18px;
            background: ${brand.ink};
            color: ${brand.amber};
            display: grid;
            place-items: center;
            font-size: 34px;
            font-weight: 950;
          }
          .day {
            display: flex;
            flex-direction: column;
            align-items: end;
            gap: 8px;
            color: ${brand.muted};
            font-size: 24px;
            font-weight: 850;
            text-transform: uppercase;
          }
          .day b {
            border-radius: 999px;
            background: ${brand.ink};
            color: white;
            padding: 10px 18px;
            font-size: 20px;
          }
          .hero {
            display: grid;
            gap: 18px;
          }
          h1 {
            margin: 0;
            max-width: 920px;
            font-size: 92px;
            line-height: 0.94;
            letter-spacing: 0;
            font-weight: 1000;
          }
          .subhead {
            max-width: 820px;
            margin: 0;
            color: #334155;
            font-size: 31px;
            line-height: 1.22;
            font-weight: 650;
          }
          .translations {
            display: grid;
            gap: 6px;
            color: #475569;
            font-size: 25px;
            line-height: 1.2;
            font-weight: 750;
          }
          .translations p { margin: 0; }
          .chips {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
          }
          .chips span {
            border: 1px solid rgba(2,6,23,0.12);
            background: rgba(255,255,255,0.82);
            border-radius: 999px;
            padding: 12px 17px;
            font-size: 22px;
            font-weight: 900;
          }
          .products {
            display: grid;
            grid-template-columns: ${single ? '1fr' : 'repeat(2, minmax(0, 1fr))'};
            gap: 18px;
            align-content: center;
          }
          .product {
            min-width: 0;
            position: relative;
            overflow: hidden;
            border: 1px solid ${brand.border};
            border-radius: 8px;
            background: white;
            min-height: 265px;
            display: grid;
            grid-template-columns: 44% 1fr;
            box-shadow: 0 20px 46px rgba(15,23,42,0.08);
          }
          .product.compact {
            min-height: 430px;
            grid-template-columns: 52% 1fr;
          }
          .image-wrap {
            min-width: 0;
            background: #f8fafc;
            overflow: hidden;
          }
          img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
          }
          .meta {
            min-width: 0;
            padding: 26px 22px 24px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            gap: 13px;
          }
          .code {
            width: fit-content;
            border-radius: 999px;
            background: ${brand.amber};
            color: ${brand.ink};
            padding: 8px 12px;
            font-size: 18px;
            line-height: 1;
            font-weight: 1000;
          }
          strong {
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
            font-size: 26px;
            line-height: 1.05;
            font-weight: 950;
          }
          .product.compact strong {
            font-size: 34px;
          }
          .price {
            color: ${brand.amberDark};
            font-size: 30px;
            font-weight: 1000;
          }
          .number {
            position: absolute;
            left: 14px;
            top: 14px;
            width: 42px;
            height: 42px;
            border-radius: 999px;
            background: ${brand.ink};
            color: white;
            display: grid;
            place-items: center;
            font-size: 20px;
            font-weight: 950;
          }
          .bottom {
            border-top: 1px solid rgba(2,6,23,0.14);
            padding-top: 26px;
            display: flex;
            justify-content: space-between;
            align-items: end;
            gap: 24px;
          }
          .handle {
            display: grid;
            gap: 5px;
            font-size: 25px;
            font-weight: 950;
          }
          .handle span {
            color: ${brand.muted};
            font-size: 20px;
            font-weight: 750;
          }
          .cta {
            max-width: 420px;
            text-align: right;
            color: ${brand.amberDark};
            font-size: 34px;
            line-height: 1;
            font-weight: 1000;
          }
        </style>
      </head>
      <body>
        <main class="canvas">
          <section class="top">
            <div class="brand"><span class="mark">S</span>${brand.name}</div>
            <div class="day"><span>${escapeHtml(post.day)}</span><b>${escapeHtml(post.label)}</b></div>
          </section>
          <section class="hero">
            <h1>${escapeHtml(post.headline)}</h1>
            <p class="subhead">${escapeHtml(post.subhead)}</p>
            <div class="translations">${translationHtml}</div>
            <div class="chips">${chipHtml}</div>
          </section>
          <section class="products">${productHtml}</section>
          <section class="bottom">
            <div class="handle">${brand.handle}<span>${brand.site}</span></div>
            <div class="cta">${escapeHtml(post.cta)}</div>
          </section>
        </main>
      </body>
    </html>
  `;
}

async function renderAll() {
  await fs.mkdir(outputDir, { recursive: true });
  const chromePath = '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
  const browser = await chromium.launch({
    headless: true,
    executablePath: chromePath,
  });
  const page = await browser.newPage({ viewport: { width: 1080, height: 1350 }, deviceScaleFactor: 1 });

  for (const post of posts) {
    await page.setContent(renderPost(post), { waitUntil: 'networkidle' });
    await page.screenshot({
      path: path.join(outputDir, post.file),
      clip: { x: 0, y: 0, width: 1080, height: 1350 },
      type: 'png',
    });
  }

  await browser.close();
  await fs.writeFile(
    path.join(outputDir, 'manifest.json'),
    JSON.stringify(posts.map(({ file, day, label, headline, cta }) => ({ file, day, label, headline, cta })), null, 2),
  );
}

await renderAll();
