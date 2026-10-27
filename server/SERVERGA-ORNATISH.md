# Adminkani serverda ishga tushirish

**Kimga:** serverga SSH orqali kira oladigan odamga.
**Nima uchun:** sayt ishlayapti, lekin `домен/admin/` ochilmayapti — chunki
adminka hali ishga tushirilmagan. Quyida noldan oxirigacha, har bir buyruq va
undan keyin **ekranda nima chiqishi** va **nima qilish kerakligi** yozilgan.

Vaqt: 20–30 daqiqa. Bir marta qilinadi. Oxirida avto-deploy sozlanadi va
undan keyin serverga umuman kirish shart emas — dasturchi kodni
oʻzgartirganda sayt oʻzi yangilanadi.

---

## 0. Bu qoʻllanma serverda nimaga tegadi

Serverda boshqa saytlar ham bor. Shuning uchun aniq yozib qoʻyamiz:

**Tegiladi — faqat shular:**

1. Saytning oʻz papkasi (sayt allaqachon oʻsha yerdan berilyapti).
2. Shu papka yonidagi `server/` papkasi (allaqachon `git clone` bilan kelgan).
3. nginx konfiguratsiyasida **faqat shu domenning oʻz `server { }` bloki** —
   ichiga 3 ta `location` qoʻshiladi.
4. PHP-FPM — agar oʻrnatilmagan boʻlsa, oʻrnatiladi va ishga tushiriladi.

**Tegilmaydi:**

- boshqa domenlar, boshqa `server { }` bloklari, ularning papkalari;
- maʼlumotlar bazasi (bu loyihada MySQL/Postgres umuman ishlatilmaydi);
- cron, firewall, foydalanuvchilar, tizim sozlamalari;
- nginx **qayta ishga tushirilmaydi** — faqat `reload` qilinadi, bu uzilishsiz
  va boshqa saytlarga taʼsir qilmaydi.

Avto-deploy sozlangandan keyin ham shunday: u faqat shu ikki papkaga yozadi
va papkani notoʻgʻri koʻrsa — **hech narsa koʻchirmasdan toʻxtaydi.**

---

## 1. Serverga kirish

Windows `cmd` dan:

```
ssh -i C:\Users\<siz>\.ssh\<kalit> <foydalanuvchi>@<domen>
```

| Ekranda | Maʼnosi | Nima qilish |
|---|---|---|
| `Welcome ...` yoki `~ #` | Kirdingiz | Davom eting |
| `Permission denied (publickey)` | Kalit notoʻgʻri yoki yoʻl xato | Kalit faylining toʻliq yoʻlini tekshiring |
| `Connection timed out` | Port/IP yopiq | Server IP va portini tekshiring |

Quyidagi buyruqlar `root` yoki `sudo` huquqini talab qiladi. `root` boʻlmasangiz
buyruqlar oldiga `sudo` qoʻshing.

---

## 2. Serverda nima borligini aniqlash

Bu bosqichda **hech narsa oʻzgartirilmaydi** — faqat qaraymiz. Chiqqan
javoblarni yozib qoʻying, keyin kerak boʻladi.

### 2.1. Qanday Linux

```sh
cat /etc/os-release | head -2
```

- `Alpine Linux` → keyingi buyruqlarda **A variantini** oling (`apk`, `rc-service`).
- `Ubuntu` yoki `Debian` → **B variantini** oling (`apt`, `systemctl`).

### 2.2. Sayt qaysi papkadan berilyapti

```sh
nginx -T 2>/dev/null | grep -nE "server_name|root " | head -40
```

Chiqqan roʻyxatdan **oʻz domeningizni** toping; undan keyingi `root ...`
qatori — saytning papkasi. Masalan:

```
server_name  forum.example.uz;
root /var/www/forum/dist;
```

Shu yoʻlni yozib oling — bundan keyin uni **`SITE_PATH`** deb ataymiz.

```sh
ls -la <SITE_PATH> | head
```

`index.html`, `assets/`, `data/`, `register/` koʻrinishi kerak. Koʻrinmasa —
notoʻgʻri papkani olgansiz, roʻyxatdan boshqasini tanlang.

### 2.3. `server/` papkasi qayerda

Sayt `git clone` bilan oʻrnatilgan, demak repo ichida `server/` papkasi bor:

```sh
ls -la <SITE_PATH>/../server
```

Koʻrinsa (`public`, `src`, `bin` papkalari bilan) — shu yoʻlni
**`SERVER_PATH`** deb yozib oling. Odatda bu `<SITE_PATH>` ning yonidagi
`server`, masalan `/var/www/forum/server`.

**Koʻrinmasa** — repo eski, yangilash kerak:

```sh
cd <repo papkasi>        # dist/ va server/ turadigan papka
git pull
```

> `error: Your local changes would be overwritten` chiqsa: `git stash` qiling,
> keyin `git pull`. Bu papkada qoʻlda oʻzgartirish boʻlmasligi kerak.

### 2.4. PHP bormi

```sh
php -v || php84 -v || php8.3 -v
```

- Versiya chiqsa (**8.1 yoki yuqori**) — yaxshi, 3-bosqichni oʻtkazib yuboring.
- `not found` chiqsa — 3-bosqich.
- Versiya 8.0 yoki pastroq boʻlsa — yangisini oʻrnatish kerak (3-bosqich).

### 2.5. PHP-FPM ishlayaptimi va soketi qayerda

```sh
ps aux | grep -c "[p]hp-fpm"        # 0 dan katta boʻlsa — ishlayapti
ls /run/php*/ /var/run/php*/ 2>/dev/null
```

`php-fpm.sock` koʻrinsa — uning **toʻliq yoʻlini** yozib oling, masalan
`/run/php-fpm84/php-fpm.sock` yoki `/run/php/php8.3-fpm.sock`. nginx ga shu
kerak boʻladi.

### 2.6. PHP qaysi foydalanuvchi nomidan ishlaydi

```sh
ps aux | grep "[p]hp-fpm" | awk '{print $1}' | sort -u
```

Chiqqanlardan `root` dan boshqasi (masalan `www-data`, `nginx`, `php-dev`) —
**PHP foydalanuvchisi** shu. Yozib oling.

Hech narsa chiqmasa, sozlama faylidan qarang:

```sh
grep -rhE "^user *=" /etc/php*/php-fpm.d/*.conf /etc/php/*/fpm/pool.d/*.conf 2>/dev/null
```

---

## 3. PHP ni oʻrnatish (faqat yoʻq boʻlsa)

**A — Alpine:**

```sh
apk add php84 php84-fpm php84-session php84-json php84-mbstring php84-opcache
apk add php84-pdo php84-pdo_sqlite        # ixtiyoriy, lekin tavsiya qilinadi
rc-update add php-fpm84 && rc-service php-fpm84 start
ln -sf /usr/bin/php84 /usr/local/bin/php  # "php" deb yozish uchun
```

**B — Ubuntu/Debian:**

```sh
apt update
apt install -y php8.3-fpm php8.3-mbstring php8.3-sqlite3
systemctl enable --now php8.3-fpm
```

Keyin yana `2.5` va `2.6` ni bajarib, soket yoʻlini va foydalanuvchini yozib
oling.

| Ekranda | Nima qilish |
|---|---|
| `ERROR: unable to select packages` (Alpine) | `apk update` qiling, keyin qayta urinib koʻring |
| `Unable to locate package php8.3-fpm` | `apt install -y php-fpm` qiling va chiqqan versiyani ishlating |
| `Job for php8.3-fpm.service failed` | `journalctl -u php8.3-fpm -n 20` — xatoni oʻqing |

---

## 4. Papkalar va huquqlar

Adminka **ikkita** papkaga yoza olishi kerak, boshqa hech qayerga emas:

| Papka | Nima turadi |
|---|---|
| `<SERVER_PATH>/data` | arizalar, parol, bilet kaliti, adminkadagi oʻzgarishlar |
| `<SITE_PATH>/data` | saytning oʻzi oʻqiydigan 2 ta JSON — adminka shularni yangilaydi |

`<PHPUSER>` oʻrniga 2.6 da topilgan nomni qoʻying:

```sh
mkdir -p <SERVER_PATH>/data
chown -R <PHPUSER> <SERVER_PATH>/data
chmod 770 <SERVER_PATH>/data

chown -R <PHPUSER> <SITE_PATH>/data
chmod 2775 <SITE_PATH>/data
```

Va saytning papkasiga belgi qoʻyamiz — avto-deploy keyinchalik aynan shu belgi
boʻyicha “bu bizning papka” deb ishonch hosil qiladi:

```sh
echo "forum" > <SITE_PATH>/.forum-site
```

---

## 5. Sozlamalar fayli — `.env`

Butun oʻrnatish shu bitta faylda.

```sh
cd <SERVER_PATH>
cp .env.example .env
```

Faylni oching (`nano .env`) va **uchta qatorni** toʻldiring:

```
SITE_DIR=<SITE_PATH>
SITE_URL=https://<domen>
DATA_DIR=<SERVER_PATH>/data
```

Qolgan qatorlarga tegmasa ham boʻladi — hammasining standart qiymati bor.
Saqlash: `Ctrl+O`, `Enter`, `Ctrl+X`.

Keyin parol:

```sh
php bin/password.php
```

Soʻraydi, ekranda koʻrsatmaydi, ikki marta kiritasiz. **Kamida 10 belgi.**

```sh
chown <PHPUSER> .env && chmod 600 .env
```

> Parolni keyin adminkaning oʻzidan ham oʻzgartirsa boʻladi. Unutilsa — shu
> buyruqni qayta ishga tushiring.

---

## 6. Tekshirish — birinchi marta

```sh
php bin/doctor.php
```

Bu buyruq hamma narsani tekshiradi va **nimasi notoʻgʻri ekanini aytadi.**
Chiqadigan satrlar:

| Satr | Maʼnosi | Nima qilish |
|---|---|---|
| `[  ok  ]` | Joyida | Hech narsa |
| `[ eslat]` | Ishlaydi, lekin tavsiya bor | Oxirida koʻrib chiqing |
| `[ XATO ]` | Shu holatda ishlamaydi | Satr oxirida yechimi yozilgan |

Koʻp uchraydiganlari:

| XATO | Sabab | Yechim |
|---|---|---|
| `.env yoʻq` | 5-bosqich qilinmagan | `cp .env.example .env` |
| `Adminka paroli oʻrnatilmagan` | parol qoʻyilmagan | `php bin/password.php` |
| `Papka yoʻq` / `Yozib boʻlmadi` | huquqlar | 4-bosqichdagi `chown`/`chmod` |
| `Sayt papkasi yoʻq` | `SITE_DIR` notoʻgʻri | `.env` dagi yoʻlni tekshiring |
| `data/ ga yozib boʻlmadi` | `<SITE_PATH>/data` huquqi | `chown -R <PHPUSER> <SITE_PATH>/data` |
| `php84-mbstring yoʻq` | paket yoʻq | 3-bosqichdagi `apk add` / `apt install` |

Oxirida `Hammasi joyida` yoki `Ishlaydi. N ta eslatma bor` chiqsa — keyingi
bosqichga oʻting. `N ta xato topildi` chiqsa — yuqoridagi jadval boʻyicha
tuzating va buyruqni qaytadan ishga tushiring.

> Diqqat: bu bosqichda `/admin/` hali **ochilmaydi** — nginx hali PHP ga
> ulanmagan. U keyingi bosqichda.

---

## 7. nginx — uchta `location`

### 7.1. Domenning konfiguratsiya faylini topish

```sh
grep -rl "<domen>" /etc/nginx/ 2>/dev/null
```

Chiqqan faylni oching: `nano <fayl>`.

### 7.2. Qoʻshish

**Faqat shu domenning `server { ... }` bloki ichiga**, oxirgi `}` dan oldin
quyidagini qoʻying. `<SERVER_PATH>` va `<SOKET>` ni oʻzingiznikiga
almashtiring:

```nginx
    # --- Forum: roʻyxatdan oʻtish, adminka, e-bilet ---------------------

    location = /api/register {
        include fastcgi_params;
        fastcgi_pass unix:<SOKET>;
        fastcgi_param SCRIPT_FILENAME <SERVER_PATH>/public/api.php;
    }

    location = /api/health {
        include fastcgi_params;
        fastcgi_pass unix:<SOKET>;
        fastcgi_param SCRIPT_FILENAME <SERVER_PATH>/public/api.php;
    }

    location ^~ /ticket/ {
        include fastcgi_params;
        fastcgi_pass unix:<SOKET>;
        fastcgi_param SCRIPT_FILENAME <SERVER_PATH>/public/ticket.php;
    }

    # Oxirida / boʻlmasa, yuqoridagi blok umuman ishlamaydi va odam
    # saytning 404 sahifasini koʻradi. Shuning uchun — redirect.
    location = /admin {
        return 301 /admin/;
    }

    location /admin/ {
        alias <SERVER_PATH>/public/;
        index index.php;

        location ~ ^/admin/(.+\.php)$ {
            include fastcgi_params;
            fastcgi_pass unix:<SOKET>;
            fastcgi_param SCRIPT_FILENAME <SERVER_PATH>/public/$1;
        }
    }

    # Adminka yangilagan 2 ta JSON keshda qolmasin.
    location /data/ {
        add_header Cache-Control "no-cache";
    }
```

> **Boshqa `server { }` bloklariga tegmang.** Agar faylda bir nechta blok
> boʻlsa, `server_name` boʻyicha oʻzingiznikini aniq toping.

### 7.3. Tekshirish va qoʻllash

```sh
nginx -t
```

| Ekranda | Nima qilish |
|---|---|
| `syntax is ok` + `test is successful` | Davom eting |
| `unexpected "}"` yoki `directive is not allowed here` | Blokni notoʻgʻri joyga qoʻygansiz — `server { }` ichida boʻlishi shart |
| boshqa xato | **`reload` qilmang.** Xato qatorini tuzating, yana `nginx -t` |

`ok` boʻlgandan keyin:

```sh
nginx -s reload        # yoki: rc-service nginx reload / systemctl reload nginx
```

Bu uzilishsiz — boshqa saytlar ishlab turaveradi.

---

## 8. Ishladimi?

```sh
curl -s https://<domen>/api/health
```

| Javob | Maʼnosi | Nima qilish |
|---|---|---|
| `{"ok":true,"store":...}` | **Hammasi ishlayapti** | Brauzerdan `/admin/` ni oching |
| `{"ok":false,...}` | PHP ishlayapti, papka muammosi | `php bin/doctor.php` |
| `404` | nginx bloki qoʻshilmagan yoki `reload` qilinmagan | 7-bosqich |
| `502 Bad Gateway` | soket yoʻli notoʻgʻri yoki PHP-FPM oʻchiq | 2.5 ni qayta tekshiring, FPM ni ishga tushiring |
| `File not found.` | `SCRIPT_FILENAME` dagi yoʻl notoʻgʻri | `ls <SERVER_PATH>/public/api.php` bilan tekshiring |
| `403 Forbidden` | papkani PHP/nginx oʻqiy olmayapti | `<SERVER_PATH>` va yuqoridagi papkalar `o+x` boʻlsin |

Keyin brauzerda: **`https://<domen>/admin/`**

| Koʻrinsa | Maʼnosi |
|---|---|
| Parol soʻraydigan oyna | **Tayyor.** Parolni kiriting |
| `Parol hali oʻrnatilmagan` | 5-bosqichdagi `php bin/password.php` bajarilmagan |
| Boʻsh oq sahifa | PHP xatosi. `tail -50 /var/log/nginx/error.log` ni koʻring |
| Sayt sahifasi yoki 404 | `location /admin/` qoʻshilmagan, yoki manzilni **`/admin/`** deb, oxirida `/` bilan yozmagansiz (`location = /admin` redirect bloki shuning uchun kerak) |

Kirgach: yuqorida **CSV yuklab olish**, **Saytni tahrirlash**, **Parol**,
**Chiqish** tugmalari boʻladi. Foydalanish qoʻllanmasi: `QOLLANMA.md`.

---

## 9. Avto-deploy — serverga boshqa kirmaslik uchun

Shundan keyin dasturchi kodni oʻzgartirib `push` qilganda sayt **oʻzi**
yangilanadi. Serverga kirish, `git pull`, `npm run build` — boshqa kerak emas.

### 9.1. Deploy uchun kalit yasash (serverda, bir marta)

```sh
ssh-keygen -t ed25519 -f ~/.ssh/deploy -N ''
cat ~/.ssh/deploy.pub >> ~/.ssh/authorized_keys
chmod 600 ~/.ssh/authorized_keys
```

Keyin ikkita narsani nusxa koʻchirib oling:

```sh
cat ~/.ssh/deploy          # 1) MAXFIY KALIT — BEGIN dan END gacha hammasi
ssh-keyscan -p 22 <domen>  # 2) server barmoq izi (bir necha qator)
```

> Maxfiy kalitni faqat dasturchiga, shaxsiy kanal orqali bering. U GitHub
> ichida shifrlangan holda saqlanadi va hech kimga koʻrinmaydi.

### 9.2. Dasturchiga beriladigan 7 ta qiymat

| Nomi | Qiymati |
|---|---|
| `SSH_KEY` | yuqoridagi maxfiy kalit |
| `SSH_KNOWN_HOSTS` | `ssh-keyscan` chiqargan qatorlar |
| `SSH_HOST` | domen yoki IP |
| `SSH_USER` | SSH foydalanuvchi nomi |
| `SSH_PORT` | port (odatda `22`) |
| `SITE_PATH` | 2.2 dagi yoʻl |
| `SERVER_PATH` | 2.3 dagi yoʻl |

### 9.3. Deploy nima qiladi

1. Saytni GitHub'da yigʻadi (serverda `npm` kerak emas).
2. **Ikkala papkani tekshiradi:** `<SITE_PATH>/.forum-site` va
   `<SERVER_PATH>/.env` joyidami. Boʻlmasa — **hech narsa koʻchirmay
   toʻxtaydi.** Serverdagi boshqa narsalarga tegish imkoni yoʻq.
3. Saytni `<SITE_PATH>` ga, backend'ni `<SERVER_PATH>` ga koʻchiradi.
   `.env` va `data/` qoʻlga tegmaydi — arizalar ham, parol ham joyida qoladi.
   Sertifikat uchun ishlatiladigan `.well-known/` papkasi ham tegilmaydi.
4. Adminkadan kiritilgan oʻzgarishlarni (sana, matn, yashirilgan loyihalar)
   yangi build ustiga qaytadan qoʻyadi.
5. `doctor.php` ni ishga tushiradi va `/api/health` ni tekshiradi.

nginx, PHP sozlamalari, boshqa saytlar — hech biriga tegmaydi, hech narsa
qayta ishga tushirilmaydi.

> **Muhim:** shundan keyin serverda `git pull` yoki `npm run build` qilmang.
> Sayt endi GitHub'dan keladi; qoʻlda qilingan oʻzgarish keyingi deploy'da
> ustiga yoziladi.

---

## 10. Keyin nima qilinadi

| Qachon | Nima |
|---|---|
| Har hafta | Hech narsa. Sayt oʻzi yangilanadi |
| Arizalar kerak boʻlsa | Adminka → **CSV yuklab olish** |
| Parolni oʻzgartirish | Adminka → **Parol** |
| Parol unutilsa | Serverga kirib: `php <SERVER_PATH>/bin/password.php` |
| Bir narsa ishlamay qolsa | `php <SERVER_PATH>/bin/doctor.php` — nimasi notoʻgʻriligini aytadi |

**Zaxira nusxa.** `<SERVER_PATH>/data` papkasi — mehmonlar roʻyxatining yagona
nusxasi, ichidagi `ticket-secret.key` esa berilgan biletlarni ochadigan kalit.
Shu papka serverning odatdagi zaxira nusxasiga kirsin. Boshqa hech narsani
zaxiralash shart emas — qolgan hammasi GitHub'da.

**Maxfiylik.** Adminkada va CSV faylida mehmonlarning ismi, telefoni va
elektron pochtasi bor. Parol ham, CSV ham tashqariga chiqmasligi kerak.
