import fs from 'node:fs/promises';
import path from 'node:path';
import sharp from 'sharp';

const W = 1080;
const H = 1350;
const outputDir = path.resolve('marketing/women-fashion-collages/assets');
const imageDir = path.resolve('marketing/women-fashion-collages/product-images');
const contentPath = path.resolve('marketing/women-fashion-collages/women-fashion-collage-content.csv');

const brand = {
  ink: '#111111',
  muted: '#61594f',
  paper: '#e8ded2',
  ivory: '#fbf7ef',
  amber: '#f59e0b',
  red: '#e90028',
  aqua: '#58d6d0',
  blue: '#142a52',
  gold: '#d9a21b',
};

const products = {
  denimDress: {
    name: 'Denim slim dress',
    code: 'PDHAL478L',
    category: 'Dress',
    priceFcfa: 16050,
    url: 'https://simbazu.net/products/denim-dress-womens-fashionable-slimming-sleeveless-waist-tight-temperament-2509280846501624300',
    image: 'https://oss-cf.cjdropshipping.com/product/2025/09/28/08/e5d89457-c1d5-4199-ac9d-50e99fc4a90c_fine.jpeg',
  },
  fairyDress: {
    name: 'Haze blue fairy dress',
    code: 'PJU85NPAJ',
    category: 'Dress',
    priceFcfa: 20382,
    url: 'https://simbazu.net/products/new-haze-blue-little-fairy-dress-with-net-gauze-print-and-big-swing-1392760353749864448',
    image: 'https://cf.cjdropshipping.com/1620894813767.png',
  },
  summerDress: {
    name: 'Summer split midi dress',
    code: 'P2HMLYW8V',
    category: 'Dress',
    priceFcfa: 13338,
    url: 'https://simbazu.net/products/womens-summer-split-dress-with-buttons-casual-sleeveless-midi-dress-clothing-2506250204281622000',
    image: 'https://oss-cf.cjdropshipping.com/product/2025/06/25/02/82b13918-fe6e-4baf-91a1-fa413cda5358.jpg',
  },
  partyDress: {
    name: 'Slim party dress',
    code: 'PZ7TY46C7',
    category: 'Dress',
    priceFcfa: 11292,
    url: 'https://simbazu.net/products/summer-slim-skinny-sleeveless-dress-for-women-fashion-party-club-dresses-1640527350196613120',
    image: 'https://cf.cjdropshipping.com/ab4ba2a6-a546-4477-90b7-df3b152d5047.jpg',
  },
  halterDress: {
    name: 'Halter split dress',
    code: 'P1YDCXZEM',
    category: 'Dress',
    priceFcfa: 13962,
    url: 'https://simbazu.net/products/ins-halter-split-long-dress-summer-slim-fit-backless-dresses-solid-high-end-womens-clothing-1760930660870533120',
    image: 'https://oss-cf.cjdropshipping.com/product/2024/04/29/08/dce58819-1200-45e4-a042-00c8a3854685.jpg',
  },
  oneShoulderDress: {
    name: 'One-shoulder mini dress',
    code: 'PSJ2HVJLS',
    category: 'Dress',
    priceFcfa: 10176,
    url: 'https://simbazu.net/products/one-shoulder-sleeveless-mini-dress-sexy-slim-backless-skirt-party-wedding-dresses-womens-clothing-1739186160615829504',
    image: 'https://cf.cjdropshipping.com/17034624/1739186165237551104.jpg',
  },
  skirt: {
    name: 'Slim waist short skirt',
    code: 'PHVFTVGCB',
    category: 'Skirt',
    priceFcfa: 6126,
    url: 'https://simbazu.net/products/temperament-slim-waist-pack-hip-short-skirt-1542432218067513344',
    image: 'https://cf.cjdropshipping.com/9661f77f-f737-4f14-913a-ad0e976f339d.jpg',
  },
  jeans: {
    name: 'Street-style jeans',
    code: 'PKAVJO88N',
    category: 'Jeans',
    priceFcfa: 23436,
    url: 'https://simbazu.net/products/new-street-style-versatile-jeans-2602110233071603800',
    image: 'https://cf.cjdropshipping.com/quick/product/d94ca010-f88b-423f-b87e-22a050d31939.jpg',
  },
  flats: {
    name: 'Square-toe flats',
    code: 'PFKZC658H',
    category: 'Shoes',
    priceFcfa: 8724,
    url: 'https://simbazu.net/products/womens-casual-european-and-american-style-square-toe-flats-2602070233271619800',
    image: 'https://cf.cjdropshipping.com/quick/product/a9f52c70-ef88-4bf5-b1fb-d10d46084d23.jpg',
  },
  sandals: {
    name: 'Open-toe sandals',
    code: 'P2VPMHI4X',
    category: 'Shoes',
    priceFcfa: 6594,
    url: 'https://simbazu.net/products/open-toe-elegant-fashion-korean-princess-sandals-2604071058451633600',
    image: 'https://cf.cjdropshipping.com/quick/product/2cefcd43-9129-4217-adb6-14fe3c48f7c5.jpg',
  },
  bag: {
    name: 'Vintage dumpling bag',
    code: 'PN1PIUPY8',
    category: 'Bag',
    priceFcfa: 6876,
    url: 'https://simbazu.net/products/trendy-vintage-dumpling-bag-niche-textured-crossbody-underarm-bag-2604101011321625400',
    image: 'https://cf.cjdropshipping.com/quick/product/141ec884-4ea1-44f4-84a7-149c4d2aa4c1.jpg',
  },
  wallet: {
    name: 'Simple long wallet',
    code: 'PZ8LDGQHE',
    category: 'Wallet',
    priceFcfa: 2730,
    url: 'https://simbazu.net/products/fashionable-simple-long-wallet-for-women-2602251240441636600',
    image: 'https://cf.cjdropshipping.com/quick/product/7ab80c1c-dac0-4616-9b50-d1c7e4a96ec4.jpg',
  },
};

const posts = [
  {
    file: '01-summer-color-combos.png',
    title: 'SUMMER COLOR',
    subtitle: 'Combos',
    palette: [brand.red, brand.aqua],
    caption: 'Summer color combos from Simbazu: dresses, skirts, sandals, and bags with searchable product codes.',
    fr: 'Combinaisons couleur ete chez Simbazu : robes, jupes, sandales et sacs avec codes produits.',
    ar: 'تنسيقات ألوان صيفية من سيمبازو: فساتين وتنانير وصنادل وحقائب مع أكواد المنتجات.',
    items: [
      { product: products.fairyDress, x: 78, y: 282, w: 320, h: 340 },
      { product: products.partyDress, x: 632, y: 300, w: 330, h: 322 },
      { product: products.skirt, x: 80, y: 686, w: 335, h: 360 },
      { product: products.summerDress, x: 430, y: 616, w: 305, h: 410 },
      { product: products.bag, x: 735, y: 680, w: 270, h: 220 },
      { product: products.sandals, x: 734, y: 940, w: 260, h: 160 },
    ],
  },
  {
    file: '02-monday-outfit-builder.png',
    title: 'Monday',
    subtitle: 'Outfit Builder',
    palette: [brand.blue, brand.gold],
    caption: 'A Monday outfit idea: denim, a clean bag, simple shoes, and one easy dress option. Search each code on Simbazu.',
    fr: 'Idee tenue du lundi : denim, sac pratique, chaussures simples et une option robe. Cherchez chaque code sur Simbazu.',
    ar: 'فكرة إطلالة يوم الاثنين: جينز وحقيبة وحذاء بسيط وخيار فستان. ابحث عن كل كود في سيمبازو.',
    items: [
      { product: products.denimDress, x: 88, y: 250, w: 340, h: 390, label: { x: 130, y: 650 } },
      { product: products.jeans, x: 568, y: 190, w: 380, h: 650, label: { x: 630, y: 835 } },
      { product: products.wallet, x: 82, y: 720, w: 220, h: 170, label: { x: 66, y: 892 } },
      { product: products.bag, x: 120, y: 930, w: 300, h: 200, label: { x: 138, y: 1095 } },
      { product: products.flats, x: 514, y: 870, w: 330, h: 205, label: { x: 552, y: 1088 } },
    ],
  },
  {
    file: '03-dress-edit.png',
    title: 'DRESS',
    subtitle: 'Edit',
    palette: [brand.ink, brand.amber],
    caption: 'Dress edit for day, evening, and summer plans. Save the post and search the product code on Simbazu.',
    fr: 'Selection robes pour la journee, le soir et les sorties ete. Gardez le post et cherchez le code sur Simbazu.',
    ar: 'اختيارات فساتين لليوم والمساء والصيف. احفظ المنشور وابحث عن الكود في سيمبازو.',
    items: [
      { product: products.denimDress, x: 68, y: 268, w: 285, h: 362 },
      { product: products.fairyDress, x: 400, y: 230, w: 300, h: 390 },
      { product: products.halterDress, x: 730, y: 275, w: 285, h: 370 },
      { product: products.oneShoulderDress, x: 88, y: 735, w: 300, h: 342 },
      { product: products.summerDress, x: 420, y: 700, w: 280, h: 380 },
      { product: products.partyDress, x: 728, y: 738, w: 285, h: 340 },
    ],
  },
];

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

function formatFcfa(value) {
  return `${new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 }).format(value).replace(/\u202f/g, ' ')} FCFA`;
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

function multiline(text, x, y, size, color, maxChars, lineHeight, weight = 800) {
  return `<text x="${x}" y="${y}" font-size="${size}" font-family="Inter, Arial, sans-serif" font-weight="${weight}" fill="${color}">${wrap(text, maxChars)
    .map((line, i) => `<tspan x="${x}" dy="${i === 0 ? 0 : lineHeight}">${esc(line)}</tspan>`)
    .join('')}</text>`;
}

function baseSvg(post) {
  return `
    <svg width="${W}" height="${H}" xmlns="http://www.w3.org/2000/svg">
      <rect width="${W}" height="${H}" fill="${brand.paper}"/>
      <text x="70" y="116" font-size="${post.title.length > 8 ? 102 : 132}" font-family="Georgia, 'Times New Roman', serif" font-weight="800" fill="${post.palette[0]}" letter-spacing="0">${esc(post.title)}</text>
      <text x="445" y="205" font-size="112" font-family="Helvetica Neue, Arial, sans-serif" font-weight="200" fill="${post.palette[1]}">${esc(post.subtitle)}</text>
      <text x="70" y="1270" font-size="64" font-family="Inter, Arial, sans-serif" font-weight="950" fill="${brand.ink}">SIMBAZU</text>
      <text x="70" y="1308" font-size="24" font-family="Inter, Arial, sans-serif" font-weight="800" fill="${brand.muted}">@simbazu.online  |  simbazu.net</text>
      <circle cx="520" cy="1210" r="48" fill="${post.palette[0]}"/>
      <circle cx="608" cy="1210" r="48" fill="${post.palette[1]}"/>
      <rect x="780" y="1224" width="230" height="46" rx="23" fill="${brand.ink}"/>
      <text x="895" y="1254" text-anchor="middle" font-size="20" font-family="Inter, Arial, sans-serif" font-weight="900" fill="#fff">Search the code</text>
    </svg>`;
}

function labelSvg(item, accent) {
  const { product } = item;
  return `
    <svg width="255" height="68" xmlns="http://www.w3.org/2000/svg">
      <rect x="0" y="0" width="255" height="68" rx="8" fill="${brand.ivory}" opacity="0.92"/>
      <text x="14" y="26" font-size="18" font-family="Inter, Arial, sans-serif" font-weight="950" fill="${accent}">ID: ${esc(product.code)}</text>
      <text x="14" y="52" font-size="17" font-family="Inter, Arial, sans-serif" font-weight="850" fill="${brand.ink}">${esc(formatFcfa(product.priceFcfa))}</text>
    </svg>`;
}

async function productImage(product, width, height) {
  const localPath = path.join(imageDir, `${product.code}.jpg`);
  try {
    await fs.access(localPath);
  } catch {
    const response = await fetch(product.image);
    if (!response.ok) throw new Error(`Could not fetch ${product.code}: ${response.status}`);
    await fs.writeFile(localPath, Buffer.from(await response.arrayBuffer()));
  }

  return sharp(localPath)
    .rotate()
    .trim({ background: '#ffffff', threshold: 12 })
    .resize(width, height, { fit: 'inside', background: { r: 232, g: 222, b: 210, alpha: 0 } })
    .png()
    .toBuffer();
}

async function renderPost(post) {
  const layers = [{ input: Buffer.from(baseSvg(post)), left: 0, top: 0 }];
  const labelLayers = [];

  for (const [index, item] of post.items.entries()) {
    const image = await productImage(item.product, item.w, item.h);
    const metadata = await sharp(image).metadata();
    const left = item.x + Math.round((item.w - (metadata.width ?? item.w)) / 2);
    const top = item.y + Math.round((item.h - (metadata.height ?? item.h)) / 2);
    layers.push({ input: image, left, top });

    const centeredLabelX = item.x + Math.round((item.w - 255) / 2);
    const belowLabelY = item.y + item.h + 8;
    const labelX = item.label?.x ?? Math.max(34, Math.min(790, centeredLabelX));
    const labelY = item.label?.y ?? (belowLabelY > 1110 ? item.y - 74 : Math.max(220, belowLabelY));
    labelLayers.push({ input: Buffer.from(labelSvg(item, post.palette[0])), left: labelX, top: labelY });
  }

  await sharp({ create: { width: W, height: H, channels: 4, background: brand.paper } })
    .composite([...layers, ...labelLayers])
    .png()
    .toFile(path.join(outputDir, post.file));
}

await fs.mkdir(outputDir, { recursive: true });
await fs.mkdir(imageDir, { recursive: true });

for (const post of posts) {
  await renderPost(post);
}

const rows = [[
  'asset_file',
  'post_theme',
  'product_codes',
  'products',
  'instagram_caption_en',
  'instagram_caption_fr',
  'instagram_caption_ar',
  'facebook_caption',
  'tiktok_script',
  'hashtags',
].join(',')];

for (const post of posts) {
  const codes = post.items.map(({ product }) => product.code).join(' ');
  const names = post.items.map(({ product }) => `${product.name} (${product.code}, ${formatFcfa(product.priceFcfa)})`).join('; ');
  rows.push([
    `marketing/women-fashion-collages/assets/${post.file}`,
    `${post.title} ${post.subtitle}`,
    codes,
    names,
    `${post.caption} Codes: ${codes}.`,
    `${post.fr} Codes : ${codes}.`,
    `${post.ar} الأكواد: ${codes}.`,
    `${post.caption}\n\n${post.fr}\n\n${post.ar}\n\nCodes: ${codes}.`,
    `Show the full collage, zoom into each product code, then end with: search the code on simbazu.net. Codes: ${codes}.`,
    '#Simbazu #SimbazuStyle #WomenFashion #DressEdit #OutfitIdeas #ModeFemme #تسوق_اونلاين',
  ].map(csv).join(','));
}

await fs.writeFile(contentPath, `${rows.join('\n')}\n`);
await fs.writeFile(
  path.resolve('marketing/women-fashion-collages/manifest.json'),
  JSON.stringify(posts.map(({ file, title, subtitle, items }) => ({
    file,
    title,
    subtitle,
    products: items.map(({ product }) => ({
      code: product.code,
      name: product.name,
      price_fcfa: product.priceFcfa,
      url: product.url,
    })),
  })), null, 2),
);
