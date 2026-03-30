const UMLAUT_MAP = { ä: 'ae', ö: 'oe', ü: 'ue', ß: 'ss' }

export function slugify(value) {
    return value
        .toLowerCase()
        .trim()
        .replace(/[äöüß]/g, (char) => UMLAUT_MAP[char])
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/[^\w\s-]/g, '')
        .replace(/[\s_]+/g, '-')
        .replace(/-+/g, '-')
}
