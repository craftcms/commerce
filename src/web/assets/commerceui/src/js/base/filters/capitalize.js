export function capitalize(value) {
    if (!value) return ''
    if (value === 'cp') return 'CP'
    value = value.toString()
    return value.charAt(0).toUpperCase() + value.slice(1)
}
