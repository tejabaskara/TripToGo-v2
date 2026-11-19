// Shared look for places: category colours, rating text and the map pin.
// Used by Home, PlaceDetail and the admin pages.
import L from 'leaflet'

// Colours for each category (chip + photo placeholder)
const categoryStyle = {
  beach: { chip: 'bg-sky-100 text-sky-700', photo: 'from-sky-300 to-sky-600' },
  mountain: { chip: 'bg-green-100 text-green-800', photo: 'from-green-200 to-green-700' },
  museum: { chip: 'bg-amber-100 text-amber-800', photo: 'from-amber-200 to-amber-600' },
}
const defaultStyle = { chip: 'bg-gray-100 text-gray-700', photo: 'from-gray-200 to-gray-500' }

export function styleFor(cat) {
  return categoryStyle[cat] ?? defaultStyle
}

// "★ 4.6 · 24 reviews" (or "No ratings · 0 reviews")
export function ratingText(p) {
  const avg = p.reviews_avg_rating ? Number(p.reviews_avg_rating).toFixed(1) : null
  const count = `${p.reviews_count} review${p.reviews_count === 1 ? '' : 's'}`
  return avg ? `★ ${avg} · ${count}` : `No ratings · ${count}`
}

// Pin drawn as SVG: green normally, bigger and orange when selected
export function pinIcon(selected) {
  const w = selected ? 40 : 28
  const h = selected ? 54 : 38
  const color = selected ? '#EA580C' : '#15803D'
  return L.divIcon({
    className: '',
    iconSize: [w, h],
    iconAnchor: [w / 2, h],
    popupAnchor: [0, -h],
    html: `<svg width="${w}" height="${h}" viewBox="-2 -2 32 42" style="filter: drop-shadow(0 2px 3px rgba(0,0,0,.3))">
      <path d="M14 0C6.3 0 0 6.3 0 14c0 10.5 14 24 14 24s14-13.5 14-24C28 6.3 21.7 0 14 0z"
        fill="${color}" stroke="#fff" stroke-width="2.5"/>
      <circle cx="14" cy="14" r="5" fill="#fff"/>
    </svg>`,
  })
}
