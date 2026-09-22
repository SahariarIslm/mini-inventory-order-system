/**
 * A fresh random key for one order attempt. crypto.randomUUID() only exists
 * in secure contexts (https or localhost), so fall back to getRandomValues(),
 * which works everywhere, when the app is opened via a LAN IP.
 */
export function newIdempotencyKey(): string {
  if (typeof crypto.randomUUID === 'function') {
    return crypto.randomUUID()
  }

  const bytes = crypto.getRandomValues(new Uint8Array(16))
  return Array.from(bytes, (b) => b.toString(16).padStart(2, '0')).join('')
}
