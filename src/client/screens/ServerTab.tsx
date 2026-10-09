import { useEffect, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Button, Input, Spinner, TitledGreyBox, httpErrorToHuman, toast, useCurrentResource } from '@pterodactyl/sdk';
import { getAdminServer, keys, resync, saveLimit, type AdminServer } from '../api';
import RecordsTable from '../components/RecordsTable';

export default function ServerTab() {
    const context = useCurrentResource();
    const id = context?.kind === 'admin.server' ? context.resource.attributes.id : 0;
    const queryClient = useQueryClient();
    const server = useQuery({ queryKey: keys.adminServer(id), queryFn: () => getAdminServer(id), enabled: id > 0 });
    const [limit, setLimit] = useState('');
    const update = (data: AdminServer) => queryClient.setQueryData(keys.adminServer(id), data);
    const onError = (error: unknown) => toast.error(httpErrorToHuman(error));

    useEffect(() => setLimit(server.data?.custom_limit === null || server.data === undefined ? '' : String(server.data.custom_limit)), [server.data?.custom_limit]);

    const saving = useMutation({ mutationFn: () => saveLimit(id, limit === '' ? null : Number(limit)), onSuccess: (data) => (update(data), toast.success('Limit saved.')), onError });
    const syncing = useMutation({ mutationFn: () => resync(id), onSuccess: (data) => (update(data), toast.success('DNS records recreated.')), onError });

    if (!server.data) {
        return <Spinner centered />;
    }

    return (
        <div className='sub:grid sub:gap-6'>
            <TitledGreyBox title='Subdomain limit'>
                <div className='sub:flex sub:gap-2'>
                    <Input
                        aria-label='Subdomain limit'
                        inputMode='numeric'
                        placeholder={`Default (${server.data.limit})`}
                        value={limit}
                        onChange={(event) => /^\d{0,3}$/.test(event.currentTarget.value) && setLimit(event.currentTarget.value)}
                    />
                    <Button isLoading={saving.isPending} onClick={() => saving.mutate()}>
                        Save
                    </Button>
                </div>
                <p className='sub:mt-2 sub:text-xs sub:text-muted-foreground'>Leave empty to use the default from the extension settings.</p>
            </TitledGreyBox>
            <TitledGreyBox title='Subdomains'>
                <div className='sub:mb-3 sub:flex sub:justify-end'>
                    <Button.Text size='small' isLoading={syncing.isPending} disabled={server.data.records.length === 0} onClick={() => syncing.mutate()}>
                        Recreate DNS records
                    </Button.Text>
                </div>
                <RecordsTable records={server.data.records} empty='This server has no subdomains.' />
            </TitledGreyBox>
        </div>
    );
}
