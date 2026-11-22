/**
 * Uzbek and Russian for the short, closed vocabularies the project sheets use
 * as values.
 *
 * This is a narrower thing than sheet-labels.ts and it has to be, because a
 * value is a fact and a translated fact is a changed fact. Only two kinds of
 * value are translated here:
 *
 *   the delivery stage — Greenfield, Concept, Construction, Operating and the
 *   dozen others, which are a fixed vocabulary the organiser uses the same way
 *   on every sheet and which carry no project-specific information;
 *
 *   the one-word activity — Gastronomy, Medical tourism — for the same reason.
 *
 * Everything else a sheet states keeps its own words. Anything this file does
 * not recognise is passed through untouched, which is the same rule the label
 * dictionary follows.
 *
 * The stage words are the ones the client listed; the rest were read off the
 * portfolio.
 */
import type { Lang } from './shared/content-types';

const DICTIONARY: Record<string, { uz: string; ru: string }> = {
  /* --- how far along the project is ----------------------------------- */
  GREENFIELD: { uz: 'Yangi qurilish loyihasi', ru: 'Проект нового строительства' },
  BROWNFIELD: { uz: 'Mavjud obyekt negizida', ru: 'На базе существующего объекта' },
  CONCEPT: { uz: 'Konsept loyiha', ru: 'Концепт-проект' },
  'DESIGN CONCEPT': { uz: 'Loyiha konsepsiyasi', ru: 'Проектная концепция' },
  DESIGN: { uz: 'Loyihalash bosqichi', ru: 'Стадия проектирования' },
  CONSTRUCTION: { uz: 'Qurilish jarayonida', ru: 'В стадии строительства' },
  'ADVANCED CONSTRUCTION': { uz: 'Qurilish yakunlanmoqda', ru: 'Завершающая стадия строительства' },
  OPERATING: { uz: 'Faoliyatdagi loyiha', ru: 'Действующий объект' },
  ACQUISITION: { uz: 'Sotib olish', ru: 'Приобретение' },
  LEASED: { uz: 'Ijaraga berilgan', ru: 'Передан в аренду' },
  PARTNERSHIP: { uz: 'Hamkorlik', ru: 'Партнёрство' },
  'PROJECT PROPOSAL': { uz: 'Loyiha taklifi', ru: 'Проектное предложение' },

  /* --- what the project is, in a word --------------------------------- */
  'SPORT TOURISM': { uz: 'Sport turizmi', ru: 'Спортивный туризм' },
  'MEDICAL TOURISM': { uz: 'Tibbiy turizm', ru: 'Медицинский туризм' },
  'ECO-TOURISM CENTER': { uz: 'Ekoturizm markazi', ru: 'Экотуристический центр' },
  'ETHNO HOUSE': { uz: 'Etno-uy', ru: 'Этно-дом' },
  'HANDICRAFT SHOP': { uz: 'Hunarmandchilik doʻkoni', ru: 'Лавка ремёсел' },
  GASTRONOMY: { uz: 'Gastronomiya', ru: 'Гастрономия' },
  'ARCHAEOLOGICAL PARK': { uz: 'Arxeologik bogʻ', ru: 'Археологический парк' },
  'GLEMPING RESORT': { uz: 'Glemping kurorti', ru: 'Глэмпинг-курорт' },
  '4-STAR RESORT HOTEL': { uz: '4 yulduzli kurort mehmonxonasi', ru: '4-звёздочный курортный отель' },
  'INN (CARAVANSERAI)': { uz: 'Karvonsaroy', ru: 'Караван-сарай' },
  HOTEL: { uz: 'Mehmonxona', ru: 'Гостиница' },

  /* --- whether the networks reach the plot ----------------------------- */
  AVAILABLE: { uz: 'Bor', ru: 'Есть' },
  'NOT AVAILABLE': { uz: 'Yoʻq', ru: 'Нет' },
  PARTIAL: { uz: 'Qisman', ru: 'Частично' },

  /* --- Bukhara's sixteen auction lots ----------------------------------
     The deck is bilingual Russian and English on every line, so these are
     its own Russian, not a translation of its English. */
  'AUCTION LOT': { uz: 'Auksion loti', ru: 'Аукционный лот' },
  'MINI-HOTEL': { uz: 'Mini-mehmonxona', ru: 'Мини-отель' },
  '3-STAR HOTEL': { uz: '3 yulduzli mehmonxona', ru: 'Отель 3★' },
  '4-STAR HOTEL': { uz: '4 yulduzli mehmonxona', ru: 'Отель 4★' },
  'CRAFT & RETAIL CENTRE': {
    uz: 'Savdo-hunarmandchilik markazi',
    ru: 'Торгово-ремесленный центр',
  },
  'GOLF COURSE': { uz: 'Golf maydoni', ru: 'Поле для гольфа' },
  'BUKHARA TOURISM CENTRE': { uz: 'Buxoro turizm markazi', ru: 'Туристический центр' },
  '“BOQIY BUXORO” CULTURAL PARK': {
    uz: '“Boqiy Buxoro” madaniy parki',
    ru: 'Культурный парк «Боқий Бухоро»',
  },
  'BUKHARA TOURISM CLUSTER': { uz: 'Buxoro turizm klasteri', ru: 'Туристический кластер' },
  'CHECK UTILITIES INFRASTRUCTURE AND SITE ACCESS': {
    uz: 'Muhandislik tarmoqlari va uchastkaga kirish yoʻlini tekshirish',
    ru: 'Проверить инженерные сети и подъезд',
  },

  /* --- the City of Tashkent's proposal deck ----------------------------
     These stretch the rule above, and the reason they may is that English is
     not the original here. That deck is written in Uzbek Cyrillic and was
     read into English for the portfolio; putting these rows back into Uzbek
     and Russian restores what the city wrote rather than translating a fact
     away from it. Figures are untouched either way. */
  'PUBLIC–PRIVATE PARTNERSHIP': {
    uz: 'Davlat-xususiy sheriklik',
    ru: 'Государственно-частное партнёрство',
  },
  'CITY OF TASHKENT': { uz: 'Toshkent shahri', ru: 'город Ташкент' },
  '44 TOURISM STREETS CREATED OR BEING CREATED IN 2025–2026': {
    uz: '2025–2026-yillarda tashkil etilgan va etilayotgan 44 ta turizm koʻchasi',
    ru: '44 туристические улицы, созданные и создаваемые в 2025–2026 годах',
  },
  'ON THE PLANT’S OWN SITE': {
    uz: 'Zavodning oʻz hududida',
    ru: 'На собственной территории завода',
  },
  'TASHKENT PLANETARIUM — 2,000 M²': {
    uz: 'Toshkent planetariysi — 2 000 m²',
    ru: 'Ташкентский планетарий — 2 000 м²',
  },
  'TWELVE HISTORIC GATE SITES AND THE OLD CITY': {
    uz: '12 ta tarixiy darvoza nuqtasi va Eski shahar',
    ru: '12 точек исторических ворот и Старый город',
  },
  'CITY ADMINISTRATION + TSUE + VISITING CHEFS': {
    uz: 'Hokimlik + TDIU + xorijiy sheflar',
    ru: 'Хокимият + ТГЭУ + иностранные шеф-повара',
  },
  'WORKING COOKS AND TALENTED YOUNG PEOPLE': {
    uz: 'Oshpazlar va iqtidorli yoshlar',
    ru: 'Повара и талантливая молодёжь',
  },
  'THEORY + PRACTICE + DUAL TRAINING': {
    uz: 'Nazariya + amaliyot + dual taʼlim',
    ru: 'Теория + практика + дуальное обучение',
  },
  'LAND ADJOINING THE TASHKENT-VOSTOCHNY AERODROME': {
    uz: '“Toshkent-Sharqiy” aerodromiga tutash hudud',
    ru: 'Территория, прилегающая к аэродрому «Ташкент-Восточный»',
  },
  'SAYILGOH STREET — 1.2 KM': {
    uz: 'Sayilgoh koʻchasi — 1,2 km',
    ru: 'улица Сайилгох — 1,2 км',
  },
  'NEW TASHKENT — 5,000–7,000 M²': {
    uz: 'Yangi Toshkent — 5 000–7 000 m²',
    ru: 'Новый Ташкент — 5 000–7 000 м²',
  },
};

/**
 * Values that carry a figure inside them — "85% built", "Construction · 30%".
 *
 * The number belongs to the project and is kept exactly; only the words
 * around it are translated, which is why these are patterns rather than
 * entries in the table above.
 */
const PATTERNS: { test: RegExp; uz: (n: string) => string; ru: (n: string) => string }[] = [
  {
    test: /^(\d+%)\s*built$/i,
    uz: (n) => `${n} qurilgan`,
    ru: (n) => `Построено ${n}`,
  },
  {
    test: /^construction\s*[·.,-]\s*(\d+%)$/i,
    uz: (n) => `Qurilish jarayonida · ${n}`,
    ru: (n) => `В стадии строительства · ${n}`,
  },
  /* --- durations and head-counts, as the city's proposal deck states them.
         The figure is captured whole, ranges included ("18–24"), and only
         the unit word after it is said in the reader's language. */
  {
    test: /^([\d–—-]+)\s*years?$/i,
    uz: (n) => `${n} yil`,
    ru: (n) => `${n} г.`,
  },
  {
    test: /^([\d–—-]+)\s*months?$/i,
    uz: (n) => `${n} oy`,
    ru: (n) => `${n} мес.`,
  },
  {
    test: /^([\d–—-]+)\s*thousand\s*visitors$/i,
    uz: (n) => `${n} ming kishi`,
    ru: (n) => `${n} тыс. человек`,
  },
  {
    test: /^([\d–—-]+)\s*thousand\+$/i,
    uz: (n) => `${n} ming+`,
    ru: (n) => `${n} тыс.+`,
  },
  /* --- the units a capacity is counted in.
         The working group's intake rows state capacity as a count and a unit
         word ("32 rooms"), and so do a good many of the sheets, so these earn
         their place twice over. The count is captured whole and copied; only
         the unit is said in the reader's language. */
  {
    test: /^([\d\s.,–—-]+?)\s*rooms?$/i,
    uz: (n) => `${n} xona`,
    ru: (n) => `${n} номеров`,
  },
  {
    test: /^([\d\s.,–—-]+?)\s*beds?$/i,
    uz: (n) => `${n} oʻrin`,
    ru: (n) => `${n} мест`,
  },
  {
    test: /^([\d\s.,–—-]+?)\s*people\s*\/\s*day$/i,
    uz: (n) => `${n} kishi/kun`,
    ru: (n) => `${n} человек/сутки`,
  },
  {
    test: /^([\d\s.,–—-]+?)\s*people$/i,
    uz: (n) => `${n} kishi`,
    ru: (n) => `${n} человек`,
  },
  /* A hectare figure is the one of these with a decimal in it, and Uzbek and
     Russian write that decimal with a comma. Left in English the card said
     "10,7 ga" in its description and "10.7 ga" in the chip above it. */
  {
    test: /^([\d\s.,–—-]+?)\s*ha$/i,
    uz: (n) => `${n.replace('.', ',')} ga`,
    ru: (n) => `${n.replace('.', ',')} га`,
  },
  /* --- how far the plot is from the two ways into the region.
         Every regional one-pager states it in this one shape, so the two
         distances are captured whole and only the two nouns are said in the
         reader's language. */
  {
    test: /^airport\s*\/\s*railway:\s*(.+)$/i,
    uz: (n) => `Aeroport / temir yoʻl: ${n}`,
    ru: (n) => `Аэропорт / вокзал: ${n}`,
  },
];

const key = (value: string) => value.replace(/\s+/g, ' ').trim().toUpperCase();

/**
 * The stage or activity in the reader's language, or the sheet's own wording
 * when this is not one of the words the organiser reuses.
 */
export function sheetValue(value: string, lang: Lang): string {
  if (lang === 'en') return value;
  const direct = DICTIONARY[key(value)];
  if (direct) return direct[lang];
  for (const pattern of PATTERNS) {
    const match = value.trim().match(pattern.test);
    if (match) return pattern[lang](match[1]!);
  }
  return value;
}
