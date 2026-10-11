import { useEffect, useState } from 'react';
import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Button, Dialog, Input, PanelLink, Spinner, TitledGreyBox, httpErrorToHuman, toast } from '@pterodactyl/sdk';
import { deleteRecord, getRecords, keys, type AdminRecord, type SubdomainRecord } from '../../api';
import RecordsTable from '../RecordsTable';

export default function AllRecords() {
    const queryClient = useQueryClient();
    const [input, setInput] = useState('');
    const [search, setSearch] = useState('');
    const [page, setPage] = useState(1);
    const [removing, setRemoving] = useState<AdminRecord | null>(null);
    const records = useQuery({ queryKey: keys.records(search, page), queryFn: () => getRecords(search, page), placeholderData: keepPreviousData });

    useEffect(() => {
        const timeout = setTimeout(() => (setSearch(input.trim()), setPage(1)), 300);

        return () => clearTimeout(timeout);
    }, [input]);

    const removal = useMutation({
        mutationFn: (id: number) => deleteRecord(id),
        onSuccess: () => {
            setRemoving(null);
            toast.success('Subdomain and its DNS records deleted.');
            void queryClient.invalidateQueries({ queryKey: keys.allRecords });
            void queryClient.invalidateQueries({ queryKey: keys.admin });
        },
        onError: (error) => toast.error(httpErrorToHuman(error)),
    });

    const pagination = records.data?.meta.pagination;
    const rows = records.data?.data ?? [];
    const rowOf = (record: SubdomainRecord) => rows.find((row) => row.id === record.id);

    return (
        <TitledGreyBox title='All subdomains'>
            <Input aria-label='Search subdomains' className='sub:mb-4' placeholder='Search by address, server or note' value={input} onChange={(event) => setInput(event.currentTarget.value)} />
            {!records.data ? (
                records.isError ? <p className='sub:text-sm sub:text-destructive'>{httpErrorToHuman(records.error)}</p> : <Spinner centered />
            ) : (
                <>
                    <RecordsTable
                        records={rows}
                        empty={search ? 'No subdomains match your search.' : 'No subdomains yet.'}
                        columns={[
                            {
                                id: 'server',
                                label: 'Server',
                                cell: (record) => {
                                    const server = rowOf(record)?.server;

                                    return server ? (
                                        <PanelLink destination={{ to: '/panel/servers/$id', params: { id: String(server.id) } }} className='sub:text-sm sub:text-foreground sub:hover:underline'>
                                            {server.name}
                                        </PanelLink>
                                    ) : (
                                        <span className='sub:text-sm sub:text-muted-foreground'>Deleted server</span>
                                    );
                                },
                            },
                        ]}
                        actions={(record) => (
                            <Button.Danger size='small' onClick={() => setRemoving(rowOf(record) ?? null)}>
                                Delete
                            </Button.Danger>
                        )}
                    />
                    {pagination && pagination.total_pages > 1 && (
                        <div className='sub:mt-4 sub:flex sub:items-center sub:justify-between sub:text-sm sub:text-muted-foreground'>
                            <span>
                                Page {pagination.current_page} of {pagination.total_pages} · {pagination.total} subdomains
                            </span>
                            <div className='sub:flex sub:gap-2'>
                                <Button.Text size='small' disabled={page <= 1} onClick={() => setPage(page - 1)}>
                                    Previous
                                </Button.Text>
                                <Button.Text size='small' disabled={page >= pagination.total_pages} onClick={() => setPage(page + 1)}>
                                    Next
                                </Button.Text>
                            </div>
                        </div>
                    )}
                </>
            )}
            <Dialog.Confirm
                open={removing !== null}
                onClose={() => setRemoving(null)}
                title={`Delete ${removing?.hostname}?`}
                confirm='Delete'
                pending={removal.isPending}
                onConfirmed={() => removing && removal.mutate(removing.id)}
            >
                Its DNS records are deleted too. The server owner can create a new subdomain afterwards.
            </Dialog.Confirm>
        </TitledGreyBox>
    );
}
