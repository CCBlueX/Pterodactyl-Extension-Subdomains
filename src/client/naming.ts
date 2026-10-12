import type { AdminDomain, NamingInput, NamingMode } from './api';

export const NAMING_MODES: { value: NamingMode; label: string }[] = [
    { value: 'custom', label: 'Users type the name' },
    { value: 'pattern', label: 'Built from a pattern' },
    { value: 'random', label: 'Random' },
];

export const DEFAULT_PATTERN = '{username}-{server}';
export const MIN_LENGTH = 4;
export const MAX_LENGTH = 16;

// What the live example in the domain dialog is built from.
const SAMPLE: Record<string, string> = { username: 'steve', server: 'Survival World', id: '7unrl0q1' };
const SAMPLE_RANDOM = 'k7m2x9qa4b8c3d5e';

const LATIN: Record<string, string> = { ß: 'ss', æ: 'ae', œ: 'oe', ø: 'o', đ: 'd', ð: 'd', ł: 'l', þ: 'th', ı: 'i' };

/** Mirrors Naming::slugify in the backend: lowercase a-z, 0-9 and single dashes. */
export function slugify(text: string, max = 63): string {
    return text
        .toLowerCase()
        .replace(/[ßæœøđðłþı]/g, (character) => LATIN[character] ?? '')
        .normalize('NFKD')
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '')
        .slice(0, max)
        .replace(/-+$/, '');
}

export const usesRandom = ({ naming_mode, naming_pattern }: Pick<NamingInput, 'naming_mode' | 'naming_pattern'>) =>
    naming_mode === 'random' || (naming_mode === 'pattern' && naming_pattern.includes('{random}'));

/** The name a sample server ("Survival World" of steve) gets. */
export function exampleName({ naming_mode, naming_pattern, naming_length }: NamingInput): string {
    const random = SAMPLE_RANDOM.slice(0, Math.min(MAX_LENGTH, Math.max(MIN_LENGTH, naming_length || MIN_LENGTH)));

    if (naming_mode === 'custom') {
        return 'play';
    }

    if (naming_mode === 'random') {
        return random;
    }

    const filled = (naming_pattern.trim() || DEFAULT_PATTERN).toLowerCase().replace(/\{([a-z]+)\}/g, (_, key: string) => (key === 'random' ? random : (SAMPLE[key] ?? '')));

    return slugify(filled) || SAMPLE.id!;
}

export function namingSummary(domain: Pick<AdminDomain, 'naming_mode' | 'naming_pattern' | 'naming_length'>): string {
    switch (domain.naming_mode) {
        case 'pattern':
            return domain.naming_pattern;
        case 'random':
            return `Random, ${domain.naming_length} characters`;
        default:
            return 'Typed by users';
    }
}
