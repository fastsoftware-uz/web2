# Tourism Investment Forum 2026 — Toshkent, 25–27 noyabr

Oʻzbek milliy uslubidagi immersiv hikoya: sakkiz bob, ganch qogʻozi va majolika sahnasi, girih naqshi. IV bobda — Oʻzbekiston xaritasi: 14 hudud, 13 tasidan 86 ta investitsiya loyihasi.

> **Konsept/demo loyiha.** Bu sayt agentlik tomonidan yuritilmaydi.
> Rasmiy maʼlumot: <https://invest.gov.uz>

**Stack:** Vue 3 · TypeScript · Vite · GSAP ScrollTrigger · Lenis · three.js

## Ishga tushirish

```bash
npm install
npm run dev      # http://localhost:5172
npm run build    # -> dist/
npm run preview
```

`dist/` — mustaqil statik bundle. `base` nisbiy, shuning uchun u domen
ildizida ham, sub-papkada ham ishlaydi. `.github/workflows/deploy.yml` har
push'da uni GitHub Pages'ga chiqaradi (Settings → Pages → Source: GitHub Actions).

## Maʼlumot

Sahifadagi **hech bir raqam kodda yozilmagan**. Hammasi ishga tushganda
`public/data/content.json` dan yuklanadi, har birining yonida manba va sana
koʻrsatiladi, footerda esa toʻliq manbalar roʻyxati bor.

Raqamni oʻzgartirish uchun `public/data/content.json` ni tahrirlang va
`sources` ga mos yozuv qoʻshing. Manbasiz raqam qoʻshilmaydi.

Til: `uz` (default), `ru`, `en`. Tanlov URL'da (`?lang=en`) va
`localStorage`da saqlanadi.

## Eslatma

Bu repo uchta konseptdan biri. Umumiy faktlar bazasi, brend quvuri va logo
manbalari alohida monorepo'da turadi va bu yerga koʻchirib chiqarilgan —
`content.json` oʻzgarsa, uchala repo ham yangilanishi kerak.

Litsenziyalar va atributlar: `CREDITS.md`.

## Roʻyxatdan oʻtish va adminka

`/register/` — alohida sahifa, alohida bundle (GSAP ham, three.js ham
yoʻq; manbada `register/index.html`). U `POST /api/register` ga yuboradi; shu endpoint va uni oʻqish uchun
adminka `server/` ichida — PHP + nginx, Alpine Linux uchun. Oʻrnatish
yoʻriqnomasi: `server/README.md`, adminkadan foydalanish qoʻllanmasi:
`server/QOLLANMA.md`.

Adminka faqat arizalarni emas, saytning oʻzini ham boshqaradi: sana, shahar,
dastur, aloqa, matnlar va 83 ta loyiha — hammasi uch tilda. Oʻzgartirilgan
narsa darhol saytga chiqadi, chunki sahifa maʼlumotni `data/*.json` dan
ishga tushganda oʻqiydi. Build ham, deploy ham shart emas.

Sayt GitHub Pages'da, API esa tashkilotchining serverida turganda endpoint
manzilini repo sozlamalaridan berish kerak: **Settings → Secrets and
variables → Actions → Variables → `REGISTER_ENDPOINT`**. U berilmaguncha
forma arizani elektron pochta orqali yuborishni taklif qiladi.

Oʻrnatish bitta faylga sigʻadi: `server/.env.example` dan `server/.env`
yasaladi va toʻldiriladi, xolos. Ishlamay qolsa — `php server/bin/doctor.php`
nimasi notoʻgʻri ekanini aytadi.

Har push ikki joyga chiqadi: `deploy.yml` — GitHub Pages (statik nusxa),
`deploy-server.yml` — tashkilotchining serveri (sayt + API + adminka).
Ikkinchisi server manzili berilmaguncha oʻtkazib yuboriladi; sozlamalari
`server/README.md` da.

`server/.env` va `server/data/` git'ga tushmaydi: biri parol
hash'i, ikkinchisi arizalarning oʻzi.
