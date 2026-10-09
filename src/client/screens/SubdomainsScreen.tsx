import { useEffect, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import {
    Button,
    Dialog,
    Input,
    Select,
    ServerContentBlock,
    ServerError,
    Spinner,
    TitledGreyBox,
    httpErrorToHuman,
    toast,
    useCurrentServerRequired,
    useServerPermission,
} from '@pterodactyl/sdk';
import { createSubdomain, deleteSubdomain, getSubdomains, keys, type SubdomainRecord } from '../api';
import RecordsTable from '../components/RecordsTable';

export default function SubdomainsScreen() {
    const { identifier } = useCurrentServerRequired().attributes;
    const queryClient = useQueryClient();
    const canManage = useServerPermission('ext.subdomains.manage');
    const subdomains = useQuery({ queryKey: keys.server(identifier), queryFn: () => getSubdomains(identifier) });
    const [name, setName] = useState('');
    const [domain, setDomain] = useState<number | null>(null);
    const [removing, setRemoving] = useState<SubdomainRecord | null>(null);
    const onError = (error: unknown) => toast.error(httpErrorToHuman(error));

    useEffect(() => setDomain((current) => current ?? subdomains.data?.domains[0]?.id ?? null), [subdomains.data]);

    const creation = useMutation({
        mutationFn: () => createSubdomain(identifier, domain!, name),
        onSuccess: (data) => {
            queryClient.setQueryData(keys.server(identifier), data);
            toast.success('Subdomain created. DNS changes can take a few minutes.');
            setName('');
        },
        onError,
    });
    const removal = useMutation({
        mutationFn: (record: SubdomainRecord) => deleteSubdomain(identifier, record.id),
        onSuccess: (data) => {
            queryClient.setQueryData(keys.server(identifier), data);
            setRemoving(null);
        },
        onError,
    });

    if (subdomains.isError) {
        return <ServerError title='Could not load subdomains' message={httpErrorToHuman(subdomains.error)} onRetry={() => subdomains.refetch()} />;
    }

    if (!subdomains.data) {
        return (
            <ServerContentBlock title='Subdomains'>
                <Spinner centered size={Spinner.Size.LARGE} />
            </ServerContentBlock>
        );
    }

    const { records, domains, limit } = subdomains.data;
    const full = records.length >= limit;

    return (
        <ServerContentBlock title='Subdomains'>
            {canManage && domains.length > 0 && (
                <TitledGreyBox title='New subdomain' className='sub:mb-6'>
                    <form
                        className='sub:grid sub:gap-2 sub:sm:grid-cols-[minmax(0,1fr)_auto_14rem_auto] sub:sm:items-center'
                        onSubmit={(event) => {
                            event.preventDefault();
                            creation.mutate();
                        }}
                    >
                        <Input
                            aria-label='Name'
                            placeholder='play'
                            value={name}
                            disabled={full}
                            onChange={(event) => setName(event.currentTarget.value.toLowerCase().replace(/[^a-z0-9-]/g, ''))}
                        />
                        <span className='sub:hidden sub:text-muted-foreground sub:sm:block'>.</span>
                        <Select value={domain} disabled={full} onChange={(value) => setDomain(Number(value))} options={domains.map((item) => ({ value: item.id, label: item.name }))} />
                        <Button type='submit' disabled={full || name === '' || domain === null} isLoading={creation.isPending}>
                            Create
                        </Button>
                    </form>
                    <p className='sub:mt-2 sub:text-xs sub:text-muted-foreground'>
                        {full ? 'This server has reached its subdomain limit.' : 'Players can join with this address instead of the IP and port.'}
                    </p>
                </TitledGreyBox>
            )}
            <p className='sub:mb-2 sub:text-sm sub:text-muted-foreground'>
                {records.length} of {limit} subdomains are in use.
            </p>
            <RecordsTable
                records={records}
                empty={domains.length === 0 ? 'No domains are set up yet.' : 'No subdomains yet.'}
                actions={(record) =>
                    canManage && (
                        <Button.Danger size='small' onClick={() => setRemoving(record)}>
                            Delete
                        </Button.Danger>
                    )
                }
            />
            <Dialog.Confirm
                open={removing !== null}
                onClose={() => setRemoving(null)}
                title={`Delete ${removing?.hostname}?`}
                confirm='Delete'
                pending={removal.isPending}
                onConfirmed={() => removing && removal.mutate(removing)}
            >
                Players can no longer join with this address. Anyone can take the name afterwards.
            </Dialog.Confirm>
        </ServerContentBlock>
    );
}
