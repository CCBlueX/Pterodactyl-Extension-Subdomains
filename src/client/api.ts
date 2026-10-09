import { http } from '@pterodactyl/sdk';

export interface SubdomainRecord {
    id: number;
    hostname: string;
    domain: string;
    target: string | null;
    port: number | null;
    srv: boolean;
    error: string | null;
    created_at: string | null;
}

export interface ServerSubdomains {
    records: SubdomainRecord[];
    domains: { id: number; name: string }[];
    limit: number;
}

export interface AdminOverview {
    domains: { id: number; name: string; enabled: boolean; records: number }[];
    nodes: { id: number; name: string; fqdn: string; ipv4: string | null; ipv6: string | null }[];
}

export interface AdminServer {
    limit: number;
    custom_limit: number | null;
    records: SubdomainRecord[];
}

const server = (identifier: string) => `/api/client/servers/${identifier}/extensions/subdomains`;
const admin = '/api/admin/extensions/subdomains';

export const keys = {
    server: (identifier: string) => ['subdomains', identifier] as const,
    admin: ['subdomains', 'admin'] as const,
    adminServer: (id: number) => ['subdomains', 'admin', 'server', id] as const,
};

export const getSubdomains = async (identifier: string) => (await http.get<ServerSubdomains>(`${server(identifier)}/`)).data;
export const createSubdomain = async (identifier: string, domain: number, name: string) =>
    (await http.post<ServerSubdomains>(`${server(identifier)}/`, { domain, name })).data;
export const deleteSubdomain = async (identifier: string, id: number) => (await http.delete<ServerSubdomains>(`${server(identifier)}/${id}`)).data;

export const getAdmin = async () => (await http.get<AdminOverview>(`${admin}/`)).data;
export const addDomain = async (name: string) => (await http.post<AdminOverview>(`${admin}/domains`, { name })).data;
export const toggleDomain = async (id: number, enabled: boolean) => (await http.patch<AdminOverview>(`${admin}/domains/${id}`, { enabled })).data;
export const removeDomain = async (id: number) => (await http.delete<AdminOverview>(`${admin}/domains/${id}`)).data;
export const saveNode = async (id: number, ipv4: string, ipv6: string) =>
    (await http.put<AdminOverview>(`${admin}/nodes/${id}`, { ipv4: ipv4 || null, ipv6: ipv6 || null })).data;
export const getAdminServer = async (id: number) => (await http.get<AdminServer>(`${admin}/servers/${id}`)).data;
export const saveLimit = async (id: number, limit: number | null) => (await http.put<AdminServer>(`${admin}/servers/${id}/limit`, { limit })).data;
export const resync = async (id: number) => (await http.post<AdminServer>(`${admin}/servers/${id}/sync`)).data;
