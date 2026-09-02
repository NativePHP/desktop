var __awaiter = (this && this.__awaiter) || function (thisArg, _arguments, P, generator) {
    function adopt(value) { return value instanceof P ? value : new P(function (resolve) { resolve(value); }); }
    return new (P || (P = Promise))(function (resolve, reject) {
        function fulfilled(value) { try { step(generator.next(value)); } catch (e) { reject(e); } }
        function rejected(value) { try { step(generator["throw"](value)); } catch (e) { reject(e); } }
        function step(result) { result.done ? resolve(result.value) : adopt(result.value).then(fulfilled, rejected); }
        step((generator = generator.apply(thisArg, _arguments || [])).next());
    });
};
function normalizeProtocol(protocol) {
    const normalized = protocol === null || protocol === void 0 ? void 0 : protocol.trim().replace(/:$/, '').toLowerCase();
    return normalized && /^[a-z][a-z0-9+.-]*$/.test(normalized) ? normalized : null;
}
export function findDeepLink(arguments_, protocol) {
    var _a;
    const normalized = normalizeProtocol(protocol);
    if (!normalized) {
        return null;
    }
    const prefix = `${normalized}://`;
    return (_a = arguments_.find((argument) => argument.toLowerCase().startsWith(prefix))) !== null && _a !== void 0 ? _a : null;
}
export class DeepLinkQueue {
    constructor(deliver) {
        this.deliver = deliver;
        this.protocol = null;
        this.pendingArguments = [];
        this.pendingUrls = [];
        this.ready = false;
        this.flushing = null;
    }
    configure(protocol, initialArguments = []) {
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
    captureArguments(arguments_) {
        if (!this.protocol) {
            this.pendingArguments.push(arguments_);
            return;
        }
        const url = findDeepLink(arguments_, this.protocol);
        if (url) {
            this.captureUrl(url);
        }
    }
    captureUrl(url) {
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
    markReady() {
        return __awaiter(this, void 0, void 0, function* () {
            this.ready = true;
            yield this.flush();
        });
    }
    flush() {
        if (this.flushing) {
            return this.flushing;
        }
        this.flushing = this.deliverPending().finally(() => {
            this.flushing = null;
        });
        return this.flushing;
    }
    deliverPending() {
        return __awaiter(this, void 0, void 0, function* () {
            while (this.ready && this.pendingUrls.length > 0) {
                const delivered = yield this.deliver(this.pendingUrls[0]);
                if (!delivered) {
                    return;
                }
                this.pendingUrls.shift();
            }
        });
    }
}
