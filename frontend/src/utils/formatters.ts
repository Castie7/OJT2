// src/utils/formatters.ts
// Shared formatting helpers — import from here instead of redefining locally.

/**
 * Formats a date value into a human-readable string.
 * Handles raw strings, ISO dates, and PHP DateTime objects ({ date: "..." }).
 */
export function formatDate(dateStr?: any): string {
  if (!dateStr) return 'N/A'

  let dateVal = dateStr
  // Handle PHP DateTime object { date: "...", timezone: "..." }
  if (typeof dateStr === 'object' && dateStr.date) {
    dateVal = dateStr.date
  }

  try {
    const d = new Date(dateVal)
    if (isNaN(d.getTime())) return dateVal
    return d.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' })
  } catch {
    return dateVal
  }
}

/**
 * Returns a label and CSS classes for a research status badge.
 */
export function getStatusBadge(status: string) {
  switch (status) {
    case 'approved': return { label: '✅ Published', classes: 'bg-green-100 text-green-700 border-green-200' }
    case 'pending': return { label: '⏳ Pending', classes: 'bg-yellow-100 text-yellow-800 border-yellow-200' }
    case 'rejected': return { label: '❌ Rejected', classes: 'bg-red-100 text-red-700 border-red-200' }
    case 'archived': return { label: '🗑️ Archived', classes: 'bg-gray-200 text-gray-600 border-gray-300' }
    default: return { label: status, classes: 'bg-gray-100 text-gray-700 border-gray-200' }
  }
}
