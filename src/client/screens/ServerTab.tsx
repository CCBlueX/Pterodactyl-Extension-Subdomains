import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Button, Spinner, TitledGreyBox, httpErrorToHuman, toast, useCurrentResource } from '@pterodactyl/sdk';
import { getAdminServer, keys, resync } from '../api';
import LimitBox from '../components/LimitBox';
import RecordsTable from '../components/RecordsTable';

export default function ServerTab() {
    const context = useCurrentResource();
    const id = context?.kind === 'admin.server' ? context.resource.attributes.id : 0;
    const queryClient = useQueryClient();
    const server = useQuery({ queryKey: keys.adminServer(id), queryFn: () => getAdminServer(id), enabled: id > 0 });

    const syncing = useMutation({
        mutationFn: () => resync(id),
        onSuccess: (data) => {
            queryClient.setQueryData(keys.adminServer(id), data);
            toast.success('DNS records recreated.');
        },
        onError: (error) => toast.error(httpErrorToHuman(error)),
    });

    if (!server.data) {
        return <Spinner centered />;
    }

    return (
        <div className='sub:grid sub:gap-6'>
            <LimitBox server={id} />
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
