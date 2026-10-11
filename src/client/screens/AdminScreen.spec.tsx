import type { ReactNode } from 'react';
import { afterEach, expect, test, vi } from 'vitest';
import { cleanup, fireEvent, render, screen } from '@testing-library/react';
import { createExtensionTestHost } from '@pterodactyl/sdk/testing';
import type { AdminOverview } from '../api';
import { Template } from './fixtures';
import AdminScreen from './AdminScreen';

const overview: AdminOverview = {
    domains: [{ id: 1, name: 'mc.example.com', provider: 'cloudflare', zone: 'example.com', enabled: true, eggs: [5], records: 2, credentials: { token: null }, secrets: ['token'] }],
    nodes: [{ id: 1, name: 'Node 1', fqdn: 'node1.example.net', mode: 'ip', ipv4: null, ipv6: null, hostname: null }],
    eggs: [
        { id: 5, name: 'Paper', minecraft: true, override: false },
        { id: 14, name: 'Rust', minecraft: false, override: true },
    ],
    allowed_eggs: [],
    providers: [
        { id: 'cloudflare', label: 'Cloudflare', fields: [{ key: 'token', label: 'API token', secret: true, required: false, placeholder: '', help: 'Zone: Read and DNS: Edit.' }] },
        { id: 'bunny', label: 'Bunny DNS', fields: [{ key: 'api_key', label: 'API key', secret: true, required: true, placeholder: '', help: '' }] },
    ],
    templates: {
        default: { template: Template.standard, custom: false },
        minecraft: { template: Template.minecraft, custom: false },
    },
};

vi.mock('../api', async (importOriginal) => ({
    ...(await importOriginal<typeof import('../api')>()),
    getAdmin: vi.fn(async () => overview),
    getRecords: vi.fn().mockResolvedValue({
        data: [
            {
                id: 3,
                name: 'survival',
                hostname: 'survival.mc.example.com',
                domain: 'mc.example.com',
                domain_id: 1,
                target: '203.0.113.7',
                port: 25565,
                allocation_id: null,
                allocation: null,
                allocation_missing: false,
                fallback_from: null,
                note: null,
                srv: false,
                proxied: false,
                error: null,
                created_at: null,
                server: { id: 8, name: 'Survival', identifier: 'abcd1234' },
                server_uuid: 'uuid',
            },
        ],
        meta: { pagination: { total: 1, count: 1, per_page: 25, current_page: 1, total_pages: 1 } },
    }),
}));

// The test host knows no extension screens, so links to them render as plain anchors.
vi.mock('@pterodactyl/sdk', async (importOriginal) => ({
    ...(await importOriginal<typeof import('@pterodactyl/sdk')>()),
    PanelLink: ({ children }: { children: ReactNode }) => <a href='#'>{children}</a>,
}));

let host: ReturnType<typeof createExtensionTestHost> | undefined;

afterEach(() => {
    cleanup();
    host?.dispose();
    host = undefined;
});

function renderScreen() {
    host = createExtensionTestHost({ extensionId: 'subdomains', prefix: 'sub' });
    render(<AdminScreen />, { wrapper: host.Wrapper });
}

test('lists every subdomain with its server', async () => {
    renderScreen();

    expect(await screen.findByText('survival.mc.example.com')).toBeTruthy();
    expect(screen.getByText('Survival')).toBeTruthy();
    expect(screen.getByLabelText('Search subdomains')).toBeTruthy();
});

test('shows domains without their secrets', async () => {
    renderScreen();
    fireEvent.click(await screen.findByRole('button', { name: 'Domains' }));

    expect(await screen.findByText('mc.example.com')).toBeTruthy();
    expect(screen.getByText('in zone example.com')).toBeTruthy();
    expect(screen.getByText('Cloudflare')).toBeTruthy();

    fireEvent.click(screen.getByRole('button', { name: 'Edit' }));

    const token = (await screen.findByLabelText('API token')) as HTMLInputElement;
    expect(token.value).toBe('');
    expect(token.placeholder).toBe('Saved. Leave empty to keep it.');
    expect(screen.getByRole('button', { name: 'Test connection' })).toBeTruthy();
});

test('documents placeholders and previews the minecraft template', async () => {
    renderScreen();
    fireEvent.click(await screen.findByRole('button', { name: 'Templates' }));

    expect(await screen.findByText('Minecraft template')).toBeTruthy();
    expect(screen.getAllByText('{server_name}').length).toBeGreaterThan(0);
    expect(screen.getByText(/_minecraft\._tcp\.play\.example\.com auto SRV 0 5 25570 play\.example\.com/)).toBeTruthy();
    expect(screen.getByText('Rust')).toBeTruthy();
});
