import type { RouteSlotData } from '@pterodactyl/sdk';
import LimitBox from './LimitBox';

/** Shown on the server's Build tab, next to the other feature limits. */
export default function BuildLimit({ data }: { data: RouteSlotData }) {
    const id = Number(data.params.id ?? 0);

    return id > 0 ? (
        <div className='sub:mt-6'>
            <LimitBox server={id} title='Subdomains' />
        </div>
    ) : null;
}
