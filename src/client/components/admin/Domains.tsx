import { useState } from 'react';
import { useMutation } from '@tanstack/react-query';
import { Button, Dialog, Label, Select, Switch, Table, TitledGreyBox, httpErrorToHuman, toast } from '@pterodactyl/sdk';
import { removeDomain, saveAllowedEggs, updateDomain, type AdminDomain, type AdminOverview } from '../../api';
import DomainDialog from './DomainDialog';

interface Props {
    overview: AdminOverview;
    onSaved: (data: AdminOverview) => void;
}

export const eggSummary = (ids: number[], overview: AdminOverview) =>
    ids.length === 0
        ? 'All eggs'
        : overview.eggs
              .filter((egg) => ids.includes(egg.id))
              .map((egg) => egg.name)
              .join(', ');

export default function Domains({ overview, onSaved }: Props) {
    const [editing, setEditing] = useState<AdminDomain | 'new' | null>(null);
    const [removing, setRemoving] = useState<AdminDomain | null>(null);
    const onError = (error: unknown) => toast.error(httpErrorToHuman(error));
    const providerLabel = (id: string) => overview.providers.find((provider) => provider.id === id)?.label ?? id;

    const toggling = useMutation({ mutationFn: ({ id, enabled }: { id: number; enabled: boolean }) => updateDomain(id, { enabled }), onSuccess: onSaved, onError });
    const removal = useMutation({ mutationFn: (id: number) => removeDomain(id), onSuccess: (data) => (onSaved(data), setRemoving(null)), onError });

    return (
        <div className='sub:grid sub:gap-6'>
            <TitledGreyBox title='Domains'>
                <div className='sub:mb-4 sub:flex sub:items-center sub:justify-between sub:gap-3'>
                    <p className='sub:text-sm sub:text-muted-foreground'>Each domain uses its own DNS provider and credentials.</p>
                    <Button className='sub:shrink-0' onClick={() => setEditing('new')}>
                        Add domain
                    </Button>
                </div>
                <Table
                    rows={overview.domains}
                    keyOf={(row) => String(row.id)}
                    emptyState='No domains yet.'
                    columns={[
                        {
                            id: 'name',
                            label: 'Domain',
                            cell: (row) => (
                                <div className='sub:min-w-0'>
                                    <p className='sub:font-mono sub:text-foreground'>{row.name}</p>
                                    {row.zone !== row.name && <p className='sub:text-xs sub:text-muted-foreground'>in zone {row.zone}</p>}
                                </div>
                            ),
                        },
                        { id: 'provider', label: 'Provider', cell: (row) => providerLabel(row.provider) },
                        { id: 'eggs', label: 'Eggs', cell: (row) => <span className='sub:text-sm sub:text-muted-foreground'>{eggSummary(row.eggs, overview)}</span> },
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
                                <div className='sub:flex sub:justify-end sub:gap-2'>
                                    <Button.Text size='small' onClick={() => setEditing(row)}>
                                        Edit
                                    </Button.Text>
                                    <Button.Danger size='small' onClick={() => setRemoving(row)}>
                                        Delete
                                    </Button.Danger>
                                </div>
                            ),
                        },
                    ]}
                />
            </TitledGreyBox>
            <AllowedEggs overview={overview} onSaved={onSaved} />
            {editing && <DomainDialog domain={editing === 'new' ? null : editing} overview={overview} onClose={() => setEditing(null)} onSaved={onSaved} />}
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
        </div>
    );
}

function AllowedEggs({ overview, onSaved }: Props) {
    const [eggs, setEggs] = useState(overview.allowed_eggs);
    const saving = useMutation({
        mutationFn: () => saveAllowedEggs(eggs),
        onSuccess: (data) => (onSaved(data), toast.success('Eggs saved.')),
        onError: (error) => toast.error(httpErrorToHuman(error)),
    });
    const changed = [...eggs].sort().join() !== [...overview.allowed_eggs].sort().join();

    return (
        <TitledGreyBox title='Eggs'>
            <Label htmlFor='sub-allowed-eggs'>Eggs that may use subdomains</Label>
            <div className='sub:flex sub:gap-2'>
                <div className='sub:min-w-0 sub:flex-1'>
                    <Select
                        id='sub-allowed-eggs'
                        multiple
                        placeholder='All eggs'
                        value={eggs}
                        onChange={(value) => setEggs(value.map(Number))}
                        options={overview.eggs.map((egg) => ({ value: egg.id, label: egg.name }))}
                    />
                </div>
                <Button className='sub:shrink-0' disabled={!changed} isLoading={saving.isPending} onClick={() => saving.mutate()}>
                    Save
                </Button>
            </div>
            <p className='sub:mt-2 sub:text-xs sub:text-muted-foreground'>
                Leave empty to allow every egg. Domains can narrow this down further. Existing subdomains stay until they are deleted.
            </p>
        </TitledGreyBox>
    );
}
