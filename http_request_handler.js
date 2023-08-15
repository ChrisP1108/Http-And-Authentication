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
        const reqMethods = ["GET", "POST", "PUT", "DELETE"];
        const headers = this.#headers(req.headers);
        if (!req) {
            const noParamMsg = `No parameter data provided for http request. Parameter must have an object with a "method" and "url" key. For "POST" and "PUT" requests, a "data" must also be provided.`;
            console.error(noParamMsg);
            return { ...statusKeys, msg: noParamMsg, request_headers: headers };
        } else if (!req.method) {
            const noMethodMsg = `No http request method parameter provided request.  Parameter must have an object with a "method" key.`;
            console.error(noMethodMsg);
            const response = { ...statusKeys, msg: noMethodMsg, request_headers: headers };
            if (req.url) {
                response.url = req.url;
            }
            if (req.data) {
                response.request_data = req.data;
            }
            return response;
        } else if (!reqMethods.includes(req.method.toUpperCase())) {
            const invalidMethod = `Http request not valid.  Request method must be "GET", "POST", "PUT", or "DELETE"`;
            console.error(invalidMethod);
            const response = { ...statusKeys, msg: invalidMethod, request_headers: headers };
            if (req.url) {
                response.url = req.url;
            }
            if (req.method) {
                response.method = `${req.method.toUpperCase()} (Invalid Request Type)`;
            }
            if (req.data) {
                response.request_data = req.data;
            }
            return response;
        } else if (!req.url) {
            const noUrlMsg = `No http request url parameter data provided for ${req.method} request.  Parameter must have an object with a "url" key.`;
            console.error(noUrlMsg);
            const response = { ...statusKeys, msg: noUrlMsg, request_headers: headers };
            if (req.method) {
                response.method = req.method;
            }
            if (req.data) {
                response.request_data = req.data;
            }
            return response;
        } else if (dataNeeded && !req.data) {
            const noDataParam = `${req.method} request must have an object parameter with a "data" key.`;
            console.error(noDataParam);
            const response = { ...statusKeys, msg: noDataParam, request_headers: headers };
            if (req.method) {
                response.method = req.method;
            }
            if (req.url) {
                response.url = req.url;
            }
            return response;
        } else return false;
    }

    // HTTP Request Handler

    static async init(reqs) {
        const output = [];
        if (!reqs || !reqs.length) {
            console.error('A parameter consisting of an array with at least one object containing "url" and "method" keys and values must be passed in to the init function.');
            return;
        }
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
                response.method = reqs[i].method.toUpperCase();
            }
            output.push(response);
        }
        return output;
    }
}
