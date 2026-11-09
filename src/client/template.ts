import type { AddressType, TemplateData } from './api';

export const ADDRESS_OPTIONS: { value: AddressType; label: string }[] = [
    { value: 'ip', label: 'A + AAAA (AAAA when the node has IPv6)' },
    { value: 'a', label: 'A (IPv4 only)' },
    { value: 'aaaa', label: 'AAAA (IPv6 only)' },
    { value: 'cname', label: "CNAME to the node's hostname" },
];

export const TTL_OPTIONS = [
    { value: 1, label: 'Automatic' },
    { value: 60, label: '1 minute' },
    { value: 300, label: '5 minutes' },
    { value: 3600, label: '1 hour' },
    { value: 86400, label: '1 day' },
];

export const PLACEHOLDERS: { key: string; help: string }[] = [
    { key: '{name}', help: 'the chosen name, e.g. play' },
    { key: '{domain}', help: 'the domain, e.g. example.com' },
    { key: '{fqdn}', help: 'the full address, e.g. play.example.com' },
    { key: '{ip}', help: 'the address the records point to' },
    { key: '{port}', help: "the allocation's port" },
    { key: '{server}', help: "the server's name as a label, e.g. survival" },
    { key: '{server_name}', help: "the server's name" },
];

type RecordFields = Pick<TemplateData, 'address' | 'srv' | 'service' | 'protocol' | 'priority' | 'weight' | 'srv_port' | 'srv_target' | 'skip_port' | 'proxied'>;

const plain: RecordFields = { address: 'ip', srv: false, service: '_minecraft', protocol: 'tcp', priority: 0, weight: 5, srv_port: '{port}', srv_target: '{fqdn}', skip_port: null, proxied: false };

/** Starting points for the record fields. TTL and comment stay as they are. */
export const PRESETS: { value: string; label: string; help: string; fields: RecordFields }[] = [
    { value: 'minecraft-java', label: 'Minecraft: Java Edition', help: 'A records plus a _minecraft._tcp SRV record, so players join without a port. No SRV on 25565.', fields: { ...plain, srv: true, skip_port: 25565 } },
    { value: 'minecraft-bedrock', label: 'Minecraft: Bedrock Edition', help: 'A records only: Bedrock clients do not read SRV records. Players add the port unless it is 19132.', fields: plain },
    { value: 'source', label: "Source engine (CS2, TF2, Garry's Mod)", help: 'A records only: Source clients do not read SRV records. Players connect with name:port unless it is 27015.', fields: plain },
    { value: 'teamspeak', label: 'TeamSpeak 3', help: 'A records plus a _ts3._udp SRV record. No SRV on 9987.', fields: { ...plain, srv: true, service: '_ts3', protocol: 'udp', priority: 10, skip_port: 9987 } },
    { value: 'plain', label: 'Address only', help: 'A and AAAA records, nothing else.', fields: plain },
];

const EXAMPLE = { name: 'play', domain: 'example.com', fqdn: 'play.example.com', ip: '203.0.113.10', port: '25570', server: 'survival', server_name: 'Survival', hostname: 'node.example.net' };

const render = (text: string, values: Record<string, string>) => text.replace(/\{([a-z_]+)\}/g, (match, key: string) => values[key] ?? match);

/** The records the template creates for an example server, as zone file lines. */
export function previewRecords(template: TemplateData): string[] {
    const ttl = template.ttl === 1 ? 'auto' : String(template.ttl);
    const lines =
        template.address === 'cname'
            ? [`${EXAMPLE.fqdn}  ${ttl}  CNAME  ${EXAMPLE.hostname}`]
            : [
                  ...(template.address !== 'aaaa' ? [`${EXAMPLE.fqdn}  ${ttl}  A  ${EXAMPLE.ip}`] : []),
                  ...(template.address !== 'a' ? [`${EXAMPLE.fqdn}  ${ttl}  AAAA  (node IPv6)`] : []),
              ];

    if (template.srv && template.skip_port !== Number(EXAMPLE.port)) {
        const service = `_${template.service.replace(/^_+/, '') || 'minecraft'}`;
        lines.push(`${service}._${template.protocol}.${EXAMPLE.fqdn}  ${ttl}  SRV  ${template.priority} ${template.weight} ${render(template.srv_port, EXAMPLE)} ${render(template.srv_target, EXAMPLE)}`);
    }

    return lines;
}

export const commentPreview = (template: TemplateData) => render(template.comment, EXAMPLE);
