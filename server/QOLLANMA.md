# Adminka qoʻllanmasi

**I Xalqaro turizm investitsiya forumi — Toshkent, 25–27-noyabr 2026**

Bu hujjat adminkadan foydalanadigan odam uchun yozilgan. Dasturchi uchun emas —
serverga oʻrnatish alohida faylda:
[`SERVERGA-ORNATISH.md`](SERVERGA-ORNATISH.md) (qadam-baqadam, serverdagi odam
uchun) va [`README.md`](README.md) (texnik tafsilotlar).

---

## Qisqacha: adminka nima qila oladi

| Boʻlim | Nima qilasiz |
|---|---|
| **Roʻyxatdan oʻtganlar** | Arizalarni oʻqish, qidirish, holatini oʻzgartirish, Excel uchun CSV yuklab olish |
| **Saytni tahrirlash → Boʻlimlar** | Sayt yozadigan har bir matn: sana, shahar, dastur, sessiyalar, aloqa, barchasi uch tilda |
| **Saytni tahrirlash → Loyihalar** | 86 ta investitsiya loyihasi: nomi, izohi, raqamlari, saytdan yashirish va qaytarish |
| **Saytni tahrirlash → Nashr** | Qilingan oʻzgarishlar roʻyxati; har birini yoki hammasini asl holiga qaytarish |
| **Loyiha qoʻshish** | Excel yoki CSV roʻyxatidan yangi loyihalar, rasmlari bilan — dublikat tekshiruvidan oʻtkazib |
| **Jurnal** | Kim, qachon, nima qilgani. Oʻchirilmaydi va tahrirlanmaydi |
| **Foydalanuvchilar** | Hamkasblarga alohida login ochish va huquq berish (faqat egasiga) |
| **E-bilet** | Har bir mehmonning beyji: ochish, chop etish, havolasini yuborish |

**Muhim:** saytda oʻzgartirilgan narsa **darhol** chiqadi. Qayta qurish
(`build`), qayta yuklash (`deploy`) shart emas — saqlash tugmasini bosganingiz
bilan sayt yangilanadi. Sahifani yangilab koʻring.

---

## 1. Kirish

Manzil: **`https://<sayt-manzili>/admin/`**

Uchta katakcha: **login**, **parol** va **tekshiruv**.

- **Login** — oʻzingizniki. Hamkasblar uchun alohida login ochiladi (§10).
  Adminkaga faqat bitta parol bilan kiriladigan boʻlsa, login katakchasini
  boʻsh qoldirsangiz ham boʻladi.
- **Tekshiruv** — rasmdagi besh belgi. Robot parol roʻyxati bilan urinmasligi
  uchun. Rasm tushunarsiz boʻlsa, **Boshqa rasm** tugmasini bosing: javob
  oʻzgarmaydi, rasm qaytadan chiziladi. Bu yerda xato qilish xavfsiz — parol
  umuman tekshirilmaydi va hech narsa bloklanmaydi.

Dastlabki parolni dasturchi oʻrnatadi. Parol faqat sizda boʻlsin: adminkada
forum mehmonlarining ismi, telefoni va elektron pochtasi turadi.

### Parolni oʻzgartirish

Buni oʻzingiz qilasiz, dasturchini kutmasdan: oʻng yuqoridagi **Parol**
tugmasi. Hozirgi parolni va yangisini ikki marta soʻraydi.

- Kamida **10 belgi**. Faqat raqamdan iborat boʻlmasin.
- Hozirgi parol ham soʻraladi — ochiq qolgan kompyuter yangi parol qoʻyish
  huquqi emas.
- Saqlagandan keyin **boshqa qurilmalardagi** sessiyalar yopiladi: kim
  qayerda kirib qolgan boʻlsa, chiqib ketadi. Oʻzingiz ochiq qolasiz.

### Parolni unutib qoʻysangiz

Tiklab boʻlmaydi — faqat yangisini qoʻyish mumkin, va buni serverga kira
oladigan odam qiladi:

```sh
php /var/www/forum/server/bin/password.php
```

Soʻraydi, ekranda koʻrsatmaydi, hech qayerga yozmaydi.

### Xato parol kiritilsa

**Bitta xato parol — shu manzil darhol bloklanadi**, 15 daqiqaga; takrorlansa
muddat ikki barobar oshadi. Qattiq qoida, va atayin shunday: mehmonlar
roʻyxati turgan eshikda parol roʻyxati bilan urinayotgan odam har bir
manzildan atigi bitta taxmin qila oladi.

Rasmdagi tekshiruvda xato qilish blokga olib **kelmaydi** — u paroldan oldin
soʻraladi va xato boʻlsa parol umuman oʻqilmaydi.

Oʻzingiz bloklanib qolsangiz va kutib boʻlmasa, serverga kira oladigan odam
bir buyruq bilan ochadi:

```sh
php /var/www/forum/server/bin/unlock.php
```

> Chiqish uchun oʻng yuqoridagi **Chiqish** tugmasi. Umumiy kompyuterda ishlab
> boʻlgach albatta chiqing. Tegilmasa, sessiya **30 daqiqada** oʻzi yopiladi.

---

## 2. Roʻyxatdan oʻtganlar

Kirgach birinchi koʻradigan sahifangiz. Yuqorida bir qator raqam, uch guruhga
ajratilgan — chiziqchalar guruhlarni ajratib turadi:

- **Jami** — kelgan arizalar soni
- **VIP** / **Investor** / **Mehmon** / **Spiker** — ishtirok turi boʻyicha
- **Yangi** — hali koʻrib chiqilmagan, **Tasdiqlangan** — siz tasdiqlagan

Raqamning oʻziga bosilsa roʻyxat shu boʻyicha filtrlanadi.

> **Telefonda.** Ekran tor boʻlsa jadval qatorlari kartochkaga aylanadi: har
> bir ariza alohida kartochka boʻlib, ichida ustun nomlari bilan birga
> yoziladi. Yon tomonga surish shart emas — holatni oʻzgartirish ham, biletni
> berish ham oʻsha kartochka ichida.

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

Birinchi sahifaning tepasida ikkita katta kartochka — **Loyihalar** va
**Nashr va tarix**. Ular sayt matni emas: biri portfel, ikkinchisi
oʻzgarishlarni nashr qilish, shuning uchun alohida turadi.

Ostida **Sayt matnlari**: **Tadbir**, **Kirish**, **Maqsadlar**,
**Yoʻnalishlar**, **Ishtirokchilar**, **Sessiyalar**, **Dastur**, **Aloqa** va
boshqalar. Har birining ostida nechta maydon borligi yozilgan. Agar siz
oʻzgartirgan boʻlsangiz — “3 ta oʻzgartirilgan” deb ham koʻrsatadi.

### Loyihalar roʻyxati

Loyihalar ikki yuzdan ortiq, shuning uchun roʻyxat hududlar boʻyicha
boʻlingan: har bir hudud oʻz sarlavhasi va soni bilan. Jadval tepasidagi
hudud tugmalaridan biriga bosilsa, roʻyxat shu hududga oʻtadi — pastga
aylantirib oʻtirish shart emas. Qidiruv maydoni loyiha nomi va kodi boʻyicha
ishlaydi.

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
Ikkalasi ham kalendarli maydon: bosasiz, kundan tanlaysiz. Sanani qoʻlda
yozmaysiz va “25–27 noyabr” degan yozuvni ham hech qayerga yozmaysiz — sayt
uni shu ikki sanadan oʻzi yasaydi, uchala tilda.

Saqlab, **Nashr** qilganingizdan keyin yangi sana shu zahoti hamma joyda
koʻrinadi:

- bosh sahifaning sarlavhasi va brauzer varagʻining nomi;
- roʻyxatdan oʻtish sahifasi;
- loyiha PDF-larining muqovasi va har bir sahifaning yuqorigi qatori;
- shu paytdan keyin ochilgan **e-biletlar va beyjlar**.

Ikki sanani bir xil qilib qoʻysangiz (masalan, ikkalasi ham `2026-11-25`),
sayt “25 noyabr 2026” deb yozadi — “25–25” emas.

> **Eslatma.** Allaqachon chop etilgan yoki saqlab olingan beyjlarda eski
> sana qoladi: qogʻozdagi narsani adminka oʻzgartira olmaydi. Sana
> oʻzgarsa, biletni qaytadan ochib chiqarish kerak.

> **Yana bir eslatma.** Brauzerga sayt ochilgunicha koʻrsatiladigan zaxira
> sarlavha HTML faylining ichida turadi va u faqat sayt qaytadan yigʻilganda
> yangilanadi. Odam koʻradigan sarlavha — sahifa ochilgandan keyingisi, u
> sizning sanangizni koʻrsatadi.

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

Yuqoridagi **Loyiha qoʻshish** boʻlimidan — toʻldirilgan roʻyxat fayli bilan
yoki bitta loyihani qoʻlda kiritib. Batafsil: §8.

### Loyihani vaqtincha olib turish

**Loyihalar** → qidiring → **Yashirish**. Keyin **Qaytarish**.


---

## 8. Loyiha qoʻshish

Yuqoridagi **Loyiha qoʻshish** boʻlimi. Ikki yoʻl bor: toʻldirilgan roʻyxat
fayli bilan (bir vaqtning oʻzida oʻnlab loyiha) yoki bitta loyihani qoʻlda
kiritish.

### Eng muhimi: dublikat qoʻshilmaydi

Saytdagi loyihalar viloyatlardan, taqdimotlardan va qoʻmita roʻyxatlaridan
yigʻilgan. Bitta mehmonxona uch xil nom bilan kelgan holatlar boʻlgan —
13-noyabrda kelgan toʻqqiz qatorli roʻyxatning yettitasi allaqachon saytda
turgan loyihalar edi.

Shuning uchun fayl yuklanganda **hech narsa darhol saytga chiqmaydi**. Avval
tekshiruv natijasi koʻrsatiladi:

| Rang | Nimani bildiradi | Nima qilasiz |
|---|---|---|
| **yangi loyiha** | Saytda bunday loyiha yoʻq | Belgisi oʻzi qoʻyilgan — tekshirib, qoʻshasiz |
| **oʻxshashi bor** | Shu hududda quvvati yoki manzili bir xil loyiha bor | Yonidagi havoladan oʻsha loyihani oching. Ikkitasi bitta obyekt boʻlsa — belgilamang |
| **allaqachon saytda** | Kodi yoki nomi saytdagi loyiha bilan bir xil | Qoʻshib boʻlmaydi — belgisini qoʻyib ham boʻlmaydi |
| **xatolik bor** | Qatorda tuzatish kerak boʻlgan narsa bor | Faylni tuzatib, qaytadan yuklang |

Pastda **Belgilanganlarni saytga qoʻshish** tugmasi. Faqat belgilangan
qatorlar qoʻshiladi; qolgani yoʻqoladi, faylning oʻzi serverda qolmaydi.

### Roʻyxat fayli

`.xlsx` yoki `.csv`. Ustunlar viloyatlarga tarqatilgan namunadagi bilan bir
xil — **Namuna faylni yuklab olish** havolasi shu yerda, birinchi qatorida
toʻldirish namunasi bilan.

Majburiy ustunlar yulduzcha bilan belgilangan: KOD, VILOYAT, TUMAN, LOYIHA
NOMI (UZ), YOʻNALISH, FAOLIYAT TURI, LOYIHA BOSQICHI, MANZIL, SUMMA HOLATI,
LOYIHA TAVSIFI va ikkita ichki ustun (masʼul tashkilot, rasmiy aloqa).

Nimalar **rad etiladi**:

- KOD formati notoʻgʻri boʻlsa (`BUX-01` koʻrinishida boʻlishi kerak);
- KOD dagi viloyat kodi VILOYAT ustuniga mos kelmasa;
- summa raqam boʻlmasa, yoki summa yozilib valyuta koʻrsatilmagan boʻlsa;
- roʻyxatdan tanlanadigan ustunga roʻyxatda yoʻq soʻz yozilgan boʻlsa;
- **biror katakda shaxsiy telefon raqami, STIR yoki hisob raqami boʻlsa.**
  Bu qatʼiy: shaxsiy maʼlumot saytga chiqmaydi.

Toʻldirilmagan katak esa rad etmaydi — u **eslatma** boʻlib qoladi va
loyihaning oʻzida «viloyat toʻldirmagan» deb yozib qoʻyiladi, keyin soʻrash
uchun.

### Rasmlar

Rasmlar roʻyxat faylining ichiga **qoʻyilmaydi** — Excel katakchasiga
qoʻyilgan rasm hech qaysi qatorga tegishli boʻlmaydi, u jadval ustida suzib
yuradi.

Shuning uchun rasmlar alohida **`.zip`** arxivda keladi va fayl nomi loyiha
kodini koʻtaradi:

```
SUR-01_1.jpg
SUR-01_2.jpg
SUR-02_1.png
```

Bitta loyihaga 8 tagacha rasm olinadi. Nomi mos kelmagan fayllar tekshiruv
natijasida alohida koʻrsatiladi — «bu rasmlar hech qaysi qatorga tegishli
emas».

Rasm serverda qayta ishlanadi: WebP ga aylantiriladi, ikki oʻlchamda
saqlanadi, EXIF maʼlumotlari (jumladan suratga olingan joy koordinatasi)
olib tashlanadi.

### Bitta loyihani qoʻlda

Fayl shart boʻlmagan yoʻl: oʻsha ustunlarning oʻzi forma koʻrinishida.
Roʻyxatdan tanlanadigan ustunlar menyu boʻlib chiqadi, shuning uchun xato
yozib boʻlmaydi.

Oʻxshash loyiha topilsa — qoʻshmaydi va nimaga oʻxshashini aytadi. Bilib
turib qoʻshmoqchi boʻlsangiz, pastdagi belgini qoʻyasiz; bu jurnalga ham
yoziladi.

### Qoʻshilgan loyihani olib tashlash

Shu boʻlimning pastida adminkadan qoʻshilgan loyihalar roʻyxati turadi, har
birining yonida **Oʻchirish**. Bu faqat adminkadan qoʻshilganlar uchun;
build dan kelgan loyihani oʻchirib boʻlmaydi, uni **yashirish** mumkin
(§4 ga qarang).

---

## 9. Loyihaga rasm qoʻshish

Har qanday loyihada — adminkadan qoʻshilganida ham, build dan kelganida ham.

**Saytni tahrirlash → Loyihalar → loyihani tanlang.** Eng tepada «Rasmlar»
boʻlimi:

- **Yangi rasm** — JPEG, PNG yoki WebP, eng kattasi 12 MB;
- **↑ ↓** — tartibni oʻzgartirish. Birinchi rasm — loyihaning asosiy rasmi;
- **×** — olib tashlash.

«build dan» belgisi bor rasm — taqdimotdan olingan rasm. Uni loyihadan olib
tashlasangiz, fayl serverda qoladi (keyingi yangilanishda qaytib keladi),
lekin bu loyihada koʻrinmaydi.

> **Nega rasm darhol koʻrinmasligi mumkin.** Brauzer rasmlarni keshlaydi.
> Sahifani `Ctrl+F5` bilan yangilang.

---

## 10. Foydalanuvchilar

Adminkaga bir necha kishi kirishi mumkin, va har biri oʻz nomi bilan.
Parolni boʻlishish oʻrniga alohida hisob ochiladi: shunda jurnalda kim nima
qilgani koʻrinadi (§11).

Bu boʻlim **faqat egasiga** koʻrinadi.

### Uch xil huquq

| Huquq | Nima qila oladi |
|---|---|
| **Egasi** | Hammasi: arizalar CSV si, foydalanuvchilar, sayt, loyihalar |
| **Muharrir** | Saytni tahrirlaydi, loyiha va rasm qoʻshadi, ariza holatini oʻzgartiradi. CSV yuklab **ololmaydi**, foydalanuvchilarni boshqara olmaydi |
| **Kuzatuvchi** | Faqat koʻradi. Hech narsani oʻzgartira olmaydi |

CSV ni faqat egasi yuklay oladi, chunki u butun mehmonlar roʻyxati —
telefon raqamlari va pochta manzillari bilan birga serverdan chiqadigan
fayl.

### Yangi foydalanuvchi

Login, ism-sharif, huquq va dastlabki parol. Parol **bir marta** ekranda
koʻrsatiladi va boshqa hech qayerda saqlanmaydi — oʻsha zahoti yetkazing.
Yoʻqolsa, shu yerdan yangisini qoʻyasiz.

### Oʻchirish va huquqni oʻzgartirish

Har qatorning yonida **Oʻzgartirish** menyusi: yangi parol qoʻyish,
vaqtincha oʻchirish, butunlay oʻchirish.

Huquq oʻzgartirilsa yoki hisob oʻchirilsa, oʻsha odamning ochiq
sessiyalari darhol yopiladi.

Oxirgi **egasini** oʻchirib ham, huquqini pasaytirib ham boʻlmaydi — aks
holda adminkaga hech kim kira olmay qoladi.

---

## 11. Jurnal

Adminkada qilingan har bir oʻzgarish yozib boriladi: kim, qachon, qaysi
manzildan, nima qilgan.

- kirish, chiqish, **notoʻgʻri parol urinishi**;
- sayt matnining tahriri — qaysi maydon oʻzgargani bilan;
- qoʻshilgan va oʻchirilgan loyiha, yashirilgan loyiha;
- yuklangan va olib tashlangan rasm;
- qoʻshilgan foydalanuvchi, berilgan huquq, oʻzgartirilgan parol;
- CSV yuklab olinishi — ham arizalarniki, ham jurnalniki.

Yuqorida qidiruv: kim boʻyicha, amal boʻyicha, oy boʻyicha. **CSV** tugmasi
tanlangan qatorlarni Excel uchun yuklab beradi.

Jurnalni **oʻchirib ham, tahrirlab ham boʻlmaydi** — adminkada bunday tugma
yoʻq. Oʻchirish tugmasi bor jurnal — jurnal emas.

Jurnalni egasi ham, muharrirlar ham koʻradi: oʻz ishi yozib borilayotgan
odam uni oʻqiy olishi kerak.

---

## 12. Nima qilmaslik kerak

- **Kalit (`id`) maydonini oʻzgartirmang.** U sayt ichida havolalar uchun
  ishlatiladi; oʻzgartirsangiz oʻsha loyihaga boradigan havolalar ishlamay
  qoladi.
- **Raqamlarni oʻylab topmang.** Loyiha raqamlari tashkilotchi bergan
  hujjatlardan olingan. Hujjatda yoʻq raqamni yozmang.
- **Parolni hech kimga bermang va brauzerda “eslab qol” demang**, ayniqsa
  umumiy kompyuterda.
- **CSV faylni tarqatmang** — ichida shaxsiy maʼlumot bor.
- **Parolni hamkasbingizga bermang** — unga alohida login oching (§10).
  Boʻlishilgan parol jurnalni maʼnosiz qiladi: har bir qator bir xil nomni
  koʻrsatadi.
- **Tekshiruv natijasini oʻqimasdan «hammasini qoʻshish» demang.** «Oʻxshashi
  bor» deb belgilangan qator — koʻpincha haqiqatan ham oʻsha loyihaning
  oʻzi, boshqa nom bilan.

---

## 13. Nimadir ishlamay qolsa

| Alomat | Nima qilish |
|---|---|
| “Parol notoʻgʻri” — lekin parol toʻgʻri | Katta-kichik harfga, klaviatura tiliga qarang. Baribir kirmasa — parolni qaytadan qoʻying (1-boʻlim) |
| “Saytning maʼlumot fayli topilmadi” | `server/.env` dagi `SITE_DIR` notoʻgʻri. Dasturchiga ayting — serverda `php server/bin/doctor.php` nima deyishini aytsin |
| «manzil vaqtincha yopildi» | Bitta xato parol ham blokga olib keladi. Yozilgan daqiqani kuting yoki serverda `php server/bin/unlock.php` |
| «Tekshiruv javobi notoʻgʻri» | Rasmdagi belgilar. **Boshqa rasm** tugmasini bosing — bu blokga olib kelmaydi |
| Fayl yuklanmayapti, sahifa boʻm-boʻsh | nginx chegarasi kichik. **Loyiha qoʻshish** sahifasidagi «Server fayl qabul qiladimi?» tugmasini bosing va javobni dasturchiga ayting |
| «Serverda GD kutubxonasi yoʻq» | Serverga `php84-gd` oʻrnatilmagan — rasm yuklab boʻlmaydi. Dasturchiga ayting |
| Sahifa oʻzi chiqib ketdi | Sessiya vaqti tugadi (30 daqiqa tegilmasa) yoki kimdir parolni oʻzgartirdi |
| Saqladim, lekin saytda oʻzgarmadi | Brauzerni `Ctrl+F5` bilan yangilang. Baribir boʻlmasa — **Nashr → Saytni qayta nashr etish** |
| “Sahifa eskirgan” | Adminka uzoq ochiq turgan. Sahifani yangilang va qaytadan saqlang |
| «Bilet topilmadi» | Havola toʻliq nusxalanmagan. Adminkadagi **E-bilet** tugmasidan qayta oling |
| «Bilet hali berilmagan» | VIP yoki spiker: adminkadan **Biletni berish** tugmasini bosing |
| Ariza kelmayapti | `https://<sayt>/api/health` ni oching. `{"ok":true…}` chiqmasa, dasturchiga ayting |

Boshqa hamma holatda — dasturchiga murojaat qiling va **nima qilganingizni**
hamda **ekranda nima yozilganini** aytib bering.
