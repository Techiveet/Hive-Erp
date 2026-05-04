/**
 * Ethiopian Calendar Conversion Utility
 * Converts Gregorian dates to Ethiopian dates.
 */

export interface EthiopianDate {
  year: number;
  month: number;
  day: number;
  monthName: string;
  monthNameAmharic: string;
}

const ethiopianMonthNames = [
  "Meskerem", "Tikimt", "Hidar", "Tahsas", "Tir", "Yekatit",
  "Megabit", "Miyaziya", "Ginbot", "Sene", "Hamle", "Nehase", "Pagume"
];

const ethiopianMonthNamesAmharic = [
  "መስከረም", "ጥቅምት", "ህዳር", "ታህሳስ", "ጥር", "የካቲት",
  "መጋቢት", "ሚያዝያ", "ግንቦት", "ሰኔ", "ሐምሌ", "ነሐሴ", "ጳጉሜ"
];

/**
 * Converts a Gregorian Date object to an Ethiopian date.
 * Uses a robust JDN calculation based on date components.
 */
export function toEthiopianDate(date: Date): EthiopianDate {
  const year = date.getFullYear();
  const month = date.getMonth() + 1;
  const day = date.getDate();

  // Gregorian to JDN
  const a = Math.floor((14 - month) / 12);
  const y = year + 4800 - a;
  const m = month + 12 * a - 3;
  
  const jdn = day + Math.floor((153 * m + 2) / 5) + 365 * y + Math.floor(y / 4) - Math.floor(y / 100) + Math.floor(y / 400) - 32045;

  // JDN to Ethiopian
  const era = 1723856; // Ethiopian Era
  const r = (jdn - era) % 1461;
  const n = (r % 365) + 365 * Math.floor(r / 1460);
  
  const etYearVal = 4 * Math.floor((jdn - era) / 1461) + Math.floor(r / 365) - Math.floor(r / 1460);
  const etMonth = Math.floor(n / 30) + 1;
  const etDay = (n % 30) + 1;

  return {
    year: etYearVal,
    month: etMonth,
    day: etDay,
    monthName: ethiopianMonthNames[etMonth - 1] || "Unknown",
    monthNameAmharic: ethiopianMonthNamesAmharic[etMonth - 1] || "Unknown"
  };
}

/**
 * Formats an Ethiopian date string.
 */
export function formatEthiopian(date: Date, locale: 'en' | 'am' = 'en'): string {
  const et = toEthiopianDate(date);
  if (locale === 'am') {
    return `${et.monthNameAmharic} ${et.day} ቀን ${et.year} ዓ.ም`;
  }
  return `${et.monthName} ${et.day}, ${et.year}`;
}
