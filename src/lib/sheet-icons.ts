/**
 * Which glyph belongs beside a project sheet's row.
 *
 * The sheets state their fields in English and invent new ones freely —
 * sheet-labels.ts carries eighty-seven recurring names and 15% of rows are
 * still one-offs — so this cannot be a field list. It is a set of rules read
 * against the label, first match wins, and a label that matches nothing gets
 * the neutral tag rather than nothing at all: a column where nine rows carry
 * a glyph and three carry a gap reads as three rows that went wrong.
 *
 * Matched on the English label, never on the translated one. The label is
 * what the organiser wrote; the translation is ours, and a dictionary that
 * grows a synonym should not silently move an icon.
 */

/** Rules in priority order — the first whose pattern matches wins. */
const RULES: [RegExp, string][] = [
  /* Money first: "TOTAL PROJECT VALUE" also contains "PROJECT". */
  [/INVEST|VALUE|PRICE|CAPEX|COST|FUNDING|BUDGET|FINANC|SUM|USD|SOUM|SO'M/, 'money'],
  [/ROI|IRR|NPV|EBIT|PROFIT|REVENUE|INCOME|YIELD|MARGIN|TURNOVER|RETURN/, 'growth'],
  [/PAYBACK|PERIOD|TERM|DURATION|TIMELINE|MONTHS|YEARS/, 'payback'],
  [/OPENING|LAUNCH|SCHEDULE|DEADLINE|DATE|SEASON/, 'calendar'],
  [/AREA|PLOT|LAND|HECTAR| HA\b|M2|M²|SQUARE|SIZE/, 'area'],
  [/ROOM|BED|SUITE|VILLA|COTTAGE|YURT|KEYS|APARTMENT|TENT|PITCH/, 'rooms'],
  [/FLOOR|STOREY|LEVEL|TIER/, 'layers'],
  [/CAPACITY|VISITOR|GUEST|TOURIST|FLOW|SEATS|COVERS|ATTENDANCE|PEOPLE|DAY/, 'capacity'],
  [/JOB|EMPLOY|STAFF|WORKPLACE|PERSONNEL|WORKER/, 'jobs'],
  [/UTILIT|ENGINEER|WATER|ELECTRIC|GAS|POWER|SEWER|HEAT|NETWORK|GRID/, 'utilities'],
  [/ACCESS|TRANSPORT|AIRPORT|RAIL|ROAD|HIGHWAY|DISTANCE|KM\b|LOGISTIC/, 'transport'],
  [/LOCATION|ADDRESS|DISTRICT|REGION|SITE|PLACE|COORDIN|NEIGHBOUR/, 'location'],
  [/STATUS|STAGE|PROGRESS|READINESS|PERMIT|LICENCE|LICENSE|DOCUMENT|APPROVAL/, 'stage'],
  [/BUILDING|CONSTRUCTION|FACILIT|INFRASTRUCT|COMPLEX|OBJECT|PREMISES/, 'building'],
  [/OWNER|PARTNER|OPERATOR|TENANT|HOLDER|BALANCE|MANAGEMENT|BRAND|PPP/, 'partner'],
  [/TYPE|ACTIVITY|SEGMENT|CONCEPT|FORMAT|CATEGORY|CLASS|PROFILE/, 'tag'],
];

/** The glyph name for a sheet label, for FactIcon. Never empty. */
export function sheetIcon(label: string): string {
  const key = label.toUpperCase();
  for (const [pattern, name] of RULES) if (pattern.test(key)) return name;
  return 'tag';
}
