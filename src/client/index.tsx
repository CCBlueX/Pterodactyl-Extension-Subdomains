import './styles.css';
import { useQuery } from '@tanstack/react-query';
import { definePterodactylExtension, type ScreenContext } from '@pterodactyl/sdk';
import { getSubdomains, keys } from './api';

// No tab until an administrator added a domain, unless the server still has subdomains.
function useHasDomains({ server }: ScreenContext): boolean {
    const identifier = server?.attributes.identifier ?? '';
    const subdomains = useQuery({ queryKey: keys.server(identifier), queryFn: () => getSubdomains(identifier), enabled: identifier !== '', retry: false });

    return (subdomains.data?.domains.length ?? 0) > 0 || (subdomains.data?.records.length ?? 0) > 0;
}

export default definePterodactylExtension({
    setup({ screens }) {
        screens.register('subdomains', () => import('./screens/SubdomainsScreen'), { visible: useHasDomains });
        screens.register('admin', () => import('./screens/AdminScreen'));
        screens.register('server-tab', () => import('./screens/ServerTab'));
    },
});
