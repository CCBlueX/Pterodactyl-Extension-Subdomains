import { afterEach, expect, test, vi } from 'vitest';
import { cleanup, fireEvent, render, screen, waitFor } from '@testing-library/react';
import { createExtensionTestHost, createTestServer } from '@pterodactyl/sdk/testing';
import type { ServerDomain, ServerSubdomains, SubdomainRecord } from '../api';
import { createSubdomain, getSubdomains, rollName, updateSubdomain } from '../api';
import SubdomainsScreen from './SubdomainsScreen';

vi.mock('../api', async (importOriginal) => ({
    ...(await importOriginal<typeof import('../api')>()),
    getSubdomains: vi.fn(),
    createSubdomain: vi.fn(),
    updateSubdomain: vi.fn(),
    rollName: vi.fn(),
}));

const custom: ServerDomain = { id: 1, name: 'example.com', naming: 'custom', random: false, preview: null };
const range: ServerDomain = { id: 2, name: 'serv.axo.im', naming: 'pattern', random: false, preview: 'steve-survival' };
const lucky: ServerDomain = { id: 3, name: 'rand.example.com', naming: 'random', random: true, preview: 'k7m2x9qa' };

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
    domains: [custom],
    allocations: [
        { id: 1, label: '203.0.113.7:25504', primary: true },
        { id: 2, label: '203.0.113.7:25505', primary: false },
    ],
    limit: 1,
    ...changes,
});

let host: ReturnType<typeof createExtensionTestHost> | undefined;

afterEach(() => {
    vi.clearAllMocks();
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

test('shows the address a pattern domain gives instead of a name input and sends no name', async () => {
    const response = data({ domains: [range, custom], limit: 3 });
    vi.mocked(createSubdomain).mockResolvedValue(response);
    renderScreen(['ext.subdomains.read', 'ext.subdomains.create'], response);

    const name = (await screen.findByLabelText('Name')) as HTMLInputElement;

    expect(name.readOnly).toBe(true);
    expect(name.value).toBe('steve-survival');
    expect(screen.getByText('steve-survival.serv.axo.im')).toBeTruthy();
    expect(screen.getByText('Names on serv.axo.im are picked for you.')).toBeTruthy();
    expect(screen.queryByRole('button', { name: 'New random name' })).toBeNull();

    fireEvent.click(screen.getByRole('button', { name: 'Create' }));

    await waitFor(() => expect(createSubdomain).toHaveBeenCalledWith('test-server', { domain: 2, allocation: null, note: '' }));
});

test('rolls a new random name before creating', async () => {
    const response = data({ domains: [lucky, custom], limit: 3 });
    vi.mocked(rollName).mockResolvedValue(data({ domains: [{ ...lucky, preview: 'q8x2m4ka' }, custom], limit: 3 }));
    renderScreen(['ext.subdomains.read', 'ext.subdomains.create'], response);

    expect(await screen.findByText('k7m2x9qa.rand.example.com')).toBeTruthy();

    fireEvent.click(screen.getByRole('button', { name: 'New random name' }));

    expect(await screen.findByText('q8x2m4ka.rand.example.com')).toBeTruthy();
    expect(rollName).toHaveBeenCalledWith('test-server', 3);
    expect(((await screen.findByLabelText('Name')) as HTMLInputElement).readOnly).toBe(true);
});

test('says when no free name is left', async () => {
    renderScreen(['ext.subdomains.read', 'ext.subdomains.create'], data({ domains: [{ ...range, preview: null }], limit: 3 }));

    expect(await screen.findByText('No free name is left on serv.axo.im. Ask an administrator.')).toBeTruthy();
    expect((screen.getByRole('button', { name: 'Create' }) as HTMLButtonElement).disabled).toBe(true);
});

test('renames subdomains on domains where users type the name', async () => {
    vi.mocked(updateSubdomain).mockResolvedValue(data());
    renderScreen(['ext.subdomains.read', 'ext.subdomains.update']);

    fireEvent.click(await screen.findByRole('button', { name: 'Edit' }));
    const name = (await screen.findByLabelText('Name')) as HTMLInputElement;
    expect(name.value).toBe('survival');

    fireEvent.change(name, { target: { value: 'Lobby!' } });
    expect(await screen.findByText('survival.example.com stops working once you save.')).toBeTruthy();
    fireEvent.click(screen.getByRole('button', { name: 'Save' }));

    await waitFor(() => expect(updateSubdomain).toHaveBeenCalledWith('test-server', 1, { name: 'lobby', allocation: null, note: 'Main lobby' }));
});

test('keeps names on pattern and random domains', async () => {
    vi.mocked(updateSubdomain).mockResolvedValue(data());
    const automatic = record({ name: 'steve-survival', hostname: 'steve-survival.serv.axo.im', domain: 'serv.axo.im', domain_id: 2 });
    renderScreen(['ext.subdomains.read', 'ext.subdomains.update'], data({ records: [automatic], domains: [range] }));

    fireEvent.click(await screen.findByRole('button', { name: 'Edit' }));

    expect(await screen.findByText('Names on serv.axo.im are picked automatically and cannot be changed.')).toBeTruthy();
    expect(screen.queryByLabelText('Name')).toBeNull();

    fireEvent.click(screen.getByRole('button', { name: 'Save' }));

    await waitFor(() => expect(updateSubdomain).toHaveBeenCalledWith('test-server', 1, { allocation: null, note: 'Main lobby' }));
});
