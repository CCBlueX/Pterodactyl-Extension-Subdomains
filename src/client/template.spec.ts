import { expect, test } from 'vitest';
import { PRESETS, previewRecords } from './template';
import { Template } from './screens/fixtures';

const preset = (value: string) => ({ ...Template.standard, ...PRESETS.find((item) => item.value === value)!.fields });

test('game presets only add SRV records where the game reads them', () => {
    expect(previewRecords(preset('minecraft-java')).some((line) => line.includes('_minecraft._tcp'))).toBe(true);
    expect(previewRecords(preset('teamspeak')).some((line) => line.includes('_ts3._udp'))).toBe(true);

    for (const value of ['minecraft-bedrock', 'source', 'plain']) {
        expect(preset(value).srv).toBe(false);
        expect(previewRecords(preset(value)).some((line) => line.includes('SRV'))).toBe(false);
    }
});

test('presets keep the TTL and comment', () => {
    const value = { ...Template.standard, ttl: 600, comment: '{server_name}' };

    expect({ ...value, ...PRESETS[0]!.fields }).toMatchObject({ ttl: 600, comment: '{server_name}' });
});
