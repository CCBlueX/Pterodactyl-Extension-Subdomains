import { useEffect, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Button, Input, Spinner, TitledGreyBox, httpErrorToHuman, toast } from '@pterodactyl/sdk';
import { getAdminServer, keys, saveLimit } from '../api';

/** The per-server subdomain limit, on the extension's server tab and the server's Build tab. */
export default function LimitBox({ server, title = 'Subdomain limit' }: { server: number; title?: string }) {
    const queryClient = useQueryClient();
    const data = useQuery({ queryKey: keys.adminServer(server), queryFn: () => getAdminServer(server), enabled: server > 0 });
    const [limit, setLimit] = useState('');

    useEffect(() => setLimit(data.data?.custom_limit == null ? '' : String(data.data.custom_limit)), [data.data?.custom_limit]);

    const saving = useMutation({
        mutationFn: () => saveLimit(server, limit === '' ? null : Number(limit)),
        onSuccess: (result) => {
            queryClient.setQueryData(keys.adminServer(server), result);
            toast.success('Subdomain limit saved.');
        },
        onError: (error) => toast.error(httpErrorToHuman(error)),
    });

    return (
        <TitledGreyBox title={title}>
            {!data.data ? (
                <Spinner centered />
            ) : (
                <>
                    <div className='sub:flex sub:gap-2'>
                        <Input
                            aria-label='Subdomain limit'
                            inputMode='numeric'
                            placeholder={`Default (${data.data.default_limit})`}
                            value={limit}
                            onChange={(event) => /^\d{0,3}$/.test(event.currentTarget.value) && setLimit(event.currentTarget.value)}
                        />
                        <Button isLoading={saving.isPending} onClick={() => saving.mutate()}>
                            Save
                        </Button>
                    </div>
                    <p className='sub:mt-2 sub:text-xs sub:text-muted-foreground'>
                        Uses {data.data.records.length} of {data.data.limit}. Leave empty to use the default from the extension settings.
                    </p>
                </>
            )}
        </TitledGreyBox>
    );
}
