import type { TemplateData } from '../api';

const standard: TemplateData = {
    address: 'ip',
    srv: false,
    service: '_minecraft',
    protocol: 'tcp',
    priority: 0,
    weight: 5,
    srv_port: '{port}',
    srv_target: '{fqdn}',
    skip_port: null,
    ttl: 1,
    proxied: false,
    comment: '',
};

/** Matches the built-in templates of the backend. */
export const Template = { standard, minecraft: { ...standard, srv: true, skip_port: 25565 } satisfies TemplateData };
