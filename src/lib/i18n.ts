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
    byRegion: 'By region',
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
    sessionsTitle: 'The three sessions',
    expand: 'Show the topics',
    sessionProgramme: 'The session hour by hour',
    sessionHide: 'Hide the timetable',
    collapse: 'Hide the topics',
    kinds: {
      registration: 'Registration',
      break: 'Break',
      opening: 'Opening',
      speech: 'Address',
      keynote: 'Keynote',
      plenary: 'Plenary {n}',
      parallel: 'In parallel',
      showcase: 'Showcase',
      b2b: 'B2B',
      closing: 'Closing',
    },
  },
  /**
   * The pages a printed project sheet carries besides the project itself —
   * see PrintBrief.vue and PrintContext.vue. Only on paper, so none of it
   * appears anywhere on the screen.
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
    totalNote:
      'The total adds the projects whose figure is stated in US dollars. Sheets that state a sum in Uzbek soums, or leave it open, are counted but not added.',
    goalsTitle: 'What the forum sets out to do',
    forumTitle: 'What an investment forum in this country draws',
    contextEyebrow: 'Where this project sits',
    contextTitle: 'The rest of the portfolio',
    contextLead:
      'This sheet is one of {n} projects the regions and the private sector brought to the forum. Here is the whole portfolio counted two ways; the row this project belongs to is marked.',
    byRegion: 'By region',
    byDirection: 'By direction',
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
    tourismLead:
      'The forum is about tourism, so the country’s figures start with that market: how many visitors arrive, which countries they arrive from, and what the state has set itself for 2030.',
    kpiArrivals2025: 'foreign visitors in 2025',
    kpiGrowth2025: 'against 2024',
    kpiArrivals2026: 'January–August 2026',
    kpiGrowth2026: 'against the same months of 2025',
    marketsTitle: 'Where the 2025 visitors came from',
    exportTitle: 'Where tourism sits in the export of services',
    exportLine:
      'Services exports: {total} — {share} of all exports, {growth} up on the same months a year earlier.',
    tourismTargetsTitle: 'What the 2030 strategy sets for tourism',
    goalNo: 'goal',
    economyEyebrow: 'The country',
    economyTitle: 'Uzbekistan’s economy in 2025',
    economyLead:
      'A project is worth what the economy around it is worth. These are the official indicators for the year the forum is held in, as the organiser’s own site publishes them.',
    gdpTitle: 'What the economy is made of',
    branchesTitle: 'The largest branches, by share of GDP',
    driversTitle: 'What carries the growth',
    peopleEyebrow: 'Human capital',
    peopleTitle: 'The people who would run it',
    peopleLead:
      'After the money comes the question of who staffs the project and what that costs. The capital pays more than twice what the cheapest region does.',
    ratesTitle: 'The labour market, in rates',
    salaryTitle: 'Average monthly pay, by region',
    salaryUnit: 'UZS thousand per month',
    salaryAverage: 'National average',
    salaryHere: 'The marked row is the region this project is in.',
    futureEyebrow: 'The investment climate',
    futureTitle: 'Where the country says it is going, and what it offers for coming',
    goalsWord: 'goals',
    prioritiesWord: 'priority areas',
    adoptedWord: 'adopted',
    prioritiesTitle: 'The five priorities, and the targets set under them',
    zonesTitle: 'Special economic zones, and what a resident is granted',
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
    byRegion: 'Hududlar boʻyicha',
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
    sessionsTitle: 'Uchta sessiya',
    expand: 'Mavzularni koʻrsatish',
    sessionProgramme: 'Sessiya dasturi',
    sessionHide: 'Dasturni yashirish',
    collapse: 'Mavzularni yashirish',
    kinds: {
      registration: 'Roʻyxatdan oʻtkazish',
      break: 'Tanaffus',
      opening: 'Ochilish',
      speech: 'Chiqish',
      keynote: 'Asosiy maʼruza',
      plenary: '{n}-plenar sessiya',
      parallel: 'Bir vaqtda',
      showcase: 'Taqdimot',
      b2b: 'B2B',
      closing: 'Yakun',
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
    totalNote:
      'Jami — summasi AQSH dollarida koʻrsatilgan loyihalar yigʻindisi. Soʻmda koʻrsatilgan yoki summasi ochiq qoldirilgan varaqalar sanaladi, lekin qoʻshilmaydi.',
    goalsTitle: 'Forum nimaga qaratilgan',
    forumTitle: 'Bu mamlakatdagi investitsiya forumi nimani yigʻadi',
    contextEyebrow: 'Bu loyiha qayerda turibdi',
    contextTitle: 'Portfelning qolgan qismi',
    contextLead:
      'Ushbu varaq — hududlar va xususiy sektor forumga olib chiqqan {n} ta loyihadan biri. Quyida portfel ikki xil sanoqda; bu loyiha tegishli qator ajratib koʻrsatilgan.',
    byRegion: 'Hududlar boʻyicha',
    byDirection: 'Yoʻnalishlar boʻyicha',
    contextNote:
      'Tashkilotchining loyiha varaqalaridan sanab chiqildi. Qolganlarini yoki butun portfelni bitta hujjat holida tashkiliy qoʻmitadan soʻrang.',
  },
  pstats: {
    tourismEyebrow: 'Bozor',
    tourismTitle: 'Oʻzbekiston allaqachon qabul qilayotgan sayyohlar',
    tourismLead:
      'Forum turizmga bagʻishlangan, shuning uchun mamlakat raqamlari shu bozordan boshlanadi: qancha sayyoh kelyapti, qaysi davlatlardan kelyapti va davlat 2030-yilga oʻzi oldiga nima qoʻygan.',
    kpiArrivals2025: '2025-yilda kelgan xorijiy sayyohlar',
    kpiGrowth2025: '2024-yilga nisbatan',
    kpiArrivals2026: '2026-yil yanvar–avgust',
    kpiGrowth2026: '2025-yilning shu oylariga nisbatan',
    marketsTitle: '2025-yilda sayyohlar qaysi davlatlardan kelgan',
    exportTitle: 'Xizmatlar eksportida turizmning oʻrni',
    exportLine:
      'Xizmatlar eksporti: {total} — umumiy eksportning {share} i, bir yil avvalgi shu oylarga nisbatan {growth} koʻp.',
    tourismTargetsTitle: '2030 strategiyasi turizm oldiga nima qoʻygan',
    goalNo: 'maqsad',
    economyEyebrow: 'Mamlakat',
    economyTitle: 'Oʻzbekiston iqtisodiyoti — 2025',
    economyLead:
      'Loyihaning qiymati uni oʻrab turgan iqtisodiyot qiymatidan ortmaydi. Quyida — forum oʻtadigan yilning rasmiy koʻrsatkichlari, tashkilotchining oʻz saytida eʼlon qilingan holida.',
    gdpTitle: 'Iqtisodiyot nimalardan tashkil topgan',
    branchesTitle: 'Eng yirik tarmoqlar, YaIM dagi ulushi boʻyicha',
    driversTitle: 'Oʻsishni nima koʻtarib turibdi',
    peopleEyebrow: 'Inson kapitali',
    peopleTitle: 'Loyihani yuritadigan odamlar',
    peopleLead:
      'Puldan keyingi savol — loyihada kim ishlaydi va bu qancha turadi. Poytaxt eng arzon hududdan ikki barobardan koʻproq toʻlaydi.',
    ratesTitle: 'Mehnat bozori koʻrsatkichlari',
    salaryTitle: 'Hududlar kesimida oʻrtacha oylik ish haqi',
    salaryUnit: 'ming soʻm / oyiga',
    salaryAverage: 'Respublika boʻyicha oʻrtacha',
    salaryHere: 'Ajratib koʻrsatilgan qator — shu loyiha joylashgan hudud.',
    futureEyebrow: 'Investitsiya muhiti',
    futureTitle: 'Mamlakat qayerga boryapti va kelgan investorga nima beradi',
    goalsWord: 'maqsad',
    prioritiesWord: 'ustuvor yoʻnalish',
    adoptedWord: 'qabul qilingan',
    prioritiesTitle: 'Besh ustuvor yoʻnalish va ular ostidagi marralar',
    zonesTitle: 'Maxsus iqtisodiy zonalar va rezidentga beriladigan imtiyozlar',
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
    byRegion: 'По регионам',
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
    sessionsTitle: 'Три сессии',
    expand: 'Показать темы',
    sessionProgramme: 'Программа сессии',
    sessionHide: 'Скрыть программу',
    collapse: 'Скрыть темы',
    kinds: {
      registration: 'Регистрация',
      break: 'Перерыв',
      opening: 'Открытие',
      speech: 'Выступление',
      keynote: 'Ключевое выступление',
      plenary: 'Пленарная сессия {n}',
      parallel: 'Параллельно',
      showcase: 'Презентация',
      b2b: 'B2B',
      closing: 'Закрытие',
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
    totalNote:
      'Итог — сумма проектов, стоимость которых указана в долларах США. Листы со стоимостью в сумах или без неё учтены, но не суммированы.',
    goalsTitle: 'На что направлен форум',
    forumTitle: 'Что собирает инвестиционный форум в этой стране',
    contextEyebrow: 'Где находится этот проект',
    contextTitle: 'Остальная часть портфеля',
    contextLead:
      'Этот лист — один из {n} проектов, которые регионы и частный сектор представили форуму. Ниже весь портфель в двух разрезах; строка, к которой относится проект, выделена.',
    byRegion: 'По регионам',
    byDirection: 'По направлениям',
    contextNote:
      'Подсчитано по проектным листам организатора. Остальные проекты или весь портфель одним документом запрашивайте в оргкомитете.',
  },
  pstats: {
    tourismEyebrow: 'Рынок',
    tourismTitle: 'Туристы, которых Узбекистан принимает уже сейчас',
    tourismLead:
      'Форум посвящён туризму, поэтому цифры страны начинаются с этого рынка: сколько туристов приезжает, откуда и что государство поставило себе на 2030 год.',
    kpiArrivals2025: 'иностранных туристов в 2025 году',
    kpiGrowth2025: 'к 2024 году',
    kpiArrivals2026: 'январь–август 2026',
    kpiGrowth2026: 'к тем же месяцам 2025 года',
    marketsTitle: 'Из каких стран приехали туристы в 2025 году',
    exportTitle: 'Место туризма в экспорте услуг',
    exportLine:
      'Экспорт услуг: {total} — {share} всего экспорта, на {growth} больше, чем за те же месяцы годом ранее.',
    tourismTargetsTitle: 'Что стратегия-2030 ставит перед туризмом',
    goalNo: 'цель',
    economyEyebrow: 'Страна',
    economyTitle: 'Экономика Узбекистана в 2025 году',
    economyLead:
      'Проект стоит ровно столько, сколько стоит экономика вокруг него. Ниже — официальные показатели года, в котором проходит форум, в том виде, в каком их публикует сайт организатора.',
    gdpTitle: 'Из чего состоит экономика',
    branchesTitle: 'Крупнейшие отрасли, по доле в ВВП',
    driversTitle: 'Что обеспечивает рост',
    peopleEyebrow: 'Человеческий капитал',
    peopleTitle: 'Люди, которые будут вести проект',
    peopleLead:
      'Следом за деньгами идёт вопрос, кто будет работать на проекте и сколько это стоит. Столица платит более чем вдвое против самого недорогого региона.',
    ratesTitle: 'Рынок труда в показателях',
    salaryTitle: 'Средняя месячная зарплата по регионам',
    salaryUnit: 'тыс. сумов в месяц',
    salaryAverage: 'В среднем по стране',
    salaryHere: 'Выделенная строка — регион, в котором находится этот проект.',
    futureEyebrow: 'Инвестиционный климат',
    futureTitle: 'Куда идёт страна и что она предлагает пришедшему инвестору',
    goalsWord: 'целей',
    prioritiesWord: 'приоритетных направления',
    adoptedWord: 'принята',
    prioritiesTitle: 'Пять приоритетов и поставленные по ним цели',
    zonesTitle: 'Специальные экономические зоны и льготы для резидента',
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
