import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Button, Dialog, Input, PageContentBlock, Spinner, Switch, Table, TitledGreyBox, httpErrorToHuman, toast } from '@pterodactyl/sdk';
import { addDomain, getAdmin, keys, removeDomain, saveNode, toggleDomain, type AdminOverview } from '../api';

export default function AdminScreen() {
    const queryClient = useQueryClient();
    const overview = useQuery({ queryKey: keys.admin, queryFn: getAdmin });
    const [domain, setDomain] = useState('');
    const [removing, setRemoving] = useState<AdminOverview['domains'][number] | null>(null);
    const update = (data: AdminOverview) => queryClient.setQueryData(keys.admin, data);
    const onError = (error: unknown) => toast.error(httpErrorToHuman(error));

    const adding = useMutation({ mutationFn: () => addDomain(domain), onSuccess: (data) => (update(data), setDomain('')), onError });
    const toggling = useMutation({ mutationFn: ({ id, enabled }: { id: number; enabled: boolean }) => toggleDomain(id, enabled), onSuccess: update, onError });
    const removal = useMutation({ mutationFn: (id: number) => removeDomain(id), onSuccess: (data) => (update(data), setRemoving(null)), onError });

    return (
        <PageContentBlock title='Subdomains'>
            <header className='sub:mb-6'>
                <h1 className='sub:font-header sub:text-2xl sub:font-semibold sub:text-foreground'>Subdomains</h1>
                <p className='sub:mt-1 sub:text-sm sub:leading-relaxed sub:text-foreground/70'>
                    Domains users can create subdomains on. Add the Cloudflare API token in the extension settings first.
                </p>
            </header>
            {!overview.data ? (
                overview.isError ? <p className='sub:text-sm sub:text-destructive'>{httpErrorToHuman(overview.error)}</p> : <Spinner centered size={Spinner.Size.LARGE} />
            ) : (
                <div className='sub:grid sub:gap-6'>
                    <TitledGreyBox title='Domains'>
                        <form
                            className='sub:mb-4 sub:flex sub:gap-2'
                            onSubmit={(event) => {
                                event.preventDefault();
                                adding.mutate();
                            }}
                        >
                            <Input aria-label='Domain' placeholder='example.com' value={domain} onChange={(event) => setDomain(event.currentTarget.value.trim())} />
                            <Button type='submit' className='sub:shrink-0 sub:whitespace-nowrap' disabled={domain === ''} isLoading={adding.isPending}>
                                Add domain
                            </Button>
                        </form>
                        <Table
                            rows={overview.data.domains}
                            keyOf={(row) => String(row.id)}
                            emptyState='No domains yet.'
                            columns={[
                                { id: 'name', label: 'Domain', cell: (row) => <span className='sub:font-mono sub:text-foreground'>{row.name}</span> },
                                { id: 'records', label: 'Subdomains', cell: (row) => row.records },
                                {
                                    id: 'enabled',
                                    label: 'Available',
                                    cell: (row) => <Switch aria-label={`${row.name} available`} checked={row.enabled} onChange={(enabled) => toggling.mutate({ id: row.id, enabled })} />,
                                },
                                {
                                    id: 'actions',
                                    label: '',
                                    cell: (row) => (
                                        <div className='sub:flex sub:justify-end'>
                                            <Button.Danger size='small' onClick={() => setRemoving(row)}>
                                                Delete
                                            </Button.Danger>
                                        </div>
                                    ),
                                },
                            ]}
                        />
                    </TitledGreyBox>
                    <TitledGreyBox title='Node addresses'>
                        <p className='sub:mb-4 sub:text-sm sub:text-muted-foreground'>
                            Subdomains point at the server's allocation address. Set a public address here for nodes whose allocations listen on 0.0.0.0 or private addresses.
                        </p>
                        <div className='sub:grid sub:gap-3'>
                            {overview.data.nodes.map((node) => (
                                <NodeRow key={node.id} node={node} onSaved={update} />
                            ))}
                        </div>
                    </TitledGreyBox>
                </div>
            )}
            <Dialog.Confirm
                open={removing !== null}
                onClose={() => setRemoving(null)}
                title={`Delete ${removing?.name}?`}
                confirm='Delete'
                pending={removal.isPending}
                onConfirmed={() => removing && removal.mutate(removing.id)}
            >
                {removing?.records ? `Its ${removing.records} subdomains and their DNS records are deleted too.` : 'Users can no longer create subdomains on it.'}
            </Dialog.Confirm>
        </PageContentBlock>
    );
}

function NodeRow({ node, onSaved }: { node: AdminOverview['nodes'][number]; onSaved: (data: AdminOverview) => void }) {
    const [ipv4, setIpv4] = useState(node.ipv4 ?? '');
    const [ipv6, setIpv6] = useState(node.ipv6 ?? '');
    const save = useMutation({
        mutationFn: () => saveNode(node.id, ipv4, ipv6),
        onSuccess: (data) => {
            onSaved(data);
            toast.success(`Saved ${node.name}.`);
        },
        onError: (error) => toast.error(httpErrorToHuman(error)),
    });
    const changed = ipv4 !== (node.ipv4 ?? '') || ipv6 !== (node.ipv6 ?? '');

    return (
        <div className='sub:grid sub:gap-2 sub:sm:grid-cols-[12rem_minmax(0,1fr)_minmax(0,1fr)_auto] sub:sm:items-center'>
            <div className='sub:min-w-0'>
                <p className='sub:truncate sub:text-sm sub:text-foreground'>{node.name}</p>
                <p className='sub:truncate sub:text-xs sub:text-muted-foreground'>{node.fqdn}</p>
            </div>
            <Input aria-label={`${node.name} IPv4`} placeholder='IPv4 (automatic)' value={ipv4} onChange={(event) => setIpv4(event.currentTarget.value.trim())} />
            <Input aria-label={`${node.name} IPv6`} placeholder='IPv6 (none)' value={ipv6} onChange={(event) => setIpv6(event.currentTarget.value.trim())} />
            <Button size='small' disabled={!changed} isLoading={save.isPending} onClick={() => save.mutate()}>
                Save
            </Button>
        </div>
    );
}
