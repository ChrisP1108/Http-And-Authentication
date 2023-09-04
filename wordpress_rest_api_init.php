<?php

    class WP_Register_Rest_API_Route {

        // All Routes registered through this are at {domainurl}/wp-json/api/{route}

        private $method;
        private $url;
        private $route;
        private $headers;
        private $cache_name;
        private $storage_interval;
        private $api_key;
        private $domain_restricted;
        private $permission_callback;
        private $custom_controller;

        public $public_data;

        // Convert time into milliseconds for frontend javascript

        public function convert_times_to_milliseconds($data) {
            $time_formatted_data = $data;
            $time_formatted_data['expiration_time'] = intval($data['expiration_time']) * 1000;
            $time_formatted_data['saved_time'] = intval($data['saved_time']) * 1000;
            return $time_formatted_data;
        }

        // Set URL parameters

        public function set_url_parameters($req_data) {
            if ($req_data) {

                // Get URL parameters

                $url_params = $req_data->get_params();
                $param_id = '';

                // If add id to url for id prior to adding parameters

                foreach($url_params as $param_name => $param_value) {
                    if ($param_name === 'param_id') {
                        $param_id .= $param_value;
                    }
                }

                // Parse URL parameters into string if parameters found

                $params_string = '';

                foreach($url_params as $param_name => $param_value) {

                    if (!empty($params_string)) {
                        $params_string .= '&';
                    }

                    // Check for parameter 'require_api_key' and its value to see where it needs to be inserted.  Either in the url parameter or header

                    if ($param_name === 'require_api_key_parameter' || $param_name === 'require_api_key_header') {
                        if ($param_name === 'require_api_key') {
                            $params_string .= $param_value . '=' . $this->api_key;
                        }

                    } else if ($param_name !== 'param_id') {
                        $params_string .= $param_name . '=' . urlencode($param_value);
                    }
                }

                // Combine url with param_id and params_string and return value

                return $this->url . $param_id . '?' . $params_string;

            } else return $this->url;
        }

        // Load from cache if data present

        public function cache_handler() {

            $load_cache_data = false;
            $save_cache_data = false;
            $data_set = [];

            // Check that cache_name exists and check for cache and set variables accordingly

            if ($this->cache_name !== null && $this->method === 'GET') {
                $cache_data = get_transient($this->cache_name);

                if ($cache_data !== false) {
                    $parsed_cache = json_decode($cache_data, true);

                    if(isset($parsed_cache['expiration_time']) && isset($parsed_cache['data']) && isset($parsed_cache['saved_time'])) {
                        $current_time = time();
                        $expiration_time = $parsed_cache['expiration_time'];

                        if ($current_time > $expiration_time) {
                            $save_cache_data = true;
                            delete_transient($this->cache_name);
                        } else {
                            $load_cache_data = true;
                            $data_set = $this->convert_times_to_milliseconds($parsed_cache);
                            $data_set['api_call_made'] = false;
                        }
                    } else {
                        $save_cache_data = true;
                        delete_transient($this->cache_name);
                    }

                } else {
                    $save_cache_data = true;
                    delete_transient($this->cache_name);
                }
            }

            return [
                'load_cache' => $load_cache_data,
                'save_cache' => $save_cache_data,
                'data' => $data_set
            ];
        }

        // Set headers

        public function set_headers($req) {

            $headers = [];

            // Check if there are headers passed in on the backend

            if ($this->headers) {
                forEach($this->headers as $head_name => $head_value) {
                    $headers[$head_name] = $head_value;
                }
            }

            // Add headers from the frontend

            $req_headers = $req->get_headers();

            if (!empty($req_headers)) {
                forEach($req_headers as $head_name => $head_value) {
                    if ($head_name !== "host") {
                        $headers[$head_name] = $head_value[0];
                    }
                }
            }

            return $headers;
        }

        // Make HTTP Request.  Throw error

        public function make_http_request($request_data) {
            $request_url_params = $request_data['url_params'] ?? null;
            $request_headers = $request_data['headers'] ?? null;
            $request_body = $request_data['body'] ?? null;

            $response = null;

            if ($request_url_params) {

                // Make HTTP Request

                switch($this->method) {
                    case 'GET':
                        $response = wp_safe_remote_get($request_url_params, ['headers' => $request_headers]);
                        break;
                    case 'POST':
                        $response = wp_remote_post($request_url_params, ['headers' => $request_headers, 'body' => $request_body]);
                        break;
                    case 'PUT':
                        $response = wp_remote_request($request_url_params, ['method' => 'PUT', 'headers' => $request_headers, 'body' => $request_body]);
                        break;
                    case 'DELETE':
                        $response = wp_remote_request($request_url_params, ['method' => 'DELETE', 'headers' => $request_headers]);
                        break;
                }

                // Check for a valid response.  Throw error if something went wrong

                if (is_wp_error($response) || wp_remote_retrieve_response_code($response) > 201) {
                    $res = $response['response'];
                    return new WP_Error('api_error', $res['message'], array('status' => $res['code']));
                }

            }

            $data_body = wp_remote_retrieve_body($response);

            return [
                'expiration_time' => time() + $this->storage_interval,
                'data' => json_decode($data_body),
                'saved_time' => time()
            ];
        }

        // General handler

        public function initialize($request) {

            // If custom_controller property was made, run it here and bypass the rest of the code in the initialize method

            if ($this->custom_controller !== null && is_callable($this->custom_controller)) {
                $callback_data = $this->public_data;
                $callback_data['request'] = $request;
                return call_user_func($this->custom_controller, $callback_data);
            }

            // Check if data for GET request is already saved in the cache.  Otherwise make new HTTP request and save file.

            $cache_info = $this->cache_handler();
            $load_cache = $cache_info['load_cache'];
            $save_cache = $cache_info['save_cache'];

            // Initialize parsed data variable

            $parsed_data = $cache_info['data'];

            // Make API Call if request is not GET or if data is not being loaded from the cache

            if (!$load_cache) {

                // Initialize headers 

                $headers = $this->set_headers($request);

                // Get URL parameters

                $request_with_params = $this->set_url_parameters($request);

                // Parse Request Body

                $body_params = $request->get_body_params();
                
                // Make HTTP request to url and get data.  Error thrown if something went wrong

                $data = $this->make_http_request([
                    'url_params' => $request_with_params,
                    'headers' => $headers,
                    'body' => $body_params
                ]);

                // If $save_cache is true, save the data to the cache

                if ($save_cache && $this->cache_name !== null) {
                    set_transient($this->cache_name, json_encode($data), intval($this->storage_interval) - 1);
                }

                // Convert times to milliseconds for frontend javascript

                $parsed_data = $this->convert_times_to_milliseconds($data);

                $parsed_data['api_call_made'] = true;
            } 

            // Set current time time loaded

            $parsed_data['time_loaded'] = time() * 1000;

            // Return data

            return rest_ensure_response($parsed_data);
        }

        // Check that request is coming from same domain to prevent external URL usage

        public function permissions_check($request) {

            if ($this->domain_restricted === true) {

                $referer = $request->get_header('referer');
            
                $allowed_domain = parse_url(get_site_url(), PHP_URL_HOST);
                $referer_domain = parse_url($referer, PHP_URL_HOST);
            
                if ($referer_domain === $allowed_domain) {
                    return true;
                } else {
                    return new WP_Error('domain_not_allowed', 'Access from external domains is not allowed.', array('status' => 403));
                }
            } 

            $callback_data = $this->public_data;
            $callback_data['request'] = $request;

            if ($this->permission_callback !== null && is_callable($this->permission_callback)) {
                return call_user_func($this->permission_callback, $callback_data);
            } else return true;
        }

        // Register route with Wordpress REST API

        public function wp_rest_api_register_route() {
            register_rest_route('api', $this->route, [
                'methods' => $this->method,
                'permission_callback' => [$this, 'permissions_check'],
                'callback' => [$this, 'initialize'],
            ]);
        }

        // Check that request method is valid.  If not, set to GET by default

        private function method_parse($method) {

            $req_method = strtoupper($method);
            
            switch($req_method) {
                case 'GET':
                    return 'GET';
                case 'POST':
                    return 'POST';
                case 'PUT':
                    return 'PUT';
                case 'DELETE':
                    return 'DELETE';
                default:
                    return 'GET';
            }
        }

        // Constructor.  Runs when class is instantiated.

        public function __construct($parameters) {
            $this->method = $this->method_parse($parameters['method'] ?? 'GET');
            $this->url = strtolower($parameters['url']) ?? null;
            $this->route = strtolower($parameters['route']) ?? null;
            $this->headers = $parameters['headers'] ?? null;
            $this->cache_name = $parameters['cache_name'] ?? null;
            $this->storage_interval = intval($parameters['storage_interval']) ?? 60;
            $this->api_key = $parameters['api_key'] ?? null;
            $this->domain_restricted = $parameters['domain_restricted'] ?? false;
            $this->permission_callback = $parameters['permission_callback'] ?? null;
            $this->custom_controller = $parameters['custom_controller'] ?? null;

            // Set data array for custom controller and permission callback usage

            $this->public_data = [
                'method' => $this->method,
                'url' => $this->url,
                'route' => $this->route,
                'headers' => $this->headers,
                'cache_name' => $this->cache_name,
                'storage_interval' => $this->storage_interval,
                'api_key' => $this->api_key,
                'domain_restricted' => $this->domain_restricted
            ];

            // Check that there are sufficient parameters.  Otherwise throw error.

            $insufficient_params = false;

            if (!$this->route) {
                $insufficient_params = true;
            }

            if (!$this->custom_controller && !$this->url) {
                $insufficient_params = true;
            }

            if (!$insufficient_params) {
                add_action('rest_api_init', [$this, 'wp_rest_api_register_route']);
            } else {
                return new WP_Error('not_enough_information', 'The WP_Register_Rest_API_Route must have route key and a value in an associative array passed in as a minimum parameter. 
                    If a custom controller is not used, it must also include a url key and value in the associative array as well.', array('status' => 500));
            }
        }
    }

    // TESTER API KEY

    // define("TESTER_API_KEY", "abe91hg62gf82");	

    // new WP_Register_Rest_API_Route([
    //     'method' => 'GET',
    //     'url' => 'https://echo.zuplo.io/',
    //     'route' => 'tester',
    //     'headers' => ['test_head' => 'test_value'],
    //     'cache_name' => 'api_test',
    //     'storage_interval' => 90,
    //     'api_key' => TESTER_API_KEY,
    //     'domain_restricted' => false,
    //     'permission_callback' =>  function($data) {
    //         return true;
    //     },
    //     'custom_controller' => null
    // ]);
