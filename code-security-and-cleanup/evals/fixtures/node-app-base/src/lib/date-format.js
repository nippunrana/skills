const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

export function formatShortDate(date) {
  const day = String(date.getUTCDate()).padStart(2, '0');
  return `${day}-${MONTHS[date.getUTCMonth()]}-${date.getUTCFullYear()}`;
}

export function parseShortDate(text) {
  const [day, month, year] = text.split('-');
  const monthIndex = MONTHS.indexOf(month);
  if (monthIndex === -1) return null;
  return new Date(Date.UTC(Number(year), monthIndex, Number(day)));
}
