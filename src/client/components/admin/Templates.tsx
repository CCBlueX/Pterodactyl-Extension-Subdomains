import { useState } from 'react';
import { useMutation } from '@tanstack/react-query';
import { Button, PanelLink, TitledGreyBox, httpErrorToHuman, toast } from '@pterodactyl/sdk';
import { resetTemplate, saveTemplate, type AdminOverview, type SharedTemplate, type TemplateData } from '../../api';
import TemplateForm from '../TemplateForm';

interface Props {
    overview: AdminOverview;
    onSaved: (data: AdminOverview) => void;
}

const SHARED: { id: SharedTemplate; title: string; help: string }[] = [
    { id: 'default', title: 'Default template', help: 'Used by every egg without its own template.' },
    { id: 'minecraft', title: 'Minecraft template', help: 'Used by eggs with the eula feature or the minecraft tag.' },
];

export default function Templates({ overview, onSaved }: Props) {
    const overrides = overview.eggs.filter((egg) => egg.override);

    return (
        <div className='sub:grid sub:gap-6'>
            {SHARED.map((item) => (
                <SharedTemplateBox key={item.id} {...item} overview={overview} onSaved={onSaved} />
            ))}
            <TitledGreyBox title='Egg templates'>
                <p className='sub:mb-3 sub:text-sm sub:text-muted-foreground'>Give a single egg its own template on the egg's Subdomains tab.</p>
                {overrides.length === 0 ? (
                    <p className='sub:text-sm sub:text-muted-foreground'>No egg has its own template.</p>
                ) : (
                    <ul className='sub:flex sub:flex-wrap sub:gap-2'>
                        {overrides.map((egg) => (
                            <li key={egg.id}>
                                <PanelLink
                                    destination={{ extension: 'subdomains', screen: 'egg-tab', params: { eggId: String(egg.id) } }}
                                    className='sub:inline-block sub:rounded-full sub:bg-sunken sub:px-3 sub:py-1 sub:text-sm sub:text-foreground sub:hover:underline'
                                >
                                    {egg.name}
                                </PanelLink>
                            </li>
                        ))}
                    </ul>
                )}
            </TitledGreyBox>
        </div>
    );
}

function SharedTemplateBox({ id, title, help, overview, onSaved }: (typeof SHARED)[number] & Props) {
    const current = overview.templates[id];
    const [draft, setDraft] = useState<TemplateData>(current.template);
    const onError = (error: unknown) => toast.error(httpErrorToHuman(error));
    const done = (message: string) => (data: AdminOverview) => {
        onSaved(data);
        setDraft(data.templates[id].template);
        toast.success(message);
    };

    const saving = useMutation({ mutationFn: () => saveTemplate(id, draft), onSuccess: done('Template saved. Existing subdomains are updated in the background.'), onError });
    const resetting = useMutation({ mutationFn: () => resetTemplate(id), onSuccess: done('Template reset. Existing subdomains are updated in the background.'), onError });
    const changed = JSON.stringify(draft) !== JSON.stringify(current.template);

    return (
        <TitledGreyBox title={title}>
            <p className='sub:mb-4 sub:text-sm sub:text-muted-foreground'>{help}</p>
            <TemplateForm id={`sub-template-${id}`} value={draft} onChange={setDraft} />
            <div className='sub:mt-4 sub:flex sub:justify-end sub:gap-2'>
                {current.custom && (
                    <Button.Text isLoading={resetting.isPending} onClick={() => resetting.mutate()}>
                        Reset to built-in
                    </Button.Text>
                )}
                <Button disabled={!changed} isLoading={saving.isPending} onClick={() => saving.mutate()}>
                    Save
                </Button>
            </div>
        </TitledGreyBox>
    );
}
