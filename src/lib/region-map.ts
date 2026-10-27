/**
 * The projected outlines of the fourteen regions, and where a counter goes.
 *
 * The geometry is produced once at build time by scripts/build-region-map.mjs
 * and shipped as flat SVG paths; nothing at runtime does geography. This
 * module holds the parts two drawings of that file have to agree on — the
 * shape of the file, how it is fetched, and the hand-placed positions for the
 * counters that will not fit inside their own region — so that the map on the
 * page and the map in the printed sheet cannot drift apart.
 *
 * The drawing is a schematic of administrative boundaries from an open data
 * set, published under ODbL and credited in the footer. It is a diagram, not a
 * survey, and it asserts no boundary.
 */

export interface RegionPath {
  id: string;
  d: string;
  label: [number, number];
  clearance: number;
  bounds: [number, number, number, number];
}

export interface MapFile {
  viewBox: string;
  regions: RegionPath[];
}

/**
 * Where a region's counter goes when it will not fit inside the region.
 *
 * Six of the fourteen are too narrow to hold a disc: the capital, which is a
 * few pixels across at this scale, Khorezm, Syrdarya, and the three that
 * share the Fergana valley. Tashkent region is moved for a different reason,
 * noted below. Their discs are placed by hand in the ground around the
 * country and joined back with a leader — outlines are projected once at
 * build time and never move, so these are fixed positions rather than a
 * layout problem to solve at runtime. Coordinates are in the viewBox of
 * scripts/build-region-map.mjs; change the projection there and these have to
 * be re-checked against it.
 */
export const CALLOUT: Record<string, [number, number]> = {
  khorezm: [222, 362],
  // Not for want of room, but because its own point sits on top of the
  // capital's, and the capital is the smaller of the two.
  tashkent_region: [772, 186],
  tashkent_city: [636, 128],
  syrdarya: [762, 402],
  namangan: [898, 150],
  andijan: [945, 243],
  fergana: [874, 442],
};

export interface Disc {
  x: number;
  y: number;
  /** The line back to the region, when the disc had to be moved out of it. */
  leader: string | null;
}

export function discAt(region: RegionPath): Disc {
  const callout = CALLOUT[region.id];
  if (!callout) return { x: region.label[0], y: region.label[1], leader: null };
  const [x, y] = callout;
  return { x, y, leader: `M${region.label[0]} ${region.label[1]}L${x} ${y}` };
}

/** Throws rather than reporting: both callers have something to fall back on. */
export async function fetchRegionMap(): Promise<MapFile> {
  const res = await fetch(`${import.meta.env.BASE_URL}geo/uz-regions-paths.json`);
  if (!res.ok) throw new Error(String(res.status));
  return (await res.json()) as MapFile;
}
