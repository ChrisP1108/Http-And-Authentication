class HttpReq {

    // HTTP Request Header Content-Type: Application/JSON And Additional Headers

    static #headers(additional) {
        const headers = {
            "content-type" : "application/json"
        }
        if (additional && typeof additional === "object") {
            Object.entries(additional).forEach(entry => {
                headers[entry[0]] = entry[1];
            });
        }
        return headers;
    }

    // Failed To Fetch

    static #fetchFailed(err, headers) {
        console.error(err);
        return { ok: false, status: null, msg: err.toString(), request_headers: headers }
    }

    // Non 200 or 201 Status Code 

    static #statusError(res, url, method, headers) {
        console.warn(`Server responded with a ${res.status} status code for a "${method}" request at "${url}"`);
        return { ok: false, status: res.status, msg: `Server responded with a ${res.status} status code.`, request_headers: headers };
    }

    // Success 200 or 201 Status Code

    static async #successRequest(res, headers) {
        const response = { ok: res.ok, status: res.status, msg: 'Success', request_headers: headers };
        response.response_data = await res.json();
        return response;
    }

    // Request Error Handler

    static #reqError(req, dataNeeded) {
        const statusKeys = { ok: false, status: null };
        if (!req) {
            const noParamMsg = `No parameter data provided for http request. Parameter must have an object with a "method" and "url" key. For "POST" and "PUT" requests, a "data" must also be provided.`;
            console.error(noParamMsg);
            return { ...statusKeys, msg: noParamMsg };
        } else if (!req.method) {
            const noMethodMsg = `No http request method parameter provided request.  Parameter must have an object with a "method" key.`;
            console.error(noMethodMsg);
            return { ...statusKeys, msg: noMethodMsg };
        } else if (!req.url) {
            const noUrlMsg = `No http request url parameter data provided for ${req.method} request.  Parameter must have an object with a "url" key.`;
            console.error(noUrlMsg);
            return { ...statusKeys, msg: noUrlMsg };
        } else if (dataNeeded && !req.data) {
            const noDataParam = `${req.method} request must have an object parameter with a "data" key.`;
            console.error(noDataParam);
            return { ...statusKeys, msg: noDataParam };
        } else return false;
    }

    // HTTP Request Handler

    static async init(reqs) {
        const output = [];
        for (let i = 0; reqs.length > i; i++) {
            let response;
            const getOrDeleteReq = reqs[i].method.toLowerCase() === "get" || reqs[i].method.toLowerCase() === "delete";
            response = this.#reqError(reqs[i], getOrDeleteReq === false);
            if (!response) {
                const method = reqs[i].method.toUpperCase();
                const headers = this.#headers(reqs[i].headers);
                const reqParams = { method, headers };
                if (getOrDeleteReq === false) {
                    reqParams.body = JSON.stringify(reqs[i].data);
                }
                try {
                    const res = await fetch(reqs[i].url, reqParams);
                    if (res.ok) {
                        response = await this.#successRequest(res, headers);
                    } else {
                        response = this.#statusError(res, reqs.url, method, headers);
                    }
                } catch (err) {
                    response = this.#fetchFailed(err, headers);
                }
                response.request_data = reqs[i].data;
                response.url = reqs[i].url;
            }
            response.method = reqs[i].method;
            output.push(response);
        }
        return output;
    }
}
