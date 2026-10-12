import { http } from '@pterodactyl/sdk';

export interface SubdomainRecord {
    id: number;
    name: string;
    hostname: string;
    domain: string;
    domain_id: number;
    target: string | null;
    port: number | null;
    allocation_id: number | null;
    allocation: string | null;
    allocation_missing: boolean;
    fallback_from: string | null;
    note: string | null;
    srv: boolean;
    proxied: boolean;
    error: string | null;
    created_at: string | null;
}

export interface Allocation {
    id: number;
    label: string;
    primary: boolean;
}

export type NamingMode = 'custom' | 'pattern' | 'random';

export interface ServerDomain {
    id: number;
    name: string;
    naming: NamingMode;
    /** Whether a new name can be rolled before creating. */
    random: boolean;
    /** The name a new subdomain gets on pattern and random domains, null when none is free or users type it. */
    preview: string | null;
}

export interface ServerSubdomains {
    records: SubdomainRecord[];
    domains: ServerDomain[];
    allocations: Allocation[];
    limit: number;
}

export type AddressType = 'ip' | 'a' | 'aaaa' | 'cname';

export interface TemplateData {
    address: AddressType;
    srv: boolean;
    service: string;
    protocol: 'tcp' | 'udp';
    priority: number;
    weight: number;
    srv_port: string;
    srv_target: string;
    skip_port: number | null;
    ttl: number;
    proxied: boolean;
    comment: string;
}

export interface ProviderField {
    key: string;
    label: string;
    secret: boolean;
    required: boolean;
    placeholder: string;
    help: string;
}

export interface ProviderDefinition {
    id: string;
    label: string;
    fields: ProviderField[];
}

export interface AdminDomain {
    id: number;
    name: string;
    provider: string;
    zone: string;
    enabled: boolean;
    eggs: number[];
    naming_mode: NamingMode;
    naming_pattern: string;
    naming_length: number;
    records: number;
    credentials: Record<string, string | null>;
    secrets: string[];
}

export type NodeMode = 'ip' | 'alias' | 'hostname';

export interface AdminNode {
    id: number;
    name: string;
    fqdn: string;
    mode: NodeMode;
    ipv4: string | null;
    ipv6: string | null;
    hostname: string | null;
}

export interface AdminEgg {
    id: number;
    name: string;
    minecraft: boolean;
    override: boolean;
}

export type SharedTemplate = 'default' | 'minecraft';

export interface AdminOverview {
    domains: AdminDomain[];
    nodes: AdminNode[];
    eggs: AdminEgg[];
    allowed_eggs: number[];
    providers: ProviderDefinition[];
    templates: Record<SharedTemplate, { template: TemplateData; custom: boolean }>;
}

export interface NamingInput {
    naming_mode: NamingMode;
    naming_pattern: string;
    naming_length: number;
}

export interface DomainInput extends NamingInput {
    name: string;
    provider: string;
    credentials: Record<string, string>;
    eggs: number[];
}

export interface AdminRecord extends SubdomainRecord {
    server: { id: number; name: string; identifier: string } | null;
    server_uuid: string;
}

export interface Paginated<T> {
    data: T[];
    meta: { pagination: { total: number; count: number; per_page: number; current_page: number; total_pages: number } };
}

export interface EggTemplate {
    egg: { id: number; name: string };
    override: TemplateData | null;
    inherited: { key: SharedTemplate; template: TemplateData };
    available: boolean;
}

export interface AdminServer {
    limit: number;
    default_limit: number;
    custom_limit: number | null;
    records: SubdomainRecord[];
}

const server = (identifier: string) => `/api/client/servers/${identifier}/extensions/subdomains`;
const admin = '/api/admin/extensions/subdomains';

export const keys = {
    server: (identifier: string) => ['subdomains', identifier] as const,
    admin: ['subdomains', 'admin'] as const,
    records: (search: string, page: number) => ['subdomains', 'admin', 'records', search, page] as const,
    allRecords: ['subdomains', 'admin', 'records'] as const,
    egg: (id: number) => ['subdomains', 'admin', 'egg', id] as const,
    adminServer: (id: number) => ['subdomains', 'admin', 'server', id] as const,
};

export const getSubdomains = async (identifier: string) => (await http.get<ServerSubdomains>(`${server(identifier)}/`)).data;
/** Leave the name out on pattern and random domains: the panel picks it. */
export const createSubdomain = async (identifier: string, input: { domain: number; name?: string; allocation: number | null; note: string }) =>
    (await http.post<ServerSubdomains>(`${server(identifier)}/`, input)).data;
export const updateSubdomain = async (identifier: string, id: number, input: { name?: string; allocation: number | null; note: string }) =>
    (await http.patch<ServerSubdomains>(`${server(identifier)}/${id}`, input)).data;
export const rollName = async (identifier: string, domain: number) => (await http.post<ServerSubdomains>(`${server(identifier)}/domains/${domain}/name`)).data;
export const deleteSubdomain = async (identifier: string, id: number) => (await http.delete<ServerSubdomains>(`${server(identifier)}/${id}`)).data;

export const getAdmin = async () => (await http.get<AdminOverview>(`${admin}/`)).data;
export const addDomain = async (input: DomainInput) => (await http.post<AdminOverview>(`${admin}/domains`, input)).data;
export const updateDomain = async (id: number, input: Partial<Pick<DomainInput, 'credentials' | 'eggs'> & NamingInput> & { enabled?: boolean }) =>
    (await http.patch<AdminOverview>(`${admin}/domains/${id}`, input)).data;
export const testDomain = async (input: DomainInput & { id?: number }) => (await http.post<{ zone: string; message: string }>(`${admin}/domains/test`, input)).data;
export const removeDomain = async (id: number) => (await http.delete<AdminOverview>(`${admin}/domains/${id}`)).data;
export const saveNode = async (id: number, input: Pick<AdminNode, 'mode' | 'ipv4' | 'ipv6' | 'hostname'>) => (await http.put<AdminOverview>(`${admin}/nodes/${id}`, input)).data;
export const saveAllowedEggs = async (eggs: number[]) => (await http.put<AdminOverview>(`${admin}/eggs`, { eggs })).data;
export const saveTemplate = async (key: SharedTemplate, template: TemplateData) => (await http.put<AdminOverview>(`${admin}/templates/${key}`, template)).data;
export const resetTemplate = async (key: SharedTemplate) => (await http.delete<AdminOverview>(`${admin}/templates/${key}`)).data;

export const getEggTemplate = async (id: number) => (await http.get<EggTemplate>(`${admin}/eggs/${id}/template`)).data;
export const saveEggTemplate = async (id: number, template: TemplateData) => (await http.put<EggTemplate>(`${admin}/eggs/${id}/template`, template)).data;
export const resetEggTemplate = async (id: number) => (await http.delete<EggTemplate>(`${admin}/eggs/${id}/template`)).data;

export const getRecords = async (search: string, page: number) => (await http.get<Paginated<AdminRecord>>(`${admin}/records`, { params: { search, page } })).data;
export const deleteRecord = async (id: number) => (await http.delete(`${admin}/records/${id}`)).data;

export const getAdminServer = async (id: number) => (await http.get<AdminServer>(`${admin}/servers/${id}`)).data;
export const saveLimit = async (id: number, limit: number | null) => (await http.put<AdminServer>(`${admin}/servers/${id}/limit`, { limit })).data;
export const resync = async (id: number) => (await http.post<AdminServer>(`${admin}/servers/${id}/sync`)).data;
