import { useEffect, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import {
    Alert,
    Button,
    Dialog,
    Input,
    Label,
    NamedIcon,
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
import { createSubdomain, deleteSubdomain, getSubdomains, keys, rollName, updateSubdomain, type Allocation, type ServerSubdomains, type SubdomainRecord } from '../api';
import RecordsTable from '../components/RecordsTable';

const PRIMARY = 0;

const allocationOptions = (allocations: Allocation[]) => [
    { value: PRIMARY, label: 'Primary allocation (follows changes)' },
    ...allocations.map((allocation) => ({ value: allocation.id, label: allocation.primary ? `${allocation.label} (primary)` : allocation.label })),
];

export default function SubdomainsScreen() {
    const { identifier } = useCurrentServerRequired().attributes;
    const queryClient = useQueryClient();
    const canCreate = useServerPermission(['ext.subdomains.create', 'ext.subdomains.manage'], true);
    const canUpdate = useServerPermission(['ext.subdomains.update', 'ext.subdomains.manage'], true);
    const canDelete = useServerPermission(['ext.subdomains.delete', 'ext.subdomains.manage'], true);
    const subdomains = useQuery({ queryKey: keys.server(identifier), queryFn: () => getSubdomains(identifier) });
    const [editing, setEditing] = useState<SubdomainRecord | null>(null);
    const [removing, setRemoving] = useState<SubdomainRecord | null>(null);
    const update = (data: ServerSubdomains) => queryClient.setQueryData(keys.server(identifier), data);
    const onError = (error: unknown) => toast.error(httpErrorToHuman(error));

    const removal = useMutation({
        mutationFn: (record: SubdomainRecord) => deleteSubdomain(identifier, record.id),
        onSuccess: (data) => (update(data), setRemoving(null)),
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
            {canCreate && full && (
                <Alert type='warning' title='Limit reached' className='sub:mb-6'>
                    {limit === 0
                        ? 'This server cannot have subdomains. Ask an administrator if you need one.'
                        : `This server has reached its subdomain limit. Delete one of its ${limit} subdomains to create another, or ask an administrator for more.`}
                </Alert>
            )}
            {canCreate && domains.length > 0 && !full && <CreateForm data={subdomains.data} identifier={identifier} onData={update} />}
            <p className='sub:mb-2 sub:text-sm sub:text-muted-foreground'>
                {records.length} of {limit} subdomains are in use.
            </p>
            <RecordsTable
                records={records}
                empty={domains.length === 0 ? 'No domains are set up for this server yet.' : 'No subdomains yet.'}
                actions={(record) => (
                    <>
                        {canUpdate && (
                            <Button.Text size='small' onClick={() => setEditing(record)}>
                                Edit
                            </Button.Text>
                        )}
                        {canDelete && (
                            <Button.Danger size='small' onClick={() => setRemoving(record)}>
                                Delete
                            </Button.Danger>
                        )}
                    </>
                )}
            />
            {editing && <EditDialog record={editing} data={subdomains.data} identifier={identifier} onClose={() => setEditing(null)} onSaved={update} />}
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

const sanitize = (name: string) => name.toLowerCase().replace(/[^a-z0-9-]/g, '');

function CreateForm({ data, identifier, onData }: { data: ServerSubdomains; identifier: string; onData: (data: ServerSubdomains) => void }) {
    const [name, setName] = useState('');
    const [domain, setDomain] = useState<number | null>(null);
    const [allocation, setAllocation] = useState(PRIMARY);
    const [note, setNote] = useState('');
    const selected = data.domains.find((item) => item.id === domain);
    // Pattern and random domains pick the name; the user only sees which one they get.
    const automatic = selected !== undefined && selected.naming !== 'custom';
    const label = automatic ? (selected.preview ?? '') : name;

    useEffect(() => setDomain((current) => (data.domains.some((item) => item.id === current) ? current : (data.domains[0]?.id ?? null))), [data.domains]);

    const creation = useMutation({
        mutationFn: () => createSubdomain(identifier, { domain: domain!, ...(automatic ? {} : { name }), allocation: allocation === PRIMARY ? null : allocation, note }),
        onSuccess: (result) => {
            onData(result);
            toast.success('Subdomain created. DNS changes can take a few minutes.');
            setName('');
            setNote('');
        },
        onError: (error) => toast.error(httpErrorToHuman(error)),
    });
    const rolling = useMutation({ mutationFn: () => rollName(identifier, domain!), onSuccess: onData, onError: (error) => toast.error(httpErrorToHuman(error)) });

    return (
        <TitledGreyBox title='New subdomain' className='sub:mb-6'>
            <form
                className='sub:grid sub:gap-4'
                onSubmit={(event) => {
                    event.preventDefault();
                    creation.mutate();
                }}
            >
                <div className='sub:grid sub:gap-2'>
                    <div className='sub:grid sub:gap-2 sub:sm:grid-cols-[minmax(0,1fr)_auto_14rem] sub:sm:items-end'>
                        <div>
                            <Label htmlFor='sub-name'>Name</Label>
                            {automatic ? (
                                <Input id='sub-name' readOnly className='sub:cursor-default sub:font-mono' placeholder='No free name' value={selected.preview ?? ''} />
                            ) : (
                                <Input id='sub-name' placeholder='play' maxLength={63} value={name} onChange={(event) => setName(sanitize(event.currentTarget.value))} />
                            )}
                        </div>
                        <span className='sub:hidden sub:pb-2 sub:text-muted-foreground sub:sm:block'>.</span>
                        <div>
                            <Label htmlFor='sub-domain'>Domain</Label>
                            <Select id='sub-domain' value={domain} onChange={(value) => setDomain(Number(value))} options={data.domains.map((item) => ({ value: item.id, label: item.name }))} />
                        </div>
                    </div>
                    {automatic && (
                        <div className='sub:flex sub:flex-wrap sub:items-center sub:gap-x-3 sub:gap-y-1 sub:text-xs sub:text-muted-foreground'>
                            {selected.preview === null ? (
                                <span className='sub:text-warning'>No free name is left on {selected.name}. Ask an administrator.</span>
                            ) : (
                                <span>{selected.random ? `Names on ${selected.name} are random.` : `Names on ${selected.name} are picked for you.`}</span>
                            )}
                            {selected.random && (
                                <Button.Text size='xsmall' isLoading={rolling.isPending} onClick={() => rolling.mutate()}>
                                    <span className='sub:inline-flex sub:items-center sub:gap-1.5'>
                                        <NamedIcon name='refresh-cw' aria-hidden className='sub:h-3.5 sub:w-3.5' />
                                        New random name
                                    </span>
                                </Button.Text>
                            )}
                        </div>
                    )}
                </div>
                <div className='sub:grid sub:gap-2 sub:sm:grid-cols-2'>
                    <div>
                        <Label htmlFor='sub-allocation'>Points to</Label>
                        <Select id='sub-allocation' value={allocation} onChange={(value) => setAllocation(Number(value))} options={allocationOptions(data.allocations)} />
                    </div>
                    <div>
                        <Label htmlFor='sub-note'>Note (optional)</Label>
                        <Input id='sub-note' maxLength={255} placeholder='What this address is for' value={note} onChange={(event) => setNote(event.currentTarget.value)} />
                    </div>
                </div>
                <div className='sub:flex sub:flex-col sub:gap-3 sub:sm:flex-row sub:sm:items-center sub:sm:justify-between'>
                    <p className='sub:min-w-0 sub:truncate sub:text-sm sub:text-muted-foreground' aria-live='polite'>
                        {label && selected ? (
                            <>
                                Players join with <span className='sub:font-mono sub:text-foreground'>{`${label}.${selected.name}`}</span>
                            </>
                        ) : (
                            'Players can join with this address instead of the IP and port.'
                        )}
                    </p>
                    <Button type='submit' className='sub:shrink-0' disabled={label === '' || domain === null} isLoading={creation.isPending}>
                        Create
                    </Button>
                </div>
            </form>
        </TitledGreyBox>
    );
}

interface EditProps {
    record: SubdomainRecord;
    data: ServerSubdomains;
    identifier: string;
    onClose: () => void;
    onSaved: (data: ServerSubdomains) => void;
}

function EditDialog({ record, data, identifier, onClose, onSaved }: EditProps) {
    const [name, setName] = useState(record.name);
    const [allocation, setAllocation] = useState(record.allocation_missing ? PRIMARY : (record.allocation_id ?? PRIMARY));
    const [note, setNote] = useState(record.note ?? '');
    // Only domains where users type the name allow renaming.
    const renamable = data.domains.find((item) => item.id === record.domain_id)?.naming === 'custom';
    const renamed = renamable && name !== record.name;
    const saving = useMutation({
        mutationFn: () => updateSubdomain(identifier, record.id, { ...(renamed ? { name } : {}), allocation: allocation === PRIMARY ? null : allocation, note }),
        onSuccess: (result) => {
            onSaved(result);
            onClose();
            toast.success('Subdomain updated. DNS changes can take a few minutes.');
        },
        onError: (error) => toast.error(httpErrorToHuman(error)),
    });

    return (
        <Dialog open onClose={onClose} title={`Edit ${record.hostname}`} preventExternalClose={saving.isPending}>
            <div className='sub:grid sub:gap-4'>
                {renamable ? (
                    <div>
                        <Label htmlFor='sub-edit-name'>Name</Label>
                        <div className='sub:flex sub:items-center sub:gap-2'>
                            <Input id='sub-edit-name' maxLength={63} value={name} onChange={(event) => setName(sanitize(event.currentTarget.value))} />
                            <span className='sub:shrink-0 sub:font-mono sub:text-sm sub:text-muted-foreground'>.{record.domain}</span>
                        </div>
                        {renamed && <p className='sub:mt-1 sub:text-xs sub:text-muted-foreground'>{record.hostname} stops working once you save.</p>}
                    </div>
                ) : (
                    <p className='sub:text-sm sub:text-muted-foreground'>Names on {record.domain} are picked automatically and cannot be changed.</p>
                )}
                <div>
                    <Label htmlFor='sub-edit-allocation'>Points to</Label>
                    <Select id='sub-edit-allocation' value={allocation} onChange={(value) => setAllocation(Number(value))} options={allocationOptions(data.allocations)} />
                </div>
                <div>
                    <Label htmlFor='sub-edit-note'>Note (optional)</Label>
                    <Input id='sub-edit-note' maxLength={255} value={note} onChange={(event) => setNote(event.currentTarget.value)} />
                </div>
            </div>
            <Dialog.Footer>
                <Button.Text onClick={onClose} disabled={saving.isPending}>
                    Cancel
                </Button.Text>
                <Button isLoading={saving.isPending} disabled={renamable && name === ''} onClick={() => saving.mutate()}>
                    Save
                </Button>
            </Dialog.Footer>
        </Dialog>
    );
}
