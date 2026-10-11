import type { ReactNode } from 'react';
import { Input, Label, Select, Switch } from '@pterodactyl/sdk';
import type { TemplateData } from '../api';
import { ADDRESS_OPTIONS, PLACEHOLDERS, TTL_OPTIONS, commentPreview, previewRecords } from '../template';

interface Props {
    id: string;
    value: TemplateData;
    onChange: (value: TemplateData) => void;
    disabled?: boolean;
}

const digits = (value: string) => value.replace(/\D/g, '').slice(0, 5);

function Field({ id, label, help, children }: { id: string; label: string; help?: ReactNode; children: ReactNode }) {
    return (
        <div className='sub:min-w-0'>
            <Label htmlFor={id}>{label}</Label>
            {children}
            {help && <p className='sub:mt-1 sub:text-xs sub:text-muted-foreground'>{help}</p>}
        </div>
    );
}

export default function TemplateForm({ id, value, onChange, disabled }: Props) {
    const set = (changes: Partial<TemplateData>) => onChange({ ...value, ...changes });
    const ttlOptions = TTL_OPTIONS.some((option) => option.value === value.ttl) ? TTL_OPTIONS : [...TTL_OPTIONS, { value: value.ttl, label: `${value.ttl} seconds` }];

    return (
        <div className='sub:grid sub:gap-4'>
            <div className='sub:grid sub:gap-4 sub:sm:grid-cols-2'>
                <Field id={`${id}-address`} label='Address records' help='CNAME targets come from the node, see Nodes.'>
                    <Select id={`${id}-address`} disabled={disabled} value={value.address} onChange={(address) => set({ address: address as TemplateData['address'] })} options={ADDRESS_OPTIONS} />
                </Field>
                <Field id={`${id}-ttl`} label='TTL'>
                    <Select id={`${id}-ttl`} disabled={disabled} value={value.ttl} onChange={(ttl) => set({ ttl: Number(ttl) })} options={ttlOptions} />
                </Field>
            </div>
            <Switch
                label='Proxy through Cloudflare'
                description={value.srv ? 'Not possible together with an SRV record.' : 'Cloudflare only. Proxied records only carry website traffic, not game connections.'}
                checked={value.proxied && !value.srv}
                disabled={disabled || value.srv}
                onChange={(proxied) => set({ proxied })}
            />
            <Switch
                label='SRV record'
                description='Lets clients like Minecraft find the port, so players join with just the name.'
                checked={value.srv}
                disabled={disabled}
                onChange={(srv) => set({ srv, proxied: srv ? false : value.proxied })}
            />
            {value.srv && (
                <div className='sub:grid sub:gap-4 sub:rounded-sm sub:bg-sunken sub:p-4 sub:sm:grid-cols-3'>
                    <Field id={`${id}-service`} label='Service'>
                        <Input id={`${id}-service`} disabled={disabled} placeholder='_minecraft' value={value.service} onChange={(event) => set({ service: event.currentTarget.value.trim() })} />
                    </Field>
                    <Field id={`${id}-protocol`} label='Protocol'>
                        <Select
                            id={`${id}-protocol`}
                            disabled={disabled}
                            value={value.protocol}
                            onChange={(protocol) => set({ protocol: protocol as TemplateData['protocol'] })}
                            options={[
                                { value: 'tcp', label: 'TCP' },
                                { value: 'udp', label: 'UDP' },
                            ]}
                        />
                    </Field>
                    <Field id={`${id}-skip`} label='Skip on port' help='No SRV record on this port. Empty: always.'>
                        <Input
                            id={`${id}-skip`}
                            disabled={disabled}
                            inputMode='numeric'
                            placeholder='Never'
                            value={value.skip_port ?? ''}
                            onChange={(event) => set({ skip_port: digits(event.currentTarget.value) === '' ? null : Number(digits(event.currentTarget.value)) })}
                        />
                    </Field>
                    <Field id={`${id}-priority`} label='Priority'>
                        <Input id={`${id}-priority`} disabled={disabled} inputMode='numeric' value={value.priority} onChange={(event) => set({ priority: Number(digits(event.currentTarget.value)) })} />
                    </Field>
                    <Field id={`${id}-weight`} label='Weight'>
                        <Input id={`${id}-weight`} disabled={disabled} inputMode='numeric' value={value.weight} onChange={(event) => set({ weight: Number(digits(event.currentTarget.value)) })} />
                    </Field>
                    <Field id={`${id}-port`} label='Port'>
                        <Input id={`${id}-port`} disabled={disabled} placeholder='{port}' value={value.srv_port} onChange={(event) => set({ srv_port: event.currentTarget.value.trim() })} />
                    </Field>
                    <div className='sub:sm:col-span-3'>
                        <Field id={`${id}-target`} label='Target'>
                            <Input id={`${id}-target`} disabled={disabled} placeholder='{fqdn}' value={value.srv_target} onChange={(event) => set({ srv_target: event.currentTarget.value.trim() })} />
                        </Field>
                    </div>
                </div>
            )}
            <Field
                id={`${id}-comment`}
                label='Record comment (optional)'
                help={value.comment ? `Example: ${commentPreview(value)}. Cloudflare and Bunny DNS show it next to the record.` : 'Cloudflare and Bunny DNS show it next to the record.'}
            >
                <Input id={`${id}-comment`} disabled={disabled} maxLength={100} placeholder='{server_name}' value={value.comment} onChange={(event) => set({ comment: event.currentTarget.value })} />
            </Field>
            <div className='sub:grid sub:gap-3 sub:rounded-sm sub:border sub:border-border sub:p-4 sub:text-xs'>
                <div>
                    <p className='sub:mb-1 sub:font-medium sub:text-foreground'>Placeholders</p>
                    <ul className='sub:grid sub:gap-0.5 sub:text-muted-foreground sub:sm:grid-cols-2'>
                        {PLACEHOLDERS.map((placeholder) => (
                            <li key={placeholder.key}>
                                <code className='sub:font-mono sub:text-foreground'>{placeholder.key}</code> {placeholder.help}
                            </li>
                        ))}
                    </ul>
                    <p className='sub:mt-1 sub:text-muted-foreground'>They work in the SRV port, SRV target and comment.</p>
                </div>
                <div>
                    <p className='sub:mb-1 sub:font-medium sub:text-foreground'>Example for play.example.com on port 25570</p>
                    <pre className='sub:overflow-x-auto sub:font-mono sub:text-muted-foreground'>{previewRecords(value).join('\n')}</pre>
                </div>
            </div>
        </div>
    );
}
