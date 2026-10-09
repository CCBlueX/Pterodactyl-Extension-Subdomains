import type { ReactNode } from 'react';
import { CopyOnClick, Table } from '@pterodactyl/sdk';
import type { SubdomainRecord } from '../api';

export default function RecordsTable({ records, empty, actions }: { records: SubdomainRecord[]; empty: string; actions?: (record: SubdomainRecord) => ReactNode }) {
    return (
        <Table
            rows={records}
            keyOf={(record) => String(record.id)}
            emptyState={empty}
            columns={[
                {
                    id: 'hostname',
                    label: 'Address',
                    cell: (record) => (
                        <CopyOnClick text={record.hostname}>
                            <span className='sub:font-mono sub:text-foreground'>{record.hostname}</span>
                        </CopyOnClick>
                    ),
                },
                {
                    id: 'target',
                    label: 'Points to',
                    cell: (record) => (
                        <span className='sub:flex sub:flex-wrap sub:items-center sub:gap-2'>
                            <span className='sub:font-mono sub:text-xs'>{record.target ? `${record.target}:${record.port}` : '-'}</span>
                            {record.srv && <span className='sub:rounded-full sub:bg-primary/15 sub:px-2 sub:py-0.5 sub:text-xs sub:text-primary'>SRV</span>}
                            {record.error && (
                                <span title={record.error} className='sub:rounded-full sub:bg-destructive/15 sub:px-2 sub:py-0.5 sub:text-xs sub:text-destructive'>
                                    Error
                                </span>
                            )}
                        </span>
                    ),
                },
                { id: 'actions', label: '', cell: (record) => <div className='sub:flex sub:justify-end'>{actions?.(record)}</div> },
            ]}
        />
    );
}
