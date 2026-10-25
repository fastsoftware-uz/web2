/**
 * Copy for the registration form.
 *
 * Its own message set rather than the site's: the site's covers eight
 * chapters and a project portfolio, and a form that asks nine questions
 * should not carry it. It lives in lib/ rather than beside the registration
 * page because the form is now in two places — that page, and the last
 * chapter of the site.
 *
 * Guests and speakers answer the same questions, at the client's asking, so
 * there is one form and one set of labels; only the first field differs.
 */

/**
 * How somebody takes part.
 *
 * A visitor and an investor are given their badge by the form; a VIP and a
 * speaker are invited, and the committee releases theirs by hand. The list
 * and that rule live in server/src/app.php — this is the same list said in
 * the reader's language.
 */
export type Role = 'vip' | 'investor' | 'visitor' | 'speaker';

export const ROLES: readonly Role[] = ['visitor', 'investor', 'vip', 'speaker'];

export type Lang = 'uz' | 'ru' | 'en';

export type Copy = {
  docTitle: string;
  docDescription: string;
  back: string;
  title: string;
  lead: string;
  dates: string;
  roleLabel: string;
  roles: Record<Role, string>;
  roleHint: Record<Role, string>;
  /** Shown to the roles whose badge the committee issues by hand. */
  ticketLater: string;
  fields: {
    full_name: string;
    organisation: string;
    position: string;
    country: string;
    email: string;
    phone: string;
    direction: string;
    note: string;
  };
  directionNone: string;
  directions: Record<string, string>;
  /** How the note names the project a reader arrived from. */
  aboutProject: string;
  optional: string;
  required: string;
  submit: string;
  sending: string;
  errors: {
    full_name: string;
    organisation: string;
    position: string;
    country: string;
    email: string;
    phone: string;
    role: string;
    /** Shown above the form when any field is wrong. */
    summary: string;
    network: string;
    rate: string;
    server: string;
  };
  doneTitle: string;
  doneBody: string;
  doneRef: string;
  /** The e-ticket the registration comes back with. */
  ticketTitle: string;
  ticketBody: string;
  ticketOpen: string;
  ticketNumber: string;
  again: string;
  /**
   * Offered when the request could not reach the API at all, so that a
   * registration is never simply lost: the same answers, by email.
   */
  byEmail: string;
  mailSubject: string;
  privacy: string;
};

export const MESSAGES: Record<Lang, Copy> = {
  uz: {
    docTitle: 'Roʻyxatdan oʻtish — Tourism Investment Forum 2026',
    docDescription:
      'Birinchi xalqaro turizm investitsiya forumi, Toshkent, 25–27 noyabr 2026. Mehmon yoki spiker sifatida roʻyxatdan oʻting.',
    back: 'Saytga qaytish',
    title: 'Roʻyxatdan oʻtish',
    lead: 'Mehmon yoki spiker sifatida. Ikkalasidan ham bir xil maʼlumot soʻraladi.',
    dates: '25–27 noyabr 2026 · Toshkent',
    roleLabel: 'Ishtirok turi',
    roles: { vip: 'VIP mehmon', investor: 'Investor', visitor: 'Mehmon', speaker: 'Spiker' },
    roleHint: {
      vip: 'Taklif asosida. Ishtirok tashkiliy qoʻmita bilan alohida kelishiladi.',
      investor: 'Loyihalar bilan tanishish, B2B va B2G uchrashuvlar.',
      visitor: 'Forum sessiyalarida qatnashasiz.',
      speaker: 'Sessiyalardan birida chiqish qilishni taklif qilasiz.',
    },
    ticketLater:
      'E-bilet avtomatik berilmaydi: tashkiliy qoʻmita siz bilan bogʻlanadi va beyjni shundan soʻng yuboradi.',
    fields: {
      full_name: 'Familiya, ism va sharif',
      organisation: 'Tashkilot',
      position: 'Lavozim',
      country: 'Mamlakat',
      email: 'Elektron pochta',
      phone: 'Telefon',
      direction: 'Qiziqish yoʻnalishi',
      note: 'Qoʻshimcha',
    },
    directionNone: 'Tanlanmagan',
    directions: {
      infrastructure: 'Mehmonxonalar va turizm infratuzilmasi',
      heritage: 'Madaniy, tarixiy va meros asosidagi rivojlanish',
      eco: 'Ekologik, tabiiy va ekstremal turizm',
      wellness: 'Tibbiy, sanatoriy-kurort va wellness turizmi',
      leisure: 'Koʻngilochar, oilaviy va tematik infratuzilma',
      transport: 'Transport va unga bogʻliq infratuzilma',
    },
    aboutProject: 'Loyiha',
    optional: 'ixtiyoriy',
    required: 'majburiy',
    submit: 'Roʻyxatdan oʻtish',
    sending: 'Yuborilmoqda…',
    errors: {
      full_name: 'Toʻliq ismingizni yozing.',
      organisation: 'Tashkilot nomini yozing.',
      position: 'Lavozimingizni yozing.',
      country: 'Mamlakatni yozing.',
      email: 'Elektron pochta manzili notoʻgʻri.',
      phone: 'Telefon raqamini yozing.',
      role: 'Ishtirok turini tanlang.',
      summary: 'Quyidagi maydonlarni tekshiring.',
      network: 'Soʻrov yuborilmadi. Internetni tekshirib, qayta urinib koʻring.',
      rate: 'Juda koʻp soʻrov yuborildi. Bir ozdan keyin urinib koʻring.',
      server: 'Serverda xatolik. Biroz oʻtib qayta urinib koʻring.',
    },
    doneTitle: 'Qabul qilindi',
    doneBody:
      'Arizangiz tashkiliy qoʻmitaga yetib bordi. Tafsilotlar elektron pochtangizga yuboriladi.',
    doneRef: 'Ariza raqami',
    ticketTitle: 'E-biletingiz tayyor',
    ticketBody:
      'Havolani saqlab qoʻying: beyj shu yerda, A4 qogʻozda chop etiladi. Forumga kirishda shu beyjni koʻrsatasiz.',
    ticketOpen: 'E-biletni ochish',
    ticketNumber: 'Bilet raqami',
    again: 'Yana bitta ariza',
    byEmail: 'Yoki arizangizni elektron pochta orqali yuboring:',
    mailSubject: 'Forumga roʻyxatdan oʻtish',
    privacy:
      'Maʼlumotlaringiz faqat forumni tashkil etish uchun ishlatiladi va uchinchi shaxslarga berilmaydi.',
  },

  ru: {
    docTitle: 'Регистрация — Tourism Investment Forum 2026',
    docDescription:
      'Первый международный инвестиционный форум в сфере туризма, Ташкент, 25–27 ноября 2026. Регистрация гостей и спикеров.',
    back: 'Вернуться на сайт',
    title: 'Регистрация',
    lead: 'Как гость или как спикер. У обоих спрашивается одно и то же.',
    dates: '25–27 ноября 2026 · Ташкент',
    roleLabel: 'Тип участия',
    roles: { vip: 'VIP-гость', investor: 'Инвестор', visitor: 'Гость', speaker: 'Спикер' },
    roleHint: {
      vip: 'По приглашению. Участие согласовывается с оргкомитетом отдельно.',
      investor: 'Знакомство с проектами, встречи B2B и B2G.',
      visitor: 'Участие в сессиях форума.',
      speaker: 'Вы предлагаете выступление на одной из сессий.',
    },
    ticketLater:
      'Электронный билет не выдаётся автоматически: оргкомитет свяжется с вами и пришлёт бейдж после этого.',
    fields: {
      full_name: 'Фамилия, имя и отчество',
      organisation: 'Организация',
      position: 'Должность',
      country: 'Страна',
      email: 'Электронная почта',
      phone: 'Телефон',
      direction: 'Интересующее направление',
      note: 'Дополнительно',
    },
    directionNone: 'Не выбрано',
    directions: {
      infrastructure: 'Гостиницы и туристическая инфраструктура',
      heritage: 'Развитие на основе культурного и исторического наследия',
      eco: 'Экологический, природный и экстремальный туризм',
      wellness: 'Медицинский, санаторно-курортный и wellness-туризм',
      leisure: 'Развлекательная, семейная и тематическая инфраструктура',
      transport: 'Транспорт и связанная инфраструктура',
    },
    aboutProject: 'Проект',
    optional: 'необязательно',
    required: 'обязательно',
    submit: 'Зарегистрироваться',
    sending: 'Отправляем…',
    errors: {
      full_name: 'Укажите полное имя.',
      organisation: 'Укажите организацию.',
      position: 'Укажите должность.',
      country: 'Укажите страну.',
      email: 'Адрес электронной почты указан неверно.',
      phone: 'Укажите номер телефона.',
      role: 'Выберите тип участия.',
      summary: 'Проверьте отмеченные поля.',
      network: 'Запрос не отправлен. Проверьте соединение и попробуйте ещё раз.',
      rate: 'Слишком много запросов. Попробуйте немного позже.',
      server: 'Ошибка на сервере. Попробуйте позже.',
    },
    doneTitle: 'Заявка принята',
    doneBody: 'Заявка получена оргкомитетом. Подробности придут на указанную почту.',
    doneRef: 'Номер заявки',
    ticketTitle: 'Ваш электронный билет готов',
    ticketBody:
      'Сохраните ссылку: бейдж открывается по ней и печатается на листе A4. Его показывают на входе на форум.',
    ticketOpen: 'Открыть билет',
    ticketNumber: 'Номер билета',
    again: 'Ещё одна заявка',
    byEmail: 'Либо отправьте заявку по электронной почте:',
    mailSubject: 'Регистрация на форум',
    privacy:
      'Данные используются только для организации форума и не передаются третьим лицам.',
  },

  en: {
    docTitle: 'Registration — Tourism Investment Forum 2026',
    docDescription:
      'First International Tourism Investment Forum, Tashkent, 25–27 November 2026. Register as a visitor, an investor, a VIP guest or a speaker.',
    back: 'Back to the site',
    title: 'Registration',
    lead: 'As a visitor, an investor, a VIP guest or a speaker. All four are asked the same questions.',
    dates: '25–27 November 2026 · Tashkent',
    roleLabel: 'Taking part as',
    roles: { vip: 'VIP guest', investor: 'Investor', visitor: 'Visitor', speaker: 'Speaker' },
    roleHint: {
      vip: 'By invitation. Attendance is arranged with the organising committee.',
      investor: 'Seeing the projects, and the B2B and B2G meetings.',
      visitor: 'Attending the sessions of the forum.',
      speaker: 'Proposing to speak in one of the sessions.',
    },
    ticketLater:
      'The e-ticket is not issued automatically: the organising committee will be in touch and send the badge afterwards.',
    fields: {
      full_name: 'Full name',
      organisation: 'Organisation',
      position: 'Position',
      country: 'Country',
      email: 'Email',
      phone: 'Telephone',
      direction: 'Direction of interest',
      note: 'Anything else',
    },
    directionNone: 'Not chosen',
    directions: {
      infrastructure: 'Hotels and tourism infrastructure',
      heritage: 'Culture, history and heritage-led development',
      eco: 'Eco, nature and adventure tourism',
      wellness: 'Medical, sanatorium and wellness tourism',
      leisure: 'Entertainment, family and themed attractions',
      transport: 'Transport and related infrastructure',
    },
    aboutProject: 'Project',
    optional: 'optional',
    required: 'required',
    submit: 'Register',
    sending: 'Sending…',
    errors: {
      full_name: 'Please give your full name.',
      organisation: 'Please give your organisation.',
      position: 'Please give your position.',
      country: 'Please give your country.',
      email: 'That email address is not valid.',
      phone: 'Please give a telephone number.',
      role: 'Choose how you are taking part.',
      summary: 'Please check the fields marked below.',
      network: 'The request did not go through. Check your connection and try again.',
      rate: 'Too many submissions from here. Please try again shortly.',
      server: 'The server could not take it. Please try again shortly.',
    },
    doneTitle: 'Registered',
    doneBody:
      'Your registration has reached the organising committee. Details will follow by email.',
    doneRef: 'Reference',
    ticketTitle: 'Your e-ticket is ready',
    ticketBody:
      'Keep the link: the badge opens there and prints on one A4 sheet. You show it at the entrance.',
    ticketOpen: 'Open the e-ticket',
    ticketNumber: 'Ticket number',
    again: 'Register somebody else',
    byEmail: 'Or send your registration by email instead:',
    mailSubject: 'Forum registration',
    privacy:
      'What you enter is used only to run the forum and is not passed to anybody else.',
  },
};

/** The organiser's own address, as shared/data/forum-2026.json records it. */
export const ORGANISER_EMAIL = 'investforum@uzbektourism.uz';

export const LANG_NAMES: Record<Lang, string> = {
  uz: 'Oʻzbekcha',
  ru: 'Русский',
  en: 'English',
};

export const DIRECTION_IDS = [
  'infrastructure',
  'heritage',
  'eco',
  'wellness',
  'leisure',
  'transport',
] as const;
