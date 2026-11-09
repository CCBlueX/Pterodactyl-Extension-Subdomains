import { afterEach, expect, test, vi } from 'vitest';
import { cleanup, fireEvent, render, screen, waitFor } from '@testing-library/react';
import { createExtensionTestHost } from '@pterodactyl/sdk/testing';
import { getAdminServer, saveLimit } from '../api';
import LimitBox from './LimitBox';

vi.mock('../api', async (importOriginal) => ({
    ...(await importOriginal<typeof import('../api')>()),
    getAdminServer: vi.fn(),
    saveLimit: vi.fn(),
}));

afterEach(cleanup);

test('saves an unlimited limit and shows it as unlimited', async () => {
    vi.mocked(getAdminServer).mockResolvedValue({ limit: 2, default_limit: 2, custom_limit: null, records: [] });
    vi.mocked(saveLimit).mockResolvedValue({ limit: -1, default_limit: 2, custom_limit: -1, records: [] });
    const host = createExtensionTestHost({ extensionId: 'subdomains', prefix: 'sub' });
    render(<LimitBox server={3} />, { wrapper: host.Wrapper });

    expect(await screen.findByText(/Uses 0 of 2\./)).toBeTruthy();
    fireEvent.click(screen.getAllByRole('checkbox', { name: 'Unlimited' })[0]!);
    expect((screen.getByLabelText('Subdomain limit') as HTMLInputElement).disabled).toBe(true);
    fireEvent.click(screen.getByRole('button', { name: 'Save' }));

    await waitFor(() => expect(saveLimit).toHaveBeenCalledWith(3, -1));
    expect(await screen.findByText(/Uses 0 of unlimited\./)).toBeTruthy();
    host.dispose();
});
