import { afterEach, expect, test, vi } from 'vitest';
import { cleanup, fireEvent, render, screen } from '@testing-library/react';
import { createExtensionTestHost } from '@pterodactyl/sdk/testing';
import type { ExtensionResourceContext } from '@pterodactyl/sdk';
import { Template } from './fixtures';
import EggTab from './EggTab';

vi.mock('../api', async (importOriginal) => ({
    ...(await importOriginal<typeof import('../api')>()),
    getEggTemplate: vi.fn().mockResolvedValue({
        egg: { id: 5, name: 'Paper' },
        override: null,
        inherited: { key: 'minecraft', template: Template.minecraft },
        available: false,
    }),
}));

afterEach(cleanup);

test('shows the inherited template until the egg gets its own', async () => {
    const resource = { kind: 'admin.egg', resource: { object: 'egg', attributes: { id: 5, name: 'Paper' } } } as unknown as ExtensionResourceContext;
    const host = createExtensionTestHost({ extensionId: 'subdomains', prefix: 'sub', resource });
    render(<EggTab />, { wrapper: host.Wrapper });

    expect(await screen.findByText('Servers of Paper use the Minecraft template, shown below.')).toBeTruthy();
    expect(screen.getByText('Subdomains are off for this egg')).toBeTruthy();
    expect((screen.getByLabelText('Service') as HTMLInputElement).disabled).toBe(true);
    expect(screen.queryByRole('button', { name: 'Save' })).toBeNull();

    fireEvent.click(screen.getByRole('switch', { name: /Own template for this egg/ }));

    expect(await screen.findByText('Replaces the Minecraft template for servers of Paper.')).toBeTruthy();
    expect((screen.getByLabelText('Service') as HTMLInputElement).disabled).toBe(false);
    expect(screen.getByRole('button', { name: 'Save' })).toBeTruthy();
    host.dispose();
});
