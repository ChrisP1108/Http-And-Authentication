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

    // Arguments Parser

    static #argsParser(args) {
        const output = [];
        args.forEach(item => {
            if (typeof item === "object" && item.length) {
                item.forEach(i => output.push(i))
            } else output.push(item);
        });
        return output;
    }

    // Arguments Error Handler

    static #argError(arg, dataNeeded, method) {
        const statusKeys = { ok: false, status: null }
        if (!arg || !arg.url) {
            const noParamMsg = `No parameter data provided for ${method} request.`;
            console.error(noParamMsg);
            return { ...statusKeys, msg: noParamMsg };
        } else if (dataNeeded && !arg.data) {
            const noDataParam = `${method} request must have an object parameter with "url" and "data" keys`;
            console.error(noDataParam);
            return { ...statusKeys, msg: noDataParam };
        } else return false;
    }

    static async get() {
        const args = this.#argsParser([...arguments]);
        const output = [];
        const method = "GET";
        for (let i = 0; args.length > i; i++) {
            let response;
            if (typeof args[i] === "string") {
                const str = args[i];
                args[i] = {};
                args[i].url = str;
            }
            response = this.#argError(args[i], false, method);
            if (!response) {
                const headers = this.#headers(args[i].headers);
                try {
                    const res = await fetch(args[i].url, {
                        method,
                        headers
                    });
                    if (res.ok) {
                        response = await this.#successRequest(res, headers);
                    } else {
                        response = this.#statusError(res, args[i], method, headers);
                    }
                } catch (err) {
                    response = this.#fetchFailed(err, headers);
                }
                response.request_data = null;
                response.url = args[i].url;
            }
            response.method = method;
            output.push(response);
        }
        return output;
    }

    static async post() {
        const args = this.#argsParser([...arguments]);
        const output = [];
        const method = "POST";
        for (let i = 0; args.length > i; i++) {
            let response;
            response = this.#argError(args[i], true, method);
            if (!response) {
                const headers = this.#headers(args[i].headers);
                try {
                    const res = await fetch(args[i].url, {
                        method,
                        headers,
                        body: JSON.stringify(args[i].data)
                    });
                    if (res.ok) {
                        response = await this.#successRequest(res, headers);
                    } else {
                        response = this.#statusError(res, args[i].url, method, headers);
                    }
                } catch (err) {
                    response = this.#fetchFailed(err, headers);
                }
                response.request_data = args[i].data;
                response.url = args[i].url;
            }
            response.method = method;
            output.push(response);
        }
        return output;
    }

    static async put() {
        const args = this.#argsParser([...arguments]);
        const output = [];
        const method = "PUT";
        for (let i = 0; args.length > i; i++) {
            let response;
            response = this.#argError(args[i], true, method);
            if (!response) {
                try {
                    const headers = this.#headers(args[i].headers);
                    const res = await fetch(args[i].url, {
                        method,
                        headers,
                        body: JSON.stringify(args[i].data)
                    });
                    if (res.ok) {
                        response = await this.#successRequest(res, headers);
                    } else {
                        response = this.#statusError(res, args[i].url, method, headers);
                    }
                } catch (err) {
                    response = this.#fetchFailed(err, headers);
                }
                response.request_data = args[i].data;
                response.url = args[i].url;
            }
            response.method = method;
            output.push(response);
        }
        return output;
    }

    static async delete() {
        const args = this.#argsParser([...arguments]);
        const output = [];
        const method = "DELETE";
        for (let i = 0; args.length > i; i++) {
            let response;
            if (typeof args[i] === "string") {
                const str = args[i];
                args[i] = {};
                args[i].url = str;
            }
            if (!response) {
                try {
                    const headers = this.#headers(args[i].headers);
                    const res = await fetch(args[i], {
                        method,
                        headers
                    });
                    if (res.ok) {
                        response = await this.#successRequest(res, headers);
                    } else {
                        response = this.#statusError(res, args[i], method, headers);
                    }
                } catch (err) {
                    response = this.#fetchFailed(err, headers);
                }
                response.url = args[i];
            }
            response.method = method;
            output.push(response);
        }
        return output;
    }
}
