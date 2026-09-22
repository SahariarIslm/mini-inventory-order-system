const formatter = new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' })

/** ISO timestamp from the API, in the viewer's locale and time zone. */
export function formatDateTime(iso: string): string {
  return formatter.format(new Date(iso))
}
