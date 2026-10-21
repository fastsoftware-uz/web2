/**
 * Uzbek and Russian for the field names the project sheets are written in.
 *
 * The sheets arrive in English and are read on a page that is being read in
 * Uzbek or Russian, so a visitor was being shown `LAND AREA`, `PAYBACK` and
 * `TOTAL PROJECT VALUE` in the middle of their own language. The values stay
 * exactly as the organiser wrote them — those are the facts, and translating a
 * fact is how a fact changes — but the name of the box a fact sits in is ours
 * to say properly.
 *
 * Only the labels that recur are here: eighty-seven of them, which is 85% of
 * every row in the portfolio. The rest are one-offs a single sheet invented,
 * and they keep the sheet's own wording, which is the same rule the panel has
 * always followed. That is the point of a dictionary with a fallback rather
 * than a fixed field list: a project that states something nobody else states
 * still shows it, in English, rather than losing it.
 *
 * Keys are the label upper-cased with its spacing collapsed, so `Land area`
 * and `LAND AREA` — both of which the templates use — resolve to one entry.
 */
import type { Lang } from './shared/content-types';

const DICTIONARY: Record<string, { uz: string; ru: string }> = {
  /* --- the chips every sheet carries -------------------------------- */
  INVESTMENT: { uz: 'Investitsiya', ru: 'Инвестиции' },
  CAPACITY: { uz: 'Sigʻimi', ru: 'Вместимость' },
  'LAND AREA': { uz: 'Yer maydoni', ru: 'Площадь участка' },
  LOCATION: { uz: 'Joylashuvi', ru: 'Расположение' },
  STATUS: { uz: 'Holati', ru: 'Статус' },
  ACTIVITY: { uz: 'Faoliyat turi', ru: 'Вид деятельности' },
  'PROJECT TYPE / ACTIVITY': { uz: 'Loyiha turi / faoliyati', ru: 'Тип проекта / деятельность' },
  AREA: { uz: 'Maydoni', ru: 'Площадь' },
  'FACILITY AREA': { uz: 'Obyekt maydoni', ru: 'Площадь объекта' },
  'BUILDING AREA': { uz: 'Bino maydoni', ru: 'Площадь здания' },
  'CONSTRUCTION AREA': { uz: 'Qurilish maydoni', ru: 'Площадь застройки' },
  FLOORS: { uz: 'Qavatlar', ru: 'Этажи' },
  BEDS: { uz: 'Oʻrinlar', ru: 'Койко-места' },
  ROOMS: { uz: 'Xonalar', ru: 'Номера' },
  SUITES: { uz: 'Lyuks xonalar', ru: 'Люксы' },
  VILLAS: { uz: 'Villalar', ru: 'Виллы' },
  COTTAGES: { uz: 'Kottejlar', ru: 'Коттеджи' },
  PARKING: { uz: 'Avtoturargoh', ru: 'Парковка' },
  UTILITIES: { uz: 'Muhandislik tarmoqlari', ru: 'Инженерные сети' },
  ACCESS: { uz: 'Transport qulayligi', ru: 'Транспортная доступность' },

  /* --- the money block ---------------------------------------------- */
  'TOTAL PROJECT VALUE': { uz: 'Loyihaning umumiy qiymati', ru: 'Общая стоимость проекта' },
  'PROJECT VALUE': { uz: 'Loyiha qiymati', ru: 'Стоимость проекта' },
  'REQUIRED INVESTMENT': { uz: 'Talab qilinadigan investitsiya', ru: 'Требуемые инвестиции' },
  'MINIMUM INVESTMENT': { uz: 'Minimal investitsiya', ru: 'Минимальные инвестиции' },
  'INVESTMENT STRUCTURE': { uz: 'Investitsiya tuzilmasi', ru: 'Структура инвестиций' },
  'INVESTOR PARTICIPATION': { uz: 'Investor ishtiroki', ru: 'Участие инвестора' },
  'ADDITIONAL CAPEX': { uz: 'Qoʻshimcha kapital xarajatlar', ru: 'Дополнительные капзатраты' },
  'ASKING PRICE': { uz: 'Soʻralayotgan narx', ru: 'Запрашиваемая цена' },
  'PRICE PER M²': { uz: '1 m² narxi', ru: 'Цена за м²' },
  PAYBACK: { uz: 'Qoplanish muddati', ru: 'Срок окупаемости' },
  'PAYBACK PERIOD': { uz: 'Qoplanish muddati', ru: 'Срок окупаемости' },
  'BALANCE SHEET HOLDER': { uz: 'Balans ushlab turuvchi', ru: 'Балансодержатель' },
  'STABLE REVENUE': { uz: 'Barqaror daromad', ru: 'Стабильный доход' },
  'TURNKEY BUSINESS': { uz: 'Tayyor biznes', ru: 'Готовый бизнес' },

  /* --- dates and progress ------------------------------------------- */
  'IMPLEMENTATION PERIOD': { uz: 'Amalga oshirish muddati', ru: 'Срок реализации' },
  'EXPECTED OPENING': { uz: 'Ochilishi rejalashtirilgan', ru: 'Планируемое открытие' },
  'CONSTRUCTION PROGRESS': { uz: 'Qurilish jarayoni', ru: 'Ход строительства' },
  'CONSTRUCTION READINESS': { uz: 'Qurilish tayyorligi', ru: 'Готовность строительства' },
  'ADVANCED STAGE': { uz: 'Yakuniy bosqich', ru: 'Завершающая стадия' },
  PERMITS: { uz: 'Ruxsatnomalar', ru: 'Разрешения' },

  /* --- what the project is made of ---------------------------------- */
  HOTEL: { uz: 'Mehmonxona', ru: 'Отель' },
  BOUTIQUE: { uz: 'Butik mehmonxona', ru: 'Бутик-отель' },
  ACCOMMODATION: { uz: 'Joylashtirish', ru: 'Размещение' },
  'MODULAR GUEST HOUSES': { uz: 'Modulli mehmon uylari', ru: 'Модульные гостевые дома' },
  'CAPSULE HOUSES': { uz: 'Kapsula uylar', ru: 'Капсульные дома' },
  WELLNESS: { uz: 'Sogʻlomlashtirish', ru: 'Велнес' },
  SPA: { uz: 'SPA', ru: 'СПА' },
  FITNESS: { uz: 'Fitnes', ru: 'Фитнес' },
  LEISURE: { uz: 'Dam olish', ru: 'Досуг' },
  RECREATION: { uz: 'Rekreatsiya', ru: 'Рекреация' },
  ENTERTAINMENT: { uz: 'Koʻngilochar qism', ru: 'Развлечения' },
  SPORT: { uz: 'Sport', ru: 'Спорт' },
  TRAKKING: { uz: 'Trekking', ru: 'Трекинг' },
  DINING: { uz: 'Ovqatlanish', ru: 'Питание' },
  RESTAURANT: { uz: 'Restoran', ru: 'Ресторан' },
  TERRACE: { uz: 'Terrassa', ru: 'Терраса' },
  LOBBY: { uz: 'Lobbi', ru: 'Лобби' },
  CONFERENCE: { uz: 'Konferensiya zali', ru: 'Конференц-зал' },
  MEETINGS: { uz: 'Uchrashuv xonalari', ru: 'Переговорные' },
  'RETAIL SHOPS': { uz: 'Savdo doʻkonlari', ru: 'Торговые точки' },
  SERVICES: { uz: 'Xizmatlar', ru: 'Услуги' },
  'ON-SITE SERVICES': { uz: 'Hududdagi xizmatlar', ru: 'Услуги на территории' },
  FACILITIES: { uz: 'Infratuzilma', ru: 'Инфраструктура' },
  ATTRACTIONS: { uz: 'Diqqatga sazovor joylar', ru: 'Достопримечательности' },
  'TRANSPORT FLEET': { uz: 'Transport parki', ru: 'Автопарк' },
  SCALE: { uz: 'Miqyosi', ru: 'Масштаб' },
  'ANNUAL CAPACITY': { uz: 'Yillik quvvat', ru: 'Годовая мощность' },
  OTHER: { uz: 'Boshqa', ru: 'Прочее' },

  /* --- why invest ---------------------------------------------------- */
  'STRATEGIC LOCATION': { uz: 'Strategik joylashuv', ru: 'Стратегическое расположение' },
  'PRIME LOCATION': { uz: 'Qulay joylashuv', ru: 'Выгодное расположение' },
  'INTEGRATED CONCEPT': { uz: 'Yaxlit konsepsiya', ru: 'Комплексная концепция' },
  'MARKET POTENTIAL': { uz: 'Bozor salohiyati', ru: 'Потенциал рынка' },
  'GROWING MARKET': { uz: 'Oʻsayotgan bozor', ru: 'Растущий рынок' },
  'MARKET BENCHMARK': { uz: 'Bozor koʻrsatkichi', ru: 'Рыночный ориентир' },
  'EXPANSION POTENTIAL': { uz: 'Kengaytirish salohiyati', ru: 'Потенциал расширения' },
  'TOURISM POTENTIAL': { uz: 'Turizm salohiyati', ru: 'Туристический потенциал' },
  'TARGET AUDIENCE': { uz: 'Maqsadli auditoriya', ru: 'Целевая аудитория' },
  DESTINATION: { uz: 'Manzil', ru: 'Направление' },
  'TOURIST DESTINATION': { uz: 'Turistik manzil', ru: 'Туристическое направление' },
  'NEARBY RATES': { uz: 'Atrofdagi narxlar', ru: 'Цены поблизости' },
  'AVG. NEARBY HOTEL RATE': {
    uz: 'Atrofdagi mehmonxonalarning oʻrtacha narxi',
    ru: 'Средняя цена отелей поблизости',
  },

  /* --- what it brings ------------------------------------------------ */
  JOBS: { uz: 'Ish oʻrinlari', ru: 'Рабочие места' },
  'NEW JOBS': { uz: 'Yangi ish oʻrinlari', ru: 'Новые рабочие места' },
  'JOB CREATION': { uz: 'Ish oʻrinlari yaratish', ru: 'Создание рабочих мест' },
  'EMPLOYMENT POTENTIAL': { uz: 'Bandlik salohiyati', ru: 'Потенциал занятости' },
  'ECONOMIC IMPACT': { uz: 'Iqtisodiy samara', ru: 'Экономический эффект' },
  'ECONOMIC & SOCIAL IMPACT': {
    uz: 'Iqtisodiy va ijtimoiy samara',
    ru: 'Экономический и социальный эффект',
  },
  'LOCAL IMPACT': { uz: 'Mahalliy samara', ru: 'Эффект для региона' },

  /* --- who runs it, and on what terms -------------------------------- */
  OPERATOR: { uz: 'Operator', ru: 'Оператор' },
  'OPERATOR MODELS': { uz: 'Operator modellari', ru: 'Модели управления' },
  OWNER: { uz: 'Egasi', ru: 'Собственник' },
  'EXPERIENCED OWNER': { uz: 'Tajribali egasi', ru: 'Опытный собственник' },
  MODEL: { uz: 'Modeli', ru: 'Модель' },
  RATING: { uz: 'Toifasi', ru: 'Категория' },
  'GUEST RATING': { uz: 'Mehmonlar bahosi', ru: 'Оценка гостей' },
  'TOP GUEST RATING': { uz: 'Yuqori mehmon bahosi', ru: 'Высокая оценка гостей' },
  'BRAND PARTNERSHIP': { uz: 'Brend bilan hamkorlik', ru: 'Партнёрство с брендом' },
  'HOTEL BRAND POTENTIAL': {
    uz: 'Mehmonxona brendi salohiyati',
    ru: 'Потенциал гостиничного бренда',
  },
  'BANK PARTNER': { uz: 'Hamkor bank', ru: 'Банк-партнёр' },
  'INVESTMENT PATHWAYS': { uz: 'Investitsiya yoʻllari', ru: 'Варианты участия' },

  /* --- the building and its paperwork -------------------------------- */
  FACADE: { uz: 'Fasad', ru: 'Фасад' },
  'FACADE & ENTRANCE': { uz: 'Fasad va kiraverish', ru: 'Фасад и вход' },
  ENGINEERING: { uz: 'Muhandislik yechimlari', ru: 'Инженерные решения' },
  TECHNICAL: { uz: 'Texnik qism', ru: 'Техническая часть' },
  DOCUMENTS: { uz: 'Hujjatlar', ru: 'Документы' },
  DOCUMENTATION: { uz: 'Hujjatlar', ru: 'Документация' },
  'DOCUMENTS IN ORDER': { uz: 'Hujjatlar tartibda', ru: 'Документы в порядке' },
  'FULL PERMITS': { uz: 'Toʻliq ruxsatnomalar', ru: 'Полный пакет разрешений' },
  'CLEAN TITLE': { uz: 'Toza huquqiy maqom', ru: 'Чистый правовой титул' },
  'CONSTRUCTION STAGE': { uz: 'Qurilish bosqichi', ru: 'Стадия строительства' },
  'REMAINING WORKS': { uz: 'Qolgan ishlar', ru: 'Оставшиеся работы' },
  'YEAR BUILT': { uz: 'Qurilgan yili', ru: 'Год постройки' },
  IMPLEMENTATION: { uz: 'Amalga oshirish', ru: 'Реализация' },
  'PROJECT STATUS': { uz: 'Loyiha holati', ru: 'Статус проекта' },

  /* --- areas and counts ---------------------------------------------- */
  'LAND PLOT': { uz: 'Yer uchastkasi', ru: 'Земельный участок' },
  PLOT: { uz: 'Yer uchastkasi', ru: 'Участок' },
  'TOTAL AREA': { uz: 'Umumiy maydon', ru: 'Общая площадь' },
  'HOTEL AREA': { uz: 'Mehmonxona maydoni', ru: 'Площадь отеля' },
  'TOTAL HOTEL AREA': { uz: 'Mehmonxonaning umumiy maydoni', ru: 'Общая площадь отеля' },
  'DEVELOPMENT AREA': { uz: 'Oʻzlashtiriladigan maydon', ru: 'Площадь освоения' },
  'HOTEL CAPACITY': { uz: 'Mehmonxona sigʻimi', ru: 'Вместимость отеля' },
  'MICE CAPACITY': { uz: 'Tadbirlar sigʻimi', ru: 'Вместимость для мероприятий' },
  'STANDARD ROOMS': { uz: 'Standart xonalar', ru: 'Стандартные номера' },
  'JUNIOR SUITES': { uz: 'Yarim lyuks xonalar', ru: 'Полулюксы' },
  'HOTEL FLOORS': { uz: 'Mehmonxona qavatlari', ru: 'Этажи отеля' },
  'DORMITORY BEDS': { uz: 'Yotoqxona oʻrinlari', ru: 'Места в общежитии' },
  TOWNHOUSES: { uz: 'Taunxauslar', ru: 'Таунхаусы' },
  CHALETS: { uz: 'Shalelar', ru: 'Шале' },
  RESIDENCES: { uz: 'Rezidensiyalar', ru: 'Резиденции' },
  RESIDENTIAL: { uz: 'Turar-joy qismi', ru: 'Жилая часть' },
  PENTHOUSE: { uz: 'Penthaus', ru: 'Пентхаус' },
  'MAIN BUILDING': { uz: 'Asosiy bino', ru: 'Основной корпус' },
  BUILDING: { uz: 'Bino', ru: 'Здание' },
  'COMMON AREAS': { uz: 'Umumiy zonalar', ru: 'Общие зоны' },
  'GREEN AREAS': { uz: 'Koʻkalamzor hududlar', ru: 'Зелёные зоны' },

  /* --- more of what a resort is made of ------------------------------- */
  AMENITIES: { uz: 'Qulayliklar', ru: 'Удобства' },
  'SUPPORTING FACILITIES': { uz: 'Yordamchi infratuzilma', ru: 'Вспомогательная инфраструктура' },
  'ESSENTIAL INFRASTRUCTURE': { uz: 'Asosiy infratuzilma', ru: 'Базовая инфраструктура' },
  'READY INFRASTRUCTURE': { uz: 'Tayyor infratuzilma', ru: 'Готовая инфраструктура' },
  SANATORIUM: { uz: 'Sanatoriy', ru: 'Санаторий' },
  SAUNA: { uz: 'Sauna', ru: 'Сауна' },
  'AQUA PARK': { uz: 'Akvapark', ru: 'Аквапарк' },
  'POOL AND AQUAPARK': { uz: 'Basseyn va akvapark', ru: 'Бассейн и аквапарк' },
  'POOL & LEISURE': { uz: 'Basseyn va dam olish', ru: 'Бассейн и отдых' },
  BAR: { uz: 'Bar', ru: 'Бар' },
  RESTAURANTS: { uz: 'Restoranlar', ru: 'Рестораны' },
  // The sheets misspell this one two different ways; both are the same field.
  RESTORANTS: { uz: 'Restoranlar', ru: 'Рестораны' },
  RESTOURANT: { uz: 'Restoran', ru: 'Ресторан' },
  GASTRONOMY: { uz: 'Gastronomiya', ru: 'Гастрономия' },
  OFFICE: { uz: 'Ofis', ru: 'Офис' },
  OFFICES: { uz: 'Ofislar', ru: 'Офисы' },
  RETAIL: { uz: 'Savdo', ru: 'Торговля' },
  'MULTIFUNCTIONAL HALL': { uz: 'Koʻp maqsadli zal', ru: 'Многофункциональный зал' },
  WORKSHOPS: { uz: 'Ustaxonalar', ru: 'Мастерские' },
  CULTURE: { uz: 'Madaniyat', ru: 'Культура' },
  ENERGY: { uz: 'Energiya taʼminoti', ru: 'Энергоснабжение' },
  CAMPING: { uz: 'Kemping', ru: 'Кемпинг' },
  GLEMPING: { uz: 'Glemping', ru: 'Глэмпинг' },
  HIKING: { uz: 'Piyoda marshrutlar', ru: 'Пешие маршруты' },
  SKI: { uz: 'Changʻi', ru: 'Горные лыжи' },
  'CABLE CAR': { uz: 'Osma yoʻl', ru: 'Канатная дорога' },
  OUTDOOR: { uz: 'Ochiq havoda', ru: 'На открытом воздухе' },
  OUTDOORS: { uz: 'Ochiq havoda', ru: 'На открытом воздухе' },
  ACTIVITIES: { uz: 'Mashgʻulotlar', ru: 'Активный отдых' },

  /* --- where it is, and who stands behind it -------------------------- */
  REGION: { uz: 'Hudud', ru: 'Регион' },
  DISTRICT: { uz: 'Tuman', ru: 'Район' },
  'AIR ACCESS': { uz: 'Havo qatnovi', ru: 'Авиасообщение' },
  'EASY ACCESS': { uz: 'Qulay yoʻl', ru: 'Удобный доступ' },
  'TOURIST ROUTES': { uz: 'Turistik marshrutlar', ru: 'Туристические маршруты' },
  'CENTRAL LOCATION': { uz: 'Markaziy joylashuv', ru: 'Центральное расположение' },
  'PREMIUM LOCATION': { uz: 'Premium joylashuv', ru: 'Премиальное расположение' },
  'HERITAGE LOCATION': { uz: 'Tarixiy joylashuv', ru: 'Историческое расположение' },
  'UNIQUE HERITAGE LOCATION': {
    uz: 'Noyob tarixiy joylashuv',
    ru: 'Уникальное историческое расположение',
  },
  'WATERFRONT LOCATION': { uz: 'Suv boʻyidagi joylashuv', ru: 'Расположение у воды' },
  'MOUNTAIN LOCATION': { uz: 'Togʻdagi joylashuv', ru: 'Горное расположение' },
  'MOUNTAIN SETTING': { uz: 'Togʻ manzarasi', ru: 'Горное окружение' },
  'METRO LOCATION': { uz: 'Metro yonida', ru: 'Рядом с метро' },
  'UNESCO DESTINATION': { uz: 'YUNESKO manzili', ru: 'Направление ЮНЕСКО' },
  'GOVERNMENT SUPPORT': { uz: 'Davlat koʻmagi', ru: 'Государственная поддержка' },
  'STATE-BACKED': { uz: 'Davlat koʻmagida', ru: 'При поддержке государства' },

  /* --- the money case ------------------------------------------------- */
  'RETURN ON INVESTMENT': { uz: 'Investitsiya qaytimi', ru: 'Доходность инвестиций' },
  'GROSS YIELD': { uz: 'Yalpi daromadlilik', ru: 'Валовая доходность' },
  'ASSET VALUE': { uz: 'Aktiv qiymati', ru: 'Стоимость актива' },
  'INCOME POTENTIAL': { uz: 'Daromad salohiyati', ru: 'Потенциал дохода' },
  'ANNUAL POTENTIAL': { uz: 'Yillik salohiyat', ru: 'Годовой потенциал' },
  'ANNUAL RENTAL INCOME': { uz: 'Yillik ijara daromadi', ru: 'Годовой арендный доход' },
  'ANNUAL RENTAL POTENTIAL': { uz: 'Yillik ijara salohiyati', ru: 'Годовой арендный потенциал' },
  'MONTHLY RENTAL POTENTIAL': { uz: 'Oylik ijara salohiyati', ru: 'Месячный арендный потенциал' },
  'STABLE RENTAL INCOME': { uz: 'Barqaror ijara daromadi', ru: 'Стабильный арендный доход' },
  'STABLE REVENUE STREAM': { uz: 'Barqaror daromad oqimi', ru: 'Стабильный денежный поток' },
  'DIVERSIFIED INCOME': { uz: 'Diversifikatsiyalangan daromad', ru: 'Диверсифицированный доход' },
  'TWO REVENUE STREAMS': { uz: 'Ikkita daromad manbasi', ru: 'Два источника дохода' },
  'PREDICTABLE DAILY INCOME': {
    uz: 'Bashorat qilinadigan kunlik daromad',
    ru: 'Предсказуемый ежедневный доход',
  },
  'STRONG EQUITY': { uz: 'Kuchli oʻz kapitali', ru: 'Сильный собственный капитал' },
  'OWN FINANCING': { uz: 'Oʻz mablagʻlari', ru: 'Собственное финансирование' },
  'UPSIDE POTENTIAL': { uz: 'Oʻsish salohiyati', ru: 'Потенциал роста' },
  'CAPITALIZATION UPSIDE': { uz: 'Kapitallashuv oʻsishi', ru: 'Рост капитализации' },

  /* --- the arguments the sheets make ---------------------------------- */
  'PHASED DEVELOPMENT': { uz: 'Bosqichma-bosqich rivojlantirish', ru: 'Поэтапное развитие' },
  'SCALABLE MODEL': { uz: 'Kengaytiriladigan model', ru: 'Масштабируемая модель' },
  'FLEXIBLE MODELS': { uz: 'Moslashuvchan modellar', ru: 'Гибкие модели' },
  'QUICK IMPLEMENTATION': { uz: 'Tez amalga oshirish', ru: 'Быстрая реализация' },
  'NEAR-TURNKEY': { uz: 'Deyarli tayyor', ru: 'Почти под ключ' },
  'NO ADDITIONAL CAPEX': { uz: 'Qoʻshimcha kapital xarajatsiz', ru: 'Без дополнительных капзатрат' },
  'FULLY EQUIPPED': { uz: 'Toʻliq jihozlangan', ru: 'Полностью оснащён' },
  'FULL SERVICE': { uz: 'Toʻliq xizmat', ru: 'Полный сервис' },
  'LIMITED SUPPLY': { uz: 'Taklif cheklangan', ru: 'Ограниченное предложение' },
  'PROVEN DEMAND': { uz: 'Tasdiqlangan talab', ru: 'Подтверждённый спрос' },
  'STRONG DEMAND': { uz: 'Yuqori talab', ru: 'Высокий спрос' },
  'STRONG VISITOR TRAFFIC': { uz: 'Katta tashrifchi oqimi', ru: 'Высокий поток посетителей' },
  'HIGH VISITOR POTENTIAL': {
    uz: 'Yuqori tashrifchi salohiyati',
    ru: 'Высокий потенциал посещаемости',
  },
  'LONGER STAYS': { uz: 'Uzoqroq qolish', ru: 'Более длительное пребывание' },
  'YEAR-ROUND LEISURE': { uz: 'Yil boʻyi dam olish', ru: 'Круглогодичный отдых' },
  'FAMILY LEISURE': { uz: 'Oilaviy dam olish', ru: 'Семейный отдых' },
  'PREMIUM POSITIONING': { uz: 'Premium pozitsiyalash', ru: 'Премиальное позиционирование' },
  'AUTHENTIC DESIGN': { uz: 'Oʻziga xos dizayn', ru: 'Аутентичный дизайн' },
  'HALAL CERTIFIED': { uz: 'Halol sertifikatiga ega', ru: 'Сертификат «халяль»' },

  /* --- one-offs that are visible enough to be worth naming -------------- */
  'ROLLER TRACK': { uz: 'Rolikli yoʻlak', ru: 'Роллерная трасса' },
  'LIQUID FORMAT': { uz: 'Likvid format', ru: 'Ликвидный формат' },
  'APPROX. LOCATION / MASTER PLAN': {
    uz: 'Taxminiy joylashuv / bosh reja',
    ru: 'Примерное расположение / мастер-план',
  },
  'HOTEL COMPONENT': { uz: 'Mehmonxona qismi', ru: 'Гостиничная часть' },
  'HOTEL COMPONENT ACQUISITION': {
    uz: 'Mehmonxona qismini sotib olish',
    ru: 'Приобретение гостиничной части',
  },

  /* --- the City of Tashkent's own proposal deck ------------------------
     Its rows are not the one-pager template's; these are the labels that
     deck uses, in the English the portfolio stores them under. */
  'VALUE PER PROJECT': { uz: 'Har bir loyiha qiymati', ru: 'Стоимость одного проекта' },
  'JOBS CREATED': { uz: 'Yaratiladigan ish oʻrinlari', ru: 'Создаваемые рабочие места' },
  'TIME TO LAUNCH': { uz: 'Ishga tushish muddati', ru: 'Срок запуска' },
  'STREETS PROPOSED': { uz: 'Taklif etilayotgan koʻchalar', ru: 'Предлагаемые улицы' },
  'ANNUAL TURNOVER': { uz: 'Yillik aylanma', ru: 'Годовой оборот' },
  'PROPOSED LOCATION': { uz: 'Tavsiya etiladigan hudud', ru: 'Рекомендуемая территория' },
  'CAPACITY BY 2030': { uz: '2030-yildagi quvvati', ru: 'Мощность к 2030 году' },
  'ROUTE LENGTH': { uz: 'Marshrut uzunligi', ru: 'Длина маршрута' },
  'ANNUAL VISITORS': { uz: 'Yillik tashrif buyuruvchilar', ru: 'Посетителей в год' },
  'ANNUAL REVENUE POTENTIAL': { uz: 'Yillik tushum salohiyati', ru: 'Потенциал годовой выручки' },
  PARTNERS: { uz: 'Asosiy hamkorlar', ru: 'Основные партнёры' },
  TRAINEES: { uz: 'Asosiy tinglovchilar', ru: 'Основные слушатели' },
  'TRAINING MODEL': { uz: 'Taʼlim modeli', ru: 'Модель обучения' },
  STRUCTURE: { uz: 'Tuzilmasi', ru: 'Структура' },
};

/** `Land area` and `LAND AREA` are the same field in two templates. */
const key = (label: string) => label.replace(/\s+/g, ' ').trim().toUpperCase();

/**
 * The field name in the reader's language, or the sheet's own wording when
 * this is a label only one project ever used.
 */
export function sheetLabel(label: string, lang: Lang): string {
  if (lang === 'en') return label;
  return DICTIONARY[key(label)]?.[lang] ?? label;
}

/** How much of a project's rows this dictionary can actually name. Used by QA. */
export const KNOWN_LABELS = Object.keys(DICTIONARY);
