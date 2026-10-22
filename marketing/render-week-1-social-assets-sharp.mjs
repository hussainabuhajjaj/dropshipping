import fs from 'node:fs/promises';
import path from 'node:path';
import sharp from 'sharp';

const outputDir = path.resolve('marketing/week-1-assets');
const W = 1080;
const H = 1350;
const USD_XOF_RATE = 600;

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
  schoolAnimalBag: ['Animal backpack', 'PVYN7NET7', '9.12', 'https://cf.cjdropshipping.com/15656256/1608144987144.jpg'],
  waterproofSchoolbag: ['Waterproof schoolbag', 'PVKIEPTYF', '20.82', 'https://cf.cjdropshipping.com/2042/546530161223.jpg'],
  pencilCase: ['Canvas pencil case', 'PA7W602VB', '6.30', 'https://cf.cjdropshipping.com/aef8aa7c-8aa5-43ee-8fb9-9e3dd535288f.jpg'],
  cartoonBackpack: ['Cartoon backpack', 'PWMU0GNSP', '12.28', 'https://cf.cjdropshipping.com/92a96835-0b33-4441-b720-fecaee57ee22.jpg'],
  tumbler: ['40 oz tumbler', 'PEIF2DQL1', '32.06', 'https://oss-cf.cjdropshipping.com/product/2024/02/05/08/cee3f7df-3fff-49cf-ad9e-ef4e16d5f586.jpg'],
  keyBox: ['Waterproof key box', 'PZKKZCQG1', '25.98', 'https://cf.cjdropshipping.com/9f7c6401-529f-4416-8d9a-9062b54842bc.jpg'],
  bento: ['Airtight lunch box', 'PNM9QPX0I', '29.54', 'https://oss-cf.cjdropshipping.com/product/2026/01/28/05/2658e891-9422-4670-94ca-24f98d7f0787_trans.jpeg'],
  counterStorage: ['Kitchen storage box', 'PXYEE6BSH', '28.07', 'https://cf.cjdropshipping.com/quick/product/390767cf-d6ef-4b72-8f12-d1040eb9bb0f.jpg'],
  hairAccessory: ['Braided hair accessory', 'PZPUGENQQ', '1.96', 'https://cf.cjdropshipping.com/quick/product/4800f7d6-9eef-4ccb-a9ad-46b1805598b7.jpg'],
  hairClip: ['Star hair clip', 'PJ03U9LIO', '0.06', 'https://cf.cjdropshipping.com/quick/product/1d6ed894-ff98-4021-bc7c-c8eb8cd71782.jpg'],
  nailBee: ['Nail art ornament', 'P3VBIKLUQ', '0.07', 'https://cf.cjdropshipping.com/quick/product/82b6eab1-ff6e-4d90-83da-1f6abafe2eb7.jpg'],
  nailButterfly: ['Butterfly nail decor', 'P8LFUBPAT', '0.09', 'https://oss-cf.cjdropshipping.com/product/2025/10/27/03/b3ec9c5c-de53-48c9-878c-461b8fdc6009_trans.jpeg'],
  retroGlasses: ['Retro round glasses', 'PON2U1DDT', '10.44', 'https://cf.cjdropshipping.com/60a66f88-db9b-420c-b275-f6d9e75d35b2.jpg'],
  fashionGlasses: ['Fashion glasses', 'PLCTSHFT0', '4.75', 'https://cf.cjdropshipping.com/1617243413587.jpg'],
  roundSunglasses: ['Round sunglasses', 'PPWVQBOGJ', '6.52', 'https://cf.cjdropshipping.com/4122899b-391c-4f75-8be9-2f397f1935aa.jpg'],
  colorfulSunglasses: ['Color sunglasses', 'PS3G7ILUV', '3.65', 'https://cf.cjdropshipping.com/197419cb-bfe4-4e03-a100-7d5e6f045f0b.jpg'],
};

for (const key of Object.keys(products)) {
  const [name, code, price, image] = products[key];
  products[key] = { name, code, price, image };
}

function formatFcfa(usdPrice) {
  const xof = Math.round(Number(usdPrice) * USD_XOF_RATE);
  return `${new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 }).format(xof).replace(/\u202f/g, ' ')} FCFA`;
}

const posts = [
  ['day-01-brand-introduction.png', 'Day 1', 'Brand', 'Smart finds for daily life', 'Fashion, beauty, home, school, travel, and useful everyday products.', ['Des trouvailles utiles pour le quotidien', 'اختيارات ذكية للحياة اليومية'], 'Follow for daily finds', ['Fashion', 'Beauty', 'Home', 'School'], [products.waterproofSchoolbag, products.tumbler, products.hairAccessory, products.retroGlasses]],
  ['day-02-product-code-search.png', 'Day 2', 'Shopping Tip', 'Search faster with product code', 'Save the code, search it on Simbazu, and find the product again quickly.', ['Cherchez plus vite avec le code produit', 'ابحث بسرعة باستخدام كود المنتج'], 'Save the code', ['Product code', products.waterproofSchoolbag.code], [products.waterproofSchoolbag]],
  ['day-03-back-to-school-picks.png', 'Day 3', 'Back To School', 'Back-to-school picks', 'Bags, pencil cases, and useful school essentials customers can search by code.', ['Selections rentree', 'اختيارات العودة إلى المدرسة'], 'Shop school picks', ['School bags', 'Pencil cases', 'Lunch'], [products.schoolAnimalBag, products.waterproofSchoolbag, products.pencilCase, products.cartoonBackpack]],
  ['day-04-home-organization.png', 'Day 4', 'Home', 'Small upgrades, easier space', 'Simple products for desk, kitchen, keys, lunch, and daily organization.', ['Petites ameliorations maison', 'تغييرات منزلية صغيرة'], 'Explore home finds', ['Desk', 'Kitchen', 'Storage'], [products.tumbler, products.keyBox, products.bento, products.counterStorage]],
  ['day-05-beauty-finds.png', 'Day 5', 'Beauty', 'Beauty finds for your routine', 'Hair accessories and nail details for quick everyday content.', ['Essentiels beaute pour votre routine', 'منتجات جمال لروتينك اليومي'], 'Comment your favorite', ['Hair', 'Nails', 'Routine'], [products.hairAccessory, products.hairClip, products.nailBee, products.nailButterfly]],
  ['day-06-fashion-accessories.png', 'Day 6', 'Fashion', 'Details change the outfit', 'Glasses and sunglasses that make a simple look feel more styled.', ['Les details changent la tenue', 'التفاصيل تغير الإطلالة'], 'Explore fashion', ['Glasses', 'Sunglasses', 'Style'], [products.retroGlasses, products.fashionGlasses, products.roundSunglasses, products.colorfulSunglasses]],
  ['day-07-audience-poll.png', 'Day 7', 'Poll', 'What should we show more?', 'Fashion, beauty, home, or gadgets? Your vote decides the next picks.', ['Que voulez-vous voir plus ?', 'ماذا تريدون أن نعرض أكثر؟'], 'Vote or comment', ['Fashion', 'Beauty', 'Home', 'Gadgets'], [products.retroGlasses, products.hairAccessory, products.tumbler, products.schoolAnimalBag]],
].map(([file, day, label, headline, subhead, translations, cta, chips, items]) => ({ file, day, label, headline, subhead, translations, cta, chips, items }));

function esc(value) {
  return String(value)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}

function wrap(text, maxChars) {
  const words = String(text).split(/\s+/);
  const lines = [];
  let current = '';
  for (const word of words) {
    const next = current ? `${current} ${word}` : word;
    if (next.length > maxChars && current) {
      lines.push(current);
      current = word;
    } else {
      current = next;
    }
  }
  if (current) lines.push(current);
  return lines;
}

function multilineText(text, x, y, size, weight, color, maxChars, lineHeight, attrs = '') {
  return `<text x="${x}" y="${y}" font-size="${size}" font-weight="${weight}" fill="${color}" ${attrs}>${wrap(text, maxChars)
    .map((line, index) => `<tspan x="${x}" dy="${index === 0 ? 0 : lineHeight}">${esc(line)}</tspan>`)
    .join('')}</text>`;
}

function baseSvg(post) {
  const chips = post.chips.map((chip, i) => {
    const x = 58 + i * 170;
    return `<rect x="${x}" y="545" width="150" height="46" rx="23" fill="#ffffff" stroke="${brand.border}"/><text x="${x + 75}" y="575" text-anchor="middle" font-size="19" font-weight="800" fill="${brand.ink}">${esc(chip)}</text>`;
  }).join('');
  return `
    <svg width="${W}" height="${H}" xmlns="http://www.w3.org/2000/svg">
      <defs>
        <radialGradient id="glow1" cx="10%" cy="8%" r="45%">
          <stop offset="0%" stop-color="#f59e0b" stop-opacity="0.24"/>
          <stop offset="100%" stop-color="#f59e0b" stop-opacity="0"/>
        </radialGradient>
        <radialGradient id="glow2" cx="94%" cy="100%" r="40%">
          <stop offset="0%" stop-color="#020617" stop-opacity="0.15"/>
          <stop offset="100%" stop-color="#020617" stop-opacity="0"/>
        </radialGradient>
      </defs>
      <rect width="1080" height="1350" fill="${brand.cream}"/>
      <rect width="1080" height="1350" fill="url(#glow1)"/>
      <rect width="1080" height="1350" fill="url(#glow2)"/>
      <rect x="58" y="52" width="62" height="62" rx="18" fill="${brand.ink}"/>
      <text x="89" y="95" text-anchor="middle" font-size="34" font-weight="1000" fill="${brand.amber}">S</text>
      <text x="138" y="94" font-size="38" font-weight="1000" fill="${brand.ink}">${brand.name}</text>
      <text x="1022" y="77" text-anchor="end" font-size="23" font-weight="900" fill="${brand.muted}">${esc(post.day)}</text>
      <rect x="842" y="91" width="180" height="42" rx="21" fill="${brand.ink}"/>
      <text x="932" y="119" text-anchor="middle" font-size="18" font-weight="900" fill="#ffffff">${esc(post.label)}</text>
      ${multilineText(post.headline, 58, 236, post.headline.length > 30 ? 70 : 84, 1000, brand.ink, 22, 74)}
      ${multilineText(post.subhead, 58, 388, 28, 700, '#334155', 48, 36)}
      <text x="58" y="470" font-size="24" font-weight="800" fill="#475569">${esc(post.translations[0])}</text>
      <text x="58" y="510" font-size="24" font-weight="800" fill="#475569">${esc(post.translations[1])}</text>
      ${chips}
      <line x1="58" y1="1190" x2="1022" y2="1190" stroke="rgba(2,6,23,0.14)" stroke-width="1"/>
      <text x="58" y="1237" font-size="26" font-weight="1000" fill="${brand.ink}">${brand.handle}</text>
      <text x="58" y="1270" font-size="21" font-weight="800" fill="${brand.muted}">${brand.site}</text>
      <text x="1022" y="1252" text-anchor="end" font-size="34" font-weight="1000" fill="${brand.amberDark}">${esc(post.cta)}</text>
    </svg>
  `;
}

function cardSvg(item, index, width, height, imageWidth) {
  const textX = imageWidth + 26;
  return `
    <svg width="${width}" height="${height}" xmlns="http://www.w3.org/2000/svg">
      <rect x="0" y="0" width="${width}" height="${height}" rx="8" fill="#ffffff" stroke="${brand.border}"/>
      <rect x="${imageWidth}" y="0" width="${width - imageWidth}" height="${height}" fill="#ffffff"/>
      <rect x="${textX}" y="34" width="126" height="34" rx="17" fill="${brand.amber}"/>
      <text x="${textX + 63}" y="57" text-anchor="middle" font-size="16" font-weight="1000" fill="${brand.ink}">${esc(item.code)}</text>
      ${multilineText(item.name, textX, 110, width > 600 ? 34 : 24, 950, brand.ink, width > 600 ? 18 : 15, width > 600 ? 38 : 29)}
      <text x="${textX}" y="${height - 35}" font-size="${width > 600 ? 32 : 24}" font-weight="1000" fill="${brand.amberDark}">${esc(formatFcfa(item.price))}</text>
      <circle cx="28" cy="28" r="20" fill="${brand.ink}"/>
      <text x="28" y="36" text-anchor="middle" font-size="20" font-weight="950" fill="#ffffff">${index + 1}</text>
    </svg>
  `;
}

async function fetchImage(url, width, height) {
  try {
    const localPath = path.join(outputDir, 'product-images', `${this?.code ?? ''}.jpg`);
    if (this?.code) {
      try {
        await fs.access(localPath);
        return await sharp(localPath).resize(width, height, { fit: 'cover' }).png().toBuffer();
      } catch {
        // Fall back to the remote URL below.
      }
    }
    const response = await fetch(url);
    if (!response.ok) throw new Error(`${response.status}`);
    const buffer = Buffer.from(await response.arrayBuffer());
    return await sharp(buffer).resize(width, height, { fit: 'cover' }).png().toBuffer();
  } catch {
    return await sharp({
      create: { width, height, channels: 4, background: '#f8fafc' },
    }).png().toBuffer();
  }
}

async function makePost(post) {
  const layers = [{ input: Buffer.from(baseSvg(post)), left: 0, top: 0 }];
  const single = post.items.length === 1;
  const cardWidth = single ? 964 : 470;
  const cardHeight = single ? 430 : 236;
  const imageWidth = single ? 500 : 210;
  const startY = single ? 660 : 626;

  for (let i = 0; i < post.items.length; i++) {
    const col = single ? 0 : i % 2;
    const row = single ? 0 : Math.floor(i / 2);
    const left = 58 + col * 494;
    const top = startY + row * 258;
    layers.push({ input: Buffer.from(cardSvg(post.items[i], i, cardWidth, cardHeight, imageWidth)), left, top });
    layers.push({ input: await fetchImage.call(post.items[i], post.items[i].image, imageWidth, cardHeight), left, top });
  }

  await sharp({
    create: { width: W, height: H, channels: 4, background: brand.cream },
  })
    .composite(layers)
    .png()
    .toFile(path.join(outputDir, post.file));
}

await fs.mkdir(outputDir, { recursive: true });
for (const post of posts) {
  await makePost(post);
}
await fs.writeFile(
  path.join(outputDir, 'manifest.json'),
  JSON.stringify(posts.map(({ file, day, label, headline, cta }) => ({ file, day, label, headline, cta })), null, 2),
);
