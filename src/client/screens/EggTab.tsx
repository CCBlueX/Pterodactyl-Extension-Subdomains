import { useEffect, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Alert, Button, Spinner, Switch, TitledGreyBox, httpErrorToHuman, toast, useCurrentResource } from '@pterodactyl/sdk';
import { getEggTemplate, keys, resetEggTemplate, saveEggTemplate, type EggTemplate, type TemplateData } from '../api';
import TemplateForm from '../components/TemplateForm';

const INHERITED = { default: 'the default template', minecraft: 'the Minecraft template' };

export default function EggTab() {
    const context = useCurrentResource();
    const id = context?.kind === 'admin.egg' ? context.resource.attributes.id : 0;
    const queryClient = useQueryClient();
    const data = useQuery({ queryKey: keys.egg(id), queryFn: () => getEggTemplate(id), enabled: id > 0 });
    const [own, setOwn] = useState(false);
    const [draft, setDraft] = useState<TemplateData | null>(null);

    useEffect(() => {
        if (data.data) {
            setOwn(data.data.override !== null);
            setDraft(data.data.override ?? data.data.inherited.template);
        }
    }, [data.data]);

    const done = (message: string) => (result: EggTemplate) => {
        queryClient.setQueryData(keys.egg(id), result);
        void queryClient.invalidateQueries({ queryKey: keys.admin });
        toast.success(message);
    };
    const onError = (error: unknown) => toast.error(httpErrorToHuman(error));
    const saving = useMutation({ mutationFn: () => saveEggTemplate(id, draft!), onSuccess: done('Template saved. Existing subdomains are updated in the background.'), onError });
    const resetting = useMutation({ mutationFn: () => resetEggTemplate(id), onSuccess: done(`This egg uses ${INHERITED[data.data?.inherited.key ?? 'default']} again.`), onError });

    if (!data.data || !draft) {
        return data.isError ? <p className='sub:text-sm sub:text-destructive'>{httpErrorToHuman(data.error)}</p> : <Spinner centered />;
    }

    const inherited = INHERITED[data.data.inherited.key];

    return (
        <div className='sub:grid sub:gap-6'>
            {!data.data.available && (
                <Alert type='warning' title='Subdomains are off for this egg'>
                    Servers of this egg cannot create subdomains. Allow the egg under Admin → Subdomains → Domains.
                </Alert>
            )}
            <TitledGreyBox title='Subdomain records'>
                <Switch
                    label='Own template for this egg'
                    description={own ? `Replaces ${inherited} for servers of ${data.data.egg.name}.` : `Servers of ${data.data.egg.name} use ${inherited}, shown below.`}
                    checked={own}
                    onChange={(value) => {
                        setOwn(value);
                        if (!value && data.data.override !== null) {
                            resetting.mutate();
                        }
                    }}
                />
                <div className='sub:mt-4'>
                    <TemplateForm id='sub-egg-template' value={own ? draft : data.data.inherited.template} onChange={setDraft} disabled={!own} />
                </div>
                {own && (
                    <div className='sub:mt-4 sub:flex sub:justify-end'>
                        <Button isLoading={saving.isPending} onClick={() => saving.mutate()}>
                            Save
                        </Button>
                    </div>
                )}
            </TitledGreyBox>
        </div>
    );
}
