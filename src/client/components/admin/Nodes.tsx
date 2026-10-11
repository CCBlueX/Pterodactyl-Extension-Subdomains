import { useState } from 'react';
import { useMutation } from '@tanstack/react-query';
import { Button, Input, Select, TitledGreyBox, httpErrorToHuman, toast } from '@pterodactyl/sdk';
import { saveNode, type AdminNode, type AdminOverview, type NodeMode } from '../../api';

const MODES: { value: NodeMode; label: string }[] = [
    { value: 'ip', label: 'IP addresses' },
    { value: 'alias', label: 'Allocation alias' },
    { value: 'hostname', label: 'Hostname (CNAME)' },
];

const HELP: Record<NodeMode, string> = {
    ip: "Records point to the allocation's IP, or to the addresses set here.",
    alias: "Records point to the allocation's alias: an address, or a hostname as CNAME.",
    hostname: 'Records are a CNAME to this hostname, for nodes with changing IPs.',
};

interface Props {
    overview: AdminOverview;
    onSaved: (data: AdminOverview) => void;
}

export default function Nodes({ overview, onSaved }: Props) {
    return (
        <TitledGreyBox title='Node targets'>
            <p className='sub:mb-4 sub:text-sm sub:text-muted-foreground'>
                Where subdomains on each node point. Set addresses for nodes whose allocations listen on 0.0.0.0 or private addresses. The hostname is also the target of CNAME
                templates; without one the node's FQDN is used.
            </p>
            {overview.nodes.length === 0 ? (
                <p className='sub:text-sm sub:text-muted-foreground'>No nodes yet.</p>
            ) : (
                <div className='sub:grid sub:gap-4'>
                    {overview.nodes.map((node) => (
                        <NodeRow key={node.id} node={node} onSaved={onSaved} />
                    ))}
                </div>
            )}
        </TitledGreyBox>
    );
}

function NodeRow({ node, onSaved }: { node: AdminNode; onSaved: (data: AdminOverview) => void }) {
    const [mode, setMode] = useState(node.mode);
    const [ipv4, setIpv4] = useState(node.ipv4 ?? '');
    const [ipv6, setIpv6] = useState(node.ipv6 ?? '');
    const [hostname, setHostname] = useState(node.hostname ?? '');
    const save = useMutation({
        mutationFn: () => saveNode(node.id, { mode, ipv4: ipv4 || null, ipv6: ipv6 || null, hostname: hostname || null }),
        onSuccess: (data) => {
            onSaved(data);
            toast.success(`Saved ${node.name}. Existing subdomains are updated in the background.`);
        },
        onError: (error) => toast.error(httpErrorToHuman(error)),
    });
    const changed = mode !== node.mode || ipv4 !== (node.ipv4 ?? '') || ipv6 !== (node.ipv6 ?? '') || hostname !== (node.hostname ?? '');

    return (
        <div className='sub:grid sub:gap-2 sub:rounded-sm sub:bg-sunken sub:p-3'>
            <div className='sub:flex sub:items-center sub:justify-between sub:gap-3'>
                <div className='sub:min-w-0'>
                    <p className='sub:truncate sub:text-sm sub:text-foreground'>{node.name}</p>
                    <p className='sub:truncate sub:text-xs sub:text-muted-foreground'>{HELP[mode]}</p>
                </div>
                <Button size='small' className='sub:shrink-0' disabled={!changed} isLoading={save.isPending} onClick={() => save.mutate()}>
                    Save
                </Button>
            </div>
            <div className='sub:grid sub:gap-2 sub:sm:grid-cols-[12rem_minmax(0,1fr)_minmax(0,1fr)_minmax(0,1fr)]'>
                <Select id={`sub-node-${node.id}-mode`} value={mode} onChange={(value) => setMode(value as NodeMode)} options={MODES} />
                <Input aria-label={`${node.name} IPv4`} placeholder='IPv4 (automatic)' value={ipv4} onChange={(event) => setIpv4(event.currentTarget.value.trim())} />
                <Input aria-label={`${node.name} IPv6`} placeholder='IPv6 (none)' value={ipv6} onChange={(event) => setIpv6(event.currentTarget.value.trim())} />
                <Input aria-label={`${node.name} hostname`} placeholder={node.fqdn} value={hostname} onChange={(event) => setHostname(event.currentTarget.value.trim())} />
            </div>
        </div>
    );
}
