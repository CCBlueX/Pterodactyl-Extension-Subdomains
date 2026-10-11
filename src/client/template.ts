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
    { key: '{fqdn}', help: 'the full address, e.g. play.example.com' },
    { key: '{ip}', help: 'the address the records point to' },
    { key: '{port}', help: "the allocation's port" },
    { key: '{server_name}', help: "the server's name" },
];

const EXAMPLE = { name: 'play', fqdn: 'play.example.com', ip: '203.0.113.10', port: '25570', server_name: 'Survival', hostname: 'node.example.net' };

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
