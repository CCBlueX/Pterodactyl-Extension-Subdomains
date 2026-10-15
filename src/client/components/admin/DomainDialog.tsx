import { useState } from 'react';
import { useMutation } from '@tanstack/react-query';
import { Button, Dialog, Input, Label, Select, httpErrorToHuman, toast } from '@pterodactyl/sdk';
import { addDomain, testDomain, updateDomain, type AdminDomain, type AdminOverview, type NamingInput } from '../../api';
import { DEFAULT_PATTERN, MAX_LENGTH, MIN_LENGTH } from '../../naming';
import NamingFields from './NamingFields';

interface Props {
    domain: AdminDomain | null;
    overview: AdminOverview;
    onClose: () => void;
    onSaved: (data: AdminOverview) => void;
}

export default function DomainDialog({ domain, overview, onClose, onSaved }: Props) {
    const [name, setName] = useState(domain?.name ?? '');
    const [provider, setProvider] = useState(domain?.provider ?? overview.providers[0]?.id ?? 'cloudflare');
    const [initial] = useState<Record<string, string>>(() => Object.fromEntries(Object.entries(domain?.credentials ?? {}).map(([key, value]) => [key, value ?? ''])));
    const [credentials, setCredentials] = useState(initial);
    const [eggs, setEggs] = useState(domain?.eggs ?? []);
    const [naming, setNaming] = useState<NamingInput>({
        naming_mode: domain?.naming_mode ?? 'custom',
        naming_pattern: domain?.naming_pattern ?? DEFAULT_PATTERN,
        naming_length: domain?.naming_length ?? 8,
    });
    const [result, setResult] = useState<{ ok: boolean; message: string } | null>(null);
    const definition = overview.providers.find((item) => item.id === provider);
    const input = { name, provider, credentials, eggs, ...naming };

    const testing = useMutation({
        mutationFn: () => testDomain({ ...input, id: domain?.id }),
        onSuccess: (data) => setResult({ ok: true, message: data.message }),
        onError: (error) => setResult({ ok: false, message: httpErrorToHuman(error) }),
    });
    const saving = useMutation({
        // Unchanged credentials are left out, so saving only the eggs does not ask the provider again.
        mutationFn: () => (domain ? updateDomain(domain.id, changed ? { credentials, eggs, ...naming } : { eggs, ...naming }) : addDomain(input)),
        onSuccess: (data) => {
            onSaved(data);
            onClose();
            toast.success(domain ? `Saved ${domain.name}.` : `Added ${name}.`);
        },
        onError: (error) => setResult({ ok: false, message: httpErrorToHuman(error) }),
    });
    const changed = Object.keys({ ...initial, ...credentials }).some((key) => (credentials[key] ?? '') !== (initial[key] ?? ''));
    const pending = testing.isPending || saving.isPending;
    const missing = definition?.fields.some((field) => field.required && !credentials[field.key]?.trim() && !(field.secret && domain?.secrets.includes(field.key)));
    const invalid = missing || (naming.naming_mode === 'pattern' && naming.naming_pattern.trim() === '') || naming.naming_length < MIN_LENGTH || naming.naming_length > MAX_LENGTH;

    return (
        <Dialog open onClose={onClose} title={domain ? `Edit ${domain.name}` : 'Add domain'} preventExternalClose={pending}>
            <form
                className='sub:grid sub:gap-4'
                onSubmit={(event) => {
                    event.preventDefault();
                    saving.mutate();
                }}
            >
                <div className='sub:grid sub:gap-4 sub:sm:grid-cols-2'>
                    <div>
                        <Label htmlFor='sub-domain-name'>Domain or range</Label>
                        <Input
                            id='sub-domain-name'
                            placeholder='serv.example.com'
                            disabled={domain !== null}
                            value={name}
                            onChange={(event) => (setName(event.currentTarget.value.trim().toLowerCase()), setResult(null))}
                        />
                        <p className='sub:mt-1 sub:text-xs sub:text-muted-foreground'>e.g. play.example.com or serv.example.com</p>
                    </div>
                    <div>
                        <Label htmlFor='sub-domain-provider'>DNS provider</Label>
                        <Select
                            id='sub-domain-provider'
                            disabled={domain !== null}
                            value={provider}
                            onChange={(value) => (setProvider(String(value)), setCredentials({}), setResult(null))}
                            options={overview.providers.map((item) => ({ value: item.id, label: item.label }))}
                        />
                    </div>
                </div>
                {definition?.fields.map((field) => {
                    const saved = field.secret && domain?.secrets.includes(field.key);

                    return (
                        <div key={`${provider}-${field.key}`}>
                            <Label htmlFor={`sub-credential-${field.key}`}>{field.label}</Label>
                            <Input
                                id={`sub-credential-${field.key}`}
                                type={field.secret ? 'password' : 'text'}
                                autoComplete='off'
                                placeholder={saved ? 'Saved. Leave empty to keep it.' : field.placeholder || (field.required ? '' : 'Optional')}
                                value={credentials[field.key] ?? ''}
                                onChange={(event) => (setCredentials({ ...credentials, [field.key]: event.currentTarget.value }), setResult(null))}
                            />
                            <p className='sub:mt-1 sub:text-xs sub:text-muted-foreground'>
                                {field.help}
                                {provider === 'cloudflare' && !saved && ' Without one, the token in the extension settings is used.'}
                            </p>
                        </div>
                    );
                })}
                <NamingFields value={naming} domain={name} editing={domain !== null} onChange={setNaming} />
                <div>
                    <Label htmlFor='sub-domain-eggs'>Eggs</Label>
                    <Select
                        id='sub-domain-eggs'
                        multiple
                        placeholder='All eggs'
                        value={eggs}
                        onChange={(value) => setEggs(value.map(Number))}
                        options={overview.eggs.map((egg) => ({ value: egg.id, label: egg.name }))}
                    />
                    <p className='sub:mt-1 sub:text-xs sub:text-muted-foreground'>Only servers of these eggs can use this domain. Leave empty for every egg.</p>
                </div>
                {result && (
                    <p role='status' className={`sub:rounded-sm sub:px-3 sub:py-2 sub:text-sm ${result.ok ? 'sub:bg-success/10 sub:text-success' : 'sub:bg-destructive/10 sub:text-destructive'}`}>
                        {result.message}
                    </p>
                )}
                <Dialog.Footer>
                    <Button.Text disabled={pending || name === '' || missing} isLoading={testing.isPending} onClick={() => testing.mutate()}>
                        Test connection
                    </Button.Text>
                    <Button type='submit' disabled={pending || name === '' || invalid} isLoading={saving.isPending}>
                        {domain ? 'Save' : 'Add domain'}
                    </Button>
                </Dialog.Footer>
            </form>
        </Dialog>
    );
}
