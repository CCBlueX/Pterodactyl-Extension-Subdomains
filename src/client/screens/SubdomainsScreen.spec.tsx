import { afterEach, expect, test, vi } from 'vitest';
import { cleanup, fireEvent, render, screen } from '@testing-library/react';
import { createExtensionTestHost, createTestServer } from '@pterodactyl/sdk/testing';
import type { ServerSubdomains, SubdomainRecord } from '../api';
import { getSubdomains } from '../api';
import SubdomainsScreen from './SubdomainsScreen';

vi.mock('../api', async (importOriginal) => ({ ...(await importOriginal<typeof import('../api')>()), getSubdomains: vi.fn() }));

const record = (changes: Partial<SubdomainRecord> = {}): SubdomainRecord => ({
    id: 1,
    name: 'survival',
    hostname: 'survival.example.com',
    domain: 'example.com',
    domain_id: 1,
    target: '203.0.113.7',
    port: 25504,
    allocation_id: null,
    allocation: null,
    allocation_missing: false,
    fallback_from: null,
    note: 'Main lobby',
    srv: true,
    proxied: false,
    error: null,
    created_at: null,
    ...changes,
});

const data = (changes: Partial<ServerSubdomains> = {}): ServerSubdomains => ({
    records: [record()],
    domains: [{ id: 1, name: 'example.com' }],
    allocations: [
        { id: 1, label: '203.0.113.7:25504', primary: true },
        { id: 2, label: '203.0.113.7:25505', primary: false },
    ],
    limit: 1,
    ...changes,
});

let host: ReturnType<typeof createExtensionTestHost> | undefined;

afterEach(() => {
    cleanup();
    host?.dispose();
    host = undefined;
});

function renderScreen(permissions: string[], response = data()) {
    vi.mocked(getSubdomains).mockResolvedValue(response);
    host = createExtensionTestHost({ extensionId: 'subdomains', prefix: 'sub', server: createTestServer({ owner: false, permissions }) });
    render(<SubdomainsScreen />, { wrapper: host.Wrapper });
}

test('lists subdomains and shows a banner at the limit', async () => {
    renderScreen(['ext.subdomains.read', 'ext.subdomains.create', 'ext.subdomains.delete']);

    expect(await screen.findByText('survival.example.com')).toBeTruthy();
    expect(screen.getByText('SRV')).toBeTruthy();
    expect(screen.getByText('Main lobby')).toBeTruthy();
    expect(screen.getByText('Limit reached')).toBeTruthy();
    expect(screen.queryByText('New subdomain')).toBeNull();
    expect(screen.getByRole('button', { name: 'Copy survival.example.com' })).toBeTruthy();
    expect(screen.getByRole('button', { name: 'Delete' })).toBeTruthy();
    expect(screen.queryByRole('button', { name: 'Edit' })).toBeNull();
});

test('previews the address while typing', async () => {
    renderScreen(['ext.subdomains.read', 'ext.subdomains.create'], data({ limit: 3 }));

    fireEvent.change(await screen.findByLabelText('Name'), { target: { value: 'Play!' } });

    expect(await screen.findByText('play.example.com')).toBeTruthy();
    expect((screen.getByRole('button', { name: 'Create' }) as HTMLButtonElement).disabled).toBe(false);
});

test('is read only without other permissions and the old manage permission still works', async () => {
    renderScreen(['ext.subdomains.read']);

    expect(await screen.findByText('survival.example.com')).toBeTruthy();
    expect(screen.queryByText('Limit reached')).toBeNull();
    expect(screen.queryByRole('button', { name: 'Delete' })).toBeNull();

    cleanup();
    host?.dispose();
    renderScreen(['ext.subdomains.read', 'ext.subdomains.manage']);

    expect(await screen.findByRole('button', { name: 'Edit' })).toBeTruthy();
    expect(screen.getByRole('button', { name: 'Delete' })).toBeTruthy();
});

test('says when a subdomain fell back to the primary allocation', async () => {
    renderScreen(['ext.subdomains.read'], data({ records: [record({ fallback_from: '203.0.113.7:25599' }), record({ id: 2, hostname: 'lobby.example.com', allocation_id: 9, allocation_missing: true })], limit: 2 }));

    expect(await screen.findByText('203.0.113.7:25599 was removed from the server, so this now points to the primary allocation.')).toBeTruthy();
    expect(screen.getByText('Its allocation was removed from the server. It switches to the primary allocation at the next check.')).toBeTruthy();
});
