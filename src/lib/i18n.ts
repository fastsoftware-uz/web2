/**
 * Interface copy for site 2. Chapter titles and narration live here; every
 * figure, name, theme and project still comes from the data files.
 *
 * The voice is deliberately different from site 1: this page is a narrative,
 * so the copy is scene-setting rather than procedural. Where the organiser's
 * own documents have a phrase for something, that phrase is used — the forum
 * motto and the session working titles are quoted, not paraphrased.
 */
import { createI18n } from 'vue-i18n';
import type { Lang } from './shared/content-types';

/**
 * The head of the document, filled in from the data.
 *
 * `{dates}`, like `{count}` and `{regions}`, is a hole the store fills: the
 * forum's dates are the organiser's to change from the admin, and a title
 * that still said November after they moved it would be the one line of the
 * page that lies. The default these templates fall back to before the data
 * arrives is the one written into index.html — 25–27 November 2026.
 */
export const UI_META: Record<Lang, { title: string; description: string }> = {
  uz: {
    title: 'Tourism Investment Forum 2026 — Toshkent, {dates}',
    description:
      'Birinchi xalqaro turizm investitsiya forumi. Oʻzbekiston Respublikasi Turizm qoʻmitasi. {regions} ta hududdan {count} ta investitsiya loyihasi, olti yoʻnalish va bir kunlik ishbilarmonlik dasturi.',
  },
  ru: {
    title: 'Tourism Investment Forum 2026 — Ташкент, {dates}',
    description:
      'Первый международный инвестиционный форум в сфере туризма. Комитет по туризму Республики Узбекистан. Инвестиционных проектов — {count}, регионов — {regions}; шесть направлений и однодневная деловая программа.',
  },
  en: {
    title: 'Tourism Investment Forum 2026 — Tashkent, {dates}',
    description:
      'The First International Tourism Investment Forum, convened by the Tourism Committee of the Republic of Uzbekistan. {count} investment projects from {regions} regions, six thematic directions and a one-day business programme.',
  },
};

/**
 * The dates the title falls back to until the data file has arrived — the
 * same ones index.html was built with, so the head does not flicker on a
 * site nobody has moved. The live value is `event.dateStart` and
 * `event.dateEnd`, which the admin edits; this is only what stands in for
 * the few hundred milliseconds before the fetch lands.
 */
export const DEFAULT_DATES: Record<Lang, string> = {
  uz: '25–27 noyabr',
  ru: '25–27 ноября',
  en: '25–27 November',
};

const en = {
  skip: 'Skip to the chapters',
  menu: {
    open: 'Menu',
    close: 'Close',
    label: 'Chapters',
    // The rail in the margin is a second list of the same chapters, so it
    // needs a name of its own: two navigation landmarks called "Chapters"
    // are indistinguishable when a screen reader lists them.
    rail: 'Page chapters',
    sections: 'Sections',
    language: 'Language',
  },
  nav: {
    why: 'Why Uzbekistan',
    themes: 'Directions',
    projects: 'Projects',
    programme: 'Programme',
    contacts: 'Contact',
    ctaProjects: 'Projects',
    register: 'Register',
  },
  progress: 'Reading progress',
  scrollHint: 'Scroll',
  state: {
    loading: 'Loading…',
    errorTitle: 'The data did not load',
    errorBody: 'Every figure on this page comes from the forum data files, and they could not be read.',
    retry: 'Try again',
  },
  chapters: {
    prologue: { n: 'The forum', title: 'Tourism Investment Forum 2026' },
    crossroads: { n: 'Chapter I', title: 'The Silk Road still runs through here' },
    momentum: { n: 'Chapter II', title: 'The ground it stands on' },
    themes: { n: 'Chapter III', title: 'Six directions' },
    portfolio: { n: 'Chapter IV', title: 'The project portfolio' },
    programme: { n: 'Chapter V', title: 'Agenda' },
    path: { n: 'Chapter VI', title: 'From a proposal to a project' },
    epilogue: { n: 'Epilogue', title: 'Registration' },
  },
  prologue: {
    organiser: 'Tourism Committee of the Republic of Uzbekistan',
    cta: 'The project portfolio',
    ctaUnit: 'projects',
  },
  crossroads: {
    lead: 'Two thousand years of travellers, and the roads are still here.',
    body: 'Samarkand, Bukhara, Khiva and Shakhrisabz sit on a UNESCO list; the mountains, the deserts and the reservoirs around them have barely been built on. That gap between what the country has and what has been built is the whole investment case.',
    europe: 'Europe',
    asia: 'Asia',
  },
  themes: {
    filter: 'Show the projects',
    projectsIn: 'no projects | 1 project | {count} projects',
  },
  portfolio: {
    lead: '{count} projects, gathered from {regions} regions.',
    leadSplit:
      '{placed} projects from {regions} regions, and {nationwide} more drawn for the country as a whole.',
    searchLabel: 'Search the portfolio',
    searchPlaceholder: 'Name, place or activity…',
    allRegions: 'Every region',
    allSegments: 'Every direction',
    sortBy: 'Sort',
    sortLargest: 'Largest first',
    sortSmallest: 'Smallest first',
    sortRegion: 'By region',
    sortName: 'By name',
    results: 'no projects | 1 project | {count} projects',
    clear: 'Clear filters',
    empty: 'Nothing matches that. Try a broader search.',
    totalLabel: 'Investment sought, stated in USD',
    projectsLabel: 'Projects in the portfolio',
    regionsLabel: 'Regions represented',
    close: 'Close',
    open: 'Open',
    prev: 'Previous project',
    next: 'Next project',
    enquire: 'Register',
    pdf: 'PDF',
    listPdf: 'The list as a PDF',
    /* The printed sheet opens and closes on pages of its own — see
       PrintCover.vue and PrintClosing.vue. */
    coverKicker: 'Investment proposal',
    contactsTitle: 'Contacts',
    contactsLead:
      'This sheet is one project out of the forum portfolio. For the rest of it, for a site visit, or to be put in touch with the party holding the land, write to the organising committee.',
    docCols: { project: 'Project', region: 'Region', direction: 'Direction' },
    /* The address is written {'@'} because a bare @ starts a linked
       message in vue-i18n's syntax and the sheet then failed to render. */
    pdfFooter:
      'First International Tourism Investment Forum 2026, Tashkent. Tourism Committee of the Republic of Uzbekistan — investforum{\'@\'}uzbektourism.uz',
    noFigure: 'Not stated',
    byAgreement: 'By agreement',
    map: {
      caption: 'Fourteen regions; the number on each is how many projects it brought. Choose one.',
      none: 'no projects yet',
      back: 'All regions',
      directions: 'Directions',
      top: 'Largest projects here',
      seeAll: 'All projects in this region',
      nationwide:
        'And {count} not filed under any one region: a hotel network drawn for every region and the capital.',
    },
    byDirection: 'By direction',
    bySize: 'By investment sought',
    bands: {
      lt1: 'Under 1 M USD',
      m1to10: '1–10 M USD',
      m10to50: '10–50 M USD',
      gte50: '50 M USD and over',
      open: 'Not stated in USD',
    },
    photoAlt: 'A concept view of the {title} project, {region}',
    photoAltNoRegion: 'A concept view of the {title} project',
    photoAltMore: 'A concept view of the {title} project, {region} — image {n} of {total}',
    gallery: 'Project visuals',
    labels: { investment: 'Investment', overview: 'Overview' },
    blocks: {
      concept: 'What it is',
      opportunity: 'Investment opportunity',
      whyInvest: 'Why invest',
      place: 'Location and access',
    },
    asStated: 'As written in the project sheet: {raw}',
  },
  programme: {
    pickLabel: 'The three days of the fair',
    dayN: 'Day {n}',
    forumDay: 'Investment forum',
    hall: 'Conference hall',
    zone: 'Zone',
    hallMeta: '{area} m² · {seats} seats',
    hallArea: '{area} m²',
    kinds: {
      break: 'Break',
      opening: 'Opening',
      sessionN: 'Session {n}',
      session: 'Session',
      forum: 'Forum',
      commission: 'Joint commission',
      award: 'Awards',
      closing: 'Closing',
      hackathon: 'Hackathon',
      b2b: 'B2B',
    },
  },
  /**
   * The parts of a printed project sheet that are not the project — the map
   * on its page and the forum and portfolio on the closing page. Only on
   * paper, so none of it appears anywhere on the screen.
   */
  print: {
    aboutTitle: 'About the forum',
    when: 'When and where',
    within: 'Held within',
    organiser: 'Organised by',
    languages: 'Working languages',
    languagesValue: 'English, with translation into Uzbek and Russian',
    portfolioTitle: 'The portfolio in numbers',
    nProjects: 'projects',
    nRegions: 'regions',
    nDirections: 'directions',
    nTotal: 'stated in US dollars',
    whereTitle: 'Where it is',
    sizeTitle: 'What this project costs, against the rest',
    sizeAxis: 'projects',
    sizeNote:
      '{usd} of the sheets state a sum in US dollars and are counted here; {uzs} state one in soums and {open} leave it to be agreed.',
    regionWhere: 'Region',
    regionSalary: 'Average pay, thousand soums',
    regionProjects: 'Projects at the forum',
    byDirection: 'By direction',
    mapCredit:
      'Region outlines: geoBoundaries (ODbL 1.0). A diagram of administrative areas, not a survey.',
    contextNote:
      'Counted from the organiser’s own project sheets. Ask the organising committee for any of the others, or for the whole portfolio as one document.',
  },
  /**
   * The three statistics pages (PrintStats.vue).
   *
   * Headings and units only: every figure on those pages comes out of
   * content.json, so there is nothing here to keep in step with it.
   */
  pstats: {
    tourismEyebrow: 'The market',
    tourismTitle: 'The visitors Uzbekistan is already receiving',
    tourismLead: 'The forum’s own market, in the state’s latest counts.',
    kpiArrivals2025: 'foreign visitors in 2025',
    kpiGrowth2025: 'against 2024',
    kpiArrivals2026: 'January–August 2026',
    kpiGrowth2026: 'against the same months of 2025',
    marketsTitle: 'Where the visitors come from',
    series12: '{year} · 12 months',
    series8: '{year} · 8 months',
    exportTitle: 'Where tourism sits in the export of services',
    exportOther: 'Other services',
    exportLine:
      'Services exports: {total} — {share} of all exports, {growth} up on the same months a year earlier.',
    tourismTargetsTitle: 'What the 2030 strategy sets for tourism',
    goalNo: 'goal',
    economyEyebrow: 'The country',
    countryTitle: 'The country, and the people in it',
    countryLead:
      'The official indicators for the year the forum is held in, and what an hour of work costs region by region.',
    gdpTitle: 'What the economy is made of',
    peopleEyebrow: 'Human capital',
    salaryTitle: 'Average monthly pay, by region',
    salaryUnit: 'UZS thousand per month',
    salaryAverage: 'National average',
    salaryHere: 'The marked row is the region this project is in.',
    offerEyebrow: 'The investment climate',
    offerTitle: 'What an investor is offered for coming',
    offerLead: 'The zones, the reliefs, and who walks the investor through them.',
    zonesTitle: 'The five kinds of special economic zone',
    incentivesTitle: 'What a resident of a zone is granted',
    legalBasis: 'Legal basis',
    sectorsTitle: 'The branches the state names as priorities',
    journeyTitle: 'From the first enquiry to a running project',
  },
  path: {
    criteriaTitle: 'How projects are chosen',
    stagesTitle: 'How the forum itself was built',
    goalsTitle: 'What the forum sets out to do',
  },
  epilogue: {
    outcomesTitle: 'What the forum expects to come out of it',
    outcomesQuantitative: 'In numbers',
    outcomesQualitative: 'In substance',
    participantsTitle: 'Who is in the room',
  },
  contact: {
    phone: 'Phone',
    email: 'Email',
    address: 'Address',
    map: 'Open in Maps',
    coords: 'Coordinates:',
    shortPhone: 'or {n} from inside Uzbekistan',
    mapTitle: 'The organising committee on the map',
    mapCredit: 'Map data © OpenStreetMap contributors',
    organiser: 'Organising committee',
    agency: 'Investment Promotion Agency',
  },
  /**
   * What is left of what used to be a Sources block.
   *
   * The client took the source list, the official links and the concept/demo
   * notice off on 17 October 2026 — this is the organiser's own site, not a
   * rendering of one. These three are not citations: who supplied the
   * projects, which language the long descriptions are in, and the map data's
   * licence condition, which is not ours to drop.
   */
  credits: {
    contributedBy: 'The portfolio was assembled from proposals by',
    mapData: 'Region outlines and the title card globe: geoBoundaries (ODbL 1.0) and Natural Earth (public domain). A diagram of administrative areas, not a survey.',
  },
};

type Messages = typeof en;

const uz: Messages = {
  skip: 'Boblarga oʻtish',
  menu: {
    open: 'Menyu',
    close: 'Yopish',
    label: 'Boblar',
    rail: 'Sahifa boblari',
    sections: 'Boʻlimlar',
    language: 'Til',
  },
  nav: {
    why: 'Nega Oʻzbekiston',
    themes: 'Yoʻnalishlar',
    projects: 'Loyihalar',
    programme: 'Dastur',
    contacts: 'Aloqa',
    ctaProjects: 'Loyihalar',
    register: 'Roʻyxatdan oʻtish',
  },
  progress: 'Oʻqish jarayoni',
  scrollHint: 'Pastga',
  state: {
    loading: 'Yuklanmoqda…',
    errorTitle: 'Maʼlumot yuklanmadi',
    errorBody: 'Sahifadagi har bir raqam forum maʼlumot fayllaridan olinadi — ular oʻqilmadi.',
    retry: 'Qayta urinish',
  },
  chapters: {
    prologue: { n: 'Forum', title: 'Tourism Investment Forum 2026' },
    crossroads: { n: 'I bob', title: 'Ipak yoʻli hamon shu yerdan oʻtadi' },
    momentum: { n: 'II bob', title: 'Qaysi zamin ustida turibdi' },
    themes: { n: 'III bob', title: 'Olti yoʻnalish' },
    portfolio: { n: 'IV bob', title: 'Loyihalar portfeli' },
    programme: { n: 'V bob', title: 'Kun tartibi' },
    path: { n: 'VI bob', title: 'Takliftan loyihagacha' },
    epilogue: { n: 'Epilog', title: 'Roʻyxatdan oʻtish' },
  },
  prologue: {
    organiser: 'Oʻzbekiston Respublikasi Turizm qoʻmitasi',
    cta: 'Loyihalar portfeli',
    ctaUnit: 'ta loyiha',
  },
  crossroads: {
    lead: 'Ikki ming yillik yoʻlovchilar — yoʻllar esa hamon shu yerda.',
    body: 'Samarqand, Buxoro, Xiva va Shahrisabz YUNESKO roʻyxatida; ularni oʻrab turgan togʻlar, choʻllar va suv omborlarida esa deyarli hech narsa qurilmagan. Mamlakatda bori bilan qurilgani oʻrtasidagi shu farq — investitsiya uchun asosiy dalil.',
    europe: 'Yevropa',
    asia: 'Osiyo',
  },
  themes: {
    filter: 'Loyihalarni koʻrsatish',
    projectsIn: 'loyiha yoʻq | 1 ta loyiha | {count} ta loyiha',
  },
  portfolio: {
    lead: '{regions} ta hududdan yigʻilgan {count} ta loyiha.',
    leadSplit:
      '{regions} ta hududdan {placed} ta loyiha, va butun mamlakat uchun yana {nationwide} tasi.',
    searchLabel: 'Portfeldan qidirish',
    searchPlaceholder: 'Nomi, joyi yoki faoliyati…',
    allRegions: 'Barcha hududlar',
    allSegments: 'Barcha yoʻnalishlar',
    sortBy: 'Saralash',
    sortLargest: 'Avval kattasi',
    sortSmallest: 'Avval kichigi',
    sortRegion: 'Hudud boʻyicha',
    sortName: 'Nomi boʻyicha',
    results: 'loyiha yoʻq | 1 ta loyiha | {count} ta loyiha',
    clear: 'Filtrlarni tozalash',
    empty: 'Mos keladigani topilmadi. Qidiruvni kengaytiring.',
    totalLabel: 'AQSh dollarida koʻrsatilgan investitsiya talabi',
    projectsLabel: 'Portfeldagi loyihalar',
    regionsLabel: 'Ishtirok etayotgan hududlar',
    close: 'Yopish',
    open: 'Ochish',
    prev: 'Oldingi loyiha',
    next: 'Keyingi loyiha',
    enquire: 'Roʻyxatdan oʻtish',
    pdf: 'PDF',
    listPdf: 'Roʻyxat — PDF',
    coverKicker: 'Investitsiya taklifi',
    contactsTitle: 'Aloqa',
    contactsLead:
      'Bu varaq — forum portfelidagi bitta loyiha. Portfelning qolgan qismi, obyektni joyida koʻrish yoki yer egasi bilan bogʻlanish uchun tashkiliy qoʻmitaga murojaat qiling.',
    docCols: { project: 'Loyiha', region: 'Hudud', direction: 'Yoʻnalish' },
    pdfFooter:
      'Birinchi xalqaro turizm investitsiya forumi 2026, Toshkent. Oʻzbekiston Respublikasi Turizm qoʻmitasi — investforum{\'@\'}uzbektourism.uz',
    noFigure: 'Koʻrsatilmagan',
    byAgreement: 'Kelishuv asosida',
    map: {
      caption: 'Oʻn toʻrt hudud; har biridagi raqam — oʻsha hudud taqdim etgan loyihalar soni. Birini tanlang.',
      none: 'hozircha loyiha yoʻq',
      back: 'Barcha hududlar',
      directions: 'Yoʻnalishlar',
      top: 'Shu hududdagi eng yirik loyihalar',
      seeAll: 'Hududning barcha loyihalari',
      nationwide:
        'Yana {count} tasi biror hududga biriktirilmagan: har bir viloyat va poytaxt uchun chizilgan mehmonxona tarmogʻi.',
    },
    byDirection: 'Yoʻnalishlar boʻyicha',
    bySize: 'Investitsiya hajmi boʻyicha',
    bands: {
      lt1: '1 mln USD gacha',
      m1to10: '1–10 mln USD',
      m10to50: '10–50 mln USD',
      gte50: '50 mln USD va undan koʻp',
      open: 'Dollarda koʻrsatilmagan',
    },
    photoAlt: '{title} loyihasining konseptual koʻrinishi, {region}',
    photoAltNoRegion: '{title} loyihasining konseptual koʻrinishi',
    photoAltMore: '{title} loyihasining konseptual koʻrinishi, {region} — {total} tadan {n}-rasm',
    gallery: 'Loyiha vizualizatsiyalari',
    labels: { investment: 'Investitsiya', overview: 'Loyiha haqida' },
    blocks: {
      concept: 'Nimadan iborat',
      opportunity: 'Investitsiya sharoitlari',
      whyInvest: 'Nega aynan shu loyiha',
      place: 'Joylashuv va yoʻllar',
    },
    asStated: 'Loyiha varaqasida shunday yozilgan: {raw}',
  },
  programme: {
    pickLabel: 'Yarmarkaning uch kuni',
    dayN: '{n}-kun',
    forumDay: 'Investitsiya forumi',
    hall: 'Konferensiya zali',
    zone: 'Zona',
    hallMeta: '{area} m² · {seats} kishi',
    hallArea: '{area} m²',
    kinds: {
      break: 'Tanaffus',
      opening: 'Ochilish',
      sessionN: '{n}-sessiya',
      session: 'Sessiya',
      forum: 'Forum',
      commission: 'Qoʻshma komissiya',
      award: 'Mukofotlar',
      closing: 'Yakun',
      hackathon: 'Xakaton',
      b2b: 'B2B',
    },
  },
  print: {
    aboutTitle: 'Forum haqida',
    when: 'Qachon va qayerda',
    within: 'Doirasida',
    organiser: 'Tashkilotchi',
    languages: 'Ish tillari',
    languagesValue: 'Ingliz tili, oʻzbek va rus tillariga tarjima bilan',
    portfolioTitle: 'Portfel raqamlarda',
    nProjects: 'loyiha',
    nRegions: 'hudud',
    nDirections: 'yoʻnalish',
    nTotal: 'AQSH dollarida koʻrsatilgan',
    whereTitle: 'Qayerda joylashgan',
    sizeTitle: 'Bu loyiha qolganlarga nisbatan qanchaga turadi',
    sizeAxis: 'loyiha',
    sizeNote:
      'Varaqalarning {usd} tasida summa AQSH dollarida koʻrsatilgan — diagrammada shular; {uzs} tasida soʻmda, {open} tasida kelishuv asosida.',
    regionWhere: 'Hudud',
    regionSalary: 'Oʻrtacha ish haqi, ming soʻm',
    regionProjects: 'Forumdagi loyihalar',
    byDirection: 'Yoʻnalishlar boʻyicha',
    mapCredit:
      'Hudud konturlari: geoBoundaries (ODbL 1.0). Bu — maʼmuriy hududlar sxemasi, oʻlchov xaritasi emas.',
    contextNote:
      'Tashkilotchining loyiha varaqalaridan sanab chiqildi. Qolganlarini yoki butun portfelni bitta hujjat holida tashkiliy qoʻmitadan soʻrang.',
  },
  pstats: {
    tourismEyebrow: 'Bozor',
    tourismTitle: 'Oʻzbekiston allaqachon qabul qilayotgan sayyohlar',
    tourismLead: 'Forumning oʻz bozori — davlatning soʻnggi hisob-kitoblarida.',
    kpiArrivals2025: '2025-yilda kelgan xorijiy sayyohlar',
    kpiGrowth2025: '2024-yilga nisbatan',
    kpiArrivals2026: '2026-yil yanvar–avgust',
    kpiGrowth2026: '2025-yilning shu oylariga nisbatan',
    marketsTitle: 'Sayyohlar qaysi davlatlardan kelyapti',
    series12: '{year} · 12 oy',
    series8: '{year} · 8 oy',
    exportTitle: 'Xizmatlar eksportida turizmning oʻrni',
    exportOther: 'Boshqa xizmatlar',
    exportLine:
      'Xizmatlar eksporti: {total} — umumiy eksportning {share} i, bir yil avvalgi shu oylarga nisbatan {growth} koʻp.',
    tourismTargetsTitle: '2030 strategiyasi turizm oldiga nima qoʻygan',
    goalNo: 'maqsad',
    economyEyebrow: 'Mamlakat',
    countryTitle: 'Mamlakat va undagi odamlar',
    countryLead:
      'Forum oʻtadigan yilning rasmiy koʻrsatkichlari va hududlar kesimida mehnat qancha turishi.',
    gdpTitle: 'Iqtisodiyot nimalardan tashkil topgan',
    peopleEyebrow: 'Inson kapitali',
    salaryTitle: 'Hududlar kesimida oʻrtacha oylik ish haqi',
    salaryUnit: 'ming soʻm / oyiga',
    salaryAverage: 'Respublika boʻyicha oʻrtacha',
    salaryHere: 'Ajratib koʻrsatilgan qator — shu loyiha joylashgan hudud.',
    offerEyebrow: 'Investitsiya muhiti',
    offerTitle: 'Kelgan investorga nima beriladi',
    offerLead: 'Zonalar, imtiyozlar va investorni ular boʻylab olib oʻtadigan tartib.',
    zonesTitle: 'Maxsus iqtisodiy zonalarning besh turi',
    incentivesTitle: 'Zona rezidentiga beriladigan imtiyozlar',
    legalBasis: 'Huquqiy asos',
    sectorsTitle: 'Davlat ustuvor deb belgilagan tarmoqlar',
    journeyTitle: 'Birinchi murojaatdan ishlayotgan loyihagacha',
  },
  path: {
    criteriaTitle: 'Loyihalar qanday tanlanadi',
    stagesTitle: 'Forumning oʻzi qanday tayyorlandi',
    goalsTitle: 'Forum nimani maqsad qilgan',
  },
  epilogue: {
    outcomesTitle: 'Forumdan nima kutilmoqda',
    outcomesQuantitative: 'Raqamlarda',
    outcomesQualitative: 'Sifat jihatidan',
    participantsTitle: 'Zalda kimlar boʻladi',
  },
  contact: {
    phone: 'Telefon',
    email: 'Elektron pochta',
    address: 'Manzil',
    map: 'Xaritada ochish',
    coords: 'Koordinatalar:',
    shortPhone: 'yoki Oʻzbekiston ichidan {n}',
    mapTitle: 'Tashkiliy qoʻmita xaritada',
    mapCredit: 'Xarita maʼlumotlari © OpenStreetMap ishtirokchilari',
    organiser: 'Tashkiliy qoʻmita',
    agency: 'Investitsiyalarni jalb qilish agentligi',
  },
  credits: {
    contributedBy: 'Portfel quyidagilarning takliflaridan shakllantirilgan',
    mapData: 'Hudud konturlari va sarlavha kartasidagi globus: geoBoundaries (ODbL 1.0) va Natural Earth (umumiy mulk). Bu — maʼmuriy hududlar sxemasi, oʻlchov xaritasi emas.',
  },
};

const ru: Messages = {
  skip: 'Перейти к главам',
  menu: {
    open: 'Меню',
    close: 'Закрыть',
    label: 'Главы',
    rail: 'Главы страницы',
    sections: 'Разделы',
    language: 'Язык',
  },
  nav: {
    why: 'Почему Узбекистан',
    themes: 'Направления',
    projects: 'Проекты',
    programme: 'Программа',
    contacts: 'Контакты',
    ctaProjects: 'Проекты',
    register: 'Регистрация',
  },
  progress: 'Прогресс чтения',
  scrollHint: 'Вниз',
  state: {
    loading: 'Загрузка…',
    errorTitle: 'Данные не загрузились',
    errorBody: 'Все цифры на этой странице берутся из файлов данных форума — прочитать их не удалось.',
    retry: 'Попробовать снова',
  },
  chapters: {
    prologue: { n: 'Форум', title: 'Tourism Investment Forum 2026' },
    crossroads: { n: 'Глава I', title: 'Шёлковый путь по-прежнему проходит здесь' },
    momentum: { n: 'Глава II', title: 'На какой почве он стоит' },
    themes: { n: 'Глава III', title: 'Шесть направлений' },
    portfolio: { n: 'Глава IV', title: 'Портфель проектов' },
    programme: { n: 'Глава V', title: 'Повестка дня' },
    path: { n: 'Глава VI', title: 'От предложения к проекту' },
    epilogue: { n: 'Эпилог', title: 'Регистрация' },
  },
  prologue: {
    organiser: 'Комитет по туризму Республики Узбекистан',
    cta: 'Портфель проектов',
    ctaUnit: 'проектов',
  },
  crossroads: {
    lead: 'Две тысячи лет путников — и дороги всё ещё здесь.',
    body: 'Самарканд, Бухара, Хива и Шахрисабз — в списке ЮНЕСКО; а в горах, пустынях и у водохранилищ вокруг них почти ничего не построено. Разрыв между тем, что у страны есть, и тем, что построено, — и есть главный инвестиционный аргумент.',
    europe: 'Европа',
    asia: 'Азия',
  },
  themes: {
    filter: 'Показать проекты',
    projectsIn: 'нет проектов | {count} проект | {count} проекта | {count} проектов',
  },
  portfolio: {
    lead: 'Проектов — {count}, собраны из {regions} регионов.',
    leadSplit:
      'Проектов: {placed} из {regions} регионов, и ещё {nationwide} для страны в целом.',
    searchLabel: 'Поиск по портфелю',
    searchPlaceholder: 'Название, место или вид деятельности…',
    allRegions: 'Все регионы',
    allSegments: 'Все направления',
    sortBy: 'Сортировка',
    sortLargest: 'Сначала крупные',
    sortSmallest: 'Сначала небольшие',
    sortRegion: 'По регионам',
    sortName: 'По названию',
    results: 'нет проектов | {count} проект | {count} проекта | {count} проектов',
    clear: 'Сбросить фильтры',
    empty: 'Ничего не найдено. Попробуйте расширить запрос.',
    totalLabel: 'Запрашиваемые инвестиции, указанные в долларах США',
    projectsLabel: 'Проектов в портфеле',
    regionsLabel: 'Представленных регионов',
    close: 'Закрыть',
    open: 'Открыть',
    prev: 'Предыдущий проект',
    next: 'Следующий проект',
    enquire: 'Регистрация',
    pdf: 'PDF',
    listPdf: 'Список в PDF',
    coverKicker: 'Инвестиционное предложение',
    contactsTitle: 'Контакты',
    contactsLead:
      'Этот лист — один проект из портфеля форума. За остальной частью портфеля, осмотром объекта или контактом с балансодержателем участка обращайтесь в организационный комитет.',
    docCols: { project: 'Проект', region: 'Регион', direction: 'Направление' },
    pdfFooter:
      'Первый международный инвестиционный форум в сфере туризма 2026, Ташкент. Комитет по туризму Республики Узбекистан — investforum{\'@\'}uzbektourism.uz',
    noFigure: 'Не указано',
    byAgreement: 'По договорённости',
    map: {
      caption: 'Четырнадцать регионов; число на каждом — сколько проектов он представил. Выберите один.',
      none: 'проектов пока нет',
      back: 'Все регионы',
      directions: 'Направления',
      top: 'Крупнейшие проекты здесь',
      seeAll: 'Все проекты региона',
      nationwide:
        'И ещё {count} вне какого-либо одного региона: сеть гостиниц, рассчитанная на каждую область и столицу.',
    },
    byDirection: 'По направлениям',
    bySize: 'По объёму инвестиций',
    bands: {
      lt1: 'до 1 млн USD',
      m1to10: '1–10 млн USD',
      m10to50: '10–50 млн USD',
      gte50: 'от 50 млн USD',
      open: 'не указано в долларах',
    },
    photoAlt: 'Концептуальный вид проекта «{title}», {region}',
    photoAltNoRegion: 'Концептуальный вид проекта «{title}»',
    photoAltMore: 'Концептуальный вид проекта «{title}», {region} — изображение {n} из {total}',
    gallery: 'Визуализации проекта',
    labels: { investment: 'Инвестиции', overview: 'О проекте' },
    blocks: {
      concept: 'Из чего состоит',
      opportunity: 'Условия инвестирования',
      whyInvest: 'Почему этот проект',
      place: 'Расположение и доступность',
    },
    asStated: 'В проектном листе записано так: {raw}',
  },
  programme: {
    pickLabel: 'Три дня ярмарки',
    dayN: '{n}-й день',
    forumDay: 'Инвестиционный форум',
    hall: 'Конференц-зал',
    zone: 'Зона',
    hallMeta: '{area} м² · {seats} мест',
    hallArea: '{area} м²',
    kinds: {
      break: 'Перерыв',
      opening: 'Открытие',
      sessionN: 'Сессия {n}',
      session: 'Сессия',
      forum: 'Форум',
      commission: 'Совместная комиссия',
      award: 'Награды',
      closing: 'Закрытие',
      hackathon: 'Хакатон',
      b2b: 'B2B',
    },
  },
  print: {
    aboutTitle: 'О форуме',
    when: 'Когда и где',
    within: 'В рамках',
    organiser: 'Организатор',
    languages: 'Рабочие языки',
    languagesValue: 'Английский, с переводом на узбекский и русский',
    portfolioTitle: 'Портфель в цифрах',
    nProjects: 'проектов',
    nRegions: 'регионов',
    nDirections: 'направлений',
    nTotal: 'указано в долларах США',
    whereTitle: 'Где находится',
    sizeTitle: 'Сколько стоит этот проект по сравнению с остальными',
    sizeAxis: 'проектов',
    sizeNote:
      'В {usd} листах сумма указана в долларах США — они и на диаграмме; в {uzs} — в сумах, в {open} — по договорённости.',
    regionWhere: 'Регион',
    regionSalary: 'Средняя зарплата, тыс. сумов',
    regionProjects: 'Проектов на форуме',
    byDirection: 'По направлениям',
    mapCredit:
      'Контуры регионов: geoBoundaries (ODbL 1.0). Это схема административных границ, а не съёмка.',
    contextNote:
      'Подсчитано по проектным листам организатора. Остальные проекты или весь портфель одним документом запрашивайте в оргкомитете.',
  },
  pstats: {
    tourismEyebrow: 'Рынок',
    tourismTitle: 'Туристы, которых Узбекистан принимает уже сейчас',
    tourismLead: 'Собственный рынок форума — в последних подсчётах государства.',
    kpiArrivals2025: 'иностранных туристов в 2025 году',
    kpiGrowth2025: 'к 2024 году',
    kpiArrivals2026: 'январь–август 2026',
    kpiGrowth2026: 'к тем же месяцам 2025 года',
    marketsTitle: 'Из каких стран приезжают туристы',
    series12: '{year} · 12 месяцев',
    series8: '{year} · 8 месяцев',
    exportTitle: 'Место туризма в экспорте услуг',
    exportOther: 'Прочие услуги',
    exportLine:
      'Экспорт услуг: {total} — {share} всего экспорта, на {growth} больше, чем за те же месяцы годом ранее.',
    tourismTargetsTitle: 'Что стратегия-2030 ставит перед туризмом',
    goalNo: 'цель',
    economyEyebrow: 'Страна',
    countryTitle: 'Страна и люди в ней',
    countryLead:
      'Официальные показатели года, в котором проходит форум, и сколько стоит труд по регионам.',
    gdpTitle: 'Из чего состоит экономика',
    peopleEyebrow: 'Человеческий капитал',
    salaryTitle: 'Средняя месячная зарплата по регионам',
    salaryUnit: 'тыс. сумов в месяц',
    salaryAverage: 'В среднем по стране',
    salaryHere: 'Выделенная строка — регион, в котором находится этот проект.',
    offerEyebrow: 'Инвестиционный климат',
    offerTitle: 'Что получает пришедший инвестор',
    offerLead: 'Зоны, льготы и тот, кто проводит инвестора через них.',
    zonesTitle: 'Пять видов специальных экономических зон',
    incentivesTitle: 'Что получает резидент зоны',
    legalBasis: 'Правовая основа',
    sectorsTitle: 'Отрасли, названные государством приоритетными',
    journeyTitle: 'От первого обращения до работающего проекта',
  },
  path: {
    criteriaTitle: 'Как отбираются проекты',
    stagesTitle: 'Как готовился сам форум',
    goalsTitle: 'Чего форум хочет добиться',
  },
  epilogue: {
    outcomesTitle: 'Чего ждут от форума',
    outcomesQuantitative: 'В цифрах',
    outcomesQualitative: 'По существу',
    participantsTitle: 'Кто будет в зале',
  },
  contact: {
    phone: 'Телефон',
    email: 'Эл. почта',
    address: 'Адрес',
    map: 'Открыть на карте',
    coords: 'Координаты:',
    shortPhone: 'или {n} внутри Узбекистана',
    mapTitle: 'Организационный комитет на карте',
    mapCredit: 'Данные карты © участники OpenStreetMap',
    organiser: 'Организационный комитет',
    agency: 'Агентство по привлечению инвестиций',
  },
  credits: {
    contributedBy: 'Портфель сформирован из предложений',
    mapData: 'Контуры регионов и глобус на титульной карточке: geoBoundaries (ODbL 1.0) и Natural Earth (общественное достояние). Это схема административных границ, а не топографическая карта.',
  },
};

/**
 * Russian needs four slots where uz and en need three.
 *
 * Message order is: none | one | few (2-4) | many (5-20, and 0-digit endings).
 * Uzbek has no number agreement at all and English has the usual two, so both
 * stay on vue-i18n's default rule.
 */
function russianPlural(choice: number): number {
  if (choice === 0) return 0;
  const hundreds = Math.abs(choice) % 100;
  if (hundreds > 10 && hundreds < 20) return 3;
  const units = hundreds % 10;
  if (units === 1) return 1;
  if (units > 1 && units < 5) return 2;
  return 3;
}

export const i18n = createI18n({
  legacy: false,
  locale: 'uz',
  fallbackLocale: 'en',
  messages: { uz, ru, en },
  pluralRules: { ru: russianPlural },
});
