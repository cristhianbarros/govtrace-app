// JSON-RPC de Stellar (https://developers.stellar.org/docs/data/apis/rpc):
// cualquier nodo público sirve; ninguno es de GovTrace.

export function stellarRpc(url, { fetch = globalThis.fetch, timeoutMs = 30_000 } = {}) {
    return {
        url,
        async call(method, params) {
            let response;
            try {
                response = await fetch(url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ jsonrpc: '2.0', id: 1, method, params }),
                    signal: AbortSignal.timeout(timeoutMs),
                });
            } catch (error) {
                throw new Error(`El RPC de Stellar (${url}) no respondió: ${error.message}`);
            }

            if (!response.ok) {
                throw new Error(`El RPC de Stellar (${url}) respondió ${response.status} a ${method}.`);
            }

            const body = await response.json();
            if (body.error) {
                throw new Error(`El RPC de Stellar rechazó ${method}: ${body.error.message}`);
            }
            return body.result;
        },
    };
}
