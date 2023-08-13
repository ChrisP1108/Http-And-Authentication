class HttpReq {

    // HTTP Request Header Content-Type: Application/JSON

    static #headers = {
        "content-type" : "application/json"
    }

    // Failed To Fetch

    static #fetchFailed(err) {
        console.error(err);
        return { ok: false, status: 0, msg: err.toString() }
    }

    // Non 200 or 201 Status Code 

    static #statusError(res, url, method) {
        console.warn(`Server responded with a ${res.status} status code for a "${method}" request at "${url}"`);
        return { ok: false, status: res.status, msg: `Server responded with a ${res.status} status code.` };
    }

    // Success 200 or 201 Status Code

    static async #successRequest(res, getData = true) {
        const response = { ok: res.ok, status: res.status };

        if (getData) {
            response.response_data = await res.json();
        }
        return response;
    }

    // Arguments Error Handler

    static #argError(arg, dataNeeded, method) {
        const statusKeys = { ok: false, status: 0 }
        if (!arg) {
            const noParamMsg = `No parameter data provided for ${method} request.`;
            console.error(noParamMsg);
            return { ...statusKeys, msg: noParamMsg };
        } else if (dataNeeded) {
            const noDataParam = `${method} request must have an object parameter with "url" and "data" keys`;
            if (!arg.data || !arg.url) {
                console.error(noDataParam);
                return { ...statusKeys, msg: noDataParam };
            }
        } else return false
    }

    static async get() {
        const args = [...arguments];
        const output = [];
        const method = "GET";
        for (let i = 0; args.length > i; i++) {
            let response;
            response = this.#argError(args[i], false, method);
            if (!response) {
                try {
                    const res = await fetch(args[i]);
                    if (res.ok) {
                        response = await this.#successRequest(res, true);
                    } else {
                        response = this.#statusError(res, args[i], method);
                    }
                } catch (err) {
                    response = this.#fetchFailed(err);
                }
            }
            response.url = args[i];
            response.method = method;
            output.push(response);
        }
        return output;
    }

    static async post() {
        const args = [...arguments];
        const output = [];
        const method = "POST";
        for (let i = 0; args.length > i; i++) {
            let response;
            response = this.#argError(args[i], true, method);
            if (!response) {
                try {
                    const res = await fetch(args[i].url, {
                        method,
                        headers: this.#headers,
                        body: JSON.stringify(args[i].data)
                    });
                    if (res.ok) {
                        response = await this.#successRequest(res, true);
                    } else {
                        response = this.#statusError(res, args[i].url, method);
                    }
                } catch (err) {
                    response = this.#fetchFailed(err);
                }
            }
            response.request_data = args[i].data;
            response.url = args[i].url;
            response.method = method;
            output.push(response);
        }
        return output;
    }

    static async put() {
        const args = [...arguments];
        const output = [];
        const method = "PUT";
        for (let i = 0; args.length > i; i++) {
            let response;
            response = this.#argError(args[i], true, method);
            if (!response) {
                try {
                    const res = await fetch(args[i].url, {
                        method,
                        headers: this.#headers,
                        body: JSON.stringify(args[i].data)
                    });
                    if (res.ok) {
                        response = await this.#successRequest(res, false);
                    } else {
                        response = this.#statusError(res, args[i].url, method);
                    }
                } catch (err) {
                    response = this.#fetchFailed(err);
                }
            }
            response.request_data = args[i].data;
            response.url = args[i].url;
            response.method = method;
            output.push(response);
        }
        return output;
    }

    static async delete() {
        const args = [...arguments];
        const output = [];
        const method = "DELETE";
        for (let i = 0; args.length > i; i++) {
            let response;
            response = this.#argError(args[i], false, method);
            if (!response) {
                try {
                    const res = await fetch(args[i], {
                        method: "DELETE",
                    });
                    if (res.ok) {
                        response = await this.#successRequest(res, false);
                    } else {
                        response = this.#statusError(res, args[i], method);
                    }
                } catch (err) {
                    response = this.#fetchFailed(err);
                }
            }
            response.url = args[i];
            response.method = method;
            output.push(response);
        }
        return output;
    }
}