type DeepLinkDelivery = (url: string) => Promise<boolean>;

function normalizeProtocol(protocol: string | null | undefined): string | null {
    const normalized = protocol?.trim().replace(/:$/, '').toLowerCase();

    return normalized && /^[a-z][a-z0-9+.-]*$/.test(normalized) ? normalized : null;
}

export function findDeepLink(arguments_: string[], protocol: string): string | null {
    const normalized = normalizeProtocol(protocol);

    if (!normalized) {
        return null;
    }

    const prefix = `${normalized}://`;

    return arguments_.find((argument) => argument.toLowerCase().startsWith(prefix)) ?? null;
}

export class DeepLinkQueue {
    private protocol: string | null = null;
    private pendingArguments: string[][] = [];
    private pendingUrls: string[] = [];
    private ready = false;
    private flushing: Promise<void> | null = null;

    constructor(private readonly deliver: DeepLinkDelivery) {}

    configure(protocol: string | null | undefined, initialArguments: string[] = []): void {
        this.protocol = normalizeProtocol(protocol);

        if (!this.protocol) {
            return;
        }

        const pendingArguments = [...this.pendingArguments, initialArguments];
        const pendingUrls = [...this.pendingUrls];

        this.pendingArguments = [];
        this.pendingUrls = [];

        pendingUrls.forEach((url) => this.captureUrl(url));
        pendingArguments.forEach((arguments_) => this.captureArguments(arguments_));
    }

    captureArguments(arguments_: string[]): void {
        if (!this.protocol) {
            this.pendingArguments.push(arguments_);

            return;
        }

        const url = findDeepLink(arguments_, this.protocol);

        if (url) {
            this.captureUrl(url);
        }
    }

    captureUrl(url: string): void {
        if (!this.protocol) {
            this.pendingUrls.push(url);

            return;
        }

        if (findDeepLink([url], this.protocol) === null || this.pendingUrls.includes(url)) {
            return;
        }

        this.pendingUrls.push(url);

        if (this.ready) {
            void this.flush();
        }
    }

    async markReady(): Promise<void> {
        this.ready = true;

        await this.flush();
    }

    private flush(): Promise<void> {
        if (this.flushing) {
            return this.flushing;
        }

        this.flushing = this.deliverPending().finally(() => {
            this.flushing = null;
        });

        return this.flushing;
    }

    private async deliverPending(): Promise<void> {
        while (this.ready && this.pendingUrls.length > 0) {
            const delivered = await this.deliver(this.pendingUrls[0]);

            if (!delivered) {
                return;
            }

            this.pendingUrls.shift();
        }
    }
}
