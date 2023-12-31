export default class HttpReq {

    // HTTP Request Header Content-Type: Application/JSON And Additional Headers

    static #headers(type, additional) {
        const headers = { };
        const ct = "content-type"
        switch(type) {
            case 'json':
                headers[ct] = "application/json";
                break;
            case 'text':
                headers[ct] = "text/html; charset=utf-8";
                break;
            case 'file':
                break;
            default: 
                break;
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

    static async #successRequest(type, res, headers) {
        const response = { ok: res.ok, status: res.status, msg: 'Fulfilled', request_headers: headers };
        response.response_data = type === 'text' ? await res.text() : await res.json();
        return response;
    }

    // Request Error Handler

    static #reqError(req, dataNeeded, type) {
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
                response.request_data = req.data ?? null;
            }
            return response;
        } else if (!reqMethods.includes(req.method.toUpperCase())) {
            const invalidMethod = `Http request method of "${req.method.toUpperCase()}" not valid.  Request method must be "GET", "POST", "PUT", or "DELETE"`;
            console.error(invalidMethod);
            const response = { ...statusKeys, msg: invalidMethod, request_headers: headers };
            if (req.url) {
                response.url = req.url;
            }
            if (req.method) {
                response.method = `${req.method.toUpperCase()} (Invalid Request Method)`;
            }
            if (req.data) {
                response.request_data = req.data ?? null;
            }
            return response;
        } else if (!req.url) {
            const noUrlMsg = `No http request url parameter data provided for ${req.method} request.  Parameter must have an object with a "url" key.`;
            console.error(noUrlMsg);
            const response = { ...statusKeys, msg: noUrlMsg, request_headers: headers };
            if (req.method) {
                response.method = req.method.toUpperCase();
            }
            if (req.data) {
                response.request_data = req.data ?? null;
            }
            return response;
        } else if (dataNeeded && !req.data) {
            const noDataParam = `${req.method} request must have an object parameter with a "data" key.`;
            console.error(noDataParam);
            const response = { ...statusKeys, msg: noDataParam, request_headers: headers };
            if (req.method) {
                response.method = req.method.toUpperCase();
            }
            if (req.url) {
                response.url = req.url;
            }
            return response;
        } else if (type === "file" && !req.file) {
            const noFileParam = `File upload request must have an object parameter with a "file" key.`;
            console.error(noFileParam);
            const response = { ...statusKeys, msg: noFileParam, request_headers: headers, file: null };
            return response;
        } else return false;
    }

    // Check That Only One Parameter Is Present And Not Multiples

    static #multipleArguments() {
        if (arguments[0].length > 1) {
            console.error('Only one parameter may be passed in for http requests.  For multiple requests, pass in an array.  For a single "GET" request, pass in a string.');
            return true;
        } else return false;
    }

    // HTTP Request Handler

    static async #init(reqs, type) {
        const output = [];
        let nonArray = false;
        if (!reqs || !reqs.length || typeof reqs === "string") {
            if (reqs && typeof reqs === "string") {
                reqs = [{url: reqs, method: "GET" }];
                nonArray = true;
            } else if (typeof reqs !== "object"){
                console.error('A minimum of a string with an http url for a "GET" request must be provided as a paramater for the init function');
                return;
            }
        }
        if (reqs.length && reqs.every(r => typeof r === "string")) {
            reqs = reqs.map(req => ({url: req }));
        }
        if (reqs && typeof reqs === "object" && !reqs.length)  {
            nonArray = true; 
            reqs = [reqs]
        }
        if (type) {
            reqs = reqs.map(req => ({...req, type}));
        }
        for (let i = 0; reqs.length > i; i++) {
            let response;
            if (!reqs[i].method) {
                reqs[i].method = "GET";
            }
            if (!reqs[i].type) {
                reqs[i].type = "json";
            }
            const getOrDeleteReq = reqs[i].method.toLowerCase() === "get" || reqs[i].method.toLowerCase() === "delete";
            response = this.#reqError(reqs[i], getOrDeleteReq === false, type);
            if (!response) {
                const method = reqs[i].method.toUpperCase();
                const headers = this.#headers(reqs[i].type, reqs[i].headers);
                const reqParams = { method, headers };
                if (getOrDeleteReq === false && type === 'json') {
                    reqParams.body = JSON.stringify(reqs[i].data);
                }
                if (reqs[i].type === "file") {
                    const formData = new FormData();
                    formData.append('file', reqs[i].file);
                    reqParams.body = formData;
                }
                try {
                    const res = await fetch(reqs[i].url, reqParams);
                    if (res.ok) {
                        response = await this.#successRequest(reqs[i].type, res, headers);
                    } else {
                        response = this.#statusError(res, reqs.url, method, headers);
                    }
                } catch (err) {
                    response = this.#fetchFailed(err, headers);
                }
                response.request_name = reqs[i].name ?? null;
                response.request_data = reqs[i].data ?? null;
                response.url = reqs[i].url;
                response.method = reqs[i].method.toUpperCase();
            }
            output.push(response);
        }
        return nonArray ? output[0] : output;
    }

    // HTTP Request JSON Data

    static async json(reqs) {
        if (this.#multipleArguments(arguments)) {
            return;
        }
        return this.#init(reqs, 'json');
    }

    // HTTP Request HTML Data

    static async text(reqs) {
        if (this.#multipleArguments(arguments)) {
            return;
        }
        return this.#init(reqs, 'text');
    }

    // HTTP Request File Upload

    static async upload(reqs) {
        if (this.#multipleArguments(arguments)) {
            return;
        }
        return this.#init(reqs, 'file');
    }

    // HTTP Multiple Requests Any Type

    static async multi(reqs) {
        if (this.#multipleArguments(arguments)) {
            return;
        }
        return this.#init(reqs);
    }
}
