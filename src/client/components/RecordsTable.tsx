import type { ReactNode } from 'react';
import { CopyOnClick, NamedIcon, Table, Tooltip, type TableColumn } from '@pterodactyl/sdk';
import type { SubdomainRecord } from '../api';

const tones = {
    primary: 'sub:bg-primary/15 sub:text-primary',
    muted: 'sub:bg-sunken sub:text-muted-foreground',
    danger: 'sub:bg-destructive/15 sub:text-destructive',
};

export function Badge({ tone = 'primary', children }: { tone?: keyof typeof tones; children: ReactNode }) {
    return <span className={`sub:rounded-full sub:px-2 sub:py-0.5 sub:text-xs sub:whitespace-nowrap ${tones[tone]}`}>{children}</span>;
}

export function Address({ hostname }: { hostname: string }) {
    return (
        <span className='sub:inline-flex sub:items-center sub:gap-2'>
            <CopyOnClick text={hostname}>
                <span className='sub:font-mono sub:text-foreground'>{hostname}</span>
            </CopyOnClick>
            <CopyOnClick text={hostname} showInNotification>
                <button type='button' aria-label={`Copy ${hostname}`} className='sub:cursor-pointer sub:text-muted-foreground sub:transition-colors sub:hover:text-foreground'>
                    <NamedIcon name='copy' size={14} />
                </button>
            </CopyOnClick>
        </span>
    );
}

export function PointsTo({ record }: { record: SubdomainRecord }) {
    return (
        <div className='sub:grid sub:gap-1'>
            <span className='sub:flex sub:flex-wrap sub:items-center sub:gap-2'>
                <span className='sub:font-mono sub:text-xs'>{record.target ? `${record.target}:${record.port}` : '-'}</span>
                {record.allocation_id === null && <Badge tone='muted'>Primary</Badge>}
                {record.srv && <Badge>SRV</Badge>}
                {record.proxied && <Badge>Proxied</Badge>}
                {record.error && (
                    <Tooltip content={record.error}>
                        <span className='sub:rounded-full sub:bg-destructive/15 sub:px-2 sub:py-0.5 sub:text-xs sub:text-destructive'>Error</span>
                    </Tooltip>
                )}
            </span>
            {record.allocation_missing && (
                <span className='sub:text-xs sub:text-warning'>Its allocation was removed from the server. It switches to the primary allocation at the next check.</span>
            )}
            {record.fallback_from && (
                <span className='sub:text-xs sub:text-warning'>{record.fallback_from} was removed from the server, so this now points to the primary allocation.</span>
            )}
        </div>
    );
}

interface Props {
    records: SubdomainRecord[];
    empty: string;
    columns?: TableColumn<SubdomainRecord>[];
    actions?: (record: SubdomainRecord) => ReactNode;
}

export default function RecordsTable({ records, empty, columns = [], actions }: Props) {
    return (
        <Table
            rows={records}
            keyOf={(record) => String(record.id)}
            emptyState={empty}
            columns={[
                { id: 'hostname', label: 'Address', cell: (record) => <Address hostname={record.hostname} /> },
                { id: 'target', label: 'Points to', cell: (record) => <PointsTo record={record} /> },
                { id: 'note', label: 'Note', cell: (record) => <span className='sub:text-sm sub:text-muted-foreground'>{record.note ?? ''}</span> },
                ...columns,
                { id: 'actions', label: '', cell: (record) => <div className='sub:flex sub:justify-end sub:gap-2'>{actions?.(record)}</div> },
            ]}
        />
    );
}
