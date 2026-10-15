import { Input, Label, Select } from '@pterodactyl/sdk';
import type { NamingInput, NamingMode } from '../../api';
import { DEFAULT_PATTERN, MAX_LENGTH, MIN_LENGTH, NAMING_MODES, exampleName, usesRandom } from '../../naming';

const HELP: Record<NamingMode, string> = {
    custom: 'Users type the name. Reserved names from the extension settings apply.',
    pattern: 'Placeholders: {username} (the owner), {server} (server name), {id} (short server id), {random}. Taken names get -2, -3 and so on.',
    random: 'Every subdomain gets random letters and digits. Users can roll a new one before creating it.',
};

interface Props {
    value: NamingInput;
    domain: string;
    editing: boolean;
    onChange: (value: NamingInput) => void;
}

/** How the left part of a subdomain is chosen, with a live example. */
export default function NamingFields({ value, domain, editing, onChange }: Props) {
    const example = `${exampleName(value)}.${domain || 'serv.example.com'}`;

    return (
        <div className='sub:grid sub:gap-2'>
            <div className='sub:flex sub:gap-4'>
                <div className='sub:min-w-0 sub:flex-1'>
                    <Label htmlFor='sub-domain-naming'>Names</Label>
                    <Select id='sub-domain-naming' value={value.naming_mode} onChange={(mode) => onChange({ ...value, naming_mode: mode as NamingMode })} options={NAMING_MODES} />
                </div>
                {usesRandom(value) && (
                    <div className='sub:w-28 sub:shrink-0'>
                        <Label htmlFor='sub-domain-length'>Characters</Label>
                        <Input
                            id='sub-domain-length'
                            inputMode='numeric'
                            value={value.naming_length ? String(value.naming_length) : ''}
                            onChange={(event) => /^\d{0,2}$/.test(event.currentTarget.value) && onChange({ ...value, naming_length: Number(event.currentTarget.value) })}
                        />
                    </div>
                )}
            </div>
            {value.naming_mode === 'pattern' && (
                <div>
                    <Label htmlFor='sub-domain-pattern'>Pattern</Label>
                    <Input
                        id='sub-domain-pattern'
                        className='sub:font-mono'
                        maxLength={100}
                        placeholder={DEFAULT_PATTERN}
                        value={value.naming_pattern}
                        onChange={(event) => onChange({ ...value, naming_pattern: event.currentTarget.value.toLowerCase() })}
                    />
                </div>
            )}
            <p className='sub:text-xs sub:text-muted-foreground'>
                {HELP[value.naming_mode]}
                {usesRandom(value) && ` Use ${MIN_LENGTH} to ${MAX_LENGTH} random characters.`}
                {editing && ' Existing subdomains keep their names.'}
            </p>
            <p className='sub:text-sm sub:text-muted-foreground'>
                Example: <span className='sub:font-mono sub:break-all sub:text-foreground'>{example}</span>
                {value.naming_mode === 'pattern' && ' (steve\'s server "Survival World")'}
            </p>
        </div>
    );
}
