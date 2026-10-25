# Adminka qoʻllanmasi

**I Xalqaro turizm investitsiya forumi — Toshkent, 25–27-noyabr 2026**

Bu hujjat adminkadan foydalanadigan odam uchun yozilgan. Dasturchi uchun emas —
oʻrnatish va server sozlamalari alohida faylda: [`README.md`](README.md).

---

## Qisqacha: adminka nima qila oladi

| Boʻlim | Nima qilasiz |
|---|---|
| **Roʻyxatdan oʻtganlar** | Arizalarni oʻqish, qidirish, holatini oʻzgartirish, Excel uchun CSV yuklab olish |
| **Saytni tahrirlash → Boʻlimlar** | Sayt yozadigan har bir matn: sana, shahar, dastur, sessiyalar, aloqa, barchasi uch tilda |
| **Saytni tahrirlash → Loyihalar** | 86 ta investitsiya loyihasi: nomi, izohi, raqamlari, saytdan yashirish va qaytarish |
| **Saytni tahrirlash → Nashr** | Qilingan oʻzgarishlar roʻyxati; har birini yoki hammasini asl holiga qaytarish |
| **E-bilet** | Har bir mehmonning beyji: ochish, chop etish, havolasini yuborish |

**Muhim:** saytda oʻzgartirilgan narsa **darhol** chiqadi. Qayta qurish
(`build`), qayta yuklash (`deploy`) shart emas — saqlash tugmasini bosganingiz
bilan sayt yangilanadi. Sahifani yangilab koʻring.

---

## 1. Kirish

Manzil: **`https://<sayt-manzili>/admin/`**

Parolni dasturchi oʻrnatadi (`README.md`, 3-bosqich). Parol faqat sizda boʻlsin:
adminkada forum mehmonlarining ismi, telefoni va elektron pochtasi turadi.

Parolni unutsangiz tiklab boʻlmaydi — yangisi qoʻyiladi. Serverda:

```sh
cd /var/www/forum
npm run server:password -- "yangi parol"
```

yoki Node oʻrnatilmagan boʻlsa, qoʻlda:

```sh
php -r 'echo password_hash("yangi parol", PASSWORD_DEFAULT), "\n";'
```

chiqqan satrni `server/src/config.php` dagi `admin_password_hash` ga qoʻying.

> Chiqish uchun oʻng yuqoridagi **Chiqish** tugmasi. Umumiy kompyuterda ishlab
> boʻlgach albatta chiqing.

---

## 2. Roʻyxatdan oʻtganlar

Kirgach birinchi koʻradigan sahifangiz. Yuqorida beshta raqam:

- **Jami** — kelgan arizalar soni
- **VIP** / **Investor** / **Mehmon** / **Spiker** — ishtirok turi boʻyicha
- **Yangi** — hali koʻrib chiqilmagan
- **Tasdiqlangan** — siz tasdiqlagan

Raqamning oʻziga bosilsa roʻyxat shu boʻyicha filtrlanadi.

### Qidirish

Qidiruv maydoni ism, tashkilot, lavozim, mamlakat, email, telefon va izoh
boʻyicha ishlaydi. Yonidagi ikkita roʻyxat turi va holati boʻyicha filtrlaydi.
**Tozalash** hammasini qaytaradi.

### Holatni oʻzgartirish

Har bir qatorning oʻng chekkasida roʻyxat bor: **Yangi → Tasdiqlangan → Rad
etilgan**. Tanlaganingiz darhol saqlanadi.

Tasdiqlangan qator yashil, rad etilgan qizil fonda koʻrinadi — roʻyxatni
koʻzdan kechirganda kim bilan ishlanganini ajratib turadi.

### CSV yuklab olish

**CSV yuklab olish** tugmasi hozir koʻrinib turgan roʻyxatni yuklaydi — ya'ni
filtr qoʻyilgan boʻlsa, faqat shu qism. Fayl Excel uchun tayyor: oʻzbekcha va
kirill harflari toʻgʻri ochiladi, ustunlar nuqta-vergul bilan ajratilgan.

> **Shaxsiy maʼlumot.** Bu fayl ichida mehmonlarning telefon raqami va elektron
> pochtasi bor. Uni umumiy papkaga, Telegramga yoki pochtaga qoʻshmang.

---

## 2a. E-bilet

### Kimga avtomatik beriladi

| Ishtirok turi | E-bilet |
|---|---|
| **Mehmon** | Roʻyxatdan oʻtgan zahoti avtomatik |
| **Investor** | Roʻyxatdan oʻtgan zahoti avtomatik |
| **VIP mehmon** | Avtomatik berilmaydi — siz berasiz |
| **Spiker** | Avtomatik berilmaydi — siz berasiz |

Mehmon va investor formani yuborgan zahoti ekranda **E-biletingiz tayyor**
degan yozuv va havola chiqadi. Oʻsha havola — uning beyji; siz hech narsa
yubormaysiz.

VIP va spikerga esa ekranda «tashkiliy qoʻmita siz bilan bogʻlanadi» deb
yoziladi, beyj esa ochilmaydi. Siz u bilan gaplashib, kelishgandan soʻng
adminkadagi **Biletni berish** tugmasini bosasiz — shundan keyin oʻsha
havola ochiladi va beyjni yuborsangiz boʻladi. Kerak boʻlsa **Bekor qilish**
bilan qaytarib olasiz.

Adminkada har bir qatorning oʻng chekkasida **E-bilet** tugmasi bor. U
ishtirokchi koʻradigan sahifani ochadi: havolani yoʻqotgan boʻlsa, shu
tugmadan nusxalab yuborasiz.

### Beyj qanday koʻrinadi

Bitta A4 varaq, toʻrtga boʻlingan:

- **Yuqori chapda** — beyjning oʻzbekcha yuzi: forum belgisi, ism, lavozim,
  tashkilot, QR kod, sana va ishtirok turi: **MEHMON**, **INVESTOR**, **VIP**
  yoki **SPIKER**;
- **Yuqori oʻngda** — xuddi shu, inglizcha (**VISITOR**, **INVESTOR**, **VIP**,
  **SPEAKER**);
- **Pastki chapda** — forum sanasi, shahri va tashkiliy qoʻmita manzili;
- **Pastki oʻngda** — yoʻriqnoma, buklash chizmasi va bilet raqami.

Mehmon varaqni chop etib, punktir chiziqlar boʻyicha buklaydi — ikki yuzida
ham ismi boʻladi.

### Bilet raqami

`TITF26-000417-K7F3QA` koʻrinishida. Oʻrtadagi raqam — ariza raqami, oxirgi
olti belgi — imzo. Imzosiz yoki notoʻgʻri imzo bilan havola ochilmaydi, yaʼni
birovning biletini raqamni oʻzgartirib topib boʻlmaydi.

### Kirishda tekshirish

Beyjdagi QR kodni oddiy telefon kamerasi bilan skanerlaysiz — maxsus qurilma
kerak emas. Ochilgan sahifada:

| Rang | Maʼnosi |
|---|---|
| **Yashil** — «Bilet haqiqiy» | Kirishga ruxsat. Ostida «tasdiqlangan» yoki «tasdiq kutilmoqda» deb yoziladi |
| **Qizil** — «Bilet bekor qilingan» | Kirishga ruxsat berilmaydi |

Sahifada mehmonning ismi, lavozimi, tashkiloti va davlati koʻrinadi.
**Elektron pochtasi va telefon raqami koʻrinmaydi** — beyjda ham yozilmagan.

Biror mehmonning bileti amal qilmasligi kerak boʻlsa, uning holatini
**Rad etilgan** qilib qoʻying: beyj ham, QR kod ham shu zahoti qizil holatga
oʻtadi. Qaytarish uchun holatni **Tasdiqlangan** qilsangiz kifoya.

---

## 3. Saytni tahrirlash

Yuqoridagi **Saytni tahrirlash** tugmasi orqali kiriladi.

Ikkita fayl bor:

- **Forum sayti** — forumning oʻzi haqidagi hamma narsa. Koʻp ish shu yerda.
- **Umumiy maʼlumotlar** — Oʻzbekiston boʻyicha umumiy raqamlar, uchala sayt
  birgalikda ishlatadigan.

### Boʻlimlar

Birinchi sahifada kartochkalar: **Tadbir**, **Kirish**, **Maqsadlar**,
**Yoʻnalishlar**, **Ishtirokchilar**, **Sessiyalar**, **Dastur**, **Aloqa** va
boshqalar. Har birining ostida nechta maydon borligi yozilgan. Agar siz
oʻzgartirgan boʻlsangiz — “3 ta oʻzgartirilgan” deb ham koʻrsatadi.

### Uch til

Deyarli har bir matn uchta qutichada: **Oʻzbekcha**, **Ruscha**, **Inglizcha**.
Uchalasini ham toʻldiring. Bittasini boʻsh qoldirsangiz sayt shu tilda boʻsh
joy koʻrsatadi — tarjimani oʻzi qilib bermaydi.

### Saqlash

Sahifaning pastida **Saqlash va saytni yangilash** tugmasi doim koʻrinib turadi.
Bosilgandan keyin yuqorida yashil yozuv chiqadi:

> Saqlandi: 2 ta oʻzgarish, 0 tasi asl holiga qaytdi. Sayt yangilandi.

Shu zahoti saytni ochib tekshiring.

### Oʻzgartirgan joyingiz belgilanadi

Siz oʻzgartirgan maydonning nomi **tilla rangda** va yonida “oʻzgartirilgan”
degan belgi bilan koʻrinadi. Shu belgi — nima oʻzingiz yozganingizni, nima
hujjatdan kelganini ajratib turish uchun.

Maydonni asl holiga qaytarish uchun ikki yoʻl bor: eski matnni qaytarib yozish,
yoki **Nashr** boʻlimidan **Asl holiga** tugmasini bosish.

---

## 4. Loyihalar

**Saytni tahrirlash → Loyihalar** — 86 ta investitsiya loyihasi.

### Qidirish va koʻrish

Qidiruv loyiha nomi va kaliti boʻyicha ishlaydi. Jadvalda hudud, yoʻnalish va
investitsiya koʻrsatilgan.

### Loyihani tahrirlash

Nomiga bossangiz tahrirlash sahifasi ochiladi. U yerda:

| Maydon | Nima |
|---|---|
| **Sarlavha** | Loyiha nomi, uch tilda |
| **Izoh** | Nom ostidagi bir qatorlik tavsif, uch tilda |
| **Tavsif** | Uzun matn, uch tilda |
| **Investitsiya (raqam)** | Saralash va jamlash uchun ishlatiladigan raqam. **Faqat raqam** — valyuta belgisisiz |
| **Valyuta** | `USD` yoki `UZS` |
| **Investitsiya (yozuv)** | Sahifada koʻrinadigan matn, masalan `USD 35 mln` |
| **Yoʻnalish** | `infrastructure`, `heritage`, `eco`, `wellness`, `leisure`, `transport` dan biri |
| **Koʻrsatkichlar** | Kartochkadagi “Holati”, “Loyiha qiymati” kabi qatorlar |

> **Diqqat.** “Investitsiya (raqam)” va “Investitsiya (yozuv)” bir xil narsani
> aytishi kerak. Birini oʻzgartirib, ikkinchisini unutsangiz, roʻyxatda bir
> raqam, kartochkada boshqa raqam chiqadi.

### Loyihani yashirish

Jadvalning oʻng chekkasidagi **Yashirish** tugmasi loyihani saytdan olib qoʻyadi.
Oʻchirmaydi — adminkada qoladi va **Qaytarish** bilan qaytariladi.

Yashirilgan loyiha jadvalda xira koʻrinadi va “saytda yoʻq” belgisi bilan
turadi. Hududlar va yoʻnalishlar boʻyicha raqamlar ham avtomatik qayta
sanaladi, ya'ni sayt “86 ta loyiha” deb turib 85 tasini koʻrsatib qolmaydi.

---

## 5. Nashr va bekor qilish

**Saytni tahrirlash → Nashr va tarix**.

Bu yerda uchta raqam (nechta maydon oʻzgartirilgan, nechta loyiha yashirilgan,
necha marta saqlangan), saytning maʼlumot fayli qayerdaligi, va qilingan
oʻzgarishlarning toʻliq roʻyxati.

- **Asl holiga** — bitta maydonni qaytaradi.
- **Hammasini asl holiga qaytarish** — barcha oʻzgarishlarni bekor qiladi va
  sayt dasturchi yuklagan holatga qaytadi. Tasdiqlash soʻraladi.
- **Saytni qayta nashr etish** — hech narsani oʻzgartirmaydi, faqat saytning
  faylini qaytadan yozadi. Fayl bilan nimadir boʻlgan deb shubhalansangiz
  bosing.

---

## 6. Sayt yangilanganda nima boʻladi

Dasturchi saytning yangi versiyasini yuklasa (masalan, tashkilotchidan yangi
loyihalar kelganda), sizning oʻzgarishlaringiz **yoʻqolmaydi**.

Adminka yangi versiyani oʻzi sezadi va keyingi marta ochganingizda yuqorida
yoziladi:

> Saytning yangi versiyasi yuklangan ekan — oʻzgarishlaringiz uning ustiga
> qayta qoʻllandi.

Agar siz oʻzgartirgan maydon yangi versiyada umuman boʻlmasa (masalan, oʻsha
loyiha olib tashlangan boʻlsa), qizil yozuv chiqadi va qaysi maydon
qoʻllanmaganini aytadi. Unday maydonni **Nashr** boʻlimidan oʻchirib
tashlasangiz boʻladi.

---

## 7. Koʻp uchraydigan ishlar

### Forum sanasi oʻzgardi

**Saytni tahrirlash → Tadbir** → `Boshlanish sanasi` va `Tugash sanasi`.
Format: `2026-11-25` (yil-oy-kun), shu koʻrinishda boʻlishi shart — sayt
sahifada koʻrinadigan “25–27-noyabr 2026” yozuvini shu ikki sanadan oʻzi
yasaydi, siz alohida yozmaysiz.

### Aloqa telefoni yoki manzili oʻzgardi

**Saytni tahrirlash → Aloqa**. `Telefon` ni oʻzgartirsangiz, `Telefon havolasi`
ni ham oʻzgartiring — u `tel:+998712050600` koʻrinishida, boʻsh joysiz va
qavssiz boʻladi. Bosganda telefon teradigan havola shu.

### Dasturga sessiya qoʻshish

**Saytni tahrirlash → Dastur**. `Bandlar` ichida oʻnta band bor, har biri
alohida guruh: `start` (boshlanish vaqti, `10:00` koʻrinishida), `end` (tugash
vaqti), `kind` (turi) va `Sarlavha` (uch tilda).

Mavjud bandning vaqtini yoki nomini oʻzgartirish mumkin. **Yangi band qoʻshish
adminkadan hozircha mumkin emas** — dasturchiga ayting.

### Yangi loyiha qoʻshish

Adminkadan **hozircha mumkin emas**. Loyihalar tashkilotchining hujjatlaridan
avtomatik oʻqiladi — yangi loyihaning varaqasini dasturchiga yuboring, u
qoʻshadi va siz bu yerdan tahrirlaysiz.

### Loyihani vaqtincha olib turish

**Loyihalar** → qidiring → **Yashirish**. Keyin **Qaytarish**.

---

## 8. Nima qilmaslik kerak

- **Kalit (`id`) maydonini oʻzgartirmang.** U sayt ichida havolalar uchun
  ishlatiladi; oʻzgartirsangiz oʻsha loyihaga boradigan havolalar ishlamay
  qoladi.
- **Raqamlarni oʻylab topmang.** Loyiha raqamlari tashkilotchi bergan
  hujjatlardan olingan. Hujjatda yoʻq raqamni yozmang.
- **Parolni hech kimga bermang va brauzerda “eslab qol” demang**, ayniqsa
  umumiy kompyuterda.
- **CSV faylni tarqatmang** — ichida shaxsiy maʼlumot bor.

---

## 9. Nimadir ishlamay qolsa

| Alomat | Nima qilish |
|---|---|
| “Parol notoʻgʻri” — lekin parol toʻgʻri | Katta-kichik harfga, klaviatura tiliga qarang. Baribir kirmasa — parolni qaytadan qoʻying (1-boʻlim) |
| “Saytning maʼlumot fayli topilmadi” | `server/src/config.php` dagi `site_dir` notoʻgʻri. Dasturchiga ayting |
| Saqladim, lekin saytda oʻzgarmadi | Brauzerni `Ctrl+F5` bilan yangilang. Baribir boʻlmasa — **Nashr → Saytni qayta nashr etish** |
| “Sahifa eskirgan” | Adminka uzoq ochiq turgan. Sahifani yangilang va qaytadan saqlang |
| «Bilet topilmadi» | Havola toʻliq nusxalanmagan. Adminkadagi **E-bilet** tugmasidan qayta oling |
| «Bilet hali berilmagan» | VIP yoki spiker: adminkadan **Biletni berish** tugmasini bosing |
| Ariza kelmayapti | `https://<sayt>/api/health` ni oching. `{"ok":true…}` chiqmasa, dasturchiga ayting |

Boshqa hamma holatda — dasturchiga murojaat qiling va **nima qilganingizni**
hamda **ekranda nima yozilganini** aytib bering.
