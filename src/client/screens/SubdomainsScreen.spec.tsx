import { afterEach, expect, test, vi } from 'vitest';
import { cleanup, render, screen } from '@testing-library/react';
import { createExtensionTestHost, createTestServer } from '@pterodactyl/sdk/testing';
import SubdomainsScreen from './SubdomainsScreen';

vi.mock('../api', async (importOriginal) => ({
    ...(await importOriginal<typeof import('../api')>()),
    getSubdomains: vi.fn().mockResolvedValue({
        records: [{ id: 1, hostname: 'survival.example.com', domain: 'example.com', target: '203.0.113.7', port: 25504, srv: true, error: null, created_at: null }],
        domains: [{ id: 1, name: 'example.com' }],
        limit: 1,
    }),
}));

let host: ReturnType<typeof createExtensionTestHost> | undefined;

afterEach(() => {
    cleanup();
    host?.dispose();
    host = undefined;
});

function renderScreen(permissions: string[]) {
    host = createExtensionTestHost({ extensionId: 'subdomains', prefix: 'sub', server: createTestServer({ owner: false, permissions }) });
    render(<SubdomainsScreen />, { wrapper: host.Wrapper });
}

test('lists subdomains and blocks new ones at the limit', async () => {
    renderScreen(['ext.subdomains.read', 'ext.subdomains.manage']);

    expect(await screen.findByText('survival.example.com')).toBeTruthy();
    expect(screen.getByText('SRV')).toBeTruthy();
    expect(screen.getByText('This server has reached its subdomain limit.')).toBeTruthy();
    expect((screen.getByRole('button', { name: 'Create' }) as HTMLButtonElement).disabled).toBe(true);
});

test('is read only without the manage permission', async () => {
    renderScreen(['ext.subdomains.read']);

    expect(await screen.findByText('survival.example.com')).toBeTruthy();
    expect(screen.queryByText('New subdomain')).toBeNull();
    expect(screen.queryByRole('button', { name: 'Delete' })).toBeNull();
});
