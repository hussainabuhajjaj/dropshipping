import fs from 'node:fs/promises';
import path from 'node:path';
import sharp from 'sharp';

const W = 1080;
const H = 1350;
const inputImageDir = path.resolve('marketing/week-1-assets/product-images');
const outputDir = path.resolve('marketing/product-posts/assets');
const contentPath = path.resolve('marketing/product-posts/product-post-content.csv');
const USD_XOF_RATE = 600;

const brand = {
  amber: '#f59e0b',
  amberDark: '#d97706',
  ink: '#020617',
  cream: '#f7f4ef',
  border: '#e7ded1',
  muted: '#667085',
};

const themeLabels = {
  back_to_school: ['Back to school', 'Rentree scolaire', 'العودة إلى المدرسة'],
  home_organization: ['Home organization', 'Organisation maison', 'تنظيم المنزل'],
  beauty: ['Beauty finds', 'Selection beaute', 'اختيارات الجمال'],
  fashion_accessories: ['Fashion accessories', 'Accessoires mode', 'اكسسوارات الموضة'],
  travel: ['Travel picks', 'Selection voyage', 'اختيارات السفر'],
};

const products = [
  ['back_to_school', 1, 'kindergarten small school bag animal backpack', 'Animal backpack', 'PVYN7NET7', '9.12', 'School Bags', 'https://simbazu.net/products/kindergarten-small-school-bag-animal-backpack-4f94a038-9117-4993-a7ee-cd86f0cc1c50', 'Cute starter backpack for younger children'],
  ['back_to_school', 2, 'Nylon wear-resistant waterproof schoolbag', 'Waterproof schoolbag', 'PVKIEPTYF', '20.82', 'School Bags', 'https://simbazu.net/products/nylon-wear-resistant-waterproof-schoolbag-5ba27082-41ed-42be-83b6-f044630ab913', 'Practical waterproof school bag'],
  ['back_to_school', 3, "Army Fan Pencil Case Men's Canvas Pencil Case", 'Canvas pencil case', 'PA7W602VB', '6.30', 'Office & School Supplies', 'https://simbazu.net/products/army-fan-pencil-case-mens-canvas-pencil-case-1412694339196424192', 'Low-cost stationery add-on'],
  ['back_to_school', 4, 'Creative Pencil Case Pencil Case Plain Starry Sky Pen Curtain Roll Pencil Case Color Lead Sketch', 'Creative pencil case', 'PVJRI685Z', '4.67', 'Office & School Supplies', 'https://simbazu.net/products/creative-pencil-case-pencil-case-plain-starry-sky-pen-curtain-roll-pencil-case-color-lead-sketch-1424205823625793536', 'Creative pencil case for school and drawing'],
  ['back_to_school', 5, "Children's Diving School Bag Cartoon Cute Animal Print Backpack", 'Cartoon backpack', 'PWMU0GNSP', '12.28', 'School Bags', 'https://simbazu.net/products/childrens-diving-school-bag-cartoon-cute-animal-print-backpack-1505065684471132160', 'Cartoon backpack for back-to-school content'],
  ['back_to_school', 6, 'Qin Le 304 Insulated Lunch Box EBay Sealed Lunch Box Dinner Plate Amazon Silicone Fresh-keeping Box Lunch Box', 'Insulated lunch box', 'PETVJMJ8Z', '24.52', 'Office & School Supplies', 'https://simbazu.net/products/qin-le-304-insulated-lunch-box-ebay-sealed-lunch-box-dinner-plate-amazon-silicone-fresh-keeping-box-lunch-box-1398464875264610304', 'Lunch box for school routine posts'],
  ['home_organization', 1, '40 Oz Tumbler With Handle Straw Insulated, Stainless Steel Spill Proof Vacuum Coffee Cup Tumbler With Lid Tapered Mug Gifts For Valentine Lover Suitable For Car Gym Office Travel', '40 oz insulated tumbler', 'PEIF2DQL1', '32.06', 'Home Office Storage', 'https://simbazu.net/products/40-oz-tumbler-with-handle-straw-insulated-stainless-steel-spill-proof-vacuum-coffee-cup-tumbler-with-lid-tapered-mug-gifts-for-valentine-lover-suitable-for-car-gym-office-travel-1610884907201474560', 'Desk, office, gym, and travel hydration'],
  ['home_organization', 2, 'Temporary Metal Waterproof Key Box At The Door', 'Waterproof key box', 'PZKKZCQG1', '25.98', 'Home Office Storage', 'https://simbazu.net/products/temporary-metal-waterproof-key-box-at-the-door-4978336f-15c5-476b-bfe8-c907659b70bb', 'Small home security and key organization'],
  ['home_organization', 3, 'Bow Cosmetic Bag Large Capacity Travel Skincare Storage', 'Bow cosmetic storage bag', 'PNIE3IFLH', '5.73', 'Home Office Storage', 'https://simbazu.net/products/bow-cosmetic-bag-large-capacity-travel-skincare-storage-2504160835041618900', 'Beauty and travel storage bag'],
  ['home_organization', 4, 'Travel Storage Toiletries Sub-package Bag', 'Travel toiletries organizer', 'PG6USVFQY', '27.25', 'Home Office Storage', 'https://simbazu.net/products/travel-storage-toiletries-sub-package-bag-1791643361879789568', 'Travel toiletries organizer'],
  ['home_organization', 5, 'Insulated Lunch Box Airtight Bento Lunch Container', 'Airtight bento lunch box', 'PNM9QPX0I', '29.54', 'Storage Bottles & Jars', 'https://simbazu.net/products/insulated-lunch-box-airtight-bento-lunch-container-2601280528321600200', 'Meal prep and school/work lunch organization'],
  ['home_organization', 6, 'Double-layer Countertop Storage Box For The Kitchen', 'Kitchen storage box', 'PXYEE6BSH', '28.07', 'Storage Bottles & Jars', 'https://simbazu.net/products/double-layer-countertop-storage-box-for-the-kitchen-2602080341051616600', 'Kitchen countertop organization'],
  ['beauty', 1, 'Braided Chain Hair Accessories Wholesale Serpentine Accessories', 'Braided hair accessory', 'PZPUGENQQ', '1.96', 'Headband & Hair Band & Hairpin', 'https://simbazu.net/products/braided-chain-hair-accessories-wholesale-serpentine-accessories-1796377933309816832', 'Easy hair accessory for daily styling'],
  ['beauty', 2, 'Golden Five-pointed Star Hair Clip Silver Side-swept Bangs For Women', 'Star hair clip', 'PJ03U9LIO', '0.06', 'Headband & Hair Band & Hairpin', 'https://simbazu.net/products/golden-five-pointed-star-hair-clip-silver-side-swept-bangs-for-women-2602080735181626900', 'Cute star hair clip for quick beauty content'],
  ['beauty', 3, 'Japanese Style Nail Ornament Little Bee', 'Little bee nail ornament', 'P3VBIKLUQ', '0.07', 'Nail Decorations', 'https://simbazu.net/products/japanese-style-nail-ornament-little-bee-2410310230151621600', 'Small nail art detail'],
  ['beauty', 4, 'New Nail Beauty Butterfly Silver Metal Alloy Rhinestone DIY Ornament Accessories', 'Butterfly nail decor', 'P8LFUBPAT', '0.09', 'Nail Art Kits', 'https://simbazu.net/products/new-nail-beauty-butterfly-silver-metal-alloy-rhinestone-diy-ornament-accessories-2510270402251625700', 'Butterfly nail art hook'],
  ['beauty', 5, 'Cute Starfish And Seashell Nail Art Accessories Featuring Adorable Marine Life', 'Seashell nail art', 'PWZX4L3AE', '0.09', 'Nail Art Kits', 'https://simbazu.net/products/cute-starfish-and-seashell-nail-art-accessories-featuring-adorable-marine-life-2601270522031637500', 'Summer/sea themed nail art'],
  ['beauty', 6, 'Love Hair Band Candy Color Headband Highly Elastic Hair Rope Hair Elastic Band', 'Candy color hair band', 'PBTZBYCJA', '0.15', 'Headband & Hair Band & Hairpin', 'https://simbazu.net/products/love-hair-band-candy-color-headband-highly-elastic-hair-rope-hair-elastic-band-1691276625830219776', 'Colorful everyday hair bands'],
  ['fashion_accessories', 1, 'TR90 Retro Round UV Resistant Glasses', 'Retro round glasses', 'PON2U1DDT', '10.44', 'Man Prescription Glasses', 'https://simbazu.net/products/tr90-retro-round-uv-resistant-glasses-1660919376607981568', 'Retro glasses as outfit detail'],
  ['fashion_accessories', 2, "New Glasses Bauhinia Metal Women's Fashion Young State Blue Light Proof Presbyopic Glasses", "Women's fashion glasses", 'PLCTSHFT0', '4.75', 'Eyewear & Accessories', 'https://simbazu.net/products/new-glasses-bauhinia-metal-womens-fashion-young-state-blue-light-proof-presbyopic-glasses-1377447457893519360', "Women's glasses accessory post"],
  ['fashion_accessories', 3, 'Vintage Small Frame Round Steampunk Sunglasses', 'Round steampunk sunglasses', 'PPWVQBOGJ', '6.52', 'Eyewear & Accessories', 'https://simbazu.net/products/vintage-small-frame-round-steampunk-sunglasses-1523124795167223808', 'Statement sunglasses for style content'],
  ['fashion_accessories', 4, 'Irregular Candy-colored Sunglasses On The Catwalk', 'Candy-colored sunglasses', 'PS3G7ILUV', '3.65', 'Eyewear & Accessories', 'https://simbazu.net/products/irregular-candy-colored-sunglasses-on-the-catwalk-1471390754248200192', 'Colorful sunglasses for engagement poll'],
  ['fashion_accessories', 5, 'European And American Fashion Ocean Beach Glasses', 'Ocean beach glasses', 'PUDCNQL8J', '5.43', 'Man Prescription Glasses', 'https://simbazu.net/products/european-and-american-fashion-ocean-beach-glasses-1535254133773709312', 'Travel/beach accessory angle'],
  ['fashion_accessories', 6, 'New Style Windshield Cycling Glasses Outdoor Sports', 'Outdoor cycling glasses', 'P8QY9IFAW', '4.57', 'Eyewear & Accessories', 'https://simbazu.net/products/new-style-windshield-cycling-glasses-outdoor-sports-1490218043995983872', 'Outdoor/sport glasses content'],
  ['travel', 1, 'All Match College Style Small Backpack Multifunctional Diagonal Bag Women', 'Multifunctional small backpack', 'PSWFFOKUJ', '21.48', 'Fashion Backpacks', 'https://simbazu.net/products/all-match-college-style-small-backpack-multifunctional-diagonal-bag-women-1454751592401211392', 'Compact travel and daily bag'],
  ['travel', 2, 'Fashion Chain Small Round Shoulder Bag', 'Round shoulder bag', 'PHNYW0DAN', '15.59', 'Fashion Backpacks', 'https://simbazu.net/products/fashion-chain-small-round-shoulder-bag-1524297756410654720', 'Small day-out shoulder bag'],
  ['travel', 3, 'Simple Small Square Bag With Solid Color Stitching', 'Small square bag', 'PA3TC8XAF', '9.82', 'Fashion Backpacks', 'https://simbazu.net/products/simple-small-square-bag-with-solid-color-stitching-1456139087177191424', 'Minimal travel accessory'],
  ['travel', 4, "Trendy Women's Fashion Premium Velvet Backpack", 'Velvet fashion backpack', 'PWZR7G1GB', '17.97', 'Fashion Backpacks', 'https://simbazu.net/products/trendy-womens-fashion-premium-velvet-backpack-2407251253561619400', 'Soft fashion backpack for daily use'],
  ['travel', 5, 'Travel Storage Toiletries Sub-package Bag', 'Travel toiletries organizer', 'PG6USVFQY', '27.25', 'Home Office Storage', 'https://simbazu.net/products/travel-storage-toiletries-sub-package-bag-1791643361879789568', 'Travel toiletries organizer'],
  ['travel', 6, 'Portable Portable Mini Mesh Simple Makeup Bag', 'Mini mesh makeup bag', 'P9Y0BOHZW', '1.78', 'Clutches', 'https://simbazu.net/products/portable-portable-mini-mesh-simple-makeup-bag-2409030216291618000', 'Low-cost makeup/travel pouch'],
].map(([theme, priority, productName, displayName, code, price, category, url, angle]) => ({
  theme,
  priority,
  productName,
  displayName,
  code,
  price,
  category,
  url,
  angle,
}));

function esc(value) {
  return String(value)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}

function csv(value) {
  return `"${String(value ?? '').replace(/"/g, '""')}"`;
}

function formatFcfa(usdPrice) {
  const xof = Math.round(Number(usdPrice) * USD_XOF_RATE);
  return `${new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 }).format(xof).replace(/\u202f/g, ' ')} FCFA`;
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

function multiline(text, x, y, size, weight, color, maxChars, lineHeight, extra = '') {
  return `<text x="${x}" y="${y}" font-size="${size}" font-weight="${weight}" fill="${color}" ${extra}>${wrap(text, maxChars)
    .map((line, i) => `<tspan x="${x}" dy="${i === 0 ? 0 : lineHeight}">${esc(line)}</tspan>`)
    .join('')}</text>`;
}

function postCopy(product) {
  const theme = themeLabels[product.theme];
  const cleanName = product.displayName;
  const price = formatFcfa(product.price);
  const en = `Simple find from Simbazu: ${cleanName}. ${product.angle}. Price: ${price}. Product code: ${product.code}. Search the code on simbazu.net and save this post for later.`;
  const fr = `Selection Simbazu : ${cleanName}. Un choix pratique a decouvrir aujourd'hui. Prix : ${price}. Code produit : ${product.code}. Cherchez le code sur simbazu.net et gardez ce post.`;
  const ar = `اختيار من سيمبازو: ${cleanName}. منتج عملي يستحق المشاهدة. السعر: ${price}. كود المنتج: ${product.code}. ابحث عن الكود على simbazu.net واحفظ المنشور.`;
  const reel = `Show product close-up, code ${product.code}, price ${price}, then a 2-second use-case scene: ${product.angle}. End with: Search the code on Simbazu.`;
  const hashtags = [
    '#Simbazu',
    '#OnlineShopping',
    product.theme === 'back_to_school' ? '#BackToSchool' : null,
    product.theme === 'home_organization' ? '#HomeEssentials' : null,
    product.theme === 'beauty' ? '#BeautyFinds' : null,
    product.theme === 'fashion_accessories' ? '#FashionFinds' : null,
    product.theme === 'travel' ? '#TravelFinds' : null,
    '#ShoppingEnLigne',
    '#تسوق_اونلاين',
  ].filter(Boolean).join(' ');
  return { en, fr, ar, reel, hashtags, theme };
}

function baseSvg(product) {
  const copy = postCopy(product);
  const [themeEn, themeFr, themeAr] = copy.theme;
  const price = formatFcfa(product.price);
  return `
    <svg width="${W}" height="${H}" xmlns="http://www.w3.org/2000/svg">
      <defs>
        <linearGradient id="bg" x1="0" x2="1" y1="0" y2="1">
          <stop offset="0%" stop-color="#fffaf3"/>
          <stop offset="52%" stop-color="#f7f4ef"/>
          <stop offset="100%" stop-color="#ffffff"/>
        </linearGradient>
        <radialGradient id="glow" cx="14%" cy="10%" r="42%">
          <stop offset="0%" stop-color="${brand.amber}" stop-opacity="0.24"/>
          <stop offset="100%" stop-color="${brand.amber}" stop-opacity="0"/>
        </radialGradient>
      </defs>
      <rect width="${W}" height="${H}" fill="url(#bg)"/>
      <rect width="${W}" height="${H}" fill="url(#glow)"/>
      <rect x="58" y="52" width="62" height="62" rx="18" fill="${brand.ink}"/>
      <text x="89" y="95" text-anchor="middle" font-size="34" font-weight="1000" fill="${brand.amber}">S</text>
      <text x="138" y="94" font-size="38" font-weight="1000" fill="${brand.ink}">Simbazu</text>
      <rect x="742" y="64" width="280" height="48" rx="24" fill="${brand.ink}"/>
      <text x="882" y="96" text-anchor="middle" font-size="20" font-weight="900" fill="#ffffff">${esc(themeEn)}</text>
      <rect x="58" y="144" width="964" height="625" rx="8" fill="#ffffff" stroke="${brand.border}"/>
      <rect x="58" y="796" width="964" height="1" fill="${brand.border}"/>
      ${multiline(product.displayName, 58, 884, 74, 1000, brand.ink, 22, 76)}
      <text x="58" y="1072" font-size="29" font-weight="800" fill="#334155">${esc(product.angle)}</text>
      <text x="58" y="1124" font-size="24" font-weight="800" fill="#475569">${esc(themeFr)}</text>
      <text x="58" y="1162" font-size="24" font-weight="800" fill="#475569">${esc(themeAr)}</text>
      <rect x="58" y="1200" width="205" height="54" rx="27" fill="${brand.amber}"/>
      <text x="160" y="1236" text-anchor="middle" font-size="24" font-weight="1000" fill="${brand.ink}">${esc(product.code)}</text>
      <text x="58" y="1300" font-size="25" font-weight="1000" fill="${brand.ink}">@simbazu.online</text>
      <text x="355" y="1236" font-size="42" font-weight="1000" fill="${brand.amberDark}">${esc(price)}</text>
      <text x="1022" y="1300" text-anchor="end" font-size="30" font-weight="1000" fill="${brand.amberDark}">Search the code</text>
    </svg>
  `;
}

async function loadImage(product) {
  const imagePath = path.join(inputImageDir, `${product.code}.jpg`);
  return sharp(imagePath)
    .resize(900, 570, { fit: 'inside', background: '#ffffff' })
    .extend({ top: 0, bottom: 0, left: 0, right: 0, background: '#ffffff' })
    .png()
    .toBuffer();
}

async function render(product) {
  const image = await loadImage(product);
  const metadata = await sharp(image).metadata();
  const left = Math.round((W - (metadata.width ?? 900)) / 2);
  const top = 172 + Math.round((570 - (metadata.height ?? 570)) / 2);
  const filename = `${String(product.priority).padStart(2, '0')}-${product.theme}-${product.code}.png`;
  await sharp({ create: { width: W, height: H, channels: 4, background: brand.cream } })
    .composite([
      { input: Buffer.from(baseSvg(product)), left: 0, top: 0 },
      { input: image, left, top },
    ])
    .png()
    .toFile(path.join(outputDir, filename));
  return filename;
}

await fs.mkdir(outputDir, { recursive: true });
const rows = [[
  'theme',
  'product_code',
  'product_name',
  'sale_price_fcfa',
  'asset_file',
  'product_url',
  'instagram_caption_en',
  'instagram_caption_fr',
  'instagram_caption_ar',
  'facebook_caption',
  'tiktok_script',
  'hashtags',
].join(',')];

const manifest = [];
for (const product of products) {
  const asset = await render(product);
  const copy = postCopy(product);
  manifest.push({ ...product, asset });
  rows.push([
    product.theme,
    product.code,
    product.productName,
    formatFcfa(product.price),
    `marketing/product-posts/assets/${asset}`,
    product.url,
    copy.en,
    copy.fr,
    copy.ar,
    `${copy.en}\n\n${copy.fr}\n\n${copy.ar}`,
    copy.reel,
    copy.hashtags,
  ].map(csv).join(','));
}

await fs.writeFile(contentPath, `${rows.join('\n')}\n`);
await fs.writeFile(path.join(path.dirname(contentPath), 'manifest.json'), JSON.stringify(manifest, null, 2));
