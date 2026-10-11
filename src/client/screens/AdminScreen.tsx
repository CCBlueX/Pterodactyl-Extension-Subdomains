import { useState } from 'react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { PageContentBlock, Spinner, httpErrorToHuman } from '@pterodactyl/sdk';
import { getAdmin, keys, type AdminOverview } from '../api';
import AllRecords from '../components/admin/AllRecords';
import Domains from '../components/admin/Domains';
import Nodes from '../components/admin/Nodes';
import Templates from '../components/admin/Templates';

const TABS = [
    { id: 'subdomains', label: 'Subdomains' },
    { id: 'domains', label: 'Domains' },
    { id: 'templates', label: 'Templates' },
    { id: 'nodes', label: 'Nodes' },
] as const;

type Tab = (typeof TABS)[number]['id'];

export default function AdminScreen() {
    const queryClient = useQueryClient();
    const overview = useQuery({ queryKey: keys.admin, queryFn: getAdmin });
    const [tab, setTab] = useState<Tab>('subdomains');
    const update = (data: AdminOverview) => queryClient.setQueryData(keys.admin, data);

    return (
        <PageContentBlock title='Subdomains'>
            <header className='sub:mb-6'>
                <h1 className='sub:font-header sub:text-2xl sub:font-semibold sub:text-foreground'>Subdomains</h1>
                <p className='sub:mt-1 sub:text-sm sub:leading-relaxed sub:text-foreground/70'>
                    Domains users create subdomains on, the records they get and where they point.
                </p>
            </header>
            <nav aria-label='Subdomain settings' className='sub:mb-6 sub:flex sub:overflow-x-auto sub:rounded-sm sub:bg-card sub:px-2 sub:text-sm sub:shadow-sm'>
                {TABS.map((item) => (
                    <button
                        key={item.id}
                        type='button'
                        aria-current={tab === item.id ? 'page' : undefined}
                        onClick={() => setTab(item.id)}
                        className={`sub:cursor-pointer sub:whitespace-nowrap sub:px-4 sub:py-3 sub:transition-colors sub:duration-150 sub:hover:text-foreground ${
                            tab === item.id ? 'sub:text-foreground sub:shadow-navigation-active' : 'sub:text-muted-foreground'
                        }`}
                    >
                        {item.label}
                    </button>
                ))}
            </nav>
            {tab === 'subdomains' ? (
                <AllRecords />
            ) : !overview.data ? (
                overview.isError ? <p className='sub:text-sm sub:text-destructive'>{httpErrorToHuman(overview.error)}</p> : <Spinner centered size={Spinner.Size.LARGE} />
            ) : tab === 'domains' ? (
                <Domains overview={overview.data} onSaved={update} />
            ) : tab === 'templates' ? (
                <Templates overview={overview.data} onSaved={update} />
            ) : (
                <Nodes overview={overview.data} onSaved={update} />
            )}
        </PageContentBlock>
    );
}
